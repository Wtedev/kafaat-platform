<?php

namespace Tests\Feature\Seeders;

use App\Enums\CompetencyTrack;
use App\Enums\ProfileGender;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramAcceptanceConditionEvaluator;
use App\Support\VolunteerLeadersProgramPeriod;
use Database\Seeders\VolunteerLeadersProgramFemaleCapacitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerLeadersProgramFemaleCapacitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_female_capacity_full_and_keeps_males_eligible(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج قادة التطوع',
            'slug' => VolunteerLeadersProgramPeriod::PROGRAM_SLUG,
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'auto_accept_registrations' => true,
            'acceptance_conditions' => [
                'genders' => [ProfileGender::Male->value],
            ],
        ]);

        $this->seed(VolunteerLeadersProgramFemaleCapacitySeeder::class);

        $program->refresh();
        $conditions = $program->acceptance_conditions;

        $this->assertSame([ProfileGender::Male->value], $conditions['genders']);
        $this->assertSame([ProfileGender::Female->value], $conditions['gender_capacity_full']);

        $female = User::factory()->create();
        Profile::query()->create([
            'user_id' => $female->id,
            'gender' => ProfileGender::Female,
        ]);
        $male = User::factory()->create();
        Profile::query()->create([
            'user_id' => $male->id,
            'gender' => ProfileGender::Male,
        ]);

        $evaluator = app(ProgramAcceptanceConditionEvaluator::class);

        $femaleResult = $evaluator->evaluate($program, $female->fresh('profile'));
        $this->assertFalse($femaleResult['eligible']);
        $this->assertSame(['السعة الاستيعابية للإناث ممتلئة'], $femaleResult['reasons']);

        $this->assertTrue($evaluator->evaluate($program, $male->fresh('profile'))['eligible']);
    }

    public function test_re_run_is_idempotent(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'slug' => VolunteerLeadersProgramPeriod::PROGRAM_SLUG,
            'program_kind' => TrainingProgramKind::Course,
            'competency_track' => CompetencyTrack::Community,
            'status' => ProgramStatus::Published,
            'auto_accept_registrations' => false,
            'acceptance_conditions' => [
                'genders' => [ProfileGender::Male->value],
                'gender_capacity_full' => [ProfileGender::Female->value],
            ],
        ]);

        $this->seed(VolunteerLeadersProgramFemaleCapacitySeeder::class);
        $this->seed(VolunteerLeadersProgramFemaleCapacitySeeder::class);

        $program->refresh();

        $this->assertSame([ProfileGender::Male->value], $program->acceptance_conditions['genders']);
        $this->assertSame(
            [ProfileGender::Female->value],
            $program->acceptance_conditions['gender_capacity_full'],
        );
    }
}
