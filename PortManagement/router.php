<?php
// PortManagement 路由 - 为 PHP 内置服务器提供路由支持
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
$mime = ['js'=>'application/javascript','css'=>'text/css','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','svg'=>'image/svg+xml','webp'=>'image/webp','ico'=>'image/x-icon','mp4'=>'video/mp4'];

// 1) 静态文件：从 URI 中提取 /static/... 部分
if (isset($mime[$ext])) {
    $pos = strpos($uri, '/static/');
    if ($pos !== false) {
        $relPath = substr($uri, $pos + 1); // 去掉开头的 /
        $file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    } else {
        $file = __DIR__ . DIRECTORY_SEPARATOR . ltrim($uri, '/');
        $file = str_replace('/', DIRECTORY_SEPARATOR, $file);
    }
    if (file_exists($file)) {
        header('Content-Type: ' . $mime[$ext]);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        return true;
    }
}

// 2) PHP 文件直接执行
if ($ext === 'php') {
    $pos = strpos($uri, dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $rel = $pos !== false ? substr($uri, $pos + strlen(dirname($_SERVER['SCRIPT_NAME'] ?? ''))) : $uri;
    $file = __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $rel), DIRECTORY_SEPARATOR);
    if (file_exists($file)) {
        chdir(__DIR__);
        require $file;
        return true;
    }
}

// 3) 目录路由：/logistics/ → /logistics/index.php, /market/ → /market/index.php 等
//    也支持 /logistics/drivers → /logistics/drivers.php（无后缀自动补 .php）
$dirPrefixes = ['/logistics', '/market', '/dashboard', '/admin'];
foreach ($dirPrefixes as $prefix) {
    if ($uri === $prefix || strpos($uri, $prefix . '/') === 0) {
        // 提取相对路径
        $baseLen = strlen($prefix);
        $rel = substr($uri, $baseLen); // e.g. '' or '/drivers' or '/drivers/'
        $rel = ltrim($rel, '/');
        
        // 尝试精确匹配文件（自动补 .php）
        if ($rel === '') $rel = 'index.php';
        elseif (pathinfo($rel, PATHINFO_EXTENSION) === '') $rel .= '.php';
        
        $subDir = ltrim($prefix, '/');
        $file = __DIR__ . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (file_exists($file) && is_file($file)) {
            chdir(__DIR__);
            require $file;
            return true;
        }
        
        // 如果请求的是目录（末尾有 / 或无扩展名），尝试找 index.php
        $relDir = substr($uri, $baseLen);
        $relDir = ltrim($relDir, '/');
        if ($relDir === '' || substr($relDir, -1) === '/' || pathinfo($relDir, PATHINFO_EXTENSION) === '') {
            $dirPath = __DIR__ . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rtrim($relDir, '/'));
            $idxFile = rtrim($dirPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
            if (is_dir($dirPath) && file_exists($idxFile)) {
                chdir(__DIR__);
                require $idxFile;
                return true;
            }
        }
    }
}

// 4) 其他所有请求 → 交给 index.php 路由
chdir(__DIR__);
require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
return true;
