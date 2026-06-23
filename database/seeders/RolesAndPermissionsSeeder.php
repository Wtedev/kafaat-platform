<?php

namespace Database\Seeders;

use App\Services\Rbac\RbacCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $validPermissions = RbacCatalog::allPermissionNames();
        $validRoles = RbacCatalog::applicationRoleNames();

        foreach ($validPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => RbacCatalog::GUARD_WEB,
            ]);
        }

        Permission::query()
            ->where('guard_name', RbacCatalog::GUARD_WEB)
            ->whereNotIn('name', $validPermissions)
            ->delete();

        $matrix = RbacCatalog::rolePermissionMatrix();

        foreach ($validRoles as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => RbacCatalog::GUARD_WEB,
            ]);

            $names = $matrix[$roleName] ?? [];
            $role->syncPermissions($names);
        }

        $obsoleteRoles = Role::query()
            ->where('guard_name', RbacCatalog::GUARD_WEB)
            ->whereNotIn('name', $validRoles)
            ->pluck('name', 'id');

        foreach ($obsoleteRoles as $roleId => $roleName) {
            DB::table('model_has_roles')
                ->where('role_id', $roleId)
                ->delete();

            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->delete();

            Role::query()->whereKey($roleId)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
