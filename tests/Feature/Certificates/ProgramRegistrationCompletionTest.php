<?php

namespace Tests\Feature\Certificates;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\ProgramAttendance;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramRegistrationCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_registration_completed_does_not_issue_a_certificate(): void
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
        ]);
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج بلا إصدار تلقائي',
            'slug' => 'no-auto-certificate-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-10',
        ]);
        $beneficiary = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
        ]);
        $registration = ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $beneficiary->id,
            'status' => RegistrationStatus::Approved,
        ]);
        ProgramPrepDay::query()->create([
            'training_program_id' => $program->id,
            'prep_date' => '2026-10-10',
            'delivery_type' => ProgramPrepDayType::Remote,
            'requires_attendance' => true,
        ]);
        ProgramAttendance::query()->create([
            'program_registration_id' => $registration->id,
            'training_date' => '2026-10-10',
            'status' => AttendanceStatus::Present,
        ]);

        $updated = app(ProgramRegistrationService::class)->markCompleted(
            registration: $registration,
            admin: $admin,
            score: 90,
            attendancePercentage: 40,
        );

        $this->assertSame(RegistrationStatus::Completed, $updated->status);
        $this->assertEquals(90, (float) $updated->score);
        $this->assertEquals(100, (float) $updated->attendance_percentage);
        $this->assertTrue($updated->isEligibleForCertificate());
        $this->assertSame(0, Certificate::query()->count());
    }
}
