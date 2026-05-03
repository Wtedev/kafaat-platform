<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Support\PublicDiskPath;
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

        $relative = PublicDiskPath::normalize($certificate->file_path);
        if ($relative === null || str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($relative)) {
            abort(404);
        }

        $filename = $certificate->certificate_number.'.pdf';

        return Storage::disk('public')->download($relative, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
