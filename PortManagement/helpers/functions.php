<?php
$sessPath = __DIR__ . '/../tmp';
if (!is_dir($sessPath)) @mkdir($sessPath, 0777, true);
session_save_path($sessPath);
// Persist session for 7 days — don't log out until manual logout
ini_set('session.gc_maxlifetime', 604800);
ini_set('session.cookie_lifetime', 604800);
session_start();

/* ── Database helpers ── */

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=fishery_db;charset=utf8mb4", 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function dbGetAll($sql, $params = []) {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dbGetRow($sql, $params = []) {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function dbPrepare($sql) {
    return getDB()->prepare($sql);
}

function dbGetValue($sql, $params = []) {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() ?: null;
}

function dbExecute($sql, $params = []) {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/* ── Auth functions ── */

function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $tmp = __DIR__ . '/../tmp';
        if (!is_dir($tmp)) @mkdir($tmp, 0777, true);
        session_save_path($tmp);
        ini_set('session.gc_maxlifetime', 604800);
        ini_set('session.cookie_lifetime', 604800);
        session_start();
    }
}

function dbLastInsertId(): string {
    return getDB()->lastInsertId();
}

/**
 * Get the PortManagement base URL path (e.g. /FisherySystem/PortManagement)
 */
function baseUrl(): string {
    $appRoot = str_replace('\\', '/', dirname(__DIR__));  // .../PortManagement/helpers -> .../PortManagement
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    return rtrim(str_replace($docRoot, '', $appRoot), '/');
}

/**
 * Redirect to a relative path within PortManagement (e.g. redirect('/dashboard/stock/'))
 */
function redirect(string $path): void {
    header('Location: ' . baseUrl() . '/' . ltrim($path, '/'));
    exit;
}

function requireLogin() {
    initSession();
    if (!isset($_SESSION['user_id'])) {
        $base = baseUrl();
        header('Location: ' . $base . '/login/');
        exit;
    }
    $user = dbGetRow(
        "SELECT pu.id, pu.username, pu.email, pu.role, pu.port_id, pu.port_name as port, pu.is_active
         FROM port_user pu
         WHERE pu.id = ?",
        [$_SESSION['user_id']]
    );
    if (!$user || !$user['is_active']) {
        unset($_SESSION['user_id']);
        session_destroy();
        header('Location: ' . baseUrl() . '/login/');
        exit;
    }
    return $user;
}

