<?php
require_once __DIR__ . '/../helpers/functions.php';

// ── Route sub-pages (Apache may route /logistics/* through this index.php) ──
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
$logiRoutes = [
    '/logistics/international'           => '/international.php',
    '/logistics/export_driver/add'       => '/intl_driver_add.php',
    '/logistics/export_driver/edit'      => '/intl_driver_edit.php',
    '/logistics/export_driver'           => '/intl_drivers.php',
    '/logistics/warehouse'               => '/warehouse.php',
    '/logistics/drivers/add'             => '/driver_add.php',
    '/logistics/drivers/edit'            => '/driver_edit.php',
    '/logistics/drivers'                 => '/drivers.php',
];
// Prevent unauthorized redirect to wrong path; must set SCRIPT_NAME so any
// login redirect in the included page goes to the correct base URL.
foreach ($logiRoutes as $pattern => $file) {
    if (strpos($reqUri, $pattern) !== false) {
        // Fix SCRIPT_NAME so requireLogin() redirects to /PortManagement/login/, not /logistics/login/
        $_SERVER['SCRIPT_NAME'] = dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/index.php';
        require __DIR__ . $file;
        exit;
    }
}

$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $orderId = (int) ($_POST['order_id'] ?? 0);

    try {
        if ($action === 'assign_driver') {
            // ── Assign driver to a new / unassigned order ──
            $driverId = (int) ($_POST['driver_id'] ?? 0);
            $order    = dbGetRow("SELECT id, order_number FROM market_order WHERE id = ?", [$orderId]);
            if ($order && $driverId) {
                $existing = dbGetValue("SELECT COUNT(*) FROM market_shipments WHERE order_id = ?", [$orderId]);
                if (!$existing) {
                    $shipNum = generateShipmentNumber('domestic');
                    dbExecute(
                        "INSERT INTO market_shipments (shipment_number, order_id, order_number, driver_id, status, loaded_at, updated_at) VALUES (?, ?, ?, ?, 'loaded', NOW(), NOW())",
                        [$shipNum, $orderId, $order['order_number'], $driverId]
                    );
                    dbExecute("UPDATE market_order SET status = 'shipping' WHERE id = ?", [$orderId]);
                    $successMsg = "🚛 Driver assigned to {$order['order_number']}!";
                } else {
                    $errorMsg = 'Order already has a shipment.';
                }
            }
        } elseif ($action === 'dispatch') {
            // ── Mark loaded shipment as in_transit ──
            dbExecute("UPDATE market_shipments SET status = 'in_transit', shipped_at = NOW(), updated_at = NOW() WHERE order_id = ? AND status = 'loaded'", [$orderId]);
            $successMsg = '🚚 Order is now in transit.';
        } elseif ($action === 'confirm_delivery') {
            // ── Confirm delivery ──
            dbExecute("UPDATE market_shipments SET status = 'delivered', delivered_at = NOW(), updated_at = NOW() WHERE order_id = ? AND status = 'in_transit'", [$orderId]);
            dbExecute("UPDATE market_order SET status = 'completed' WHERE id = ?", [$orderId]);
            $successMsg = '✅ Order delivered successfully!';
        } elseif ($action === 'undo_delivery') {
            // ── Rollback (admin only) ──
            dbExecute("UPDATE market_shipments SET status = 'in_transit', delivered_at = NULL, updated_at = NOW() WHERE order_id = ?", [$orderId]);
            dbExecute("UPDATE market_order SET status = 'shipping' WHERE id = ?", [$orderId]);
            $successMsg = '↩️ Delivery reverted.';
        }
    } catch (Exception $e) {
        $errorMsg = 'Error: ' . $e->getMessage();
    }
}

// ── Fetch data ──
$drivers   = [];
$unassigned = [];  // orders without any shipment
$assigned   = [];  // shipments with driver assigned
$delivered  = [];  // completed deliveries
try {
    $drivers = dbGetAll(
        "SELECT d.*, p.name as port_name FROM market_drivers d JOIN ports p ON p.id = d.port_id WHERE d.is_active = TRUE ORDER BY d.name"
    );

    // Orders with NO shipment at all
    $unassigned = dbGetAll(
        "SELECT o.id, o.order_number, o.total_amount, o.items, o.shipping_address, o.created_at,
                u.full_name, u.username, u.phone
         FROM market_order o
         LEFT JOIN market_user u ON u.id = o.user_id
         WHERE o.id NOT IN (SELECT order_id FROM market_shipments)
           AND o.status IN ('confirmed','pending')
         ORDER BY o.created_at DESC"
    );

    // Active shipments (loaded or in_transit)
    $assigned = dbGetAll(
        "SELECT s.*, o.total_amount, o.items, o.shipping_address, o.created_at as order_date,
                u.full_name, u.username, u.phone, d.name as driver_name, d.vehicle_no, d.phone as driver_phone
         FROM market_shipments s
         JOIN market_order o ON o.id = s.order_id
         LEFT JOIN market_user u ON u.id = o.user_id
         LEFT JOIN market_drivers d ON d.id = s.driver_id
         WHERE s.status IN ('loaded','in_transit')
         ORDER BY s.updated_at DESC"
    );

    // Completed deliveries
    $delivered = dbGetAll(
        "SELECT s.*, o.total_amount, o.items, o.shipping_address,
                u.full_name, u.username, u.phone, d.name as driver_name, d.vehicle_no
         FROM market_shipments s
         JOIN market_order o ON o.id = s.order_id
         LEFT JOIN market_user u ON u.id = o.user_id
         LEFT JOIN market_drivers d ON d.id = s.driver_id
         WHERE s.status = 'delivered'
         ORDER BY s.delivered_at DESC LIMIT 30"
    );
} catch (Exception $e) {
    $errorMsg = 'Database error: ' . $e->getMessage();
}

