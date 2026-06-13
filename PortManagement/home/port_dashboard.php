<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$port_id = (int)($user['port_id'] ?? 0);
$port_name = dbGetValue("SELECT name FROM ports WHERE id = ?", [$port_id]) ?: $userPort;

$vessel_count = 0; $today_arrivals = 0; $total_inventory = 0;
try { $vessel_count = (int)dbGetValue("SELECT COUNT(*) FROM vessels WHERE port_id = ?", [$port_id] ?: []); } catch (Exception $e) {}
try { $today_arrivals = (int)dbGetValue("SELECT COUNT(*) FROM port_entries WHERE port_id = ? AND entry_date = CURRENT_DATE", [$port_id] ?: []); } catch (Exception $e) {}
try { $total_inventory = (int)dbGetValue("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE port_id = ?", [$port_id] ?: []); } catch (Exception $e) {}

$title = $port_name . ' Dashboard';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🏭 Port</div>
    <a class="dash-sidebar-item active" href="/dashboard/port/">🏭 Port Overview</a>
    <a class="dash-sidebar-item" href="/dashboard/">🏠 Home</a>
  </nav>

  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <h2 style="margin:0;">🏭 <?= e($port_name) ?> Dashboard</h2>
      <p class="subtle" style="margin:4px 0 0;">Port-specific operations and metrics</p>
    </div>

    <div class="dash-grid" style="grid-template-columns:repeat(3,1fr);">
      <div class="dash-card"><div class="dash-icon" style="background:rgba(45,212,191,0.12);">🚢</div><div><div class="dash-label">Vessels</div><div class="dash-value"><?= e($vessel_count) ?></div></div></div>
      <div class="dash-card"><div class="dash-icon" style="background:rgba(96,165,250,0.12);">📥</div><div><div class="dash-label">Today Arrivals</div><div class="dash-value"><?= e($today_arrivals) ?></div></div></div>
      <div class="dash-card"><div class="dash-icon" style="background:rgba(52,211,153,0.12);">📦</div><div><div class="dash-label">Inventory</div><div class="dash-value"><?= e($total_inventory) ?></div></div></div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px;">
      <div class="card">
        <h3 style="margin:0 0 6px;font-size:16px;">Operations Overview</h3>
        <p class="subtle" style="margin:0 0 12px;">This panel is ready for vessel schedules, cargo data, berth usage, reports, alerts, and port-specific records.</p>
        <div style="background:rgba(96,165,250,0.10);border:1px solid rgba(96,165,250,0.25);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--brand-2);margin-bottom:16px;">
          ℹ️ Access granted for this port.
        </div>
        <div style="display:flex;gap:12px;">
          <a href="/dashboard/" class="btn btn-ghost" style="padding:10px 24px;font-size:13px;font-weight:700;">📊 Back to Dashboard</a>
          <a href="/accounts/logout/" class="btn btn-ghost" style="padding:10px 24px;font-size:13px;">Logout</a>
        </div>
      </div>
      <div class="card">
        <h3 style="margin:0 0 12px;font-size:16px;">Access Summary</h3>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid var(--border);">
            <span style="color:var(--muted);">Username</span>
            <span style="font-weight:700;"><?= e($displayName) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid var(--border);">
            <span style="color:var(--muted);">Role</span>
            <span style="font-weight:700;"><?= e($roleDisplay) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid var(--border);">
            <span style="color:var(--muted);">Authorized Port</span>
            <span style="font-weight:700;"><?= e($port_name) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;">
            <span style="color:var(--muted);">Status</span>
            <span style="font-weight:700;color:var(--ok);">Active</span>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div style="display:flex;align-items:center;gap:16px;">
        <span style="font-size:32px;">🏭</span>
        <div>
          <strong style="font-size:16px;">Port Management</strong>
          <p class="subtle" style="margin:4px 0 0;">Monitor port activities, vessel entries, and inventory at <strong><?= e($port_name) ?></strong>.</p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
