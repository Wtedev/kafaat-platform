<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use App\Services\Rbac\StaffPermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * فريق المنصة: مسؤولو النظام والموظفون التشغيليون (ليسوا ضمن مجموعة الـ 25 مستخدماً للبوابة).
 */
class UserSeeder extends Seeder
{
    private function passwordHash(): string
    {
        return Hash::make(env('SEED_USER_PASSWORD', 'Kafaat-Seed-2026-Secure!Change'));
    }

    public function run(): void
    {
        $pw = $this->passwordHash();

        $admins = [
            ['name' => 'لمى المشيقح', 'email' => 'lama.almeshiqeh@kafaat.org.sa', 'role_type' => 'admin', 'spatie' => 'admin'],
            ['name' => 'عبدالسلام الصغير', 'email' => 'abdulsalam.alsagheer@kafaat.org.sa', 'role_type' => 'admin', 'spatie' => 'admin'],
        ];

        foreach ($admins as $row) {
            $user = User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => $pw,
                    'role_type' => $row['role_type'],
                    'is_active' => true,
                ]
            );
            $user->syncRoles([$row['spatie']]);
            Profile::firstOrCreate(['user_id' => $user->id]);
        }

        $staff = [
            ['name' => 'آمنة البطي', 'email' => 'amna.albatti@kafaat.org.sa'],
            ['name' => 'وجدان الصمعاني', 'email' => 'wajdan.alsumani@kafaat.org.sa'],
            ['name' => 'مالك القصير', 'email' => 'malik.alqasir@kafaat.org.sa'],
            ['name' => 'إيمان المطيري', 'email' => 'eman.almutairi@kafaat.org.sa'],
            ['name' => 'حسام التويجري', 'email' => 'husam.altuwaijri@kafaat.org.sa'],
            ['name' => 'فيصل الحميضان', 'email' => 'faisal.alhumaidan@kafaat.org.sa'],
            ['name' => 'عبدالله السعوي', 'email' => 'abdullah.alsuwi@kafaat.org.sa'],
        ];

        $staffPermissions = app(StaffPermissionService::class);

        foreach ($staff as $row) {
            $user = User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => $pw,
                    'role_type' => RbacCatalog::ROLE_STAFF,
                    'is_active' => true,
                ]
            );
            $user->syncRoles([RbacCatalog::ROLE_STAFF]);
            $staffPermissions->grantAllAssignable($user);
            Profile::firstOrCreate(['user_id' => $user->id]);
        }
    }
}
