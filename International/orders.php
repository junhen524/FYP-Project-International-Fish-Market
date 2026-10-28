<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};
require_once __DIR__ . '/includes/bootstrap.php';
intl_require_login();

$user = intl_current_user();
if (!$user) {
    header('Location: ' . url_for('login'));
    exit;
}
$isRestaurantUser = isset($_SESSION['ifm_restaurant_id']);
if ($isRestaurantUser) {
    $orders = dbGetAll("SELECT * FROM export_orders WHERE restaurant_id = ? ORDER BY created_at DESC", [(int)$user['id']]);
} else {
    $orders = dbGetAll("SELECT * FROM export_orders WHERE user_id = ? ORDER BY created_at DESC", [(int)$user['id']]);
}
$balance = intl_wallet_balance();
$walletId = intl_ensure_wallet();
$txns = dbGetAll("SELECT * FROM export_wallet_txn WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 5", [$walletId]);

function ord_status_class(string $stage): string {
    return match ($stage) {
        'confirmed' => 'bg-amber-100 text-amber-700',
        'processing' => 'bg-sky-100 text-sky-700',
        'paid' => 'bg-blue-100 text-blue-700',
        'shipping' => 'bg-sky-100 text-sky-700',
        'delivery' => 'bg-violet-100 text-violet-700',
        'delivered' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-600',
    };
}
function ord_status_icon(string $stage): string {
    return match ($stage) {
        'confirmed' => '📋',
        'processing' => '⚙️',
        'paid' => '💳',
        'shipping' => '🚢',
        'delivery' => '🚚',
        'delivered' => '✅',
        'rejected' => '❌',
        'cancelled' => '❌',
        default => '📦',
    };
}
function ord_status_label(string $stage): string {
    return match ($stage) {
        'confirmed' => 'Preparing to Ship',
        'processing' => 'Processing',
        'paid' => 'Paid',
        'shipping' => 'Shipping',
        'delivery' => 'Delivery',
        'delivered' => 'Confirmed Delivered',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        default => ucfirst($stage),
    };
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width,initial-scale=1" name="viewport">
<title>My Orders — International Fish Market</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          'brand-blue': '#0369a1',
          'stone-150': '#e8e5e0',
          'stone-250': '#d6d2cb',
          'stone-350': '#b8b2a8',
          'amber-350': '#d9995b',
        },
        fontFamily: {
          display: ['Inter', 'system-ui', 'sans-serif'],
          mono: ['JetBrains Mono', 'monospace'],
        },
      },
    },
  };
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/orders.css?v=<?= urlencode($__ifmAssetVersion('css/orders.css')) ?>"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;overflow-x:hidden}
body{overflow-x:hidden;font-family:'Inter',system-ui,-apple-system,sans-serif}
.font-display{font-family:'Inter',system-ui,-apple-system,sans-serif;letter-spacing:-0.03em}
</style>
</head>
<body>
<div id="root">
  <div class="relative min-h-screen bg-transparent text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip">
    <header id="main-app-header" class="fixed top-0 left-0 w-full z-50 transition-all duration-300 bg-transparent py-5">
  <div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center">
    <a href="<?= url_for('index') ?>" class="cursor-pointer flex items-center space-x-2 group" id="brand-logo-trigger">
      <span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">INTERNATIONAL FISH MARKET</span>
      <span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse"></span>
    </a>
    <?php require __DIR__ . '/includes/nav_bar.php'; ?>
  </div>
