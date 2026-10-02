<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Exceptions\OpportunityCapacityExceededException;
use App\Exceptions\PathCapacityExceededException;
use App\Exceptions\ProgramCapacityExceededException;
use App\Models\InboxNotification;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Models\VolunteerRegistration;
use App\Services\Inbox\InboxRegistrationDecisions;
use App\Services\PathRegistrationService;
use App\Services\ProgramRegistrationService;
use App\Services\VolunteerRegistrationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * إجراءات صف جدول التنبيهات في لوحة الإدارة (روابط + قبول/رفض للتسجيلات).
 */
final class InboxNotificationRecordActions
{
    /**
     * @return list<Action>
     */
    public static function contextual(): array
    {
        return [
            Action::make('inbox_open_portal_or_public')
                ->label(fn (InboxNotification $record): string => self::inboxOpenLabel(auth()->user(), $record))
                ->icon(fn (InboxNotification $record): string => self::inboxOpenIcon(auth()->user(), $record))
                ->color('gray')
                ->url(fn (InboxNotification $record): ?string => self::inboxOpenUrl(auth()->user(), $record))
                ->visible(fn (InboxNotification $record): bool => self::inboxOpenUrl(auth()->user(), $record) !== null)
                ->openUrlInNewTab(fn (InboxNotification $record): bool => self::portalUrl(auth()->user(), $record) === null),

            Action::make('inbox_approve_program_registration')
                ->label('قبول')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('تأكيد قبول التسجيل')
                ->modalDescription('سيتم قبول طلب التسجيل في البرنامج.')
                ->visible(fn (InboxNotification $record): bool => self::canApproveProgramRegistration($record))
                ->action(function (InboxNotification $record): void {
                    $reg = self::programRegistration($record);
                    if ($reg === null) {
                        return;
                    }
                    Gate::authorize('approve', $reg);
                    try {
                        app(ProgramRegistrationService::class)->approve($reg, auth()->user());
                        $record->markAsRead();
                        Notification::make()->title('تم قبول التسجيل')->success()->send();
                    } catch (ProgramCapacityExceededException) {
                        Notification::make()->title('البرنامج بلغ طاقته القصوى')->danger()->send();
                    }
                }),

            Action::make('inbox_reject_program_registration')
                ->label('رفض')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (InboxNotification $record): bool => self::canRejectProgramRegistration($record))
                ->form([
                    Textarea::make('rejected_reason')
                        ->label('سبب الرفض (اختياري)')
                        ->rows(3),
                ])
                ->action(function (InboxNotification $record, array $data): void {
                    $reg = self::programRegistration($record);
                    if ($reg === null) {
                        return;
                    }
                    Gate::authorize('reject', $reg);
                    app(ProgramRegistrationService::class)->reject($reg, $data['rejected_reason'] ?? null);
                    $record->markAsRead();
                    Notification::make()->title('تم رفض التسجيل')->warning()->send();
                }),

            Action::make('inbox_approve_path_registration')
                ->label('قبول')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('تأكيد قبول التسجيل في المسار')
                ->visible(fn (InboxNotification $record): bool => self::canApprovePathRegistration($record))
                ->action(function (InboxNotification $record): void {
                    $reg = self::pathRegistration($record);
                    if ($reg === null) {
                        return;
                    }
                    Gate::authorize('approve', $reg);
                    try {
                        app(PathRegistrationService::class)->approve($reg, auth()->user());
                        $record->markAsRead();
                        Notification::make()->title('تم قبول التسجيل')->success()->send();
                    } catch (PathCapacityExceededException) {
                        Notification::make()->title('المسار بلغ طاقته القصوى')->danger()->send();
                    }
                }),

            Action::make('inbox_reject_path_registration')
                ->label('رفض')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (InboxNotification $record): bool => self::canRejectPathRegistration($record))
                ->form([
                    Textarea::make('rejected_reason')
                        ->label('سبب الرفض (اختياري)')
                        ->rows(3),
                ])
                ->action(function (InboxNotification $record, array $data): void {
                    $reg = self::pathRegistration($record);
                    if ($reg === null) {
                        return;
                    }
                    Gate::authorize('reject', $reg);
                    app(PathRegistrationService::class)->reject($reg, $data['rejected_reason'] ?? null);
                    $record->markAsRead();
                    Notification::make()->title('تم رفض التسجيل')->warning()->send();
                }),

            Action::make('inbox_approve_volunteer_registration')
                ->label('قبول')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('تأكيد قبول التسجيل التطوعي')
                ->visible(fn (InboxNotification $record): bool => self::canApproveVolunteerRegistration($record))
                ->action(function (InboxNotification $record): void {
                    $reg = self::volunteerRegistration($record);
                    if ($reg === null) {
                        return;
                    }
                    Gate::authorize('approve', $reg);
                    try {
                        app(VolunteerRegistrationService::class)->approve($reg, auth()->user());
                        $record->markAsRead();
                        Notification::make()->title('تم قبول التسجيل')->success()->send();
                    } catch (OpportunityCapacityExceededException) {
                        Notification::make()->title('الفرصة بلغت طاقتها القصوى')->danger()->send();
                    }
                }),

