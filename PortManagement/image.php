<?php
/**
 * 从数据库读取图片并输出
 * 用法: image.php?id=123
 */
require_once __DIR__ . '/helpers/functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('Invalid ID');
}

try {
    $pdo = getDB();
    $st = $pdo->prepare("SELECT image_data FROM product WHERE id = ? AND image_data IS NOT NULL");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['image_data'])) {
        http_response_code(404);
        die('Image not found');
    }

    $data = $row['image_data'];
    
    // Detect MIME type from magic bytes
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_buffer($finfo, $data);
    finfo_close($finfo);
    
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: public, max-age=86400');
    echo $data;
} catch (Exception $e) {
    http_response_code(500);
    die('Error loading image');
}
