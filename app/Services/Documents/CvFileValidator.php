<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final class CvFileValidator
{
    /**
     * @return array{mime: string, extension: string, size: int}
     */
    public static function validate(UploadedFile $file): array
    {
        $maxKb = (int) config('cv.max_size_kb', 10240);
        $maxBytes = $maxKb * 1024;

        if ($file->getSize() === false || $file->getSize() > $maxBytes) {
            throw new InvalidArgumentException('cv_file_too_large');
        }

        $original = strtolower($file->getClientOriginalName());
        if (preg_match('/\.(pdf|doc|docx)\./i', $original) === 1) {
            throw new InvalidArgumentException('cv_double_extension');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = config('cv.allowed_extensions', ['pdf']);
        if (! in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException('cv_invalid_extension');
        }

        $path = $file->getRealPath();
        if ($path === false) {
            throw new InvalidArgumentException('cv_unreadable');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? finfo_file($finfo, $path) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowedMimes = config('cv.allowed_mimes', ['application/pdf']);
        if ($detectedMime === false || ! in_array($detectedMime, $allowedMimes, true)) {
            throw new InvalidArgumentException('cv_invalid_mime');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('cv_unreadable');
        }
        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            throw new InvalidArgumentException('cv_invalid_pdf');
        }

        return [
            'mime' => (string) $detectedMime,
            'extension' => $extension,
            'size' => (int) $file->getSize(),
        ];
    }
}
