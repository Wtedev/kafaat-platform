<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use App\Services\Rbac\StaffPermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * حسابات موظفين للتجربة المحلية (كلمة المرور: password).
 */
class EmployeeSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $rows = [
            ['name' => 'حسام التويجري', 'email' => 'husam.altuwaijri@kafaat.org.sa'],
            ['name' => 'عبدالله السعوي', 'email' => 'abdullah.alsuwayyi@kafaat.org.sa'],
            ['name' => 'آمنة البطي', 'email' => 'amna.albatti@kafaat.org.sa'],
            ['name' => 'وجدان الصمعاني', 'email' => 'wejdan.alsumani@kafaat.org.sa'],
            ['name' => 'مالك القصير', 'email' => 'malik.alqasir@kafaat.org.sa'],
            ['name' => 'إيمان المطيري', 'email' => 'eman.almutairi@kafaat.org.sa'],
        ];

        $service = app(StaffPermissionService::class);

        foreach ($rows as $row) {
            $user = User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make(self::PASSWORD),
                    'role_type' => RbacCatalog::ROLE_STAFF,
                    'is_active' => true,
                ]
            );
            $user->syncRoles([RbacCatalog::ROLE_STAFF]);
            $service->grantAllAssignable($user);
            Profile::firstOrCreate(['user_id' => $user->id]);
            $this->command?->info("  Employee ready: {$row['email']} (موظف)");
        }
    }
}
