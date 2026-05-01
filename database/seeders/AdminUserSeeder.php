<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Read credentials from environment ─────────────────────────────────
        $email    = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        $name     = env('ADMIN_NAME', 'System Admin');

        if (empty($email) || empty($password)) {
            $this->command->warn('');
            $this->command->warn('  AdminUserSeeder: ADMIN_EMAIL or ADMIN_PASSWORD is not set.');
            $this->command->warn('  Set both variables in your .env (local) or Railway environment');
            $this->command->warn('  variables (production) and re-run the seeder.');
            $this->command->warn('');
            return;
        }

        // ── Validate email format ─────────────────────────────────────────────
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command->error("  AdminUserSeeder: ADMIN_EMAIL \"{$email}\" is not a valid email address.");
            return;
        }

        // ── Validate password strength ────────────────────────────────────────
        $errors = $this->validatePassword($password);

        if (! empty($errors)) {
            $this->command->error('  AdminUserSeeder: ADMIN_PASSWORD does not meet strength requirements:');
            foreach ($errors as $error) {
                $this->command->error("    - {$error}");
            }
            return;
        }

        // ── Create or update admin user ───────────────────────────────────────
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'      => $name,
                'password'  => Hash::make($password),
                'role_type' => 'admin',
                'is_active' => true,
            ]
        );

        // Ensure Spatie role is assigned
        $user->syncRoles(['admin']);

        // Ensure a profile record exists
        Profile::firstOrCreate(['user_id' => $user->id]);

        $this->command->info("  Admin user ready: {$email}");
    }

    /**
     * Validate password complexity.
     * Returns an array of human-readable error strings (empty = passes).
     *
     * @return string[]
     */
    private function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 14) {
            $errors[] = 'Must be at least 14 characters long.';
        }

        if (! preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Must contain at least one uppercase letter (A–Z).';
        }

        if (! preg_match('/[a-z]/', $password)) {
            $errors[] = 'Must contain at least one lowercase letter (a–z).';
        }

        if (! preg_match('/[0-9]/', $password)) {
            $errors[] = 'Must contain at least one digit (0–9).';
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Must contain at least one special character (!@#$%^&* etc.).';
        }

        return $errors;
    }
}
