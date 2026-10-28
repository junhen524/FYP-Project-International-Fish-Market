<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$port_id = (int)($_GET['port_id'] ?? 0);
$port = dbGetRow("SELECT id, name, code FROM ports WHERE id = ? AND is_active = 1", [$port_id]);

$title = ($port ? $port['name'] : 'Port') . ' Stock';
require __DIR__ . '/../helpers/header.php';
?>
<style>
  .placeholder-icon { font-size: 48px; margin-bottom: 16px; }
  .placeholder-title { font-size: 20px; font-weight: 800; margin: 0 0 8px; }
  .placeholder-desc { color: var(--muted); font-size: 14px; max-width: 420px; line-height: 1.6; margin: 0 0 24px; }
</style>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🏭 Warehouse</div>
    <a class="dash-sidebar-item" href="/dashboard/stock/">📋 Stock</a>
    <a class="dash-sidebar-item <?= $port_id === 1 ? 'active' : '' ?>" href="/dashboard/stock/port/1/">🏭 Port Klang</a>
    <a class="dash-sidebar-item <?= $port_id === 2 ? 'active' : '' ?>" href="/dashboard/stock/port/2/">🏭 Penang Port</a>
    <a class="dash-sidebar-item <?= $port_id === 3 ? 'active' : '' ?>" href="/dashboard/stock/port/3/">🏭 Johor Port</a>
    <a class="dash-sidebar-item <?= $port_id === 4 ? 'active' : '' ?>" href="/dashboard/stock/port/4/">🏭 Kuantan Port</a>
    <div style="border-top:1px solid var(--border);margin:8px 14px;"></div>
    <a class="dash-sidebar-item" href="/dashboard/stock/movements/">📊 Movements</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/add/">➕ Add Product</a>
  </nav>
  <div class="dash-content">
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:80px 20px;">
      <div class="placeholder-icon">🏗️</div>
      <h2 class="placeholder-title"><?= e($port['name'] ?? 'Port #' . $port_id) ?> — Coming Soon</h2>
      <p class="placeholder-desc">This port's dedicated stock dashboard is under development. You can still view overall stock across all ports from the main Stock page.</p>
      <a href="/dashboard/stock/" class="btn btn-primary">← Back to Stock Overview</a>
    </div>
  </div>
</div>
