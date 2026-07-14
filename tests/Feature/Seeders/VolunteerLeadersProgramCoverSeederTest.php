<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Database\Seeders\VolunteerLeadersProgramCoverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VolunteerLeadersProgramCoverSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_restores_durable_cover_path_for_matching_title(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'image' => null,
        ]);

        $this->seed(VolunteerLeadersProgramCoverSeeder::class);

        $program->refresh();

        $this->assertSame(
            VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH,
            $program->image,
        );
        $this->assertTrue(
            File::exists(public_path(VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH)),
        );
    }

    public function test_re_run_is_idempotent_when_cover_already_set(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'image' => VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH,
        ]);

        $this->seed(VolunteerLeadersProgramCoverSeeder::class);
        $this->seed(VolunteerLeadersProgramCoverSeeder::class);

        $program->refresh();

        $this->assertSame(
            VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH,
            $program->image,
        );
    }
}
