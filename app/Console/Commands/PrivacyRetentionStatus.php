<?php

namespace App\Console\Commands;

use App\Enums\RetentionRunMode;
use App\Enums\RetentionRunStatus;
use App\Models\RetentionPolicy;
use App\Models\RetentionRun;
use Illuminate\Console\Command;

class PrivacyRetentionStatus extends Command
{
    protected $signature = 'privacy:retention-status';

    protected $description = 'Show retention scheduler health without PII';

    public function handle(): int
    {
        $activePolicies = RetentionPolicy::query()->where('status', 'active')->count();
        $lastExecute = RetentionRun::query()
            ->where('mode', RetentionRunMode::Execute)
            ->whereIn('status', [
                RetentionRunStatus::Completed,
                RetentionRunStatus::CompletedWithFailures,
            ])
            ->latest('completed_at')
            ->first();

        $lastPreview = RetentionRun::query()
            ->where('mode', RetentionRunMode::Preview)
            ->where('status', RetentionRunStatus::Completed)
            ->latest('completed_at')
            ->first();

        $this->info('Retention engine status');
        $this->line('Active policies: '.$activePolicies);
        $this->line('Last successful execute: '.($lastExecute?->completed_at?->toIso8601String() ?? 'never'));
        $this->line('Last execute run UUID: '.($lastExecute?->uuid ?? '—'));
        $this->line('Last preview: '.($lastPreview?->completed_at?->toIso8601String() ?? 'never'));
        $this->line('Scheduler requires: * * * * * php artisan schedule:run');

        return self::SUCCESS;
    }
}
