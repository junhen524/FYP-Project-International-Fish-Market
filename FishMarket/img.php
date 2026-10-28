<?php
// 图片代理：从 PortManagement/static/img/products_db/ 读取图片
$file = basename($_GET['file'] ?? '');
if (!$file) {
    http_response_code(400);
    exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
    http_response_code(403);
    exit;
}

$path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'PortManagement' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'products_db' . DIRECTORY_SEPARATOR . $file;

if (!file_exists($path)) {
    http_response_code(404);
    exit;
}

$mime = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];

header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
readfile($path);
