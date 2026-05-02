<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\TeamMember;
use App\Models\TeamNotification;
use App\Models\User;
use App\Models\VolunteerTeam;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * فريق كفاءات التطوعي — عضوات (قائدة، منسقة، عضوة) من مستخدمات ذوات دور المتطوع.
 * Does not rely on legacy demo emails; ensures three portal volunteer accounts exist (idempotent).
 */
class VolunteerTeamSeeder extends Seeder
{
    public function run(): void
    {
        if (! Role::query()->where('name', 'volunteer')->where('guard_name', RbacCatalog::GUARD_WEB)->exists()) {
            $this->command?->warn('VolunteerTeamSeeder: Spatie role "volunteer" not found. Run RolesAndPermissionsSeeder first.');

            return;
        }

        $coordinator = User::query()->where('email', 'eman.almutairi@kafaat.org.sa')->first();
        $creator = User::query()->where('email', 'lama.almeshiqeh@kafaat.org.sa')->first()
            ?? User::role('admin')->orderBy('id')->first();

        if ($coordinator === null && $creator === null) {
            $this->command?->warn('VolunteerTeamSeeder: no volunteering manager or admin found for team metadata. Skipping.');

            return;
        }

        $this->ensureVolunteerTeamPortalUsers();

        $team = VolunteerTeam::updateOrCreate(
            ['slug' => 'fariq-kafaat-altatawui'],
            [
                'name' => 'فريق كفاءات التطوعي',
                'description' => 'فريق نسائي يدعم تنظيم فعاليات التطوع ومبادرات المنصة المجتمعية، بالتنسيق مع إدارة التطوع.',
                'is_active' => true,
                'assigned_to' => $coordinator?->id,
                'created_by' => $creator?->id ?? $coordinator?->id,
            ]
        );

        $members = [
            ['email' => 'portal.user19@kafaat.org.sa', 'role_label' => 'قائدة الفريق'],
            ['email' => 'portal.user20@kafaat.org.sa', 'role_label' => 'منسقة'],
            ['email' => 'portal.user21@kafaat.org.sa', 'role_label' => 'عضوة'],
        ];

        foreach ($members as $row) {
            $user = User::query()->where('email', $row['email'])->first();
            if ($user === null) {
                $this->command?->warn("VolunteerTeamSeeder: user {$row['email']} missing; run RegistrationsSeeder or check ensureVolunteerTeamPortalUsers. Skipping member.");

                continue;
            }

            if (! $user->hasRole('volunteer')) {
                $user->syncRoles(['volunteer']);
            }

            TeamMember::firstOrCreate([
                'volunteer_team_id' => $team->id,
                'user_id' => $user->id,
            ]);
        }

        TeamNotification::updateOrCreate(
            [
                'volunteer_team_id' => $team->id,
                'title' => 'ترحيب بفريق كفاءات التطوعي',
            ],
            [
                'body' => "مرحباً بكن في الفريق.\nسيتم إعلامكن بجدولة الاجتماعات التعريفية والمهام عبر البوابة.",
                'published_at' => now()->subDays(2),
                'created_by' => $coordinator?->id,
            ]
        );
    }

    /**
     * Idempotent: same emails/names as RegistrationsSeeder volunteer block — creates or updates female volunteer portal users.
     */
    private function ensureVolunteerTeamPortalUsers(): void
    {
        $pw = Hash::make(env('SEED_USER_PASSWORD', 'Kafaat-Seed-2026-Secure!Change'));

        $defs = [
            ['email' => 'portal.user19@kafaat.org.sa', 'name' => 'نورة المطيري'],
            ['email' => 'portal.user20@kafaat.org.sa', 'name' => 'ريم الشهري'],
            ['email' => 'portal.user21@kafaat.org.sa', 'name' => 'لطيفة العتيبي'],
        ];

        foreach ($defs as $def) {
            $user = User::updateOrCreate(
                ['email' => $def['email']],
                [
                    'name' => $def['name'],
                    'password' => $pw,
                    'role_type' => 'trainee',
                    'is_active' => true,
                ]
            );
            $user->syncRoles(['volunteer']);
            Profile::firstOrCreate(['user_id' => $user->id]);
        }
    }
}
