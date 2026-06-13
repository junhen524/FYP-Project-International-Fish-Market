<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function adm_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=fishery_db;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function adm_q(string $sql, array $p = []): array {
    $s = adm_db()->prepare($sql); $s->execute($p); return $s->fetchAll();
}
function adm_v(string $sql, array $p = []): mixed {
    $s = adm_db()->prepare($sql); $s->execute($p); return $s->fetchColumn();
}

if (session_status() === PHP_SESSION_NONE) {
    @session_save_path(sys_get_temp_dir());
    session_name('IFM_ADMIN');
    @session_start();
}

$adminUser = $_SESSION['ifm_admin_user'] ?? null;

// ── Nav compat stubs (admin uses separate session, no cart) ──
function intl_cart_count(): int { return 0; }
function intl_current_user(): ?array { return null; }
function dbGetValue(string $sql, array $params = []): mixed {
    try { return adm_v($sql, $params); } catch (Exception $e) { return 0; }
}
function url_for(string $page): string {
    global $__ifmBasePath;
    $map = ['shop'=>'shop.php','recipes'=>'recipes.php','about'=>'about.php','cart'=>'cart.php','login'=>'login.php','logout'=>'logout.php','profile'=>'profile.php','orders'=>'orders.php','favorites'=>'favorites.php','wallet'=>'wallet.php'];
    return $__ifmBasePath . ($map[$page] ?? $page . '.php');
}
$error = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['ifm_admin_user']);
    session_destroy();
    // ── Clear admin marker from public session ──
    @session_write_close();
    @session_save_path(sys_get_temp_dir());
    @session_name('PHPSESSID');
    @session_start();
    unset($_SESSION['ifm_admin_marker']);
    @session_write_close();
    @session_save_path(sys_get_temp_dir());
    @session_name('IFM_ADMIN');
    @session_start();
    header('Location: ' . ($__ifmBasePath ?: '/') . 'admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $user = adm_q("SELECT * FROM export_user WHERE (username = ? OR email = ?) AND is_active = 1 AND role = 'admin'", [$username, $username]);
    if ($user && password_verify($password, $user[0]['password_hash'])) {
        $_SESSION['ifm_admin_user'] = $user[0];
        $adminUser = $user[0];
        // ── Write admin marker into public session too (avoids session switching in nav) ──
        @session_write_close();
        @session_save_path(sys_get_temp_dir());
        @session_name('PHPSESSID');
        @session_start();
        $_SESSION['ifm_admin_marker'] = ['name' => $adminUser['full_name'] ?? $adminUser['username'] ?? 'Admin'];
        @session_write_close();
        // Restore admin session
        @session_save_path(sys_get_temp_dir());
        @session_name('IFM_ADMIN');
        @session_start();
    } else {
        $error = 'Invalid credentials.';
    }
}

