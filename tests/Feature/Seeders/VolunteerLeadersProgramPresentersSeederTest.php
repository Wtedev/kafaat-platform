<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use App\Support\TrainingProgramExtrasSupport;
use Database\Seeders\VolunteerLeadersProgramPresentersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerLeadersProgramPresentersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_presenters_for_matching_title(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'program_presenters' => null,
        ]);

        $this->seed(VolunteerLeadersProgramPresentersSeeder::class);

        $program->refresh();

        $this->assertSame(
            TrainingProgramExtrasSupport::normalizeProgramPresenters(
                VolunteerLeadersProgramPresentersSeeder::PRESENTERS,
            ),
            $program->program_presenters,
        );
    }

    public function test_re_run_is_idempotent_when_presenters_already_set(): void
    {
        $canonical = TrainingProgramExtrasSupport::normalizeProgramPresenters(
            VolunteerLeadersProgramPresentersSeeder::PRESENTERS,
        );

        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'program_presenters' => $canonical,
        ]);

        $this->seed(VolunteerLeadersProgramPresentersSeeder::class);
        $this->seed(VolunteerLeadersProgramPresentersSeeder::class);

        $program->refresh();

        $this->assertSame($canonical, $program->program_presenters);
    }
}
