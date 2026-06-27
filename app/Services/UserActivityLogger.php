<?php

namespace App\Services;

use App\Enums\UserActivityAction;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Support\Carbon;

class UserActivityLogger
{
    public static function log(User $user, UserActivityAction $action, ?string $detail = null, ?Carbon $occurredAt = null): void
    {
        if (! $user->isPortalUser()) {
            return;
        }

        UserActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'title' => $action->title(),
            'detail' => $detail,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    public static function logAccountCreated(User $user): void
    {
        self::log($user, UserActivityAction::AccountCreated, 'تم إنشاء حساب المستفيد في المنصة.');
    }

    public static function logEmailVerified(User $user): void
    {
        self::log($user, UserActivityAction::EmailVerified, 'أكّد المستفيد بريده الإلكتروني.');
    }

    public static function logLogin(User $user): void
    {
        self::log($user, UserActivityAction::Login);
    }

    public static function logLogout(User $user): void
    {
        self::log($user, UserActivityAction::Logout);
    }

    public static function logEmailNotifications(User $user, bool $enabled): void
    {
        self::log(
            $user,
            $enabled ? UserActivityAction::EmailNotificationsEnabled : UserActivityAction::EmailNotificationsDisabled,
        );
    }

    /**
     * @param  list<string>  $fields
     */
    public static function logProfileUpdated(User $user, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        $labels = implode('، ', $fields);
        self::log($user, UserActivityAction::ProfileUpdated, 'الحقول: '.$labels);
    }

    /**
     * @param  list<string>  $fieldLabels
     */
    public static function logAdminUserUpdated(User $beneficiary, array $fieldLabels, User $actor): void
    {
        if ($fieldLabels === []) {
            return;
        }

        $labels = implode('، ', $fieldLabels);
        $actorName = filled($actor->name) ? $actor->name : 'مسؤول';

        self::log(
            $beneficiary,
            UserActivityAction::AdminUserUpdated,
            'الحقول: '.$labels.' — المعدّل: '.$actorName,
        );
    }

    public static function logCompetencyUpdated(User $user, string $sectionKey, ?string $extraDetail = null): void
    {
        $label = UserActivityAction::competencySectionLabels()[$sectionKey] ?? $sectionKey;
        $detail = $extraDetail !== null && $extraDetail !== ''
            ? $label.' — '.$extraDetail
            : $label;

        self::log($user, UserActivityAction::CompetencyUpdated, $detail);
    }

    public static function logProgramRegistration(User $user, string $programTitle): void
    {
        self::log($user, UserActivityAction::ProgramRegistration, '«'.$programTitle.'»');
    }

    public static function logPathRegistration(User $user, string $pathTitle): void
    {
        self::log($user, UserActivityAction::PathRegistration, '«'.$pathTitle.'»');
    }

    public static function logVolunteerRegistration(User $user, string $opportunityTitle): void
    {
        self::log($user, UserActivityAction::VolunteerRegistration, '«'.$opportunityTitle.'»');
    }

    public static function logAttendanceCheckIn(User $user, string $contextLabel): void
    {
        self::log($user, UserActivityAction::AttendanceCheckIn, $contextLabel);
    }

    public static function logCertificateDownload(User $user, string $certificateNumber): void
    {
        self::log($user, UserActivityAction::CertificateDownloaded, 'رقم الشهادة: '.$certificateNumber);
    }
}