$porData = []; $totalOrders = 0; $totalPending = 0; $totalShipped = 0; $totalCompleted = 0; $totalRevenue = 0; $totalUsers = 0; $newUsers = 0; $prodStats = []; $prodCategories = []; $recentProducts = []; $domesticOrders = []; $domesticDrivers = [];
if ($adminUser) {
    try {
        $porData = adm_q("SELECT p.id, p.name as port_name, p.location as country FROM ports p ORDER BY p.name");
        foreach ($porData as &$p) {
            $p['cold_rooms'] = 1;
            $p['outgoing_loads'] = (int)adm_v("SELECT COUNT(*) FROM market_shipments s WHERE s.port_id = ? AND s.status NOT IN ('delivered','cancelled')", [$p['id']]);
            $p['stockLevel'] = 60;
            $p['status'] = 'Healthy';
            $p['id'] = (string)$p['id'];
        }
        unset($p);
    } catch (Exception $e) { error_log('port error: '.$e->getMessage()); }
    try {
        $totalOrders = (int)adm_v("SELECT COUNT(*) FROM export_orders");
        $totalPending = (int)adm_v("SELECT COUNT(*) FROM export_orders WHERE stage IN ('confirmed','processing')");
        $totalCompleted = (int)adm_v("SELECT COUNT(*) FROM export_orders WHERE stage = 'delivered'");
        $totalRevenue = (float)adm_v("SELECT COALESCE(SUM(total_amount),0) FROM export_orders WHERE stage NOT IN ('cancelled')");
        $totalUsers = (int)adm_v("SELECT COUNT(*) FROM (SELECT id FROM export_user WHERE is_active = 1 UNION ALL SELECT id FROM export_restaurant_user WHERE is_active = 1) u");
        $newUsers = (int)adm_v("SELECT COUNT(*) FROM (SELECT created_at FROM export_user WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) UNION ALL SELECT created_at FROM export_restaurant_user WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) u");
    } catch (Exception $e) {}
    try {
        $totalProducts = (int)adm_v("SELECT COUNT(*) FROM product WHERE is_active = 1");
        $lowStock = (int)adm_v("SELECT COUNT(*) FROM product WHERE is_active = 1 AND (tier_3kg_stock + tier_6kg_stock + tier_10kg_stock) < 10");
        $outOfStock = (int)adm_v("SELECT COUNT(*) FROM product WHERE is_active = 1 AND (tier_3kg_stock + tier_6kg_stock + tier_10kg_stock) = 0");
        $prodCategories = adm_q("SELECT category, COUNT(*) as cnt FROM product WHERE category IS NOT NULL AND category != '' AND is_active = 1 GROUP BY category ORDER BY cnt DESC");
        $recentProducts = adm_q("SELECT id, name, category, export_price, tier_3kg_price, tier_6kg_price, tier_10kg_price, tier_3kg_stock, tier_6kg_stock, tier_10kg_stock FROM product WHERE is_active = 1 ORDER BY category, id DESC");
        $exportOrders = adm_q("SELECT o.*, 
            COALESCE(u.full_name, u.username, ru.company_name, '—') as customer_name,
            u.full_name, u.username, u.email, u.phone,
            ru.company_name as restaurant_name,
            es.shipment_number, es.status as ship_status, es.driver_id
            FROM export_orders o
            LEFT JOIN export_user u ON u.id = o.user_id
            LEFT JOIN export_restaurant_user ru ON ru.id = o.restaurant_id
            LEFT JOIN export_shipment es ON es.order_id = o.id
            ORDER BY o.created_at DESC LIMIT 50");
        $intlDrivers = adm_q("SELECT id, name, vehicle_no, phone FROM export_driver WHERE is_active = TRUE");
    } catch (Exception $e) { error_log('product query error: '.$e->getMessage()); }
    // ── Pending Recipes ──
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $recipeId = (int)($_POST['recipe_id'] ?? 0);
            if ($_POST['action'] === 'approve_recipe' && $recipeId > 0) {
                adm_db()->prepare("UPDATE export_recipes SET is_active = 1, status = 'approved' WHERE id = ?")->execute([$recipeId]);
                header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'admin.php'));
                exit;
            } elseif ($_POST['action'] === 'reject_recipe' && $recipeId > 0) {
                adm_db()->prepare("UPDATE export_recipes SET is_active = 0, status = 'rejected' WHERE id = ?")->execute([$recipeId]);
                header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'admin.php'));
                exit;
            }
        }
        $pendingRecipes = adm_q("SELECT r.*, ru.company_name FROM export_recipes r LEFT JOIN export_restaurant_user ru ON ru.id = r.restaurant_id WHERE r.status = 'pending' ORDER BY r.created_at DESC");
        // Fetch items for each pending recipe
        $pendingRecipeItems = [];
        $pendingRecipesJson = [];
        foreach ($pendingRecipes as $pr) {
            $items = adm_q("SELECT type, content, sort_order, product_id FROM export_recipe_items WHERE recipe_id = ? ORDER BY sort_order", [$pr['id']]);
            $pendingRecipeItems[$pr['id']] = $items;
            $ings = []; $stps = [];
            foreach ($items as $it) {
                if ($it['type'] === 'ingredient') $ings[] = $it['content'];
                else $stps[] = $it['content'];
            }
            $pendingRecipesJson[] = [
                'img' => (function() use ($pr, $__ifmBasePath) {
                    $u = $pr['image_url'];
                    if ($u !== '' && $u[0] !== '/' && !preg_match('#^https?://#i', $u)) {
                        return rtrim($__ifmBasePath, '/') . '/' . $u;
                    }
                    return $u;
                })(),
                'title' => $pr['title'],
                'sub' => $pr['subtitle'] ?? '',
                'desc' => $pr['description'] ?? '',
                'level' => $pr['level'],
                'time' => (int)$pr['time_minutes'],
                'ingredients' => $ings,
                'steps' => $stps,
            ];
        }
    } catch (Exception $e) { $pendingRecipes = []; $pendingRecipeItems = []; $pendingRecipesJson = []; error_log('pending recipes error: '.$e->getMessage()); }
}
?><!doctype html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/><title>International Fish Market Admin</title><?php if ($adminUser): ?><link rel="stylesheet" href="css/admin.css?v=<?= urlencode($__ifmAssetVersion('css/admin.css')) ?>"/><?php else: ?><link rel="stylesheet" href="css/adminlogin.css?v=<?= urlencode($__ifmAssetVersion('css/adminlogin.css')) ?>"/><?php endif; ?></head><body style="margin:0;overflow-x:hidden"><div id="root"><div class="relative min-h-screen bg-bg-dark text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip" id="alche-studio-replica-root">
<header id="main-app-header" class="fixed top-0 left-0 w-full z-50 bg-white border-b border-stone-200/80 py-5">
<div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center">
<div class="cursor-pointer flex items-center space-x-2 group" id="brand-logo-trigger"><span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">INTERNATIONAL FISH MARKET</span><span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse"></span></div>
<?php if ($adminUser): ?>
<nav class="hidden md:flex items-center space-x-6"><?php require __DIR__ . '/includes/user_nav.php'; ?></nav>
<?php else: ?>
<nav class="hidden md:flex items-center space-x-6"><a href="<?= $__ifmBasePath ?>shop.php" class="flex items-center space-x-1.5 font-display text-xs tracking-widest uppercase transition-all duration-200 text-slate-600 hover:text-slate-950 active:scale-95 cursor-pointer no-underline"><span>← Back to Market</span></a></nav>
<?php endif; ?>
</div></header>

<div id="subpage-viewport"><?php if (!$adminUser): ?><div class="min-h-screen bg-slate-100/60 pt-28 pb-16 px-4 md:px-8 flex items-center justify-center"><div class="w-full max-w-md">
<div class="bg-white border border-slate-200 rounded-[28px] shadow-xl overflow-hidden">
<div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex items-center gap-3">
<div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center">
<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path></svg></div>
<div><h1 class="font-display font-black text-lg text-slate-900 uppercase tracking-tight">Admin Login</h1><p class="text-[10px] font-mono uppercase tracking-widest text-slate-400">Hidden operations entry</p></div></div>
<form method="post" class="p-6 space-y-4">
<?php if ($error): ?><div class="rounded-2xl bg-red-50 border border-red-100 px-4 py-3 text-[11px] text-red-700">❌ <?= e($error) ?></div><?php endif; ?>
<div class="space-y-1"><label class="font-mono text-[8px] uppercase text-slate-400 font-bold block">Admin Username</label><input type="text" name="username" value="admin" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-xs font-mono text-slate-900 focus:outline-none"></div>
<div class="space-y-1"><label class="font-mono text-[8px] uppercase text-slate-400 font-bold block">Admin Password</label><input type="password" name="password" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-xs font-mono text-slate-900 focus:outline-none"></div>
<button type="submit" name="action" value="login" class="w-full py-3 bg-slate-900 hover:bg-slate-950 text-white font-mono text-[10px] uppercase tracking-widest font-black rounded-2xl transition-all cursor-pointer flex items-center justify-center gap-2">
<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="16" r="1"></circle><rect x="3" y="10" width="18" height="12" rx="2"></rect><path d="M7 10V7a5 5 0 0 1 10 0v3"></path></svg><span>Enter Admin Dashboard</span></button></form></div></div></div>

