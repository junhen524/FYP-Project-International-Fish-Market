<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);

function getIntlWarehouse($country) {
    $map = [
        'japan' => 1, 'china' => 4, 'hong kong' => 4, 'taiwan' => 4, 'korea' => 4,
        'singapore' => 3, 'indonesia' => 3, 'vietnam' => 3, 'thailand' => 3,
        'usa' => 1, 'canada' => 1, 'mexico' => 1, 'brazil' => 1,
        'australia' => 2, 'new zealand' => 2, 'india' => 2, 'bangladesh' => 2,
        'france' => 1, 'germany' => 1, 'uk' => 1, 'netherlands' => 1, 'spain' => 1,
    ];
    foreach ($map as $keyword => $whId) {
        if (stripos($country, $keyword) !== false) return $whId;
    }
    return 1;
}

$successMsg = ''; $errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);
    try {
        if ($action === 'assign_warehouse') {
            $order = dbGetRow("SELECT id, order_number, destination_country FROM export_orders WHERE id = ?", [$orderId]);
            if ($order) {
                $existing = dbGetValue("SELECT COUNT(*) FROM export_shipment WHERE order_id = ?", [$orderId]);
                if (!$existing) {
                    $shipNum = generateShipmentNumber('export');
                    dbExecute("INSERT INTO export_shipment (shipment_number, order_id, order_number, status, destination_country) VALUES (?, ?, ?, 'pending_pickup', ?)",
                        [$shipNum, $orderId, $order['order_number'], $order['destination_country']]);
                    $successMsg = "Order {$order['order_number']} assigned to departure port.";
                }
            }
        } elseif ($action === 'pack') {
            dbExecute("UPDATE export_shipment SET status = 'packing', packed_at = NOW(), updated_at = NOW() WHERE order_id = ?", [$orderId]);
            $successMsg = 'Marked as packing.';
        } elseif ($action === 'assign_driver') {
            $driverId = (int)($_POST['driver_id'] ?? 0);
            $ship = dbGetRow("SELECT destination_port FROM export_shipment WHERE order_id = ?", [$orderId]);
            if ($ship && $driverId) {
                dbExecute("UPDATE export_shipment SET driver_id = ?, status = 'loaded', loaded_at = NOW(), updated_at = NOW() WHERE order_id = ?", [$driverId, $orderId]);
                dbExecute("UPDATE export_orders SET stage = 'shipping' WHERE id = ?", [$orderId]);
                $successMsg = 'Loaded onto truck for port delivery!';
            }
        } elseif ($action === 'dispatch') {
            $destPort = $_POST['destination_port'] ?? '';
            dbExecute("UPDATE export_shipment SET status = 'in_transit', destination_port = COALESCE(?, destination_port), shipped_at = NOW(), updated_at = NOW() WHERE order_id = ?", [$destPort ?: null, $orderId]);
            dbExecute("UPDATE export_orders SET stage = 'shipping', shipped_at = NOW() WHERE id = ?", [$orderId]);
            $successMsg = 'Order shipped to destination port!';
        } elseif ($action === 'confirm_arrival') {
            dbExecute("UPDATE export_shipment SET status = 'arrived', delivered_at = NOW(), updated_at = NOW() WHERE order_id = ?", [$orderId]);
            dbExecute("UPDATE export_orders SET stage = 'shipping', shipped_at = COALESCE(shipped_at, NOW()) WHERE id = ?", [$orderId]);
            $successMsg = 'Order arrived at destination! Awaiting driver delivery.';
        } elseif ($action === 'out_for_delivery') {
            $orderCheck = dbGetValue("SELECT stage FROM export_orders WHERE id = ?", [$orderId]);
            if ($orderCheck === 'delivered' || $orderCheck === 'rejected') {
                $errorMsg = 'Cannot set out for delivery — order is already ' . $orderCheck . '.';
            } else {
                dbExecute("UPDATE export_shipment SET status = 'out_for_delivery', updated_at = NOW() WHERE order_id = ?", [$orderId]);
                dbExecute("UPDATE export_orders SET stage = 'delivery' WHERE id = ?", [$orderId]);
                $successMsg = 'Order is out for delivery! Customer can scan QR code to accept/reject.';
            }
        }
    } catch (Exception $e) { $errorMsg = 'Error: ' . $e->getMessage(); }
}

$orders = []; $shipments = []; $drivers = [];
try {
    $orders = dbGetAll("SELECT id, order_number, total_amount, stage, destination_country, destination_port, created_at, delivery_qr_code, delivery_pin, delivery_qr_used FROM export_orders ORDER BY created_at DESC");
    $shipments = dbGetAll("SELECT s.* FROM export_shipment s ORDER BY s.created_at DESC");
    $drivers = dbGetAll("SELECT d.*, p.name as port_name FROM export_driver d JOIN ports p ON p.id = d.port_id WHERE d.is_active = TRUE ORDER BY d.name");
    $ports = dbGetAll("SELECT id, name FROM ports ORDER BY id");
} catch (Exception $e) {}

$shipMap = [];
foreach ($shipments as $s) $shipMap[$s['order_id']] = $s;

