<?php
declare(strict_types=1);

// Set session save path to a writable temp directory
$sessPath = sys_get_temp_dir() . '/fm_sessions';
if (!is_dir($sessPath)) @mkdir($sessPath, 0777, true);
session_save_path($sessPath);

// Persist session for 7 days (604800 seconds) — don't log out until browser closes or manual logout
ini_set('session.gc_maxlifetime', 604800);
ini_set('session.cookie_lifetime', 604800);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

session_start();

define('APP_ROOT', __DIR__ . '/..');

// ── Order number generator (FM + YYMM + monthly sequential) ──
function generateOrderNumber($date = null) {
    $d = $date ? new DateTime($date) : new DateTime();
    $prefix = 'FM' . $d->format('ym');
    try {
        $count = (int)dbGetValue(
            "SELECT COUNT(*) FROM market_order WHERE created_at >= ? AND created_at <= ?",
            [$d->format('Y-m-01'), $d->format('Y-m-t 23:59:59')]
        );
    } catch (Exception $e) { $count = 0; }
    return $prefix . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
}

// ── Supabase DB connection ──

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=fishery_db;charset=utf8mb4", 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function dbGetRow(string $sql, array $params = []): ?array {
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Exception) {
        return null;
    }
}

function dbGetAll(string $sql, array $params = []): array {
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception) {
        return [];
    }
}

function dbExecute(string $sql, array $params = []): int {
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (Exception) {
        return 0;
    }
}

function dbGetValue(string $sql, array $params = []): mixed {
    $row = dbGetRow($sql, $params);
    return $row ? reset($row) : null;
}

// ── Helpers ──

function load_json(string $file): array {
    return [];
}