</header>
    <div id="subpage-viewport">
      <div class="min-h-screen bg-stone-100/50 pt-28 pb-24 px-4 md:px-8">
  <div class="max-w-5xl mx-auto space-y-8">

    <!-- Header -->
    <div class="border-b border-stone-250 pb-5 flex items-center space-x-3">
      <div class="p-2 bg-stone-900 text-stone-50 rounded-xl shadow-lg">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
      </div>
      <div>
        <h1 class="font-display font-black text-2xl text-stone-900 tracking-tight uppercase">My Orders</h1>
        <p class="text-[10px] text-stone-500 font-mono uppercase tracking-widest mt-0.5">Track your purchases &amp; order history</p>
      </div>
    </div>

    <!-- Wallet Summary Bar -->
    <div class="bg-white border border-stone-200/80 rounded-2xl p-5 shadow-sm flex items-center justify-between">
      <div>
        <div class="font-mono text-[9px] text-stone-500 uppercase tracking-widest font-bold">Wallet Balance</div>
        <div class="font-display font-black text-2xl mt-1 tracking-tight text-slate-900">$<?= formatted_money($balance) ?></div>
      </div>
      <a href="<?= url_for('wallet') ?>" class="px-4 py-2 bg-brand-blue text-white rounded-xl text-[10px] font-bold uppercase tracking-widest no-underline hover:opacity-90 transition-all">Manage Wallet</a>
    </div>

    <!-- Orders -->
    <?php if ($orders): ?>
    <div class="space-y-4">
      <?php foreach ($orders as $o):
        $items = json_decode($o['items'] ?? '[]', true);
        $statusClass = ord_status_class($o['stage']);
        $oId = (int)$o['id'];
      ?>
      <div class="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all cursor-pointer" data-order-id="<?= $oId ?>">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
          <div class="flex items-center gap-3">
            <div class="font-display font-black text-base text-stone-900">#<?= e($o['order_number']) ?></div>
            <span class="font-mono text-[9px] text-stone-400 uppercase tracking-wider"><?= e(date('M d, Y H:i', strtotime($o['created_at']))) ?></span>
          </div>
          <div class="flex items-center gap-3">
            <span class="font-mono text-[10px] font-bold uppercase tracking-wider px-3 py-1 rounded-full <?= $statusClass ?>"><?= ord_status_icon($o['stage']) ?> <?= ord_status_label($o['stage']) ?></span>
            <span class="font-display font-black text-lg text-stone-900">$<?= formatted_money((float)$o['total_amount']) ?></span>
          </div>
        </div>

        <?php if ($items): ?>
        <div class="border-t border-stone-100 pt-3 space-y-2">
          <?php foreach (array_slice($items, 0, 3) as $i): ?>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 min-w-0">
              <span class="w-1.5 h-1.5 rounded-full bg-sky-400 shrink-0"></span>
              <span class="font-mono text-[12px] text-stone-700 font-semibold truncate"><?= e($i['product_name'] ?? '') ?><?= !empty($i['tier_label']) ? ' <span class="text-stone-400">(' . e($i['tier_label']) . ')</span>' : '' ?></span>
            </div>
            <span class="font-mono text-[10px] text-stone-400 shrink-0 ml-4">× <?= (int)($i['quantity'] ?? 0) ?></span>
          </div>
          <?php endforeach; ?>
          <?php if (count($items) > 3): ?>
          <div class="font-mono text-[9px] text-stone-400 text-center pt-1">+ <?= count($items) - 3 ?> more item(s)</div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="flex items-center gap-3 mt-4 pt-3 border-t border-stone-100">
          <div class="flex items-center gap-1.5 text-[10px] font-mono text-stone-400">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span><?= e($o['destination_country'] ?: 'N/A') ?></span>
          </div>
          <span class="ml-auto font-mono text-[9px] text-sky-500 uppercase tracking-wider font-bold">Click to view details →</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Empty State -->
    <div class="text-center py-20">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d6d3d1" stroke-width="1.5" class="mx-auto mb-4"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
      <p class="text-stone-600 font-mono text-xs uppercase tracking-widest font-bold">No orders yet</p>
      <p class="text-stone-400 text-[10px] font-mono mt-1">Start shopping to see your orders here</p>
      <a href="<?= url_for('shop') ?>" class="inline-block mt-6 px-5 py-2.5 bg-brand-blue text-white rounded-xl text-[10px] font-bold uppercase tracking-widest no-underline hover:opacity-90 transition-all">Browse Shop</a>
    </div>
    <?php endif; ?>

    <!-- Recent Wallet Activity -->
    <?php if ($txns): ?>
    <div class="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-sm">
      <h2 class="font-display font-black text-sm text-stone-900 uppercase tracking-wider mb-4 flex items-center gap-2">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-stone-400"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
        Recent Wallet Activity
      </h2>
      <div class="space-y-1">
        <?php foreach ($txns as $t): ?>
        <div class="flex items-center justify-between py-2.5 border-b border-stone-100 last:border-b-0">
          <div class="flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full <?= $t['transaction_type']==='topup'?'bg-emerald-400':'bg-red-400' ?> shrink-0"></span>
            <div>
              <span class="font-mono text-[11px] font-bold text-stone-700 capitalize"><?= e($t['transaction_type']) ?></span>
              <span class="font-mono text-[9px] text-stone-400 ml-2"><?= e(date('M d', strtotime($t['created_at']))) ?></span>
            </div>
          </div>
          <span class="font-mono text-[12px] font-bold <?= $t['transaction_type']==='topup'?'text-emerald-600':'text-red-600' ?>">
            <?= $t['transaction_type']==='topup'?'+':'-' ?>$<?= formatted_money((float)$t['amount']) ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <a href="<?= url_for('wallet') ?>" class="inline-block mt-3 font-mono text-[10px] text-sky-600 font-bold uppercase tracking-wider no-underline hover:text-sky-700 transition-all">View all transactions →</a>
    </div>
    <?php endif; ?>

    </div> <!-- max-w-5xl -->
    </div> <!-- subpage-viewport -->
  </div> <!-- outer bg-stone-100 wrapper -->
