<?php

namespace App\Services\Privacy\Export;

use App\Enums\AuditLogResult;
use App\Enums\PrivacyExportFileStatus;
use App\Models\PrivacyExportFile;
use App\Models\User;
use App\Enums\UserActivityAction;
use App\Services\Access\SensitiveAccessVerification;
use App\Services\Audit\AuditLogger;
use App\Services\Security\SecurityLogService;
use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Services\UserActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PrivacyExportDownloadService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SecurityLogService $securityLog,
    ) {}

    public function download(User $user, PrivacyExportFile $exportFile, Request $request, ?string $password = null): StreamedResponse
    {
        if ($exportFile->user_id !== $user->id) {
            $this->securityLog->record(
                'privacy_export.download_denied',
                SecurityLogResult::Denied,
                SecurityLogSeverity::Warning,
                $user,
                metadata: ['export_uuid' => $exportFile->uuid],
                request: $request,
            );

            throw new AuthorizationException('You cannot download this export.');
        }

        if ($user->isAnonymized()) {
            throw new AuthorizationException('This account cannot download exports.');
        }

        if ($exportFile->status === PrivacyExportFileStatus::Expired
            || $exportFile->status === PrivacyExportFileStatus::Deleted
            || ($exportFile->expires_at !== null && $exportFile->expires_at->isPast())) {
            throw ValidationException::withMessages([
                'export' => 'انتهت صلاحية ملف التصدير.',
            ]);
        }

        if ($exportFile->status !== PrivacyExportFileStatus::Ready) {
            throw ValidationException::withMessages([
                'export' => 'ملف التصدير غير جاهز بعد.',
            ]);
        }

        if (! SensitiveAccessVerification::isRecentlyVerified($request)) {
            if ($password === null || ! Hash::check($password, (string) $user->password)) {
                $this->securityLog->record(
                    'privacy_export.verification_failed',
                    SecurityLogResult::Failed,
                    SecurityLogSeverity::Warning,
                    $user,
                    request: $request,
                );

                throw ValidationException::withMessages(['password' => 'يلزم تأكيد كلمة المرور لتنزيل التصدير.']);
            }

            SensitiveAccessVerification::markVerified($request);
        }

        if (! filled($exportFile->path) || ! filled($exportFile->disk)) {
            throw ValidationException::withMessages(['export' => 'ملف التصدير غير متوفر.']);
        }

        $disk = Storage::disk($exportFile->disk);
        if (! $disk->exists($exportFile->path)) {
            throw ValidationException::withMessages(['export' => 'ملف التصدير غير متوفر.']);
        }

        $now = now();
        $exportFile->forceFill([
            'first_downloaded_at' => $exportFile->first_downloaded_at ?? $now,
            'last_downloaded_at' => $now,
            'download_count' => (int) $exportFile->download_count + 1,
        ])->save();

        $this->auditLogger->recordOrFail(
            $user,
            'privacy_export.downloaded',
            AuditLogResult::Success,
            $user,
            metadata: [
                'export_uuid' => $exportFile->uuid,
                'download_count' => $exportFile->download_count,
            ],
            request: $request,
        );

        UserActivityLogger::log($user, UserActivityAction::PrivacyExportDownloaded);

        $filename = 'my-data-export-'.$now->format('Y-m-d').'.zip';

        return response()->streamDownload(function () use ($disk, $exportFile): void {
            echo $disk->get($exportFile->path);
        }, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
