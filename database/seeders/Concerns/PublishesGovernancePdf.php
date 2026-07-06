<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

trait PublishesGovernancePdf
{
    protected function publishGovernancePdf(string $subdir, string $filename): ?string
    {
        $relativePath = "governance-docs/{$subdir}/{$filename}";
        $source = database_path("seeders/assets/{$subdir}/{$filename}");

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
