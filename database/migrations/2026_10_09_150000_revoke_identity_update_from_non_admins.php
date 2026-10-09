<?php

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'beneficiaries.identity.update')
            ->where('guard_name', RbacCatalog::GUARD_WEB)
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        $userModel = (new User)->getMorphClass();
        $adminRoleId = DB::table('roles')
            ->where('name', RbacCatalog::ROLE_ADMIN)
            ->where('guard_name', RbacCatalog::GUARD_WEB)
            ->value('id');

        $adminUserIds = DB::table('users')
            ->where('role_type', RbacCatalog::ROLE_ADMIN)
            ->pluck('id');

        if ($adminRoleId !== null) {
            $adminUserIds = $adminUserIds->merge(
                DB::table('model_has_roles')
                    ->where('role_id', $adminRoleId)
                    ->where('model_type', $userModel)
                    ->pluck('model_id'),
            )->unique()->values();
        }

        $direct = DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('model_type', $userModel);

        if ($adminUserIds->isNotEmpty()) {
            $direct->whereNotIn('model_id', $adminUserIds);
        }

        $direct->delete();

        $roles = DB::table('role_has_permissions')
            ->where('permission_id', $permissionId);

        if ($adminRoleId !== null) {
            $roles->where('role_id', '!=', $adminRoleId);
        }

        $roles->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // لا تُستعاد التعيينات المباشرة؛ الصلاحية تبقى لدور الأدمن فقط.
    }
};
