<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);

$successMsg = ''; $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'assign_warehouse') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $pId = (int)($_POST['port_id'] ?? 0);
            if ($orderId && $pId) {
                $order = dbGetRow("SELECT id, order_number, shipping_address FROM market_order WHERE id = ?", [$orderId]);
                if ($order) {
                    $existing = (int)dbGetValue("SELECT COUNT(*) FROM market_shipments WHERE order_id = ?", [$orderId]);
                    if (!$existing) {
                        $shipNum = generateShipmentNumber('domestic');
                        dbExecute("INSERT INTO market_shipments (shipment_number, order_id, order_number, port_id, status) VALUES (?, ?, ?, ?, 'pending_pickup')",
                            [$shipNum, $orderId, $order['order_number'], $pId]);
                        dbExecute("UPDATE market_order SET status = 'processing' WHERE id = ?", [$orderId]);
                        $successMsg = "✅ Order {$order['order_number']} assigned successfully!";
                    } else { $errorMsg = 'Order already has a shipment.'; }
                }
            } else { $errorMsg = 'Please select an order and port.'; }
        } elseif ($action === 'auto_assign') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            if ($orderId) {
                $order = dbGetRow("SELECT id, order_number, shipping_address FROM market_order WHERE id = ?", [$orderId]);
                if ($order) {
                    $pId = getNearestWarehouse($order['shipping_address'] ?? '');
                    $existing = (int)dbGetValue("SELECT COUNT(*) FROM market_shipments WHERE order_id = ?", [$orderId]);
                    if (!$existing) {
                        $shipNum = generateShipmentNumber('domestic');
                        dbExecute("INSERT INTO market_shipments (shipment_number, order_id, order_number, port_id, status) VALUES (?, ?, ?, ?, 'pending_pickup')",
                            [$shipNum, $orderId, $order['order_number'], $pId]);
                        dbExecute("UPDATE market_order SET status = 'processing' WHERE id = ?", [$orderId]);
                        $successMsg = "✅ {$order['order_number']} auto-assigned to nearest port!";
                    } else { $errorMsg = 'Already assigned.'; }
                }
            }
        } elseif ($action === 'reassign') {
            $shipId = (int)($_POST['shipment_id'] ?? 0);
            $newP = (int)($_POST['port_id'] ?? 0);
            if ($shipId && $newP) {
                dbExecute("UPDATE market_shipments SET port_id = ?, updated_at = NOW() WHERE id = ?", [$newP, $shipId]);
                $successMsg = "✅ Shipment reassigned to new port!";
            }
        }
    } catch (Exception $e) { $errorMsg = 'Error: ' . $e->getMessage(); }
}

