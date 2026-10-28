<?php
// Turn off output buffering for streaming
if (ob_get_level()) ob_end_flush();

function __ifmAssetVersion(string $path): string {
    $f = __DIR__ . '/../' . $path;
    return file_exists($f) ? (string)filemtime($f) : '1';
}

// session already started in index.php or router.php
if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_name('IFMSESSID');
    session_start();
}

// ── Database ──
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=fishery_db;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function dbGetAll(string $sql, array $params = []): array {
    $s = db()->prepare($sql); $s->execute($params); return $s->fetchAll();
}
function dbGetRow(string $sql, array $params = []): ?array {
    $s = db()->prepare($sql); $s->execute($params); return $s->fetch() ?: null;
}
function dbGetValue(string $sql, array $params = []): mixed {
    $s = db()->prepare($sql); $s->execute($params); return $s->fetchColumn();
}
function dbExecute(string $sql, array $params = []): void {
    db()->prepare($sql)->execute($params);
}
function dbLastInsertId(): string {
    return db()->lastInsertId();
}

// ── Paths ──
$IFM_BASE = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($IFM_BASE === '' || $IFM_BASE === '.') $IFM_BASE = '';
define('IFM_BASE', $IFM_BASE);

function url_for(string $page, array $params = []): string {
    $pageMap = [
        'index'   => 'index.php',
        'shop'    => 'shop.php',
        'cart'    => 'cart.php',
        'checkout'=> 'checkout.php',
        'login'   => 'login.php',
        'wallet'  => 'wallet.php',
        'profile' => 'profile.php',
        'orders'  => 'orders.php',
        'recipes' => 'recipes.php',
        'about'   => 'about.php',
        'admin'   => 'admin.php',
        'favorites' => 'favorites.php',
        'track'   => 'track.php',
        'logout'  => 'profile.php?logout=1',
    ];
    $file = $pageMap[$page] ?? ($page . '.php');
    $q = http_build_query($params);
    return IFM_BASE . '/' . $file . ($q ? '?' . $q : '');
}

// ── Auth ──
function intl_login(int $userId): void {
    intl_merge_session_cart_to_db('user_id', $userId);
    $_SESSION['ifm_user_id'] = $userId;
    unset($_SESSION['ifm_restaurant_id']);
    session_regenerate_id(true);
}
function intl_logout(): void {
    unset($_SESSION['ifm_user_id'], $_SESSION['ifm_restaurant_id']);
    session_destroy();
}
function intl_user(): ?array {
    $id = $_SESSION['ifm_user_id'] ?? null;
    if (!$id) return null;
    return dbGetRow("SELECT * FROM export_user WHERE id = ? AND is_active = TRUE", [$id]);
}
function intl_restaurant_login(int $restaurantId): void {
    intl_merge_session_cart_to_db('restaurant_id', $restaurantId);
    $_SESSION['ifm_restaurant_id'] = $restaurantId;
    unset($_SESSION['ifm_user_id']);
    session_regenerate_id(true);
}
function intl_restaurant_user(): ?array {
    $id = $_SESSION['ifm_restaurant_id'] ?? null;
    if (!$id) return null;
    return dbGetRow("SELECT * FROM export_restaurant_user WHERE id = ? AND is_active = TRUE", [$id]);
}
function intl_current_user(): ?array {
    return intl_user() ?? intl_restaurant_user();
}
function intl_require_login(): void {
    if (!isset($_SESSION['ifm_user_id']) && !isset($_SESSION['ifm_restaurant_id'])) {
        header('Location: ' . url_for('login'));
        exit;
    }
}

