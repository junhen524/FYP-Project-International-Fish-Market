<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$title = 'Data Export';
require __DIR__ . '/../helpers/header.php';

$datasets = [
    'stock' => ['name' => 'Product Stock', 'icon' => '📦', 'desc' => 'All products with prices, categories, and total unit stock'],
    'stock_tiers' => ['name' => 'Stock Tiers (Units)', 'icon' => '📐', 'desc' => 'Breakdown of each product unit option and its stock'],
    'sales' => ['name' => 'Sales Orders', 'icon' => '🛒', 'desc' => 'Market orders with customer info, amounts, and status'],
    'wallet_transactions' => ['name' => 'Wallet Transactions', 'icon' => '💰', 'desc' => 'Customer top-ups and wallet movements'],
    'catches' => ['name' => 'Fish Catches', 'icon' => '🐟', 'desc' => 'All catch records from vessel fishing operations'],
    'fishing_trips' => ['name' => 'Fishing Trips', 'icon' => '🎣', 'desc' => 'Vessel fishing expedition logs'],
    'drivers' => ['name' => 'Local Drivers', 'icon' => '🚚', 'desc' => 'Local delivery driver records'],
    'export_drivers' => ['name' => 'Export Drivers', 'icon' => '🚢', 'desc' => 'International shipping driver records'],
    'vessels' => ['name' => 'Vessels', 'icon' => '🚢', 'desc' => 'All fishing vessels and their status'],
    'vessel_tracks' => ['name' => 'Vessel Tracks', 'icon' => '📍', 'desc' => 'Vessel GPS tracking data points (last 500)'],
];
?>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">📊 Analytics</div>
    <a class="dash-sidebar-item" href="/dashboard/analytics/unified/">📈 Dashboard</a>
    <a class="dash-sidebar-item active" href="/dashboard/analytics/data-export/">📤 Data Export</a>
    <div style="border-top:1px solid var(--border);margin:8px 14px;"></div>
    <a class="dash-sidebar-item" href="/dashboard/stock/">📋 Stock</a>
    <a class="dash-sidebar-item" href="/vessels/trips/">🎣 Fishing Trips</a>
    <a class="dash-sidebar-item" href="/logistics/drivers/">🚚 Drivers</a>
  </nav>
  <div class="dash-content">
    <div style="margin-bottom:20px;">
      <h2 style="margin:0;font-size:1.3rem;">📤 Data Export</h2>
      <p class="subtle" style="margin:4px 0 0;">Export data for Power BI, Excel, or external analytics tools. Use the JSON URL for Power BI's <strong>Web</strong> data connector.</p>
    </div>

    <!-- Power BI Connection Guide -->
    <div style="background:rgba(52,211,153,0.06);border:1px solid rgba(52,211,153,0.2);border-radius:12px;padding:16px 20px;margin-bottom:24px;">
      <h3 style="margin:0 0 8px;font-size:1rem;">🔗 Power BI Connection</h3>
      <p style="margin:0 0 6px;font-size:.85rem;color:var(--muted);">In Power BI Desktop:</p>
      <ol style="margin:0;padding-left:20px;font-size:.85rem;color:var(--muted);line-height:1.8;">
        <li>Click <strong>Get Data → Web</strong></li>
        <li>Enter: <code style="background:rgba(0,0,0,0.3);padding:2px 8px;border-radius:4px;font-size:.8rem;">http://localhost:8000/dashboard/analytics/api/?format=json&dataset=all</code></li>
        <li>Click OK — Power BI will parse all datasets as tables</li>
        <li>Or use individual dataset URLs for specific tables (see below)</li>
      </ol>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:12px;">
      <?php foreach ($datasets as $key => $ds): ?>
      <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:16px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
          <div>
            <div style="font-weight:700;font-size:.95rem;"><?= e($ds['icon']) ?> <?= e($ds['name']) ?></div>
            <div style="font-size:.75rem;color:var(--muted);margin-top:2px;"><?= e($ds['desc']) ?></div>
          </div>
          <div style="display:flex;gap:4px;">
            <a href="/dashboard/analytics/api/?format=json&dataset=<?= e($key) ?>" target="_blank" class="btn btn-ghost" style="padding:4px 10px;font-size:.7rem;">JSON</a>
            <a href="/dashboard/analytics/api/?format=csv&dataset=<?= e($key) ?>" class="btn btn-ghost" style="padding:4px 10px;font-size:.7rem;">CSV</a>
          </div>
        </div>
        <div style="font-size:.7rem;background:rgba(0,0,0,0.2);padding:6px 10px;border-radius:6px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          api/?format=json&dataset=<?= e($key) ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- All Data JSON Link -->
    <div style="margin-top:24px;padding:16px;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.2);border-radius:12px;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-weight:700;font-size:.95rem;">📊 All Data (Power BI Recommended)</div>
          <div style="font-size:.75rem;color:var(--muted);">Single JSON with all datasets — Power BI auto-detects each table</div>
        </div>
        <a href="/dashboard/analytics/api/?format=json&dataset=all" target="_blank" class="btn btn-primary" style="padding:8px 18px;font-size:.8rem;">🌐 Open JSON</a>
      </div>
      <div style="margin-top:8px;font-size:.75rem;background:rgba(0,0,0,0.2);padding:8px 12px;border-radius:6px;color:var(--muted);word-break:break-all;">
        http://localhost:8000/dashboard/analytics/api/?format=json&dataset=all
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
