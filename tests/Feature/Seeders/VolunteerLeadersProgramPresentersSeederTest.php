<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Database\Seeders\VolunteerLeadersProgramPresentersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerLeadersProgramPresentersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_clears_presenters_for_matching_title(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'program_presenters' => [
                ['name' => 'أ. أحمد الرفاعي', 'role' => 'دكتوراه في التمكين الشخصي والقيادي'],
                ['name' => 'د. محمد النصار', 'role' => 'دكتوراه في القيادة والإدارة التربوية'],
            ],
        ]);

        $this->seed(VolunteerLeadersProgramPresentersSeeder::class);

        $program->refresh();

        $this->assertNull($program->program_presenters);
    }

    public function test_re_run_is_idempotent_when_presenters_already_cleared(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'program_presenters' => null,
        ]);

        $this->seed(VolunteerLeadersProgramPresentersSeeder::class);
        $this->seed(VolunteerLeadersProgramPresentersSeeder::class);

        $program->refresh();

        $this->assertNull($program->program_presenters);
    }
}
