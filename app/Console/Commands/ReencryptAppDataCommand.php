<?php

namespace App\Console\Commands;

use App\Services\Security\AppDataReencryptionService;
use Illuminate\Console\Command;

class ReencryptAppDataCommand extends Command
{
    protected $signature = 'security:reencrypt-app-data
                            {--dry-run : Scan and report without writing ciphertext}
                            {--force : Required when APP_ENV is production}
                            {--chunk=100 : Records loaded per chunk / transaction batch}';

    protected $description = 'Re-encrypt identity and privacy ciphertext with the current APP_KEY (supports APP_PREVIOUS_KEYS for decrypt)';

    public function handle(AppDataReencryptionService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $chunk = max(1, (int) $this->option('chunk'));

        if ($this->laravel->environment('production') && ! $force) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Mode: dry-run (no database writes)');
        } else {
            $this->info('Mode: write (re-encrypt)');
        }

        $this->line('Chunk size: '.$chunk);
        $this->newLine();

        $stats = $service->run(dryRun: $dryRun, chunkSize: $chunk);

        $this->line('scanned: '.$stats['scanned']);
        $this->line('eligible: '.$stats['eligible']);
        $this->line('reencrypted: '.$stats['reencrypted']);
        $this->line('skipped_null: '.$stats['skipped_null']);
        $this->line('failed: '.$stats['failed']);

        if ($stats['failed'] > 0) {
            $this->newLine();
            $this->error('One or more records failed. Re-run after investigating (exit non-zero).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
