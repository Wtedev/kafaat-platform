<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Staff accounts for Filament (password: password). Roles use Spatie names from {@see RbacCatalog}.
 */
class EmployeeSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $rows = [
            ['name' => 'حسام التويجري', 'email' => 'husam.altuwaijri@kafaat.org.sa', 'role' => 'public_relations'],
            ['name' => 'عبدالله السعوي', 'email' => 'abdullah.alsuwayyi@kafaat.org.sa', 'role' => 'media'],
            ['name' => 'آمنة البطي', 'email' => 'amna.albatti@kafaat.org.sa', 'role' => 'training_enablement_manager'],
            ['name' => 'وجدان الصمعاني', 'email' => 'wejdan.alsumani@kafaat.org.sa', 'role' => 'programs_activities_manager'],
            ['name' => 'مالك القصير', 'email' => 'malik.alqasir@kafaat.org.sa', 'role' => 'programs_activities_manager'],
            ['name' => 'إيمان المطيري', 'email' => 'eman.almutairi@kafaat.org.sa', 'role' => 'volunteer_manager'],
        ];

        foreach ($rows as $row) {
            $user = User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make(self::PASSWORD),
                    'role_type' => 'staff',
                    'is_active' => true,
                ]
            );
            $user->syncRoles([$row['role']]);
            Profile::firstOrCreate(['user_id' => $user->id]);
            $this->command?->info("  Employee ready: {$row['email']} ({$row['role']})");
        }
    }
}
