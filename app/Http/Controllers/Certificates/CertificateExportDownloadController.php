<?php

namespace App\Http\Controllers\Certificates;

use App\Http\Controllers\Controller;
use App\Jobs\ExportCertificatesZipJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateExportDownloadController extends Controller
{
    public function __invoke(Request $request, string $export): StreamedResponse
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $export)) {
            abort(404);
        }

        $payload = Cache::get(ExportCertificatesZipJob::cacheKey($export));
        if (! is_array($payload) || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()?->id) {
            abort(404);
        }

        $path = $payload['path'] ?? null;
        if (! is_string($path) || ! str_starts_with($path, 'certificate-exports/') || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->download($path, 'شهادات.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
