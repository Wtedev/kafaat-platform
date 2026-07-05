<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\File;

trait PublishesGovernancePdf
{
    protected function publishGovernancePdf(string $subdir, string $filename): ?string
    {
        $relativePath = "governance/{$subdir}/{$filename}";
        $source = database_path("seeders/assets/{$subdir}/{$filename}");
        $destination = storage_path('app/public/'.$relativePath);

        if (File::exists($source)) {
            File::ensureDirectoryExists(dirname($destination));
            File::copy($source, $destination);
        }

        return File::exists($destination) ? $relativePath : null;
    }
}
