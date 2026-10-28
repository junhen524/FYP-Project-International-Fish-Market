<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$stage_filter = $_GET['stage'] ?? '';
$country_filter = $_GET['country'] ?? '';
$search_q = $_GET['q'] ?? '';

$all_orders = [];
$stageCounts = [];
$totalOrders = 0;
$countries = [];

try {
    $stageCounts = dbGetAll("SELECT stage as s, COUNT(*) as c FROM export_orders GROUP BY stage ORDER BY FIELD(stage,'confirmed','processing','shipping','delivery','delivered','rejected','cancelled')");
    $totalOrders = (int)dbGetValue("SELECT COUNT(*) FROM export_orders");
    $countries = dbGetAll("SELECT DISTINCT destination_country FROM export_orders WHERE destination_country IS NOT NULL ORDER BY destination_country");

    $sql = "SELECT id, order_number, stage, total_amount, currency, destination_country, shipping_terms, notes, items, created_at, ordered_at, delivery_qr_code, delivery_pin, delivery_qr_used FROM export_orders";
    $params = [];
    $wheres = [];
    if ($stage_filter) { $wheres[] = "stage = ?"; $params[] = $stage_filter; }
    if ($country_filter) { $wheres[] = "destination_country = ?"; $params[] = $country_filter; }
    if ($search_q) { $wheres[] = "(LOWER(order_number) LIKE LOWER(?) OR LOWER(destination_country) LIKE LOWER(?))"; $params[] = "%$search_q%"; $params[] = "%$search_q%"; }
    if ($wheres) $sql .= " WHERE " . implode(' AND ', $wheres);
    $sql .= " ORDER BY created_at DESC LIMIT 100";
    $all_orders = dbGetAll($sql, $params);
} catch (Exception $e) {}

