<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$search_q = $_GET['q'] ?? '';
$users = []; $totalUsers = 0;
try {
    $sql = "SELECT id, username, email, full_name, phone, COALESCE(balance, 0) as balance FROM market_user";
    $params = [];
    if ($search_q) {
        $sql .= " WHERE LOWER(username) LIKE LOWER(?) OR LOWER(email) LIKE LOWER(?) OR LOWER(full_name) LIKE LOWER(?)";
        $params[] = "%$search_q%"; $params[] = "%$search_q%"; $params[] = "%$search_q%";
    }
    $sql .= " ORDER BY id DESC LIMIT 100";
    $users = dbGetAll($sql, $params);
    $totalUsers = (int)dbGetValue("SELECT COUNT(*) FROM market_user");
} catch (Exception $e) {}

$title = 'Market Users';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<div class="dash-layout">
<?php $sidebarActive = 'users'; require __DIR__ . '/../helpers/sidebar_market.php'; ?>

  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
          <h2 style="margin:0;">👥 Market Users</h2>
          <p class="subtle" style="margin:4px 0 0;">Total: <strong><?= e($totalUsers) ?></strong> users</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <a href="/dashboard/analytics/market/users/add/" class="btn btn-ghost" style="padding:10px 18px;font-size:12px;font-weight:700;background:var(--brand);color:#081225;border:none;border-radius:8px;">➕ Add</a>
          <form method="get" style="display:flex;gap:6px;">
          <input type="search" name="q" placeholder="Search users..." value="<?= e($search_q) ?>" class="form-control" style="width:200px;">
          <button type="submit" class="btn btn-ghost" style="padding:10px 14px;font-size:13px;font-weight:700;">🔍 Search</button>
          <?php if ($search_q): ?>
          <a href="/dashboard/analytics/market/users/" class="btn btn-ghost" style="padding:8px 14px;">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="card" style="overflow-x:auto;">
      <table class="activity-table">
        <thead>
          <tr><th>Username</th><th>Email</th><th>Name</th><th>Phone</th><th style="text-align:right;">Wallet</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><strong><?= e($u['username'] ?? '') ?></strong></td>
            <td style="color:var(--muted);font-size:12px;"><?= e($u['email'] ?? '') ?></td>
            <td><?= e($u['full_name'] ?? '') ?></td>
            <td style="color:var(--muted);"><?= e($u['phone'] ?? '—') ?></td>
            <td style="text-align:right;font-weight:700;font-family:monospace;color:<?= (float)($u['balance'] ?? 0) > 0 ? 'var(--ok, #10b981)' : 'var(--muted)' ?>;">RM<?= number_format((float)($u['balance'] ?? 0), 2) ?></td>
            <td><a href="/dashboard/analytics/market/users/edit/?id=<?= e($u['id']) ?>" class="btn btn-ghost" style="padding:4px 12px;font-size:11px;font-weight:700;">✏️ Edit</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);">No users found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
