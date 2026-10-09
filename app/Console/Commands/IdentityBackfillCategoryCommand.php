<?php

namespace App\Console\Commands;

use App\Services\Identity\IdentityCategoryBackfillService;
use Illuminate\Console\Command;

class IdentityBackfillCategoryCommand extends Command
{
    protected $signature = 'identity:backfill-category
                            {--dry-run : Scan and report without writing}
                            {--chunk=100 : Users processed per batch}';

    protected $description = 'Derive identity_category / identity_type from encrypted identity numbers (counts only; never prints numbers)';

    public function handle(IdentityCategoryBackfillService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $this->info($dryRun ? 'Mode: dry-run (no database writes)' : 'Mode: write');
        $this->line('Chunk size: '.$chunk);
        $this->newLine();

        $stats = $service->run(dryRun: $dryRun, chunkSize: $chunk);

        $this->table(
            ['metric', 'count'],
            [
                ['scanned', $stats['scanned']],
                ['saudi', $stats['saudi']],
                ['resident', $stats['resident']],
                ['no_number', $stats['no_number']],
                ['type_mismatch', $stats['type_mismatch']],
                ['invalid_prefix', $stats['invalid_prefix']],
                ['decrypt_failed', $stats['decrypt_failed']],
                ['would_update_or_updated', $stats['updated']],
            ],
        );

        $this->newLine();
        $this->line('invalid_prefix rows were counted only and not modified.');

        return self::SUCCESS;
    }
}
