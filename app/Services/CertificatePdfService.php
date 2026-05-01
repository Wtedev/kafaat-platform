<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class CertificatePdfService
{
    /**
     * Generate a PDF for the given certificate and persist it to
     * storage/app/public/certificates/{certificate_number}.pdf
     *
     * @return string The storage-relative path stored in the database.
     */
    public function generate(Certificate $certificate): string
    {
        // Eager-load relationships needed by the Blade template
        $certificate->loadMissing(['user.profile', 'certificateable']);

        $pdf = Pdf::loadView('certificates.certificate', [
            'certificate' => $certificate,
        ])->setPaper('a4', 'landscape');

        $directory = 'public/certificates';
        $filename = $certificate->certificate_number.'.pdf';
        $storagePath = $directory.'/'.$filename;

        Storage::put($storagePath, $pdf->output());

        // Return the path relative to the storage disk root
        return $storagePath;
    }

    /**
     * Re-generate the PDF for an already-issued certificate (e.g. after a template change).
     */
    public function regenerate(Certificate $certificate): string
    {
        $path = $this->generate($certificate);
        $certificate->update(['file_path' => $path]);

        return $path;
    }
}
