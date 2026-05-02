<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
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
            ['name' => 'آمنة البطي', 'email' => 'amna.albatti@kafaat.org.sa', 'role' => 'training_manager'],
            ['name' => 'وجدان الصمعاني', 'email' => 'wajdan.alsumani@kafaat.org.sa', 'role' => 'training_manager'],
            ['name' => 'مالك القصير', 'email' => 'malik.alqasir@kafaat.org.sa', 'role' => 'training_manager'],
            ['name' => 'إيمان المطيري', 'email' => 'eman.almutairi@kafaat.org.sa', 'role' => 'volunteering_manager'],
            ['name' => 'حسام التويجري', 'email' => 'husam.altuwaijri@kafaat.org.sa', 'role' => 'pr_employee'],
            ['name' => 'فيصل الحميضان', 'email' => 'faisal.alhumaidan@kafaat.org.sa', 'role' => 'media_employee'],
            ['name' => 'عبدالله السعوي', 'email' => 'abdullah.alsuwi@kafaat.org.sa', 'role' => 'media_employee'],
        ];

        foreach ($staff as $row) {
            $user = User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => $pw,
                    'role_type' => 'staff',
                    'is_active' => true,
                ]
            );
            $user->syncRoles([$row['role']]);
            Profile::firstOrCreate(['user_id' => $user->id]);
        }
    }
}
