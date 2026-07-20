<?php

namespace Tests\Feature\PrivacyPhase05;

use App\Enums\IdentityType;
use App\Models\Profile;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\Security\SensitiveDataRedactor;
use App\Support\Privacy\SensitiveContactMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class AccessControlTest extends TestCase
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

    public function test_staff_without_contact_permission_sees_masked_email_in_user_table_context(): void
    {
        $staff = $this->makeStaffWithPermissions(['users.view', 'beneficiaries.view_basic']);
        $beneficiary = $this->makeBeneficiaryWithIdentity('staff-mask@example.com');

        $this->assertFalse($staff->can('viewContact', $beneficiary));
        $this->assertSame('s***@example.com', SensitiveContactMasker::maskEmail($beneficiary->email));
    }

    public function test_staff_with_full_identity_permission_can_reveal_identity_with_password(): void
    {
        $staff = $this->makeStaffWithPermissions([
            'users.view',
            'beneficiaries.view_basic',
            'beneficiaries.identity.view_full',
        ], password: 'SecretPass1!');

        $beneficiary = $this->makeBeneficiaryWithIdentity('reveal@example.com');

        $response = $this->actingAsOtpVerified($staff)
            ->postJson(route('admin.beneficiaries.identity.reveal', $beneficiary), [
                'password' => 'SecretPass1!',
                'reason' => 'تحقق من هوية المستفيد لإصدار شهادة',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['identity_number', 'expires_in_seconds'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(
            IdentityNumberService::normalize(
                IdentityNumberService::decrypt((string) $beneficiary->identity_number_ciphertext)
            ),
            $response->json('identity_number'),
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'identity.full_viewed',
            'target_user_id' => $beneficiary->id,
        ]);
    }

    public function test_staff_without_full_identity_permission_is_denied(): void
    {
        $staff = $this->makeStaffWithPermissions([
            'users.view',
            'beneficiaries.identity.view_masked',
        ], password: 'SecretPass1!');

        $beneficiary = $this->makeBeneficiaryWithIdentity('denied@example.com');

        $this->actingAsOtpVerified($staff)
            ->postJson(route('admin.beneficiaries.identity.reveal', $beneficiary), [
                'password' => 'SecretPass1!',
                'reason' => 'محاولة غير مصرح بها',
            ])
            ->assertForbidden();
    }

    public function test_sensitive_data_redactor_removes_password_and_otp_keys(): void
    {
        $redacted = SensitiveDataRedactor::redact([
            'password' => 'secret',
            'nested' => ['otp' => '123456', 'safe' => 'ok'],
        ]);

        $this->assertArrayNotHasKey('password', $redacted);
        $this->assertSame(['safe' => 'ok'], $redacted['nested']);
    }

    public function test_login_failure_creates_security_log_without_plain_email(): void
    {
        $this->post(route('login'), [
            'email' => 'fail-login@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('security_logs', [
            'event' => 'auth.login_failed',
        ]);

        $log = SecurityLog::query()->where('event', 'auth.login_failed')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('fail-login@example.com', json_encode($log->metadata ?? []));
    }

    public function test_request_id_header_is_present_on_web_response(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Request-ID');
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeStaffWithPermissions(array $permissions, string $password = 'password'): User
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
        ]);
        $staff->assignRole('staff');
        $staff->givePermissionTo($permissions);

        return $staff;
    }

    private function makeBeneficiaryWithIdentity(string $email): User
    {
        $nationalId = $this->generateValidNationalId();

        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'email' => $email,
            ...IdentityNumberService::prepareStoragePayload(
                $nationalId,
                IdentityType::NationalId,
            ),
        ]);
        $user->assignRole('beneficiary');
        Profile::query()->create(['user_id' => $user->id]);

        return $user->fresh();
    }
}
