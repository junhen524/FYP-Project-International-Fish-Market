<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};
require_once __DIR__ . '/includes/bootstrap.php';

$code = $_GET['code'] ?? '';
$error = '';
$order = null;
$actionMsg = '';
$actionType = '';
$pinVerified = false;

if (!$code) {
    $error = 'Invalid QR code.';
} else {
    $order = dbGetRow("SELECT * FROM export_orders WHERE delivery_qr_code = ?", [$code]);
    if (!$order) {
        $error = 'Order not found. This QR code may be invalid.';
    } elseif ($order['delivery_qr_used']) {
        $error = 'This QR code has already been used.';
        $order = null;
    } elseif ($order['stage'] !== 'delivery') {
        $error = 'This order is not ready for delivery confirmation yet. Current status: ' . e($order['stage']);
        $order = null;
    }
}

// ── Handle PIN verification ──
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_pin') {
    $enteredPin = trim($_POST['pin'] ?? '');
    if ($enteredPin === $order['delivery_pin']) {
        $pinVerified = true;
    } else {
        $error = 'Incorrect PIN. Please check the 6-digit code and try again.';
    }
}

// ── Handle Accept/Reject (only after PIN verified) ──
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['accept', 'reject'])) {
    // Re-verify PIN
    $enteredPin = trim($_POST['pin'] ?? '');
    if ($enteredPin !== $order['delivery_pin']) {
        $error = 'Incorrect PIN. Please try again.';
    } else {
        $action = $_POST['action'];
        if ($action === 'accept') {
            $affected = dbExecute("UPDATE export_orders SET stage = 'delivered', delivery_qr_used = 1, delivered_at = NOW() WHERE id = ? AND delivery_qr_used = 0", [$order['id']]);
            if ($affected === 0) { $error = 'This order has already been confirmed.'; $order = null; }
            else {
                dbExecute("UPDATE export_shipment SET status = 'delivered', delivered_at = NOW(), updated_at = NOW() WHERE order_id = ?", [$order['id']]);
                $actionMsg = '✅ Delivery Confirmed! Thank you.';
                $actionType = 'accept';
                $order['stage'] = 'delivered';
                $order['delivery_qr_used'] = 1;
            }
        } elseif ($action === 'reject') {
            $reason = trim($_POST['reason'] ?? 'No reason provided');
            dbExecute("UPDATE export_orders SET stage = 'rejected', delivery_qr_used = 1, rejected_reason = ? WHERE id = ?", [$reason, $order['id']]);
            dbExecute("UPDATE export_shipment SET status = 'rejected', updated_at = NOW() WHERE order_id = ?", [$order['id']]);
            $actionMsg = '❌ Delivery Rejected.';
            $actionType = 'reject';
            $order['stage'] = 'rejected';
            $order['delivery_qr_used'] = 1;
        }
    }
}

