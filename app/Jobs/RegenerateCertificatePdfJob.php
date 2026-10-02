<?php

namespace App\Jobs;

use App\Enums\CertificatePdfStatus;
use App\Models\Certificate;
use App\Services\Certificates\CertificateRenderer;
use App\Support\Certificates\CertificateStoredFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يعيد توليد PDF شهادة صادرة من لقطة البيانات المحفوظة، دون إعادة حساب الاسم أو الدرجات.
 */
class RegenerateCertificatePdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $certificateId)
    {
        $this->onQueue('certificates');
    }

    public function handle(CertificateRenderer $renderer): void
    {
        $certificate = Certificate::query()->with('certificateTemplate')->find($this->certificateId);
        if (! $certificate instanceof Certificate || ! $certificate->certificateTemplate) {
            return;
        }

        $snapshot = $certificate->data_snapshot;
        if (! is_array($snapshot) || $snapshot === []) {
            $certificate->update([
                'pdf_status' => CertificatePdfStatus::Failed,
                'pdf_error' => 'لا توجد لقطة بيانات لإعادة التوليد.',
            ]);

            return;
        }

        try {
            $binary = $renderer->render($certificate->certificateTemplate, $snapshot);
            $relative = 'certificates/'.$certificate->certificate_number.'.pdf';
            CertificateStoredFile::put($relative, $binary);
            $certificate->update([
                'file_path' => $relative,
                'pdf_status' => CertificatePdfStatus::Generated,
                'pdf_error' => null,
                'template_version' => $certificate->certificateTemplate->version,
            ]);
        } catch (Throwable $exception) {
            Log::warning('certificate.regenerate_failed', [
                'certificate_id' => $certificate->id,
                'message' => $exception->getMessage(),
            ]);
            $certificate->update([
                'pdf_status' => CertificatePdfStatus::Failed,
                'pdf_error' => 'تعذر إعادة توليد الشهادة.',
            ]);

            throw $exception;
        }
    }
}
