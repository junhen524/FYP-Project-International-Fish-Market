<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$total_products = 0; $total_orders = 0; $total_revenue = 0; $total_customers = 0; $low_stock_count = 0;
$top_products = []; $recent_orders = []; $orders_by_status = []; $monthly_rev = [];
try {
    $total_products = (int)dbGetValue("SELECT COUNT(*) FROM product WHERE is_active = TRUE");
    $total_orders = (int)dbGetValue("SELECT COUNT(*) FROM market_order");
    $total_revenue = (float)dbGetValue("SELECT COALESCE(SUM(total_amount), 0) FROM market_order WHERE status NOT IN ('cancelled', 'refunded')");
    $total_customers = (int)dbGetValue("SELECT COUNT(*) FROM market_user WHERE account_status = 'active'");
    $low_stock_count = (int)dbGetValue("SELECT COUNT(*) FROM product WHERE is_active = TRUE AND (COALESCE(tier_3kg_stock,0)+COALESCE(tier_6kg_stock,0)+COALESCE(tier_10kg_stock,0)) < 10");
    $recent_orders = dbGetAll("SELECT d.id, d.order_number, d.status, d.total_amount, u.username,
            s.shipment_number, s.driver_id
            FROM market_order d
            JOIN market_user u ON u.id = d.user_id
            LEFT JOIN market_shipments s ON s.order_id = d.id
            ORDER BY d.created_at DESC LIMIT 10");
    $orders_by_status = dbGetAll("SELECT status, COUNT(*) as cnt FROM market_order GROUP BY status ORDER BY status");
    $monthly_rev = dbGetAll("SELECT DATE_FORMAT(created_at, '%Y-%m') as mon, COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as rev FROM market_order WHERE created_at >= NOW() - INTERVAL 6 MONTH GROUP BY mon ORDER BY mon");

    // Top products: items stored as JSON in market_order.items
    $allOrderRows = dbGetAll("SELECT items FROM market_order WHERE items IS NOT NULL AND items != '' AND items != '[]'");
    $agg = [];
    foreach ($allOrderRows as $row) {
        $items = json_decode($row['items'], true) ?: [];
        foreach ($items as $item) {
            $name = $item['product_name'] ?? 'Unknown';
            $qty = (int)($item['qty'] ?? 0);
            $rev = (float)($item['subtotal'] ?? 0);
            if (!isset($agg[$name])) $agg[$name] = ['name' => $name, 'qty' => 0, 'rev' => 0.0];
            $agg[$name]['qty'] += $qty;
            $agg[$name]['rev'] += $rev;
        }
    }
    usort($agg, fn($a, $b) => $b['rev'] <=> $a['rev']);
    $top_products = array_slice($agg, 0, 5);
    $drivers = dbGetAll("SELECT id, name, vehicle_no FROM market_drivers WHERE is_active = TRUE");
    $driverMap = [];
    foreach ($drivers as $d) $driverMap[$d['id']] = $d;
} catch (Exception $e) {}

$title = 'Fish Market Dashboard';
$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>';
require __DIR__ . '/../helpers/header.php';
?>
<div class="dash-layout">
<?php $sidebarActive = 'dashboard'; require __DIR__ . '/../helpers/sidebar_market.php'; ?>

  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <h2 style="margin:0;">🐟 Fish Market Dashboard</h2>
      <p class="subtle" style="margin:4px 0 0;">Domestic market analytics — products, orders, revenue</p>
    </div>

    <div class="dash-grid" style="grid-template-columns:repeat(4,1fr);">
      <div class="dash-card"><div class="dash-icon" style="background:rgba(45,212,191,0.12);">📦</div><div><div class="dash-label">Products</div><div class="dash-value"><?= e($total_products) ?></div></div></div>
      <div class="dash-card"><div class="dash-icon" style="background:rgba(96,165,250,0.12);">🛒</div><div><div class="dash-label">Orders</div><div class="dash-value"><?= e($total_orders) ?></div></div></div>
      <div class="dash-card"><div class="dash-icon" style="background:rgba(52,211,153,0.12);">💰</div><div><div class="dash-label">Revenue</div><div class="dash-value">RM<?= number_format($total_revenue, 0) ?></div></div></div>
      <div class="dash-card"><div class="dash-icon" style="background:rgba(253,186,116,0.12);">👥</div><div><div class="dash-label">Customers</div><div class="dash-value"><?= e($total_customers) ?></div></div></div>
    </div>

    <div class="charts-row" style="grid-template-columns:1fr 1fr;">
      <div class="chart-panel">
        <p class="panel-title">📈 Monthly Revenue</p>
        <p class="panel-sub">Last 6 months</p>
        <canvas id="revChart" height="120"></canvas>
      </div>
      <div class="chart-panel">
        <p class="panel-title">📊 Orders by Status</p>
        <p class="panel-sub">Current distribution</p>
        <div style="display:flex;align-items:center;gap:24px;">
          <div style="max-width:160px;flex-shrink:0;"><canvas id="statusChart" height="160"></canvas></div>
          <div style="flex:1;display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($orders_by_status as $s): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;">
              <span style="color:var(--muted);display:flex;align-items:center;gap:8px;text-transform:capitalize;"><?= e($s['status'] ?? '') ?></span>
              <span style="font-weight:800;"><?= e($s['cnt'] ?? 0) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="bottom-row">
      <div class="chart-panel">
        <p class="panel-title">🏆 Top Selling Products</p>
        <p class="panel-sub">By revenue</p>
        <div class="table-scroll">
          <table class="activity-table">
            <thead><tr><th>#</th><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
            <tbody>
              <?php $i = 1; foreach ($top_products as $p): ?>
              <tr>
                <td style="color:var(--muted);"><?= $i++ ?></td>
                <td><strong><?= e($p['name'] ?? '') ?></strong></td>
                <td style="color:var(--muted);"><?= e($p['qty'] ?? 0) ?></td>
                <td style="font-weight:700;color:var(--brand);">RM<?= number_format($p['rev'] ?? 0, 0) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($top_products)): ?><tr><td colspan="4" style="text-align:center;padding:30px;color:var(--muted);">No sales yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="chart-panel">
        <p class="panel-title">🛒 Recent Orders</p>
        <p class="panel-sub">Latest domestic orders</p>
        <div class="table-scroll">
          <table class="activity-table" style="font-size:12px;">
            <thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Shipment</th><th>Driver</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recent_orders as $o):
                $d = $o['driver_id'] ? ($driverMap[$o['driver_id']] ?? null) : null;
              ?>
              <tr>
                <td><strong><?= e($o['order_number'] ?? '') ?></strong></td>
                <td style="color:var(--muted);"><?= e($o['username'] ?? '') ?></td>
                <td>RM<?= number_format($o['total_amount'] ?? 0, 2) ?></td>
                <td><?= $o['shipment_number'] ? '<span style="font-size:11px;background:rgba(45,212,191,0.12);color:#2dd4bf;padding:2px 6px;border-radius:4px;font-weight:700;">'.e($o['shipment_number']).'</span>' : '<span style="color:var(--muted);font-size:11px;">—</span>' ?></td>
                <td style="font-size:12px;"><?= $d ? e($d['name']).' <span style="color:var(--muted);font-size:10px;">('.e($d['vehicle_no']).')</span>' : '<span style="color:var(--muted);font-size:11px;">—</span>' ?></td>
                <td><span class="status-badge badge-<?= e($o['status'] ?? '') ?>"><?= e($o['status'] ?? '') ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($recent_orders)): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted);">No orders yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
