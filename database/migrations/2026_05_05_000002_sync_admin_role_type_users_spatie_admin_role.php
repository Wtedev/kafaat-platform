<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $hasAdminRole = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->exists();

        if (! $hasAdminRole) {
            return;
        }

        User::query()
            ->where('role_type', 'admin')
            ->each(function (User $user): void {
                $user->syncRoles(['admin']);
            });
    }

    public function down(): void
    {
        // لا عكس: إبقاء أدوار Spatie كما هي بعد التشغيل
    }
};
