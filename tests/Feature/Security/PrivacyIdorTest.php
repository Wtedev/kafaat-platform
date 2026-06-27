<?php

namespace Tests\Feature\Security;

use App\Enums\AccountStatus;
use App\Enums\PrivacyExportFileStatus;
use App\Enums\ProgramStatus;
use App\Models\PrivacyExportFile;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PrivacyIdorTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        $this->seedActivePrivacyPolicy();
    }

    public function test_user_cannot_download_another_users_export(): void
    {
        $owner = $this->makeBeneficiary('owner-export@example.com');
        $attacker = $this->makeBeneficiary('attacker-export@example.com');

        $export = PrivacyExportFile::query()->create([
            'uuid' => (string) Str::uuid(),
            'privacy_request_id' => null,
            'user_id' => $owner->id,
            'disk' => 'private_documents',
            'path' => 'privacy/exports/test.zip',
            'format' => 'zip',
            'status' => PrivacyExportFileStatus::Ready,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAsOtpVerified($attacker)
            ->post(route('portal.privacy.exports.download', $export), [
                'password' => 'SecretPass1!',
            ])
            ->assertForbidden();
    }

    public function test_anonymized_user_cannot_access_portal(): void
    {
        $user = $this->makeBeneficiary('anon-portal@example.com');
        $user->forceFill([
            'account_status' => AccountStatus::Anonymized,
            'is_active' => false,
        ])->save();

        $this->actingAsOtpVerified($user->fresh())
            ->get(route('portal.dashboard'))
            ->assertForbidden();
    }

    private function makeBeneficiary(string $email): User
    {
        $user = User::factory()->create([
            'role_type' => 'trainee',
            'is_active' => true,
            'email_verified_at' => now(),
            'email' => $email,
            'password' => Hash::make('SecretPass1!'),
            'account_status' => AccountStatus::Active,
        ]);
        $user->assignRole('trainee');
        Profile::query()->create(['user_id' => $user->id, 'birth_date' => '1995-01-01']);

        return $user->fresh();
    }
}
