<?php

namespace Database\Seeders;

use App\Enums\RegistrationStatus;
use App\Enums\VolunteerHoursStatus;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerHour;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use Illuminate\Database\Seeder;

class RegistrationsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $beneficiary = User::where('email', 'beneficiary@example.com')->first();
        $sara = User::where('email', 'sara@example.com')->first();
        $khalid = User::where('email', 'khalid@example.com')->first();

        if (! $admin || ! $beneficiary || ! $sara || ! $khalid) {
            $this->command->warn('RegistrationsSeeder: required users not found — skipping.');

            return;
        }

        $this->seedPathRegistrations($admin, $beneficiary, $sara, $khalid);
        $this->seedProgramRegistrations($admin, $beneficiary, $sara, $khalid);
        $this->seedVolunteerRegistrationsAndHours($admin, $beneficiary, $sara, $khalid);
    }

    // ─── Path registrations ───────────────────────────────────────────────────

    private function seedPathRegistrations(User $admin, User $beneficiary, User $sara, User $khalid): void
    {
        $paths = LearningPath::all()->keyBy('slug');

        // beneficiary → pending (awaiting admin approval)
        if ($paths->has('مسار-ريادة-الأعمال') || $paths->first()) {
            $path1 = LearningPath::first();

            PathRegistration::firstOrCreate(
                ['learning_path_id' => $path1->id, 'user_id' => $beneficiary->id],
                ['status' => RegistrationStatus::Pending]
            );

            // sara → approved
            PathRegistration::firstOrCreate(
                ['learning_path_id' => $path1->id, 'user_id' => $sara->id],
                [
                    'status' => RegistrationStatus::Approved,
                    'approved_by' => $admin->id,
                    'approved_at' => now()->subDays(3),
                ]
            );
        }

        if (LearningPath::count() >= 2) {
            $path2 = LearningPath::skip(1)->first();

            // khalid → approved
            PathRegistration::firstOrCreate(
                ['learning_path_id' => $path2->id, 'user_id' => $khalid->id],
                [
                    'status' => RegistrationStatus::Approved,
                    'approved_by' => $admin->id,
                    'approved_at' => now()->subDays(5),
                ]
            );

            // beneficiary → pending
            PathRegistration::firstOrCreate(
                ['learning_path_id' => $path2->id, 'user_id' => $beneficiary->id],
                ['status' => RegistrationStatus::Pending]
            );
        }

        if (LearningPath::count() >= 3) {
            $path3 = LearningPath::skip(2)->first();

            // sara → pending
            PathRegistration::firstOrCreate(
                ['learning_path_id' => $path3->id, 'user_id' => $sara->id],
                ['status' => RegistrationStatus::Pending]
            );
        }
    }

    // ─── Program registrations ────────────────────────────────────────────────

    private function seedProgramRegistrations(User $admin, User $beneficiary, User $sara, User $khalid): void
    {
        if (! TrainingProgram::exists()) {
            return;
        }

        $prog1 = TrainingProgram::first();

        // beneficiary → pending
        ProgramRegistration::firstOrCreate(
            ['training_program_id' => $prog1->id, 'user_id' => $beneficiary->id],
            ['status' => RegistrationStatus::Pending]
        );

        // sara → approved
        ProgramRegistration::firstOrCreate(
            ['training_program_id' => $prog1->id, 'user_id' => $sara->id],
            [
                'status' => RegistrationStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(2),
            ]
        );

        if (TrainingProgram::count() >= 2) {
            $prog2 = TrainingProgram::skip(1)->first();

            // khalid → pending
            ProgramRegistration::firstOrCreate(
                ['training_program_id' => $prog2->id, 'user_id' => $khalid->id],
                ['status' => RegistrationStatus::Pending]
            );

            // beneficiary → approved with attendance + score
            ProgramRegistration::firstOrCreate(
                ['training_program_id' => $prog2->id, 'user_id' => $beneficiary->id],
                [
                    'status' => RegistrationStatus::Approved,
                    'approved_by' => $admin->id,
                    'approved_at' => now()->subDays(7),
                    'attendance_percentage' => 85.00,
                    'score' => 78.50,
                ]
            );
        }

        if (TrainingProgram::count() >= 3) {
            $prog3 = TrainingProgram::skip(2)->first();

            ProgramRegistration::firstOrCreate(
                ['training_program_id' => $prog3->id, 'user_id' => $sara->id],
                ['status' => RegistrationStatus::Pending]
            );
        }
    }

    // ─── Volunteer registrations + hours ──────────────────────────────────────

    private function seedVolunteerRegistrationsAndHours(User $admin, User $beneficiary, User $sara, User $khalid): void
    {
        if (! VolunteerOpportunity::exists()) {
            return;
        }

        $opp1 = VolunteerOpportunity::first();

        // beneficiary → approved registration + partial approved hours (not yet completed)
        $reg1 = VolunteerRegistration::firstOrCreate(
            ['opportunity_id' => $opp1->id, 'user_id' => $beneficiary->id],
            [
                'status' => RegistrationStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(4),
            ]
        );

        // 15 approved hours out of 40 expected → not yet complete
        VolunteerHour::firstOrCreate(
            ['user_id' => $beneficiary->id, 'opportunity_id' => $opp1->id, 'notes' => 'جلسة التدريب الأولى'],
            [
                'hours' => 8.00,
                'status' => VolunteerHoursStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(3),
            ]
        );
        VolunteerHour::firstOrCreate(
            ['user_id' => $beneficiary->id, 'opportunity_id' => $opp1->id, 'notes' => 'جلسة التدريب الثانية'],
            [
                'hours' => 7.00,
                'status' => VolunteerHoursStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(1),
            ]
        );
        // Pending hours (not yet approved)
        VolunteerHour::firstOrCreate(
            ['user_id' => $beneficiary->id, 'opportunity_id' => $opp1->id, 'notes' => 'جلسة إضافية قيد المراجعة'],
            [
                'hours' => 10.00,
                'status' => VolunteerHoursStatus::Pending,
            ]
        );

        // sara → pending registration
        VolunteerRegistration::firstOrCreate(
            ['opportunity_id' => $opp1->id, 'user_id' => $sara->id],
            ['status' => RegistrationStatus::Pending]
        );

        if (VolunteerOpportunity::count() >= 2) {
            $opp2 = VolunteerOpportunity::skip(1)->first();

            // khalid → approved registration + pending hours
            VolunteerRegistration::firstOrCreate(
                ['opportunity_id' => $opp2->id, 'user_id' => $khalid->id],
                [
                    'status' => RegistrationStatus::Approved,
                    'approved_by' => $admin->id,
                    'approved_at' => now()->subDays(2),
                ]
            );

            VolunteerHour::firstOrCreate(
                ['user_id' => $khalid->id, 'opportunity_id' => $opp2->id, 'notes' => 'أول يوم تطوعي'],
                [
                    'hours' => 5.00,
                    'status' => VolunteerHoursStatus::Pending,
                ]
            );
        }

        if (VolunteerOpportunity::count() >= 3) {
            $opp3 = VolunteerOpportunity::skip(2)->first();

            // beneficiary → pending registration
            VolunteerRegistration::firstOrCreate(
                ['opportunity_id' => $opp3->id, 'user_id' => $beneficiary->id],
                ['status' => RegistrationStatus::Pending]
            );
        }
    }
}
