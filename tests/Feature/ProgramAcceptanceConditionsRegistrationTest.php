<?php

namespace Tests\Feature;

use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\RegistrationNotEligibleException;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\ProgramRegistrationService;
use App\Support\ProgramAcceptanceConditions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProgramAcceptanceConditionsRegistrationTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_auto_accept_approves_eligible_user(): void
    {
        $user = $this->makeEligiblePortalUser();
        $program = $this->makeOpenProgram([
            'auto_accept_registrations' => true,
            'acceptance_conditions' => [
                'require_saudi_national' => true,
            ],
        ]);

        $registration = app(ProgramRegistrationService::class)->register($program, $user);

        $this->assertSame(RegistrationStatus::Approved, $registration->status);
    }

    public function test_ineligible_user_cannot_register(): void
    {
        $user = $this->makeEligiblePortalUser(IdentityType::Iqama);
        $program = $this->makeOpenProgram([
            'auto_accept_registrations' => true,
            'acceptance_conditions' => [
                'require_saudi_national' => true,
            ],
        ]);

        $this->expectException(RegistrationNotEligibleException::class);
        app(ProgramRegistrationService::class)->register($program, $user);
    }

    public function test_manual_mode_keeps_eligible_pending(): void
    {
        $user = $this->makeEligiblePortalUser();
        $program = $this->makeOpenProgram([
            'auto_accept_registrations' => false,
            'acceptance_conditions' => [
                'cities' => ['الرياض'],
            ],
        ]);

        $registration = app(ProgramRegistrationService::class)->register($program, $user);

        $this->assertSame(RegistrationStatus::Pending, $registration->status);
    }

    public function test_public_route_blocks_ineligible_applicant(): void
    {
        $user = $this->makeEligiblePortalUser(IdentityType::Iqama);
        $program = $this->makeOpenProgram([
            'auto_accept_registrations' => false,
            'acceptance_conditions' => [
                'require_saudi_national' => true,
            ],
        ]);

        $this->actingAsOtpVerified($user)
            ->from(route('public.programs.show', $program))
            ->post(route('public.programs.register', $program), [
                'attendance_acknowledgement' => '1',
            ])
            ->assertRedirect(route('public.programs.show', $program))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('program_registrations', [
            'training_program_id' => $program->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_form_data_packer_keeps_conditions_when_auto_accept_on(): void
    {
        $packed = ProgramAcceptanceConditions::applyFormData([
            'auto_accept_registrations' => true,
            'acceptance_manual_review' => false,
            'acceptance_require_saudi_national' => true,
            'acceptance_genders' => [ProfileGender::Female->value],
            'acceptance_min_age' => 18,
            'acceptance_max_age' => 40,
            'acceptance_cities' => ['الرياض', 'جدة'],
            'acceptance_require_complete_profile' => false,
        ]);

        $this->assertTrue($packed['auto_accept_registrations']);
        $this->assertSame([
            'require_saudi_national' => true,
            'genders' => [ProfileGender::Female->value],
            'gender_capacity_full' => [],
            'min_age' => 18,
            'max_age' => 40,
            'cities' => ['الرياض', 'جدة'],
            'require_complete_profile' => false,
        ], $packed['acceptance_conditions']);
        $this->assertArrayNotHasKey('acceptance_require_saudi_national', $packed);
    }

    public function test_form_data_packer_keeps_conditions_when_acceptance_toggles_are_off(): void
    {
        $packed = ProgramAcceptanceConditions::applyFormData([
            'auto_accept_registrations' => false,
            'acceptance_manual_review' => false,
            'acceptance_require_saudi_national' => true,
            'acceptance_genders' => [],
            'acceptance_min_age' => null,
            'acceptance_max_age' => null,
            'acceptance_cities' => ['جدة'],
            'acceptance_require_complete_profile' => false,
        ]);

        $this->assertSame([
            'require_saudi_national' => true,
            'genders' => [],
            'gender_capacity_full' => [],
            'min_age' => null,
            'max_age' => null,
            'cities' => ['جدة'],
            'require_complete_profile' => false,
        ], $packed['acceptance_conditions']);
    }

    public function test_form_data_packer_clears_conditions_only_when_fields_are_empty(): void
    {
        $packed = ProgramAcceptanceConditions::applyFormData([
            'auto_accept_registrations' => false,
            'acceptance_manual_review' => false,
            'acceptance_require_saudi_national' => false,
            'acceptance_genders' => [],
            'acceptance_min_age' => null,
            'acceptance_max_age' => null,
            'acceptance_cities' => [],
            'acceptance_require_complete_profile' => false,
        ]);

        $this->assertNull($packed['acceptance_conditions']);
    }

    public function test_missing_condition_field_sends_the_beneficiary_to_profile_completion_then_back(): void
    {
        $user = $this->makeEligiblePortalUser();
        $user->profile->forceFill(['gender' => null])->save();
        $program = $this->makeOpenProgram([
            'auto_accept_registrations' => false,
            'acceptance_conditions' => [
                'genders' => [ProfileGender::Female->value],
            ],
        ]);

        $this->actingAsOtpVerified($user)
            ->from(route('public.programs.show', $program))
            ->post(route('public.programs.register', $program), [
                'attendance_acknowledgement' => '1',
            ])
            ->assertRedirect(route('portal.profile.complete', [
                'return' => '/programs/'.$program->slug,
            ]));

        $this->assertDatabaseMissing('program_registrations', [
            'training_program_id' => $program->id,
            'user_id' => $user->id,
        ]);

        $payload = $this->validRegistrationPayload();
        unset($payload['email'], $payload['password'], $payload['password_confirmation']);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.profile.complete', [
                'return' => '/programs/'.$program->slug,
            ]))
            ->assertOk();

        $this->post(route('portal.profile.complete.store'), $payload)
            ->assertRedirect('/programs/'.$program->slug);
    }

    private function makeEligiblePortalUser(IdentityType $type = IdentityType::NationalId): User
    {
        $identity = $this->generateValidIdentityForType($type);
        $payload = IdentityNumberService::prepareStoragePayload($identity, $type);

        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'identity_type' => $type,
            'identity_number_ciphertext' => $payload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $payload['identity_number_lookup_hash'],
            'identity_number_last4' => $payload['identity_number_last4'],
            'identity_confirmed_at' => $payload['identity_confirmed_at'],
            'phone' => '0501234567',
            'first_name' => 'سارة',
            'father_name' => 'أحمد',
            'grandfather_name' => 'علي',
            'family_name' => 'القحطاني',
        ]);
        $user->assignRole('beneficiary');

        Profile::query()->create([
            'user_id' => $user->id,
            'gender' => ProfileGender::Female,
            'birth_date' => Carbon::today()->subYears(24)->toDateString(),
            'city' => 'الرياض',
        ]);

        return $user->fresh(['profile']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOpenProgram(array $overrides = []): TrainingProgram
    {
        return TrainingProgram::query()->create(array_merge([
            'title' => 'برنامج قبول',
            'slug' => 'acceptance-reg-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'registration_start' => now()->subDay(),
            'registration_end' => now()->addMonth(),
            'auto_accept_registrations' => true,
        ], $overrides));
    }
}