<?php else: ?><div class="bg-slate-100/60 min-h-screen pt-28 pb-16 px-4 md:px-8">
<div class="max-w-7xl mx-auto space-y-6">

<!-- Stats Row (clickable) -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 cursor-pointer hover:shadow-md hover:border-brand-blue transition-all" onclick="filterOrders('all')">
    <p class="font-mono text-[8px] uppercase tracking-widest text-slate-400 font-bold">Total Orders</p>
    <p class="font-display font-black text-2xl text-slate-900 mt-1"><?= number_format($totalOrders) ?></p>
    <p class="text-[10px] text-slate-500 mt-0.5">All time</p>
  </div>
  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 cursor-pointer hover:shadow-md hover:border-amber-400 transition-all" onclick="filterOrders('pending')">
    <p class="font-mono text-[8px] uppercase tracking-widest text-slate-400 font-bold">Pending</p>
    <p class="font-display font-black text-2xl text-amber-600 mt-1"><?= number_format($totalPending) ?></p>
  </div>
  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 cursor-pointer hover:shadow-md hover:border-emerald-400 transition-all" onclick="filterOrders('completed')">
    <p class="font-mono text-[8px] uppercase tracking-widest text-slate-400 font-bold">Completed</p>
    <p class="font-display font-black text-2xl text-emerald-600 mt-1"><?= number_format($totalCompleted) ?></p>
  </div>
  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 cursor-pointer hover:shadow-md hover:border-cyan-400 transition-all" onclick="filterOrders('revenue')">
    <p class="font-mono text-[8px] uppercase tracking-widest text-slate-400 font-bold">Revenue</p>
    <p class="font-display font-black text-2xl text-cyan-700 mt-1">$<?= number_format($totalRevenue, 0) ?></p>
  </div>
  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 cursor-pointer hover:shadow-md hover:border-slate-400 transition-all" onclick="filterOrders('users')">
    <p class="font-mono text-[8px] uppercase tracking-widest text-slate-400 font-bold">Users</p>
    <p class="font-display font-black text-2xl text-slate-900 mt-1"><?= number_format($totalUsers) ?></p>
    <p class="text-[10px] text-slate-500 mt-0.5">+<?= number_format($newUsers) ?> this month</p>
  </div>
</div>

<!-- Pending Recipes (from restaurants) -->
<script>
const pendingRecipesJson = <?= json_encode($pendingRecipesJson) ?>;

function filterOrders(type) {
  if (type === 'users') {
    var us = document.getElementById('users-section');
    if (us) us.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return;
  }
  if (type === 'revenue') {
    var rs = document.getElementById('revenue-section');
    if (rs) rs.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return;
  }
  var section = document.getElementById('export-orders-section');
  if (!section) return;
  var rows = section.querySelectorAll('tbody tr');
  rows.forEach(function(row) {
    var stage = row.getAttribute('data-stage');
    if (type === 'all') { row.style.display = ''; return; }
    if (type === 'pending') {
      row.style.display = (stage === 'confirmed' || stage === 'processing' || stage === 'deposit_paid' || stage === 'quote') ? '' : 'none';
      return;
    }
    if (type === 'completed') {
      row.style.display = (stage === 'delivered' || stage === 'completed') ? '' : 'none';
      return;
    }
    row.style.display = 'none';
  });
  var count = 0;
  rows.forEach(function(r) { if (r.style.display !== 'none') count++; });
  var label = type === 'all' ? 'All' : type.charAt(0).toUpperCase() + type.slice(1);
  var hdr = section.querySelector('.orders-count');
  if (hdr) hdr.textContent = count + ' orders \u2014 ' + label.toLowerCase();
  section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

document.addEventListener('DOMContentLoaded', function() {
  // Generate modal HTML once
  var modal = document.createElement('div');
  modal.id = 'pr-modal';
  modal.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);overflow-y:auto;padding:80px 20px';
  modal.onclick = function(e) { if (e.target === this) closePendingRecipe(); };
  modal.innerHTML = '<div style="max-width:640px;margin:0 auto;background:#fafaf9;border-radius:24px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.08)">' +
    '<div style="width:100%;aspect-ratio:16/9;overflow:hidden;background:#e7e5e4"><img id="pr-modal-img" src="" alt="" style="width:100%;height:100%;object-fit:cover;display:block" referrerpolicy="no-referrer"></div>' +
    '<div style="padding:28px">' +
    '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">' +
    '<div><span id="pr-modal-level" style="font-family:monospace;font-size:10px;background:#1c1917;color:#fafaf9;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;font-weight:700"></span>' +
    '<h2 id="pr-modal-title" style="font-family:\'Georgia\',serif;font-weight:900;font-size:22px;color:#1c1917;margin-top:12px;margin-bottom:4px"></h2>' +
    '<p id="pr-modal-sub" style="font-family:monospace;font-size:10px;color:#78716c;text-transform:uppercase;letter-spacing:1px;font-weight:600"></p></div>' +
    '<button onclick="closePendingRecipe()" style="background:none;border:none;cursor:pointer;font-size:24px;color:#78716c;padding:4px 8px">&times;</button></div>' +
    '<p id="pr-modal-desc" style="font-size:13px;color:#57534e;line-height:1.7;margin-bottom:20px"></p>' +
    '<div style="border:1px solid #e7e5e4;border-radius:16px;padding:20px;margin-bottom:20px;background:white"><h4 style="font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#78716c;margin-bottom:12px;font-weight:700">🥘 Ingredients</h4><ul id="pr-modal-ingredients" style="list-style:none;padding:0;font-size:13px;color:#44403c"></ul></div>' +
    '<div style="border:1px solid #e7e5e4;border-radius:16px;padding:20px;background:white"><h4 style="font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#78716c;margin-bottom:12px;font-weight:700">👨‍🍳 Instructions</h4><ol id="pr-modal-steps" style="padding-left:20px;font-size:13px;color:#44403c;line-height:1.8"></ol></div>' +
    '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding-top:16px;border-top:1px solid #e7e5e4">' +
    '<span id="pr-modal-time" style="font-family:monospace;font-size:11px;color:#a8a29e;font-weight:600">🕐 </span>' +
    '<button onclick="closePendingRecipe()" style="padding:8px 24px;background:#1c1917;color:#fafaf9;border:none;border-radius:12px;font-size:11px;font-weight:700;cursor:pointer">Close</button></div></div></div>';
  document.body.appendChild(modal);

  // Bind click on all pending recipe cards
  document.querySelectorAll('[data-pr-click]').forEach(function(el) {
    el.addEventListener('click', function() {
      var i = parseInt(this.getAttribute('data-pr-click'), 10);
      var r = pendingRecipesJson[i];
      if (!r) return;

      var img = document.getElementById('pr-modal-img');
      if (!img) { console.error('pr-modal not in DOM'); return; }
      img.src = r.img || '';
      document.getElementById('pr-modal-title').textContent = r.title;
      document.getElementById('pr-modal-sub').textContent = r.sub;
      document.getElementById('pr-modal-desc').textContent = r.desc;
      document.getElementById('pr-modal-level').textContent = r.level;
      document.getElementById('pr-modal-time').textContent = '🕐 ' + r.time + ' mins';

      var ing = document.getElementById('pr-modal-ingredients');
      ing.innerHTML = '';
      (r.ingredients || []).forEach(function(item) {
        var li = document.createElement('li');
        li.style.cssText = 'padding:6px 0;border-bottom:1px solid #f5f5f4;display:flex;align-items:center;gap:8px;justify-content:space-between';
        li.textContent = item;
        ing.appendChild(li);
      });

      var st = document.getElementById('pr-modal-steps');
      st.innerHTML = '';
      (r.steps || []).forEach(function(item) {
        var li = document.createElement('li');
        li.style.cssText = 'margin-bottom:10px;line-height:1.6';
        li.textContent = item;
        st.appendChild(li);
      });

      document.getElementById('pr-modal').style.display = 'block';
      document.body.style.overflow = 'hidden';
    });
  });
});

