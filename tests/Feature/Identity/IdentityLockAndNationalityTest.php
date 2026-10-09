<?php

namespace Tests\Feature\Identity;

use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\RegistrationNotEligibleException;
use App\Enums\PrivacyCorrectionFieldCode;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\Privacy\PrivacyRequestService;
use App\Services\ProgramRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class IdentityLockAndNationalityTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config([
            'staff_ui.maintenance' => false,
            'staff_ui.ready_modules' => ['users'],
        ]);
    }

    public function test_beneficiary_cannot_change_identity_on_profile_complete_or_privacy_request(): void
    {
        $original = IdentityNumberService::prepareStoragePayload('1098765432', IdentityType::NationalId);
        $user = $this->beneficiary($original);
        $payload = $this->profilePayload([
            'identity_type' => IdentityType::Iqama->value,
            'identity_number' => '2098765432',
        ]);

        $this->actingAsOtpVerified($user)
            ->patch(route('portal.profile.update'), $payload)
            ->assertSessionHasErrors('identity_number');

        $this->actingAsOtpVerified($user)
            ->post(route('portal.profile.complete.store'), $payload)
            ->assertSessionHasErrors('identity_number');

        $locked = false;
        try {
            app(PrivacyRequestService::class)->submitDataCorrection(
                $user->fresh(),
                PrivacyCorrectionFieldCode::IdentityNumber,
                'أريد تغيير رقم الهوية المسجل',
                [
                    'identity_type' => IdentityType::Iqama->value,
                    'identity_number' => '2098765432',
                ],
                Request::create('/portal/privacy', 'POST'),
                'password',
            );
        } catch (ValidationException $exception) {
            $locked = $exception->errors()['field_code'][0] ?? null;
        }

        $this->assertIsString($locked);

        $user->refresh();
        $this->assertSame($original['identity_number_lookup_hash'], $user->identity_number_lookup_hash);
        $this->assertSame(IdentityType::NationalId, $user->identity_type);
    }

    public function test_legacy_account_can_enter_identity_once_then_it_locks(): void
    {
        $user = User::factory()->create([
            'name' => 'حساب قديم',
            'role_type' => 'beneficiary',
            'email_verified_at' => now(),
            'phone' => '0555000111',
        ]);
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);
        Profile::query()->create(['user_id' => $user->id]);

        $first = $this->profilePayload([
            'identity_type' => IdentityType::NationalId->value,
            'identity_number' => '1234567890',
        ]);

        $this->actingAsOtpVerified($user)
            ->post(route('portal.profile.complete.store'), $first)
            ->assertRedirect(route('portal.dashboard'));

        $user->refresh();
        $lockedHash = $user->identity_number_lookup_hash;
        $this->assertNotNull($lockedHash);
        $this->assertTrue(IdentityNumberService::isSaudiNationalNumber(
            IdentityNumberService::digitsFromCiphertext($user->identity_number_ciphertext),
        ));

        $second = $this->profilePayload([
            'identity_type' => IdentityType::Iqama->value,
            'identity_number' => '2234567890',
        ]);

        $this->actingAsOtpVerified($user)
            ->post(route('portal.profile.complete.store'), $second)
            ->assertSessionHasErrors('identity_number');

        $user->refresh();
        $this->assertSame($lockedHash, $user->identity_number_lookup_hash);
        $this->assertSame(IdentityType::NationalId, $user->identity_type);
    }

    public function test_admin_identity_change_requires_a_reason_and_is_audited(): void
    {
        $original = IdentityNumberService::prepareStoragePayload('1098765432', IdentityType::NationalId);
        $beneficiary = $this->beneficiary($original);
        $admin = $this->staff(['users.view', 'beneficiaries.identity.update']);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.identity.update', $beneficiary), [
                'identity_type' => IdentityType::Iqama->value,
                'identity_number' => '2098765432',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.identity.update', $beneficiary), [
                'identity_type' => IdentityType::Iqama->value,
                'identity_number' => '2098765432',
                'reason' => 'تصحيح نوع الهوية بعد مراجعة الوثيقة',
            ])
            ->assertRedirect(route('staff-ui.users.show', $beneficiary));

        $beneficiary->refresh();
        $this->assertSame(IdentityType::Iqama, $beneficiary->identity_type);
        $this->assertNotSame($original['identity_number_lookup_hash'], $beneficiary->identity_number_lookup_hash);
        $this->assertSame('5432', $beneficiary->identity_number_last4);

        $log = AuditLog::query()->where('action', 'identity.corrected')->first();
        $this->assertNotNull($log);
        $this->assertSame('تصحيح نوع الهوية بعد مراجعة الوثيقة', $log->reason);
        $this->assertSame('national_id', $log->metadata['identity_type_before']);
        $this->assertSame('iqama', $log->metadata['identity_type_after']);
        $this->assertArrayNotHasKey('identity_number', $log->metadata ?? []);
    }

    public function test_saudi_condition_uses_the_first_digit_at_registration_and_approval(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج سعوديين',
            'slug' => 'saudi-prefix-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'acceptance_conditions' => ['require_saudi_national' => true],
        ]);
        $approver = User::factory()->create(['role_type' => 'staff']);
        $service = app(ProgramRegistrationService::class);

        $prefixTwoStoredAsSaudi = $this->beneficiary(
            IdentityNumberService::prepareStoragePayload('2091111111', IdentityType::NationalId),
            'prefix-two@example.com',
        );

        try {
            $service->register($program, $prefixTwoStoredAsSaudi);
            $this->fail('Registration should reject a number that does not start with 1.');
        } catch (RegistrationNotEligibleException) {
            $this->assertTrue(true);
        }

        $pending = ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $prefixTwoStoredAsSaudi->id,
            'status' => RegistrationStatus::Pending,
        ]);

        $this->expectException(RegistrationNotEligibleException::class);
        $service->approve($pending, $approver);
    }

    public function test_prefix_one_is_approved_even_when_identity_type_is_iqama(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج سعوديين',
            'slug' => 'saudi-prefix-ok-'.uniqid(),
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'acceptance_conditions' => ['require_saudi_national' => true],
        ]);
        $user = $this->beneficiary(
            IdentityNumberService::prepareStoragePayload('1091111111', IdentityType::Iqama),
            'prefix-one@example.com',
        );
        $registration = ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Pending,
        ]);

        $approved = app(ProgramRegistrationService::class)->approve($registration, User::factory()->create());

        $this->assertSame(RegistrationStatus::Approved, $approved->status);
    }

    /**
     * @param  array<string, mixed>  $storage
     */
    private function beneficiary(array $storage, string $email = 'locked@example.com'): User
    {
        $user = User::factory()->create([
            'name' => 'مستفيد مقفل',
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'عبدالله',
            'family_name' => 'السعود',
            'email' => $email,
            'phone' => '0555123499',
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);
        $user->forceFill([
            'identity_type' => $storage['identity_type'] instanceof IdentityType
                ? $storage['identity_type']->value
                : $storage['identity_type'],
            'identity_number_ciphertext' => $storage['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $storage['identity_number_lookup_hash'],
            'identity_number_last4' => $storage['identity_number_last4'],
            'identity_confirmed_at' => $storage['identity_confirmed_at'],
        ])->save();
        $user->profile()->create([
            'gender' => ProfileGender::Male,
            'birth_date' => '1995-05-15',
            'city' => 'الرياض',
        ]);

        return $user->fresh(['profile']);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions): User
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        $staff->givePermissionTo($permissions);

        return $staff->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profilePayload(array $overrides = []): array
    {
        $payload = $this->validRegistrationPayload($overrides);
        unset($payload['email'], $payload['email_confirmation'], $payload['password'], $payload['password_confirmation'], $payload['privacy_policy_version'], $payload['privacy_policy_acknowledged']);

        return $payload;
    }
}
