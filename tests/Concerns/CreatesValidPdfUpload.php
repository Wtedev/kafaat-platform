<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

trait CreatesValidPdfUpload
{
    protected function validPdfUpload(string $name = 'resume.pdf', int $paddingBytes = 64): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            '%PDF-1.4'.str_repeat("\n", 2).str_repeat(' ', max(0, $paddingBytes)),
        );
    }
}