// ── Wallet ──
function intl_wallet_user_id(): ?int {
    $rest = $_SESSION['ifm_restaurant_id'] ?? null;
    if ($rest) return null; // restaurant users use restaurant_id lookup
    $user = intl_user();
    return $user ? (int)$user['id'] : null;
}
function intl_wallet_restaurant_id(): ?int {
    return $_SESSION['ifm_restaurant_id'] ?? null;
}
function intl_wallet_balance(): float {
    $user = intl_user();
    if ($user) {
        return (float)dbGetValue("SELECT COALESCE(balance, 0) FROM export_wallets WHERE user_id = ?", [$user['id']]);
    }
    $rest = $_SESSION['ifm_restaurant_id'] ?? null;
    if ($rest) {
        return (float)dbGetValue("SELECT COALESCE(balance, 0) FROM export_wallets WHERE restaurant_id = ?", [$rest]);
    }
    return 0;
}
function intl_ensure_wallet(): int {
    $user = intl_user();
    if ($user) {
        $wallet = dbGetRow("SELECT id FROM export_wallets WHERE user_id = ?", [$user['id']]);
        if (!$wallet) {
            dbExecute("INSERT INTO export_wallets (user_id, balance, currency) VALUES (?, 0, 'USD')", [$user['id']]);
            return (int)dbLastInsertId();
        }
        return (int)$wallet['id'];
    }
    $rest = $_SESSION['ifm_restaurant_id'] ?? null;
    if ($rest) {
        $wallet = dbGetRow("SELECT id FROM export_wallets WHERE restaurant_id = ?", [$rest]);
        if (!$wallet) {
            dbExecute("INSERT INTO export_wallets (restaurant_id, balance, currency) VALUES (?, 0, 'USD')", [$rest]);
            return (int)dbLastInsertId();
        }
        return (int)$wallet['id'];
    }
    return 0;
}

// ── Cart helpers ──
function _intl_cart_owner(): ?array {
    $user = intl_user();
    if ($user) return ['field' => 'user_id', 'value' => (int)$user['id']];
    $rest = intl_restaurant_user();
    if ($rest) return ['field' => 'restaurant_id', 'value' => (int)$rest['id']];
    return null;
}

/**
 * Merge session cart into DB on login.
 * Combine quantities for identical items; add new items separately.
 */
function intl_merge_session_cart_to_db(string $field, int $id): void {

    $sessionCart = $_SESSION['intl_cart'] ?? [];
    if (empty($sessionCart)) return;

    foreach ($sessionCart as $item) {
        $slug = $item['slug'];
        $tier = $item['tier_label'] ?? '';
        $qty = (int)$item['quantity'];
        $price = (float)$item['unit_price'];

        $existing = dbGetRow(
            "SELECT id, quantity FROM export_cart WHERE $field = ? AND product_slug = ? AND tier_label = ?",
            [$id, $slug, $tier]
        );
        if ($existing) {
            $newQty = (int)$existing['quantity'] + $qty;
            dbExecute(
                "UPDATE export_cart SET quantity = ?, unit_price = ?, updated_at = NOW() WHERE id = ?",
                [$newQty, $price, (int)$existing['id']]
            );
        } else {
            dbExecute(
                "INSERT INTO export_cart ($field, product_slug, tier_label, quantity, unit_price) VALUES (?, ?, ?, ?, ?)",
                [$id, $slug, $tier, $qty, $price]
            );
        }
    }

    unset($_SESSION['intl_cart']);
}

// ── Cart (DB for logged-in, Session for guest) ──
function intl_cart_items(): array {
    $owner = _intl_cart_owner();
    if (!$owner) {
        return $_SESSION['intl_cart'] ?? [];
    }
    // Logged in → read from DB
    $field = $owner['field'];
    $rows = dbGetAll(
        "SELECT c.*, p.name AS product_name, p.image_url, p.unit
         FROM export_cart c
         JOIN product p ON c.product_slug = p.slug
         WHERE c.$field = ?",
        [$owner['value']]
    );
    $items = [];
    foreach ($rows as $row) {
        $key = $row['product_slug'] . ($row['tier_label'] ? '_' . $row['tier_label'] : '');
        $items[$key] = [
            'slug'          => $row['product_slug'],
            'product_name'  => $row['product_name'],
            'product_image' => $row['image_url'] ?? '',
            'unit_price'    => (float)$row['unit_price'],
            'unit'          => $row['tier_label'] ?: ($row['unit'] ?? 'kg'),
            'quantity'      => (int)$row['quantity'],
            'subtotal'      => (int)$row['quantity'] * (float)$row['unit_price'],
            'tier_label'    => $row['tier_label'],
        ];
    }
    return $items;
}

function intl_cart_count(): int {
    return array_sum(array_column(intl_cart_items(), 'quantity'));
}

