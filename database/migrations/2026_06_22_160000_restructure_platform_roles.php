<?php

use App\Services\Rbac\RbacCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RbacCatalog::applicationRoleNames() as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => RbacCatalog::GUARD_WEB,
            ]);
        }

        $map = RbacCatalog::legacyRoleMigrationMap();
        $userModel = app(\App\Models\User::class)->getMorphClass();

        foreach ($map as $legacy => $replacement) {
            $legacyRole = Role::query()
                ->where('name', $legacy)
                ->where('guard_name', RbacCatalog::GUARD_WEB)
                ->first();

            $newRole = Role::query()
                ->where('name', $replacement)
                ->where('guard_name', RbacCatalog::GUARD_WEB)
                ->first();

            if ($legacyRole === null || $newRole === null) {
                continue;
            }

            $userIds = DB::table('model_has_roles')
                ->where('role_id', $legacyRole->id)
                ->where('model_type', $userModel)
                ->pluck('model_id');

            foreach ($userIds as $userId) {
                $alreadyHas = DB::table('model_has_roles')
                    ->where('role_id', $newRole->id)
                    ->where('model_type', $userModel)
                    ->where('model_id', $userId)
                    ->exists();

                if (! $alreadyHas) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $newRole->id,
                        'model_type' => $userModel,
                        'model_id' => $userId,
                    ]);
                }

                DB::table('model_has_roles')
                    ->where('role_id', $legacyRole->id)
                    ->where('model_type', $userModel)
                    ->where('model_id', $userId)
                    ->delete();
            }
        }

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversible: legacy roles were removed from the catalog.
    }
};
