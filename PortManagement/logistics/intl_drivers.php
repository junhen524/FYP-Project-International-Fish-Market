<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

// ── Handle Start Delivery ──
$deliveryMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_delivery') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    try {
        $currentStage = dbGetValue("SELECT stage FROM export_orders WHERE id = ?", [$orderId]);
        if ($currentStage === 'delivered' || $currentStage === 'rejected') {
            $deliveryMsg = 'Cannot start delivery — order is already ' . $currentStage . '.';
        } else {
            dbExecute("UPDATE export_shipment SET status = 'out_for_delivery', updated_at = NOW() WHERE order_id = ?", [$orderId]);
            dbExecute("UPDATE export_orders SET stage = 'delivery' WHERE id = ?", [$orderId]);
            $deliveryMsg = '✅ Delivery started! Customer can now scan QR code.';
        }
    } catch (Exception $e) { $deliveryMsg = 'Error: ' . $e->getMessage(); }
}

$drivers = []; $ports = []; $pendingDeliveries = [];
try {
    $drivers = dbGetAll("SELECT d.*, p.name as port_name FROM export_driver d LEFT JOIN ports p ON p.id = d.port_id ORDER BY d.name");
    $ports = dbGetAll("SELECT id, name FROM ports ORDER BY id");
    
    // Get shipments ready for delivery: arrived + has driver, or out_for_delivery
    $pendingDeliveries = dbGetAll("
        SELECT o.id, o.order_number, o.total_amount, o.stage, o.destination_country,
               o.delivery_qr_code, o.delivery_pin, o.delivery_qr_used,
               s.status as ship_status, s.driver_id, d.name as driver_name, d.vehicle_no
        FROM export_orders o
        JOIN export_shipment s ON s.order_id = o.id
        LEFT JOIN export_driver d ON d.id = s.driver_id
        WHERE s.driver_id IS NOT NULL
          AND o.stage IN ('shipping', 'delivery')
          AND s.status IN ('arrived', 'out_for_delivery')
        ORDER BY o.created_at DESC
    ");
} catch (Exception $e) {}

$title = 'International Drivers';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.logi-btn { padding:6px 14px; border-radius:8px; border:none; font-weight:700; font-size:11px; cursor:pointer; transition:.12s; white-space:nowrap; }
.logi-btn:hover { filter:brightness(1.1); }
.logi-btn-brand { background:var(--brand); color:#081225; }
.logi-btn-ghost { background:rgba(255,255,255,0.08); color:var(--text); border:1px solid var(--border); }
.logi-btn-ok { background:rgba(52,211,153,0.15); color:#34d399; }
.logi-btn-ok:hover { background:rgba(52,211,153,0.25); }
</style>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🌍 International</div>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/">📊 Dashboard</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/orders/">📋 Orders</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/users/">👥 Users</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/restaurants/">🏪 Restaurants</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/topup/">💰 Top-Up</a>
    <a class="dash-sidebar-item" href="/logistics/international/">🚢 Export Logistics</a>
    <a class="dash-sidebar-item active" href="/logistics/export_driver/">👤 Intl. Drivers</a>
  </nav>
  <div class="dash-content">
  
    <?php if ($deliveryMsg): ?>
    <div style="padding:12px 16px;border-radius:10px;background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.3);color:var(--ok);margin-bottom:16px;font-size:13px;"><?= e($deliveryMsg) ?></div>
    <?php endif; ?>

    <!-- Pending Deliveries Section -->
    <?php if ($pendingDeliveries): ?>
    <div class="card" style="margin-bottom:16px;">
      <h2 style="margin:0;">🚚 Active Deliveries</h2>
      <p class="subtle" style="margin:4px 0 0;">Orders assigned to drivers — ready for delivery or currently out for delivery</p>
    </div>
    <div class="card" style="overflow-x:auto;margin-bottom:20px;">
      <table class="activity-table">
        <thead><tr><th>Order #</th><th>Destination</th><th>Amount</th><th>Driver</th><th>Vehicle</th><th>Status</th><th>QR</th><th>PIN</th><th style="min-width:160px;">Action</th></tr></thead>
        <tbody>
          <?php foreach ($pendingDeliveries as $pd):
            $stage = $pd['stage'];
            $shipSt = $pd['ship_status'];
          ?>
          <tr>
            <td><strong><?= e($pd['order_number']) ?></strong></td>
            <td style="font-size:12px;"><?= e($pd['destination_country'] ?? '—') ?></td>
            <td style="font-weight:700;">USD<?= number_format($pd['total_amount'] ?? 0, 2) ?></td>
            <td style="font-size:12px;"><?= e($pd['driver_name'] ?? '—') ?></td>
            <td style="font-size:12px;"><?= e($pd['vehicle_no'] ?? '—') ?></td>
            <td><span class="status-badge badge-<?= e($shipSt) ?>"><?= e(ucfirst(str_replace('_', ' ', $shipSt))) ?></span></td>
            <td style="text-align:center;">
              <?php if (!empty($pd['delivery_qr_code'])): ?>
                <a href="javascript:void(0)" onclick="openQrModal('<?= e($pd['order_number']) ?>','<?= e($pd['delivery_qr_code']) ?>')" style="text-decoration:none;font-size:18px;" title="View QR Code">🔳</a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td style="text-align:center;">
              <?php if (!empty($pd['delivery_pin'])): ?>
                <span style="font-family:'Courier New',monospace;font-weight:700;font-size:14px;letter-spacing:3px;"><?= e($pd['delivery_pin']) ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <?php if ($shipSt === 'arrived' && $stage === 'shipping'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="start_delivery">
                  <input type="hidden" name="order_id" value="<?= e($pd['id']) ?>">
                  <button type="submit" class="logi-btn logi-btn-brand">📱 Start Delivery</button>
                </form>
              <?php elseif ($shipSt === 'out_for_delivery'): ?>
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                  <span style="color:#f59e0b;font-weight:700;font-size:11px;">🚚 Out for Delivery</span>
                  <?php if (!empty($pd['delivery_qr_used'])): ?>
                    <span style="color:#34d399;font-weight:700;font-size:11px;">✅ Used</span>
                  <?php else: ?>
                    <span style="color:#f59e0b;font-weight:700;font-size:11px;">⏳ Active</span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
      <div>
        <h2 style="margin:0;">👤 All International Drivers</h2>
        <p class="subtle" style="margin:4px 0 0;">Manage export shipping drivers (separate from local delivery drivers)</p>
      </div>
      <a href="/logistics/export_driver/add/" class="btn btn-ghost" style="padding:10px 18px;font-size:12px;font-weight:700;background:var(--brand);color:#081225;border:none;border-radius:8px;">➕ Add</a>
    </div>
    <div class="card">
      <table class="activity-table">
        <thead><tr><th>Name</th><th>Phone</th><th>IC / ID</th><th>Departure Port</th><th>License</th><th>Vehicle</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (empty($drivers)): ?><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No drivers found.</td></tr><?php endif; ?>
          <?php foreach ($drivers as $d): ?>
          <tr><td><strong><?= e($d['name']) ?></strong></td><td><?= e($d['phone'] ?? '—') ?></td><td style="font-family:monospace;font-size:12px;"><?= e($d['identification_no'] ?? '—') ?></td><td><?= e($d['port_name'] ?? '—') ?></td><td><?= e($d['license_no'] ?? '—') ?></td><td><?= e($d['vehicle_no'] ?? '—') ?></td><td><span class="status-badge badge-active"><?= $d['is_active'] ? 'Active' : 'Inactive' ?></span></td><td><a href="/logistics/export_driver/edit/?id=<?= e($d['id']) ?>" class="btn btn-ghost" style="padding:2px 10px;font-size:11px;">Edit</a></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
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
