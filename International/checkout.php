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

$user = intl_user() ?? intl_restaurant_user();
$isRestaurantUser = isset($_SESSION['ifm_restaurant_id']);
$restaurantDiscount = intl_restaurant_discount();
$items = intl_cart_items();
$cartTotal = intl_cart_total();
$balance = intl_wallet_balance();

if (empty($items)) {
    header('Location: ' . url_for('cart'));
    exit;
}

// ── Handle order ──
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $sameAddress = !isset($_POST['different_address']) || $_POST['different_address'] !== '1';
    $phone = trim($_POST['phone'] ?? ($user['phone'] ?? ''));
    $country = trim($_POST['destination_country'] ?? ($user['country_code'] ?? ''));
    $address = '';

    if ($sameAddress) {
        $address = trim($user['address'] ?? '');
    } else {
        $address = trim($_POST['shipping_address'] ?? '');
        $country = trim($_POST['alt_country'] ?? $country);
        $phone = trim($_POST['alt_phone'] ?? $phone);
    }

    if (!$address || !$country) {
        $error = 'Please fill in all required shipping fields.';
    } elseif ($balance < $cartTotal) {
        $error = 'Insufficient wallet balance. Please top up.';
    } else {
        try {
            $orderNum = intl_generate_order_number();
            $qrCode = intl_generate_delivery_qr();
            $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $itemsJson = json_encode(array_map(fn($k, $v) => [
                'product_name' => $v['product_name'],
                'slug' => $v['slug'],
                'quantity' => $v['quantity'],
                'unit_price' => $v['unit_price'],
                'unit' => $v['unit'] ?? 'kg',
                'subtotal' => $v['subtotal'],
                'tier_label' => $v['tier_label'] ?? null,
            ], array_keys($items), $items));

            $walletId = intl_ensure_wallet();
            $before = $balance;
            $after = $before - $cartTotal;

            db()->beginTransaction();

            // Check stock availability for kg tier items only
            foreach ($items as $item) {
                $slug = $item['slug'] ?? '';
                $qty = (int)($item['quantity'] ?? 0);
                $tier = $item['tier_label'] ?? '';
                if ($slug && $qty > 0 && $tier) {
                    $stockCol = match ($tier) { '3kg' => 'tier_3kg_stock', '6kg' => 'tier_6kg_stock', '10kg' => 'tier_10kg_stock', default => null };
                    if ($stockCol) {
                        $avail = (int)dbGetValue("SELECT $stockCol FROM product WHERE slug = ?", [$slug]);
                        if ($avail < $qty) {
                            throw new Exception("Not enough $tier stock for " . e($item['product_name']) . ". Available: $avail, requested: $qty.");
                        }
                    }
                }
            }
            $ownerId = $isRestaurantUser ? null : (int)$user['id'];
            $restId  = $isRestaurantUser ? (int)$user['id'] : null;
            dbExecute("INSERT INTO export_orders (order_number, user_id, restaurant_id, wallet_id, stage, total_amount, currency, destination_country, shipping_terms, notes, delivery_qr_code, delivery_pin, items, ordered_at, created_at, updated_at) VALUES (?, ?, ?, ?, 'processing', ?, 'USD', ?, 'FOB', ?, ?, ?, ?, NOW(), NOW(), NOW())",
                [$orderNum, $ownerId, $restId, $walletId, $cartTotal, $country, $address, $qrCode, $pin, $itemsJson]);
            dbExecute("UPDATE export_wallets SET balance = ?, updated_at = NOW() WHERE id = ?", [$after, $walletId]);
            dbExecute("INSERT INTO export_wallet_txn (wallet_id, transaction_type, amount, balance_before, balance_after, description, status, created_at) VALUES (?, 'payment', ?, ?, ?, 'Payment for order #$orderNum', 'completed', NOW())",
                [$walletId, $cartTotal, $before, $after]);
            // Deduct stock for kg tier items only
            $orderItems = json_decode($itemsJson, true);
            foreach ($orderItems as $item) {
                $slug = $item['slug'] ?? '';
                $qty = (int)($item['quantity'] ?? 0);
                $tier = $item['tier_label'] ?? '';
                if ($slug && $qty > 0 && $tier) {
                    $stockCol = match ($tier) { '3kg' => 'tier_3kg_stock', '6kg' => 'tier_6kg_stock', '10kg' => 'tier_10kg_stock', default => null };
                    if ($stockCol) {
                        dbExecute("UPDATE product SET $stockCol = GREATEST($stockCol - ?, 0) WHERE slug = ?", [$qty, $slug]);
                    }
                }
            }
            db()->commit();

            intl_save_cart([]);
            header('Location: ' . url_for('orders'));
            exit;
        } catch (Exception $e) {
            db()->rollBack();
            $error = 'Checkout failed: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width,initial-scale=1" name="viewport">
<title>Checkout — International Fish Market</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{'brand-blue':'#0369a1','stone-150':'#e8e5e0','stone-250':'#d6d2cb','stone-350':'#b8b2a8','amber-350':'#d9995b'},fontFamily:{display:['Inter','system-ui','sans-serif'],mono:['JetBrains Mono','monospace']}}}}
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/checkout.css?v=<?= urlencode($__ifmAssetVersion('css/checkout.css')) ?>"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;overflow-x:hidden}
body{overflow-x:hidden;font-family:'Inter',system-ui,-apple-system,sans-serif}
.font-display{font-family:'Inter',system-ui,-apple-system,sans-serif;letter-spacing:-0.03em}
.toast{position:fixed;top:24px;right:24px;z-index:9999;padding:14px 20px;border-radius:12px;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;box-shadow:0 8px 30px rgba(0,0,0,0.12);max-width:400px;animation:toastIn .3s ease;cursor:pointer}
.toast-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
@keyframes toastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
@keyframes toastOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(40px)}}
</style>
</head>
<body>
<div id="root">
  <div class="relative min-h-screen bg-stone-100 text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip">
    <header id="main-app-header" class="fixed top-0 left-0 w-full z-50 transition-all duration-300 bg-stone-100 py-5">
      <div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center">
        <a href="<?= url_for('index') ?>" class="cursor-pointer flex items-center space-x-2 group" id="brand-logo-trigger">
          <span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">INTERNATIONAL FISH MARKET</span>
          <span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse"></span>
        </a>
        <div class="flex items-center space-x-4">
          <a href="<?= url_for('cart') ?>" class="flex items-center space-x-1 font-display text-[10px] tracking-widest uppercase text-stone-500 hover:text-stone-950 transition-all no-underline font-bold">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
            <span>Back to Cart</span>
          </a>
          <?php require __DIR__ . '/includes/nav_bar.php'; ?>
        </div>
      </div>
    </header>

    <!-- Checkout Content -->
    <div id="subpage-viewport"><div class="pt-28 pb-24 px-4 md:px-8">
  <div class="max-w-4xl mx-auto space-y-8">

    <!-- Header -->
    <div class="border-b border-slate-200 pb-5 flex items-center space-x-3">
      <div class="p-2 bg-slate-900 text-slate-50 rounded-xl shadow-lg">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      </div>
      <div>
        <h1 class="font-display font-black text-2xl text-slate-900 tracking-tight uppercase">Checkout</h1>
        <p class="text-[10px] text-slate-400 font-mono uppercase tracking-widest mt-0.5">Review your order &amp; confirm purchase</p>
      </div>
    </div>

    <form method="post" id="checkout-form">
      <input type="hidden" name="action" value="place_order">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- LEFT COLUMN: Shipping -->
        <div class="space-y-6">

          <!-- Default Address (user's saved) -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
            <h2 class="font-display font-black text-sm text-slate-900 uppercase tracking-wider mb-4 flex items-center gap-2">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-slate-400"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              Shipping Information
            </h2>

            <!-- Saved Address Card -->
            <div id="default-address" class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-2">
              <div class="flex items-center justify-between">
                <span class="font-mono text-[9px] text-slate-400 uppercase tracking-widest font-bold">Your Saved Address</span>
                <span class="bg-emerald-100 text-emerald-700 font-mono text-[8px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-full">Default</span>
              </div>
              <div class="font-mono text-[11px] text-slate-700 font-semibold leading-relaxed">
                <?= e(($user['address'] ?? '') ?: 'No address saved yet') ?>
              </div>
              <div class="flex gap-4 text-[10px] font-mono text-slate-500">
                <span>📞 <?= e($user['phone'] ?? '-') ?></span>
                <span>🌍 <?= e($user['country_code'] ?? '-') ?></span>
              </div>
            </div>

            <!-- Different Address Toggle -->
            <div class="mt-4 flex items-center space-x-2">
              <input type="checkbox" id="different-address" name="different_address" value="1" class="w-4 h-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer" onchange="toggleAltAddress(this)">
              <label for="different-address" class="font-mono text-[10px] text-slate-500 uppercase tracking-wider font-bold cursor-pointer select-none">Ship to a different address</label>
            </div>

            <!-- Alternate Address Fields (hidden by default) -->
            <div id="alt-address-fields" class="mt-4 space-y-3" style="display:none">
              <hr class="border-slate-100">
              <p class="font-mono text-[9px] text-amber-600 uppercase tracking-widest font-bold">Alternate Shipping Address</p>
              <div>
                <label class="font-mono text-[9px] text-slate-400 uppercase tracking-widest font-bold">Full Address *</label>
                <textarea name="shipping_address" rows="3" class="w-full mt-1 px-3 py-2.5 border border-slate-200 rounded-xl text-[13px] font-sans outline-none transition-all focus:border-amber-400 focus:ring-2 focus:ring-amber-100" placeholder="Street, city, state, postal code"></textarea>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="font-mono text-[9px] text-slate-400 uppercase tracking-widest font-bold">Country *</label>
                  <input type="text" name="alt_country" class="w-full mt-1 px-3 py-2.5 border border-slate-200 rounded-xl text-[13px] font-sans outline-none transition-all focus:border-amber-400 focus:ring-2 focus:ring-amber-100" placeholder="e.g. Japan">
                </div>
                <div>
                  <label class="font-mono text-[9px] text-slate-400 uppercase tracking-widest font-bold">Phone</label>
                  <input type="text" name="alt_phone" class="w-full mt-1 px-3 py-2.5 border border-slate-200 rounded-xl text-[13px] font-sans outline-none transition-all focus:border-amber-400 focus:ring-2 focus:ring-amber-100" placeholder="Contact number">
                </div>
              </div>
            </div>

            <!-- Hidden fields for default address -->
            <input type="hidden" name="phone" value="<?= e($user['phone'] ?? '') ?>">
            <input type="hidden" name="destination_country" value="<?= e($user['country_code'] ?? '') ?>">
          </div>

          <!-- Wallet Balance -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
            <h2 class="font-display font-black text-sm text-slate-900 uppercase tracking-wider mb-4 flex items-center gap-2">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-slate-400"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
              Payment
            </h2>
            <div class="flex items-center justify-between p-4 bg-slate-50 border border-slate-200 rounded-xl">
              <div>
                <div class="font-mono text-[9px] text-slate-400 uppercase tracking-widest font-bold">Wallet Balance</div>
                <div class="font-display font-black text-xl text-slate-900 mt-1">$<?= formatted_money($balance) ?></div>
              </div>
              <?php if ($balance < $cartTotal): ?>
              <a href="<?= url_for('wallet') ?>" class="px-4 py-2 bg-amber-500 text-white rounded-xl text-[10px] font-bold uppercase tracking-widest no-underline hover:bg-amber-600 transition-all">Top Up</a>
              <?php else: ?>
              <div class="bg-emerald-100 text-emerald-700 font-mono text-[9px] uppercase tracking-widest font-bold px-3 py-1.5 rounded-full">Sufficient</div>
              <?php endif; ?>
            </div>
          </div>

        </div>

        <!-- RIGHT COLUMN: Order Summary -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm">
          <h2 class="font-display font-black text-sm text-slate-900 uppercase tracking-wider mb-4 flex items-center gap-2">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-slate-400"><path d="M16 10a4 4 0 0 1-8 0"/><path d="M3.103 6.034h17.794"/><path d="M3.4 5.467a2 2 0 0 0-.4 1.2V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.667a2 2 0 0 0-.4-1.2l-2-2.667A2 2 0 0 0 17 2H7a2 2 0 0 0-1.6.8z"/></svg>
            Order Summary
          </h2>

          <div class="space-y-3 mb-4">
            <?php foreach ($items as $item): ?>
            <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-b-0">
              <div class="flex-1 min-w-0">
                <div class="font-mono text-[12px] font-bold text-slate-800 truncate"><?= e($item['product_name']) ?></div>
                <div class="font-mono text-[9px] text-slate-400 uppercase tracking-wider">
                  × <?= (int)$item['quantity'] ?> <?= isset($item['tier_kg']) ? '(' . e($item['tier_kg']) . ')' : '' ?>
                </div>
              </div>
              <div class="font-mono text-[12px] font-bold text-slate-800 ml-4">$<?= formatted_money((float)$item['subtotal']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="border-t-2 border-slate-200 pt-4 space-y-2">
            <div class="flex justify-between font-mono text-[10px] text-slate-500">
              <span>Subtotal</span>
              <span>$<?= formatted_money($cartTotal) ?></span>
            </div>
            <?php if ($isRestaurantUser && $restaurantDiscount > 0): ?>
            <div class="flex justify-between font-mono text-[10px] text-rose-500 font-bold">
              <span>🏪 Restaurant Discount (<?= e(number_format($restaurantDiscount,0)) ?>%)</span>
              <span>-<?= e(number_format($restaurantDiscount,0)) ?>% applied to items</span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between font-mono text-[10px] text-slate-500">
              <span>Shipping</span>
              <span class="text-emerald-600 font-bold">FOB — Free</span>
            </div>
            <div class="flex justify-between font-display font-black text-lg text-slate-900 pt-2 border-t border-slate-100">
              <span>Total</span>
              <span>$<?= formatted_money($cartTotal) ?></span>
            </div>
          </div>

          <!-- Confirm Button -->
          <button type="submit" id="place-order-btn" class="w-full mt-6 py-3.5 bg-slate-900 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all cursor-pointer hover:bg-slate-800 disabled:opacity-30 disabled:cursor-not-allowed" <?= $balance < $cartTotal ? 'disabled' : '' ?>>
            <?php if ($balance < $cartTotal): ?>
            ❌ Insufficient Balance
            <?php else: ?>
            🌊 Confirm &amp; Place Order
            <?php endif; ?>
          </button>

          <?php if ($balance >= $cartTotal): ?>
          <p class="text-center text-[9px] font-mono text-slate-400 mt-3 uppercase tracking-wider">
            By placing this order, you agree to our Terms &amp; Conditions
          </p>
          <?php endif; ?>
        </div>

      </div>
    </form>

  </div> <!-- max-w-4xl -->
  </div> <!-- pt-28 -->
  </div> <!-- subpage-viewport -->
  </div> <!-- bg-stone-100 -->
</div> <!-- root -->

<script>
function toggleAltAddress(cb) {
  var fields = document.getElementById('alt-address-fields');
  fields.style.display = cb.checked ? 'block' : 'none';
}
</script>
<?php if ($error): ?>
<div class="toast toast-error" onclick="this.style.animation='toastOut .3s ease forwards';setTimeout(function(){this.style.display='none'}.bind(this),300)"><?= e($error) ?></div>
<script>(function(){var t=document.querySelector('.toast');if(t){setTimeout(function(){t.style.animation='toastOut .3s ease forwards';setTimeout(function(){t.style.display='none'},300)},3000)}})();</script>
<?php endif; ?>
<script src="js/app.js?v=<?= urlencode($__ifmAssetVersion('js/app.js')) ?>"></script>
</body>
</html>