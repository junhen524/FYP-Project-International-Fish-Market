<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$message = $_SESSION['_topup_msg'] ?? '';
$error = $_SESSION['_topup_err'] ?? '';
unset($_SESSION['_topup_msg'], $_SESSION['_topup_err']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $userType = $_POST['user_type'] ?? 'user';
    $amount = (float)($_POST['amount'] ?? 0);
    if ($userId > 0 && $amount > 0) {
        try {
            if ($userType === 'restaurant') {
                $walletId = dbGetValue("SELECT id FROM export_wallets WHERE restaurant_id = ?", [$userId]);
                if (!$walletId) {
                    dbExecute("INSERT INTO export_wallets (restaurant_id, balance, currency) VALUES (?, 0, 'USD')", [$userId]);
                    $walletId = dbLastInsertId();
                }
            } else {
                $walletId = dbGetValue("SELECT id FROM export_wallets WHERE user_id = ?", [$userId]);
                if (!$walletId) {
                    dbExecute("INSERT INTO export_wallets (user_id, balance, currency) VALUES (?, 0, 'USD')", [$userId]);
                    $walletId = dbLastInsertId();
                }
            }
            $before = (float)dbGetValue("SELECT COALESCE(balance, 0) FROM export_wallets WHERE id = ?", [$walletId]);
            $after = $before + $amount;
            dbExecute("UPDATE export_wallets SET balance = ?, updated_at = NOW() WHERE id = ?", [$after, $walletId]);
            dbExecute(
                "INSERT INTO export_wallet_txn (wallet_id, transaction_type, amount, balance_before, balance_after, description, status, created_at)
                 VALUES (?, 'topup', ?, ?, ?, 'Admin top-up', 'completed', NOW())",
                [$walletId, $amount, $before, $after]
            );
            $_SESSION['_topup_msg'] = 'Successfully topped up USD ' . number_format($amount, 2) . '.';
            $qs = $_GET['q'] ? '&q=' . urlencode($_GET['q']) : '';
            header('Location: ?' . $qs);
            exit;
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Select a user and enter an amount.';
    }
}

$search_q = $_GET['q'] ?? '';
$users = [];
try {
    // Regular users
    $sql = "SELECT u.id, u.username, u.full_name, 'user' as user_type, COALESCE(w.balance, 0) as balance "
          . "FROM export_user u LEFT JOIN export_wallets w ON w.user_id = u.id";
    $params = [];
    $wheres = [];
    if ($search_q) {
        $wheres[] = "(LOWER(u.username) LIKE LOWER(?) OR LOWER(u.full_name) LIKE LOWER(?) OR LOWER(u.email) LIKE LOWER(?))";
        $params = array_merge($params, ["%$search_q%", "%$search_q%", "%$search_q%"]);
    }
    if ($wheres) $sql .= " WHERE " . implode(' AND ', $wheres);
    $sql .= " ORDER BY u.id";
    $users = dbGetAll($sql, $params);

    // Restaurant users
    $sql2 = "SELECT ru.id, ru.company_name as username, ru.company_name as full_name, 'restaurant' as user_type, COALESCE(w.balance, 0) as balance "
           . "FROM export_restaurant_user ru LEFT JOIN export_wallets w ON w.restaurant_id = ru.id";
    $params2 = [];
    $wheres2 = [];
    if ($search_q) {
        $wheres2[] = "(LOWER(ru.company_name) LIKE LOWER(?) OR LOWER(ru.email) LIKE LOWER(?))";
        $params2 = array_merge($params2, ["%$search_q%", "%$search_q%"]);
    }
    if ($wheres2) $sql2 .= " WHERE " . implode(' AND ', $wheres2);
    $sql2 .= " ORDER BY ru.id";
    $restUsers = dbGetAll($sql2, $params2);

    // Merge and sort
    $users = array_merge($users, $restUsers);
    usort($users, function($a, $b) { return $a['id'] - $b['id']; });
} catch (Exception $e) {}

$title = 'International Top-Up';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.topup-table { width: 100%; border-collapse: collapse; }
.topup-table th { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); padding: 12px; border-bottom: 1px solid var(--border); }
.topup-table td { padding: 14px 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
.topup-table tr:hover td { background: rgba(255, 255, 255, 0.06); }
.topup-input { padding: 8px 12px; border-radius: 8px; background: rgba(255, 255, 255, 0.10); border: 1px solid var(--border); color: var(--text); width: 100px; font-size: 13px; }
.btn-topup { padding: 8px 18px; border-radius: 8px; border: none; background: var(--brand); color: #081225; font-weight: 700; font-size: 12px; cursor: pointer; }
.btn-topup:hover { filter: brightness(1.1); }
</style>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🌍 International</div>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/">📊 Dashboard</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/orders/">📋 Orders</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/users/">👥 Users</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/restaurants/">🏪 Restaurants</a>
    <a class="dash-sidebar-item active" href="/dashboard/analytics/international/topup/">💰 Top-Up</a>
    <a class="dash-sidebar-item" href="/logistics/international/">🚢 Export Logistics</a>
    <a class="dash-sidebar-item" href="/logistics/export_driver/">👤 Intl. Drivers</a>
  </nav>

  <div class="dash-content">
    <div class="card" style="margin-bottom:20px;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
          <h2 style="margin:0;">💰 International Wallet Top-Up</h2>
          <p class="subtle" style="margin:4px 0 0;">Add balance to international user wallets</p>
        </div>
        <form method="get" style="display:flex;gap:6px;">
          <input type="search" name="q" placeholder="Search users..." value="<?= e($_GET['q'] ?? '') ?>" class="form-control" style="width:200px;">
          <button type="submit" class="btn btn-ghost" style="padding:10px 14px;font-size:13px;font-weight:700;">🔍 Search</button>
          <?php if (!empty($_GET['q'])): ?>
          <a href="/dashboard/analytics/international/topup/" class="btn btn-ghost" style="padding:8px 14px;">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <?php if ($message): ?><div class="msg-success topup-msg"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg-error topup-msg"><?= e($error) ?></div><?php endif; ?>

    <script>
    (function() {
      var els = document.querySelectorAll('.topup-msg');
      if (els.length) els.forEach(function(el) {
        setTimeout(function() { el.style.transition = 'opacity 0.5s'; el.style.opacity = '0'; setTimeout(function(){ el.style.display='none'; }, 500); }, 3000);
      });
    })();
    </script>

    <div class="card" style="overflow-x:auto;">
      <table class="topup-table">
        <thead><tr><th>Type</th><th>Username</th><th>Name</th><th>Current Balance</th><th>Top-Up Amount</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?php if ($u['user_type'] === 'restaurant'): ?><span style="padding:3px 10px;border-radius:6px;font-size:9px;font-weight:700;text-transform:uppercase;background:rgba(251,191,36,0.15);color:#f59e0b;border:1px solid rgba(251,191,36,0.2);">🏪 Restaurant</span><?php else: ?><span style="padding:3px 10px;border-radius:6px;font-size:9px;font-weight:700;text-transform:uppercase;background:rgba(56,189,248,0.15);color:#38bdf8;border:1px solid rgba(56,189,248,0.2);">👤 User</span><?php endif; ?></td>
            <td><strong><?= e($u['username'] ?? '') ?></strong></td>
            <td><?= e($u['full_name'] ?? '—') ?></td>
            <td style="font-weight:700;">USD<?= number_format($u['balance'] ?? 0, 2) ?></td>
            <td>
              <form method="POST" style="display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                <input type="hidden" name="user_type" value="<?= e($u['user_type']) ?>">
                <input type="number" name="amount" class="topup-input" placeholder="0.00" step="0.01" min="1" required>
                <button class="btn-topup" type="submit">Top Up</button>
              </form>
            </td>
            <td></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);">No users found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
