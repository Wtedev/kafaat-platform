<?php

namespace Database\Seeders;

use App\Enums\InboxNotificationType;
use App\Models\Certificate;
use App\Models\InboxNotification;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Models\TeamMember;
use App\Models\TeamNotification;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerHour;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Models\VolunteerTeam;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Removes seeded/demo domain data: training paths, programs, volunteering, registrations,
 * related certificates and in-app notifications. Preserves news, roles/permissions, and
 * the production admin user (ADMIN_EMAIL). Safe to run multiple times (idempotent).
 */
class CleanDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->purgeProgramAttendance();
            $this->purgeCertificatesForDomainEntities();
            $this->purgeDomainInAppNotifications();
            $this->purgeVolunteerTeams();
            $this->purgeVolunteerDomain();
            $this->purgeProgramAndPathRegistrations();
            $this->purgeTrainingProgramsAndPaths();
            $this->purgeCertificatesForDemoUsers();
            $this->purgeNotificationsForDemoUsers();
            $this->deleteDemoUsers();
        });
    }

    /**
     * Attendance rows depend on program_registrations — must go first.
     */
    private function purgeProgramAttendance(): void
    {
        ProgramAttendance::query()->delete();
    }

    /**
     * Certificates polymorph to programs, paths, volunteer opportunities.
     * Remove before deleting those parent rows.
     */
    private function purgeCertificatesForDomainEntities(): void
    {
        $types = [
            TrainingProgram::class,
            LearningPath::class,
            VolunteerOpportunity::class,
        ];

        Certificate::query()->whereIn('certificateable_type', $types)->delete();
    }

    /**
     * In-app inbox: training, volunteering, registrations, certificates.
     * Does not delete NewsPublished (keeps inbox aligned with “do not touch news” for notification copy tied to news workflow).
     * Does not delete GeneralMessage / UserAlert (may be operational).
     */
    private function purgeDomainInAppNotifications(): void
    {
        $types = [
            InboxNotificationType::ProgramLaunched->value,
            InboxNotificationType::ProgramUpdated->value,
            InboxNotificationType::RegistrationApproved->value,
            InboxNotificationType::RegistrationRejected->value,
            InboxNotificationType::CertificateIssued->value,
            InboxNotificationType::VolunteerOpportunityUpdated->value,
        ];

        InboxNotification::query()->whereIn('type', $types)->delete();
    }

    /**
     * Team members and notifications reference volunteer_teams; delete children then teams.
     * (Could rely on FK cascade from volunteer_teams, but explicit is clearer.)
     */
    private function purgeVolunteerTeams(): void
    {
        TeamNotification::query()->delete();
        TeamMember::query()->delete();
        VolunteerTeam::query()->delete();
    }

    /**
     * Volunteer hours and registrations reference opportunities and users.
     */
    private function purgeVolunteerDomain(): void
    {
        VolunteerHour::query()->delete();
        VolunteerRegistration::query()->delete();
        VolunteerOpportunity::query()->delete();
    }

    /**
     * Program and path registrations reference programs/paths and users.
     */
    private function purgeProgramAndPathRegistrations(): void
    {
        ProgramRegistration::query()->delete();
        PathRegistration::query()->delete();
    }

    /**
     * Editors pivots cascade when parents are deleted; order: programs then paths.
     */
    private function purgeTrainingProgramsAndPaths(): void
    {
        TrainingProgram::query()->delete();
        LearningPath::query()->delete();
    }

    /**
     * Any remaining certificates tied to demo user accounts (edge cases after morph cleanup).
     */
    private function purgeCertificatesForDemoUsers(): void
    {
        $ids = $this->demoUserIdsQuery()->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        Certificate::query()->whereIn('user_id', $ids)->delete();
    }

    /**
     * Notifications addressed to or sent by demo users (after type-based purge).
     */
    private function purgeNotificationsForDemoUsers(): void
    {
        $ids = $this->demoUserIdsQuery()->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        InboxNotification::query()
            ->where(function ($q) use ($ids): void {
                $q->whereIn('user_id', $ids)
                    ->orWhereIn('sender_id', $ids);
            })
            ->delete();
    }

    /**
     * Demo accounts from legacy DatabaseSeeder patterns; never deletes ADMIN_EMAIL user.
     */
    private function deleteDemoUsers(): void
    {
        $users = $this->demoUserIdsQuery()->get();

        foreach ($users as $user) {
            $user->syncRoles([]);
            $user->delete();
        }
    }

    /**
     * Users eligible for removal: explicit seeded emails + *@kafaat.test, excluding env admin.
     */
    private function demoUserIdsQuery()
    {
        $protectedEmail = $this->protectedAdminEmail();

        $explicit = [
            'admin@kafaat.test',
            'admin@example.com',
            'staff@kafaat.test',
            'staff@example.com',
            'beneficiary@kafaat.test',
            'beneficiary@example.com',
            'sara@example.com',
            'khalid@example.com',
        ];

        return User::query()
            ->where(function ($q) use ($explicit): void {
                $q->whereIn('email', $explicit)
                    ->orWhere('email', 'like', '%@kafaat.test')
                    ->orWhere('email', 'like', 'beneficiary.%@seed.kafaat.org.sa');
            })
            ->when($protectedEmail, fn ($q) => $q->whereRaw('LOWER(email) != ?', [strtolower($protectedEmail)]));
    }

    private function protectedAdminEmail(): ?string
    {
        $email = env('ADMIN_EMAIL');

        return is_string($email) && $email !== '' ? trim($email) : null;
    }
}