Chart.defaults.color = 'rgba(238,242,255,0.55)';
Chart.defaults.borderColor = 'rgba(255,255,255,0.07)';
Chart.defaults.font.family = "ui-sans-serif, system-ui, sans-serif";

const revLabels = <?= json_encode(array_column($monthly_rev, 'mon')) ?>;
const revData = <?= json_encode(array_map(function($r) { return (float)($r['rev'] ?? 0); }, $monthly_rev)) ?>;
new Chart(document.getElementById('revChart'), {
  type: 'bar',
  data: { labels: revLabels, datasets: [{ label: 'Revenue', data: revData, backgroundColor: 'rgba(45,212,191,0.6)', borderRadius: 6 }] },
  options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: v => 'RM'+v.toLocaleString() } } } }
});

const statusLabels = <?= json_encode(array_column($orders_by_status, 'status')) ?>;
const statusData = <?= json_encode(array_map(function($s) { return (int)($s['cnt'] ?? 0); }, $orders_by_status)) ?>;
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: ['rgba(45,212,191,0.8)','rgba(96,165,250,0.8)','rgba(52,211,153,0.8)','rgba(253,186,116,0.8)','rgba(99,102,241,0.8)'], borderWidth: 0 }] },
  options: { cutout: '60%', plugins: { legend: { display: false } } }
});
</script>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