function intl_cart_total(): float {
    $total = 0;
    foreach (intl_cart_items() as $item) $total += (float)$item['subtotal'];
    return $total;
}

function intl_save_cart(array $cart): void {
    $owner = _intl_cart_owner();
    if (!$owner) {
        $_SESSION['intl_cart'] = $cart;
        return;
    }
    // Logged in → sync full cart to DB
    $field = $owner['field'];
    $value = $owner['value'];
    db()->beginTransaction();
    dbExecute("DELETE FROM export_cart WHERE $field = ?", [$value]);
    foreach ($cart as $item) {
        dbExecute(
            "INSERT INTO export_cart ($field, product_slug, tier_label, quantity, unit_price) VALUES (?, ?, ?, ?, ?)",
            [$value, $item['slug'], $item['tier_label'] ?? '', (int)$item['quantity'], (float)$item['unit_price']]
        );
    }
    db()->commit();
}

// ── Product helpers ──
function intl_products(string $category = '', string $search = '', string $sort = ''): array {
    $sql = "SELECT p.*, COALESCE(p.image_url, '') as image_url, p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price, p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock FROM product p WHERE p.is_active = TRUE";
    $params = [];
    if ($category) { $sql .= " AND p.category = ?"; $params[] = $category; }
    if ($search) { $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)"; $like = "%$search%"; $params[] = $like; $params[] = $like; }
    $order = match($sort) {
        'price_asc' => 'ORDER BY p.tier_3kg_price ASC',
        'price_desc' => 'ORDER BY p.tier_3kg_price DESC',
        'newest' => 'ORDER BY p.id DESC',
        default => 'ORDER BY p.name ASC',
    };
    $sql .= " $order";
    return dbGetAll($sql, $params);
}
function intl_categories(): array {
    return dbGetAll("SELECT DISTINCT category as slug, category as name FROM product WHERE is_active = TRUE AND category IS NOT NULL AND category != '' ORDER BY category");
}

// ── Image ──
function intl_product_image(?string $image): string {
    if (empty($image)) return '';
    // If it's already a full URL, return it directly
    if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
        return $image;
    }
    // Fetch photos from PortManagement/static/img/products_db/
    $filename = basename(str_replace('\\', '/', $image));
    return 'img.php?file=' . urlencode($filename);
}

// ── Restaurant discount percentage ──
function intl_restaurant_discount(): float {
    // Check if logged-in user is a restaurant
    $rid = $_SESSION['ifm_restaurant_id'] ?? null;
    if (!$rid) return 0;
    try {
        $val = dbGetValue("SELECT discount_percent FROM export_restaurant_user WHERE id = ?", [(int)$rid]);
        return (float)($val ?: 0);
    } catch (Exception $e) { return 0; }
}

