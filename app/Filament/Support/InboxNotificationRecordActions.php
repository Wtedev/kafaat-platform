<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\RegistrationStatus;
use App\Exceptions\OpportunityCapacityExceededException;
use App\Exceptions\PathCapacityExceededException;
use App\Exceptions\ProgramCapacityExceededException;
use App\Models\InboxNotification;
use App\Models\LearningPath;
use App\Models\News;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
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

    private static function ctx(InboxNotification $record): ?array
    {
        $c = $record->context;

        return is_array($c) && isset($c['resource'], $c['id']) ? $c : null;
    }

    public static function publicUrl(InboxNotification $record): ?string
    {
        $c = self::ctx($record);
        if ($c === null) {
            return null;
        }

        $id = (int) $c['id'];

        return match ($c['resource']) {
            'news' => ($m = News::find($id)) ? route('public.news.show', $m) : null,
            'training_program' => ($m = TrainingProgram::find($id)) ? route('public.programs.show', $m) : null,
            'learning_path' => ($m = LearningPath::find($id)) ? route('public.paths.show', $m) : null,
            'volunteer_opportunity' => ($m = VolunteerOpportunity::find($id)) ? route('public.volunteering.show', $m) : null,
            default => null,
        };
    }

    /**
     * رابط البوابة للمستفيد (برنامج / مسار / تطوع / شهادة) عند وجود سياق مناسب.
     */
    public static function portalUrl(?User $user, InboxNotification $record): ?string
    {
        if ($user === null) {
            return null;
        }

        $c = self::ctx($record);
        if ($c === null) {
            return null;
        }

        $id = (int) $c['id'];

        return match ($c['resource']) {
            'training_program' => ($p = TrainingProgram::find($id)) !== null
                && $user->programRegistrations()->where('training_program_id', $p->id)->exists()
                ? route('portal.programs.show', $p)
                : null,
            'learning_path' => ($path = LearningPath::find($id)) !== null
                && PathRegistration::query()
                    ->where('user_id', $user->id)
                    ->where('learning_path_id', $path->id)
                    ->exists()
                ? route('portal.paths.show', $path)
                : null,
            'program_registration' => ($r = ProgramRegistration::find($id)) !== null
                && (int) $r->user_id === (int) $user->id
                && $r->trainingProgram !== null
                ? route('portal.programs.show', $r->trainingProgram)
                : null,
            'path_registration' => ($r = PathRegistration::find($id)) !== null
                && (int) $r->user_id === (int) $user->id
                && $r->learningPath !== null
                ? route('portal.paths.show', $r->learningPath)
                : null,
            'volunteer_opportunity' => ($opp = VolunteerOpportunity::find($id)) !== null
                && VolunteerRegistration::query()
                    ->where('user_id', $user->id)
                    ->where('opportunity_id', $opp->id)
                    ->exists()
                ? route('portal.volunteering')
                : null,
            'volunteer_registration' => ($r = VolunteerRegistration::find($id)) !== null
                && (int) $r->user_id === (int) $user->id
                ? route('portal.volunteering')
                : null,
            'certificate' => route('portal.certificates'),
            default => null,
        };
    }

    public static function inboxOpenUrl(?User $user, InboxNotification $record): ?string
    {
        if ($user !== null && $user->isPortalUser()) {
            return self::portalUrl($user, $record) ?? self::publicUrl($record);
        }

        return self::publicUrl($record);
    }

    public static function inboxOpenLabel(?User $user, InboxNotification $record): string
    {
        if ($user !== null && $user->isPortalUser() && self::portalUrl($user, $record) !== null) {
            $resource = (self::ctx($record) ?? [])['resource'] ?? '';

            return match ($resource) {
                'training_program', 'program_registration' => 'عرض البرنامج',
                'learning_path', 'path_registration' => 'عرض المسار',
                'volunteer_opportunity', 'volunteer_registration' => 'عرض التطوع',
                'certificate' => 'شهاداتي',
                default => 'عرض في البوابة',
            };
        }

        return 'عرض على الموقع';
    }

    public static function inboxOpenIcon(?User $user, InboxNotification $record): string
    {
        if ($user !== null && $user->isPortalUser() && self::portalUrl($user, $record) !== null) {
            return 'heroicon-o-home';
        }

        return 'heroicon-o-globe-alt';
    }

    public static function programRegistration(InboxNotification $record): ?ProgramRegistration
    {
        $c = self::ctx($record);

        return ($c !== null && $c['resource'] === 'program_registration')
            ? ProgramRegistration::find((int) $c['id'])
            : null;
    }

    public static function pathRegistration(InboxNotification $record): ?PathRegistration
    {
        $c = self::ctx($record);

        return ($c !== null && $c['resource'] === 'path_registration')
            ? PathRegistration::find((int) $c['id'])
            : null;
    }

    public static function volunteerRegistration(InboxNotification $record): ?VolunteerRegistration
    {
        $c = self::ctx($record);

        return ($c !== null && $c['resource'] === 'volunteer_registration')
            ? VolunteerRegistration::find((int) $c['id'])
            : null;
    }

    public static function canApproveProgramRegistration(InboxNotification $record): bool
    {
        $reg = self::programRegistration($record);

        return $reg !== null
            && $reg->status === RegistrationStatus::Pending
            && Gate::allows('approve', $reg);
    }

    public static function canRejectProgramRegistration(InboxNotification $record): bool
    {
        $reg = self::programRegistration($record);

        return $reg !== null
            && $reg->status === RegistrationStatus::Pending
            && Gate::allows('reject', $reg);
    }

    public static function canApprovePathRegistration(InboxNotification $record): bool
    {
        $reg = self::pathRegistration($record);

        return $reg !== null
            && $reg->status === RegistrationStatus::Pending
            && Gate::allows('approve', $reg);
    }

    public static function canRejectPathRegistration(InboxNotification $record): bool
    {
        $reg = self::pathRegistration($record);

        return $reg !== null
            && $reg->status === RegistrationStatus::Pending
            && Gate::allows('reject', $reg);
    }

    public static function canApproveVolunteerRegistration(InboxNotification $record): bool
    {
        $reg = self::volunteerRegistration($record);

        return $reg !== null
            && $reg->status === RegistrationStatus::Pending
            && Gate::allows('approve', $reg);
    }

    public static function canRejectVolunteerRegistration(InboxNotification $record): bool
    {
        $reg = self::volunteerRegistration($record);

        return $reg !== null
            && $reg->status === RegistrationStatus::Pending
            && Gate::allows('reject', $reg);
    }
}
