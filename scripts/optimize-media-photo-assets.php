#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__).'/database/seeders/assets/media-photos';

if (! is_dir($root)) {
    fwrite(STDERR, "Missing assets directory: {$root}\n");
    exit(1);
}

if (! extension_loaded('gd')) {
    fwrite(STDERR, "GD extension is required.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$optimized = 0;
$savedBytes = 0;

foreach ($iterator as $file) {
    if (! $file->isFile()) {
        continue;
    }

    $path = $file->getPathname();

    if (! preg_match('/\.(jpe?g|png|webp)$/i', $path)) {
        continue;
    }

    $before = filesize($path) ?: 0;
    $binary = optimizeImage($path);

    if ($binary === null) {
        fwrite(STDERR, "Skipped (unreadable): {$path}\n");

        continue;
    }

    $target = buildTargetPath($path);

    if (file_put_contents($target, $binary) === false) {
        fwrite(STDERR, "Failed writing: {$target}\n");

        continue;
    }

    if ($target !== $path && is_file($path)) {
        unlink($path);
    }

    $after = filesize($target) ?: 0;
    $savedBytes += max(0, $before - $after);
    $optimized++;
}

printf(
    "Optimized %d photos. Saved %.1f MB.\n",
    $optimized,
    $savedBytes / 1024 / 1024
);

function buildTargetPath(string $path): string
{
    $directory = dirname($path);
    $filename = pathinfo($path, PATHINFO_FILENAME);
    $target = $directory.'/'.$filename.'.jpg';

    if (! is_file($target) || realpath($target) === realpath($path)) {
        return $target;
    }

    $suffix = 2;

    while (is_file($directory.'/'.$filename.'-'.$suffix.'.jpg')) {
        $suffix++;
    }

    return $directory.'/'.$filename.'-'.$suffix.'.jpg';
}

function optimizeImage(string $path): ?string
{
    $mime = mime_content_type($path) ?: '';

    $image = match (true) {
        str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => @imagecreatefromjpeg($path),
        str_contains($mime, 'png') => @imagecreatefrompng($path),
        str_contains($mime, 'webp') => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
        default => null,
    };

    if ($image === false || $image === null) {
        return null;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $maxEdge = 1920;

    if ($width <= 0 || $height <= 0) {
        imagedestroy($image);

        return null;
    }

    $longest = max($width, $height);

    if ($longest > $maxEdge) {
        if ($width >= $height) {
            $newWidth = $maxEdge;
            $newHeight = (int) round($height * ($maxEdge / $width));
        } else {
            $newHeight = $maxEdge;
            $newWidth = (int) round($width * ($maxEdge / $height));
        }
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($image);

    ob_start();
    imagejpeg($canvas, null, 82);
    $binary = ob_get_clean() ?: null;
    imagedestroy($canvas);

    return $binary;
}
