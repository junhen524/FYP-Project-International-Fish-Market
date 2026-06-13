<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$message = $_GET['message'] ?? $_SESSION['_dashboard_msg'] ?? '';
$error = $_GET['error'] ?? '';
unset($_SESSION['_dashboard_msg']);

$total_export_orders = 0;
$total_export_revenue = 0;
$total_intl_users = 0;
$total_restaurants = 0;
$export_by_stage = [];
$intl_by_country = [];
$restaurant_discount_percent = 0;

try {
    // Load discount from first active restaurant (they all share the same)
    $discountRow = dbGetValue("SELECT MAX(discount_percent) FROM export_restaurant_user WHERE is_active = 1");
    $restaurant_discount_percent = (float)($discountRow ?: 0);
} catch (Exception $e) {}

// Handle POST - save discount to all restaurants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restaurant_discount_percent'])) {
    $val = (float)$_POST['restaurant_discount_percent'];
    if ($val < 0) $val = 0;
    if ($val > 80) $val = 80;
    try {
        dbExecute("UPDATE export_restaurant_user SET discount_percent = ? WHERE is_active = 1", [$val]);
        $restaurant_discount_percent = $val;
        $_SESSION['_dashboard_msg'] = 'Restaurant discount set to ' . number_format($val, 1) . '%.';
        header('Location: ' . ($_SERVER['REDIRECT_URL'] ?? '/dashboard/analytics/international/'));
        exit;
    } catch (Exception $e) {
        $error = 'Error saving discount: ' . $e->getMessage();
    }
}

try {
    $total_export_orders = (int)dbGetValue("SELECT COUNT(*) FROM export_orders");
    $total_export_revenue = (float)dbGetValue("SELECT COALESCE(SUM(total_amount), 0) FROM export_orders WHERE stage NOT IN ('cancelled')");
    $total_intl_users = (int)dbGetValue("SELECT COUNT(*) FROM export_user WHERE account_status = 'active'");
    $total_restaurants = (int)dbGetValue("SELECT COUNT(*) FROM export_restaurant_user WHERE is_active = 1");
    $export_by_stage = dbGetAll("SELECT stage, COUNT(*) as cnt FROM export_orders GROUP BY stage ORDER BY stage");
    $intl_by_country = dbGetAll("SELECT destination_country, COUNT(*) as cnt FROM export_orders GROUP BY destination_country ORDER BY cnt DESC LIMIT 10");
} catch (Exception $e) {
}

