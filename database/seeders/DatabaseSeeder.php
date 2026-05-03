<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Full platform seed for local/staging. Safe to re-run: seeders use updateOrCreate / firstOrCreate.
 *
 * Run:
 *   php artisan db:seed
 *
 * Optional cleanup of prior demo domain data (paths, programs, registrations, volunteer rows, demo users):
 *   RESET_DEMO_DATA=true php artisan db:seed --class=CleanDemoDataSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->envFlag('RESET_DEMO_DATA')) {
            $this->call(CleanDemoDataSeeder::class);
        }

        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(EmployeeSeeder::class);
        $this->call(BeneficiaryUserSeeder::class);
        $this->call(LearningPathSeeder::class);
        $this->call(TrainingProgramSeeder::class);
        $this->call(VolunteerOpportunitySeeder::class);
        $this->call(ProgramRegistrationSeeder::class);
        $this->call(NewsSeeder::class);
    }

    private function envFlag(string $key, bool $default = false): bool
    {
        return filter_var(env($key, $default), FILTER_VALIDATE_BOOLEAN);
    }
}
