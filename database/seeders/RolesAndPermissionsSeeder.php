<?php

namespace Database\Seeders;

use App\Services\Rbac\RbacCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RbacCatalog::allPermissionNames() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => RbacCatalog::GUARD_WEB,
            ]);
        }

        $matrix = RbacCatalog::rolePermissionMatrix();

        foreach (RbacCatalog::applicationRoleNames() as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => RbacCatalog::GUARD_WEB,
            ]);

            $names = $matrix[$roleName] ?? [];
            $role->syncPermissions($names);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
