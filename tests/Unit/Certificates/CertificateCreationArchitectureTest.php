<?php

namespace Tests\Unit\Certificates;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class CertificateCreationArchitectureTest extends TestCase
{
    #[Test]
    public function certificates_are_created_only_inside_the_issuance_service(): void
    {
        $pattern = '/Certificate::(?:query\(\)->)?create\s*\(|->certificates\(\)->create\s*\(/';
        $violations = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (str_ends_with($file->getPathname(), 'CertificateIssuanceService.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (is_string($contents) && preg_match($pattern, $contents) === 1) {
                $violations[] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $violations);
    }
}