$title = 'International Orders';
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
    <a class="dash-sidebar-item active" href="/dashboard/analytics/international/orders/">📋 Orders</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/users/">👥 Users</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/restaurants/">🏪 Restaurants</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/topup/">💰 Top-Up</a>
    <a class="dash-sidebar-item" href="/logistics/international/">🚢 Export Logistics</a>
    <a class="dash-sidebar-item" href="/logistics/export_driver/">👤 Intl. Drivers</a>
  </nav>

  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
          <h2 style="margin:0;">📋 International Orders</h2>
          <p class="subtle" style="margin:4px 0 0;">Manage export orders — filter by stage and destination country</p>
        </div>
        <form method="get" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
          <select name="country" onchange="this.form.submit()" class="form-control" style="width:auto;display:inline-block;padding:8px 12px;font-size:12px;font-weight:600;">
            <option value="">🌍 All Countries</option>
            <?php foreach ($countries as $c): ?>
            <option value="<?= e($c['destination_country']) ?>" <?= $country_filter === $c['destination_country'] ? 'selected' : '' ?>><?= e($c['destination_country']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="search" name="q" placeholder="Search orders..." value="<?= e($search_q) ?>" class="form-control" style="width:160px;">
          <button type="submit" class="btn btn-ghost" style="padding:8px 14px;font-size:12px;font-weight:700;">🔍</button>
          <?php if ($stage_filter || $country_filter || $search_q): ?>
          <a href="/dashboard/analytics/international/orders/" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
      <a href="/dashboard/analytics/international/orders/" class="filter-tag <?= !$stage_filter ? 'active' : '' ?>">All (<?= $totalOrders ?>)</a>
      <?php
      // Build a count map from DB results
      $countMap = [];
      foreach ($stageCounts as $sc) $countMap[$sc['s']] = (int)$sc['c'];
      $stageOrder = ['confirmed','processing','shipping','delivery','delivered','rejected','cancelled'];
      $stageLabel = ['confirmed'=>'Confirmed','processing'=>'Processing','shipping'=>'Shipping','delivery'=>'Delivery','delivered'=>'Confirmed Delivered','rejected'=>'Rejected','cancelled'=>'Cancelled'];
      foreach ($stageOrder as $s): $cnt = $countMap[$s] ?? 0; ?>
      <a href="?stage=<?= urlencode($s) ?><?= $country_filter ? '&country='.urlencode($country_filter) : '' ?>" class="filter-tag <?= $stage_filter === $s ? 'active' : '' ?>"><?= e($stageLabel[$s] ?? ucfirst(str_replace('_', ' ', $s))) ?> (<?= $cnt ?>)</a>
      <?php endforeach; ?>
    </div>

    <div class="card" style="overflow-x:auto;">
      <table class="activity-table">
        <thead><tr><th>Order #</th><th>Destination</th><th>Amount</th><th>Stage</th><th>Date</th><th style="min-width:200px;">Items</th></tr></thead>
        <tbody>
          <?php foreach ($all_orders as $o):
            $oItems = json_decode($o['items'] ?? '[]', true);
          ?>
          <tr style="cursor:pointer;" onclick="openOrderDetail(<?= (int)$o['id'] ?>)">
            <td><strong><?= e($o['order_number'] ?? '—') ?></strong></td>
            <td style="color:var(--muted);"><?= e($o['destination_country'] ?? '—') ?></td>
            <td style="font-weight:700;">USD<?= number_format($o['total_amount'] ?? 0, 2) ?></td>
            <td><span class="status-badge badge-<?= e($o['stage'] ?? '') ?>"><?php $__s = $o['stage'] ?? ''; $__label = ['confirmed'=>'Confirmed','processing'=>'Processing','shipping'=>'Shipping','delivery'=>'Delivery','delivered'=>'Confirmed Delivered','rejected'=>'Rejected','cancelled'=>'Cancelled']; echo e($__label[$__s] ?? ucfirst(str_replace('_', ' ', $__s))); ?></span></td>
            <td style="color:var(--muted);font-size:12px;"><?= e(substr($o['created_at'] ?? '', 0, 10)) ?></td>
            <td style="font-size:12px;color:var(--text);">
              <?php if ($oItems): ?>
                <?php foreach (array_slice($oItems, 0, 2) as $i): ?>
                  <div>• <?= e($i['product_name'] ?? '') ?><?= !empty($i['tier_label']) ? ' <span style="color:var(--muted)">(' . e($i['tier_label']) . ')</span>' : '' ?></div>
                <?php endforeach; ?>
                <?php if (count($oItems) > 2): ?><div style="color:var(--muted);font-size:10px;">+ <?= count($oItems) - 2 ?> more</div><?php endif; ?>
              <?php else: ?>
                <span style="color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($all_orders)): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);">No orders found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Order Detail Modal -->
<div id="order-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(6px);overflow-y:auto;padding:60px 20px" onclick="if(event.target===this)closeOrderDetail()">
  <div style="max-width:560px;margin:0 auto;background:var(--card);border-radius:20px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,0.4);border:1px solid var(--border)">
    <div style="padding:28px" id="order-modal-content">
      <!-- Populated by JS -->
    </div>
  </div>
</div>

<script>
var __orders = <?= json_encode(array_map(function($o) {
    return [
        'id' => (int)$o['id'],
        'order_number' => $o['order_number'],
        'stage' => $o['stage'],
        'total_amount' => (float)$o['total_amount'],
        'currency' => $o['currency'] ?? 'USD',
        'destination_country' => $o['destination_country'] ?? '',
        'shipping_terms' => $o['shipping_terms'] ?? 'FOB',
        'notes' => $o['notes'] ?? '',
        'created_at' => $o['created_at'],
        'ordered_at' => $o['ordered_at'] ?? $o['created_at'],
        'items' => json_decode($o['items'] ?? '[]', true),
        'delivery_qr_code' => $o['delivery_qr_code'] ?? null,
        'delivery_pin' => $o['delivery_pin'] ?? null,
    ];
}, $all_orders)) ?>;

function stageIcon(s) {
  var m = {'confirmed':'📋','processing':'⚙️','paid':'💳','shipping':'🚢','delivery':'🚚','delivered':'✅','rejected':'❌','cancelled':'❌'};
  return m[s] || '📦';
}
function stageLabel(s) {
  var m = {'confirmed':'Confirmed','processing':'Processing','shipping':'Shipping','delivery':'Delivery','delivered':'Confirmed Delivered','rejected':'Rejected','cancelled':'Cancelled'};
  return m[s] || s.charAt(0).toUpperCase() + s.slice(1);
}
function stageClass(s) {
  var m = {'confirmed':'badge-confirmed','processing':'badge-processing','shipping':'badge-shipping','delivery':'badge-delivery','delivered':'badge-delivered','rejected':'badge-rejected','cancelled':'badge-cancelled'};
  return m[s] || '';
}

function openOrderDetail(id) {
  var o = __orders.find(function(x) { return x.id === id; });
  if (!o) return;
  var itemsHtml = '';
  (o.items || []).forEach(function(i) {
    itemsHtml += '<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">' +
      '<div><div style="font-weight:700;font-size:13px;color:var(--text)">' + escHtml(i.product_name || '') + '</div>' +
      '<div style="font-size:10px;color:var(--muted);font-family:monospace;margin-top:2px">' +
      '$' + (i.unit_price || 0).toFixed(2) + ' × ' + (i.quantity || 0) +
      (i.tier_label ? ' <span style="color:#f59e0b">(' + escHtml(i.tier_label) + ')</span>' : '') +
      '</div></div>' +
      '<div style="font-weight:800;font-size:14px;color:var(--text)">$' + (i.subtotal || 0).toFixed(2) + '</div></div>';
  });
  var html =
    '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">' +
      '<div>' +
        '<div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-weight:700">Order #' + escHtml(o.order_number) + '</div>' +
        '<div style="font-size:10px;color:var(--muted);font-family:monospace;margin-top:2px">' + escHtml(o.created_at) + '</div>' +
      '</div>' +
      '<button onclick="closeOrderDetail()" style="background:none;border:none;cursor:pointer;font-size:22px;color:var(--muted);padding:0 4px">&times;</button>' +
    '</div>' +
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;padding:14px 16px;background:var(--bg);border-radius:12px;border:1px solid var(--border)">' +
      '<span class="status-badge ' + stageClass(o.stage) + '" style="font-size:10px;font-weight:700;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px">' + stageIcon(o.stage) + ' ' + stageLabel(o.stage) + '</span>' +
      '<span style="margin-left:auto;font-weight:900;font-size:22px;color:var(--text)">$' + o.total_amount.toFixed(2) + '</span>' +
    '</div>' +
    '<div style="margin-bottom:20px">' +
      '<h4 style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:10px">🛒 Items</h4>' +
      itemsHtml +
    '</div>' +
    '<div style="background:var(--bg);border-radius:12px;padding:16px;margin-bottom:20px;border:1px solid var(--border)">' +
      '<h4 style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:10px">🚢 Shipping</h4>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
        '<div><span style="color:var(--muted);font-size:10px;">Country</span><br><span style="font-weight:600;color:var(--text)">' + escHtml(o.destination_country || 'N/A') + '</span></div>' +
        '<div><span style="color:var(--muted);font-size:10px;">Terms</span><br><span style="font-weight:600;color:var(--text)">' + escHtml(o.shipping_terms || 'FOB') + '</span></div>' +
      '</div>' +
      (o.notes ? '<div style="margin-top:8px;padding-top:10px;border-top:1px solid var(--border)"><span style="color:var(--muted);font-size:10px;">Notes</span><br><span style="font-weight:500;color:var(--muted);font-size:12px;">' + escHtml(o.notes) + '</span></div>' : '') +
    '</div>' +
    '<div style="display:flex;justify-content:space-between;align-items:center;padding-top:16px;border-top:1px solid var(--border)">' +
      '<div style="font-size:10px;color:var(--muted)">Ordered: ' + escHtml(o.ordered_at) + '</div>' +
      '<button onclick="closeOrderDetail()" style="padding:8px 24px;background:var(--brand);color:#081225;border:none;border-radius:10px;font-size:11px;font-weight:800;cursor:pointer">Close</button>' +
    '</div>';
  document.getElementById('order-modal-content').innerHTML = html;
  document.getElementById('order-modal').style.display = 'block';
  document.body.style.overflow = 'hidden';
}
function closeOrderDetail() {
  document.getElementById('order-modal').style.display = 'none';
  document.body.style.overflow = '';
}
function escHtml(s) {
  if (!s) return '';
  var d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}
</script>

<?php require __DIR__ . '/../helpers/footer.php'; ?>
