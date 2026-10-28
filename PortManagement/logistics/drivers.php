<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$drivers = []; $ports = [];
try {
    $drivers = dbGetAll("SELECT d.*, p.name as port_name FROM market_drivers d LEFT JOIN ports p ON p.id = d.port_id ORDER BY d.name");
    $ports = dbGetAll("SELECT id, name FROM ports ORDER BY id");
} catch (Exception $e) {}

$title = 'Drivers';
require __DIR__ . '/../helpers/header.php';
?>
<div class="dash-layout">
<?php $sidebarActive = 'drivers'; require __DIR__ . '/../helpers/sidebar_market.php'; ?>
  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
      <div>
        <h2 style="margin:0;">👤 Drivers</h2>
        <p class="subtle" style="margin:4px 0 0;">Manage delivery drivers</p>
      </div>
      <a href="/logistics/drivers/add/" class="btn btn-ghost" style="padding:10px 18px;font-size:12px;font-weight:700;background:var(--brand);color:#081225;border:none;border-radius:8px;">➕ Add</a>
    </div>
    <div class="card">
      <table class="activity-table">
        <thead><tr><th>Name</th><th>Phone</th><th>IC / ID</th><th>Port</th><th>License</th><th>Vehicle</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (empty($drivers)): ?><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No drivers found.</td></tr><?php endif; ?>
          <?php foreach ($drivers as $d): ?>
          <tr><td><strong><?= e($d['name']) ?></strong></td><td><?= e($d['phone'] ?? '—') ?></td><td style="font-family:monospace;font-size:12px;"><?= e($d['identification_no'] ?? '—') ?></td><td><?= e($d['port_name'] ?? '—') ?></td><td><?= e($d['license_no'] ?? '—') ?></td><td><?= e($d['vehicle_no'] ?? '—') ?></td><td><span class="status-badge badge-active"><?= $d['is_active'] ? 'Active' : 'Inactive' ?></span></td><td><a href="/logistics/drivers/edit/?id=<?= e($d['id']) ?>" class="btn btn-ghost" style="padding:2px 10px;font-size:11px;">Edit</a></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
