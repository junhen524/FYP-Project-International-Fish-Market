<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_q = $_GET['q'] ?? '';
$sql = "SELECT d.id, d.order_number, d.status, d.total_amount, d.items, u.username, d.created_at,
         s.shipment_number, s.driver_id
         FROM market_order d JOIN market_user u ON u.id = d.user_id
         LEFT JOIN market_shipments s ON s.order_id = d.id";
$params = [];
$wheres = [];
if ($from) { $wheres[] = "d.created_at >= ?"; $params[] = $from; }
if ($to) { $wheres[] = "d.created_at <= ?"; $params[] = $to . ' 23:59:59'; }
if ($status_filter) { $wheres[] = "d.status = ?"; $params[] = $status_filter; }
if ($search_q) { $wheres[] = "(LOWER(d.order_number) LIKE LOWER(?) OR LOWER(u.username) LIKE LOWER(?) OR LOWER(u.full_name) LIKE LOWER(?))"; $params[] = "%$search_q%"; $params[] = "%$search_q%"; $params[] = "%$search_q%"; }
if ($wheres) $sql .= " WHERE " . implode(' AND ', $wheres);
$sql .= " ORDER BY d.created_at DESC LIMIT 50";
$all_orders = dbGetAll($sql, $params);

$statusCounts = [];
$rows = dbGetAll("SELECT status, COUNT(*) as cnt FROM market_order GROUP BY status");
foreach ($rows as $r) { $statusCounts[$r['status'] ?? ''] = (int)($r['cnt'] ?? 0); }
$totalOrders = array_sum($statusCounts);

$drivers = dbGetAll("SELECT id, name, vehicle_no FROM market_drivers WHERE is_active = TRUE");
$driverMap = [];
foreach ($drivers as $d) $driverMap[$d['id']] = $d;

$title = 'Market Orders';
$extra_head = '<style>.order-main-row:hover{background:rgba(45,212,191,0.04)}.order-main-row td:first-child span{transition:transform .15s}.order-items-row td>div{animation:fadeSlideIn .2s ease}@keyframes fadeSlideIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}</style>';
require __DIR__ . '/../helpers/header.php';
?>
<div class="dash-layout">
<?php $sidebarActive = 'orders'; require __DIR__ . '/../helpers/sidebar_market.php'; ?>

  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
          <h2 style="margin:0;">📋 Fish Market Orders</h2>
          <p class="subtle" style="margin:4px 0 0;">Manage domestic orders — filter, search, and update status</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <select name="status" onchange="this.form.submit()" class="form-control" style="width:auto;display:inline-block;padding:10px 14px;font-size:13px;">
              <option value="">All Status</option>
              <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
              <option value="shipping" <?= $status_filter === 'shipping' ? 'selected' : '' ?>>Shipping</option>
              <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
              <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <input type="text" name="q" placeholder="Search order/customer..." value="<?= e($_GET['q'] ?? '') ?>" class="form-control" style="width:180px;display:inline-block;">
            <button class="btn btn-ghost" type="submit" style="padding:10px 16px;font-size:13px;font-weight:700;">🔍 Search</button>
          </form>
        </div>
      </div>
    </div>

    <div class="card" style="overflow-x:auto;">
      <table class="activity-table" id="orders-table">
        <thead>
          <tr><th style="width:32px"></th><th>Order #</th><th>Customer</th><th>Amount</th><th>Shipment #</th><th>Driver</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php foreach ($all_orders as $o):
            $d = $o['driver_id'] ? ($driverMap[$o['driver_id']] ?? null) : null;
            $items = $o['items'] ? json_decode($o['items'], true) : [];
          ?>
          <tr class="order-main-row" onclick="toggleOrderItems(<?= (int)$o['id'] ?>)" style="cursor:pointer;">
            <td style="text-align:center;"><span id="icon-<?= (int)$o['id'] ?>" style="font-size:11px;color:var(--muted);transition:transform .15s;">▶</span></td>
            <td><strong><?= e($o['order_number'] ?? '') ?></strong></td>
            <td style="color:var(--muted);"><?= e($o['username'] ?? '') ?></td>
            <td style="font-weight:700;">RM<?= number_format($o['total_amount'] ?? 0, 2) ?></td>
            <td><?= $o['shipment_number'] ? '<span style="font-size:11px;background:rgba(45,212,191,0.12);color:#2dd4bf;padding:2px 6px;border-radius:4px;font-weight:700;">'.e($o['shipment_number']).'</span>' : '<span style="color:var(--muted);font-size:11px;">—</span>' ?></td>
            <td style="font-size:12px;"><?= $d ? e($d['name']).' <span style="color:var(--muted);font-size:10px;">('.e($d['vehicle_no']).')</span>' : '<span style="color:var(--muted);font-size:11px;">—</span>' ?></td>
            <td><span class="status-badge badge-<?= e($o['status'] ?? '') ?>"><?= e($o['status'] ?? '') ?></span></td>
            <td style="color:var(--muted);font-size:12px;"><?= e($o['created_at'] ?? '') ?></td>
          </tr>
          <tr id="items-<?= (int)$o['id'] ?>" class="order-items-row" style="display:none;">
            <td colspan="8" style="padding:0;">
              <div style="padding:12px 16px 14px 48px;background:var(--bg-alt,rgba(0,0,0,0.02));border-bottom:1px solid var(--border);">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                  <thead>
                    <tr style="color:var(--muted);">
                      <th style="text-align:left;padding:4px 8px;font-weight:600;text-transform:uppercase;font-size:10px;letter-spacing:.5px;border-bottom:1px solid var(--border);">Product</th>
                      <th style="text-align:center;padding:4px 8px;font-weight:600;text-transform:uppercase;font-size:10px;letter-spacing:.5px;border-bottom:1px solid var(--border);">Qty</th>
                      <th style="text-align:center;padding:4px 8px;font-weight:600;text-transform:uppercase;font-size:10px;letter-spacing:.5px;border-bottom:1px solid var(--border);">Tier</th>
                      <th style="text-align:right;padding:4px 8px;font-weight:600;text-transform:uppercase;font-size:10px;letter-spacing:.5px;border-bottom:1px solid var(--border);">Price</th>
                      <th style="text-align:right;padding:4px 8px;font-weight:600;text-transform:uppercase;font-size:10px;letter-spacing:.5px;border-bottom:1px solid var(--border);">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($items): ?>
                      <?php foreach ($items as $item): ?>
                      <tr>
                        <td style="padding:6px 8px;font-weight:600;"><?= e($item['product_name'] ?? '—') ?></td>
                        <td style="padding:6px 8px;text-align:center;"><?= (float)($item['qty'] ?? 0) ?></td>
                        <td style="padding:6px 8px;text-align:center;"><?= e($item['tier_label'] ?? '—') ?></td>
                        <td style="padding:6px 8px;text-align:right;">RM<?= number_format((float)($item['unit_price'] ?? 0), 2) ?></td>
                        <td style="padding:6px 8px;text-align:right;font-weight:700;">RM<?= number_format((float)($item['subtotal'] ?? 0), 2) ?></td>
                      </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="5" style="padding:12px 8px;text-align:center;color:var(--muted);">No item details available.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($all_orders)): ?><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No orders found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
<script>
function toggleOrderItems(orderId) {
  var row = document.getElementById('items-' + orderId);
  var icon = document.getElementById('icon-' + orderId);
  if (!row) return;
  var isHidden = row.style.display === 'none' || row.style.display === '';
  row.style.display = isHidden ? 'table-row' : 'none';
  icon.textContent = isHidden ? '▼' : '▶';
}
</script>
