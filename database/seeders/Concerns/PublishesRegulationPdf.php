<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

trait PublishesRegulationPdf
{
    protected function publishRegulationPdf(string $filename): ?string
    {
        $relativePath = "regulation-docs/files/{$filename}";
        $source = database_path("seeders/assets/regulations/{$filename}");

        if (! File::exists($source)) {
            return File::exists(public_path($relativePath)) || Storage::disk('public')->exists($relativePath)
                ? $relativePath
                : null;
        }

        foreach ([public_path($relativePath), storage_path('app/public/'.$relativePath)] as $destination) {
            File::ensureDirectoryExists(dirname($destination));
            File::copy($source, $destination);
        }

        return File::exists(public_path($relativePath)) || Storage::disk('public')->exists($relativePath)
            ? $relativePath
            : null;
    }
}
