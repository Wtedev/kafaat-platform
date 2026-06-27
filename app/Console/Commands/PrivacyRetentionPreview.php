<?php

namespace App\Console\Commands;

use App\Models\RetentionPolicy;
use App\Services\Privacy\Retention\RetentionPolicyEngine;
use Illuminate\Console\Command;

class PrivacyRetentionPreview extends Command
{
    protected $signature = 'privacy:retention-preview
                            {--policy= : Policy UUID}
                            {--resource= : Resource type code}
                            {--batch= : Batch size}
                            {--at= : Reference datetime (ISO8601)}';

    protected $description = 'Preview eligible records for retention policies without modifying data';

    public function handle(RetentionPolicyEngine $engine): int
    {
        $policy = $this->resolvePolicy();
        $resource = $this->option('resource');
        $batch = max(0, (int) $this->option('batch'));
        $at = $this->option('at') ? \Illuminate\Support\Carbon::parse($this->option('at')) : null;

        $run = $engine->preview($policy, is_string($resource) && $resource !== '' ? $resource : null, $at, $batch);

        $this->info('Retention preview completed.');
        $this->line('Run UUID: '.$run->uuid);
        $this->line('Eligible: '.$run->eligible_count);
        $this->line('Excluded (exceptions): '.$run->excluded_count);
        $this->line('Processed: '.$run->processed_count);
        $this->line('Succeeded: '.$run->succeeded_count);
        $this->line('Skipped: '.$run->skipped_count);
        $this->line('Failed: '.$run->failed_count);

        return $run->failed_count > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolvePolicy(): ?RetentionPolicy
    {
        $uuid = $this->option('policy');
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return RetentionPolicy::query()->where('uuid', $uuid)->firstOrFail();
    }
}
