<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Database\Seeders\FaeqoonProgramArchiveSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaeqoonProgramArchiveSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_archives_matching_title_with_extra_spaces(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => ' فائقون وفائقات ',
            'slug' => 'other-slug-temp',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $this->seed(FaeqoonProgramArchiveSeeder::class);

        $program->refresh();

        $this->assertSame(ProgramStatus::Archived, $program->status);
        $this->assertNull($program->published_at);
    }

    public function test_archives_by_canonical_slug(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج مختلف العنوان',
            'slug' => FaeqoonProgramArchiveSeeder::SLUG,
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $this->seed(FaeqoonProgramArchiveSeeder::class);

        $program->refresh();

        $this->assertSame(ProgramStatus::Archived, $program->status);
        $this->assertNull($program->published_at);
    }

    public function test_does_not_touch_unrelated_programs(): void
    {
        $other = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'slug' => 'volunteer-leaders',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $this->seed(FaeqoonProgramArchiveSeeder::class);

        $other->refresh();

        $this->assertSame(ProgramStatus::Published, $other->status);
        $this->assertNotNull($other->published_at);
    }

    public function test_re_run_is_idempotent_when_already_archived(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'فائقون وفائقات',
            'slug' => FaeqoonProgramArchiveSeeder::SLUG,
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Professional,
            'status' => ProgramStatus::Archived,
            'published_at' => null,
        ]);

        $this->seed(FaeqoonProgramArchiveSeeder::class);
        $this->seed(FaeqoonProgramArchiveSeeder::class);

        $program->refresh();

        $this->assertSame(ProgramStatus::Archived, $program->status);
        $this->assertNull($program->published_at);
    }
}