function closePendingRecipe() {
  var pm = document.getElementById('pr-modal');
  if (pm) pm.style.display = 'none';
  document.body.style.overflow = '';
}
</script>
<div class="bg-white border border-slate-200 rounded-[28px] shadow-sm overflow-hidden">
  <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex items-center gap-3">
    <div class="w-10 h-10 rounded-2xl bg-amber-600 text-white flex items-center justify-center">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
    </div>
    <div>
      <h2 class="font-display font-black text-lg text-slate-900 uppercase tracking-tight">🍳 Pending Recipes</h2>
      <p class="text-[10px] font-mono uppercase tracking-widest text-slate-400"><?= count($pendingRecipes) ?> recipe(s) awaiting approval from restaurants</p>
    </div>
  </div>
  <div class="p-6">
    <?php if ($pendingRecipes): ?>
    <div class="space-y-4">
      <?php $prIdx = 0; foreach ($pendingRecipes as $pr): ?>
      <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-4 transition-all duration-200 hover:shadow-md hover:border-slate-300">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1 cursor-pointer" data-pr-click="<?= $prIdx ?>">
            <div style="display:flex;gap:12px;align-items:flex-start">
              <?php
                $thumbUrl = $pr['image_url'];
                if ($thumbUrl !== '' && $thumbUrl[0] !== '/' && !preg_match('#^https?://#i', $thumbUrl)) {
                    $thumbUrl = rtrim($__ifmBasePath, '/') . '/' . $thumbUrl;
                }
              ?>
              <?php if ($thumbUrl): ?>
              <img src="<?= e($thumbUrl) ?>" alt="" style="width:56px;height:56px;border-radius:10px;object-fit:cover;flex-shrink:0;background:#e7e5e4" referrerpolicy="no-referrer">
              <?php endif; ?>
              <div>
                <h3 class="font-display font-bold text-slate-900 text-base"><?= e($pr['title']) ?></h3>
                <?php if ($pr['subtitle']): ?><p class="text-[10px] text-slate-400 font-mono uppercase tracking-widest"><?= e($pr['subtitle']) ?></p><?php endif; ?>
                <div class="flex items-center gap-2 mt-2">
                  <span class="font-mono text-[8px] bg-stone-200 text-stone-700 px-2 py-0.5 rounded-full font-bold uppercase"><?= e($pr['level']) ?></span>
                  <span class="font-mono text-[8px] text-slate-400">🕐 <?= (int)$pr['time_minutes'] ?> mins</span>
                </div>
                <p class="text-[11px] text-slate-500 mt-2 line-clamp-2"><?= e(mb_substr($pr['description'] ?? '', 0, 150)) ?></p>
                <p class="text-[9px] text-slate-400 font-mono mt-2">🏪 By: <?= e($pr['company_name'] ?? 'Restaurant') ?> · <?= e($pr['created_at']) ?></p>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <form method="post" style="display:inline-flex;gap:6px">
              <input type="hidden" name="recipe_id" value="<?= (int)$pr['id'] ?>">
              <button type="submit" name="action" value="approve_recipe" style="padding:8px 14px;background:#059669;color:white;border:none;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;cursor:pointer">✓ Approve</button>
              <button type="submit" name="action" value="reject_recipe" style="padding:8px 14px;background:#ef4444;color:white;border:none;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;cursor:pointer">✕ Reject</button>
            </form>
          </div>
        </div>
      </div>
      <?php $prIdx++; endforeach; ?>
    </div>
    <?php else: ?>
    <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-8 text-center">
      <p class="font-display font-bold text-slate-500 text-lg">No pending recipes</p>
      <p class="text-[11px] text-slate-400 mt-1">Recipes submitted by restaurants will appear here for approval.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Products Overview -->
