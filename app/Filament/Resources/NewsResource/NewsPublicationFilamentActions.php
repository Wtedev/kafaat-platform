<?php

namespace App\Filament\Resources\NewsResource;

use App\Filament\Resources\NewsResource;
use App\Models\News;
use App\Models\User;
use App\Services\News\NewsPublicationService;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

final class NewsPublicationFilamentActions
{
    /**
     * حقل موعد الجدولة — يُستخدم في إنشاء خبر مجدول وأزرار الجدولة.
     */
    public static function schedulePublishAtPicker(): DateTimePicker
    {
        return DateTimePicker::make('publish_at')
            ->label('موعد الظهور للعامة')
            ->required()
            ->native(false)
            ->seconds(false)
            ->timezone(config('app.timezone'))
            ->minDate(fn (): Carbon => now()->addMinute())
            ->helperText(NewsResource::publicationTimezoneHelper())
            ->rules([
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    try {
                        $at = $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
                    } catch (\Throwable) {
                        return;
                    }
                    if ($at->lessThanOrEqualTo(now())) {
                        $fail(NewsPublicationService::schedulePublishMustBeFutureMessage());
                    }
                },
            ]);
    }

    /**
     * صفحة العرض: مجموعة «النشر والجدولة» — شريط أسفل المحتوى.
     *
     * @param  Closure(): News  $resolveNews
     * @return array<int, ActionGroup>
     */
    public static function viewPagePublicationGroup(Closure $resolveNews): array
    {
        if (! auth()->user()?->can('manage_news')) {
            return [];
        }

        return [
            self::publicationDropdown($resolveNews, [
                'publish' => 'vp_publish_now',
                'schedule' => 'vp_schedule_publish',
                'draft' => 'vp_move_to_draft',
            ]),
        ];
    }

    /**
     * أزرار النشر والجدولة في بطاقة «إدارة الخبر» بصفحة التعديل — نصية وواضحة دون مسار جدولة مكرر.
     *
     * @param  Closure(): News  $resolveNews
     * @return array<int, Action>
     */
    public static function editPagePublicationActions(Closure $resolveNews, ?Closure $after = null): array
    {
        if (! auth()->user()?->can('manage_news')) {
            return [];
        }

        $publish = Action::make('news_edit_publish_now')
            ->label('نشر الآن')
            ->button()
            ->icon('heroicon-o-megaphone')
            ->color('primary')
            ->visible(function () use ($resolveNews): bool {
                $record = $resolveNews();

                return $record->isDraft() || $record->isScheduled();
            })
            ->requiresConfirmation()
            ->modalHeading('نشر الخبر الآن')
            ->modalDescription('سيتم جعل الخبر ظاهراً للعامة فوراً، وقد يُرسل تنبيه الوارد حسب سياسة المنصة (مرة واحدة لكل دورة نشر).')
            ->modalSubmitActionLabel('نشر الآن')
            ->action(function () use ($resolveNews): void {
                self::runPublishNow($resolveNews());
            });

        $schedule = Action::make('news_edit_schedule')
            ->label(function () use ($resolveNews): string {
                return $resolveNews()->isScheduled()
                    ? 'تعديل موعد الجدولة'
                    : 'جدولة النشر';
            })
            ->button()
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->visible(function () use ($resolveNews): bool {
                $record = $resolveNews();

                return $record->isDraft() || $record->isScheduled();
            })
            ->modalHeading(function () use ($resolveNews): string {
                return $resolveNews()->isScheduled()
                    ? 'تعديل موعد الجدولة'
                    : 'جدولة النشر';
            })
            ->form([
                self::schedulePublishAtPicker(),
            ])
            ->action(function (array $data) use ($resolveNews): void {
                self::runSchedule($resolveNews(), $data);
            });

        $draft = Action::make('news_edit_move_draft')
            ->label(function () use ($resolveNews): string {
                $record = $resolveNews();

                return $record->isScheduled()
                    ? 'إلغاء الجدولة'
                    : 'تحويل لمسودة';
            })
            ->button()
            ->icon('heroicon-o-document')
            ->color('gray')
            ->visible(function () use ($resolveNews): bool {
                $record = $resolveNews();

                return $record->isPublished() || $record->isScheduled();
            })
            ->requiresConfirmation()
            ->modalHeading(function () use ($resolveNews): string {
                $record = $resolveNews();

                return $record->isScheduled()
                    ? 'إلغاء الجدولة'
                    : 'إرجاع الخبر إلى مسودة';
            })
            ->modalDescription(function () use ($resolveNews): string {
                $record = $resolveNews();

                return $record->isScheduled()
                    ? 'سيتم إلغاء الموعد المجدول ولن يظهر الخبر للجمهور حتى يُنشر من جديد.'
                    : 'لن يظهر الخبر للجمهور حتى يُنشر من جديد.';
            })
            ->modalSubmitActionLabel('تأكيد')
            ->action(function () use ($resolveNews): void {
                self::runMoveToDraft($resolveNews());
            });

        if ($after instanceof Closure) {
            $publish->after($after);
            $schedule->after($after);
            $draft->after($after);
        }

        return [$publish, $schedule, $draft];
    }

    /**
     * @param  array{publish: string, schedule: string, draft: string}  $names
     */
    private static function publicationDropdown(Closure $resolveNews, array $names): ActionGroup
    {
        return ActionGroup::make([
            self::publishNowHeaderAction($resolveNews, $names['publish']),
            self::scheduleHeaderAction($resolveNews, $names['schedule']),
            self::moveToDraftHeaderAction($resolveNews, $names['draft']),
        ])
            ->label('النشر والجدولة')
            ->icon('heroicon-o-megaphone')
            ->button()
            ->dropdownPlacement('bottom-end');
    }

    private static function publishNowHeaderAction(Closure $resolveNews, string $name, ?Closure $after = null): Action
    {
        $action = Action::make($name)
            ->label('نشر الآن')
            ->icon('heroicon-o-megaphone')
            ->color('success')
            ->visible(function () use ($resolveNews): bool {
                $record = $resolveNews();

                return ($record->isDraft() || $record->isScheduled()) && (auth()->user()?->can('manage_news') ?? false);
            })
            ->requiresConfirmation()
            ->modalHeading('نشر الخبر الآن')
            ->modalDescription('سيتم جعل الخبر ظاهراً للعامة فوراً، وقد يُرسل تنبيه الوارد حسب سياسة المنصة (مرة واحدة لكل دورة نشر).')
            ->modalSubmitActionLabel('نشر الآن')
            ->action(function () use ($resolveNews): void {
                self::runPublishNow($resolveNews());
            });

        if ($after instanceof Closure) {
            $action->after($after);
        }

        return $action;
    }

    private static function scheduleHeaderAction(Closure $resolveNews, string $name, ?Closure $after = null): Action
    {
        $action = Action::make($name)
            ->label(function () use ($resolveNews): string {
                return $resolveNews()->isScheduled()
                    ? 'تعديل موعد الجدولة'
                    : 'جدولة النشر';
            })
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->visible(function () use ($resolveNews): bool {
                $record = $resolveNews();

                return ($record->isDraft() || $record->isScheduled()) && (auth()->user()?->can('manage_news') ?? false);
            })
            ->form([
                self::schedulePublishAtPicker(),
            ])
            ->action(function (array $data) use ($resolveNews): void {
                self::runSchedule($resolveNews(), $data);
            });

        if ($after instanceof Closure) {
            $action->after($after);
        }

        return $action;
    }

    private static function moveToDraftHeaderAction(Closure $resolveNews, string $name, ?Closure $after = null): Action
    {
        $action = Action::make($name)
            ->label(function () use ($resolveNews): string {
                $record = $resolveNews();

                return $record->isScheduled()
                    ? 'إلغاء الجدولة'
                    : 'تحويل لمسودة';
            })
            ->icon('heroicon-o-document')
            ->color('gray')
            ->visible(function () use ($resolveNews): bool {
                $record = $resolveNews();

                return ($record->isPublished() || $record->isScheduled()) && (auth()->user()?->can('manage_news') ?? false);
            })
            ->requiresConfirmation()
            ->modalHeading(function () use ($resolveNews): string {
                $record = $resolveNews();

                return $record->isScheduled()
                    ? 'إلغاء الجدولة'
                    : 'إرجاع الخبر إلى مسودة';
            })
            ->modalDescription(function () use ($resolveNews): string {
                $record = $resolveNews();

                return $record->isScheduled()
                    ? 'سيتم إلغاء الموعد المجدول ولن يظهر الخبر للجمهور حتى يُنشر من جديد.'
                    : 'لن يظهر الخبر للجمهور حتى يُنشر من جديد.';
            })
            ->modalSubmitActionLabel('تأكيد')
            ->action(function () use ($resolveNews): void {
                self::runMoveToDraft($resolveNews());
            });

        if ($after instanceof Closure) {
            $action->after($after);
        }

        return $action;
    }

    private static function runPublishNow(News $record): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $hadSentBefore = $record->published_notification_sent_at !== null;
        app(NewsPublicationService::class)->publishNow($record, $actor);
        $record->refresh();
        $sentThisTime = $record->published_notification_sent_at !== null && ! $hadSentBefore;
        $notification = Notification::make()
            ->title('تم نشر الخبر الآن.')
            ->success();

        if ($sentThisTime) {
            $notification->body('تم إرسال تنبيه الوارد.');
        }

        $notification->send();
    }

    private static function runMoveToDraft(News $record): void
    {
        $wasScheduled = $record->isScheduled();
        app(NewsPublicationService::class)->moveToDraft($record);
        Notification::make()
            ->title($wasScheduled
                ? 'تم إلغاء الجدولة وتحويل الخبر إلى مسودة.'
                : 'تم تحويل الخبر إلى مسودة.')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function runSchedule(News $record, array $data): void
    {
        $at = Carbon::parse($data['publish_at'])->timezone(config('app.timezone'));
        app(NewsPublicationService::class)->schedule($record, $at);
        $formatted = $at->format('Y/m/d H:i');
        Notification::make()
            ->title('تم جدولة الخبر للظهور في '.$formatted.' ('.NewsResource::platformTimezoneLabel().').')
            ->success()
            ->send();
    }
}
