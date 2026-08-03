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

    public function test_female_capacity_full_message_preferred_over_male_only_gender_label(): void
    {
        $program = $this->makeProgram([
            'genders' => [ProfileGender::Male->value],
            'gender_capacity_full' => [ProfileGender::Female->value],
        ]);

        $female = $this->makeUserWithIdentity(IdentityType::NationalId, [
            'gender' => ProfileGender::Female,
        ]);
        $male = $this->makeUserWithIdentity(IdentityType::NationalId, [
            'gender' => ProfileGender::Male,
        ]);

        $evaluator = app(ProgramAcceptanceConditionEvaluator::class);

        $femaleResult = $evaluator->evaluate($program, $female);
        $this->assertFalse($femaleResult['eligible']);
        $this->assertSame(
            ['انتهت المقاعد للإناث'],
            $femaleResult['reasons'],
        );

        $maleResult = $evaluator->evaluate($program, $male);
        $this->assertTrue($maleResult['eligible']);
        $this->assertSame([], $maleResult['reasons']);
    }

    public function test_true_male_only_program_keeps_gender_restriction_message(): void
    {
        $program = $this->makeProgram([
            'genders' => [ProfileGender::Male->value],
        ]);

        $female = $this->makeUserWithIdentity(IdentityType::NationalId, [
            'gender' => ProfileGender::Female,
        ]);

        $result = app(ProgramAcceptanceConditionEvaluator::class)->evaluate($program, $female);

        $this->assertFalse($result['eligible']);
        $this->assertSame(
            ['هذا البرنامج مخصص لـ: ذكر.'],
            $result['reasons'],
        );
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
