<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

// Session flash message
$flashMsg = $_SESSION['_user_msg'] ?? '';
unset($_SESSION['_user_msg']);

$search_q = $_GET['q'] ?? '';
$country_filter = $_GET['country'] ?? '';

$users = [];
$totalUsers = 0;
$countries = [];

try {
    $countries = dbGetAll("SELECT DISTINCT country_code FROM export_user WHERE country_code IS NOT NULL AND role != 'admin' ORDER BY country_code");
    $totalUsers = (int)dbGetValue("SELECT COUNT(*) FROM export_user WHERE role != 'admin'");

    $sql = "SELECT id, username, email, full_name, country_code, role, account_status, created_at FROM export_user WHERE role != 'admin'";
    $params = [];
    $wheres = [];
    if ($search_q) { $wheres[] = "(LOWER(username) LIKE LOWER(?) OR LOWER(email) LIKE LOWER(?) OR LOWER(full_name) LIKE LOWER(?))"; $params[] = "%$search_q%"; $params[] = "%$search_q%"; $params[] = "%$search_q%"; }
    if ($country_filter) { $wheres[] = "country_code = ?"; $params[] = $country_filter; }
    if ($wheres) $sql .= " WHERE " . implode(' AND ', $wheres);
    $sql .= " ORDER BY id";
    $users = dbGetAll($sql, $params);
} catch (Exception $e) {}

$title = 'International Users';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.filter-tag { display:inline-block; padding:4px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; background:rgba(255,255,255,0.06); color:var(--muted); border:1px solid var(--border); transition:.12s; }
.filter-tag:hover { background:rgba(255,255,255,0.12); color:var(--text); }
.filter-tag.active { background:rgba(45,212,191,0.15); color:var(--brand); border-color:var(--brand); }
</style>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🌍 International</div>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/">📊 Dashboard</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/orders/">📋 Orders</a>
    <a class="dash-sidebar-item active" href="/dashboard/analytics/international/users/">👥 Users</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/restaurants/">🏪 Restaurants</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/topup/">💰 Top-Up</a>
    <a class="dash-sidebar-item" href="/logistics/international/">🚢 Export Logistics</a>
    <a class="dash-sidebar-item" href="/logistics/export_driver/">👤 Intl. Drivers</a>
  </nav>

  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
          <h2 style="margin:0;">👥 International Users</h2>
          <p class="subtle" style="margin:4px 0 0;">Total: <strong><?= e($totalUsers) ?></strong> users</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <a href="/dashboard/analytics/international/users/add/" class="btn btn-ghost" style="padding:10px 18px;font-size:12px;font-weight:700;background:var(--brand);color:#081225;border:none;border-radius:8px;">➕ Add</a>
          <form method="get" style="display:flex;gap:6px;">
          <select name="country" onchange="this.form.submit()" class="form-control" style="width:auto;display:inline-block;padding:8px 12px;font-size:12px;font-weight:600;">
            <option value="">🌍 All Countries</option>
            <?php foreach ($countries as $c): ?>
            <option value="<?= e($c['country_code']) ?>" <?= $country_filter === $c['country_code'] ? 'selected' : '' ?>><?= e($c['country_code']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="search" name="q" placeholder="Search users..." value="<?= e($search_q) ?>" class="form-control" style="width:200px;">
          <button type="submit" class="btn btn-ghost" style="padding:10px 14px;font-size:13px;font-weight:700;">🔍</button>
          <?php if ($search_q || $country_filter): ?>
          <a href="/dashboard/analytics/international/users/" class="btn btn-ghost" style="padding:8px 14px;">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
    </div>

    <?php if ($flashMsg): ?><div class="topup-msg" style="padding:12px 16px;border-radius:10px;background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.3);color:#34d399;margin-bottom:16px;font-size:13px;"><?= e($flashMsg) ?></div><?php endif; ?>
    <script>
    (function(){ var els=document.querySelectorAll('.topup-msg'); if(els.length) els.forEach(function(el){ setTimeout(function(){ el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(function(){ el.style.display='none'; },500); },3000); }); })();
    </script>

    <div class="card" style="overflow-x:auto;">
      <table class="activity-table">
        <thead><tr><th>Username</th><th>Email</th><th>Full Name</th><th>Country</th><th>Role</th><th>Status</th><th>Joined</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><strong><?= e($u['username'] ?? '') ?></strong></td>
            <td style="color:var(--muted);font-size:12px;"><?= e($u['email'] ?? '') ?></td>
            <td><?= e($u['full_name'] ?? '—') ?></td>
            <td><span class="port-badge"><?= e($u['country_code'] ?? '—') ?></span></td>
            <td><span class="status-badge"><?= e(ucfirst($u['role'] ?? 'user')) ?></span></td>
            <td><span class="status-badge badge-<?= e($u['account_status'] ?? '') ?>"><?= e(ucfirst($u['account_status'] ?? 'inactive')) ?></span></td>
            <td style="font-family:monospace;font-size:11px;color:var(--muted);"><?= e(date('Y-m-d', strtotime($u['created_at'] ?? ''))) ?></td>
            <td><a href="/dashboard/analytics/international/users/edit/?id=<?= e($u['id']) ?>" class="btn btn-ghost" style="padding:4px 12px;font-size:11px;">Edit</a> <form method="POST" action="/dashboard/analytics/international/users/edit/?id=<?= e($u['id']) ?>" style="display:inline;" onsubmit="return confirm('Delete this user?');"><input type="hidden" name="action" value="delete"><button type="submit" class="btn btn-ghost" style="padding:4px 12px;font-size:11px;color:#ef4444;border-color:rgba(239,68,68,0.3);">Delete</button></form></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No users found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
