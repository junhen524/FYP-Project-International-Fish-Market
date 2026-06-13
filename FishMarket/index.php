<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// ── 图片代理（处理 PHP 内置服务器不直接提供 img.php 的情况） ──
$__uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_ends_with($__uriPath, '/img.php') || basename($__uriPath) === 'img.php') {
    $__imgFile = basename($_GET['file'] ?? '');
    if ($__imgFile) {
        $__ext = strtolower(pathinfo($__imgFile, PATHINFO_EXTENSION));
        if (in_array($__ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $__path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'PortManagement' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'products_db' . DIRECTORY_SEPARATOR . $__imgFile;
            if (file_exists($__path)) {
                $__mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
                header('Content-Type: ' . ($__mime[$__ext] ?? 'application/octet-stream'));
                header('Content-Length: ' . filesize($__path));
                readfile($__path);
                exit;
            }
        }
    }
}

$page = current_page();
$allowed = [
    'home', 'shop', 'product', 'cart', 'checkout', 'orders',
    'order_detail', 'profile', 'login', 'register',
    'dashboard', 'dashboard_products', 'dashboard_orders',
    'dashboard_users',
];
if (!in_array($page, $allowed, true)) {
    $page = 'home';
}

switch ($page) {
    case 'home':
        render('home', [
            'view' => 'home',
            'title' => 'Fish Market - Port API Marketplace',
            'featured' => array_slice(featured_products(), 0, 8),
            'latest' => recent_catches(8),
            'categories' => categories(),
        ]);
        break;
    case 'shop':
        $category = $_GET['category'] ?? null;
        $search = trim((string) ($_GET['q'] ?? ''));
        $sort = (string) ($_GET['sort'] ?? '');
        render('shop', [
            'view' => 'shop',
            'title' => 'Shop - Fish Market',
            'categories' => categories(),
            'selectedCategory' => $category,
            'search' => $search,
            'sort' => $sort,
            'productsList' => filter_products($category, $search, $sort),
        ]);
        break;
    case 'product':
        $slug = (string) ($_GET['slug'] ?? '');
        $product = product_by_slug($slug);
        if (!$product) {
            header('Location: ' . url_for('shop'));
            exit;
        }
        render('product', [
            'view' => 'product',
            'title' => $product['name'] . ' - Fish Market',
            'product' => $product,
            'related' => related_products($product['category_slug'], $product['slug']),
        ]);
        break;
    case 'cart':
        render('cart', [
            'view' => 'cart',
            'title' => 'Cart - Fish Market',
            'cart' => cart_details(),
        ]);
        break;
    case 'checkout':
        if (!isFmLoggedIn()) {
            add_flash('error', 'Please login to checkout.');
            header('Location: ' . url_for('login'));
            exit;
        }
        render('checkout', [
            'view' => 'checkout',
            'title' => 'Checkout - Fish Market',
            'cart' => cart_details(),
        ]);
        break;
    case 'orders':
        render('orders', [
            'view' => 'orders',
            'title' => 'My Orders - Fish Market',
            'orders' => demo_orders(),
        ]);
        break;
    case 'order_detail':
        $number = (string) ($_GET['order_number'] ?? '');
        $selectedOrder = null;
        foreach (demo_orders() as $order) {
            if ($order['order_number'] === $number) {
                $selectedOrder = $order;
                break;
            }
        }
        if (!$selectedOrder) {
            header('Location: ' . url_for('orders'));
            exit;
        }
        render('order_detail', [
            'view' => 'order_detail',
            'title' => 'Order ' . $selectedOrder['order_number'] . ' - Fish Market',
            'order' => $selectedOrder,
        ]);
        break;
    case 'profile':
        render('profile', [
            'view' => 'profile',
            'title' => 'Profile - Fish Market',
            'transactions' => demo_transactions(),
        ]);
        break;
    case 'login':
        render('login', ['view' => 'login', 'title' => 'Login - Fish Market']);
        break;
    case 'register':
        render('register', ['view' => 'register', 'title' => 'Register - Fish Market']);
        break;
    case 'dashboard':
        if (!isFmAdmin()) { redirectUnauthorized(); }
        render('dashboard', [
            'view' => 'dashboard',
            'title' => 'Admin Dashboard - Fish Market',
            'orders' => all_orders(),
            'allProducts' => products(),
        ]);
        break;
    case 'dashboard_products':
        if (!isFmAdmin()) { redirectUnauthorized(); }
        render('dashboard_products', [
            'view' => 'dashboard_products',
            'title' => 'Admin Products - Fish Market',
            'allProducts' => products(),
            'categories' => categories(),
        ]);
        break;
    case 'dashboard_orders':
        if (!isFmAdmin()) { redirectUnauthorized(); }
        render('dashboard_orders', [
            'view' => 'dashboard_orders',
            'title' => 'Admin Orders - Fish Market',
            'orders' => all_orders(),
        ]);
        break;
    case 'dashboard_users':
        if (!isFmAdmin()) { redirectUnauthorized(); }
        render('dashboard_users', [
            'view' => 'dashboard_users',
            'title' => 'Admin Users - Fish Market',
            'allUsers' => all_users(),
        ]);
        break;
}
