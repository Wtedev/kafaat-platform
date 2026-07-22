<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Database\Seeders\VolunteerLeadersProgramDatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerLeadersProgramDatesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_dates_for_matching_title(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
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
    }
}