</div> <!-- root -->

<!-- Order Detail Modal -->
<div id="order-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);overflow-y:auto;padding:80px 20px" onclick="if(event.target===this)closeOrderDetail()">
  <div style="max-width:560px;margin:0 auto;background:white;border-radius:24px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,0.15)">
    <div style="padding:28px" id="order-modal-content">
      <!-- Populated by JS -->
    </div>
  </div>
</div>

<?php
try {
    $__tmpOrders = $orders ? array_map(function($o) {
        $items = json_decode($o['items'] ?? '[]', true);
        foreach ($items as &$item) {
            $slug = $item['slug'] ?? '';
            if ($slug) {
                $img = dbGetValue("SELECT image_url FROM product WHERE slug = ? AND is_active = 1 LIMIT 1", [$slug]);
                if ($img && $img[0] !== '/' && !preg_match('#^https?://#i', $img)) {
                    $img = '/assets/products/' . basename(str_replace('\\', '/', $img));
                }
                $item['img_url'] = $img ?: '';
            } else {
                $item['img_url'] = '';
            }
        }
        unset($item);
        return [
            'id' => (int)$o['id'],
            'order_number' => $o['order_number'],
            'stage' => $o['stage'],
            'total_amount' => (float)$o['total_amount'],
            'currency' => $o['currency'] ?? 'USD',
            'destination_country' => $o['destination_country'] ?? '',
            'shipping_terms' => $o['shipping_terms'] ?? 'FOB',
            'notes' => $o['notes'] ?? '',
            'items' => $items,
            'delivery_qr_code' => $o['delivery_qr_code'] ?? null,
            'delivery_qr_used' => (int)($o['delivery_qr_used'] ?? 0),
            'delivery_pin' => $o['delivery_pin'] ?? null,
            'qr_track_url' => $o['delivery_qr_code'] ? intl_qr_track_url($o['delivery_qr_code']) : null,
            'created_at' => $o['created_at'],
            'ordered_at' => $o['ordered_at'] ?? $o['created_at'],
        ];
    }, $orders) : [];
    $__ordersJson = json_encode($__tmpOrders, JSON_THROW_ON_ERROR);
} catch (Exception $e) {
    $__ordersJson = '[]';
}
?>
<script>
// ── Store orders data for modal ──
var __orders = <?= $__ordersJson ?>;
var __basePath = '<?= rtrim($__ifmBasePath, '/') ?>/';

