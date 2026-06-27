<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Data\Privacy\Retention\RetentionActionResult;
use App\Enums\PrivacyExportFileStatus;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\PrivacyExportFile;
use App\Models\RetentionPolicy;
use App\Models\RetentionRun;
use App\Models\RetentionRunItem;
use App\Services\Privacy\Export\PersonalDataExportService;
use App\Services\Privacy\Retention\RetentionExceptionChecker;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class PrivacyExportRetentionHandler extends AbstractRetentionHandler
{
    public function __construct(
        RetentionExceptionChecker $exceptionChecker,
        private readonly PersonalDataExportService $exportService,
    ) {
        parent::__construct($exceptionChecker);
    }

    public function resourceType(): string
    {
        return 'privacy_export_files';
    }

    public function supportedActions(): array
    {
        return [RetentionPolicyAction::Delete];
    }

    public function supportedTriggers(): array
    {
        return [RetentionTriggerEvent::ExpiredAt];
    }

    public function hasFiles(): bool
    {
        return true;
    }

    public function eligibleQuery(RetentionPolicy $policy, CarbonInterface $cutoff): Builder
    {
        return PrivacyExportFile::query()
            ->where('status', PrivacyExportFileStatus::Ready->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff);
    }

    public function sourceId(object $record): ?int
    {
        return $record instanceof PrivacyExportFile ? $record->id : null;
    }

    public function opaqueIdentifier(object $record): string
    {
        if ($record instanceof PrivacyExportFile) {
            return $record->uuid;
        }

        return parent::opaqueIdentifier($record);
    }

    public function process(
        RetentionPolicy $policy,
        RetentionRun $run,
        ?RetentionRunItem $item,
        object $record,
        bool $dryRun,
    ): RetentionActionResult {
        if (! $record instanceof PrivacyExportFile) {
            return RetentionActionResult::failed('invalid_record');
        }

        if ($this->isExcludedByException($record, $policy)) {
            return RetentionActionResult::skipped('retention_exception');
        }

        if ($dryRun) {
            return RetentionActionResult::succeeded();
        }

        return $this->exportService->purgeExpiredExport($record)
            ? RetentionActionResult::succeeded()
            : RetentionActionResult::failed('purge_failed');
    }
}
