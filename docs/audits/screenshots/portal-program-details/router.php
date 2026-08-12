<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = rawurldecode($uri);
$shots = __DIR__;
$public = dirname(__DIR__, 4).'/public';
$shotFile = $shots.$uri;
$publicFile = $public.$uri;

if (is_file($shotFile)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($shotFile);

    return;
}

if (is_file($publicFile)) {
    $mime = mime_content_type($publicFile) ?: 'application/octet-stream';
    if (str_ends_with($publicFile, '.css')) {
        $mime = 'text/css';
    } elseif (str_ends_with($publicFile, '.js')) {
        $mime = 'application/javascript';
    } elseif (str_ends_with($publicFile, '.svg')) {
        $mime = 'image/svg+xml';
    } elseif (str_ends_with($publicFile, '.woff2')) {
        $mime = 'font/woff2';
    }
    header('Content-Type: '.$mime);
    readfile($publicFile);

    return;
}

http_response_code(404);
echo 'Not found';
