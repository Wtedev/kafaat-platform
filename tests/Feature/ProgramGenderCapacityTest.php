<?php

namespace Tests\Feature;

use App\Enums\ProfileGender;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramCapacityExceededException;
use App\Models\Profile;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramAcceptanceConditionEvaluator;
use App\Services\ProgramRegistrationService;
use App\Support\ProgramAcceptanceConditions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramGenderCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_gender_capacity_blocks_only_the_full_gender(): void
    {
        $program = $this->program([
            'capacity' => null,
            'capacity_male' => 2,
            'capacity_female' => 1,
        ]);
        $this->approved($program, ProfileGender::Female);

        $female = $this->person(ProfileGender::Female);
        $male = $this->person(ProfileGender::Male);
        $evaluator = app(ProgramAcceptanceConditionEvaluator::class);

        $this->assertSame(
            [ProgramAcceptanceConditions::genderCapacityFullMessage(ProfileGender::Female->value)],
            $evaluator->evaluate($program, $female)['reasons'],
        );
        $this->assertTrue($evaluator->evaluate($program, $male)['eligible']);

        $pending = ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $female->id,
            'status' => RegistrationStatus::Pending,
        ]);

        $this->expectException(ProgramCapacityExceededException::class);
        app(ProgramRegistrationService::class)->approve($pending, User::factory()->create());
    }

    public function test_shared_capacity_blocks_the_next_approval(): void
    {
        $program = $this->program(['capacity' => 1]);
        $this->approved($program, ProfileGender::Male);
        $second = $this->person(ProfileGender::Female);
        $pending = ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $second->id,
            'status' => RegistrationStatus::Pending,
        ]);

        $this->assertTrue(app(ProgramAcceptanceConditionEvaluator::class)->evaluate($program, $second)['eligible']);

        $this->expectException(ProgramCapacityExceededException::class);
        app(ProgramRegistrationService::class)->approve($pending, User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function program(array $extra = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج السعة',
            'slug' => 'capacity-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'auto_accept_registrations' => false,
        ], $extra));
    }

    private function person(ProfileGender $gender): User
    {
        $user = User::factory()->create(['role_type' => 'beneficiary', 'is_active' => true]);
        Profile::query()->create([
            'user_id' => $user->id,
            'gender' => $gender,
            'birth_date' => '1995-01-01',
        ]);

        return $user->fresh('profile');
    }

    private function approved(TrainingProgram $program, ProfileGender $gender): void
    {
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $this->person($gender)->id,
            'status' => RegistrationStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
