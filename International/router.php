<?php
// ── Extract path from REQUEST_URI ──
// Note: parse_url will interpret //track.php as a protocol-relative URL (host=track.php, path=null)
// So normalize from the original REQUEST_URI first
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
// Normalize: remove duplicate slashes (e.g. //track.php → /track.php)
$normalizedUri = preg_replace('#/{2,}#', '/', $rawUri);
$path = parse_url($normalizedUri, PHP_URL_PATH);
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

// Extract path relative to __DIR__ (compatible with multiple access modes)
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if (!file_exists($file) && str_starts_with($path, '/International/')) {
    // When accessed via /International/ prefix, strip the prefix
    $altPath = substr($path, strlen('/International')) ?: '/';
    $file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $altPath);
}
// Also support hard-coded full prefix paths (e.g. /FisherySystem/International)
if (!file_exists($file)) {
    $known = '/FisherySystem/International';
    if (str_starts_with($path, $known)) {
        $relPath = substr($path, strlen($known)) ?: '/';
        $file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    }
}

$mime = [
    'js' => 'application/javascript',
    'css' => 'text/css',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp',
    'mp4' => 'video/mp4', 'ico' => 'image/x-icon',
    'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
];

// ── Serve product images from PortManagement ──
if (str_starts_with($path, '/products-img/')) {
    $imgFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'PortManagement' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'products_db' . DIRECTORY_SEPARATOR . basename($path);
    $imgExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (isset($mime[$imgExt]) && file_exists($imgFile)) {
        header('Content-Type: ' . $mime[$imgExt]);
        header('Content-Length: ' . filesize($imgFile));
        readfile($imgFile);
        return true;
    }
}

if (isset($mime[$ext]) && file_exists($file)) {
    header('Content-Type: ' . $mime[$ext]);
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}

// ── Page routing (query-string based, like FishMarket) ──
$page = $_GET['page'] ?? '';
if ($page) {
    $pageFile = __DIR__ . DIRECTORY_SEPARATOR . preg_replace('/[^a-z0-9_-]/i', '', $page) . '.php';
    if (file_exists($pageFile)) {
        chdir(__DIR__);
        require $pageFile;
        return true;
    }
}

// ── Direct PHP file ──
if ($ext === 'php' && file_exists($file)) {
    chdir(__DIR__);
    require $file;
    return true;
}

// ── Fallback to home ──
chdir(__DIR__);
require __DIR__ . '/index.php';
return true;