function ordIcon(s) {
  var m = {
    confirmed: '📋',
    processing: '⚙️',
    paid: '💳',
    shipping: '🚢',
    delivery: '🚚',
    delivered: '✅',
    rejected: '❌',
    cancelled: '❌',
  };
  return m[s] || '📦';
}

function ordClass(s) {
  var m = {
    confirmed: 'bg-amber-100 text-amber-700',
    processing: 'bg-sky-100 text-sky-700',
    paid: 'bg-blue-100 text-blue-700',
    shipping: 'bg-sky-100 text-sky-700',
    delivery: 'bg-violet-100 text-violet-700',
    delivered: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-red-100 text-red-700',
  };
  return m[s] || 'bg-slate-100 text-slate-600';
}

function ordLabel(s) {
  var m = {
    confirmed: 'Preparing to Ship',
    processing: 'Processing',
    paid: 'Paid',
    shipping: 'Shipping',
    delivery: 'Out for Delivery',
    delivered: 'Confirmed Delivered',
    rejected: 'Rejected',
    cancelled: 'Cancelled',
  };
  return m[s] || s.charAt(0).toUpperCase() + s.slice(1);
}

function openOrderDetail(id) {
  var o = __orders.find(function(x) { return x.id === id; });
  if (!o) return;

  function buildItemHtml(i) {
    var thumb = i.img_url
      ? '<img src="' + escHtml(i.img_url) + '" alt="" style="width:44px;height:44px;border-radius:8px;object-fit:cover;background:#e7e5e4;flex-shrink:0" referrerpolicy="no-referrer">'
      : '<div style="width:44px;height:44px;border-radius:8px;background:#f1f5f9;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:10px">📷</div>';

    return (
      '<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f1f5f9;gap:10px">' +
        thumb +
        '<div style="flex:1;min-width:0">' +
          '<div style="font-weight:700;font-size:13px;color:#1e293b">' + escHtml(i.product_name || '') + '</div>' +
          '<div style="font-size:10px;color:#94a3b8;font-family:monospace;margin-top:2px">' +
            '$' + (i.unit_price || 0).toFixed(2) + ' × ' + (i.quantity || 0) +
            (i.tier_label ? ' <span style="color:#f59e0b">(' + escHtml(i.tier_label) + ')</span>' : '') +
          '</div>' +
        '</div>' +
        '<div style="font-weight:800;font-size:14px;color:#1e293b;white-space:nowrap">$' + (i.subtotal || 0).toFixed(2) + '</div>' +
      '</div>'
    );
  }

  var itemsHtml = (o.items || []).map(buildItemHtml).join('');
  var html =
    '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">' +
      '<div>' +
        '<div style="font-family:monospace;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;font-weight:700">Order #' + escHtml(o.order_number) + '</div>' +
        '<div style="font-family:monospace;font-size:10px;color:#94a3b8;margin-top:2px">' + escHtml(o.created_at) + '</div>' +
      '</div>' +
      '<button onclick="closeOrderDetail()" style="background:none;border:none;cursor:pointer;font-size:22px;color:#94a3b8;padding:0 4px">&times;</button>' +
    '</div>' +
    '<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;padding:12px 16px;background:#f8fafc;border-radius:12px">' +
      '<span class="' + ordClass(o.stage) + '" style="font-family:monospace;font-size:10px;font-weight:700;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px">' + ordIcon(o.stage) + ' ' + ordLabel(o.stage) + '</span>' +
      '<span style="margin-left:auto;font-weight:900;font-size:20px;color:#0f172a">$' + o.total_amount.toFixed(2) + '</span>' +
    '</div>' +
    '<div style="margin-bottom:20px">' +
      '<h4 style="font-family:monospace;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:10px">🛒 Items</h4>' +
      itemsHtml +
    '</div>';
  // ── PIN section (visible from confirmed stage, until delivered/rejected) ──
  if (o.delivery_pin && !['delivered','rejected','cancelled'].includes(o.stage)) {
    html += '<div style="background:#f8fafc;border-radius:12px;padding:16px;margin-bottom:20px;text-align:center">' +
      '<h4 style="font-family:monospace;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:8px">🔐 Delivery PIN</h4>' +
      '<div style="font-family:monospace;font-size:22px;font-weight:900;color:#0f172a;letter-spacing:8px">' + escHtml(o.delivery_pin) + '</div>' +
      '<p style="font-family:monospace;font-size:9px;color:#94a3b8;margin-top:6px">Keep this PIN — you will need it to accept delivery</p>' +
    '</div>';
  }
  // ── QR Code section (only visible when out for delivery) ──
  if (o.delivery_qr_code && o.stage === 'delivery') {
    var qrUrl = o.qr_track_url || (window.location.origin + __basePath + 'track.php?code=' + encodeURIComponent(o.delivery_qr_code));
    var qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(qrUrl);
    html += '<div style="background:#f8fafc;border-radius:12px;padding:16px;margin-bottom:20px;text-align:center">' +
      '<h4 style="font-family:monospace;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:12px">📱 Scan to Accept Delivery</h4>' +
      '<a href="' + qrUrl + '" target="_blank" style="text-decoration:none;display:inline-block">' +
      '<img src="' + qrImg + '" alt="QR" style="width:160px;height:160px;border-radius:12px;border:2px solid #e2e8f0;display:inline-block;cursor:pointer">' +
      '</a>' +
      '<p style="font-family:monospace;font-size:9px;color:#94a3b8;margin-top:8px">Tap the QR code or <a href="' + qrUrl + '" target="_blank" style="color:#0d9488;font-weight:700;text-decoration:underline">click here</a> to accept delivery</p>' +
    '</div>';
  }
  html += '<div style="background:#f8fafc;border-radius:12px;padding:16px;margin-bottom:20px">' +
      '<h4 style="font-family:monospace;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:10px">🚢 Shipping Information</h4>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
        '<div><span style="color:#94a3b8;font-size:10px;font-family:monospace">Country</span><br><span style="font-weight:600;color:#1e293b">' + escHtml(o.destination_country || 'N/A') + '</span></div>' +
        '<div><span style="color:#94a3b8;font-size:10px;font-family:monospace">Terms</span><br><span style="font-weight:600;color:#1e293b">' + escHtml(o.shipping_terms || 'FOB') + '</span></div>' +
      '</div>' +
    '</div>' +
    '<div style="display:flex;justify-content:space-between;align-items:center;padding-top:16px;border-top:1px solid #e2e8f0">' +
      '<div style="font-family:monospace;font-size:10px;color:#94a3b8">Ordered: ' + escHtml(o.ordered_at || o.created_at) + '</div>' +
      '<button onclick="closeOrderDetail()" style="padding:8px 24px;background:#1e293b;color:white;border:none;border-radius:12px;font-size:11px;font-weight:700;cursor:pointer">Close</button>' +
    '</div>';
  document.getElementById('order-modal-content').innerHTML = html;
  document.getElementById('order-modal').style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeOrderDetail() {
  document.getElementById('order-modal').style.display = 'none';
  document.body.style.overflow = '';
}

// Event delegation for order cards
document.addEventListener('click', function(e) {
  var card = e.target.closest('[data-order-id]');
  if (card) openOrderDetail(parseInt(card.getAttribute('data-order-id'), 10));
});

function escHtml(s) {
  if (!s) return '';
  var d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}
</script>
<script src="js/app.js?v=<?= urlencode($__ifmAssetVersion('js/app.js')) ?>"></script>
</body>
</html>