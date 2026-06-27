<?php

namespace App\Filament\Support;

use App\Enums\UserActivityAction;
use App\Models\PathAttendance;
use App\Models\PathRegistration;
use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\VolunteerRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class UserTechnicalLogService
{
    /**
     * @return Collection<int, array{id: string, occurred_at: Carbon, category: string, title: string, detail: string}>
     */
    public static function tableRecords(User $user): Collection
    {
        return self::timeline($user)
            ->values()
            ->map(fn (array $entry, int $index): array => [
                'id' => 'log-'.$index,
                'occurred_at' => $entry['occurred_at'],
                'category' => $entry['category'],
                'title' => $entry['title'],
                'detail' => $entry['detail'],
            ]);
    }

    /**
     * @return Collection<int, array{occurred_at: Carbon, category: string, title: string, detail: string}>
     */
    public static function timeline(User $user): Collection
    {
        $entries = collect();

        if (Schema::hasTable('user_activity_logs')) {
            UserActivityLog::query()
                ->where('user_id', $user->id)
                ->orderByDesc('occurred_at')
                ->limit(500)
                ->get()
                ->each(function (UserActivityLog $log) use ($entries): void {
                    $action = $log->action;
                    $entries->push([
                        'occurred_at' => $log->occurred_at ?? $log->created_at ?? now(),
                        'category' => $action instanceof UserActivityAction ? $action->category() : 'نشاط',
                        'title' => $log->title,
                        'detail' => (string) ($log->detail ?? ''),
                    ]);
                });
        }

        self::appendLegacyEntries($user, $entries);

        return $entries
            ->unique(fn (array $entry): string => $entry['title'].'|'.$entry['occurred_at']->toIso8601String().'|'.$entry['detail'])
            ->sortByDesc(fn (array $entry): int => $entry['occurred_at']->getTimestamp())
            ->values();
    }

    /**
     * @param  Collection<int, array{occurred_at: Carbon, category: string, title: string, detail: string}>  $entries
     */
    private static function appendLegacyEntries(User $user, Collection $entries): void
    {
        if (! self::hasLoggedAction($entries, UserActivityAction::AccountCreated->title()) && $user->created_at !== null) {
            $entries->push([
                'occurred_at' => $user->created_at,
                'category' => UserActivityAction::AccountCreated->category(),
                'title' => UserActivityAction::AccountCreated->title(),
                'detail' => 'تم إنشاء حساب المستفيد في المنصة.',
            ]);
        }

        if (! self::hasLoggedAction($entries, UserActivityAction::EmailVerified->title()) && $user->email_verified_at !== null) {
            $entries->push([
                'occurred_at' => $user->email_verified_at,
                'category' => UserActivityAction::EmailVerified->category(),
                'title' => UserActivityAction::EmailVerified->title(),
                'detail' => 'أكّد المستفيد بريده الإلكتروني.',
            ]);
        }

        if ($user->notification_prefs_set_at !== null) {
            $title = $user->notify_email
                ? UserActivityAction::EmailNotificationsEnabled->title()
                : UserActivityAction::EmailNotificationsDisabled->title();

            if (! self::hasLoggedAction($entries, $title)) {
                $entries->push([
                    'occurred_at' => $user->notification_prefs_set_at,
                    'category' => UserActivityAction::EmailNotificationsEnabled->category(),
                    'title' => $title,
                    'detail' => '',
                ]);
            }
        }

        if (! self::hasLoggedAction($entries, UserActivityAction::Login->title()) && $user->last_login_at !== null) {
            $entries->push([
                'occurred_at' => $user->last_login_at,
                'category' => UserActivityAction::Login->category(),
                'title' => UserActivityAction::Login->title(),
                'detail' => 'آخر دخول مسجّل قبل تفعيل السجل التفصيلي.',
            ]);
        }

        self::appendLegacyAttendance($user, $entries);
        self::appendLegacyRegistrations($user, $entries);
    }

    /**
     * @param  Collection<int, array{occurred_at: Carbon, category: string, title: string, detail: string}>  $entries
     */
    private static function appendLegacyRegistrations(User $user, Collection $entries): void
    {
        ProgramRegistration::query()
            ->where('user_id', $user->id)
            ->with('trainingProgram')
            ->orderBy('created_at')
            ->get()
            ->each(function (ProgramRegistration $registration) use ($entries): void {
                $title = UserActivityAction::ProgramRegistration->title();
                $detail = '«'.($registration->trainingProgram?->title ?? 'برنامج تدريبي').'»';

                if (self::hasDetailEntry($entries, $title, $detail)) {
                    return;
                }

                $entries->push([
                    'occurred_at' => $registration->created_at ?? now(),
                    'category' => UserActivityAction::ProgramRegistration->category(),
                    'title' => $title,
                    'detail' => $detail,
                ]);
            });

        PathRegistration::query()
            ->where('user_id', $user->id)
            ->with('learningPath')
            ->orderBy('created_at')
            ->get()
            ->each(function (PathRegistration $registration) use ($entries): void {
                $title = UserActivityAction::PathRegistration->title();
                $detail = '«'.($registration->learningPath?->title ?? 'مسار تدريبي').'»';

                if (self::hasDetailEntry($entries, $title, $detail)) {
                    return;
                }

                $entries->push([
                    'occurred_at' => $registration->created_at ?? now(),
                    'category' => UserActivityAction::PathRegistration->category(),
                    'title' => $title,
                    'detail' => $detail,
                ]);
            });

        VolunteerRegistration::query()
            ->where('user_id', $user->id)
            ->with('opportunity')
            ->orderBy('created_at')
            ->get()
            ->each(function (VolunteerRegistration $registration) use ($entries): void {
                $title = UserActivityAction::VolunteerRegistration->title();
                $detail = '«'.($registration->opportunity?->title ?? 'فرصة تطوعية').'»';

                if (self::hasDetailEntry($entries, $title, $detail)) {
                    return;
                }

                $entries->push([
                    'occurred_at' => $registration->created_at ?? now(),
                    'category' => UserActivityAction::VolunteerRegistration->category(),
                    'title' => $title,
                    'detail' => $detail,
                ]);
            });
    }

    /**
     * @param  Collection<int, array{occurred_at: Carbon, category: string, title: string, detail: string}>  $entries
     */
    private static function appendLegacyAttendance(User $user, Collection $entries): void
    {
        ProgramAttendance::query()
            ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id))
            ->where('notes', 'تسجيل حضور ذاتي')
            ->with('registration.trainingProgram')
            ->get()
            ->each(function (ProgramAttendance $attendance) use ($entries): void {
                $program = $attendance->registration?->trainingProgram;
                $label = $program?->title ?? 'برنامج تدريبي';
                $detail = 'برنامج: «'.$label.'» — '.($attendance->training_date?->format('Y/m/d') ?? '');

                if (self::hasDetailEntry($entries, UserActivityAction::AttendanceCheckIn->title(), $detail)) {
                    return;
                }

                $entries->push([
                    'occurred_at' => $attendance->updated_at ?? $attendance->created_at ?? now(),
                    'category' => UserActivityAction::AttendanceCheckIn->category(),
                    'title' => UserActivityAction::AttendanceCheckIn->title(),
                    'detail' => $detail,
                ]);
            });

        PathAttendance::query()
            ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id))
            ->where('notes', 'تسجيل حضور ذاتي')
            ->with('registration.learningPath')
            ->get()
            ->each(function (PathAttendance $attendance) use ($entries): void {
                $path = $attendance->registration?->learningPath;
                $label = $path?->title ?? 'مسار تدريبي';
                $detail = 'مسار: «'.$label.'» — '.($attendance->attendance_date?->format('Y/m/d') ?? '');

                if (self::hasDetailEntry($entries, UserActivityAction::AttendanceCheckIn->title(), $detail)) {
                    return;
                }

                $entries->push([
                    'occurred_at' => $attendance->updated_at ?? $attendance->created_at ?? now(),
                    'category' => UserActivityAction::AttendanceCheckIn->category(),
                    'title' => UserActivityAction::AttendanceCheckIn->title(),
                    'detail' => $detail,
                ]);
            });
    }

    /**
     * @param  Collection<int, array{occurred_at: Carbon, category: string, title: string, detail: string}>  $entries
     */
    private static function hasLoggedAction(Collection $entries, string $title): bool
    {
        return $entries->contains(fn (array $entry): bool => $entry['title'] === $title);
    }

    private static function hasDetailEntry(Collection $entries, string $title, string $detail): bool
    {
        return $entries->contains(fn (array $entry): bool => $entry['title'] === $title
            && $entry['detail'] === $detail);
    }
}
