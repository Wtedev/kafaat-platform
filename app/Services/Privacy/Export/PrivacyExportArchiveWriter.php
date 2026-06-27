<?php

namespace App\Services\Privacy\Export;

use App\Data\Privacy\Export\PersonalDataExportBundle;
use App\Models\PrivacyExportFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class PrivacyExportArchiveWriter
{
    /**
     * @return array{disk: string, path: string, size_bytes: int, sha256_checksum: string}
     */
    public function write(PrivacyExportFile $exportFile, PersonalDataExportBundle $bundle, string $diskName): array
    {
        $workDir = storage_path('app/private/privacy-export-work/'.$exportFile->uuid);
        File::ensureDirectoryExists($workDir);

        try {
            $fileMap = $this->sectionFileMap($bundle);
            $writtenFiles = [];

            foreach ($fileMap as $archiveName => $payload) {
                if ($payload === null) {
                    continue;
                }
                $absolute = $workDir.'/'.$archiveName;
                File::ensureDirectoryExists(dirname($absolute));
                $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                File::put($absolute, $json);
                $writtenFiles[$archiveName] = hash('sha256', $json);
            }

            $readme = $this->buildReadme($exportFile, array_keys($writtenFiles));
            File::put($workDir.'/README-ar.txt', $readme);
            $writtenFiles['README-ar.txt'] = hash('sha256', $readme);

            if ($bundle->cvSourcePath !== null && $bundle->cvArchiveName !== null) {
                $cvTarget = $workDir.'/'.$bundle->cvArchiveName;
                File::ensureDirectoryExists(dirname($cvTarget));
                $cvDisk = Storage::disk(config('privacy.export.disk', 'private_documents'));
                if ($cvDisk->exists($bundle->cvSourcePath)) {
                    File::put($cvTarget, $cvDisk->get($bundle->cvSourcePath));
                    $writtenFiles[$bundle->cvArchiveName] = hash_file('sha256', $cvTarget) ?: '';
                }
            }

            $manifest = [
                'export_uuid' => $exportFile->uuid,
                'generated_at' => now()->toIso8601String(),
                'schema_version' => (int) config('privacy.export.schema_version', 1),
                'timezone' => (string) config('app.timezone', 'UTC'),
                'files' => collect($writtenFiles)->map(fn (string $checksum, string $name): array => [
                    'name' => $name,
                    'sha256' => $checksum,
                    'record_count' => $this->recordCountForFile($name, $bundle),
                ])->values()->all(),
            ];
            $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            File::put($workDir.'/manifest.json', $manifestJson);

            $zipLocal = $workDir.'/export.zip';
            $zip = new ZipArchive;
            if ($zip->open($zipLocal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('zip_create_failed');
            }

            foreach (File::allFiles($workDir) as $file) {
                if ($file->getFilename() === 'export.zip') {
                    continue;
                }
                $relative = Str::after($file->getPathname(), $workDir.'/');
                $zip->addFile($file->getPathname(), $relative);
            }
            $zip->close();

            if (! is_file($zipLocal)) {
                throw new RuntimeException('zip_missing');
            }

            $checksum = hash_file('sha256', $zipLocal) ?: '';
            $storagePath = 'privacy-exports/'.$exportFile->uuid.'/'.Str::uuid()->toString().'.zip';
            $disk = Storage::disk($diskName);
            $disk->put($storagePath, File::get($zipLocal));

            if (! $disk->exists($storagePath)) {
                throw new RuntimeException('zip_store_failed');
            }

            return [
                'disk' => $diskName,
                'path' => $storagePath,
                'size_bytes' => (int) $disk->size($storagePath),
                'sha256_checksum' => $checksum,
            ];
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * @return array<string, mixed|null>
     */
    private function sectionFileMap(PersonalDataExportBundle $bundle): array
    {
        $sections = $bundle->sections;

        return [
            'account.json' => $sections['account'] ?? null,
            'profile.json' => $sections['profile'] ?? null,
            'privacy-policy-acknowledgements.json' => $sections['privacy_policy_acknowledgements'] ?? null,
            'candidate-pool-consent-history.json' => $sections['candidate_pool_consent_history'] ?? null,
            'program-registrations.json' => $sections['program_registrations'] ?? null,
            'path-registrations.json' => $sections['path_registrations'] ?? null,
            'volunteer-registrations.json' => $sections['volunteer_registrations'] ?? null,
            'attendance.json' => $sections['attendance'] ?? null,
            'certificates.json' => $sections['certificates'] ?? null,
            'activity-history.json' => $sections['activity_history'] ?? null,
            'documents-manifest.json' => $sections['documents_manifest'] ?? null,
        ];
    }

    /**
     * @param  list<string>  $fileNames
     */
    private function buildReadme(PrivacyExportFile $exportFile, array $fileNames): string
    {
        $expires = $exportFile->expires_at?->translatedFormat('j F Y H:i') ?? '—';
        $generated = $exportFile->generated_at?->translatedFormat('j F Y H:i') ?? now()->translatedFormat('j F Y H:i');
        $filesList = implode("\n", array_map(fn (string $name): string => '- '.$name, $fileNames));
        $schema = $this->schemaVersion();

        return <<<TXT
نسخة تصدير بياناتك الشخصية — منصة كفاءات
==========================================

تاريخ إنشاء هذا الملف: {$generated}
إصدار المخطط (schema): {$schema}
تاريخ انتهاء صلاحية التنزيل: {$expires}

محتويات الأرشيف:
{$filesList}
- manifest.json

ملاحظات مهمة:
- تمثل هذه البيانات حالة حسابك في المنصة وقت التوليد.
- لا تتضمن سجلات تدقيق داخلية أو سجلات أمنية أو بيانات موظفين.
- رقم الهوية يظهر مقنّعاً في account.json وفق سياسة الخصوصية.
- لطلب تصحيح بيانات غير دقيقة، استخدم مركز الخصوصية في البوابة.

TXT;
    }

    private function schemaVersion(): int
    {
        return (int) config('privacy.export.schema_version', 1);
    }

    private function recordCountForFile(string $name, PersonalDataExportBundle $bundle): int
    {
        return match ($name) {
            'account.json' => 1,
            'profile.json' => $bundle->sections['profile'] !== null ? 1 : 0,
            'privacy-policy-acknowledgements.json' => count($bundle->sections['privacy_policy_acknowledgements'] ?? []),
            'candidate-pool-consent-history.json' => count($bundle->sections['candidate_pool_consent_history'] ?? []),
            'program-registrations.json' => count($bundle->sections['program_registrations'] ?? []),
            'path-registrations.json' => count($bundle->sections['path_registrations'] ?? []),
            'volunteer-registrations.json' => count($bundle->sections['volunteer_registrations'] ?? []),
            'attendance.json' => count($bundle->sections['attendance'] ?? []),
            'certificates.json' => count($bundle->sections['certificates'] ?? []),
            'activity-history.json' => count($bundle->sections['activity_history'] ?? []),
            'documents-manifest.json' => 1,
            'README-ar.txt' => 1,
            default => 0,
        };
    }
}
