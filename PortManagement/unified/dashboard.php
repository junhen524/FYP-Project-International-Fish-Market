<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$period = $_GET['period'] ?? '6m';
$months = [ '1m'=>1, '3m'=>3, '6m'=>6, '1y'=>12 ][$period] ?? 6;

// ─── ALL REAL DATA ───
$total_ports = (int)dbGetValue("SELECT COUNT(*) FROM ports");
$total_products = (int)dbGetValue("SELECT COUNT(*) FROM product WHERE is_active=TRUE");
$total_orders = (int)dbGetValue("SELECT COUNT(*) FROM market_order");
$total_revenue = (float)dbGetValue("SELECT COALESCE(SUM(total_amount),0) FROM market_order WHERE status NOT IN ('cancelled','refunded')");
$total_users = (int)dbGetValue("SELECT COUNT(*) FROM market_user WHERE account_status='active'");
$active_vessels = (int)dbGetValue("SELECT COUNT(*) FROM vessels WHERE status='at_sea'");
$total_export_orders = (int)dbGetValue("SELECT COUNT(*) FROM export_orders");
$total_drivers = (int)dbGetValue("SELECT COUNT(*) FROM market_drivers WHERE is_active=TRUE");

$rev_chart = dbGetAll("SELECT DATE_FORMAT(created_at,'%Y-%m') as mon, COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as rev FROM market_order WHERE created_at>=NOW()-INTERVAL ? MONTH GROUP BY mon ORDER BY mon", [$months]);
$order_status_chart = dbGetAll("SELECT status as s, COUNT(*) as c FROM market_order GROUP BY status ORDER BY c DESC");
$opg = max(1, (int)($_GET['opg'] ?? 1));
$perPage = 6;
$totalOrders = (int)dbGetValue("SELECT COUNT(*) FROM market_order");
$totalOpages = max(1, (int)ceil($totalOrders / $perPage));
$oOffset = ($opg - 1) * $perPage;
$orders = dbGetAll("SELECT mo.*, mu.username FROM market_order mo LEFT JOIN market_user mu ON mu.id=mo.user_id ORDER BY mo.created_at DESC LIMIT $perPage OFFSET $oOffset");
$title = 'Unified Dashboard';
$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>';
require __DIR__ . '/../helpers/header.php';
?>
<style>
:root { --radius: 14px; --glow: 0 4px 24px rgba(0,0,0,0.2); }
.dash-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.dash-card {
  background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); border-radius: var(--radius);
  padding: 20px; display: flex; align-items: center; gap: 16px; transition: .25s;
}
.dash-card:hover { border-color: rgba(255,255,255,0.18); transform: translateY(-3px); box-shadow: var(--glow); }
.dash-card .icon-wrap { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.dash-card .label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); margin-bottom: 3px; }
.dash-card .value { font-size: 28px; font-weight: 800; letter-spacing: -0.3px; }
.charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 24px; }
.chart-panel {
  background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); border-radius: var(--radius);
  padding: 24px; transition: .2s;
}
.chart-panel:hover { border-color: rgba(255,255,255,0.16); }
.panel-title { font-size: 15px; font-weight: 700; margin: 0 0 3px; }
.panel-sub { font-size: 12px; color: var(--muted); margin: 0 0 20px; }
.bottom-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
.insight-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
.insight-item { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 14px 16px; text-align: center; }
.insight-item .num { font-size: 22px; font-weight: 800; }
.insight-item .lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin-top: 3px; }
.activity-table { width: 100%; border-collapse: collapse; }
.activity-table th {
  text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--muted); padding: 0 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.08);
}
.activity-table td { padding: 10px 8px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.04); }
.activity-table tr:last-child td { border-bottom: none; }
.activity-table tr:hover td { background: rgba(255,255,255,0.03); }
.status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-ok { background: rgba(52,211,153,0.15); color: #34d399; }
.badge-warn { background: rgba(253,186,116,0.15); color: #fbbf24; }
.badge-idle { background: rgba(255,255,255,0.06); color: var(--muted); }
.badge-delivered { background: rgba(52,211,153,0.15); color: #34d399; }
.badge-shipping { background: rgba(96,165,250,0.15); color: #60a5fa; }
.badge-confirmed { background: rgba(253,186,116,0.15); color: #fbbf24; }
.badge-processing { background: rgba(99,102,241,0.15); color: #818cf8; }
.badge-pending { background: rgba(255,255,255,0.06); color: var(--muted); }
.data-table { width: 100%; border-collapse: collapse; font-size: .82rem; margin-top: 12px; }
.data-table th { text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); padding: 8px 10px 10px; border-bottom: 1px solid rgba(255,255,255,0.06); }
.data-table td { padding: 8px 10px; border-bottom: 1px solid rgba(255,255,255,0.03); }
.data-table tr:hover td { background: rgba(255,255,255,0.02); }
.badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; }
.bg-blue { background: rgba(96,165,250,0.15); color: #60a5fa; }
.bg-green { background: rgba(52,211,153,0.15); color: #34d399; }
.bg-amber { background: rgba(253,186,116,0.15); color: #fbbf24; }
.bg-purple { background: rgba(153,102,255,0.15); color: #9966ff; }
.bg-red { background: rgba(239,68,68,0.15); color: #ef4444; }
.bg-gray { background: rgba(255,255,255,0.06); color: var(--muted); }
</style>
<div class="dash-content" style="max-width:100%;">
  <div class="card" style="margin-bottom:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      <div>
        <h2 style="margin:0;">📊 Unified Analytics</h2>
        <p class="subtle" style="margin:4px 0 0;">Cross-system overview — Port Management · Fish Market · International</p>
      </div>
      <form method="GET" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <select name="period" onchange="this.form.submit()" style="padding:6px 12px;border-radius:8px;background:rgba(255,255,255,0.09);border:1px solid rgba(255,255,255,0.12);color:var(--text);font-size:12px;font-weight:600;">
          <option value="1m" <?= $period==='1m'?'selected':'' ?>>Last 30 days</option>
          <option value="3m" <?= $period==='3m'?'selected':'' ?>>Last 3 months</option>
          <option value="6m" <?= $period==='6m'?'selected':'' ?>>Last 6 months</option>
          <option value="1y" <?= $period==='1y'?'selected':'' ?>>Last year</option>
        </select>
        <button type="submit" class="btn btn-ghost" style="padding:6px 14px;font-size:12px;font-weight:700;">Filter</button>
      </form>
    </div>
  </div>

  <div class="dash-grid">
    <div class="dash-card"><div class="icon-wrap" style="background:rgba(45,212,191,0.12);">🏭</div><div><div class="label">Ports</div><div class="value"><?= $total_ports ?></div></div></div>
    <div class="dash-card"><div class="icon-wrap" style="background:rgba(96,165,250,0.12);">📦</div><div><div class="label">Products</div><div class="value"><?= $total_products ?></div></div></div>
    <div class="dash-card"><div class="icon-wrap" style="background:rgba(52,211,153,0.12);">🛒</div><div><div class="label">Orders</div><div class="value"><?= $total_orders ?></div></div></div>
    <div class="dash-card"><div class="icon-wrap" style="background:rgba(253,186,116,0.12);">💰</div><div><div class="label">Revenue</div><div class="value">RM<?= number_format($total_revenue,0) ?></div></div></div>
    <div class="dash-card"><div class="icon-wrap" style="background:rgba(99,102,241,0.12);">👥</div><div><div class="label">Users</div><div class="value"><?= $total_users ?></div></div></div>
    <div class="dash-card"><div class="icon-wrap" style="background:rgba(52,211,153,0.12);">🚢</div><div><div class="label">Active Vessels</div><div class="value"><?= $active_vessels ?></div><div style="font-size:11px;color:var(--muted);">at sea</div></div></div>
    <div class="dash-card"><div class="icon-wrap" style="background:rgba(96,165,250,0.12);">🌍</div><div><div class="label">Export Orders</div><div class="value"><?= $total_export_orders ?></div></div></div>
    <div class="dash-card"><div class="icon-wrap" style="background:rgba(52,211,153,0.12);">🚛</div><div><div class="label">Active Drivers</div><div class="value"><?= $total_drivers ?></div></div></div>
  </div>

  <div class="charts-row">
    <div class="chart-panel">
      <p class="panel-title">📈 Revenue Trend</p>
      <p class="panel-sub">Monthly revenue — domestic orders</p>
      <canvas id="revChart" height="130"></canvas>
    </div>
    <div class="chart-panel">
      <p class="panel-title">📊 Order Status</p>
      <p class="panel-sub">Distribution of order statuses</p>
      <canvas id="statusChart" height="130"></canvas>
    </div>
  </div>

  <div class="bottom-row">
    <div class="chart-panel" style="grid-column:1/-1;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <div><p class="panel-title" style="margin:0 0 3px;">🛒 Recent Orders</p><p class="panel-sub" style="margin:0;">Latest domestic market orders</p></div>
        <a href="/dashboard/analytics/market/orders/" style="font-size:12px;font-weight:700;color:var(--brand);text-decoration:none;">View All →</a>
      </div>
      <?php if (!empty($orders)): ?>
      <table class="data-table">
        <thead><tr><th>Order #</th><th>Customer</th><th>Items</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o):
            $items = [];
            $itemsRaw = json_decode($o['items'] ?? '[]', true);
            if (is_array($itemsRaw)) foreach ($itemsRaw as $it) $items[] = $it['name'] ?? $it['product_name'] ?? 'Item';
            $itemsStr = !empty($items) ? implode(', ', array_slice($items, 0, 2)) . (count($items) > 2 ? ' +' . (count($items)-2) : '') : '—';
            $ts = strtotime($o['created_at']);
            $dateStr = date('Y-m-d', $ts) === date('Y-m-d') ? 'Today ' . date('H:i', $ts) : date('d M, H:i', $ts);
            $statusMap = ['delivered'=>'Delivered','shipping'=>'Shipping','confirmed'=>'Confirmed','processing'=>'Processing','pending'=>'Pending','cancelled'=>'Cancelled','refunded'=>'Refunded'];
            $s = $o['status'] ?? 'pending';
            $sLabel = $statusMap[$s] ?? ucfirst($s);
          ?>
          <tr style="cursor:pointer;" onclick="window.location='/dashboard/analytics/market/orders/?q=<?= urlencode($o['order_number']??'') ?>'">
            <td style="font-weight:700;font-size:12px;font-family:monospace;">#<?= e($o['order_number']??'') ?></td>
            <td style="font-weight:600;"><?= e($o['username']??'—') ?></td>
            <td style="font-size:12px;color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($itemsStr) ?></td>
            <td style="font-weight:700;">RM<?= number_format((float)($o['total_amount']??0),2) ?></td>
            <td style="font-size:12px;color:var(--muted);"><?= $dateStr ?></td>
            <td><span class="badge bg-<?= match($s){'delivered'=>'green','shipping'=>'blue','confirmed'=>'amber','processing'=>'orange','cancelled'=>'red','refunded'=>'red',default=>'gray'} ?>"><?= e($sLabel) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div style="text-align:center;padding:40px 20px;color:var(--muted);">
        <div style="font-size:32px;margin-bottom:10px;">🛒</div>
        <p style="font-weight:600;margin:0 0 4px;">No orders yet</p>
        <p style="font-size:13px;margin:0;">Orders placed by customers will appear here.</p>
      </div>
      <?php endif; ?>
      <?php if ($totalOpages > 1): ?>
      <div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:16px;padding-top:12px;border-top:1px solid var(--border);">
        <?php
          $currentPath = strtok($_SERVER['REQUEST_URI'], '?');
          $url = fn($pg) => $currentPath . '?' . http_build_query(array_merge($_GET, ['opg' => $pg])) . '#recent-orders';
        ?>
        <?php if ($opg > 1): ?><a href="<?= e($url(1)) ?>" style="padding:6px 12px;border-radius:6px;background:rgba(255,255,255,0.06);color:var(--text);text-decoration:none;font-size:12px;font-weight:700;">««</a><a href="<?= e($url($opg - 1)) ?>" style="padding:6px 12px;border-radius:6px;background:rgba(255,255,255,0.06);color:var(--text);text-decoration:none;font-size:12px;font-weight:700;">«</a><?php endif; ?>
        <span style="font-size:13px;color:var(--muted);padding:0 6px;"><?= $opg ?> / <?= $totalOpages ?></span>
        <?php if ($opg < $totalOpages): ?><a href="<?= e($url($opg + 1)) ?>" style="padding:6px 12px;border-radius:6px;background:rgba(255,255,255,0.06);color:var(--text);text-decoration:none;font-size:12px;font-weight:700;">»</a><a href="<?= e($url($totalOpages)) ?>" style="padding:6px 12px;border-radius:6px;background:rgba(255,255,255,0.06);color:var(--text);text-decoration:none;font-size:12px;font-weight:700;">»»</a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
// ─── Revenue Chart ───
var revLabels = <?= json_encode(array_column($rev_chart, 'mon')) ?>;
var revData   = <?= json_encode(array_map(fn($r)=>(float)$r['rev'], $rev_chart)) ?>;
if (revLabels.length===0) { revLabels=['No Data']; revData=[0]; }
new Chart(document.getElementById('revChart'), {
  type:'line', data:{ labels:revLabels, datasets:[{ data:revData, borderColor:'#2dd4bf',
    backgroundColor:(function(){var c=document.createElement('canvas').getContext('2d');var g=c.createLinearGradient(0,0,0,200);g.addColorStop(0,'rgba(45,212,191,0.25)');g.addColorStop(1,'rgba(45,212,191,0.01)');return g;})(),
    fill:true, tension:0.4, pointBackgroundColor:'#2dd4bf', pointBorderColor:'#081225', pointBorderWidth:2, pointRadius:4, borderWidth:2.5 }] },
  options:{ responsive:true, plugins:{ legend:{ display:false } },
    scales:{ y:{ ticks:{ callback:function(v){return'RM'+v.toLocaleString();} }, grid:{ color:'rgba(255,255,255,0.04)' } }, x:{ grid:{ display:false } } },
    interaction:{ intersect:false, mode:'index' } }
});

// ─── Order Status Doughnut ───
var ss = <?= json_encode(array_column($order_status_chart,'s')) ?>;
var sv = <?= json_encode(array_map(fn($s)=>(int)($s['c']??0), $order_status_chart)) ?>;
if (ss.length===0) { ss=['No Orders']; sv=[1]; }
var pal={'confirmed':['#fbbf24','#fde68a'],'processing':['#f97316','#fdba74'],'shipping':['#60a5fa','#93c5fd'],'delivered':['#22c55e','#86efac'],'completed':['#22c55e','#86efac'],'pending':['rgba(255,255,255,0.4)','rgba(255,255,255,0.2)'],'cancelled':['#ef4444','#fca5a5'],'refunded':['#ef4444','#fca5a5']};
var sb=ss.map(function(l){return pal[l.toLowerCase()]?pal[l.toLowerCase()][0]:'rgba(255,255,255,0.3)';});
var sr=ss.map(function(l){return pal[l.toLowerCase()]?pal[l.toLowerCase()][1]:'rgba(255,255,255,0.1)';});
new Chart(document.getElementById('statusChart'), {
  type:'doughnut', data:{ labels:ss, datasets:[{ data:sv, backgroundColor:sb, borderColor:sr, borderWidth:2, hoverOffset:8 }] },
  options:{ cutout:'62%', plugins:{ legend:{ position:'bottom', labels:{ boxWidth:14, padding:14, usePointStyle:true, pointStyle:'circle', font:{ size:11, weight:'600' } } } } },
  animation:{ animateRotate:true }
});
</script>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
