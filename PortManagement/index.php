<?php
require_once __DIR__ . '/helpers/functions.php';

// Compute base URL path from the actual script file location relative to DOCUMENT_ROOT
$scriptPath = str_replace('\\', '/', __FILE__);
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$basePath = $docRoot && strpos($scriptPath, $docRoot) === 0
    ? dirname(substr($scriptPath, strlen($docRoot)))
    : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$origUri = $uri; // save for fallback matching
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
$uri = rtrim($uri, '/');
if (empty($uri)) $uri = '/';

// ── Serve static files (use $origUri to avoid basePath corruption) ──
if (preg_match('#^/(?:PortManagement/)?static/(.+)$#', $origUri, $m) || preg_match('#^/static/(.+)$#', $uri, $m)) {
    $staticRoot = realpath(__DIR__ . '/static');
    $file = realpath(__DIR__ . '/static/' . $m[1]);
    if (!$staticRoot || !$file || strpos($file, $staticRoot) !== 0 || !file_exists($file)) { http_response_code(404); exit; }
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mime = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png',
             'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];
    header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
    readfile($file);
    exit;
}

// ── Simple route map ──
$routes = [
    '/'                       => '/home/dashboard.php',
    '/login/'                 => '/accounts/login.php',
    '/logout/'                => '/accounts/logout.php',
    '/home/'                  => '/home/home.php',
    '/dashboard/'             => '/home/dashboard.php',
    '/dashboard/port/'        => '/home/port_dashboard.php',
    '/dashboard/analytics/unified/' => '/unified/dashboard.php',
    '/dashboard/analytics/market/'  => '/market/dashboard.php',
    '/dashboard/analytics/market/orders/' => '/market/orders.php',
    '/dashboard/analytics/market/users/'  => '/market/users.php',
    '/dashboard/analytics/market/users/add/' => '/market/user_add.php',
    '/dashboard/analytics/market/users/edit/' => '/market/user_edit.php',
    '/dashboard/analytics/market/topup/'  => '/market/topup.php',
    '/dashboard/analytics/api/'     => '/analytics/api.php',
    '/dashboard/analytics/data-export/' => '/analytics/export.php',
    '/dashboard/analytics/international/' => '/international/dashboard.php',
    '/dashboard/analytics/international/orders/' => '/international/orders.php',
    '/dashboard/analytics/international/users/'  => '/international/users.php',
    '/dashboard/analytics/international/users/add/' => '/international/user_add.php',
    '/dashboard/analytics/international/users/edit/' => '/international/user_edit.php',
    '/dashboard/analytics/international/restaurants/' => '/international/restaurants.php',
    '/dashboard/analytics/international/restaurants/add/' => '/international/restaurant_add.php',
    '/dashboard/analytics/international/restaurants/edit/' => '/international/restaurant_edit.php',
    '/dashboard/analytics/international/topup/'  => '/international/topup.php',
    '/dashboard/stock/'       => '/stock/list.php',
    '/dashboard/stock/add/'   => '/stock/add.php',
    '/dashboard/stock/edit/'  => '/stock/edit.php',
    '/dashboard/stock/movements/' => '/stock/movements.php',
    '/vessels/'               => '/vessel/dashboard.php',
    '/vessels/trips/'          => '/vessel/fishing_trips.php',
    '/vessels/sorting/'         => '/vessel/fishing_trips.php',
    '/vessels/api/data/'      => '/vessel/api_data.php',
    '/vessels/api/tick/'      => '/vessel/api_tick.php',
    '/vessels/api/fleet/'     => '/vessel/api_fleet.php',
    '/vessels/api/trip_catches/' => '/vessel/api/trip_catches.php',
    '/logistics/'             => '/logistics/index.php',
    '/logistics/warehouse/'   => '/logistics/warehouse.php',
    '/logistics/drivers/'     => '/logistics/drivers.php',
    '/logistics/drivers/add/' => '/logistics/driver_add.php',
    '/logistics/drivers/edit/' => '/logistics/driver_edit.php',
    '/logistics/international/' => '/logistics/international.php',
    '/logistics/export_driver/' => '/logistics/intl_drivers.php',
    '/logistics/export_driver/add/' => '/logistics/intl_driver_add.php',
    '/logistics/export_driver/edit/' => '/logistics/intl_driver_edit.php',
];

// ── No-trailing-slash aliases (for Windows dirname compatibility) ──
$noSlashRoutes = [];
foreach ($routes as $key => $val) {
    $trimmed = rtrim($key, '/');
    if ($trimmed !== $key && $trimmed !== '') {
        $noSlashRoutes[$trimmed] = $val;
    }
}
$routes = array_merge($routes, $noSlashRoutes);

// ── Dynamic routes ──
$stockEditMatch = null;
if (preg_match('#^/dashboard/stock/edit/(\d+)/?$#', $uri, $m)) $stockEditMatch = $m[1];
$stockDeleteMatch = null;
if (preg_match('#^/dashboard/stock/delete/(\d+)/?$#', $uri, $m)) $stockDeleteMatch = $m[1];
$stockPortMatch = null;
if (preg_match('#^/dashboard/stock/port/(\d+)/?$#', $uri, $m)) $stockPortMatch = (int)$m[1];
$portDashMatch = null;
if (preg_match('#^/port/([^/]+)/?$#', $uri, $m)) $portDashMatch = $m[1];

// ── Route matching ──
if (isset($routes[$uri])) {
    require __DIR__ . $routes[$uri];
} elseif ($stockEditMatch) {
    $_GET['id'] = $stockEditMatch;
    require __DIR__ . '/stock/edit.php';
} elseif ($stockDeleteMatch) {
    $_GET['delete_id'] = $stockDeleteMatch;
    require __DIR__ . '/stock/list.php';
} elseif ($stockPortMatch) {
    $_GET['port_id'] = $stockPortMatch;
    require __DIR__ . '/stock/port.php';
} elseif ($portDashMatch) {
    require __DIR__ . '/home/port_dashboard.php';
} elseif (isset($routes[rtrim($uri, '/') . '/'])) {
    require __DIR__ . $routes[rtrim($uri, '/') . '/'];
} elseif ($origUri && $origUri !== $uri && isset($routes[rtrim($origUri, '/')])) {
    // Fallback: try original URI (for PHP built-in server parent-directory lookup)
    require __DIR__ . $routes[rtrim($origUri, '/')];
} else {
    http_response_code(404);
    echo '<h1>404 Not Found</h1>';
}
