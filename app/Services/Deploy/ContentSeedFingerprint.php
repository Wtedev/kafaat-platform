<?php

namespace App\Services\Deploy;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Fingerprint of database/seeders so content seeders run only when those sources change.
 */
class ContentSeedFingerprint
{
    public function __construct(
        private readonly string $seedersDirectory,
        private readonly string $stampPath,
    ) {}

    public function current(): string
    {
        $files = [];
        if (is_dir($this->seedersDirectory)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->seedersDirectory, FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);
        $context = hash_init('sha256');
        $root = rtrim($this->seedersDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        foreach ($files as $path) {
            $relative = str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
            hash_update($context, str_replace('\\', '/', $relative));
            hash_update($context, (string) file_get_contents($path));
        }

        return hash_final($context);
    }

    public function stored(): ?string
    {
        if (! is_file($this->stampPath)) {
            return null;
        }

        $value = trim((string) file_get_contents($this->stampPath));

        return $value === '' ? null : $value;
    }

    public function matches(): bool
    {
        $stored = $this->stored();

        return $stored !== null && hash_equals($stored, $this->current());
    }

    public function store(): void
    {
        $directory = dirname($this->stampPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->stampPath, $this->current().PHP_EOL);
    }
}
