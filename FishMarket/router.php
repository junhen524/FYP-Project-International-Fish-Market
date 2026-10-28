<?php
declare(strict_types=1);

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$normalizedUri = preg_replace('#/{2,}#', '/', $rawUri);
$path = parse_url($normalizedUri, PHP_URL_PATH) ?: '/';
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);

if (!file_exists($file) && str_starts_with($path, '/FishMarket/')) {
    $relPath = substr($path, strlen('/FishMarket')) ?: '/';
    $file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
}

if (!file_exists($file)) {
    $known = '/FisherySystem/FishMarket';
    if (str_starts_with($path, $known)) {
        $relPath = substr($path, strlen($known)) ?: '/';
        $file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    }
}

$mime = [
    'js' => 'application/javascript',
    'css' => 'text/css',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
    'mp4' => 'video/mp4',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'json' => 'application/json',
];

if (isset($mime[$ext]) && file_exists($file) && is_file($file)) {
    header('Content-Type: ' . $mime[$ext]);
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}

if ($ext === 'php' && file_exists($file) && is_file($file)) {
    chdir(__DIR__);
    require $file;
    return true;
}

chdir(__DIR__);
require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
return true;
