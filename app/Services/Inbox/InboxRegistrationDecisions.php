<?php

namespace App\Services\Inbox;

use App\Enums\RegistrationStatus;
use App\Models\InboxNotification;
use App\Models\LearningPath;
use App\Models\News;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use Illuminate\Support\Facades\Gate;

final class InboxRegistrationDecisions
{
    /**
     * @return array<string, mixed>|null
     */
    public static function context(InboxNotification $record): ?array
    {
        $context = $record->context;

        return is_array($context) && isset($context['resource'], $context['id']) ? $context : null;
    }

    public static function publicUrl(InboxNotification $record): ?string
    {
        $context = self::context($record);
        if ($context === null) {
            return null;
        }

        $id = (int) $context['id'];

        return match ($context['resource']) {
            'news' => ($model = News::find($id)) ? route('public.news.show', $model) : null,
            'training_program' => ($model = TrainingProgram::find($id)) ? route('public.programs.show', $model) : null,
            'learning_path' => null,
            'volunteer_opportunity' => ($model = VolunteerOpportunity::find($id)) ? route('public.volunteering.show', $model) : null,
            default => null,
        };
    }

    public static function portalUrl(?User $user, InboxNotification $record): ?string
    {
        if ($user === null) {
            return null;
        }

        $context = self::context($record);
        if ($context === null) {
            return null;
        }

        $id = (int) $context['id'];

        return match ($context['resource']) {
            'training_program' => null,
            'learning_path' => ($path = LearningPath::find($id)) !== null
                && PathRegistration::query()
                    ->where('user_id', $user->id)
                    ->where('learning_path_id', $path->id)
                    ->exists()
                ? route('portal.paths.show', $path)
                : null,
            'program_registration' => ($registration = ProgramRegistration::find($id)) !== null
                && (int) $registration->user_id === (int) $user->id
                && $registration->trainingProgram !== null
                ? route('portal.programs', ['open_attendance' => $registration->trainingProgram->id])
                : null,
            'path_registration' => ($registration = PathRegistration::find($id)) !== null
                && (int) $registration->user_id === (int) $user->id
                && $registration->learningPath !== null
                ? route('portal.paths.show', $registration->learningPath)
                : null,
            'volunteer_opportunity' => ($opportunity = VolunteerOpportunity::find($id)) !== null
                && VolunteerRegistration::query()
                    ->where('user_id', $user->id)
                    ->where('opportunity_id', $opportunity->id)
                    ->exists()
                ? route('portal.volunteering')
                : null,
            'volunteer_registration' => ($registration = VolunteerRegistration::find($id)) !== null
                && (int) $registration->user_id === (int) $user->id
                ? route('portal.volunteering')
                : null,
            'certificate' => route('portal.certificates'),
            default => null,
        };
    }

    public static function openUrl(?User $user, InboxNotification $record): ?string
    {
        if ($user !== null && $user->isPortalUser()) {
            return self::portalUrl($user, $record) ?? self::publicUrl($record);
        }

        return self::publicUrl($record);
    }

    public static function openLabel(?User $user, InboxNotification $record): string
    {
        if ($user !== null && $user->isPortalUser() && self::portalUrl($user, $record) !== null) {
            $resource = (self::context($record) ?? [])['resource'] ?? '';

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

    public static function openIcon(?User $user, InboxNotification $record): string
    {
        if ($user !== null && $user->isPortalUser() && self::portalUrl($user, $record) !== null) {
            return 'heroicon-o-home';
        }

        return 'heroicon-o-globe-alt';
    }

    public static function programRegistration(InboxNotification $record): ?ProgramRegistration
    {
        $context = self::context($record);

        return ($context !== null && $context['resource'] === 'program_registration')
            ? ProgramRegistration::find((int) $context['id'])
            : null;
    }

    public static function pathRegistration(InboxNotification $record): ?PathRegistration
    {
        $context = self::context($record);

        return ($context !== null && $context['resource'] === 'path_registration')
            ? PathRegistration::find((int) $context['id'])
            : null;
    }

    public static function volunteerRegistration(InboxNotification $record): ?VolunteerRegistration
    {
        $context = self::context($record);

        return ($context !== null && $context['resource'] === 'volunteer_registration')
            ? VolunteerRegistration::find((int) $context['id'])
            : null;
    }

    public static function canApproveProgramRegistration(InboxNotification $record): bool
    {
        $registration = self::programRegistration($record);

        return $registration !== null
            && $registration->status === RegistrationStatus::Pending
            && Gate::allows('approve', $registration);
    }

    public static function canRejectProgramRegistration(InboxNotification $record): bool
    {
        $registration = self::programRegistration($record);

        return $registration !== null
            && $registration->status === RegistrationStatus::Pending
            && Gate::allows('reject', $registration);
    }

    public static function canApprovePathRegistration(InboxNotification $record): bool
    {
        $registration = self::pathRegistration($record);

        return $registration !== null
            && $registration->status === RegistrationStatus::Pending
            && Gate::allows('approve', $registration);
    }

    public static function canRejectPathRegistration(InboxNotification $record): bool
    {
        $registration = self::pathRegistration($record);

        return $registration !== null
            && $registration->status === RegistrationStatus::Pending
            && Gate::allows('reject', $registration);
    }

    public static function canApproveVolunteerRegistration(InboxNotification $record): bool
    {
        $registration = self::volunteerRegistration($record);

        return $registration !== null
            && $registration->status === RegistrationStatus::Pending
            && Gate::allows('approve', $registration);
    }

    public static function canRejectVolunteerRegistration(InboxNotification $record): bool
    {
        $registration = self::volunteerRegistration($record);

        return $registration !== null
            && $registration->status === RegistrationStatus::Pending
            && Gate::allows('reject', $registration);
    }
}
