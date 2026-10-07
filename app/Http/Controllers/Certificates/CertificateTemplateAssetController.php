<?php

namespace App\Http\Controllers\Certificates;

use App\Models\CertificateTemplate;
use App\Services\Certificates\CertificateDesignService;
use App\Services\Certificates\CertificateRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CertificateTemplateAssetController
{
    public function background(CertificateTemplate $template): Response
    {
        $path = $template->background_path;
        $disk = $template->background_disk ?: 'local';
        abort_unless(is_string($path) && $path !== '' && Storage::disk($disk)->exists($path), 404);

        $mime = str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';

        return response(Storage::disk($disk)->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function elementImage(CertificateTemplate $template, string $filename): Response
    {
        abort_unless(preg_match('/^[A-Za-z0-9\-]+\.(png|jpe?g)$/', $filename) === 1, 404);
        $path = 'certificate-elements/'.$template->id.'/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        $mime = str_ends_with(strtolower($filename), '.png') ? 'image/png' : 'image/jpeg';

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function preview(Request $request, CertificateTemplate $template, CertificateDesignService $design, CertificateRenderer $renderer): Response
    {
        $token = (string) $request->query('token', '');
        $payload = $design->pullPreview($template, $token);
        abort_unless($payload !== null, 404);

        $binary = $renderer->render($template, $payload['values'], $payload['elements']);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificate-preview.pdf"',
        ]);
    }

    public function font(string $font): BinaryFileResponse
    {
        $allowed = [
            'IBMPlexSansArabic-Regular.ttf',
            'IBMPlexSansArabic-Bold.ttf',
        ];
        abort_unless(in_array($font, $allowed, true), 404);
        $path = resource_path('fonts/certificates/'.$font);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