function asset_url(string $path): string {
    return './media/' . ltrim($path, '/');
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function url_for(string $page, array $params = []): string {
    $query = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($query);
}

function current_page(): string {
    return $_GET['page'] ?? 'home';
}

function formatProduct(array $p): array {
    // Use tier data directly from the main query (no separate DB call)
    $t3s = (int)($p['tier_3kg_stock'] ?? 0);
    $t6s = (int)($p['tier_6kg_stock'] ?? 0);
    $t10s = (int)($p['tier_10kg_stock'] ?? 0);
    $t3p = $p['tier_3kg_price'] ?? null;
    $t6p = $p['tier_6kg_price'] ?? null;
    $t10p = $p['tier_10kg_price'] ?? null;

    $totalStock = $t3s + $t6s + $t10s;
    $startPrice = (float)($t3p ?: ($t6p ?: ($t10p ?: 0)));

    $tiers = [];
    $map = [
        ['label' => '3kg', 'weight_kg' => 3.0, 'price' => $t3p, 'stock' => $t3s],
        ['label' => '6kg', 'weight_kg' => 6.0, 'price' => $t6p, 'stock' => $t6s],
        ['label' => '10kg', 'weight_kg' => 10.0, 'price' => $t10p, 'stock' => $t10s],
    ];
    foreach ($map as $m) {
        if ($m['price'] !== null) {
            $tiers[] = [
                'id' => $m['label'],
                'label' => $m['label'],
                'weight_kg' => $m['weight_kg'],
                'price' => (float)$m['price'],
                'stock' => $m['stock'],
            ];
        }
    }

    return [
        'id' => $p['id'],
        'name' => $p['name'],
        'slug' => $p['slug'],
        'sku' => $p['sku'] ?? '',
        'category_slug' => $p['category'],
        'category_name' => ucwords((string)($p['category'])),
        'unit' => $p['unit'] ?? 'unit',
        'price' => $startPrice,
        'domestic_price' => $startPrice,
        'export_price' => (float)($p['export_price'] ?? 0),
        'description' => $p['description'] ?? '',
        'origin' => $p['origin'] ?? '',
        'image' => $p['image_url'] ?? '',
        'is_featured' => !empty($p['is_featured']),
        'is_active' => !empty($p['is_active']),
        'stock' => $totalStock,
        'tiers' => $tiers,
        'freshness' => $p['freshness'] ?? 'fresh',
        'created_at' => $p['created_at'] ?? '',
        'vessel_name' => $p['vessel_name'] ?? '',
        'trip_id' => (int)($p['trip_id'] ?? 0),
        'catch_id' => (int)($p['catch_id'] ?? 0),
        'pieces_caught' => (int)($p['pieces_caught'] ?? 0),
        'estimated_weight_kg' => (float)($p['estimated_weight_kg'] ?? 0),
        'landed_at' => $p['landed_at'] ?? ($p['catch_landed_at'] ?? null),
    ];
}

// ── Product / Category functions (live from Supabase) ──

function categories(): array {
    $rows = dbGetAll("SELECT DISTINCT category FROM product WHERE is_active = TRUE ORDER BY category");
    $cats = [];
    foreach ($rows as $r) {
        $slug = $r['category'];
        $cats[] = ['slug' => $slug, 'name' => ucwords($slug)];
    }
    return $cats;
}

function products(): array {
    $sql = "SELECT p.id, p.product_id, p.name, p.slug, p.category, p.freshness, p.unit,
                   p.domestic_price, p.export_price, p.description, p.origin, p.image_url,
                   p.is_active, p.created_at, p.updated_at,
                   p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock,
                   p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price
            FROM product p
            WHERE p.is_active = TRUE
            ORDER BY p.name";
    $rows = dbGetAll($sql);
    return array_map('formatProduct', $rows);
}

function featured_products(): array {
    $sql = "SELECT p.id, p.product_id, p.name, p.slug, p.category, p.freshness, p.unit,
                   p.domestic_price, p.export_price, p.description, p.origin, p.image_url,
                   p.is_active, p.created_at, p.updated_at,
                   p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock,
                   p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price
            FROM product p
            WHERE p.is_active = TRUE
            ORDER BY p.name
            LIMIT 8";
    $rows = dbGetAll($sql);
    return array_map('formatProduct', $rows);
}

function latest_products(): array {
    $sql = "SELECT p.id, p.product_id, p.name, p.slug, p.category, p.freshness, p.unit,
                   p.domestic_price, p.export_price, p.description, p.origin, p.image_url,
                   p.is_active, p.created_at, p.updated_at,
                   p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock,
                   p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price
            FROM product p
            WHERE p.is_active = TRUE
            ORDER BY p.id DESC LIMIT 8";
    $rows = dbGetAll($sql);
    return array_map('formatProduct', $rows);
}

function product_by_slug(string $slug): ?array {
    $sql = "SELECT p.id, p.product_id, p.name, p.slug, p.category, p.freshness, p.unit,
                   p.domestic_price, p.export_price, p.description, p.origin, p.image_url,
                   p.is_active, p.created_at, p.updated_at,
                   p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock,
                   p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price
            FROM product p
            WHERE p.slug = ? AND p.is_active = TRUE";
    $row = dbGetRow($sql, [$slug]);
    return $row ? formatProduct($row) : null;
}

function related_products(string $categorySlug, string $excludeSlug): array {
    $sql = "SELECT p.id, p.product_id, p.name, p.slug, p.category, p.freshness, p.unit,
                   p.domestic_price, p.export_price, p.description, p.origin, p.image_url,
                   p.is_active, p.created_at, p.updated_at,
                   p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock,
                   p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price
            FROM product p
            WHERE p.is_active = TRUE AND p.category = ? AND p.slug != ?
            ORDER BY p.name LIMIT 4";
    $rows = dbGetAll($sql, [$categorySlug, $excludeSlug]);
    return array_map('formatProduct', $rows);
}

/**
 * Get the most recently accepted catches (from trip_catches)
 * This powers the "Newly Listed" section on the home page,
 * showing what was just caught and added to inventory.
 */
function recent_catches(int $limit = 8): array {
    $lim = (int)$limit;
    // Get completed trips, newest first
    $trips = dbGetAll(
        "SELECT ft.id, ft.vessel_id, v.name as vessel_name, ft.zone_id, ft.completed_at, ft.created_at
         FROM fishing_trips ft
         JOIN vessels v ON v.id = ft.vessel_id
         WHERE ft.status = 'completed'
         ORDER BY COALESCE(ft.completed_at, ft.created_at) DESC
         LIMIT 10"
    );
    if (empty($trips)) return [];

    $result = [];
    foreach ($trips as $trip) {
        if (count($result) >= $lim) break;

        $tid = (int)$trip['id'];
        $rows = dbGetAll(
            "SELECT p.id, p.product_id, p.name, p.slug, p.category, p.freshness, p.unit,
                    p.domestic_price, p.export_price, p.description, p.origin, p.image_url,
                    p.is_active, p.created_at, p.updated_at,
                    p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock,
                    p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price,
                    tc.processed_at as catch_landed_at, tc.pieces_caught, tc.estimated_weight_kg,
                    tc.id as catch_id, tc.trip_id,
                    COALESCE(tc.processed_at, ft.completed_at) as landed_at,
                    v.name as vessel_name
             FROM trip_catches tc
             JOIN product p ON p.id = tc.product_id
             JOIN fishing_trips ft ON ft.id = tc.trip_id
             JOIN vessels v ON v.id = ft.vessel_id
             WHERE tc.trip_id = ? AND tc.status = 'accepted'
             ORDER BY tc.processed_at ASC",
            [$tid]
        );
        $remaining = $lim - count($result);
        $formatted = array_map('formatProduct', array_slice($rows, 0, $remaining));
        $result = array_merge($result, $formatted);
    }

    return $result;
}

function filter_products(?string $category, string $search, string $sort): array {
    $where = "p.is_active = TRUE";
    $params = [];
    if ($category) {
        $where .= " AND p.category = ?";
        $params[] = $category;
    }
    if ($search !== '') {
        $where .= " AND (LOWER(p.name) LIKE ? OR LOWER(COALESCE(p.description,'')) LIKE ? OR LOWER(COALESCE(p.origin,'')) LIKE ?)";
        $like = '%' . strtolower($search) . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $order = match ($sort) {
        'price_asc' => 'COALESCE(p.tier_3kg_price, 0) ASC',
        'price_desc' => 'COALESCE(p.tier_3kg_price, 0) DESC',
        'newest' => 'p.id DESC',
        default => 'p.name ASC',
    };
    $sql = "SELECT p.id, p.product_id, p.name, p.slug, p.category, p.freshness, p.unit,
                   p.domestic_price, p.export_price, p.description, p.origin, p.image_url,
                   p.is_active, p.created_at, p.updated_at,
                   p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock,
                   p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price
            FROM product p
            WHERE $where
            ORDER BY $order";
    $rows = dbGetAll($sql, $params);
    return array_map('formatProduct', $rows);
}

function sort_products(array $products, string $sort): array {
    usort($products, function (array $a, array $b) use ($sort): int {
        if ($sort === 'price_asc') return (float)$a['price'] <=> (float)$b['price'];
        if ($sort === 'price_desc') return (float)$b['price'] <=> (float)$a['price'];
        if ($sort === 'newest') return (int)$b['id'] <=> (int)$a['id'];
        return strcmp($a['name'], $b['name']);
    });
    return $products;
}

function product_image(?string $image): string {
    if (empty($image)) return '';
    // 如果已经是完整 URL 就直接返回
    if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
        return $image;
    }
    // 从 PortManagement/static/img/products_db/ 拿照片
    $filename = basename(str_replace('\\', '/', $image));
    return 'img.php?file=' . urlencode($filename);
}

// ── Cart functions (session + DB for logged-in users) ──

/** Check if we should use DB cart (user is logged in) */
function _cart_use_db(): bool {
    return !empty($_SESSION['fm_user_id']);
}

/** Generate cart key from slug and tier_id */
function _cart_key(string $slug, string $tierId): string {
    return $slug . ($tierId !== '' ? '_t' . $tierId : '');
}

/** Fetch cart items from DB for the current logged-in user */
function market_cart_items_db(): array {
    $userId = (int)($_SESSION['fm_user_id'] ?? 0);
    if ($userId <= 0) return [];
    $rows = dbGetAll(
        "SELECT slug, tier_id, quantity FROM market_cart WHERE user_id = ? ORDER BY id",
        [$userId]
    );
    $cart = [];
    foreach ($rows as $row) {
        $key = _cart_key($row['slug'], $row['tier_id']);
        $cart[$key] = [
            'slug' => $row['slug'],
            'tier_id' => $row['tier_id'],
            'quantity' => (int)$row['quantity'],
        ];
    }
    return $cart;
}

/** Save cart items to DB for the current logged-in user (full sync) */
function market_cart_save_db(array $cart): void {
    $userId = (int)($_SESSION['fm_user_id'] ?? 0);
    if ($userId <= 0) return;

    try {
        db()->beginTransaction();
        // Clear existing cart
        dbExecute("DELETE FROM market_cart WHERE user_id = ?", [$userId]);
        // Insert new items
        foreach ($cart as $key => $item) {
            $slug = $item['slug'] ?? $key;
            // Extract tier_id from key if not explicitly provided
            $tierId = $item['tier_id'] ?? '';
            if ($tierId === '' && str_contains($key, '_t')) {
                $parts = explode('_t', $key, 2);
                $tierId = $parts[1] ?? '';
            }
            $qty = max(1, (int)($item['quantity'] ?? 0));
            dbExecute(
                "INSERT INTO market_cart (user_id, slug, tier_id, quantity) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = NOW()",
                [$userId, $slug, $tierId, $qty]
            );
        }
        db()->commit();
    } catch (Exception $e) {
        db()->rollBack();
    }
}

/** Clear DB cart for the current logged-in user */
function market_cart_clear_db(): void {
    $userId = (int)($_SESSION['fm_user_id'] ?? 0);
    if ($userId > 0) {
        dbExecute("DELETE FROM market_cart WHERE user_id = ?", [$userId]);
    }
}

/** Merge session cart into DB cart (called after login) */
function market_cart_merge_session_to_db(): void {
    $sessionCart = $_SESSION['cart'] ?? [];
    if (empty($sessionCart)) return;

    $userId = (int)($_SESSION['fm_user_id'] ?? 0);
    if ($userId <= 0) return;

    try {
        db()->beginTransaction();
        foreach ($sessionCart as $key => $item) {
            $slug = $item['slug'] ?? $key;
            $tierId = $item['tier_id'] ?? '';
            if ($tierId === '' && str_contains($key, '_t')) {
                $parts = explode('_t', $key, 2);
                $tierId = $parts[1] ?? '';
            }
            $qty = max(1, (int)($item['quantity'] ?? 0));

            // Check if item already exists in DB cart
            $existing = dbGetRow(
                "SELECT id, quantity FROM market_cart WHERE user_id = ? AND slug = ? AND tier_id = ?",
                [$userId, $slug, $tierId]
            );
            if ($existing) {
                // Merge quantities
                $newQty = (int)$existing['quantity'] + $qty;
                dbExecute(
                    "UPDATE market_cart SET quantity = ?, updated_at = NOW() WHERE id = ?",
                    [$newQty, $existing['id']]
                );
            } else {
                dbExecute(
                    "INSERT INTO market_cart (user_id, slug, tier_id, quantity) VALUES (?, ?, ?, ?)",
                    [$userId, $slug, $tierId, $qty]
                );
            }
        }
        db()->commit();
        // Clear session cart after merge
        $_SESSION['cart'] = [];
    } catch (Exception $e) {
        db()->rollBack();
    }
}

/** Get cart items - uses DB if logged in, session otherwise */
function cart_items(): array {
    if (_cart_use_db()) {
        return market_cart_items_db();
    }
    return $_SESSION['cart'] ?? [];
}

/** Save cart - uses DB if logged in, session otherwise */
function save_cart(array $cart): void {
    if (_cart_use_db()) {
        market_cart_save_db($cart);
    } else {
        $_SESSION['cart'] = $cart;
    }
}

function cart_count(): int {
    $count = 0;
    foreach (cart_items() as $item) $count += (int)($item['quantity'] ?? 0);
    return $count;
}

function cart_details(): array {
    $items = [];
    $total = 0.0;
    foreach (cart_items() as $key => $cartItem) {
        $slug = $cartItem['slug'] ?? $key;
        $product = product_by_slug($slug);
        if (!$product) continue;
        $quantity = (int)($cartItem['quantity'] ?? 0);
        $tierId = $cartItem['tier_id'] ?? '';
        $tierPrice = 0; $tierLabel = ''; $tierKg = 0;
        if ($tierId && !empty($product['tiers'])) {
            foreach ($product['tiers'] as $t) {
                if ($t['id'] === $tierId) { $tierPrice = (float)$t['price']; $tierLabel = $t['label']; $tierKg = (float)$t['weight_kg']; break; }
            }
        }
        if ($tierPrice <= 0) $tierPrice = (float)$product['price'];
        $lineTotal = $quantity * $tierPrice;
        $items[] = ['key' => $key, 'slug' => $slug, 'product' => $product, 'quantity' => $quantity, 'tier_id' => $tierId, 'tier_label' => $tierLabel, 'tier_kg' => $tierKg, 'unit_price' => $tierPrice, 'subtotal' => $lineTotal];
        $total += $lineTotal;
    }
    return ['items' => $items, 'total' => $total];
}

// ── Auth / Profile ──

/** Current logged-in market_user, or null */
function loggedInUser(): ?array {
    $id = $_SESSION['fm_user_id'] ?? null;
    if (!$id) return null;
    $user = dbGetRow("SELECT * FROM market_user WHERE id = ? AND is_active = TRUE", [$id]);
    if (!$user) {
        unset($_SESSION['fm_user_id']);
        return null;
    }
    return $user;
}

function demo_profile(): array {
    $u = loggedInUser();
    if (!$u) return [
        'username' => '', 'first_name' => '', 'last_name' => '', 'full_name' => 'Guest User',
        'email' => '', 'phone' => '', 'address' => '', 'role' => 'user', 'wallet_balance' => 0,
    ];
    return [
        'username' => $u['username'] ?? '',
        'first_name' => $u['full_name'] ?? '',
        'last_name' => '',
        'full_name' => $u['full_name'] ?? $u['username'] ?? 'User',
        'email' => $u['email'] ?? '',
        'phone' => $u['phone'] ?? '',
        'address' => $u['address_line1'] ?? '',
        'role' => $u['role'] ?? 'customer',
        'wallet_balance' => (float)($u['balance'] ?? 0),
    ];
}

function isFmLoggedIn(): bool {
    return !empty($_SESSION['fm_user_id']);
}

function isFmAdmin(): bool {
    $u = loggedInUser();
    return $u && ($u['role'] ?? 'customer') === 'admin';
}

function redirectUnauthorized(): void {
    add_flash('error', 'You do not have permission to access that page.');
    header('Location: ' . url_for('home'));
    exit;
}

// ── Admin helpers ──

function admin_sidebar(string $active): string {
    $items = [
        'dashboard' => ['📊', 'Overview'],
        'dashboard_products' => ['🐟', 'Products'],
        'dashboard_orders' => ['📦', 'Orders'],
        'dashboard_users' => ['👥', 'Users'],
    ];
    $links = '<nav class="admin-nav">';
    foreach ($items as $page => [$icon, $label]) {
        $cls = $active === $page ? ' class="active"' : '';
        $links .= "<a href=\"" . url_for($page) . "\"{$cls}>{$icon} {$label}</a>";
    }
    $links .= '<a href="' . url_for('home') . '">🏠 Back to Store</a></nav>';
    return $links;
}

function admin_css(): string {
    return '<style>.admin-layout{display:grid;grid-template-columns:220px 1fr;gap:2rem;align-items:start}.admin-sidebar,.admin-card,.filter-panel,.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);backdrop-filter:blur(12px)}.admin-sidebar{overflow:hidden;position:sticky;top:80px}.admin-sidebar-header{padding:1.5rem;border-bottom:1px solid var(--border)}.admin-nav a{display:flex;align-items:center;gap:.75rem;padding:.75rem 1.5rem;color:var(--muted);text-decoration:none;font-size:.875rem;font-weight:500;border-left:3px solid transparent}.admin-nav a.active,.admin-nav a:hover{color:var(--brand);background:rgba(45,212,191,.08);border-left-color:var(--brand)}.admin-nav a.active{font-weight:700;color:#fff}.stat-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;margin-bottom:2rem}.stat-card{padding:1.5rem;display:flex;align-items:center;gap:1rem}.admin-card{overflow:hidden;margin-bottom:1.5rem}.admin-card-header{display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--border)}.filter-panel{padding:1.25rem 1.5rem;margin-bottom:1.25rem}.admin-search{width:100%;padding:.65rem 1rem;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:.85rem;outline:none}.admin-search:focus{border-color:var(--brand)}@media (max-width:900px){.admin-layout{grid-template-columns:1fr}.admin-sidebar{position:static}.stat-cards{grid-template-columns:1fr 1fr}}</style>';
}

function all_users(): array {
    return dbGetAll(
        "SELECT u.id, u.username, u.email, u.full_name, u.role, u.account_status,
                u.is_active, u.phone, u.address_line1, u.created_at, u.last_login_at,
                COALESCE(u.balance, 0) as wallet_balance
         FROM market_user u
         ORDER BY u.created_at DESC"
    );
}

function search_orders(string $q): array {
    $like = '%' . strtolower($q) . '%';
    $orders = dbGetAll(
        "SELECT o.id, o.order_number, o.user_id, o.status, o.total_amount,
                o.shipping_address, o.phone, o.notes, o.created_at, u.username, u.full_name
         FROM market_order o
         LEFT JOIN market_user u ON u.id = o.user_id
         WHERE LOWER(o.order_number) LIKE ?
            OR LOWER(COALESCE(u.username,'')) LIKE ?
            OR LOWER(COALESCE(u.full_name,'')) LIKE ?
            OR LOWER(o.status) LIKE ?
         ORDER BY o.created_at DESC",
        [$like, $like, $like, $like]
    );
    return array_map(function ($o) {
        $status = $o['status'];
        $statusClass = match ($status) {
            'pending' => 'warning', 'confirmed' => 'info', 'shipping' => 'primary',
            'delivered' => 'success', 'completed' => 'success', 'cancelled' => 'danger',
            default => 'info',
        };
        $statusLabel = match ($status) {
            'pending' => 'Pending', 'confirmed' => 'Confirmed', 'shipping' => 'Shipping',
            'delivered' => 'Delivered', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
            default => ucfirst($status),
        };
        return [
            'order_number' => $o['order_number'],
            'created_at' => date('d M Y, g:i A', strtotime($o['created_at'])),
            'status' => $status, 'status_class' => $statusClass, 'status_label' => $statusLabel,
            'total_amount' => $o['total_amount'],
            'shipping_address' => $o['shipping_address'] ?? '',
            'phone' => $o['phone'] ?? '',
            'notes' => $o['notes'] ?? '',
            'customer' => $o['full_name'] ?: $o['username'] ?: "User #{$o['user_id']}",
            'items' => [],
        ];
    }, $orders);
}

function search_products(string $q): array {
    $like = '%' . strtolower($q) . '%';
    $rows = dbGetAll(
        "SELECT p.id, p.product_id, p.name, p.slug, p.category, p.freshness, p.unit,
                p.domestic_price, p.export_price, p.description, p.origin, p.image_url,
                p.is_active, p.created_at, p.updated_at,
                p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock,
                p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price
         FROM product p
         WHERE p.is_active = TRUE
           AND (LOWER(p.name) LIKE ? OR LOWER(COALESCE(p.description,'')) LIKE ? OR LOWER(COALESCE(p.origin,'')) LIKE ?)
         ORDER BY p.name",
        [$like, $like, $like]
    );
    return array_map('formatProduct', $rows);
}

function search_users(string $q): array {
    $like = '%' . strtolower($q) . '%';
    return dbGetAll(
        "SELECT u.id, u.username, u.email, u.full_name, u.role, u.account_status,
                u.is_active, u.phone, u.address_line1, u.created_at, u.last_login_at,
                COALESCE(u.balance, 0) as wallet_balance
         FROM market_user u
         WHERE LOWER(COALESCE(u.username,'')) LIKE ?
            OR LOWER(COALESCE(u.email,'')) LIKE ?
            OR LOWER(COALESCE(u.full_name,'')) LIKE ?
         ORDER BY u.created_at DESC",
        [$like, $like, $like]
    );
}

// ── Admin: all orders (all users) ──

function all_orders(): array {
    $orders = dbGetAll(
        "SELECT o.id, o.order_number, o.user_id, o.status, o.total_amount,
                o.shipping_address, o.phone, o.notes, o.items, o.created_at, u.username, u.full_name
         FROM market_order o
         LEFT JOIN market_user u ON u.id = o.user_id
         ORDER BY o.created_at DESC"
    );
    return array_map(function ($o) {
        $status = $o['status'];
        $statusClass = match ($status) {
            'pending' => 'warning', 'confirmed' => 'info', 'shipping' => 'primary',
            'delivered' => 'success', 'completed' => 'success', 'cancelled' => 'danger',
            default => 'info',
        };
        $statusLabel = match ($status) {
            'pending' => 'Pending', 'confirmed' => 'Confirmed', 'shipping' => 'Shipping',
            'delivered' => 'Delivered', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
            default => ucfirst($status),
        };
        $rawItems = json_decode($o['items'] ?? '[]', true) ?: [];
        $items = array_map(fn($i) => [
            'image' => $i['image_url'] ?? '',
            'product_name' => $i['product_name'] ?? 'Product',
            'quantity' => (int)($i['qty'] ?? 0),
            'product_price' => (float)($i['unit_price'] ?? 0),
            'subtotal' => (float)($i['subtotal'] ?? 0),
        ], $rawItems);
        return [
            'order_number' => $o['order_number'],
            'created_at' => date('d M Y, g:i A', strtotime($o['created_at'])),
            'status' => $status,
            'status_class' => $statusClass,
            'status_label' => $statusLabel,
            'total_amount' => $o['total_amount'],
            'shipping_address' => $o['shipping_address'] ?? '',
            'phone' => $o['phone'] ?? '',
            'notes' => $o['notes'] ?? '',
            'customer' => $o['full_name'] ?: $o['username'] ?: "User #{$o['user_id']}",
            'items' => $items,
        ];
    }, $orders);
}

// ── Orders ──

function demo_orders(): array {
    $u = loggedInUser();
    if (!$u) return [];
    $orders = dbGetAll(
        "SELECT o.id, o.order_number, o.status, o.total_amount, o.shipping_address, o.phone, o.notes, o.items, o.paid_at, o.updated_at, o.created_at,
                s.status as shipment_status
         FROM market_order o
         LEFT JOIN market_shipments s ON s.order_id = o.id
         WHERE o.user_id = ? ORDER BY o.created_at DESC",
        [$u['id']]
    );
    return array_map(function ($o) {
        $status = $o['status'];
        $shipStatus = $o['shipment_status'] ?? null;
        $statusClass = match ($status) {
            'pending' => 'warning', 'confirmed' => 'info', 'shipping' => 'primary',
            'delivered' => 'success', 'completed' => 'success', 'cancelled' => 'danger',
            default => 'info',
        };
        $statusLabel = match ($status) {
            'pending' => 'Pending', 'confirmed' => 'Confirmed', 'shipping' => 'Shipping',
            'delivered' => 'Delivered', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
            default => ucfirst($status),
        };
        $rawItems = json_decode($o['items'] ?? '[]', true) ?: [];
        $items = array_map(fn($i) => [
            'image' => $i['image_url'] ?? '',
            'product_name' => $i['product_name'] ?? 'Product',
            'quantity' => (int)($i['qty'] ?? 0),
            'product_price' => (float)($i['unit_price'] ?? 0),
            'subtotal' => (float)($i['subtotal'] ?? 0),
        ], $rawItems);
        return [
            'order_number' => $o['order_number'],
            'created_at' => date('d M Y, g:i A', strtotime($o['created_at'])),
            'paid_at' => $o['paid_at'] ? date('d M Y, g:i A', strtotime($o['paid_at'])) : null,
            'updated_at' => $o['updated_at'] ? date('d M Y, g:i A', strtotime($o['updated_at'])) : null,
            'status' => $status,
            'shipment_status' => $shipStatus,
            'status_class' => $statusClass,
            'status_label' => $statusLabel,
            'total_amount' => $o['total_amount'],
            'shipping_address' => $o['shipping_address'] ?? '',
            'phone' => $o['phone'] ?? '',
            'notes' => $o['notes'] ?? '',
            'items' => $items,
        ];
    }, $orders);
}

function demo_transactions(): array {
    $u = loggedInUser();
    if (!$u) return [];
    $txns = dbGetAll(
        "SELECT id, transaction_type, amount, balance_before, balance_after, description, created_at
         FROM market_wallet_txn WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
        [$u['id']]
    );
    return array_map(function ($t) {
        $type = $t['transaction_type'];
        $typeLabel = match ($type) {
            'topup' => 'Top Up',
            'payment' => 'Payment',
            'refund' => 'Refund',
            default => ucfirst($type),
        };
        return [
            'id' => $t['id'],
            'type' => $type,
            'type_label' => $typeLabel,
            'amount' => $t['amount'],
            'balance_before' => $t['balance_before'],
            'balance_after' => $t['balance_after'],
            'description' => $t['description'] ?? '',
            'created_at' => date('d M Y, g:i A', strtotime($t['created_at'])),
        ];
    }, $txns);
}

// ── Misc ──

function formatted_money(float $value): string {
    return number_format($value, 2, '.', '');
}

function add_flash(string $type, string $message): void {
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function consume_flashes(): array {
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

function status_badge_class(string $status): string {
    return match ($status) {
        'pending' => 'warning', 'paid' => 'info', 'shipping' => 'primary',
        'completed' => 'success', 'cancelled' => 'danger',
        default => 'info',
    };
}

// ── Render ──

function render(string $view, array $data = []): void {
    extract($data, EXTR_SKIP);
    $flashes = consume_flashes();
    $profile = demo_profile();
    $page = current_page();
    require APP_ROOT . '/includes/layout.php';
}

// ── Handle POST actions ──

function handle_cart_actions(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    // ── Add to cart ──
    if ($action === 'add_to_cart') {
        $slug = (string)($_POST['slug'] ?? '');
        $tierId = (string)($_POST['tier_id'] ?? '');
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        // ── Stock validation ──
        if ($tierId !== '') {
            $stockCol = match ($tierId) { '3kg' => 'tier_3kg_stock', '6kg' => 'tier_6kg_stock', '10kg' => 'tier_10kg_stock', default => null };
            if ($stockCol) {
                $avail = (int)dbGetValue("SELECT $stockCol FROM product WHERE slug = ?", [$slug]);
                $cart = cart_items();
                $key = $slug . '_t' . $tierId;
                $existingQty = (int)($cart[$key]['quantity'] ?? 0);
                if ($avail < ($quantity + $existingQty)) {
                    add_flash('error', "Not enough stock for $tierId. Available: $avail.");
                    $referer = $_SERVER['HTTP_REFERER'] ?? url_for('shop');
                    header('Location: ' . $referer);
                    exit;
                }
            }
        }

        $cart = cart_items();
        $key = $slug . '_t' . $tierId;
        $cart[$key] = ['quantity' => ($cart[$key]['quantity'] ?? 0) + $quantity, 'tier_id' => $tierId, 'slug' => $slug];
        save_cart($cart);
        add_flash('success', 'Item added to cart.');
        // Go back to product page or shop
        $referer = $_SERVER['HTTP_REFERER'] ?? url_for('shop');
        header('Location: ' . $referer);
        exit;
    }

    // ── Remove from cart ──
    if ($action === 'remove_cart') {
        $key = (string)($_POST['key'] ?? '');
        $cart = cart_items();
        unset($cart[$key]);
        save_cart($cart);
        header('Location: ' . url_for('cart'));
        exit;
    }

    // ── Auto-update cart quantity (JS) ──
    if ($action === 'update_cart_qty') {
        $key = (string)($_POST['key'] ?? '');
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        $cart = cart_items();
        if (isset($cart[$key])) {
            // Parse slug and tier from key (e.g. "ikan-kembong_t3kg" → slug="ikan-kembong", tier="3kg")
            $item = $cart[$key];
            $parts = explode('_t', $key);
            $slug = $parts[0] ?? '';
            $tierId = $parts[1] ?? '';
            
            // Check stock if quantity increased
            if ($quantity > 0 && $tierId !== '') {
                $stockCol = match ($tierId) { '3kg' => 'tier_3kg_stock', '6kg' => 'tier_6kg_stock', '10kg' => 'tier_10kg_stock', default => null };
                if ($stockCol) {
                    $avail = (int)dbGetValue("SELECT $stockCol FROM product WHERE slug = ?", [$slug]);
                    if ($quantity > $avail) {
                        $quantity = $avail; // Cap at available stock
                    }
                }
            }
            
            $cart[$key]['quantity'] = $quantity;
            save_cart($cart);
        }
        exit; // AJAX, no redirect
    }

    // ── Login ──
    if ($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $user = dbGetRow(
            "SELECT id, username, email, password_hash, full_name FROM market_user
             WHERE (username = ? OR LOWER(email) = LOWER(?)) AND is_active = TRUE",
            [$username, $username]
        );
        if ($user && verifyPassword($password, $user['password_hash'])) {
            $_SESSION['fm_user_id'] = $user['id'];
            // Merge guest session cart into DB cart
            market_cart_merge_session_to_db();
            dbExecute("UPDATE market_user SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
            add_flash('success', 'Welcome back, ' . ($user['full_name'] ?: $user['username']) . '!');
            header('Location: ' . url_for('home'));
        } else {
            add_flash('error', 'Invalid username or password.');
            header('Location: ' . url_for('login'));
        }
        exit;
    }

    // ── Register ──
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (strlen($password) < 6) {
            add_flash('error', 'Password must be at least 6 characters.');
            header('Location: ' . url_for('register'));
            exit;
        }
        if ($password !== $confirmPassword) {
            add_flash('error', 'Passwords do not match.');
            header('Location: ' . url_for('register'));
            exit;
        }
        $existing = dbGetRow("SELECT id FROM market_user WHERE username = ? OR LOWER(email) = LOWER(?)", [$username, $email]);
        if ($existing) {
            add_flash('error', 'Username or Email is already taken.');
            header('Location: ' . url_for('register'));
            exit;
        }
        $hash = hashPassword($password);
        try {
            $stmt = db()->prepare(
                "INSERT INTO market_user (username, email, phone, password_hash, full_name, account_status, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 'active', TRUE, NOW(), NOW())"
            );
            $stmt->execute([$username, $email, $phone, $hash, $username]);
            add_flash('success', 'Registration successful! Please sign in.');
            header('Location: ' . url_for('login'));
        } catch (Exception $e) {
            add_flash('error', 'Registration failed: ' . $e->getMessage());
            header('Location: ' . url_for('register'));
        }
        exit;
    }

    // ── Logout ──
    if ($action === 'logout') {
        $_SESSION = [];
        setcookie(session_name(), '', time() - 3600, '/');
        session_destroy();
        header('Location: ' . url_for('home'));
        exit;
    }

    // ── Checkout ──
    if ($action === 'checkout') {
        $u = loggedInUser();
        if (!$u) {
            add_flash('error', 'Please login first.');
            header('Location: ' . url_for('login'));
            exit;
        }
        $cart = cart_details();
        if (empty($cart['items'])) {
            add_flash('error', 'Your cart is empty.');
            header('Location: ' . url_for('cart'));
            exit;
        }
        $address = trim($_POST['shipping_address'] ?? '');
        if ($address === '') $address = $u['address_line1'] ?? '';
        $recipientName = trim($_POST['recipient_name'] ?? $u['full_name'] ?? '');
        // Prepend recipient name to address for shipping label
        if ($recipientName !== '') {
            $address = "Recipient: $recipientName\n$address";
        }
        $phone = trim($_POST['phone'] ?? $u['phone'] ?? '');
        $notes = trim($_POST['note'] ?? '');
        $total = $cart['total'];

        // Check wallet balance
        $balance = (float)($u['balance'] ?? 0);
        if ($balance < $total) {
            add_flash('error', 'Insufficient wallet balance. Please top up.');
            header('Location: ' . url_for('checkout'));
            exit;
        }

        try {
            db()->beginTransaction();
            $orderNum = generateOrderNumber();

            // ── Validate stock for tier items before processing ──
            foreach ($cart['items'] as $item) {
                $slug = $item['slug'] ?? '';
                $qty = (int)($item['quantity'] ?? 0);
                $tierId = $item['tier_id'] ?? '';
                if ($slug && $qty > 0 && $tierId) {
                    $stockCol = match ($tierId) { '3kg' => 'tier_3kg_stock', '6kg' => 'tier_6kg_stock', '10kg' => 'tier_10kg_stock', default => null };
                    if ($stockCol) {
                        $avail = (int)dbGetValue("SELECT $stockCol FROM product WHERE slug = ?", [$slug]);
                        if ($avail < $qty) {
                            throw new Exception("Not enough $tierId stock for " . e($item['product']['name']) . ". Available: $avail, requested: $qty.");
                        }
                    }
                }
            }

            // Build items JSON
            $itemsJson = json_encode(array_map(fn($item) => [
                'product_id' => $item['product']['id'],
                'product_name' => $item['product']['name'],
                'image_url' => $item['product']['image_url'] ?? $item['product']['image'] ?? '',
                'slug' => $item['slug'],
                'qty' => $item['quantity'],
                'tier_id' => $item['tier_id'] ?? '',
                'tier_label' => $item['tier_label'] ?? '',
                'unit_price' => (float)$item['unit_price'],
                'subtotal' => (float)$item['subtotal'],
            ], $cart['items']));
            // Create order
            $stmt = db()->prepare(
                "INSERT INTO market_order (order_number, user_id, wallet_id, status, total_amount, shipping_address, phone, notes, items, paid_at, created_at, updated_at)
                 VALUES (?, ?, 0, 'confirmed', ?, ?, ?, ?, ?, NOW(), NOW(), NOW())"
            );
            $stmt->execute([$orderNum, $u['id'], $total, $address, $phone, $notes, $itemsJson]);
            $orderId = db()->lastInsertId();

            // Deduct wallet
            $after = $balance - $total;
            dbExecute("UPDATE market_user SET balance = ?, updated_at = NOW() WHERE id = ?", [$after, $u['id']]);

            // Record transaction
            dbExecute(
                "INSERT INTO market_wallet_txn (user_id, transaction_type, amount, balance_before, balance_after, description, status, created_at)
                 VALUES (?, 'payment', ?, ?, ?, ?, 'completed', NOW())",
                [$u['id'], $total, $balance, $after, 'Payment for order #' . $orderNum]
            );

            // ── Deduct stock for tier items ──
            $orderItems = json_decode($itemsJson, true);
            foreach ($orderItems as $item) {
                $slug = $item['slug'] ?? '';
                $qty = (int)($item['qty'] ?? 0);
                $tierId = $item['tier_id'] ?? '';
                if ($slug && $qty > 0 && $tierId) {
                    $stockCol = match ($tierId) { '3kg' => 'tier_3kg_stock', '6kg' => 'tier_6kg_stock', '10kg' => 'tier_10kg_stock', default => null };
                    if ($stockCol) {
                        dbExecute("UPDATE product SET $stockCol = GREATEST($stockCol - ?, 0) WHERE slug = ?", [$qty, $slug]);
                    }
                }
            }

            db()->commit();
            save_cart([]);
            add_flash('success', 'Order placed successfully! Order #' . $orderNum);
            header('Location: ' . url_for('orders'));
        } catch (Exception $e) {
            db()->rollBack();
            add_flash('error', 'Checkout failed: ' . $e->getMessage());
            header('Location: ' . url_for('checkout'));
        }
        exit;
    }

    // ── Top-up ──
    if ($action === 'topup') {
        $u = loggedInUser();
        if (!$u) {
            add_flash('error', 'Please login first.');
            header('Location: ' . url_for('login'));
            exit;
        }
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            add_flash('error', 'Invalid amount.');
            header('Location: ' . url_for('profile'));
            exit;
        }
        $before = (float)($u['balance'] ?? 0);
        $after = $before + $amount;
        dbExecute("UPDATE market_user SET balance = ?, updated_at = NOW() WHERE id = ?", [$after, $u['id']]);
        dbExecute(
            "INSERT INTO market_wallet_txn (user_id, transaction_type, amount, balance_before, balance_after, description, status, created_at)
             VALUES (?, 'topup', ?, ?, ?, 'Wallet top-up', 'completed', NOW())",
            [$u['id'], $amount, $before, $after]
        );
        add_flash('success', 'Top-up successful! RM ' . number_format($amount, 2) . ' added.');
        header('Location: ' . url_for('profile'));
        exit;
    }

    // ── Update profile ──
    if ($action === 'profile_save') {
        $u = loggedInUser();
        if (!$u) {
            add_flash('error', 'Please login first.');
            header('Location: ' . url_for('login'));
            exit;
        }
        $fields = [];
        $params = [];
        foreach (['full_name' => 'full_name', 'phone' => 'phone', 'address_line1' => 'address'] as $col => $form) {
            $val = trim($_POST[$form] ?? '');
            if ($val !== '') {
                $fields[] = "$col = ?";
                $params[] = $val;
            }
        }
        if (!empty($fields)) {
            $params[] = $u['id'];
            dbExecute("UPDATE market_user SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?", $params);
        }
        add_flash('success', 'Profile updated.');
        header('Location: ' . url_for('profile'));
        exit;
    }
}

// ── Password helpers ──

function hashPassword(string $plain): string {
    return password_hash($plain, PASSWORD_BCRYPT);
}

function verifyPassword(string $plain, string $hash): bool {
    // Supports both bcrypt and legacy pbkdf2_sha256 (Django)
    if (password_verify($plain, $hash)) return true;
    if (str_starts_with($hash, 'pbkdf2_sha256$')) {
        return verifyPbkdf2($plain, $hash);
    }
    return false;
}

function verifyPbkdf2(string $plain, string $hash): bool {
    $parts = explode('$', $hash);
    if (count($parts) < 5) return false;
    $iterations = (int)$parts[1];
    $salt = $parts[2];
    $expected = $parts[3];
    $computed = hash_pbkdf2('sha256', $plain, $salt, $iterations, 32);
    return hash_equals($expected, $computed);
}

handle_cart_actions();