<div class="bg-white border border-slate-200 rounded-[28px] shadow-sm overflow-hidden">
  <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex items-center gap-3">
    <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    </div>
    <div>
      <h2 class="font-display font-black text-lg text-slate-900 uppercase tracking-tight">Products Overview</h2>
      <p class="text-[10px] font-mono uppercase tracking-widest text-slate-400"><?= number_format($totalProducts) ?> active products across <?= count($prodCategories) ?> categories</p>
    </div>
  </div>
  <div class="p-6 space-y-6">
    <!-- Category breakdown stat cards (clickable filters) -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3" id="category-filters">
      <div class="rounded-2xl bg-slate-900 border-2 border-slate-900 p-3 text-center cursor-pointer hover:shadow-md transition-all category-filter active" data-category="all" onclick="filterProducts('all')">
        <p class="font-display font-black text-xl text-white"><?= number_format($totalProducts) ?></p>
        <p class="font-mono text-[8px] uppercase tracking-widest text-white/70 font-bold mt-1">All Products</p>
      </div>
      <?php foreach ($prodCategories as $cat): ?>
      <div class="rounded-2xl bg-slate-50 border border-slate-200 p-3 text-center cursor-pointer hover:shadow-md hover:border-slate-400 transition-all category-filter" data-category="<?= e($cat['category']) ?>" onclick="filterProducts('<?= e($cat['category']) ?>')">
        <p class="font-display font-black text-xl text-slate-900"><?= (int)$cat['cnt'] ?></p>
        <p class="font-mono text-[8px] uppercase tracking-widest text-slate-400 font-bold mt-1"><?= e(ucfirst($cat['category'])) ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Products table -->
    <?php if ($recentProducts): ?>
    <div class="overflow-x-auto">
      <table class="w-full text-[11px]">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/50">
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Product</th>
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">3kg / USD</th>
            <th class="text-right px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">3kg Stock</th>
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">6kg / USD</th>
            <th class="text-right px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">6kg Stock</th>
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">10kg / USD</th>
            <th class="text-right px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">10kg Stock</th>
          </tr>
        </thead>
        <tbody id="products-table-body">
          <?php
          function _stockBadge($s): string {
            if ($s <= 0) return '<span class="font-bold text-rose-600">0</span>';
            if ($s < 10) return '<span class="font-bold text-amber-600">'.$s.'</span>';
            return '<span class="font-bold text-emerald-600">'.$s.'</span>';
          }
          foreach ($recentProducts as $prod):
            $s3 = (int)($prod['tier_3kg_stock']??0);
            $s6 = (int)($prod['tier_6kg_stock']??0);
            $s10 = (int)($prod['tier_10kg_stock']??0);
          ?>
          <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors product-row" data-category="<?= e($prod['category']) ?>">
            <td class="px-4 py-3">
              <span class="font-bold text-slate-900"><?= e($prod['name']) ?></span>
              <span class="text-[9px] text-slate-400 ml-2 font-mono uppercase"><?= e($prod['category']) ?></span>
            </td>
            <td class="px-4 py-3 font-mono font-bold text-brand-blue">$<?= number_format((float)($prod['tier_3kg_price']??0), 2) ?></td>
            <td class="px-4 py-3 text-right font-mono"><?= _stockBadge($s3) ?> unit<?= $s3 !== 1 ? 's' : '' ?></td>
            <td class="px-4 py-3 font-mono font-bold text-brand-blue">$<?= number_format((float)($prod['tier_6kg_price']??0), 2) ?></td>
            <td class="px-4 py-3 text-right font-mono"><?= _stockBadge($s6) ?> unit<?= $s6 !== 1 ? 's' : '' ?></td>
            <td class="px-4 py-3 font-mono font-bold text-brand-blue">$<?= number_format((float)($prod['tier_10kg_price']??0), 2) ?></td>
            <td class="px-4 py-3 text-right font-mono"><?= _stockBadge($s10) ?> unit<?= $s10 !== 1 ? 's' : '' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="flex items-center justify-between">
      <p class="font-mono text-[9px] text-slate-400" id="products-count">Showing <?= count($recentProducts) ?> products</p>
      <div class="flex items-center gap-2">
        <?php if ($lowStock > 0): ?>
        <span class="font-mono text-[8px] bg-amber-50 text-amber-700 border border-amber-100 px-2 py-1 rounded-full font-bold">⚠️ <?= $lowStock ?> low stock</span>
        <?php endif; ?>
        <?php if ($outOfStock > 0): ?>
        <span class="font-mono text-[8px] bg-rose-50 text-rose-700 border border-rose-100 px-2 py-1 rounded-full font-bold">✕ <?= $outOfStock ?> out of stock</span>
        <?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-8 text-center">
      <p class="font-display font-bold text-slate-500 text-lg">No products found</p>
      <p class="text-[11px] text-slate-400 mt-1">Add products in the system to see them here.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Export Orders -->