$title = 'International Dashboard';
$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>';
require __DIR__ . '/../helpers/header.php';
?>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🌍 International</div>
    <a class="dash-sidebar-item active" href="/dashboard/analytics/international/">📊 Dashboard</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/orders/">📋 Orders</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/users/">👥 Users</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/restaurants/">🏪 Restaurants</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/topup/">💰 Top-Up</a>
    <a class="dash-sidebar-item" href="/logistics/international/">🚢 Export Logistics</a>
    <a class="dash-sidebar-item" href="/logistics/export_driver/">👤 Intl. Drivers</a>
  </nav>

  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <h2 style="margin:0;">🌍 International Dashboard</h2>
      <p class="subtle" style="margin:4px 0 0;">Export market analytics — international orders, restaurants, contracts</p>
    </div>

    <?php if ($message): ?><div class="topup-msg" style="padding:12px 16px;border-radius:10px;background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.3);color:#34d399;margin-bottom:16px;font-size:13px;"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="topup-msg" style="padding:12px 16px;border-radius:10px;background:rgba(251,113,133,0.12);border:1px solid rgba(251,113,133,0.3);color:#fb7185;margin-bottom:16px;font-size:13px;"><?= e($error) ?></div><?php endif; ?>
    <script>
    (function(){ var els=document.querySelectorAll('.topup-msg'); if(els.length) els.forEach(function(el){ setTimeout(function(){ el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(function(){ el.style.display='none'; },500); },3000); }); })();
    </script>

    <div class="dash-grid" style="grid-template-columns:repeat(4,1fr);">
      <div class="dash-card"><div class="dash-icon" style="background:rgba(45,212,191,0.12);">🚢</div><div><div class="dash-label">Export Orders</div><div class="dash-value"><?= e($total_export_orders) ?></div></div></div>
      <div class="dash-card"><div class="dash-icon" style="background:rgba(96,165,250,0.12);">💰</div><div><div class="dash-label">Export Revenue</div><div class="dash-value">USD<?= number_format($total_export_revenue, 0) ?></div></div></div>
      <div class="dash-card"><div class="dash-icon" style="background:rgba(52,211,153,0.12);">👥</div><div><div class="dash-label">Intl. Users</div><div class="dash-value"><?= e($total_intl_users) ?></div></div></div>
      <div class="dash-card"><div class="dash-icon" style="background:rgba(253,186,116,0.12);">🏪</div><div><div class="dash-label">Restaurants</div><div class="dash-value"><?= e($total_restaurants) ?></div></div></div>
    </div>

    <div class="card" style="margin-bottom:16px;">
      <form method="post" style="display:flex;align-items:end;gap:16px;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;">
          <h3 style="margin:0 0 6px;font-size:16px;">Restaurant Partner Pricing</h3>
          <p class="subtle" style="margin:0;">Discount applied when InternationalMarket users log in as restaurant partners.</p>
        </div>
        <div>
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Discount %</label>
          <input type="number" name="restaurant_discount_percent" value="<?= e(number_format($restaurant_discount_percent, 1)) ?>" min="0" max="80" step="0.1" style="padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:rgba(11,18,32,0.35);color:var(--text);font-size:14px;width:120px;font-weight:700;">
        </div>
        <button type="submit" class="btn btn-primary" style="padding:10px 18px;">Save Discount</button>
      </form>
    </div>

    <div class="charts-row" style="grid-template-columns:2fr 1fr;">
      <div class="chart-panel">
        <p class="panel-title">📊 Export Orders by Stage</p>
        <p class="panel-sub">Pipeline distribution</p>
        <canvas id="stageChart" height="160"></canvas>
        <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:12px;justify-content:center;">
          <?php foreach ($export_by_stage as $s): ?>
          <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
            <span style="width:10px;height:10px;border-radius:50%;display:inline-block;background:rgba(45,212,191,0.7);"></span>
            <span style="text-transform:capitalize;"><?= e($s['stage'] ?? '') ?></span>
            <span style="font-weight:700;"><?= e($s['cnt'] ?? 0) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="chart-panel">
        <p class="panel-title">🌎 Restaurants by Country</p>
        <p class="panel-sub">Geographic distribution</p>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <?php foreach ($intl_by_country as $c): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
            <span style="font-size:13px;color:var(--muted);"><?= e($c['destination_country'] ?? '') ?></span>
            <span style="font-weight:800;"><?= e($c['cnt'] ?? 0) ?></span>
          </div>
          <?php endforeach; ?>
          <?php if (empty($intl_by_country)): ?><div style="text-align:center;padding:30px;color:var(--muted);">No restaurants registered yet.</div><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div style="display:flex;align-items:center;gap:16px;">
        <span style="font-size:32px;">🚀</span>
        <div>
          <strong style="font-size:16px;">International Market — Ready for Growth</strong>
          <p class="subtle" style="margin:4px 0 0;">Set up export pricing, register international users, and manage bulk orders with smart contracts.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
Chart.defaults.color = 'rgba(238,242,255,0.55)';
Chart.defaults.borderColor = 'rgba(255,255,255,0.07)';
const stageLabels = <?= json_encode(array_column($export_by_stage, 'stage')) ?>;
const stageData = <?= json_encode(array_map(function($s) { return (int)($s['cnt'] ?? 0); }, $export_by_stage)) ?>;
new Chart(document.getElementById('stageChart'), {
  type: 'bar',
  data: {
    labels: stageLabels,
    datasets: [{ label: 'Orders', data: stageData, backgroundColor: ['rgba(45,212,191,0.7)','rgba(96,165,250,0.7)','rgba(52,211,153,0.7)','rgba(253,186,116,0.7)','rgba(99,102,241,0.7)'], borderRadius: 6 }]
  },
  options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { stepSize: 1 } } } }
});
</script>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
