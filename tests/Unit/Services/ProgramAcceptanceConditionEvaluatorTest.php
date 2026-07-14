<?php

namespace Tests\Unit\Services;

use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Enums\ProgramStatus;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\ProgramAcceptanceConditionEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\TestCase;

class ProgramAcceptanceConditionEvaluatorTest extends TestCase
{
    use GeneratesTestIdentityData;
    use RefreshDatabase;

    public function test_eligible_when_no_conditions(): void
    {
        $program = $this->makeProgram();
        $user = User::factory()->create();

        $result = app(ProgramAcceptanceConditionEvaluator::class)->evaluate($program, $user);

        $this->assertTrue($result['eligible']);
        $this->assertSame([], $result['reasons']);
    }

    public function test_requires_saudi_national_id(): void
    {
        $program = $this->makeProgram([
            'require_saudi_national' => true,
        ]);

        $saudi = $this->makeUserWithIdentity(IdentityType::NationalId);
        $resident = $this->makeUserWithIdentity(IdentityType::Iqama);

        $evaluator = app(ProgramAcceptanceConditionEvaluator::class);

        $this->assertTrue($evaluator->evaluate($program, $saudi)['eligible']);
        $this->assertFalse($evaluator->evaluate($program, $resident)['eligible']);
    }

    public function test_age_and_city_and_gender_filters(): void
    {
        $program = $this->makeProgram([
            'genders' => [ProfileGender::Female->value],
            'min_age' => 18,
            'max_age' => 30,
            'cities' => ['الرياض'],
        ]);

        $eligible = $this->makeUserWithIdentity(IdentityType::NationalId, [
            'gender' => ProfileGender::Female,
            'birth_date' => Carbon::today()->subYears(22)->toDateString(),
            'city' => 'الرياض',
        ]);

        $tooOld = $this->makeUserWithIdentity(IdentityType::NationalId, [
            'gender' => ProfileGender::Female,
            'birth_date' => Carbon::today()->subYears(40)->toDateString(),
            'city' => 'الرياض',
        ]);

        $wrongCity = $this->makeUserWithIdentity(IdentityType::NationalId, [
            'gender' => ProfileGender::Female,
            'birth_date' => Carbon::today()->subYears(22)->toDateString(),
            'city' => 'جدة',
        ]);

        $evaluator = app(ProgramAcceptanceConditionEvaluator::class);

        $this->assertTrue($evaluator->evaluate($program, $eligible)['eligible']);
        $this->assertFalse($evaluator->evaluate($program, $tooOld)['eligible']);
        $this->assertFalse($evaluator->evaluate($program, $wrongCity)['eligible']);
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    private function makeProgram(array $conditions = []): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج شروط',
            'slug' => 'acceptance-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'auto_accept_registrations' => true,
            'acceptance_conditions' => $conditions === [] ? null : $conditions,
        ]);
    }

    /**
     * @param  array<string, mixed>  $profileAttrs
     */
    private function makeUserWithIdentity(IdentityType $type, array $profileAttrs = []): User
    {
        $identity = $this->generateValidIdentityForType($type);
        $payload = IdentityNumberService::prepareStoragePayload($identity, $type);

        $user = User::factory()->create([
            'identity_type' => $type,
            'identity_number_ciphertext' => $payload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $payload['identity_number_lookup_hash'],
            'identity_number_last4' => $payload['identity_number_last4'],
            'identity_confirmed_at' => $payload['identity_confirmed_at'],
            'phone' => '0500000001',
            'first_name' => 'اختبار',
            'father_name' => 'محمد',
            'grandfather_name' => 'عبدالله',
            'family_name' => 'العلي',
        ]);

        Profile::query()->create(array_merge([
            'user_id' => $user->id,
            'gender' => ProfileGender::Male,
            'birth_date' => Carbon::today()->subYears(25)->toDateString(),
            'city' => 'الرياض',
        ], $profileAttrs));

        return $user->fresh(['profile']);
    }
}
