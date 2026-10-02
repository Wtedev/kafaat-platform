#!/usr/bin/env php
<?php

/**
 * Sync FF Shamel font binaries from the Vite source tree to public runtime assets.
 *
 * Source of truth: resources/fonts/shamel/
 * Runtime copies:  public/fonts/shamel/ (Filament CSS, error pages, static HTML)
 *
 * Vite bundles fonts from resources/ into public/build/assets/ for the public site CSS.
 */
$root = dirname(__DIR__);
$source = $root.'/resources/fonts/shamel';
$target = $root.'/public/fonts/shamel';

if (! is_dir($source)) {
    fwrite(STDERR, "Missing source directory: {$source}\n");
    exit(1);
}

if (! is_dir($target) && ! mkdir($target, 0755, true) && ! is_dir($target)) {
    fwrite(STDERR, "Could not create target directory: {$target}\n");
    exit(1);
}

$files = glob($source.'/*.{ttf,otf,woff,woff2}', GLOB_BRACE) ?: [];
if ($files === []) {
    fwrite(STDERR, "No font files found in {$source}\n");
    exit(1);
}

foreach ($files as $file) {
    $basename = basename($file);
    $destination = $target.'/'.$basename;

    if (is_file($destination) && hash_file('sha256', $file) === hash_file('sha256', $destination)) {
        continue;
    }

    if (! copy($file, $destination)) {
        fwrite(STDERR, "Failed to copy {$basename}\n");
        exit(1);
    }

    echo "synced {$basename}\n";
}

echo "Shamel fonts synced to public/fonts/shamel\n";