$title = 'Delivery';
$extra_head = '<style>
.delivery-card { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; padding:20px; margin-bottom:20px; }
.delivery-card h3 { margin:0 0 12px; font-size:16px; display:flex; align-items:center; gap:8px; }
.count-badge { display:inline-flex; align-items:center; justify-content:center; min-width:22px; height:22px; padding:0 6px; border-radius:11px; font-size:11px; font-weight:700; background:var(--brand); color:#081225; }
.status-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; }
.status-dot.loaded { background:#f59e0b; }
.status-dot.in_transit { background:#3b82f6; animation:pulse 1.5s infinite; }
.status-dot.delivered { background:#10b981; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
.logi-btn { padding:6px 14px; border-radius:8px; border:none; font-weight:700; font-size:11px; cursor:pointer; transition:.12s; white-space:nowrap; }
.logi-btn:hover { filter:brightness(1.1); }
.logi-btn-brand { background:var(--brand); color:#081225; }
.logi-btn-ghost { background:rgba(255,255,255,0.08); color:var(--text); border:1px solid var(--border); }
.logi-btn-ok { background:rgba(52,211,153,0.15); color:#34d399; }
.logi-btn-ok:hover { background:rgba(52,211,153,0.25); }
.logi-btn-outline { background:transparent; color:var(--muted); border:1px dashed var(--border); }
.logi-btn-outline:hover { border-color:var(--brand); color:var(--brand); }
.empty-state { padding:32px; text-align:center; color:var(--muted); font-size:14px; }
.empty-state .icon { font-size:32px; margin-bottom:8px; }
</style>';
require __DIR__ . '/../helpers/header.php';
?>
<div class="dash-layout">
<?php $sidebarActive = 'delivery'; require __DIR__ . '/../helpers/sidebar_market.php'; ?>

<div class="dash-content">

    <!-- Header -->
    <div class="card" style="margin-bottom:20px;">
        <h2 style="margin:0;">🚛 Delivery Dashboard</h2>
        <p class="subtle" style="margin:4px 0 0;">Assign drivers and track shipments — no warehouse needed.</p>
    </div>

    <?php if ($successMsg): ?>
    <div style="padding:12px 16px;border-radius:10px;background:rgba(52,211,153,0.12);border:1px solid rgba(52,211,153,0.3);color:var(--ok);margin-bottom:16px;font-size:13px;"><?= $successMsg ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
    <div style="padding:12px 16px;border-radius:10px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#ef4444;margin-bottom:16px;font-size:13px;">❌ <?= e($errorMsg) ?></div>
    <?php endif; ?>

    <!-- ─── 🆕 Unassigned Orders ─── -->
    <div class="delivery-card">
        <h3>🆕 Unassigned Orders <span class="count-badge"><?= count($unassigned) ?></span></h3>
        <p class="subtle" style="margin:-6px 0 12px;font-size:12px;">New confirmed orders waiting for a driver assignment.</p>

        <?php if ($unassigned): ?>
        <table class="activity-table">
            <thead>
                <tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Items</th><th>Date</th><th style="min-width:190px;">Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($unassigned as $o):
                    $items = $o['items'] ? json_decode($o['items'], true) : [];
                    $itemCount = count($items);
                    $firstItem = $items[0]['product_name'] ?? '';
                ?>
                <tr>
                    <td><strong style="cursor:pointer;color:var(--brand);" onclick="openDetail(<?= (int)$o['id'] ?>, 'unassigned')"><?= e($o['order_number']) ?></strong></td>
                    <td style="font-size:12px;color:var(--muted);"><?= e($o['full_name'] ?: $o['username'] ?: '—') ?></td>
                    <td style="font-weight:700;">RM<?= number_format($o['total_amount'] ?? 0, 2) ?></td>
                    <td style="font-size:12px;"><?= $itemCount ?> item<?= $itemCount > 1 ? 's' : '' ?><?= $firstItem ? ' <span style="color:var(--muted)">· ' . e($firstItem) . '</span>' : '' ?></td>
                    <td style="font-size:11px;color:var(--muted);"><?= date('d M', strtotime($o['created_at'])) ?></td>
                    <td>
                        <form method="POST" style="display:inline-flex;gap:6px;width:100%;">
                            <input type="hidden" name="action" value="assign_driver">
                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                            <select name="driver_id" required class="form-control" style="width:auto;display:inline-block;padding:6px 10px;font-size:12px;flex:1;">
                                <option value="">Select driver…</option>
                                <?php foreach ($drivers as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?> (<?= e($d['vehicle_no']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="logi-btn logi-btn-brand">🚛 Assign</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state"><div class="icon">✅</div>All orders have been assigned.</div>
        <?php endif; ?>
    </div>

    <!-- ─── 🚛 Assigned Shipments ─── -->
    <div class="delivery-card">
        <h3>🚛 Assigned Shipments <span class="count-badge"><?= count($assigned) ?></span></h3>
        <p class="subtle" style="margin:-6px 0 12px;font-size:12px;">Orders that are loaded or currently in transit.</p>

        <?php if ($assigned): ?>
        <table class="activity-table">
            <thead>
                <tr><th>Shipment #</th><th>Order</th><th>Customer</th><th>Driver</th><th>Status</th><th>Amount</th><th style="min-width:140px;">Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($assigned as $s):
                    $driverInfo = e($s['driver_name'] ?? '—') . ' <span style="color:var(--muted);font-size:10px;">(' . e($s['vehicle_no'] ?? '') . ')</span>';
                ?>
                <tr>
                    <td><span style="font-size:11px;background:rgba(45,212,191,0.12);color:#2dd4bf;padding:2px 6px;border-radius:4px;font-weight:700;"><?= e($s['shipment_number']) ?></span></td>
                    <td><strong style="cursor:pointer;color:var(--brand);" onclick="openDetail(<?= (int)$s['order_id'] ?>, 'assigned')"><?= e($s['order_number']) ?></strong></td>
                    <td style="font-size:12px;color:var(--muted);"><?= e($s['full_name'] ?: $s['username'] ?: '—') ?></td>
                    <td style="font-size:12px;"><?= $driverInfo ?></td>
                    <td>
                        <span class="status-dot <?= e($s['status']) ?>"></span>
                        <span class="status-badge badge-<?= e($s['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?></span>
                    </td>
                    <td style="font-weight:700;">RM<?= number_format($s['total_amount'] ?? 0, 2) ?></td>
                    <td>
                        <?php if ($s['status'] === 'loaded'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="dispatch">
                            <input type="hidden" name="order_id" value="<?= (int)$s['order_id'] ?>">
                            <button type="submit" class="logi-btn logi-btn-ok">🚚 Dispatch</button>
                        </form>
                        <?php elseif ($s['status'] === 'in_transit'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="confirm_delivery">
                            <input type="hidden" name="order_id" value="<?= (int)$s['order_id'] ?>">
                            <button type="submit" class="logi-btn logi-btn-brand">✅ Confirm Delivery</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state"><div class="icon">🚚</div>No active shipments right now.</div>
        <?php endif; ?>
    </div>

    <!-- ─── ✅ Recently Delivered ─── -->
    <?php if ($delivered): ?>
    <div class="delivery-card">
        <h3>✅ Recently Delivered <span class="count-badge"><?= count($delivered) ?></span></h3>
        <p class="subtle" style="margin:-6px 0 12px;font-size:12px;">Last 30 completed deliveries.</p>
        <table class="activity-table">
            <thead>
                <tr><th>Shipment #</th><th>Order</th><th>Customer</th><th>Driver</th><th>Delivered</th></tr>
            </thead>
            <tbody>
                <?php foreach ($delivered as $s): ?>
                <tr>
                    <td><span style="font-size:11px;background:rgba(52,211,153,0.12);color:#34d399;padding:2px 6px;border-radius:4px;font-weight:700;"><?= e($s['shipment_number']) ?></span></td>
                    <td><?= e($s['order_number']) ?></td>
                    <td style="font-size:12px;color:var(--muted);"><?= e($s['full_name'] ?: $s['username'] ?: '—') ?></td>
                    <td style="font-size:12px;"><?= e($s['driver_name'] ?? '—') ?></td>
                    <td style="font-size:12px;color:var(--muted);"><?= e($s['delivered_at'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ─── Quick Pipeline Guide ─── -->
    <div style="display:flex;gap:16px;flex-wrap:wrap;padding:14px 18px;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;">
        <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">Pipeline</div>
        <div class="status-pipeline" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <span class="pipe-step" style="padding:4px 10px;border-radius:6px;font-size:10px;font-weight:700;background:rgba(255,255,255,0.05);color:var(--muted);">🆕 Unassigned</span>
            <span style="color:var(--muted);">→</span>
            <span class="pipe-step" style="padding:4px 10px;border-radius:6px;font-size:10px;font-weight:700;background:rgba(245,158,11,0.12);color:#f59e0b;">🚛 Loaded</span>
            <span style="color:var(--muted);">→</span>
            <span class="pipe-step" style="padding:4px 10px;border-radius:6px;font-size:10px;font-weight:700;background:rgba(59,130,246,0.12);color:#3b82f6;">🚚 In Transit</span>
            <span style="color:var(--muted);">→</span>
            <span class="pipe-step" style="padding:4px 10px;border-radius:6px;font-size:10px;font-weight:700;background:rgba(52,211,153,0.15);color:#34d399;">✅ Delivered</span>
        </div>
    </div>
</div>
</div>

<!-- ─── Order Detail Modal ─── -->
<div id="orderModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;" onclick="if(event.target===this)closeModal()">
  <div style="background:var(--bg);border:1px solid var(--border);border-radius:14px;width:90%;max-width:580px;max-height:85vh;overflow-y:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <h3 style="margin:0;font-size:1.1rem;">📋 Order Details</h3>
      <button onclick="closeModal()" style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;">✕</button>
    </div>
    <div id="modalBody"></div>
  </div>
</div>

<script>
// Gather data for modal lookups
var modalOrders = <?php
$orderData = [];
foreach ($unassigned as $o) {
    $orderData[] = [
        'id' => $o['id'], 'order_number' => $o['order_number'],
        'total_amount' => $o['total_amount'], 'items' => $o['items'],
        'shipping_address' => $o['shipping_address'], 'created_at' => $o['created_at'],
        'full_name' => $o['full_name'], 'username' => $o['username'], 'phone' => $o['phone'] ?? ''
    ];
}
foreach ($assigned as $s) {
    $orderData[] = [
        'id' => $s['order_id'], 'order_number' => $s['order_number'],
        'total_amount' => $s['total_amount'], 'items' => $s['items'],
        'shipping_address' => $s['shipping_address'], 'created_at' => $s['order_date'],
        'full_name' => $s['full_name'], 'username' => $s['username'], 'phone' => $s['phone'] ?? ''
    ];
}
echo json_encode($orderData);
?>;
const modalDrivers = <?= json_encode($drivers) ?>;

function findOrder(orderId) {
    return modalOrders.find(o => o.id == orderId);
}

function openDetail(orderId, source) {
    const o = findOrder(orderId);
    if (!o) return;
    const items = o.items ? JSON.parse(o.items) : [];
    let html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;padding:12px;background:rgba(255,255,255,0.04);border-radius:10px;">';
    html += '<div><span style="font-size:11px;color:var(--muted);">Order #</span><div style="font-weight:700;">' + o.order_number + '</div></div>';
    html += '<div><span style="font-size:11px;color:var(--muted);">Total</span><div style="font-weight:700;">RM' + Number(o.total_amount || 0).toFixed(2) + '</div></div>';
    html += '<div><span style="font-size:11px;color:var(--muted);">Customer</span><div>' + (o.full_name || o.username || '—') + '</div></div>';
    html += '<div><span style="font-size:11px;color:var(--muted);">Date</span><div>' + (o.created_at || '—') + '</div></div>';
    if (o.phone) html += '<div style="grid-column:span 2;"><span style="font-size:11px;color:var(--muted);">Phone</span><div>' + o.phone + '</div></div>';
    html += '</div>';
    if (o.shipping_address) {
        html += '<div style="margin-bottom:14px;padding:12px;background:rgba(255,255,255,0.04);border-radius:10px;">';
        html += '<div style="font-size:11px;color:var(--muted);margin-bottom:4px;">📍 Shipping Address</div>';
        html += '<div>' + o.shipping_address + '</div></div>';
    }
    if (items.length) {
        html += '<table class="activity-table" style="margin-bottom:10px;font-size:13px;"><thead><tr><th>Product</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Price</th><th style="text-align:right;">Subtotal</th></tr></thead><tbody>';
        items.forEach(function(item) {
            html += '<tr><td>' + (item.product_name || '—') + '</td><td style="text-align:center;">' + (item.qty || 0) + '</td><td style="text-align:right;">RM' + Number(item.unit_price || item.price || 0).toFixed(2) + '</td><td style="text-align:right;font-weight:700;">RM' + Number(item.subtotal || 0).toFixed(2) + '</td></tr>';
        });
        html += '</tbody></table>';
    }
    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('orderModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('orderModal').style.display = 'none';
}
</script>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
