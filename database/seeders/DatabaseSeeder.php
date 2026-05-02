<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Environment flags (use with `php artisan db:seed`):
 *
 * SEED_DEMO_DATA=true
 *   Seeds demo/domain data for local/staging: staff users, learning paths, training programs,
 *   partners, volunteer opportunities, portal registrations, certificates, volunteer team.
 *   Default: off — do not enable in production unless you intentionally want this dataset.
 *
 * SEED_NEWS=true
 *   Runs NewsSeeder (truncates and repopulates the `news` table — see that class).
 *   Default: off so production deploys that run `db:seed` do not wipe curated news.
 *
 * RESET_DEMO_DATA=true
 *   Runs CleanDemoDataSeeder: removes demo training/volunteer domain rows and listed demo users.
 *   Default: off. If you enable this together with SEED_DEMO_DATA in one command, cleanup runs
 *   after demo seeding and will remove the data just seeded — use separate runs or only one flag.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(AdminUserSeeder::class);

        if ($this->envFlag('SEED_DEMO_DATA')) {
            $this->call(UserSeeder::class);
            $this->call(LearningPathSeeder::class);
            $this->call(TrainingProgramSeeder::class);
            $this->call(PartnerSeeder::class);
            $this->call(VolunteerOpportunitySeeder::class);
            $this->call(RegistrationsSeeder::class);
            $this->call(CertificateSeeder::class);
            $this->call(VolunteerTeamSeeder::class);
        }

        if ($this->envFlag('SEED_NEWS')) {
            $this->call(NewsSeeder::class);
        }

        if ($this->envFlag('RESET_DEMO_DATA')) {
            $this->call(CleanDemoDataSeeder::class);
        }
    }

    private function envFlag(string $key, bool $default = false): bool
    {
        return filter_var(env($key, $default), FILTER_VALIDATE_BOOLEAN);
    }
}
