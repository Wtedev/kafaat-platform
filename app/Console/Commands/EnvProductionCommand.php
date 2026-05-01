<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnvProductionCommand extends Command
{
    protected $signature = 'env:production';

    protected $description = 'Switch to production environment: PostgreSQL (Supabase), debug off';

    public function handle(): int
    {
        $source = base_path('.env.production');

        if (! file_exists($source)) {
            $this->error('.env.production not found. Please create it first.');
            return self::FAILURE;
        }

        // Safety confirmation — production affects live data
        $this->newLine();
        $this->warn('  ⚠️   SWITCHING TO PRODUCTION ENVIRONMENT');
        $this->warn('  This will point your app at the live Supabase database.');
        $this->warn('  Running seeders or migrations here affects real data.');
        $this->newLine();

        if (! $this->confirm('Continue switching to production?', false)) {
            $this->info('Cancelled — environment unchanged.');
            return self::SUCCESS;
        }

        file_put_contents(base_path('.env'), file_get_contents($source));

        $this->call('config:clear');
        $this->call('cache:clear');

        $this->newLine();
        $this->info('✅  Environment switched to [production].');
        $this->line('   Driver : PostgreSQL (Supabase)');
        $this->newLine();
        $this->line('  Run migrations : php artisan migrate --force');
        $this->line('  Seed roles     : php artisan db:seed --class=RolesAndPermissionsSeeder --force');
        $this->newLine();

        return self::SUCCESS;
    }
}
