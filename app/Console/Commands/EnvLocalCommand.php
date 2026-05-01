<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnvLocalCommand extends Command
{
    protected $signature = 'env:local';

    protected $description = 'Switch to local environment: SQLite database, debug on';

    public function handle(): int
    {
        $source = base_path('.env.local');

        if (! file_exists($source)) {
            $this->error('.env.local not found. Please create it first.');

            return self::FAILURE;
        }

        // Read template and resolve DB_DATABASE to absolute path
        $content = file_get_contents($source);
        $sqlitePath = database_path('database.sqlite');

        $content = preg_replace(
            '/^DB_DATABASE=.*$/m',
            'DB_DATABASE='.$sqlitePath,
            $content
        );

        file_put_contents(base_path('.env'), $content);

        // Ensure the SQLite file exists
        if (! file_exists($sqlitePath)) {
            touch($sqlitePath);
            $this->line("  Created SQLite database file: {$sqlitePath}");
        }

        $this->call('config:clear');
        $this->call('cache:clear');

        $this->newLine();
        $this->info('✅  Environment switched to [local].');
        $this->line('   Driver : SQLite');
        $this->line('   File   : '.$sqlitePath);
        $this->newLine();
        $this->line('  Run migrations : php artisan migrate');
        $this->line('  Seed roles     : php artisan db:seed --class=RolesAndPermissionsSeeder');
        $this->newLine();

        return self::SUCCESS;
    }
}