function authLogin($email, $password) {
    $user = dbGetRow(
        "SELECT id, username, email, password_hash, role, port_id
         FROM port_user WHERE LOWER(email) = LOWER(?) AND is_active = TRUE",
        [$email]
    );
    if (!$user || !verifyPassword($password, $user['password_hash'])) return false;
    initSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    dbExecute("UPDATE port_user SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
    return true;
}

function authLogout() {
    initSession();
    $_SESSION = [];
    setcookie(session_name(), '', time() - 3600, '/');
    session_destroy();
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    header('Location: ' . $base . '/login/');
    exit;
}

function isLoggedIn() {
    initSession();
    return !empty($_SESSION['user_id']);
}

function authenticateUser($username, $password) {
    $user = dbGetRow(
        "SELECT id, username, email, password_hash
         FROM port_user
         WHERE (username = ? OR LOWER(email) = LOWER(?)) AND is_active = TRUE",
        [$username, $username]
    );
    if (!$user || !verifyPassword($password, $user['password_hash'])) return false;
    return $user;
}

function loginUser($userId, $remember = false) {
    initSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    if ($remember) {
        setcookie('remember_user', $userId, time() + 86400 * 30, '/');
    }
}

/* ── Password helpers (supports bcrypt + legacy Django pbkdf2) ── */

function verifyPassword(string $plain, string $hash): bool {
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

/* ── Role helpers ── */

function getRoleDisplay($user) {
    $raw = $user['role'] ?? 'viewer';
    $port = $user['port'] ?? null;
    if (in_array($raw, ['super_admin', 'superadmin'])) return ['Super Admin', 'super_admin', $port];
    if (in_array($raw, ['port_admin', 'admin'])) return ['Port Admin', 'port_admin', $port];
    return ['User', 'user', $port];
}

function getPortsForDisplay() {
    $r = [];
    try {
        $ports = dbGetAll("SELECT DISTINCT port_code as `key`, port_name as name FROM port_user WHERE port_code IS NOT NULL ORDER BY port_code");
        foreach ($ports as $p) {
            $r[] = ['key' => $p['key'], 'name' => $p['name'], 'lat' => 0, 'lon' => 0];
        }
    } catch (Exception $e) {}
    return $r;
}

/* ── General helpers ── */

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function imgUrl($path) {
    if (empty($path)) return '';
    // Ensure path always starts from web root
    $clean = ltrim(str_replace('\\', '/', $path), '/');
    return '/' . $clean;
}

function getProductTiers($productId) {
    try {
        $p = dbGetRow("SELECT tier_3kg_price, tier_3kg_stock, tier_6kg_price, tier_6kg_stock, tier_10kg_price, tier_10kg_stock FROM product WHERE id = ?", [$productId]);
        if (!$p) return [];
        $tiers = [];
        if ($p['tier_3kg_price'] !== null) $tiers[] = ['label' => '3kg', 'weight_kg' => 3.0, 'price' => (float)$p['tier_3kg_price'], 'stock_quantity' => (int)$p['tier_3kg_stock'], 'sort_order' => 1];
        if ($p['tier_6kg_price'] !== null) $tiers[] = ['label' => '6kg', 'weight_kg' => 6.0, 'price' => (float)$p['tier_6kg_price'], 'stock_quantity' => (int)$p['tier_6kg_stock'], 'sort_order' => 2];
        if ($p['tier_10kg_price'] !== null) $tiers[] = ['label' => '10kg', 'weight_kg' => 10.0, 'price' => (float)$p['tier_10kg_price'], 'stock_quantity' => (int)$p['tier_10kg_stock'], 'sort_order' => 3];
        return $tiers;
    } catch (Exception $e) {
        return [];
    }
}

function renderTierBadge($tiers) {
    if (empty($tiers)) return '<span style="color:var(--muted);font-size:11px;">No tiers</span>';
    $html = '<div style="display:flex;gap:3px;flex-wrap:wrap;">';
    foreach ($tiers as $t) {
        $stock = (int)($t['stock_quantity'] ?? 0);
        $html .= '<span style="font-size:10px;padding:2px 6px;border-radius:4px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.1);color:var(--muted);">'
               . e($t['label']) . ' · RM' . number_format((float)$t['price'], 0) . ' · ' . $stock . ' Unit'
               . '</span>';
    }
    $html .= '</div>';
    return $html;
}

function generateOrderNumber($date = null) {
    $d = $date ? new DateTime($date) : new DateTime();
    $prefix = 'FM' . $d->format('ym');
    $monthStart = $d->format('Y-m-01');
    $monthEnd = $d->format('Y-m-t 23:59:59');
    try {
        $count = (int)dbGetValue(
            "SELECT COUNT(*) FROM market_order WHERE created_at >= ? AND created_at <= ?",
            [$monthStart, $monthEnd]
        );
    } catch (Exception $e) { $count = 0; }
    return $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function generateIntlOrderNumber($date = null) {
    $d = $date ? new DateTime($date) : new DateTime();
    $prefix = 'IM' . $d->format('ym');
    $monthStart = $d->format('Y-m-01');
    $monthEnd = $d->format('Y-m-t 23:59:59');
    try {
        $count = (int)dbGetValue(
            "SELECT COUNT(*) FROM export_orders WHERE created_at >= ? AND created_at <= ?",
            [$monthStart, $monthEnd]
        );
    } catch (Exception $e) { $count = 0; }
    return $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function generateShipmentNumber($type = 'domestic', $date = null) {
    $d = $date ? new DateTime($date) : new DateTime();
    $prefix = ($type === 'export' ? 'ISN' : 'SM') . $d->format('ym');
    $table = ($type === 'export' ? 'export_shipment' : 'market_shipments');
    $monthStart = $d->format('Y-m-01');
    $monthEnd = $d->format('Y-m-t 23:59:59');
    try {
        $count = (int)dbGetValue(
            "SELECT COUNT(*) FROM $table WHERE created_at >= ? AND created_at <= ?",
            [$monthStart, $monthEnd]
        );
    } catch (Exception $e) { $count = 0; }
    return $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function getNearestWarehouse(string $address): int {
    // Simple port matching based on address keywords
    $address = strtolower($address);
    $portMap = [
        'klang' => 1, 'selangor' => 1,
        'penang' => 2, 'pulau pinang' => 2,
        'johor' => 3, 'jb' => 3, 'pasir gudang' => 3,
        'kuantan' => 4, 'pahang' => 4,
    ];
    foreach ($portMap as $keyword => $portId) {
        if (strpos($address, $keyword) !== false) return $portId;
    }
    return 1; // Default to Port Klang
}

function getSettingDecimal($key, $default = 0) {
    $v = dbGetValue("SELECT stat_value FROM dashboard_stat WHERE stat_key = ? ORDER BY computed_at DESC LIMIT 1", [$key]);
    return $v !== null ? (float)$v : $default;
}

function setSettingDecimal($key, $value, $label, $source = 'port_management') {
    $id = dbGetValue("SELECT id FROM dashboard_stat WHERE stat_key = ? ORDER BY computed_at DESC LIMIT 1", [$key]);
    if ($id) dbExecute("UPDATE dashboard_stat SET stat_value=?, stat_label=?, source=?, computed_at=NOW() WHERE id=?", [$value, $label, $source, $id]);
    else dbExecute("INSERT INTO dashboard_stat (stat_key,stat_value,stat_label,source,period_start,period_end,metadata,computed_at) VALUES (?,?,?,?,CURRENT_DATE,CURRENT_DATE,'{}',NOW())", [$key, $value, $label, $source]);
}

function getMalaysiaNow() { return new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur')); }

/**
 * Get real LAN IP — skips VirtualBox/VMware virtual adapters.
 * Use for QR code URLs that phones on the same network need to reach.
 */
function lan_ip(): string {
    $all = gethostbynamel(gethostname());
    if (!$all) return 'localhost';
    $skipPatterns = ['/^127\./', '/^169\.254\./', '/^192\.168\.56\./', '/^192\.168\.99\./', '/^10\./'];
    foreach ($all as $ip) {
        $skip = false;
        foreach ($skipPatterns as $p) { if (preg_match($p, $ip)) { $skip = true; break; } }
        if (!$skip && $ip !== '::1') return $ip;
    }
    // Fallback: first non-loopback
    foreach ($all as $ip) { if ($ip !== '127.0.0.1' && $ip !== '::1') return $ip; }
    return 'localhost';
}
