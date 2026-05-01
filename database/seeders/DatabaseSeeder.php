<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles must exist before any user seeder assigns them
        $this->call(RolesAndPermissionsSeeder::class);

        // Production admin from env vars — safe to call in all environments
        $this->call(AdminUserSeeder::class);

        // ─── Admin users ──────────────────────────────────────────────────────
        foreach ([
            ['email' => 'admin@kafaat.test',   'name' => 'مسؤول النظام'],
            ['email' => 'admin@example.com',   'name' => 'مسؤول النظام'],
        ] as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'      => $data['name'],
                    'password'  => Hash::make('password'),
                    'role_type' => 'admin',
                    'is_active' => true,
                ]
            );
            $user->syncRoles(['admin']);
            Profile::firstOrCreate(['user_id' => $user->id]);
        }

        // ─── Staff users ──────────────────────────────────────────────────────
        foreach ([
            ['email' => 'staff@kafaat.test', 'name' => 'موظف العمليات'],
            ['email' => 'staff@example.com', 'name' => 'موظف العمليات'],
        ] as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'      => $data['name'],
                    'password'  => Hash::make('password'),
                    'role_type' => 'staff',
                    'is_active' => true,
                ]
            );
            $user->syncRoles(['staff']);
            Profile::firstOrCreate(['user_id' => $user->id]);
        }

        // ─── Beneficiary users ────────────────────────────────────────────────
        $beneficiaries = [
            ['email' => 'beneficiary@kafaat.test',  'name' => 'أحمد العمري',    'city' => 'الرياض', 'gender' => 'male'],
            ['email' => 'beneficiary@example.com',  'name' => 'أحمد العمري',    'city' => 'الرياض', 'gender' => 'male'],
            ['email' => 'sara@example.com',         'name' => 'سارة الخالدي',   'city' => 'جدة',    'gender' => 'female'],
            ['email' => 'khalid@example.com',       'name' => 'خالد المطيري',   'city' => 'الدمام', 'gender' => 'male'],
        ];

        foreach ($beneficiaries as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'      => $data['name'],
                    'password'  => Hash::make('password'),
                    'role_type' => 'beneficiary',
                    'is_active' => true,
                ]
            );
            $user->syncRoles(['beneficiary']);
            Profile::firstOrCreate(
                ['user_id' => $user->id],
                ['city' => $data['city'], 'gender' => $data['gender']]
            );
        }

        // ─── Sample content + registrations ──────────────────────────────────
        $this->call([
            TrainingProgramSeeder::class,
            SampleDataSeeder::class,
            VolunteerOpportunitySeeder::class,
            NewsSeeder::class,
            RegistrationsSeeder::class,
        ]);
    }
}

