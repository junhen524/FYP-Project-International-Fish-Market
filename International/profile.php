<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};
require_once __DIR__ . '/includes/bootstrap.php';

$user = intl_current_user();
$balance = intl_wallet_balance();
$isRestaurant = isset($_SESSION['ifm_restaurant_id']);

// Handle logout
if (isset($_GET['logout'])) {
    intl_logout();
    header('Location: ' . url_for('index'));
    exit;
}

// Helper to get display name regardless of user type
$displayName = $user['full_name'] ?? $user['company_name'] ?? $user['username'] ?? 'User';

// Handle profile update
$updateMsg = '';
$updateType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    $updates = [];
    $params = [];

    if ($fullName !== '') {
        $col = $isRestaurant ? 'company_name' : 'full_name';
        $updates[] = "$col = ?";
        $params[] = $fullName;
    }
    $updates[] = 'phone = ?';
    $params[] = $phone;
    $updates[] = 'address = ?';
    $params[] = $address;

    if ($newPass !== '') {
        if (strlen($newPass) < 6) {
            $updateMsg = 'Password must be at least 6 characters.';
            $updateType = 'error';
        } elseif ($newPass !== $confirmPass) {
            $updateMsg = 'Passwords do not match.';
            $updateType = 'error';
        } else {
            $updates[] = 'password_hash = ?';
            $params[] = password_hash($newPass, PASSWORD_DEFAULT);
        }
    }

    if (!$updateMsg) {
        $table = $isRestaurant ? 'export_restaurant_user' : 'export_user';
        $params[] = $user['id'];
        dbExecute("UPDATE $table SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        $user = intl_current_user(); // refresh
        $displayName = $user['full_name'] ?? $user['company_name'] ?? $user['username'] ?? 'User';
        $updateMsg = 'Profile updated successfully!';
        $updateType = 'success';
    }
}

