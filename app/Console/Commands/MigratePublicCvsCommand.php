<?php

namespace App\Console\Commands;

use App\Enums\AuditLogResult;
use App\Enums\UserDocumentStatus;
use App\Enums\UserDocumentType;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\Audit\AuditLogService;
use App\Services\Documents\PrivateDocumentsStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigratePublicCvsCommand extends Command
{
    protected $signature = 'privacy:migrate-public-cvs
                            {--dry-run : Report only, no writes}
                            {--batch=100 : Batch size}
                            {--user= : Limit to a single user id}';

    protected $description = 'Migrate CV files from public disk to private documents storage';

    public function handle(AuditLogService $auditLog): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batch = max(1, (int) $this->option('batch'));
        $userId = $this->option('user');

        $query = Profile::query()
            ->whereNotNull('cv_path')
            ->when($userId, fn ($q) => $q->where('user_id', (int) $userId))
            ->orderBy('id');

        $processed = $migrated = $skipped = $missing = $failed = 0;

        $query->chunkById($batch, function ($profiles) use ($dryRun, &$processed, &$migrated, &$skipped, &$missing, &$failed, $auditLog): void {
            foreach ($profiles as $profile) {
                $processed++;

                if ($profile->current_cv_document_id !== null) {
                    $skipped++;

                    continue;
                }

                $legacyPath = (string) $profile->cv_path;
                if ($legacyPath === '' || str_contains($legacyPath, '..')) {
                    $failed++;

                    continue;
                }

                if (! Storage::disk('public')->exists($legacyPath)) {
                    $missing++;
                    if (! $dryRun) {
                        $auditLog->record(null, 'cv.migration_failed', AuditLogResult::Failure, User::query()->find($profile->user_id), metadata: ['reason' => 'missing_source']);
                    }

                    continue;
                }

                if ($dryRun) {
                    $migrated++;

                    continue;
                }

                try {
                    DB::transaction(function () use ($profile, $legacyPath, $auditLog): void {
                        $uuid = (string) Str::uuid();
                        $extension = strtolower(pathinfo($legacyPath, PATHINFO_EXTENSION)) ?: 'pdf';
                        $relativePath = 'cv/'.substr($uuid, 0, 2).'/'.$uuid.'.'.$extension;

                        $contents = Storage::disk('public')->get($legacyPath);
                        $privateDisk = PrivateDocumentsStorage::disk();
                        $privateDisk->put($relativePath, $contents);

                        $checksum = hash('sha256', $contents);
                        $size = strlen($contents);

                        $document = UserDocument::query()->create([
                            'uuid' => $uuid,
                            'user_id' => $profile->user_id,
                            'document_type' => UserDocumentType::Cv,
                            'disk' => PrivateDocumentsStorage::diskName(),
                            'path' => $relativePath,
                            'mime_type' => 'application/pdf',
                            'extension' => $extension,
                            'size_bytes' => $size,
                            'sha256_checksum' => $checksum,
                            'status' => UserDocumentStatus::Active,
                            'uploaded_by' => $profile->user_id,
                            'uploaded_at' => now(),
                        ]);

                        $profile->forceFill(['current_cv_document_id' => $document->id])->save();

                        $verify = hash('sha256', (string) $privateDisk->get($relativePath));
                        if ($verify !== $checksum) {
                            throw new \RuntimeException('checksum_mismatch');
                        }

                        Storage::disk('public')->delete($legacyPath);
                        $profile->forceFill(['cv_path' => null])->save();

                        $targetUser = User::query()->find($profile->user_id);

                        $auditLog->record(null, 'cv.migration_succeeded', AuditLogResult::Success, $targetUser, $document, metadata: ['document_uuid' => $uuid]);
                    });

                    $migrated++;
                } catch (\Throwable) {
                    $failed++;
                    $auditLog->record(null, 'cv.migration_failed', AuditLogResult::Failure, User::query()->find($profile->user_id), metadata: ['reason' => 'exception']);
                }
            }
        });

        $this->table(['Metric', 'Count'], [
            ['processed', $processed],
            ['migrated', $migrated],
            ['skipped', $skipped],
            ['missing', $missing],
            ['failed', $failed],
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
