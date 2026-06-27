<?php

namespace App\Services\Documents;

use App\Enums\AuditLogResult;
use App\Enums\UserDocumentStatus;
use App\Enums\UserDocumentType;
use App\Enums\UserActivityAction;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\Audit\AuditLogService;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CvDocumentService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function currentCv(User $user): ?UserDocument
    {
        $profile = Profile::query()
            ->where('user_id', $user->id)
            ->with('currentCvDocument')
            ->first();

        $document = $profile?->currentCvDocument;

        if ($document !== null && $document->isActive()) {
            return $document;
        }

        return null;
    }

    public function hasActiveCv(User $user): bool
    {
        return $this->currentCv($user) !== null;
    }

    public function upload(User $owner, UploadedFile $file, ?User $actor = null, ?Request $request = null): UserDocument
    {
        $meta = CvFileValidator::validate($file);

        $existing = $this->currentCv($owner);

        if ($existing !== null) {
            return $this->replace($owner, $file, $actor, $request);
        }

        return $this->storeNew($owner, $file, $meta, $actor, $request, 'cv.uploaded');
    }

    public function replace(User $owner, UploadedFile $file, ?User $actor = null, ?Request $request = null): UserDocument
    {
        $meta = CvFileValidator::validate($file);
        $previous = $this->currentCv($owner);

        $document = $this->storeNew($owner, $file, $meta, $actor, $request, 'cv.replaced');

        if ($previous !== null) {
            $this->markDeleted($previous, $actor, $request, deleteFile: true);
        }

        return $document;
    }

    public function delete(User $owner, ?User $actor = null, ?Request $request = null): void
    {
        $document = $this->currentCv($owner);

        if ($document === null) {
            throw new InvalidArgumentException('cv_not_found');
        }

        DB::transaction(function () use ($owner, $document, $actor, $request): void {
            Profile::query()
                ->where('user_id', $owner->id)
                ->update(['current_cv_document_id' => null]);

            $this->markDeleted($document, $actor, $request, deleteFile: true);
        });

        UserActivityLogger::log($owner, UserActivityAction::CvDeleted, 'حذفت سيرتك الذاتية.');

        $this->auditLog->record(
            $actor ?? $owner,
            'cv.deleted',
            AuditLogResult::Success,
            $owner,
            $document,
            metadata: ['document_uuid' => $document->uuid],
            request: $request,
        );
    }

    public function downloadResponse(User $owner, UserDocument $document, User $actor, ?Request $request = null): StreamedResponse
    {
        if ($document->user_id !== $owner->id || ! $document->isActive()) {
            throw new InvalidArgumentException('cv_unauthorized');
        }

        $disk = PrivateDocumentsStorage::disk();

        if (! $disk->exists($document->path)) {
            $this->auditLog->record(
                $actor,
                'cv.downloaded',
                AuditLogResult::Failure,
                $owner,
                $document,
                reason: 'file_missing',
                request: $request,
            );

            throw new InvalidArgumentException('cv_missing_file');
        }

        $this->auditLog->record(
            $actor,
            $actor->id === $owner->id ? 'cv.downloaded' : 'cv.viewed',
            AuditLogResult::Success,
            $owner,
            $document,
            metadata: ['document_uuid' => $document->uuid],
            request: $request,
        );

        $filename = $this->secureDownloadFilename($owner);

        return $disk->download($document->path, $filename, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function secureDownloadFilename(User $owner): string
    {
        $code = str_pad((string) $owner->id, 6, '0', STR_PAD_LEFT);

        return 'cv-BEN-'.$code.'.pdf';
    }

    /**
     * @param  array{mime: string, extension: string, size: int}  $meta
     */
    private function storeNew(
        User $owner,
        UploadedFile $file,
        array $meta,
        ?User $actor,
        ?Request $request,
        string $auditAction,
    ): UserDocument {
        $diskName = PrivateDocumentsStorage::diskName();
        $disk = PrivateDocumentsStorage::disk();
        $storedPath = null;

        try {
            $document = DB::transaction(function () use ($owner, $file, $meta, $diskName, $disk, $actor, &$storedPath): UserDocument {
                $uuid = (string) Str::uuid();
                $relativePath = trim((string) config('cv.storage_directory', 'cv'), '/')
                    .'/'.substr($uuid, 0, 2)
                    .'/'.$uuid.'.'.$meta['extension'];

                $storedPath = $disk->putFileAs(
                    dirname($relativePath),
                    $file,
                    basename($relativePath),
                );

                if ($storedPath === false) {
                    throw new InvalidArgumentException('cv_store_failed');
                }

                $checksum = hash_file('sha256', $file->getRealPath() ?: '');

                $document = UserDocument::query()->create([
                    'uuid' => $uuid,
                    'user_id' => $owner->id,
                    'document_type' => UserDocumentType::Cv,
                    'disk' => $diskName,
                    'path' => $storedPath,
                    'mime_type' => $meta['mime'],
                    'extension' => $meta['extension'],
                    'size_bytes' => $meta['size'],
                    'sha256_checksum' => $checksum,
                    'status' => UserDocumentStatus::Active,
                    'uploaded_by' => ($actor ?? $owner)->id,
                    'uploaded_at' => now(),
                ]);

                Profile::query()->updateOrCreate(
                    ['user_id' => $owner->id],
                    ['current_cv_document_id' => $document->id],
                );

                return $document;
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null && $disk->exists($storedPath)) {
                $disk->delete($storedPath);
            }

            throw $exception;
        }

        $activity = $auditAction === 'cv.replaced'
            ? UserActivityAction::CvReplaced
            : UserActivityAction::CvUploaded;

        UserActivityLogger::log(
            $owner,
            $activity,
            $auditAction === 'cv.replaced' ? 'استبدلت سيرتك الذاتية.' : 'رفعت سيرتك الذاتية.',
        );

        $this->auditLog->record(
            $actor ?? $owner,
            $auditAction,
            AuditLogResult::Success,
            $owner,
            $document,
            metadata: ['document_uuid' => $document->uuid],
            request: $request,
        );

        return $document;
    }

    private function markDeleted(UserDocument $document, ?User $actor, ?Request $request, bool $deleteFile): void
    {
        DB::transaction(function () use ($document, $deleteFile): void {
            $document->forceFill([
                'status' => UserDocumentStatus::Deleted,
                'deleted_at' => now(),
            ])->save();

            if ($deleteFile) {
                $disk = PrivateDocumentsStorage::disk();
                if ($disk->exists($document->path)) {
                    $disk->delete($document->path);
                }
            }
        });
    }
}
