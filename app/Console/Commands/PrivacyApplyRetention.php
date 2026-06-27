<?php

namespace App\Console\Commands;

use App\Models\RetentionPolicy;
use App\Models\RetentionRun;
use App\Services\Privacy\Retention\RetentionPolicyEngine;
use Illuminate\Console\Command;

class PrivacyApplyRetention extends Command
{
    protected $signature = 'privacy:apply-retention
                            {--policy= : Policy UUID}
                            {--resource= : Resource type code}
                            {--batch= : Batch size}
                            {--max-items= : Maximum items to process}
                            {--resume= : Resume run UUID}
                            {--dry-run : Preview logic without modifying data}';

    protected $description = 'Apply active retention policies to eligible records';

    public function handle(RetentionPolicyEngine $engine): int
    {
        $policy = $this->resolvePolicy();
        $resource = $this->option('resource');
        $batch = max(0, (int) $this->option('batch'));
        $maxItems = $this->option('max-items') !== null ? max(1, (int) $this->option('max-items')) : null;
        $resume = $this->resolveResumeRun();
        $dryRun = (bool) $this->option('dry-run');

        $run = $engine->execute(
            policy: $policy,
            resourceType: is_string($resource) && $resource !== '' ? $resource : null,
            batchSize: $batch,
            maxItems: $maxItems,
            resumeRun: $resume,
            dryRun: $dryRun,
            actor: null,
        );

        $this->info($dryRun ? 'Retention dry-run completed.' : 'Retention execution completed.');
        $this->line('Run UUID: '.$run->uuid);
        $this->line('Status: '.$run->status->value);
        $this->line('Eligible: '.$run->eligible_count);
        $this->line('Excluded: '.$run->excluded_count);
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

    private function resolveResumeRun(): ?RetentionRun
    {
        $uuid = $this->option('resume');
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return RetentionRun::query()->where('uuid', $uuid)->firstOrFail();
    }
}
