<?php

namespace Tests\Unit\Models;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Database\Seeders\VolunteerLeadersProgramCoverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingProgramCoverLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_image_is_ignored_without_allow_flag(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع — اختبار القفل',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Draft,
            'image' => VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH,
        ]);

        $program->fill(['image' => null, 'title' => 'قادة التطوع — محدّث'])->save();

        $program->refresh();

        $this->assertSame(VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH, $program->image);
        $this->assertSame('قادة التطوع — محدّث', $program->title);
    }

    public function test_allow_cover_update_permits_image_change(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع — اختبار السماح',
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Draft,
            'image' => null,
        ]);

        $program->allowCoverUpdate = true;
        $program->forceFill([
            'image' => VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH,
        ])->save();

        $program->refresh();

        $this->assertSame(
            VolunteerLeadersProgramCoverSeeder::COVER_RELATIVE_PATH,
            $program->image,
        );
    }
}
