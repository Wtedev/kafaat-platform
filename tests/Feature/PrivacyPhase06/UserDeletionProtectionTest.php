<?php

namespace Tests\Feature\PrivacyPhase06;

use App\Enums\AccountStatus;
use App\Exceptions\UserDeletionNotAllowedException;
use App\Filament\Resources\UserResource;
use App\Models\Profile;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Privacy\AccountDeactivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class UserDeletionProtectionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
    }

    public function test_user_policy_delete_is_always_denied_even_with_users_delete_permission(): void
    {
        $admin = $this->makeStaffWithPermissions(['users.delete', 'users.view']);
        $beneficiary = $this->makeBeneficiaryWithIdentity('protected-delete@example.com');

        $policy = new UserPolicy;

        $this->assertFalse($policy->delete($admin, $beneficiary));
        $this->assertFalse($admin->can('delete', $beneficiary));
    }

    public function test_direct_user_delete_is_blocked(): void
    {
        $beneficiary = $this->makeBeneficiaryWithIdentity('direct-delete@example.com');

        $this->expectException(UserDeletionNotAllowedException::class);

        $beneficiary->delete();
    }

    public function test_force_delete_is_blocked(): void
    {
        $beneficiary = $this->makeBeneficiaryWithIdentity('force-delete@example.com');

        $this->expectException(UserDeletionNotAllowedException::class);

        $beneficiary->forceDelete();
    }

    public function test_system_admin_with_users_delete_cannot_delete_via_model_or_resource(): void
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'account_status' => AccountStatus::Active,
        ]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('users.delete');

        $beneficiary = $this->makeBeneficiaryWithIdentity('admin-bypass@example.com');

        $this->assertFalse(UserResource::canDelete($beneficiary));

        try {
            $beneficiary->delete();
            $this->fail('Expected UserDeletionNotAllowedException was not thrown.');
        } catch (UserDeletionNotAllowedException) {
            $this->assertDatabaseHas('users', ['id' => $beneficiary->id]);
        }
    }

    public function test_user_resource_can_delete_is_always_false(): void
    {
        $beneficiary = $this->makeBeneficiaryWithIdentity('resource-delete@example.com');

        $this->assertFalse(UserResource::canDelete($beneficiary));
    }

    public function test_filament_user_views_do_not_register_delete_actions(): void
    {
        $viewUserSource = file_get_contents(app_path('Filament/Resources/UserResource/Pages/ViewUser.php'));
        $userResourceSource = file_get_contents(app_path('Filament/Resources/UserResource.php'));

        $this->assertStringNotContainsString('DeleteAction', $viewUserSource);
        $this->assertStringNotContainsString('DeleteBulkAction', $userResourceSource);
        $this->assertStringNotContainsString('bulkActions', $userResourceSource);
    }

    public function test_account_deactivation_works_and_invalidates_sessions(): void
    {
        $staff = $this->makeStaffWithPermissions(['users.activate', 'beneficiaries.deactivate', 'users.view']);
        $beneficiary = $this->makeBeneficiaryWithIdentity('deactivate-me@example.com');

        DB::table('sessions')->insert([
            'id' => 'session-for-deactivation-test',
            'user_id' => $beneficiary->id,
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        app(AccountDeactivationService::class)->deactivate(
            $beneficiary,
            $staff,
            reason: 'اختبار تعطيل حساب',
        );

        $beneficiary->refresh();

        $this->assertFalse($beneficiary->is_active);
        $this->assertDatabaseMissing('sessions', ['user_id' => $beneficiary->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.deactivated',
            'target_user_id' => $beneficiary->id,
        ]);
    }

    public function test_toggling_is_active_to_false_invalidates_sessions(): void
    {
        $beneficiary = $this->makeBeneficiaryWithIdentity('toggle-off@example.com');

        DB::table('sessions')->insert([
            'id' => 'session-for-toggle-test',
            'user_id' => $beneficiary->id,
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $beneficiary->update(['is_active' => false]);

        $this->assertDatabaseMissing('sessions', ['user_id' => $beneficiary->id]);
        $this->assertDatabaseHas('users', ['id' => $beneficiary->id, 'is_active' => false]);
    }

    public function test_blocked_delete_does_not_cascade_remove_related_records(): void
    {
        $beneficiary = $this->makeBeneficiaryWithIdentity('cascade-check@example.com');
        $profileId = $beneficiary->profile?->id;

        try {
            $beneficiary->delete();
        } catch (UserDeletionNotAllowedException) {
            // expected
        }

        $this->assertDatabaseHas('users', ['id' => $beneficiary->id]);
        $this->assertDatabaseHas('profiles', ['id' => $profileId]);
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
            'account_status' => AccountStatus::Active,
        ]);
        $staff->assignRole('programs_management');
        $staff->givePermissionTo($permissions);

        return $staff;
    }

    private function makeBeneficiaryWithIdentity(string $email): User
    {
        $user = User::factory()->create([
            'role_type' => 'trainee',
            'is_active' => true,
            'email_verified_at' => now(),
            'email' => $email,
            'account_status' => AccountStatus::Active,
        ]);
        $user->assignRole('trainee');
        Profile::query()->create(['user_id' => $user->id]);

        return $user->fresh();
    }
}