$whNames = [1 => 'Port Klang', 2 => 'Port Penang', 3 => 'Port Johor', 4 => 'Port Kuantan'];

$title = 'International Logistics';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.logi-btn { padding:6px 14px; border-radius:8px; border:none; font-weight:700; font-size:11px; cursor:pointer; transition:.12s; white-space:nowrap; }
.logi-btn:hover { filter:brightness(1.1); }
.logi-btn-brand { background:var(--brand); color:#081225; }
.logi-btn-ghost { background:rgba(255,255,255,0.08); color:var(--text); border:1px solid var(--border); }
.logi-btn-ok { background:rgba(52,211,153,0.15); color:#34d399; }
.logi-btn-ok:hover { background:rgba(52,211,153,0.25); }
.pipe-step { padding:4px 10px; border-radius:6px; font-size:10px; font-weight:700; background:rgba(255,255,255,0.05); color:var(--muted); white-space:nowrap; }
.pipe-step.done { background:rgba(52,211,153,0.15); color:#34d399; }
</style>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🌍 International</div>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/">📊 Dashboard</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/orders/">📋 Orders</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/users/">👥 Users</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/restaurants/">🏪 Restaurants</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/topup/">💰 Top-Up</a>
    <a class="dash-sidebar-item active" href="/logistics/international/">🚢 Export Logistics</a>
    <a class="dash-sidebar-item" href="/logistics/export_driver/">👤 Intl. Drivers</a>
  </nav>
  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <h2 style="margin:0;">🌍 International Order Fulfillment</h2>
      <p class="subtle" style="margin:4px 0 0;">Process export orders — pack at local port → truck to port → ship to destination</p>
    </div>
    <?php if ($successMsg): ?><div style="padding:12px 16px;border-radius:10px;background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.3);color:var(--ok);margin-bottom:16px;font-size:13px;">✅ <?= e($successMsg) ?></div><?php endif; ?>
    <?php if ($errorMsg): ?><div style="padding:12px 16px;border-radius:10px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#ef4444;margin-bottom:16px;font-size:13px;">❌ <?= e($errorMsg) ?></div><?php endif; ?>

    <div class="card" style="overflow-x:auto;">
      <table class="activity-table">
        <thead><tr><th>Order #</th><th>Destination</th><th>Amount</th><th>Status</th><th>QR Code</th><th>PIN</th><th>Departure Port</th><th>Driver</th><th style="min-width:240px;">Action</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o):
            $sm = $shipMap[$o['id']] ?? null;
            // Safety: if QR was used but stage stuck at 'delivery', treat as delivered
            // If rejected, keep as rejected
            $stage = ($o['stage'] === 'delivery' && !empty($o['delivery_qr_used'])) ? 'delivered' : ($o['stage'] ?? 'quote');
          ?>
          <tr>
            <td><strong><?= e($o['order_number']) ?></strong></td>
            <td style="font-size:12px;"><?= e($o['destination_country'] ?? '—') ?><br><span style="color:var(--muted);"><?= e($o['destination_port'] ?? '') ?></span></td>
            <td style="font-weight:700;">USD<?= number_format($o['total_amount'] ?? 0, 2) ?></td>
            <td><span class="status-badge badge-<?= $stage === 'delivered' ? 'delivered' : ($stage === 'rejected' ? 'rejected' : ($sm ? e($sm['status']) : e($stage))) ?>"><?php if ($stage === 'delivered'): ?>✅ Confirmed Delivered<?php elseif ($stage === 'rejected'): ?>❌ Rejected<?php else: ?><?= e(ucfirst(str_replace('_', ' ', $sm['status'] ?? $stage))) ?><?php endif; ?></span></td>
            <td style="text-align:center;font-size:13px;">
              <?php if (!empty($o['delivery_qr_code'])): ?>
                <a href="javascript:void(0)" onclick="openQrModal('<?= e($o['order_number']) ?>','<?= e($o['delivery_qr_code']) ?>')" style="text-decoration:none;font-size:18px;" title="View QR Code">🔳</a>
              <?php else: ?>
                <span style="color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;font-size:13px;">
              <?php if (!empty($o['delivery_pin'])): ?>
                <span style="font-family:'Courier New',monospace;font-weight:700;font-size:14px;letter-spacing:3px;color:var(--text);"><?= e($o['delivery_pin']) ?></span>
              <?php else: ?>
                <span style="color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;"><?php if ($sm): $whId = getIntlWarehouse($o['destination_country'] ?? ''); echo e($whNames[$whId] ?? '—'); else: ?>—<?php endif; ?></td>
            <td style="font-size:12px;"><?php if ($sm && $sm['driver_id']): $dn = ''; foreach ($drivers as $d) { if ($d['id'] == $sm['driver_id']) { $dn = $d['name']; break; } } echo e($dn); else: ?>—<?php endif; ?></td>
            <td>
              <?php if (!$sm): ?>
                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="assign_warehouse"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><button type="submit" class="logi-btn logi-btn-brand">📦 Assign Port</button></form>
              <?php elseif ($sm['status'] === 'pending_pickup'): ?>
                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="pack"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><button type="submit" class="logi-btn logi-btn-brand">📦 Pack</button></form>
              <?php elseif ($sm['status'] === 'packing'): ?>
                <form method="POST" style="display:inline-flex;gap:6px;"><input type="hidden" name="action" value="assign_driver"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><select name="driver_id" required class="form-control" style="width:auto;display:inline-block;padding:6px 10px;font-size:12px;"><option value="">Driver...</option><?php foreach ($drivers as $d): ?><option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select><button type="submit" class="logi-btn logi-btn-ghost">🚛 Assign</button></form>
              <?php elseif ($sm['status'] === 'loaded'): ?>
                <form method="POST" style="display:inline-flex;gap:6px;"><input type="hidden" name="action" value="dispatch"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><input type="text" name="destination_port" placeholder="Port name..." class="form-control" style="width:120px;padding:6px 8px;font-size:11px;" value="<?= e($o['destination_port'] ?? '') ?>"><button type="submit" class="logi-btn logi-btn-brand">🚢 Ship</button></form>
              <?php elseif ($sm['status'] === 'in_transit'): ?>
                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="confirm_arrival"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><button type="submit" class="logi-btn logi-btn-ok">✅ Arrived</button></form>
              <?php elseif ($sm['status'] === 'arrived'): ?>
                <span style="color:#f59e0b;font-weight:700;font-size:11px;">⏳ Awaiting Driver</span>
              <?php elseif ($stage === 'rejected'): ?>
                <span style="color:#ef4444;font-weight:700;">❌ Rejected</span>
              <?php elseif ($stage === 'delivered' || $sm['status'] === 'delivered'): ?>
                <span style="color:#34d399;font-weight:700;">✅ Confirmed Delivered</span>
              <?php elseif ($sm['status'] === 'out_for_delivery'): ?>
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                  <span style="color:#f59e0b;font-weight:700;font-size:11px;">🚚 Out for Delivery</span>
                  <?php if (!empty($o['delivery_qr_used'])): ?>
                    <span style="color:#34d399;font-weight:700;font-size:11px;">✅ Used</span>
                  <?php else: ?>
                    <span style="color:#f59e0b;font-weight:700;font-size:11px;">⏳ Active</span>
                  <?php endif; ?>
                </div>
              <?php elseif ($sm['status'] === 'delivered' || $stage === 'delivered'): ?>
                <span style="color:#34d399;font-weight:700;">✅ Confirmed Delivered</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($orders)): ?><tr><td colspan="9" style="text-align:center;padding:40px;color:var(--muted);">No international orders yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="margin-top:16px;">
      <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
        <span style="font-size:11px;color:var(--muted);">🌍 Country → Departure Port:</span>
        <span class="pipe-step">Japan → Port Klang</span>
        <span class="pipe-step">Singapore → Port Johor</span>
        <span class="pipe-step">China → Port Kuantan</span>
        <span class="pipe-step">USA → Port Klang</span>
        <span class="pipe-step">Australia → Port Penang</span>
      </div>
    </div>
  </div>
</div>

<!-- QR Code Modal -->
<div id="qrModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;" onclick="if(event.target===this)closeQrModal()">
  <div style="background:#fff;border-radius:20px;padding:32px;max-width:400px;width:90%;text-align:center;position:relative;">
    <button onclick="closeQrModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:24px;cursor:pointer;color:#94a3b8;">&times;</button>
    <h3 id="qrModalTitle" style="margin:0 0 16px;font-size:16px;font-weight:800;color:#0f172a;">QR Code</h3>
    <img id="qrModalImage" src="" alt="QR Code" style="width:280px;height:280px;border-radius:12px;border:2px solid #e2e8f0;padding:8px;background:#fff;">
    <p style="margin:12px 0 0;font-size:12px;color:#64748b;">Scan this code to confirm delivery</p>
  </div>
</div>

<script>
<?php
// Detect real LAN IP (skip VirtualBox adapters) for QR code
// International server runs on port 8002, not 8000 (PortManagement)
$__qrHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$__isLocal = strpos($__qrHost, 'localhost') !== false || strpos($__qrHost, '127.0.0.1') !== false;
if ($__isLocal) {
    $lan = lan_ip();
    if ($lan && $lan !== '127.0.0.1' && $lan !== '::1') {
        $__qrHost = $lan . ':8002'; // International server is on port 8002
    }
}
$__qrProtocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$__qrBaseUrl = $__qrProtocol . '://' . $__qrHost . '/International/track.php?code=';
?>
function openQrModal(orderNum, qrCode) {
  document.getElementById('qrModalTitle').textContent = 'QR Code — Order #' + orderNum;
  document.getElementById('qrModalImage').src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent('<?= rtrim($__qrBaseUrl) ?>' + qrCode);
  document.getElementById('qrModal').style.display = 'flex';
}
function closeQrModal() {
  document.getElementById('qrModal').style.display = 'none';
}
</script>

<?php require __DIR__ . '/../helpers/footer.php'; ?>
