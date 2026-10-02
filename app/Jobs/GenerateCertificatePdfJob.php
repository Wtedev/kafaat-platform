<?php

namespace App\Jobs;

use App\Enums\CertificatePdfStatus;
use App\Models\Certificate;
use App\Services\CertificatePdfService;
use App\Services\Certificates\CertificateRenderer;
use App\Support\Certificates\CertificateStoredFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateCertificatePdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $certificateId)
    {
        $this->onQueue('certificates');
    }

    public function handle(CertificateRenderer $renderer, CertificatePdfService $legacy): void
    {
        $certificate = Certificate::query()->with(['certificateTemplate', 'user', 'certificateable'])->find($this->certificateId);
        if (! $certificate instanceof Certificate || $certificate->isRevoked()) {
            return;
        }

        try {
            if ($certificate->certificateTemplate) {
                $snapshot = $certificate->data_snapshot;
                if (! is_array($snapshot) || $snapshot === []) {
                    $certificate->update([
                        'pdf_status' => CertificatePdfStatus::Failed,
                        'pdf_error' => 'لا توجد لقطة بيانات لإعادة التوليد.',
                    ]);

                    return;
                }

                $binary = $renderer->render($certificate->certificateTemplate, $snapshot);
                $relative = 'certificates/'.$certificate->certificate_number.'.pdf';
                CertificateStoredFile::put($relative, $binary);
                $certificate->update([
                    'file_path' => $relative,
                    'pdf_status' => CertificatePdfStatus::Generated,
                    'pdf_error' => null,
                    'template_version' => $certificate->certificateTemplate->version,
                ]);

                return;
            }

            $path = $legacy->generate($certificate);
            $certificate->update([
                'file_path' => $path,
                'pdf_status' => CertificatePdfStatus::Generated,
                'pdf_error' => null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('certificate.generate_failed', [
                'certificate_id' => $certificate->id,
                'message' => $exception->getMessage(),
            ]);
            $certificate->update([
                'pdf_status' => CertificatePdfStatus::Failed,
                'pdf_error' => 'تعذر توليد الشهادة.',
            ]);

            throw $exception;
        }
    }
}
