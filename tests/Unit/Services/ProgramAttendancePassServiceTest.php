<?php

namespace Tests\Unit\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProgramAttendancePassServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_pass_payload_from_raw_code_and_url_fragment(): void
    {
        $service = app(ProgramAttendanceService::class);

        $this->assertSame(
            ['program_id' => 12, 'registration_id' => 34],
            $service->parsePassPayload('KAFAAT-P12-R34'),
        );

        $this->assertSame(
            ['program_id' => 12, 'registration_id' => 34],
            $service->parsePassPayload('https://example.test/programs/demo/registered/34#KAFAAT-P12-R34'),
        );

        $this->assertNull($service->parsePassPayload('not-a-pass'));
    }

    public function test_mark_present_from_pass_for_in_person_program(): void
    {
        Carbon::setTestNow('2026-07-14 10:00:00');

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج حضوري بوابة',
            'slug' => 'gate-in-person',
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::InPerson,
            'venue' => 'القاعة',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'capacity' => 20,
            'auto_accept_registrations' => true,
        ]);

        $user = User::factory()->create(['name' => 'نورة المتحققة']);
        $registration = ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => now(),
        ]);

        $checker = ProgramAttendanceChecker::query()->create([
            'training_program_id' => $program->id,
            'name' => 'متحضّرة تجريبية',
            'email' => 'checker@example.test',
            'is_active' => true,
            'verified_at' => now(),
        ]);

        $service = app(ProgramAttendanceService::class);
        $pass = sprintf('KAFAAT-P%d-R%d', $program->id, $registration->id);

        $first = $service->markPresentFromPass($program, $pass, $checker);
        $this->assertTrue($first['ok']);
        $this->assertSame('marked', $first['reason']);
        $this->assertSame('نورة المتحققة', $first['beneficiary_name']);

        $this->assertDatabaseHas('program_attendance', [
            'program_registration_id' => $registration->id,
            'status' => AttendanceStatus::Present->value,
        ]);

        $second = $service->markPresentFromPass($program, $pass, $checker);
        $this->assertTrue($second['ok']);
        $this->assertSame('already_present', $second['reason']);

        $this->assertSame(1, ProgramAttendance::query()->where('program_registration_id', $registration->id)->count());
    }

    public function test_mark_present_from_pass_rejects_wrong_program(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج حضوري أ',
            'slug' => 'gate-a',
            'description' => 'وصف',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Self,
            'delivery_mode' => ProgramDeliveryMode::InPerson,
            'venue' => 'القاعة',
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
            'capacity' => 20,
            'auto_accept_registrations' => true,
        ]);

        $result = app(ProgramAttendanceService::class)->markPresentFromPass(
            $program,
            'KAFAAT-P999-R1',
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('wrong_program', $result['reason']);
    }
}