// Get unassigned orders (no shipment yet)
$unassignedOrders = [];
try {
    $unassignedOrders = dbGetAll("SELECT o.*, u.full_name, u.username FROM market_order o 
        LEFT JOIN market_user u ON u.id = o.user_id 
        WHERE o.id NOT IN (SELECT COALESCE(order_id,0) FROM market_shipments)
        ORDER BY o.created_at DESC");
} catch (Exception $e) {}

// Get all shipments with port info
$shipments = [];
try {
    $shipments = dbGetAll("SELECT s.*, p.name as port_name, p.code as port_code 
        FROM market_shipments s 
        LEFT JOIN port p ON p.id = s.port_id 
        ORDER BY s.created_at DESC");
    } catch (Exception $e) {}

    // Get ports
    $ports = [];
    try {
        $ports = dbGetAll("SELECT id, name, code FROM port WHERE is_active = TRUE ORDER BY id");
} catch (Exception $e) {}

$title = 'Warehouse Assignment';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.wh-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; margin-bottom:20px; }
.wh-card { background:rgba(255,255,255,0.06); border:1px solid var(--border); border-radius:var(--radius-sm); padding:16px; }
.wh-card .name { font-weight:700; font-size:14px; }
.wh-card .detail { font-size:11px; color:var(--muted); margin-top:4px; }
.action-btn { padding:6px 14px; border-radius:8px; border:none; font-weight:700; font-size:11px; cursor:pointer; transition:.12s; }
.action-btn:hover { filter:brightness(1.1); }
.btn-assign { background:var(--brand); color:#081225; }
.btn-auto { background:rgba(99,102,241,0.15); color:#818cf8; border:1px solid rgba(99,102,241,0.25); }
.btn-reassign { background:rgba(251,191,36,0.15); color:#fbbf24; }
.form-select-sm { padding:5px 10px; border:1px solid var(--border); border-radius:6px; background:rgba(10,14,26,0.4); color:var(--text); font-size:12px; }
.badge-pending { background:rgba(251,191,36,0.15); color:#fbbf24; }
.badge-assigned { background:rgba(99,102,241,0.15); color:#818cf8; }
</style>
<div class="dash-layout">
<?php $sidebarActive = 'warehouse'; require __DIR__ . '/../helpers/sidebar_market.php'; ?>
  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <h2 style="margin:0;">📦 Warehouse Assignment</h2>
      <p class="subtle" style="margin:4px 0 0;">Assign incoming orders to the nearest port for processing</p>
    </div>

    <?php if ($successMsg): ?><div style="padding:12px 16px;border-radius:10px;background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.3);color:var(--ok);margin-bottom:16px;font-size:13px;"><?= e($successMsg) ?></div><?php endif; ?>
    <?php if ($errorMsg): ?><div style="padding:12px 16px;border-radius:10px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#ef4444;margin-bottom:16px;font-size:13px;">❌ <?= e($errorMsg) ?></div><?php endif; ?>

    <!-- Port Overview -->
    <div class="card" style="margin-bottom:16px;">
      <h3 style="margin:0 0 12px;font-size:14px;">🏭 Port Overview</h3>
      <div class="wh-grid">
        <?php foreach ($ports as $p): ?>
        <div class="wh-card">
          <div class="name"><?= e($p['name']) ?></div>
          <div class="detail">📦 Capacity: <?= number_format((int)($p['capacity'] ?? 0)) ?><?php 
            $pCount = 0; foreach ($shipments as $s) if ($s['port_id'] == $p['id']) $pCount++;
            echo " | Active: $pCount"; 
          ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Unassigned Orders -->
    <div class="card" style="margin-bottom:16px;">
      <h3 style="margin:0 0 12px;font-size:14px;">🆕 Unassigned Orders</h3>
      <?php if ($unassignedOrders): ?>
      <table class="activity-table">
        <thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Address</th><th style="min-width:200px;">Assign Port</th></tr></thead>
        <tbody>
          <?php foreach ($unassignedOrders as $o): ?>
          <tr>
            <td><strong><?= e($o['order_number']) ?></strong></td>
            <td style="font-size:12px;"><?= e($o['full_name'] ?: $o['username'] ?: "#{$o['user_id']}") ?></td>
            <td style="font-weight:700;">RM<?= number_format($o['total_amount'] ?? 0, 2) ?></td>
            <td style="font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($o['shipping_address'] ?? '-') ?></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <form method="POST" style="display:inline-flex;gap:4px;">
                  <input type="hidden" name="action" value="assign_warehouse">
                  <input type="hidden" name="order_id" value="<?= e($o['id']) ?>">
                  <select name="port_id" required class="form-select-sm">
                    <option value="">Select...</option>
                    <?php foreach ($ports as $p): ?>
                    <option value="<?= e($p['id']) ?>"><?= e($p['code']) ?> - <?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="action-btn btn-assign">Assign</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="auto_assign">
                  <input type="hidden" name="order_id" value="<?= e($o['id']) ?>">
                  <button type="submit" class="action-btn btn-auto">🤖 Auto</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p style="color:var(--muted);font-size:13px;">🎉 All orders have been assigned.</p>
      <?php endif; ?>
    </div>

    <!-- Assigned Shipments -->
    <div class="card">
      <h3 style="margin:0 0 12px;font-size:14px;">📋 Assigned Shipments</h3>
      <?php if ($shipments): ?>
      <table class="activity-table">
        <thead><tr><th>Ship #</th><th>Order</th><th>Port</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($shipments as $s): ?>
          <tr>
            <td style="font-family:monospace;font-size:12px;"><?= e($s['shipment_number']) ?></td>
            <td><strong><?= e($s['order_number']) ?></strong></td>
            <td style="font-size:12px;"><?= e($s['port_name'] ?: '-') ?></td>
            <td><span class="status-badge badge-<?= e($s['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?></span></td>
            <td>
              <?php if ($s['status'] === 'pending_pickup'): ?>
              <form method="POST" style="display:inline-flex;gap:4px;">
                <input type="hidden" name="action" value="reassign">
                <input type="hidden" name="shipment_id" value="<?= e($s['id']) ?>">
                <select name="port_id" class="form-select-sm">
                  <?php foreach ($ports as $p): ?>
                  <option value="<?= e($p['id']) ?>" <?= $p['id']==$s['port_id']?'selected':'' ?>><?= e($p['code']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="action-btn btn-reassign" onclick="return confirm('Reassign this shipment?')">🔄 Reassign</button>
              </form>
              <?php else: ?>
              <a href="/logistics/" style="font-size:12px;color:var(--brand)">View in Fulfillment →</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p style="color:var(--muted);font-size:13px;">No shipments created yet.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
