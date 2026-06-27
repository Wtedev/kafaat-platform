<?php

namespace App\Services\Privacy\Export;

use App\Data\Privacy\Export\PersonalDataExportBundle;
use App\Enums\AccountStatus;
use App\Enums\AuditLogResult;
use App\Enums\PrivacyExportFileStatus;
use App\Enums\PrivacyRequestEventType;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Enums\UserActivityAction;
use App\Jobs\GeneratePersonalDataExport;
use App\Models\PrivacyExportFile;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\CvDocumentService;
use App\Services\Privacy\PrivacyRequestNotificationService;
use App\Services\Privacy\PrivacyRequestService;
use App\Services\UserActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class PersonalDataExportService
{
    private const LOCK_PREFIX = 'privacy-export-generate:';

    public function __construct(
        private readonly CvDocumentService $cvDocumentService,
        private readonly PrivacyExportArchiveWriter $archiveWriter,
        private readonly AuditLogger $auditLogger,
        private readonly PrivacyRequestNotificationService $notifications,
    ) {}

    public function dispatchGeneration(PrivacyRequest $privacyRequest, User $actor): PrivacyRequest
    {
        if ($privacyRequest->request_type !== PrivacyRequestType::DataExport) {
            throw new InvalidArgumentException('Not a data export request.');
        }

        if (! in_array($privacyRequest->status, [PrivacyRequestStatus::Approved, PrivacyRequestStatus::PartiallyApproved], true)) {
            throw new InvalidArgumentException('Export request is not approved.');
        }

        if (! $actor->can('privacy_requests.export.generate')) {
            throw new AuthorizationException('You cannot generate exports.');
        }

        $existing = $privacyRequest->exportFile;
        if ($existing !== null && $existing->status === PrivacyExportFileStatus::Ready) {
            throw new InvalidArgumentException('Export file is already ready.');
        }

        return DB::transaction(function () use ($privacyRequest, $actor): PrivacyRequest {
            $exportFile = PrivacyExportFile::query()->updateOrCreate(
                ['privacy_request_id' => $privacyRequest->id],
                [
                    'uuid' => $privacyRequest->exportFile?->uuid ?? (string) Str::uuid(),
                    'user_id' => $privacyRequest->user_id,
                    'disk' => (string) config('privacy.export.disk', 'private_documents'),
                    'path' => '',
                    'format' => 'zip',
                    'status' => PrivacyExportFileStatus::Pending->value,
                    'failure_code' => null,
                ],
            );

            app(PrivacyRequestService::class)->markExportProcessing($privacyRequest->fresh(), $actor);

            $this->auditLogger->recordOrFail(
                $actor,
                'privacy_export.generation_started',
                AuditLogResult::Success,
                $privacyRequest->user,
                metadata: [
                    'privacy_request_uuid' => $privacyRequest->uuid,
                    'export_uuid' => $exportFile->uuid,
                ],
            );

            GeneratePersonalDataExport::dispatch($privacyRequest->id);

            return $privacyRequest->fresh(['exportFile']);
        });
    }

    public function retryGeneration(PrivacyRequest $privacyRequest, User $actor): PrivacyRequest
    {
        if ($privacyRequest->exportFile?->status !== PrivacyExportFileStatus::Failed) {
            throw new InvalidArgumentException('Only failed exports can be retried.');
        }

        if (! $actor->can('privacy_requests.export.retry')) {
            throw new AuthorizationException('You cannot retry exports.');
        }

        $privacyRequest->forceFill(['status' => PrivacyRequestStatus::Approved])->save();
        $privacyRequest->exportFile?->forceFill([
            'status' => PrivacyExportFileStatus::Pending->value,
            'failure_code' => null,
        ])->save();

        $this->auditLogger->recordOrFail(
            $actor,
            'privacy_export.retry_requested',
            AuditLogResult::Success,
            $privacyRequest->user,
            metadata: [
                'privacy_request_uuid' => $privacyRequest->uuid,
                'export_uuid' => $privacyRequest->exportFile?->uuid,
            ],
        );

        return $this->dispatchGeneration($privacyRequest->fresh(['exportFile']), $actor);
    }

    public function generateForRequest(int $privacyRequestId): void
    {
        $lock = Cache::lock(self::LOCK_PREFIX.$privacyRequestId, 600);

        if (! $lock->get()) {
            return;
        }

        try {
            $privacyRequest = PrivacyRequest::query()
                ->with(['user.profile', 'exportFile'])
                ->find($privacyRequestId);

            if ($privacyRequest === null || $privacyRequest->request_type !== PrivacyRequestType::DataExport) {
                return;
            }

            $user = $privacyRequest->user;
            if ($user->isAnonymized() || $user->account_status === AccountStatus::DeletionProcessing) {
                $this->markFailed($privacyRequest, 'account_unavailable');

                return;
            }

            $exportFile = $privacyRequest->exportFile;
            if ($exportFile === null) {
                return;
            }

            if ($exportFile->status === PrivacyExportFileStatus::Ready) {
                return;
            }

            $exportFile->forceFill(['status' => PrivacyExportFileStatus::Generating->value])->save();

            $diskName = (string) config('privacy.export.disk', 'private_documents');
            $ttlDays = (int) config('privacy.export.ttl_days', 7);
            $expiresAt = now()->addDays($ttlDays);
            $exportFile->forceFill(['expires_at' => $expiresAt])->save();

            $bundle = PersonalDataExportBundle::build($user, $this->cvDocumentService);
            $stored = $this->archiveWriter->write($exportFile, $bundle, $diskName);

            $exportFile->forceFill([
                ...$stored,
                'status' => PrivacyExportFileStatus::Ready->value,
                'generated_at' => now(),
                'expires_at' => $expiresAt,
                'failure_code' => null,
            ])->save();

            app(PrivacyRequestService::class)->completeExportRequest($privacyRequest->fresh(), $user);

            $this->auditLogger->recordOrFail(
                $user,
                'privacy_export.generated',
                AuditLogResult::Success,
                $user,
                metadata: [
                    'privacy_request_uuid' => $privacyRequest->uuid,
                    'export_uuid' => $exportFile->uuid,
                    'size_bytes' => $exportFile->size_bytes,
                    'expires_at' => $exportFile->expires_at?->toIso8601String(),
                ],
            );

            UserActivityLogger::log($user, UserActivityAction::PrivacyExportReady);
            $this->notifications->notifyExportReady($user, $privacyRequest->fresh(['exportFile']));
        } catch (Throwable $exception) {
            $privacyRequest = PrivacyRequest::query()->with('exportFile')->find($privacyRequestId);
            if ($privacyRequest !== null) {
                $this->markFailed($privacyRequest, 'generation_error');
            }

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function markFailed(PrivacyRequest $privacyRequest, string $failureCode): void
    {
        DB::transaction(function () use ($privacyRequest, $failureCode): void {
            $exportFile = $privacyRequest->exportFile;
            if ($exportFile !== null && filled($exportFile->path) && filled($exportFile->disk)) {
                $disk = \Illuminate\Support\Facades\Storage::disk($exportFile->disk);
                if ($disk->exists($exportFile->path)) {
                    $disk->delete($exportFile->path);
                }
                $exportFile->forceFill(['path' => ''])->save();
            }

            $exportFile?->forceFill([
                'status' => PrivacyExportFileStatus::Failed->value,
                'failure_code' => $failureCode,
            ])->save();

            if (! $privacyRequest->status->isTerminal()) {
                app(PrivacyRequestService::class)->fail($privacyRequest, $privacyRequest->user, 'فشل توليد ملف التصدير.');
            }

            UserActivityLogger::log($privacyRequest->user, UserActivityAction::PrivacyExportFailed);
            $this->notifications->notifyExportFailed($privacyRequest->user, $privacyRequest->fresh());

            $this->auditLogger->recordOrFail(
                $privacyRequest->user,
                'privacy_export.failed',
                AuditLogResult::Failure,
                $privacyRequest->user,
                metadata: [
                    'privacy_request_uuid' => $privacyRequest->uuid,
                    'export_uuid' => $exportFile?->uuid,
                    'failure_code' => $failureCode,
                ],
            );
        });
    }

    public function purgeExpiredExport(PrivacyExportFile $exportFile): bool
    {
        if ($exportFile->status !== PrivacyExportFileStatus::Ready) {
            return false;
        }

        if ($exportFile->expires_at === null || $exportFile->expires_at->isFuture()) {
            return false;
        }

        if (filled($exportFile->path) && filled($exportFile->disk)) {
            $disk = \Illuminate\Support\Facades\Storage::disk($exportFile->disk);
            if ($disk->exists($exportFile->path)) {
                $disk->delete($exportFile->path);
            }
        }

        $exportFile->forceFill([
            'status' => PrivacyExportFileStatus::Deleted->value,
            'path' => '',
        ])->save();

        UserActivityLogger::log($exportFile->user, UserActivityAction::PrivacyExportExpired);

        $this->auditLogger->recordOrFail(
            $exportFile->user,
            'privacy_export.deleted',
            AuditLogResult::Success,
            $exportFile->user,
            metadata: [
                'export_uuid' => $exportFile->uuid,
                'result' => 'expired_purge',
            ],
        );

        return true;
    }
}
