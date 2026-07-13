<?php

namespace App\Services\Rbac;

use App\Enums\AuditLogResult;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class StaffPermissionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * منح الموظف كل صلاحيات المصفوفة (عند الإنشاء).
     */
    public function grantAllAssignable(User $staff): void
    {
        $this->syncAssignablePermissions($staff, PermissionMatrixCatalog::assignablePermissionNames());
    }

    /**
     * @param  list<string>  $permissionNames
     */
    public function syncAssignablePermissions(User $staff, array $permissionNames, ?User $actor = null): void
    {
        $allowed = array_values(array_intersect(
            $permissionNames,
            PermissionMatrixCatalog::assignablePermissionNames(),
        ));

        $permissions = Permission::query()
            ->where('guard_name', RbacCatalog::GUARD_WEB)
            ->whereIn('name', $allowed)
            ->get();

        $staff->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($actor !== null) {
            $this->auditLogger->record(
                $actor,
                'staff.permissions_updated',
                AuditLogResult::Success,
                $staff,
                metadata: [
                    'permission_count' => count($allowed),
                    'permissions' => $allowed,
                ],
            );
        }
    }

    /**
     * ترحيل المستخدمين من الأدوار القديمة إلى النموذج الرباعي + صلاحيات مباشرة للموظفين.
     */
    public function migrateUsersToFourRoleModel(): void
    {
        $map = RbacCatalog::legacyRoleMigrationMap();

        User::query()->with(['roles.permissions', 'permissions'])->orderBy('id')->chunkById(100, function ($users) use ($map): void {
            foreach ($users as $user) {
                /** @var User $user */
                if ($user->isProtectedAdminUser() || $user->role_type === RbacCatalog::ROLE_ADMIN || $user->hasRole(RbacCatalog::ROLE_ADMIN)) {
                    $user->syncRoles([RbacCatalog::ROLE_ADMIN]);
                    $user->syncPermissions([]);
                    if ($user->role_type !== RbacCatalog::ROLE_ADMIN) {
                        $user->update(['role_type' => RbacCatalog::ROLE_ADMIN]);
                    }

                    continue;
                }

                $roleNames = $user->roles->pluck('name')->all();
                $target = null;

                foreach ($roleNames as $name) {
                    if ($name === RbacCatalog::ROLE_VOLUNTEER || ($map[$name] ?? null) === RbacCatalog::ROLE_VOLUNTEER) {
                        $target = RbacCatalog::ROLE_VOLUNTEER;
                        break;
                    }
                    if (in_array($name, [RbacCatalog::ROLE_STAFF, ...array_keys(array_filter($map, fn ($v) => $v === RbacCatalog::ROLE_STAFF))], true)
                        || ($map[$name] ?? null) === RbacCatalog::ROLE_STAFF
                        || $user->role_type === 'staff') {
                        $target = RbacCatalog::ROLE_STAFF;
                    }
                }

                if ($target === null) {
                    if ($user->role_type === 'volunteer' || in_array('volunteer', $roleNames, true)) {
                        $target = RbacCatalog::ROLE_VOLUNTEER;
                    } elseif ($user->role_type === 'staff') {
                        $target = RbacCatalog::ROLE_STAFF;
                    } else {
                        $target = RbacCatalog::ROLE_BENEFICIARY;
                    }
                }

                // احتفظ بصلاحيات الدور القديم قبل الحذف
                $inherited = [];
                foreach ($user->roles as $role) {
                    foreach ($role->permissions as $permission) {
                        $inherited[] = $permission->name;
                    }
                }
                foreach ($user->permissions as $permission) {
                    $inherited[] = $permission->name;
                }
                $inherited = array_values(array_unique($inherited));

                $user->syncRoles([$target]);

                if ($target === RbacCatalog::ROLE_STAFF) {
                    $assignable = PermissionMatrixCatalog::assignablePermissionNames();
                    $keep = array_values(array_intersect($inherited, $assignable));
                    if ($keep === []) {
                        $keep = $assignable;
                    }
                    $this->syncAssignablePermissions($user, $keep);
                    if ($user->role_type !== RbacCatalog::ROLE_STAFF) {
                        $user->update(['role_type' => RbacCatalog::ROLE_STAFF]);
                    }
                } else {
                    $user->syncPermissions([]);
                    $roleType = $target === RbacCatalog::ROLE_VOLUNTEER
                        ? RbacCatalog::ROLE_VOLUNTEER
                        : RbacCatalog::ROLE_BENEFICIARY;
                    if ($user->role_type !== $roleType) {
                        $user->update(['role_type' => $roleType]);
                    }
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * فرض وجود أدمن واحد محمي (حساب ADMIN_EMAIL إن وُجد، وإلا أقدم admin).
     */
    public function enforceSingleAdmin(): void
    {
        $adminEmail = config('app.admin_email');

        $primary = null;
        if (is_string($adminEmail) && $adminEmail !== '') {
            $primary = User::query()->where('email', $adminEmail)->first();
        }

        if ($primary === null) {
            $primary = User::query()
                ->where(function ($q): void {
                    $q->where('role_type', RbacCatalog::ROLE_ADMIN)
                        ->orWhereHas('roles', fn ($r) => $r->where('name', RbacCatalog::ROLE_ADMIN));
                })
                ->orderBy('id')
                ->first();
        }

        if ($primary === null) {
            return;
        }

        $primary->syncRoles([RbacCatalog::ROLE_ADMIN]);
        $primary->syncPermissions([]);
        $primary->update([
            'role_type' => RbacCatalog::ROLE_ADMIN,
            'is_active' => true,
        ]);

        User::query()
            ->whereKeyNot($primary->id)
            ->where(function ($q): void {
                $q->where('role_type', RbacCatalog::ROLE_ADMIN)
                    ->orWhereHas('roles', fn ($r) => $r->where('name', RbacCatalog::ROLE_ADMIN));
            })
            ->each(function (User $user): void {
                $user->syncRoles([RbacCatalog::ROLE_STAFF]);
                $user->update(['role_type' => RbacCatalog::ROLE_STAFF]);
                $this->grantAllAssignable($user);
            });
    }
}
