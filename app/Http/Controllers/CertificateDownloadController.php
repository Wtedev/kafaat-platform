<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\UserActivityLogger;
use App\Support\Certificates\CertificateStoredFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateDownloadController extends Controller
{
    /**
     * Stream the certificate PDF from storage (no dependency on the public/storage symlink).
     */
    public function __invoke(Request $request, Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        if ($certificate->isRevoked()) {
            abort(404);
        }

        $relative = CertificateStoredFile::relative($certificate->file_path);
        $disk = CertificateStoredFile::diskFor($certificate->file_path);
        if ($relative === null || $disk === null) {
            abort(404);
        }

        $user = $request->user();
        if ($user !== null) {
            UserActivityLogger::logCertificateDownload($user, $certificate->certificate_number);
        }

        $filename = $certificate->certificate_number.'.pdf';

        return Storage::disk($disk)->download($relative, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