$isRestaurant = isset($_SESSION['ifm_restaurant_id']);
if ($isRestaurant) {
    $orders = dbGetAll("SELECT id, order_number, total_amount, currency, stage, created_at FROM export_orders WHERE restaurant_id = ? ORDER BY created_at DESC LIMIT 10", [$user['id']]);
} else {
    $orders = dbGetAll("SELECT id, order_number, total_amount, currency, stage, created_at FROM export_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", [$user['id']]);
}
$__ifmProfileConfig = ['loggedOutTitle' => 'Session De-authenticated', 'loggedOutBody' => 'Please log in.', 'loginCta' => 'GO TO LOGIN PAGE', 'recentActivityTitle' => 'Recent Orders', 'walletCta' => 'Open Wallet'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width,initial-scale=1" name="viewport">
<title>Profile — International Fish Market</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{'brand-blue':'#0369a1','stone-150':'#e8e5e0','stone-250':'#d6d2cb','stone-350':'#b8b2a8','amber-350':'#d9995b'},fontFamily:{display:['Inter','system-ui','sans-serif'],mono:['JetBrains Mono','monospace']}}}}
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/profile.css?v=<?= urlencode($__ifmAssetVersion('css/profile.css')) ?>"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;overflow-x:hidden}
body{overflow-x:hidden;font-family:'Inter',system-ui,-apple-system,sans-serif}
.font-display{font-family:'Inter',system-ui,-apple-system,sans-serif;letter-spacing:-0.03em}
.f-input{font-family:'JetBrains Mono',monospace;font-size:13px;border:1.5px solid #d6d2cb;border-radius:10px;padding:10px 14px;width:100%;transition:border-color .2s;outline:none;background:#fff}
.f-input:focus{border-color:#0d9488;box-shadow:0 0 0 3px rgba(13,148,136,0.1)}
.toast{position:fixed;top:24px;right:24px;z-index:9999;padding:14px 20px;border-radius:12px;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;box-shadow:0 8px 30px rgba(0,0,0,0.12);max-width:400px;animation:toastIn .3s ease;cursor:pointer}
.toast-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.toast-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
@keyframes toastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
@keyframes toastOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(40px)}}
</style>
</head>
<body style="margin:0;overflow-x:hidden">
<div id="root">
  <div class="relative min-h-screen bg-transparent text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip">
    <header id="main-app-header" class="fixed top-0 left-0 w-full z-[60] transition-all duration-300 bg-transparent py-5">
      <div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center">
        <a href="<?= url_for('index') ?>" class="cursor-pointer flex items-center space-x-2 group" id="brand-logo-trigger">
          <span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">INTERNATIONAL FISH MARKET</span>
          <span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse"></span>
        </a>
        <?php require __DIR__ . '/includes/nav_bar.php'; ?>
      </div>
    </header>
    <div id="subpage-viewport">
      <div class="min-h-screen bg-stone-100/50 pt-28 pb-24 px-4 md:px-8">
        <div class="max-w-5xl mx-auto space-y-8">

          <!-- Header -->
          <div class="border-b border-stone-250 pb-5 flex items-center space-x-3">
            <div class="p-2 bg-stone-900 text-stone-50 rounded-xl shadow-lg">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div>
              <h1 class="font-display font-black text-2xl text-stone-900 tracking-tight uppercase">Profile</h1>
              <p class="text-[10px] text-stone-500 font-mono uppercase tracking-widest mt-0.5">Manage your account &amp; view activity</p>
            </div>
          </div>

          <!-- Quick Links -->
          <div class="flex flex-wrap gap-2.5">
            <a href="<?= url_for('wallet') ?>" class="font-mono text-[9px] font-bold uppercase tracking-widest px-3 py-2 bg-stone-800 text-stone-50 rounded-xl no-underline hover:bg-stone-700 transition-all">💰 Wallet</a>
            <a href="<?= url_for('orders') ?>" class="font-mono text-[9px] font-bold uppercase tracking-widest px-3 py-2 bg-stone-800 text-stone-50 rounded-xl no-underline hover:bg-stone-700 transition-all">📦 My Orders</a>
            <a href="?logout=1" class="font-mono text-[9px] font-bold uppercase tracking-widest px-3 py-2 border border-stone-300 text-stone-500 rounded-xl no-underline hover:bg-red-50 hover:border-red-300 hover:text-red-600 transition-all">🚪 Logout</a>
          </div>

          <!-- Profile Info + Edit Form -->
          <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
            <!-- Profile Summary -->
            <div class="md:col-span-2 bg-white border border-stone-200/80 rounded-2xl p-6 shadow-sm">
              <div class="text-center">
                <div class="w-16 h-16 rounded-full bg-stone-900 text-stone-50 flex items-center justify-center mx-auto text-2xl font-black font-display">
                  <?= e(strtoupper(substr($displayName, 0, 1))) ?>
                </div>
                <h2 class="font-display font-black text-lg text-stone-900 mt-3"><?= e($displayName) ?></h2>
                <p class="font-mono text-[11px] text-stone-400"><?= e($user['company_name'] ?? '') ?></p>
              </div>
              <div class="mt-6 pt-4 border-t border-stone-100 space-y-3">
                <div>
                  <div class="font-mono text-[9px] text-stone-400 uppercase tracking-widest font-bold">Email</div>
                  <div class="font-mono text-[12px] font-bold text-stone-700 mt-0.5"><?= e($user['email']) ?></div>
                </div>
                <div>
                  <div class="font-mono text-[9px] text-stone-400 uppercase tracking-widest font-bold">Country</div>
                  <div class="font-mono text-[12px] font-bold text-stone-700 mt-0.5"><?= e($user['country_code'] ?: '-') ?></div>
                </div>
                <div>
                  <div class="font-mono text-[9px] text-stone-400 uppercase tracking-widest font-bold">Member Since</div>
                  <div class="font-mono text-[12px] font-bold text-stone-700 mt-0.5"><?= e($user['created_at'] ? date('M Y', strtotime($user['created_at'])) : '-') ?></div>
                </div>
                <div>
                  <div class="font-mono text-[9px] text-stone-400 uppercase tracking-widest font-bold">Status</div>
                  <div class="font-mono text-[12px] font-bold text-stone-700 mt-0.5 capitalize"><?= e($user['account_status'] ?: 'active') ?></div>
                </div>
                <div class="pt-3 text-center">
                  <div class="font-mono text-[9px] text-stone-400 uppercase tracking-widest font-bold">Wallet Balance</div>
                  <div class="font-display font-black text-3xl text-stone-900 mt-1">$<?= formatted_money($balance) ?></div>
                </div>
              </div>
            </div>

            <!-- Edit Form -->
            <div class="md:col-span-3 bg-white border border-stone-200/80 rounded-2xl p-6 shadow-sm">
              <h2 class="font-display font-black text-sm text-stone-900 uppercase tracking-wider mb-5">✏️ Edit Profile</h2>
              <form method="post">
                <input type="hidden" name="action" value="update_profile">

                <!-- Name (Full Name for user, Company Name for restaurant) -->
                <div class="mb-4">
                  <label class="font-mono text-[10px] text-stone-500 uppercase tracking-widest font-bold block mb-1.5"><?= $isRestaurant ? 'Company Name' : 'Full Name' ?></label>
                  <input type="text" name="full_name" class="f-input" value="<?= e($user['full_name'] ?? $user['company_name'] ?? '') ?>" placeholder="<?= $isRestaurant ? 'Your company name' : 'Your full name' ?>">
                </div>

                <!-- Phone -->
                <div class="mb-4">
                  <label class="font-mono text-[10px] text-stone-500 uppercase tracking-widest font-bold block mb-1.5">Phone Number</label>
                  <input type="text" name="phone" class="f-input" value="<?= e($user['phone'] ?? '') ?>" placeholder="+60 12-345 6789">
                </div>

                <!-- Address -->
                <div class="mb-4">
                  <label class="font-mono text-[10px] text-stone-500 uppercase tracking-widest font-bold block mb-1.5">Address</label>
                  <textarea name="address" class="f-input" rows="3" placeholder="Your shipping address"><?= e($user['address'] ?? '') ?></textarea>
                </div>

                <!-- New Password -->
                <div class="mb-4">
                  <label class="font-mono text-[10px] text-stone-500 uppercase tracking-widest font-bold block mb-1.5">New Password <span class="text-stone-300 normal-case tracking-normal">(leave blank to keep current)</span></label>
                  <input type="password" name="new_password" class="f-input" placeholder="Min. 6 characters" autocomplete="new-password">
                </div>

                <!-- Confirm Password -->
                <div class="mb-5">
                  <label class="font-mono text-[10px] text-stone-500 uppercase tracking-widest font-bold block mb-1.5">Confirm New Password</label>
                  <input type="password" name="confirm_password" class="f-input" placeholder="Re-enter new password" autocomplete="new-password">
                </div>

                <button type="submit" class="w-full py-3 bg-stone-900 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all cursor-pointer hover:bg-stone-800">Save Changes</button>
              </form>
            </div>
          </div>

          <!-- Recent Orders -->
          <div class="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-sm">
            <h2 class="font-display font-black text-sm text-stone-900 uppercase tracking-wider mb-4">📦 Recent Orders</h2>
            <?php if ($orders): ?>
            <div class="space-y-0">
              <?php foreach ($orders as $o): ?>
              <div class="flex items-center justify-between py-3 border-b border-stone-100 last:border-b-0">
                <div>
                  <div class="font-display font-bold text-sm text-stone-900">#<?= e($o['order_number']) ?></div>
                  <div class="font-mono text-[9px] text-stone-400 mt-0.5"><?= e($o['created_at']) ?></div>
                </div>
                <div style="text-align:right">
                  <span class="font-mono text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full <?php $s = $o['stage']; echo $s==='confirmed'?'bg-amber-100 text-amber-700':($s==='shipping'?'bg-sky-100 text-sky-700':($s==='delivered'?'bg-emerald-100 text-emerald-700':($s==='rejected'?'bg-red-100 text-red-700':'bg-slate-100 text-slate-600'))) ?>"><?= e(ucfirst($s)) ?></span>
                  <div class="font-display font-black text-sm text-stone-900 mt-1">$<?= formatted_money((float)$o['total_amount']) ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="font-mono text-[11px] text-stone-400 text-center py-6">No orders yet.</p>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script src="js/app.js?v=<?= urlencode(__ifmAssetVersion('js/app.js')) ?>"></script>
<?php if ($updateMsg): ?>
<div class="toast toast-<?= $updateType ?>" onclick="this.style.animation='toastOut .3s ease forwards';setTimeout(function(){this.style.display='none'}.bind(this),300)"><?= e($updateMsg) ?></div>
<script>(function(){var t=document.querySelector('.toast');if(t){setTimeout(function(){t.style.animation='toastOut .3s ease forwards';setTimeout(function(){t.style.display='none'},300)},3000)}})();</script>
<?php endif; ?>
</body>
</html>