$items = $order ? json_decode($order['items'] ?? '[]', true) : [];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width,initial-scale=1" name="viewport">
<title>Delivery Confirmation — International Fish Market</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/track.css?v=<?= urlencode($__ifmAssetVersion('css/track.css')) ?>"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,-apple-system,sans-serif;background:#f8fafc;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{max-width:420px;width:100%}
.pin-input{font-family:'JetBrains Mono',monospace;font-size:32px;letter-spacing:12px;text-align:center;width:100%;padding:16px;border:2px solid #e2e8f0;border-radius:16px;outline:none;transition:border-color .2s}
.pin-input:focus{border-color:#0d9488;box-shadow:0 0 0 3px rgba(13,148,136,0.1)}
.pin-input::placeholder{letter-spacing:2px;font-size:14px;color:#cbd5e1}
</style>
</head>
<body>
<div class="card">

<?php if ($actionMsg): ?>
<div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-lg text-center" style="animation:fadeIn .4s">
  <div style="font-size:64px;margin-bottom:16px"><?= $actionType === 'accept' ? '🎉' : '😞' ?></div>
  <h1 class="font-display font-black text-xl text-slate-900 mb-2" style="letter-spacing:-0.03em">
    <?= $actionType === 'accept' ? 'Delivery Confirmed!' : 'Delivery Rejected' ?>
  </h1>
  <p class="text-slate-500 text-sm mb-6"><?= e($actionMsg) ?></p>
  <a href="/" class="inline-block px-6 py-3 bg-slate-900 text-white rounded-xl text-xs font-bold uppercase tracking-widest no-underline hover:bg-slate-800 transition-all">Back to Home</a>
</div>

<?php elseif ($error && !$order): ?>
<div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-lg text-center">
  <div style="font-size:48px;margin-bottom:16px">⚠️</div>
  <h1 class="font-display font-black text-lg text-slate-900 mb-2" style="letter-spacing:-0.03em">Oops</h1>
  <p class="text-slate-500 text-sm"><?= e($error) ?></p>
</div>

<?php elseif ($order && !$pinVerified && !isset($_POST['action'])): ?>
<!-- PIN Entry Screen -->
<div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-lg text-center">
  <div style="font-size:48px;margin-bottom:12px">🔐</div>
  <h1 class="font-display font-black text-xl text-slate-900 mb-1" style="letter-spacing:-0.03em">Enter PIN</h1>
  <p class="text-slate-400 text-xs font-mono mb-6 uppercase tracking-wider">Order #<?= e($order['order_number']) ?></p>
  
  <form method="post" id="pin-form" style="space-y-4">
    <input type="hidden" name="action" value="verify_pin">
    <input type="text" name="pin" class="pin-input" placeholder="000000" maxlength="6" inputmode="numeric" autocomplete="off" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6);document.getElementById('pin-submit').disabled=this.value.length<6">
    <?php if ($error): ?>
    <p class="text-red-500 text-xs font-mono mt-2"><?= e($error) ?></p>
    <?php endif; ?>
    <button type="submit" id="pin-submit" disabled class="w-full mt-4 py-3.5 bg-slate-900 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all disabled:opacity-30 disabled:cursor-not-allowed hover:bg-slate-800 cursor-pointer" style="border:none">Verify PIN</button>
  </form>
  <p class="text-[9px] font-mono text-slate-400 mt-4 uppercase tracking-wider">Enter the 6-digit PIN shown on your order</p>
</div>

<?php elseif ($order && $pinVerified): ?>
<!-- Accept/Reject Screen (PIN verified) -->
<div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-lg">
  <div style="text-align:center;margin-bottom:20px">
    <div style="font-size:48px;margin-bottom:8px">📦</div>
    <h1 class="font-display font-black text-xl text-slate-900" style="letter-spacing:-0.03em">Delivery Confirmation</h1>
    <p class="font-mono text-[10px] text-slate-400 mt-1 uppercase tracking-wider">Order #<?= e($order['order_number']) ?></p>
  </div>

  <div style="background:#f1f5f9;border-radius:16px;padding:16px;margin-bottom:20px">
    <h4 class="font-mono text-[9px] text-slate-400 uppercase tracking-widest font-bold mb-3">🛒 Items</h4>
    <?php foreach ($items as $i): ?>
    <div class="flex items-center justify-between py-2 border-b border-slate-200/50 last:border-b-0">
      <div>
        <div class="font-mono text-[12px] font-bold text-slate-800"><?= e($i['product_name'] ?? '') ?></div>
        <div class="font-mono text-[9px] text-slate-400">× <?= (int)($i['quantity'] ?? 0) ?><?= !empty($i['tier_label']) ? ' (' . e($i['tier_label']) . ')' : '' ?></div>
      </div>
      <div class="font-mono text-[12px] font-bold text-slate-800">$<?= number_format((float)($i['subtotal'] ?? 0), 2) ?></div>
    </div>
    <?php endforeach; ?>
    <div class="flex justify-between pt-3 mt-2 border-t-2 border-slate-300">
      <span class="font-mono text-[10px] text-slate-500 uppercase tracking-wider font-bold">Total</span>
      <span class="font-display font-black text-lg text-slate-900">$<?= number_format((float)$order['total_amount'], 2) ?></span>
    </div>
  </div>

  <div style="background:#f1f5f9;border-radius:16px;padding:16px;margin-bottom:20px">
    <h4 class="font-mono text-[9px] text-slate-400 uppercase tracking-widest font-bold mb-2">🚢 Shipping</h4>
    <p class="font-mono text-[11px] text-slate-700 font-semibold"><?= e($order['destination_country'] ?? 'N/A') ?></p>
    <p class="font-mono text-[9px] text-slate-400 mt-1">Terms: <?= e($order['shipping_terms'] ?? 'FOB') ?></p>
  </div>

  <form method="post" id="delivery-form">
    <input type="hidden" name="pin" value="<?= e($order['delivery_pin']) ?>">
    <div id="reject-reason" style="display:none;margin-bottom:12px">
      <label class="font-mono text-[9px] text-slate-400 uppercase tracking-widest font-bold">Reason for rejection</label>
      <textarea name="reason" rows="2" class="w-full mt-1 px-3 py-2.5 border border-red-200 rounded-xl text-[13px] font-sans outline-none transition-all focus:border-red-400 focus:ring-2 focus:ring-red-100" placeholder="Tell us why..."></textarea>
    </div>
    <div style="display:flex;gap:10px">
      <button type="submit" name="action" value="accept" class="flex-1 py-3.5 bg-emerald-600 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all cursor-pointer hover:bg-emerald-700" style="border:none">✅ Accept</button>
      <button type="button" id="reject-btn" onclick="showReject()" class="flex-1 py-3.5 bg-white text-red-600 rounded-xl font-bold text-xs uppercase tracking-widest transition-all cursor-pointer hover:bg-red-50" style="border:2px solid #fecaca">❌ Reject</button>
    </div>
    <div id="reject-confirm" style="display:none">
      <button type="submit" name="action" value="reject" class="w-full py-3 bg-red-600 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all cursor-pointer hover:bg-red-700" style="border:none">Confirm Reject Delivery</button>
    </div>
  </form>
  <p class="text-center text-[9px] font-mono text-slate-400 mt-4 uppercase tracking-wider">
    By accepting, you confirm receipt of goods in good condition
  </p>
</div>

<script>
function showReject() {
  document.getElementById('reject-reason').style.display = 'block';
  document.getElementById('reject-confirm').style.display = 'block';
  document.getElementById('reject-btn').style.display = 'none';
}
</script>
<?php endif; ?>

<style>
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
</style>
</div>
<script src="js/app.js?v=<?= urlencode($__ifmAssetVersion('js/app.js')) ?>"></script>
</body>
</html>