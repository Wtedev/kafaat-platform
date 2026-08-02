<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Database\Seeders\VolunteerLeadersProgramDescriptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerLeadersProgramDescriptionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_description_for_matching_title(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'description' => 'وصف قديم بدون تفاصيل التنفيذ.',
        ]);

        $this->seed(VolunteerLeadersProgramDescriptionSeeder::class);

        $program->refresh();

        $this->assertSame(
            trim(VolunteerLeadersProgramDescriptionSeeder::DESCRIPTION),
            trim((string) $program->description),
        );
        $this->assertStringContainsString(VolunteerLeadersProgramDescriptionSeeder::DESCRIPTION_MARKER, (string) $program->description);
        $this->assertStringContainsString('يتضمن عددًا من الأيام الحضورية وعن بعد، موضحة في تفاصيل البرنامج.', (string) $program->description);
        $this->assertStringNotContainsString('مالك البرنامج', (string) $program->description);
        $this->assertStringNotContainsString('شركاء البرنامج', (string) $program->description);
        $this->assertStringNotContainsString('جمعية عضيد للخدمات التطوعية', (string) $program->description);
        $this->assertStringNotContainsString('6 أيام حضورية', (string) $program->description);
    }

    public function test_re_run_is_idempotent_when_description_already_set(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'description' => trim(VolunteerLeadersProgramDescriptionSeeder::DESCRIPTION),
        ]);

        $this->seed(VolunteerLeadersProgramDescriptionSeeder::class);
        $this->seed(VolunteerLeadersProgramDescriptionSeeder::class);

        $program->refresh();

        $this->assertSame(
            trim(VolunteerLeadersProgramDescriptionSeeder::DESCRIPTION),
            trim((string) $program->description),
        );
    }
}
