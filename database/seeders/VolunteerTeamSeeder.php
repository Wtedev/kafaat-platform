<?php

namespace Database\Seeders;

use App\Models\TeamMember;
use App\Models\TeamNotification;
use App\Models\User;
use App\Models\VolunteerTeam;
use Illuminate\Database\Seeder;

class VolunteerTeamSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $volunteeringManager = User::role('volunteering_manager')->first();
        $beneficiary = User::where('email', 'beneficiary@example.com')->first();
        $sara = User::where('email', 'sara@example.com')->first();

        if (! $beneficiary || ! $sara) {
            $this->command->warn('VolunteerTeamSeeder: sample users not found — skipping.');

            return;
        }

        $team = VolunteerTeam::firstOrCreate(
            ['slug' => 'fariq-multaqa-kafaat'],
            [
                'name' => 'فريق ملتقى كفاءات',
                'description' => 'فريق تطوعي لدعم تنظيم فعاليات المنصة.',
                'is_active' => true,
                'assigned_to' => $volunteeringManager?->id,
                'created_by' => $admin?->id ?? $volunteeringManager?->id,
            ]
        );

        foreach ([$beneficiary, $sara] as $user) {
            TeamMember::firstOrCreate([
                'volunteer_team_id' => $team->id,
                'user_id' => $user->id,
            ]);
        }

        TeamNotification::firstOrCreate(
            [
                'volunteer_team_id' => $team->id,
                'title' => 'اجتماع تعريفي للفريق',
            ],
            [
                'body' => "مرحباً بكم في الفريق.\nالاجتماع التعريفي يوم الأحد القادم الساعة 5 مساءً عبر الرابط الذي سيُرسل على البريد.",
                'published_at' => now()->subDay(),
                'created_by' => $volunteeringManager?->id ?? $admin?->id,
            ]
        );
    }
}
