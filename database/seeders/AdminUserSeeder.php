<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent demo/production admins for local and staging seeds.
 *
 * Optional: set ADMIN_EMAIL + ADMIN_PASSWORD in .env for an additional admin with a strong password
 * (validated below). Seeded platform admins always use password "password".
 */
class AdminUserSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        foreach ($this->platformAdmins() as $row) {
            $user = User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'role_type' => 'admin',
                    'is_active' => true,
                ]
            );
            $user->syncRoles(['admin']);
            Profile::firstOrCreate(['user_id' => $user->id]);
            $this->command?->info("  Admin ready: {$row['email']}");
        }

        $this->seedOptionalEnvAdmin();
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    private function platformAdmins(): array
    {
        return [
            ['name' => 'عبدالسلام الصغير', 'email' => 'abdulsalam@kafaat.org.sa'],
            ['name' => 'لمى المشيقح', 'email' => 'lama@kafaat.org.sa'],
        ];
    }

    private function seedOptionalEnvAdmin(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        $name = env('ADMIN_NAME', 'System Admin');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command?->error("  AdminUserSeeder: ADMIN_EMAIL \"{$email}\" is not valid.");

            return;
        }

        $errors = $this->validatePassword($password);
        if ($errors !== []) {
            $this->command?->error('  AdminUserSeeder: ADMIN_PASSWORD does not meet strength requirements; skipping env admin.');
            foreach ($errors as $error) {
                $this->command?->error("    - {$error}");
            }

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role_type' => 'admin',
                'is_active' => true,
            ]
        );
        $user->syncRoles(['admin']);
        Profile::firstOrCreate(['user_id' => $user->id]);
        $this->command?->info("  Env admin ready: {$email}");
    }

    /**
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