// ── Order number generator ──
function intl_generate_order_number($date = null): string {
    $d = $date ? new DateTime($date) : new DateTime();
    $prefix = 'IM' . $d->format('ym');
    try {
        $max = (string)dbGetValue(
            "SELECT MAX(order_number) FROM export_orders WHERE order_number LIKE ?",
            [$prefix . '%']
        );
        if ($max && str_starts_with($max, $prefix)) {
            $seq = (int)substr($max, -4) + 1;
        } else {
            $seq = 1;
        }
    } catch (Exception $e) { $seq = 1; }
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// ── Helpers ──
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function formatted_money(float $amount): string {
    return number_format($amount, 2);
}
function flash(?string $key = null): mixed {
    if ($key === null) {
        $msgs = $_SESSION['__flash'] ?? [];
        unset($_SESSION['__flash']);
        return $msgs;
    }
    return $_SESSION['__flash'][$key] ?? '';
}
function set_flash(string $key, string $value): void {
    $_SESSION['__flash'][$key] = $value;
}

// ── Recipe Favorites ──
function intl_favorite_user_id(): ?int { return $_SESSION['ifm_user_id'] ?? null; }
function intl_favorite_restaurant_id(): ?int { return $_SESSION['ifm_restaurant_id'] ?? null; }

function intl_recipe_favorites(): array {
    $uid = intl_favorite_user_id();
    $rid = intl_favorite_restaurant_id();
    if (!$uid && !$rid) return [];
    if ($uid) {
        $rows = dbGetAll("SELECT recipe_id FROM export_recipe_favorites WHERE user_id = ?", [$uid]);
    } else {
        $rows = dbGetAll("SELECT recipe_id FROM export_recipe_favorites WHERE restaurant_id = ?", [$rid]);
    }
    return array_map(function($r) { return (int)$r['recipe_id']; }, $rows);
}
function intl_is_recipe_favorited(int $recipeId): bool {
    $uid = intl_favorite_user_id();
    $rid = intl_favorite_restaurant_id();
    if (!$uid && !$rid) return false;
    if ($uid) return (bool)dbGetValue("SELECT COUNT(*) FROM export_recipe_favorites WHERE user_id = ? AND recipe_id = ?", [$uid, $recipeId]);
    return (bool)dbGetValue("SELECT COUNT(*) FROM export_recipe_favorites WHERE restaurant_id = ? AND recipe_id = ?", [$rid, $recipeId]);
}
function intl_toggle_recipe_favorite(int $recipeId): bool {
    $uid = intl_favorite_user_id();
    $rid = intl_favorite_restaurant_id();
    if (!$uid && !$rid) return false;
    if ($uid) {
        $exists = (bool)dbGetValue("SELECT COUNT(*) FROM export_recipe_favorites WHERE user_id = ? AND recipe_id = ?", [$uid, $recipeId]);
        if ($exists) { dbExecute("DELETE FROM export_recipe_favorites WHERE user_id = ? AND recipe_id = ?", [$uid, $recipeId]); return false; }
        else { dbExecute("INSERT INTO export_recipe_favorites (user_id, recipe_id) VALUES (?, ?)", [$uid, $recipeId]); return true; }
    } else {
        $exists = (bool)dbGetValue("SELECT COUNT(*) FROM export_recipe_favorites WHERE restaurant_id = ? AND recipe_id = ?", [$rid, $recipeId]);
        if ($exists) { dbExecute("DELETE FROM export_recipe_favorites WHERE restaurant_id = ? AND recipe_id = ?", [$rid, $recipeId]); return false; }
        else { dbExecute("INSERT INTO export_recipe_favorites (restaurant_id, recipe_id) VALUES (?, ?)", [$rid, $recipeId]); return true; }
    }
}

// ── Delivery QR ──
function intl_generate_delivery_qr(): string {
    return bin2hex(random_bytes(20));
}
function intl_qr_image_html(string $qrCode): string {
    $trackUrl = url_for('track', ['code' => $qrCode]);
    $qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($trackUrl);
    return '<img src="' . e($qrImg) . '" alt="QR Code" style="width:160px;height:160px;border-radius:12px;border:2px solid #e2e8f0">';
}

/**
 * Get real LAN IP — skips VirtualBox/VMware virtual adapters so QR codes
 * work when scanned from phones on the same network.
 */
function intl_lan_ip(): string {
    // gethostbynamel returns ALL IPs for this hostname
    $all = gethostbynamel(gethostname());
    if (!$all) return 'localhost';
    $skipPatterns = ['/^127\./', '/^169\.254\./', '/^192\.168\.56\./', '/^192\.168\.99\./'];
    foreach ($all as $ip) {
        $skip = false;
        foreach ($skipPatterns as $p) {
            if (preg_match($p, $ip)) { $skip = true; break; }
        }
        if (!$skip && $ip !== '::1') return $ip;
    }
    // Fallback: return the first non-loopback
    foreach ($all as $ip) {
        if ($ip !== '127.0.0.1' && $ip !== '::1') return $ip;
    }
    return 'localhost';
}

/**
 * Get full track URL for QR code, auto-detecting LAN IP when host is localhost.
 * This ensures QR codes scanned from phones on the same network will work.
 */
function intl_qr_track_url(string $qrCode): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        $lan = intl_lan_ip();
        if ($lan && $lan !== '127.0.0.1' && $lan !== '::1') {
            $port = parse_url('http://' . $host, PHP_URL_PORT);
            $host = $lan . ($port ? ':' . $port : '');
        }
    }
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $protocol . '://' . $host . '/International/track.php?code=' . urlencode($qrCode);
}
