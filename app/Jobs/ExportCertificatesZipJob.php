<?php

namespace App\Jobs;

use App\Enums\CertificatePdfStatus;
use App\Models\Certificate;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\Certificates\CertificateStoredFile;
use App\Support\Certificates\CertificateZipName;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use ZipArchive;

class ExportCertificatesZipJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>|null  $certificateIds
     */
    public function __construct(
        public int $programId,
        public int $requestedById,
        public ?array $certificateIds = null,
        public string $ownerClass = TrainingProgram::class,
    ) {
        $this->onQueue('certificates');
    }

    public function handle(): void
    {
        $user = User::query()->find($this->requestedById);
        if (! $user instanceof User) {
            return;
        }

        $query = Certificate::query()
            ->with('user')
            ->where('certificateable_type', (new $this->ownerClass)->getMorphClass())
            ->where('certificateable_id', $this->programId)
            ->active()
            ->where('pdf_status', CertificatePdfStatus::Generated)
            ->whereNotNull('file_path');

        if ($this->certificateIds !== null) {
            $query->whereIn('id', $this->certificateIds);
        }

        $certificates = $query->get();
        if ($certificates->isEmpty()) {
            Notification::make()
                ->title('لا توجد شهادات صادرة للتصدير')
                ->warning()
                ->sendToDatabase($user);

            return;
        }

        $token = (string) Str::uuid();
        $relative = 'certificate-exports/'.$token.'.zip';
        Storage::disk('local')->makeDirectory('certificate-exports');
        $absolute = Storage::disk('local')->path($relative);

        $zip = new ZipArchive;
        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Notification::make()
                ->title('تعذر إنشاء ملف الشهادات')
                ->danger()
                ->sendToDatabase($user);

            return;
        }

        foreach ($certificates as $certificate) {
            $bytes = CertificateStoredFile::get($certificate->file_path);
            if (! is_string($bytes)) {
                continue;
            }

            $zip->addFromString(
                CertificateZipName::make($certificate->user?->certificateName() ?? '', (string) $certificate->certificate_number),
                $bytes,
            );
        }

        $zip->close();

        Cache::put($this->cacheKey($token), [
            'user_id' => $user->id,
            'path' => $relative,
        ], now()->addHours(24));

        $url = URL::temporarySignedRoute(
            'certificates.exports.download',
            now()->addHours(24),
            ['export' => $token],
        );

        Notification::make()
            ->title('ملف الشهادات جاهز')
            ->body('رابط التنزيل صالح لمدة 24 ساعة.')
            ->actions([
                Action::make('download')
                    ->label('تنزيل')
                    ->url($url),
            ])
            ->sendToDatabase($user);
    }

    public static function cacheKey(string $token): string
    {
        return 'certificate-export:'.$token;
    }
}
