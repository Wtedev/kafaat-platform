<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Support\UserInlineEditSupport;
use App\Models\Profile;
use App\Models\User;
use App\Support\UserAccountRoleForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class UserAccountInlineEditTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_change_beneficiary_email_via_account_inline_edit(): void
    {
        $admin = $this->makeAdmin();
        $beneficiary = $this->makeBeneficiary([
            'email' => 'beneficiary-before@example.com',
            'password' => Hash::make('SecretPass1!'),
            'email_verified_at' => now(),
        ]);
        $originalPassword = $beneficiary->password;
        $originalRoles = $beneficiary->roles->pluck('name')->sort()->values()->all();
        $originalPermissions = $beneficiary->getAllPermissions()->pluck('name')->sort()->values()->all();

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $beneficiary->getKey()])
            ->assertSuccessful()
            ->mountAction('editEntityField', ['field' => 'account'])
            ->assertActionMounted('editEntityField')
            ->setActionData([
                'name' => $beneficiary->name,
                'email' => 'beneficiary-after@example.com',
                'phone' => $beneficiary->phone,
                'password' => null,
                'is_active' => true,
                'notify_email' => true,
                'platform_role' => 'beneficiary',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertHasNoErrors()
            ->assertNotified('تم تحديث معلومات الحساب بنجاح')
            ->assertSee('beneficiary-after@example.com');

        $beneficiary->refresh();
        $beneficiary->load('roles');

        $this->assertSame('beneficiary-after@example.com', $beneficiary->email);
        $this->assertSame($originalPassword, $beneficiary->password);
        $this->assertNull($beneficiary->email_verified_at);
        $this->assertSame($originalRoles, $beneficiary->roles->pluck('name')->sort()->values()->all());
        $this->assertSame(
            $originalPermissions,
            $beneficiary->getAllPermissions()->pluck('name')->sort()->values()->all(),
        );
        $this->assertTrue($beneficiary->hasRole('beneficiary'));
    }

    public function test_staff_with_users_update_can_change_email(): void
    {
        $staff = $this->makeStaffWithPermissions(['users.view', 'users.update']);
        $beneficiary = $this->makeBeneficiary(['email' => 'staff-edit-before@example.com']);

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($staff)
            ->test(ViewUser::class, ['record' => $beneficiary->getKey()])
            ->mountAction('editEntityField', ['field' => 'account'])
            ->setActionData([
                'name' => $beneficiary->name,
                'email' => 'staff-edit-after@example.com',
                'phone' => $beneficiary->phone,
                'is_active' => true,
                'notify_email' => (bool) $beneficiary->notify_email,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('تم تحديث معلومات الحساب بنجاح');

        $this->assertSame('staff-edit-after@example.com', $beneficiary->fresh()->email);
    }

    public function test_view_only_staff_cannot_save_account_inline_edit(): void
    {
        $staff = $this->makeStaffWithPermissions(['users.view']);
        $beneficiary = $this->makeBeneficiary(['email' => 'view-only-target@example.com']);

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($staff)
            ->test(ViewUser::class, ['record' => $beneficiary->getKey()])
            ->call('mountAction', 'editEntityField', ['field' => 'account'])
            ->call('callMountedAction')
            ->assertForbidden();

        $this->assertSame('view-only-target@example.com', $beneficiary->fresh()->email);
    }

    public function test_email_only_change_preserves_password_roles_permissions_and_profile(): void
    {
        $admin = $this->makeAdmin();
        $beneficiary = $this->makeBeneficiary([
            'email' => 'preserve-before@example.com',
            'password' => Hash::make('KeepPass1!'),
            'phone' => '0500000001',
        ]);
        $profile = Profile::query()->create([
            'user_id' => $beneficiary->id,
            'city' => 'الرياض',
            'job_title' => 'متدرب',
            'bio' => 'نبذة ثابتة',
        ]);
        $passwordHash = $beneficiary->password;
        $roleNames = $beneficiary->roles->pluck('name')->sort()->values()->all();
        $permissionNames = $beneficiary->getAllPermissions()->pluck('name')->sort()->values()->all();

        $this->saveAccountViaLivewire($admin, $beneficiary, [
            'name' => $beneficiary->name,
            'email' => 'preserve-after@example.com',
            'phone' => $beneficiary->phone,
            'is_active' => true,
            'notify_email' => true,
            'platform_role' => 'beneficiary',
        ]);

        $beneficiary->refresh();
        $profile->refresh();

        $this->assertSame('preserve-after@example.com', $beneficiary->email);
        $this->assertSame($passwordHash, $beneficiary->password);
        $this->assertSame($roleNames, $beneficiary->roles()->pluck('name')->sort()->values()->all());
        $this->assertSame(
            $permissionNames,
            $beneficiary->getAllPermissions()->pluck('name')->sort()->values()->all(),
        );
        $this->assertSame('الرياض', $profile->city);
        $this->assertSame('متدرب', $profile->job_title);
        $this->assertSame('نبذة ثابتة', $profile->bio);
    }

    public function test_empty_password_keeps_existing_hash(): void
    {
        $admin = $this->makeAdmin();
        $beneficiary = $this->makeBeneficiary([
            'email' => 'pwd-keep@example.com',
            'password' => Hash::make('OriginalPass1!'),
        ]);
        $original = $beneficiary->password;

        $this->saveAccountViaLivewire($admin, $beneficiary, [
            'name' => $beneficiary->name,
            'email' => 'pwd-keep@example.com',
            'phone' => $beneficiary->phone,
            'password' => '',
            'is_active' => true,
            'notify_email' => true,
            'platform_role' => 'beneficiary',
        ]);

        $this->assertSame($original, $beneficiary->fresh()->password);
        $this->assertTrue(Hash::check('OriginalPass1!', $beneficiary->fresh()->password));
    }

    public function test_new_password_is_hashed_once(): void
    {
        $admin = $this->makeAdmin();
        $beneficiary = $this->makeBeneficiary([
            'email' => 'pwd-change@example.com',
            'password' => Hash::make('OldPass1!'),
        ]);

        $this->saveAccountViaLivewire($admin, $beneficiary, [
            'name' => $beneficiary->name,
            'email' => 'pwd-change@example.com',
            'phone' => $beneficiary->phone,
            'password' => 'BrandNewPass1!',
            'is_active' => true,
            'notify_email' => true,
            'platform_role' => 'beneficiary',
        ]);

        $beneficiary->refresh();
        $this->assertTrue(Hash::check('BrandNewPass1!', $beneficiary->password));
        $this->assertFalse(Hash::check('OldPass1!', $beneficiary->password));
    }

    public function test_duplicate_email_shows_arabic_validation_without_500(): void
    {
        $admin = $this->makeAdmin();
        $this->makeBeneficiary(['email' => 'taken@example.com']);
        $beneficiary = $this->makeBeneficiary(['email' => 'free@example.com']);

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $beneficiary->getKey()])
            ->mountAction('editEntityField', ['field' => 'account'])
            ->setActionData([
                'name' => $beneficiary->name,
                'email' => 'taken@example.com',
                'phone' => $beneficiary->phone,
                'is_active' => true,
                'notify_email' => true,
                'platform_role' => 'beneficiary',
            ])
            ->callMountedAction()
            ->assertHasErrors(['email' => 'البريد الإلكتروني مستخدم بالفعل.'])
            ->assertSuccessful();

        $this->assertSame('free@example.com', $beneficiary->fresh()->email);
    }

    public function test_email_trim_and_lowercase_normalization(): void
    {
        $admin = $this->makeAdmin();
        $beneficiary = $this->makeBeneficiary(['email' => 'normalize-me@example.com']);

        UserInlineEditSupport::persistAccountSection($beneficiary, [
            'name' => $beneficiary->name,
            'email' => '  New.Email@Example.COM  ',
            'phone' => $beneficiary->phone,
            'is_active' => true,
            'notify_email' => true,
            'platform_role' => 'beneficiary',
        ], $admin);

        $this->assertSame('new.email@example.com', $beneficiary->fresh()->email);

        $this->saveAccountViaLivewire($admin, $beneficiary->fresh(), [
            'name' => $beneficiary->name,
            'email' => 'Livewire.Case@Example.COM',
            'phone' => $beneficiary->phone,
            'is_active' => true,
            'notify_email' => true,
            'platform_role' => 'beneficiary',
        ]);

        $this->assertSame('livewire.case@example.com', $beneficiary->fresh()->email);
    }

    public function test_case_insensitive_email_uniqueness(): void
    {
        $admin = $this->makeAdmin();
        $this->makeBeneficiary(['email' => 'caseuser@example.com']);
        $beneficiary = $this->makeBeneficiary(['email' => 'other-case@example.com']);

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $beneficiary->getKey()])
            ->mountAction('editEntityField', ['field' => 'account'])
            ->setActionData([
                'name' => $beneficiary->name,
                'email' => 'CaseUser@Example.com',
                'phone' => $beneficiary->phone,
                'is_active' => true,
                'notify_email' => true,
                'platform_role' => 'beneficiary',
            ])
            ->callMountedAction()
            ->assertHasErrors(['email' => 'البريد الإلكتروني مستخدم بالفعل.']);

        $this->assertSame('other-case@example.com', $beneficiary->fresh()->email);
    }

    public function test_name_phone_is_active_and_notify_email_update(): void
    {
        $admin = $this->makeAdmin();
        $beneficiary = $this->makeBeneficiary([
            'email' => 'fields@example.com',
            'name' => 'اسم قديم',
            'phone' => '0501111111',
            'is_active' => true,
            'notify_email' => true,
        ]);

        $this->saveAccountViaLivewire($admin, $beneficiary, [
            'name' => 'اسم جديد',
            'email' => 'fields@example.com',
            'phone' => '0502222222',
            'is_active' => false,
            'notify_email' => false,
            'platform_role' => 'beneficiary',
        ]);

        $beneficiary->refresh();
        $this->assertSame('اسم جديد', $beneficiary->name);
        $this->assertSame('0502222222', $beneficiary->phone);
        $this->assertFalse($beneficiary->is_active);
        $this->assertFalse($beneficiary->notify_email);
    }

    public function test_authorized_platform_role_sync_changes_role_type(): void
    {
        $admin = $this->makeAdmin();
        $beneficiary = $this->makeBeneficiary(['email' => 'role-sync@example.com']);

        $this->saveAccountViaLivewire($admin, $beneficiary, [
            'name' => $beneficiary->name,
            'email' => 'role-sync@example.com',
            'phone' => $beneficiary->phone,
            'is_active' => true,
            'notify_email' => true,
            'platform_role' => 'volunteer',
        ]);

        $beneficiary->refresh();
        $this->assertTrue($beneficiary->hasRole('volunteer'));
        $this->assertFalse($beneficiary->hasRole('beneficiary'));
        $this->assertSame('volunteer', $beneficiary->role_type);
    }

    public function test_unauthorized_platform_role_is_ignored_server_side(): void
    {
        $staff = $this->makeStaffWithPermissions(['users.view', 'users.update']);
        $beneficiary = $this->makeBeneficiary(['email' => 'role-ignore@example.com']);

        $this->assertFalse(
            UserAccountRoleForm::canActorEditRoleSection($staff, $beneficiary),
        );

        $this->saveAccountViaLivewire($staff, $beneficiary, [
            'name' => $beneficiary->name,
            'email' => 'role-ignore@example.com',
            'phone' => $beneficiary->phone,
            'is_active' => true,
            'notify_email' => true,
            'platform_role' => 'staff',
        ]);

        $beneficiary->refresh();
        $this->assertTrue($beneficiary->hasRole('beneficiary'));
        $this->assertSame('beneficiary', $beneficiary->role_type);
        $this->assertFalse($beneficiary->hasRole('staff'));
    }

    public function test_account_save_rolls_back_on_failure(): void
    {
        $admin = $this->makeAdmin();
        $beneficiary = $this->makeBeneficiary([
            'email' => 'rollback@example.com',
            'name' => 'قبل الفشل',
        ]);

        $failForId = (int) $beneficiary->getKey();
        User::updating(function (User $user) use ($failForId): void {
            if ((int) $user->getKey() === $failForId && $user->isDirty('email')) {
                throw new \RuntimeException('forced failure for rollback');
            }
        });

        try {
            UserInlineEditSupport::persistAccountSection($beneficiary, [
                'name' => 'بعد الفشل',
                'email' => 'rollback-changed@example.com',
                'phone' => $beneficiary->phone,
                'is_active' => true,
                'notify_email' => true,
                'platform_role' => 'beneficiary',
            ], $admin);
            $this->fail('Expected rollback failure did not throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('forced failure for rollback', $exception->getMessage());
        }

        $beneficiary->refresh();
        $this->assertSame('rollback@example.com', $beneficiary->email);
        $this->assertSame('قبل الفشل', $beneficiary->name);

        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $beneficiary->getKey()])
            ->mountAction('editEntityField', ['field' => 'account'])
            ->setActionData([
                'name' => 'بعد الفشل',
                'email' => 'rollback-changed@example.com',
                'phone' => $beneficiary->phone,
                'is_active' => true,
                'notify_email' => true,
                'platform_role' => 'beneficiary',
            ])
            ->callMountedAction()
            ->assertNotified('تعذّر حفظ معلومات الحساب. لم تُجرَ أي تغييرات، يرجى المحاولة مرة أخرى.');

        $beneficiary->refresh();
        $this->assertSame('rollback@example.com', $beneficiary->email);
        $this->assertSame('قبل الفشل', $beneficiary->name);
    }

    public function test_account_allowlist_is_explicit(): void
    {
        $this->assertSame(
            ['name', 'email', 'phone', 'password', 'is_active', 'notify_email', 'platform_role'],
            UserInlineEditSupport::accountPersistAllowlist(),
        );
    }

    public function test_protected_admin_role_is_not_mutated_via_account_inline_edit(): void
    {
        $admin = $this->makeAdmin();
        $protected = $this->makeAdmin();
        config(['app.admin_email' => $protected->email]);

        $this->assertTrue($protected->fresh()->isProtectedAdminUser());

        $this->saveAccountViaLivewire($admin, $protected, [
            'name' => $protected->name,
            'email' => $protected->email,
            'phone' => $protected->phone,
            'is_active' => true,
            'notify_email' => true,
            'platform_role' => 'staff',
        ]);

        $protected->refresh();
        $this->assertTrue($protected->hasRole('admin'));
        $this->assertSame('admin', $protected->role_type);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveAccountViaLivewire(User $actor, User $target, array $data): void
    {
        $this->withSession(['otp_verified' => true]);

        Livewire::actingAs($actor)
            ->test(ViewUser::class, ['record' => $target->getKey()])
            ->assertSuccessful()
            ->mountAction('editEntityField', ['field' => 'account'])
            ->setActionData($data)
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('تم تحديث معلومات الحساب بنجاح');
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeStaffWithPermissions(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('staff');
        $staff->syncPermissions($permissions);

        return $staff->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeBeneficiary(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'notify_email' => true,
        ], $overrides));
        $user->assignRole('beneficiary');

        return $user->fresh(['roles']);
    }
}