<div id="export-orders-section" class="bg-white border border-slate-200 rounded-[28px] shadow-sm overflow-hidden">
  <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex items-center gap-3">
    <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    </div>
    <div>
      <h2 class="font-display font-black text-lg text-slate-900 uppercase tracking-tight">📦 Export Orders</h2>
      <p class="text-[10px] font-mono uppercase tracking-widest text-slate-400 orders-count"><?= count($exportOrders) ?> orders — All</p>
    </div>
  </div>
  <div class="p-6">
    <?php if ($exportOrders): ?>
    <div class="overflow-x-auto">
      <table class="w-full text-[11px]">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50/50">
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Order #</th>
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Shipment #</th>
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Customer</th>
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Amount</th>
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Stage</th>
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Destination</th>
            <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($exportOrders as $o):
            $driver = null;
            $did = $o['driver_id'] ?? null;
            if ($did) {
                foreach ($intlDrivers as $d) { if ($d['id'] == $did) { $driver = $d; break; } }
            }
          ?>
          <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors" data-stage="<?= e($o['stage']) ?>">
            <td class="px-4 py-3">
              <span class="font-bold text-slate-900 cursor-pointer hover:text-brand-blue hover:underline" onclick="openOrderModal(<?= (int)$o['id'] ?>)">
                #<?= e($o['order_number']) ?>
              </span>
            </td>
            <td class="px-4 py-3">
              <?php $sn = $o['shipment_number'] ?? null; ?>
              <?php if ($sn): ?>
              <span class="font-mono font-bold text-sky-700 text-[10px] bg-sky-50 border border-sky-100 px-2 py-0.5 rounded"><?= e($sn) ?></span>
              <div style="font-size:9px;color:<?= $driver ? '#475569' : '#94a3b8' ?>;margin-top:2px;"><?= $driver ? '🚛 '.e($driver['name']) : 'Not assigned' ?></div>
              <?php else: ?>
              <span class="text-slate-300">—</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 font-mono text-slate-600"><?= e($o['customer_name']) ?></td>
            <td class="px-4 py-3 font-mono font-bold">$<?= number_format((float)$o['total_amount'], 0) ?></td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-1 rounded-full font-mono text-[8px] uppercase tracking-widest font-bold border
                <?= match($o['stage']) { 'quote' => 'bg-slate-50 text-slate-600 border-slate-200', 'deposit_paid' => 'bg-blue-50 text-blue-700 border-blue-100', 'processing' => 'bg-amber-50 text-amber-700 border-amber-100', 'shipping' => 'bg-sky-50 text-sky-700 border-sky-100', 'customs' => 'bg-purple-50 text-purple-700 border-purple-100', 'delivered' => 'bg-emerald-50 text-emerald-700 border-emerald-100', 'cancelled' => 'bg-rose-50 text-rose-700 border-rose-100', default => 'bg-slate-50 text-slate-600 border-slate-200' } ?>">
                <?= e(ucfirst(str_replace('_', ' ', $o['stage']))) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-slate-500"><?= e($o['destination_country'] ?: '—') ?></td>
            <td class="px-4 py-3 text-slate-400 font-mono text-[10px]"><?= e(date('Y-m-d', strtotime($o['created_at'] ?? ''))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-8 text-center">
      <p class="font-display font-bold text-slate-500 text-lg">No export orders found</p>
      <p class="text-[11px] text-slate-400 mt-1">Orders will appear here once customers place them.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Revenue Section -->
<div id="revenue-section" class="bg-white border border-slate-200 rounded-[28px] shadow-sm overflow-hidden">
  <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex items-center gap-3">
    <div class="w-10 h-10 rounded-2xl bg-cyan-600 text-white flex items-center justify-center">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
    </div>
    <div>
      <h2 class="font-display font-black text-lg text-slate-900 uppercase tracking-tight">💰 Revenue Breakdown</h2>
      <p class="text-[10px] font-mono uppercase tracking-widest text-slate-400">Export orders revenue summary</p>
    </div>
  </div>
  <div class="p-6">
    <?php
      $revenueByStage = adm_q("SELECT stage, COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as total FROM export_orders WHERE stage NOT IN ('cancelled') GROUP BY stage ORDER BY total DESC");
      $recentTransactions = adm_q("SELECT order_number, total_amount, stage, created_at, destination_country FROM export_orders WHERE stage NOT IN ('cancelled') ORDER BY created_at DESC");
    ?>
    <?php if ($revenueByStage): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6" id="stage-filters">
      <div class="rounded-2xl bg-slate-900 border-2 border-slate-900 p-3 text-center cursor-pointer hover:shadow-md transition-all stage-filter active" data-stage="all" onclick="filterRevenueStage('all')">
        <p class="font-display font-black text-lg text-white">$<?= number_format(array_sum(array_column($revenueByStage, 'total')), 0) ?></p>
        <p class="font-mono text-[8px] uppercase tracking-widest text-white/70 font-bold">All Stages (<?= array_sum(array_column($revenueByStage, 'cnt')) ?>)</p>
      </div>
      <?php foreach ($revenueByStage as $rs): ?>
      <div class="rounded-2xl bg-slate-50 border border-slate-200 p-3 text-center cursor-pointer hover:shadow-md hover:border-slate-400 transition-all stage-filter" data-stage="<?= e($rs['stage']) ?>" onclick="filterRevenueStage('<?= e($rs['stage']) ?>')">
        <p class="font-display font-black text-lg text-slate-900">$<?= number_format((float)$rs['total'], 0) ?></p>
        <p class="font-mono text-[8px] uppercase tracking-widest text-slate-400 font-bold"><?= e(ucfirst(str_replace('_', ' ', $rs['stage']))) ?> (<?= (int)$rs['cnt'] ?>)</p>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-[11px]">
        <thead><tr class="border-b border-slate-100 bg-slate-50/50">
          <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Order</th>
          <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Amount</th>
          <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Stage</th>
          <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Destination</th>
          <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Date</th>
        </tr></thead>
        <tbody id="revenue-orders-body">
          <?php foreach ($recentTransactions as $rt): ?>
          <tr class="border-b border-slate-100 revenue-row" data-stage="<?= e($rt['stage']) ?>">
            <td class="px-4 py-3 font-bold text-slate-900">#<?= e($rt['order_number']) ?></td>
            <td class="px-4 py-3 font-mono font-bold">$<?= number_format((float)$rt['total_amount'], 0) ?></td>
            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[8px] font-bold uppercase font-mono border bg-slate-50 text-slate-600 border-slate-200"><?= e(ucfirst(str_replace('_', ' ', $rt['stage']))) ?></span></td>
            <td class="px-4 py-3 text-slate-500"><?= e($rt['destination_country'] ?: '—') ?></td>
            <td class="px-4 py-3 text-slate-400 font-mono text-[10px]"><?= e(date('Y-m-d', strtotime($rt['created_at'] ?? ''))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="font-mono text-[9px] text-slate-400" id="revenue-orders-count">Showing <?= count($recentTransactions) ?> orders</p>
    <?php else: ?>
    <div class="text-center py-8"><p class="text-slate-400 text-sm">No revenue data yet.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- Users Section -->
<div id="users-section" class="bg-white border border-slate-200 rounded-[28px] shadow-sm overflow-hidden">
  <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex items-center gap-3">
    <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    </div>
    <div>
      <h2 class="font-display font-black text-lg text-slate-900 uppercase tracking-tight">👥 Registered Users</h2>
      <p class="text-[10px] font-mono uppercase tracking-widest text-slate-400"><?= number_format($totalUsers) ?> active users — <?= number_format($newUsers) ?> new this month</p>
    </div>
  </div>
  <div class="p-6">
    <?php
      $allUsers = adm_q("SELECT id, username, full_name, email, phone, 'user' as role, created_at FROM export_user WHERE is_active = 1 UNION ALL SELECT id, '' as username, company_name as full_name, email, phone, 'restaurant' as role, created_at FROM export_restaurant_user WHERE is_active = 1 ORDER BY created_at DESC LIMIT 50");
    ?>
    <?php if ($allUsers): ?>
    <div class="overflow-x-auto">
      <table class="w-full text-[11px]">
        <thead><tr class="border-b border-slate-100 bg-slate-50/50">
          <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Name</th>
          <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Email</th>
          <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Phone</th>
          <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Role</th>
          <th class="text-left px-4 py-3 font-mono text-[9px] uppercase tracking-widest text-slate-400 font-bold">Joined</th>
        </tr></thead>
        <tbody>
          <?php foreach ($allUsers as $u): ?>
          <tr class="border-b border-slate-100 hover:bg-slate-50/50">
            <td class="px-4 py-3">
              <span class="font-bold text-slate-900"><?= e($u['full_name'] ?: $u['username']) ?></span>
            </td>
            <td class="px-4 py-3 font-mono text-slate-600"><?= e($u['email'] ?: '—') ?></td>
            <td class="px-4 py-3 font-mono text-slate-500"><?= e($u['phone'] ?: '—') ?></td>
            <td class="px-4 py-3">
              <?php if ($u['role'] === 'restaurant'): ?>
              <span class="px-2 py-0.5 rounded-full text-[8px] font-bold uppercase font-mono bg-amber-50 text-amber-700 border border-amber-100">Restaurant</span>
              <?php elseif ($u['role'] === 'admin'): ?>
              <span class="px-2 py-0.5 rounded-full text-[8px] font-bold uppercase font-mono bg-rose-50 text-rose-700 border border-rose-100">Admin</span>
              <?php else: ?>
              <span class="px-2 py-0.5 rounded-full text-[8px] font-bold uppercase font-mono bg-sky-50 text-sky-700 border border-sky-100">User</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-slate-400 font-mono text-[10px]"><?= e(date('Y-m-d', strtotime($u['created_at'] ?? ''))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="text-center py-8"><p class="text-slate-400 text-sm">No users found.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- Export Order Detail Modal -->
<div id="orderModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;" onclick="if(event.target===this)closeOrderModal()">
  <div style="background:white;border-radius:20px;width:90%;max-width:600px;max-height:85vh;overflow-y:auto;padding:28px;box-shadow:0 25px 50px rgba(0,0,0,0.25);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="margin:0;font-size:1.1rem;font-weight:900;letter-spacing:-0.02em;" id="modalTitle">📦 Order Details</h3>
      <button onclick="closeOrderModal()" style="background:none;border:none;color:#94a3b8;font-size:22px;cursor:pointer;padding:4px;">✕</button>
    </div>
    <div id="modalBody"></div>
  </div>
</div>

<script>
const ordersData = <?= json_encode($exportOrders) ?>;
const intlDriversData = <?= json_encode($intlDrivers) ?>;

function openOrderModal(orderId) {
  const o = ordersData.find(x => x.id == orderId);
  if (!o) return;
  const items = o.items ? JSON.parse(o.items) : [];
  const driver = (o.driver_id ?? null) ? intlDriversData.find(d => d.id == o.driver_id) : null;
  let html = '';

  // Shipment # banner
  if (o.shipment_number) {
    html += `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding:12px 16px;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border:1px solid #bae6fd;border-radius:12px;">`;
    html += `<div><span style="font-size:10px;color:#0284c7;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">🚛 Shipment Number</span><div style="font-weight:900;font-size:16px;color:#0369a1;margin-top:2px;">${o.shipment_number}</div></div>`;
    html += `<div style="text-align:right;"><span style="font-size:10px;color:#0284c7;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">Status</span><div style="margin-top:2px;"><span style="padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;text-transform:uppercase;background:${o.ship_status==='delivered'?'#d1fae5':o.ship_status==='in_transit'?'#dbeafe':'#fef3c7'};color:${o.ship_status==='delivered'?'#065f46':o.ship_status==='in_transit'?'#1e40af':'#92400e'};">${o.ship_status || o.stage}</span></div></div>`;
    html += `</div>`;
  }

  // Info grid
  html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;padding:14px;background:#f8fafc;border-radius:12px;font-size:13px;">`;
  html += `<div><span style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">Order #</span><div style="font-weight:800;margin-top:2px;">#${o.order_number}</div></div>`;
  html += `<div><span style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">Stage</span><div style="margin-top:2px;"><span style="padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;text-transform:uppercase;background:${o.stage==='delivered'?'#d1fae5':o.stage==='cancelled'?'#fee2e2':'#fef3c7'};color:${o.stage==='delivered'?'#065f46':o.stage==='cancelled'?'#991b1b':'#92400e'};">${o.stage}</span></div></div>`;
  html += `<div><span style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">Customer</span><div style="margin-top:2px;">${o.customer_name || o.full_name || o.username || o.restaurant_name || '—'}</div></div>`;
  html += `<div><span style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">Amount</span><div style="font-weight:800;font-size:18px;color:#0369a1;margin-top:2px;">$${Number(o.total_amount || 0).toLocaleString()}</div></div>`;
  if (o.email) html += `<div><span style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">Email</span><div style="margin-top:2px;">${o.email}</div></div>`;
  if (o.phone) html += `<div><span style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">Phone</span><div style="margin-top:2px;">${o.phone}</div></div>`;
  html += `<div><span style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">Date</span><div style="margin-top:2px;">${o.created_at || '—'}</div></div>`;
  html += `<div><span style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">Destination</span><div style="margin-top:2px;">${o.destination_country || '—'}</div></div>`;
  if (o.deposit_amount) html += `<div><span style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;">Deposit</span><div style="margin-top:2px;">$${Number(o.deposit_amount).toLocaleString()}</div></div>`;
  html += `</div>`;

  // Shipping info
  html += `<div style="margin-bottom:16px;padding:14px;background:#f8fafc;border-radius:12px;font-size:13px;">`;
  html += `<div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-bottom:8px;">🚢 Shipping Details</div>`;
  html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">`;
  if (o.shipment_number) html += `<div style="grid-column:1/-1;"><span style="color:#64748b;">Shipment:</span> <strong>${o.shipment_number}</strong></div>`;
  if (o.wh_name) html += `<div><span style="color:#64748b;">Warehouse:</span> ${o.wh_name}</div>`;
  if (driver) html += `<div><span style="color:#64748b;">Driver:</span> <strong>${driver.name}</strong> ${driver.vehicle_no ? '(' + driver.vehicle_no + ')' : ''}</div>`;
  if (o.destination_port) html += `<div><span style="color:#64748b;">Port:</span> ${o.destination_port}</div>`;
  if (o.incoterm) html += `<div><span style="color:#64748b;">Incoterm:</span> ${o.incoterm}</div>`;
  if (o.container_no) html += `<div><span style="color:#64748b;">Container:</span> ${o.container_no}</div>`;
  if (o.tracking_number) html += `<div><span style="color:#64748b;">Tracking:</span> ${o.tracking_number}</div>`;
  if (o.shipping_terms) html += `<div style="grid-column:1/-1;"><span style="color:#64748b;">Terms:</span> ${o.shipping_terms}</div>`;
  if (o.packed_at) html += `<div><span style="color:#64748b;">Packed:</span> ${o.packed_at}</div>`;
  if (o.shipped_at) html += `<div><span style="color:#64748b;">Shipped:</span> ${o.shipped_at}</div>`;
  if (o.delivered_at) html += `<div><span style="color:#64748b;">Delivered:</span> ${o.delivered_at}</div>`;
  html += `</div></div>`;

  // Items
  if (items.length) {
    html += `<div style="overflow-x:auto;margin-bottom:16px;border-radius:12px;border:1px solid #e2e8f0;"><table style="width:100%;font-size:12px;border-collapse:collapse;"><thead><tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;"><th style="padding:8px 12px;text-align:left;font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;">Product</th><th style="padding:8px 12px;text-align:center;font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;">Qty</th><th style="padding:8px 12px;text-align:right;font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;">Price</th><th style="padding:8px 12px;text-align:right;font-size:9px;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;">Subtotal</th></tr></thead><tbody>`;
    items.forEach(item => {
      html += `<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:8px 12px;font-weight:600;">${item.product_name || '—'}</td><td style="padding:8px 12px;text-align:center;">${item.qty || item.quantity || 0}</td><td style="padding:8px 12px;text-align:right;">$${Number(item.price || item.unit_price || 0).toFixed(2)}</td><td style="padding:8px 12px;text-align:right;font-weight:700;">$${Number(item.subtotal || 0).toFixed(2)}</td></tr>`;
    });
    html += `</tbody></table></div>`;
  }

  // Notes
  if (o.notes) {
    html += `<div style="padding:14px;background:#f8fafc;border-radius:12px;font-size:13px;">`;
    html += `<div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-bottom:4px;">📝 Notes</div>`;
    html += `<div style="color:#475569;">${o.notes}</div></div>`;
  }

  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('orderModal').style.display = 'flex';
}

function closeOrderModal() {
  document.getElementById('orderModal').style.display = 'none';
}

// ── Products category filter ──
function filterProducts(category) {
  // Update active state on filter cards
  document.querySelectorAll('.category-filter').forEach(function(el) {
    el.classList.remove('active', 'bg-slate-900', 'border-slate-900');
    el.classList.add('bg-slate-50', 'border-slate-200');
    var p = el.querySelector('.font-display');
    if (p) p.classList.remove('text-white');
    var span = el.querySelector('.font-mono');
    if (span) span.classList.remove('text-white/70');
  });

  var activeEl = document.querySelector('.category-filter[data-category="' + category + '"]');
  if (activeEl) {
    activeEl.classList.add('active', 'bg-slate-900', 'border-slate-900');
    activeEl.classList.remove('bg-slate-50', 'border-slate-200');
    var p = activeEl.querySelector('.font-display');
    if (p) p.classList.add('text-white');
    var span = activeEl.querySelector('.font-mono');
    if (span) span.classList.add('text-white/70');
  }

  // Filter table rows
  var visibleCount = 0;
  document.querySelectorAll('.product-row').forEach(function(row) {
    var rowCat = row.getAttribute('data-category');
    if (category === 'all' || rowCat === category) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  // Update count
  var countEl = document.getElementById('products-count');
  if (countEl) {
    var label = category === 'all' ? 'all categories' : category;
    countEl.textContent = 'Showing ' + visibleCount + ' products in ' + label;
  }
}
// ── Revenue stage filter ──
function filterRevenueStage(stage) {
  document.querySelectorAll('.stage-filter').forEach(function(el) {
    el.classList.remove('active', 'bg-slate-900', 'border-slate-900');
    el.classList.add('bg-slate-50', 'border-slate-200');
    var p = el.querySelector('.font-display');
    if (p) p.classList.remove('text-white');
    var span = el.querySelector('.font-mono');
    if (span) span.classList.remove('text-white/70');
  });
  var activeEl = document.querySelector('.stage-filter[data-stage="' + stage.replace(/"/g,'\\"') + '"]');
  if (activeEl) {
    activeEl.classList.add('active', 'bg-slate-900', 'border-slate-900');
    activeEl.classList.remove('bg-slate-50', 'border-slate-200');
    var p = activeEl.querySelector('.font-display');
    if (p) p.classList.add('text-white');
    var span = activeEl.querySelector('.font-mono');
    if (span) span.classList.add('text-white/70');
  }
  var visibleCount = 0;
  document.querySelectorAll('.revenue-row').forEach(function(row) {
    var rowStage = row.getAttribute('data-stage');
    if (stage === 'all' || rowStage === stage) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });
  var countEl = document.getElementById('revenue-orders-count');
  if (countEl) {
    var label = stage === 'all' ? 'all stages' : stage.replace(/_/g, ' ');
    countEl.textContent = 'Showing ' + visibleCount + ' orders in ' + label;
  }
}
</script>
<?php endif; ?>
</div></div></div>

</body></html>
