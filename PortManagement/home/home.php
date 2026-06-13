<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$title = 'Port Management System';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🏠 Home</div>
    <a class="dash-sidebar-item active" href="/home/">🏠 Home</a>
    <a class="dash-sidebar-item" href="/dashboard/">📊 Dashboard</a>
  </nav>
  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;text-align:center;padding:48px 32px;">
      <h1 style="margin:0 0 8px;font-size:28px;font-weight:800;">Port Management System</h1>
      <p class="subtle" style="margin:0 0 24px;font-size:15px;max-width:600px;margin-left:auto;margin-right:auto;">
        A unified operations workspace for Malaysia ports: weather overview, vessel schedules, berth usage, cargo status, reports, and alerts.
      </p>
      <div style="display:flex;gap:12px;justify-content:center;">
        <a href="/dashboard/" class="btn-save">📊 Enter Dashboard</a>
        <a href="/accounts/login/" class="btn btn-ghost" style="padding:10px 28px;font-size:14px;">🔑 Login</a>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
      <div class="card" style="padding:24px;">
        <div style="font-size:32px;margin-bottom:12px;">⚡</div>
        <h3 style="margin:0 0 6px;font-size:16px;">Quick Access</h3>
        <p class="subtle" style="margin:0;">View live weather overview and role-based access panel.</p>
      </div>
      <div class="card" style="padding:24px;">
        <div style="font-size:32px;margin-bottom:12px;">🔒</div>
        <h3 style="margin:0 0 6px;font-size:16px;">Role-based</h3>
        <p class="subtle" style="margin:0;">Super Admin / Port Admin / User access boundaries by port.</p>
      </div>
      <div class="card" style="padding:24px;">
        <div style="font-size:32px;margin-bottom:12px;">🏭</div>
        <h3 style="margin:0 0 6px;font-size:16px;">Port-centric</h3>
        <p class="subtle" style="margin:0;">Users are assigned to a port by administrator.</p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