            Action::make('inbox_reject_volunteer_registration')
                ->label('رفض')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (InboxNotification $record): bool => self::canRejectVolunteerRegistration($record))
                ->form([
                    Textarea::make('rejected_reason')
                        ->label('سبب الرفض (اختياري)')
                        ->rows(3),
                ])
                ->action(function (InboxNotification $record, array $data): void {
                    $reg = self::volunteerRegistration($record);
                    if ($reg === null) {
                        return;
                    }
                    Gate::authorize('reject', $reg);
                    app(VolunteerRegistrationService::class)->reject(
                        $reg,
                        auth()->user(),
                        $data['rejected_reason'] ?? null,
                    );
                    $record->markAsRead();
                    Notification::make()->title('تم رفض التسجيل')->warning()->send();
                }),
        ];
    }

    /**
     * إجراءات الصف نفسها في مركز التنبيهات وفي ويدجت «آخر التنبيهات» بالرئيسية.
     *
     * @return list<Action>
     */
    public static function filamentStandardRowActions(): array
    {
        return [
            ...self::contextual(),

            Action::make('mark_read')
                ->label('تحديد كمقروء')
                ->icon('heroicon-o-check')
                ->visible(fn (InboxNotification $record): bool => $record->read_at === null)
                ->action(function (InboxNotification $record): void {
                    Gate::authorize('update', $record);
                    $record->markAsRead();
                }),

            Action::make('mark_unread')
                ->label('تحديد كغير مقروء')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (InboxNotification $record): bool => $record->read_at !== null)
                ->action(function (InboxNotification $record): void {
                    Gate::authorize('update', $record);
                    $record->forceFill(['read_at' => null])->save();
                }),

            Action::make('view_details')
                ->label('عرض التفاصيل')
                ->icon('heroicon-o-eye')
                ->modalHeading('تفاصيل التنبيه')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('إغلاق')
                ->modalContent(fn (InboxNotification $record): HtmlString => new HtmlString(
                    '<div class="fi-prose space-y-3 text-sm">'
                    .'<p><strong>العنوان:</strong> '.e($record->title).'</p>'
                    .'<p><strong>الرسالة:</strong><br>'.e($record->message ?? '').'</p>'
                    .'<p><strong>النوع:</strong> '.e($record->type?->arabicLabel() ?? '').'</p>'
                    .'<p><strong>المرسل:</strong> '.e($record->sender?->name ?? '—').'</p>'
                    .'<p><strong>تاريخ الإرسال:</strong> '.e($record->created_at?->format('Y/m/d H:i') ?? '').'</p>'
                    .'</div>'
                )),
        ];
    }

    public static function publicUrl(InboxNotification $record): ?string
    {
        return InboxRegistrationDecisions::publicUrl($record);
    }

    /**
     * رابط البوابة للمستفيد (برنامج / مسار / تطوع / شهادة) عند وجود سياق مناسب.
     */
    public static function portalUrl(?User $user, InboxNotification $record): ?string
    {
        return InboxRegistrationDecisions::portalUrl($user, $record);
    }

    public static function inboxOpenUrl(?User $user, InboxNotification $record): ?string
    {
        return InboxRegistrationDecisions::openUrl($user, $record);
    }

    public static function inboxOpenLabel(?User $user, InboxNotification $record): string
    {
        return InboxRegistrationDecisions::openLabel($user, $record);
    }

    public static function inboxOpenIcon(?User $user, InboxNotification $record): string
    {
        return InboxRegistrationDecisions::openIcon($user, $record);
    }

    public static function programRegistration(InboxNotification $record): ?ProgramRegistration
    {
        return InboxRegistrationDecisions::programRegistration($record);
    }

    public static function pathRegistration(InboxNotification $record): ?PathRegistration
    {
        return InboxRegistrationDecisions::pathRegistration($record);
    }

    public static function volunteerRegistration(InboxNotification $record): ?VolunteerRegistration
    {
        return InboxRegistrationDecisions::volunteerRegistration($record);
    }

    public static function canApproveProgramRegistration(InboxNotification $record): bool
    {
        return InboxRegistrationDecisions::canApproveProgramRegistration($record);
    }

    public static function canRejectProgramRegistration(InboxNotification $record): bool
    {
        return InboxRegistrationDecisions::canRejectProgramRegistration($record);
    }

    public static function canApprovePathRegistration(InboxNotification $record): bool
    {
        return InboxRegistrationDecisions::canApprovePathRegistration($record);
    }

    public static function canRejectPathRegistration(InboxNotification $record): bool
    {
        return InboxRegistrationDecisions::canRejectPathRegistration($record);
    }

    public static function canApproveVolunteerRegistration(InboxNotification $record): bool
    {
        return InboxRegistrationDecisions::canApproveVolunteerRegistration($record);
    }

    public static function canRejectVolunteerRegistration(InboxNotification $record): bool
    {
        return InboxRegistrationDecisions::canRejectVolunteerRegistration($record);
    }
}
