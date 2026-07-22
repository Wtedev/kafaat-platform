<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Carbon\Carbon;
use Database\Seeders\VolunteerLeadersProgramDatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerLeadersProgramDatesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sets_program_and_registration_dates_for_matching_title(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22')->startOfDay());

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'registration_start' => '2026-01-01',
            'registration_end' => '2026-01-15',
        ]);

        $this->seed(VolunteerLeadersProgramDatesSeeder::class);

        $program->refresh();

        $this->assertSame(
            VolunteerLeadersProgramDatesSeeder::START_DATE,
            $program->start_date?->toDateString(),
        );
        $this->assertSame(
            VolunteerLeadersProgramDatesSeeder::END_DATE,
            $program->end_date?->toDateString(),
        );
        $this->assertSame(
            VolunteerLeadersProgramDatesSeeder::REGISTRATION_START,
            $program->registration_start?->toDateString(),
        );
        $this->assertSame(
            VolunteerLeadersProgramDatesSeeder::REGISTRATION_END,
            $program->registration_end?->toDateString(),
        );
        $this->assertTrue($program->isRegistrationOpen());
        $this->assertSame('مفتوح', $program->registrationWindowStatusLabel());
        $this->assertSame('30 يوماً', $program->programDurationDescription());
    }

    public function test_re_run_is_idempotent_when_dates_already_set(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'start_date' => VolunteerLeadersProgramDatesSeeder::START_DATE,
            'end_date' => VolunteerLeadersProgramDatesSeeder::END_DATE,
            'registration_start' => VolunteerLeadersProgramDatesSeeder::REGISTRATION_START,
            'registration_end' => VolunteerLeadersProgramDatesSeeder::REGISTRATION_END,
        ]);

        $this->seed(VolunteerLeadersProgramDatesSeeder::class);
        $this->seed(VolunteerLeadersProgramDatesSeeder::class);

        $program->refresh();

        $this->assertSame(
            VolunteerLeadersProgramDatesSeeder::START_DATE,
            $program->start_date?->toDateString(),
        );
        $this->assertSame(
            VolunteerLeadersProgramDatesSeeder::END_DATE,
            $program->end_date?->toDateString(),
        );
        $this->assertSame(
            VolunteerLeadersProgramDatesSeeder::REGISTRATION_START,
            $program->registration_start?->toDateString(),
        );
        $this->assertSame(
            VolunteerLeadersProgramDatesSeeder::REGISTRATION_END,
            $program->registration_end?->toDateString(),
        );
    }
}
