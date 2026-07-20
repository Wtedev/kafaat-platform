<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Models\Profile;
use App\Models\User;
use App\Policies\CertificatePolicy;
use App\Policies\ProfilePolicy;
use App\Policies\UserPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class AdminAuthorizationBaselineTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_beneficiary_cannot_access_filament_admin_panel(): void
    {
        $beneficiary = $this->makePortalUser();

        $this->assertFalse($beneficiary->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_admin_can_access_filament_admin_panel(): void
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_staff_with_certificates_issue_permission_can_issue(): void
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('staff');
        $staff->givePermissionTo('certificates.issue');

        $policy = app(CertificatePolicy::class);

        $this->assertTrue($policy->issue($staff));
    }

    public function test_beneficiary_cannot_issue_certificates(): void
    {
        $beneficiary = $this->makePortalUser();
        $policy = app(CertificatePolicy::class);

        $this->assertFalse($policy->issue($beneficiary));
    }

    public function test_beneficiary_cannot_update_other_users_via_user_policy(): void
    {
        $beneficiary = $this->makePortalUser();
        $target = User::factory()->create(['email' => 'target@example.com']);
        $policy = app(UserPolicy::class);

        $this->assertFalse($policy->update($beneficiary, $target));
    }

    public function test_beneficiary_cannot_export_profiles_via_profile_policy(): void
    {
        $beneficiary = $this->makePortalUser();
        $profile = Profile::query()->create(['user_id' => $beneficiary->id]);
        $policy = app(ProfilePolicy::class);

        $this->assertFalse($policy->export($beneficiary));
        $this->assertFalse($policy->view($beneficiary, $profile));
    }

    public function test_staff_with_roles_view_permission_can_export_profiles(): void
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('staff');
        // Minimal assignable permissions: avoid admin-only roles.view for profile view.
        $staff->givePermissionTo(['exports.beneficiaries.basic', 'edit_profile_badges']);

        $beneficiary = $this->makePortalUser(['email' => 'beneficiary-export@example.com']);
        $profile = Profile::query()->where('user_id', $beneficiary->id)->firstOrFail();
        $policy = app(ProfilePolicy::class);

        $this->assertTrue($policy->export($staff));
        $this->assertTrue($policy->view($staff, $profile));
    }

    private function makePortalUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
        $user->assignRole('beneficiary');
        Profile::query()->create(['user_id' => $user->id]);

        return $user;
    }
}
