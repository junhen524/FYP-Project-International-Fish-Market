<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};
require_once __DIR__ . '/includes/bootstrap.php';

$user = intl_user();
$restaurantDiscount = intl_restaurant_discount();
$isRestaurantUser = isset($_SESSION['ifm_restaurant_id']);

// Handle remove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $cart = intl_cart_items();
    if ($action === 'remove' && isset($_POST['key'])) {
        unset($cart[$_POST['key']]);
        intl_save_cart($cart);
        header('Location: ' . url_for('cart'));
        exit;
    }
    if ($action === 'update_qty' && isset($_POST['key'])) {
        $qty = max(0, (int)($_POST['quantity'] ?? 0));
        if ($qty <= 0) unset($cart[$_POST['key']]);
        else {
            // Check stock
            $key = $_POST['key'];
            $item = $cart[$key] ?? null;
            if ($item) {
                $slug = $item['slug'] ?? '';
                $tierLabel = $item['tier_label'] ?? '';
                if ($slug && $tierLabel) {
                    $stockCol = match ($tierLabel) { '3kg' => 'tier_3kg_stock', '6kg' => 'tier_6kg_stock', '10kg' => 'tier_10kg_stock', default => null };
                    if ($stockCol) {
                        $avail = (int)dbGetValue("SELECT $stockCol FROM product WHERE slug = ?", [$slug]);
                        if ($qty > $avail) $qty = $avail; // Cap at available stock
                    }
                }
            }
            $cart[$key]['quantity'] = $qty;
            $cart[$key]['subtotal'] = $qty * (float)$cart[$key]['unit_price'];
        }
        intl_save_cart($cart);
        exit;
    }
    if ($action === 'update_cart_item' && isset($_POST['old_key']) && isset($_POST['slug'])) {
        $oldKey = $_POST['old_key'];
        $slug = $_POST['slug'];
        $tierLabel = trim($_POST['tier_label'] ?? '');
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        // Remove old item
        unset($cart[$oldKey]);
        // Look up product for new price
        $product = dbGetRow("SELECT name, slug, export_price, tier_3kg_price, tier_6kg_price, tier_10kg_price, image_url, unit FROM product WHERE slug = ?", [$slug]);
        if ($product) {
            $unitPrice = (float)$product['export_price'];
            if ($tierLabel === '3kg' && (float)$product['tier_3kg_price'] > 0) $unitPrice = (float)$product['tier_3kg_price'];
            elseif ($tierLabel === '6kg' && (float)$product['tier_6kg_price'] > 0) $unitPrice = (float)$product['tier_6kg_price'];
            elseif ($tierLabel === '10kg' && (float)$product['tier_10kg_price'] > 0) $unitPrice = (float)$product['tier_10kg_price'];
            if ($restaurantDiscount > 0) $unitPrice = round($unitPrice * (1 - $restaurantDiscount / 100), 2);
            $newKey = $slug . ($tierLabel ? '_' . $tierLabel : '');
            $cart[$newKey] = ['slug'=>$slug,'product_name'=>$product['name'],'product_image'=>$product['image_url']??'','unit_price'=>$unitPrice,'unit'=>$tierLabel ?: ($product['unit'] ?? 'kg'),'quantity'=>$qty,'subtotal'=>$qty*$unitPrice,'tier_label'=>$tierLabel];
        }
        intl_save_cart($cart);
        header('Location: ' . url_for('cart'));
        exit;
    }
    if ($action === 'add_cart_item' && isset($_POST['slug'])) {
        $slug = $_POST['slug'];
        $tierLabel = trim($_POST['tier_label'] ?? '');
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        // Stock check
        if ($tierLabel) {
            $stockCol = match ($tierLabel) { '3kg' => 'tier_3kg_stock', '6kg' => 'tier_6kg_stock', '10kg' => 'tier_10kg_stock', default => null };
            if ($stockCol) {
                $avail = (int)dbGetValue("SELECT $stockCol FROM product WHERE slug = ?", [$slug]);
                $existingKey = $slug . ($tierLabel ? '_' . $tierLabel : '');
                $existingQty = (int)($cart[$existingKey]['quantity'] ?? 0);
                if (($qty + $existingQty) > $avail) {
                    header('Location: ' . url_for('cart'));
                    exit;
                }
            }
        }
        $product = dbGetRow("SELECT name, slug, export_price, tier_3kg_price, tier_6kg_price, tier_10kg_price, image_url, unit FROM product WHERE slug = ?", [$slug]);
        if ($product) {
            $unitPrice = (float)$product['export_price'];
            if ($tierLabel === '3kg' && (float)$product['tier_3kg_price'] > 0) $unitPrice = (float)$product['tier_3kg_price'];
            elseif ($tierLabel === '6kg' && (float)$product['tier_6kg_price'] > 0) $unitPrice = (float)$product['tier_6kg_price'];
            elseif ($tierLabel === '10kg' && (float)$product['tier_10kg_price'] > 0) $unitPrice = (float)$product['tier_10kg_price'];
            if ($restaurantDiscount > 0) $unitPrice = round($unitPrice * (1 - $restaurantDiscount / 100), 2);
            $newKey = $slug . ($tierLabel ? '_' . $tierLabel : '');
            if (isset($cart[$newKey])) { $cart[$newKey]['quantity'] += $qty; $cart[$newKey]['subtotal'] = $cart[$newKey]['quantity'] * $unitPrice; }
            else { $cart[$newKey] = ['slug'=>$slug,'product_name'=>$product['name'],'product_image'=>$product['image_url']??'','unit_price'=>$unitPrice,'unit'=>$tierLabel ?: ($product['unit'] ?? 'kg'),'quantity'=>$qty,'subtotal'=>$qty*$unitPrice,'tier_label'=>$tierLabel]; }
        }
        intl_save_cart($cart);
        header('Location: ' . url_for('cart'));
        exit;
    }
}
$items = intl_cart_items();
$total = intl_cart_total();
$balance = intl_wallet_balance();

// Fetch product tier data for each cart item
$cartProductData = [];
foreach ($items as $key => $item) {
    $slug = $item['slug'];
    if (!isset($cartProductData[$slug])) {
        $p = dbGetRow("SELECT slug, name, image_url, export_price, tier_3kg_price, tier_6kg_price, tier_10kg_price, tier_3kg_stock, tier_6kg_stock, tier_10kg_stock, unit, description FROM product WHERE slug = ?", [$slug]);
        if ($p) $cartProductData[$slug] = $p;
    }
}
?><!doctype html>
<html lang="en" style="margin:0;padding:0;">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Cart - International Fish Market</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{'brand-blue':'#0369a1','stone-150':'#e8e5e0','stone-250':'#d6d2cb','stone-350':'#b8b2a8','amber-350':'#d9995b'},fontFamily:{display:['Inter','system-ui','sans-serif'],mono:['JetBrains Mono','monospace']}}}}
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/recipes.css?v=<?= urlencode($__ifmAssetVersion('css/recipes.css')) ?>"/>
</head>
<body style="margin:0;padding:0;overflow-x:hidden">
<div id="root">
<div class="relative min-h-screen bg-bg-dark text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip" id="alche-studio-replica-root">
  <header id="main-app-header" class="fixed top-0 left-0 w-full z-50 transition-all duration-300 bg-transparent py-5">
    <div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center">
      <a href="<?= $__ifmBasePath ?>" class="cursor-pointer flex items-center space-x-2 group" id="brand-logo-trigger">
        <span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">INTERNATIONAL FISH MARKET</span>
        <span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse"></span>
      </a>
      <?php require __DIR__ . '/includes/nav_bar.php'; ?>
    </div>
  </header>
  <div id="subpage-viewport">
    <div class="min-h-screen bg-stone-100/50 pt-28 pb-24 px-4 md:px-8">
      <div class="max-w-6xl mx-auto space-y-10">
        <div class="border-b border-stone-250 pb-5 space-y-4">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
              <div class="p-2 bg-stone-900 text-stone-50 rounded-xl shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-brand-teal"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
              </div>
              <div>
                <h1 class="font-display font-black text-2xl text-stone-900 tracking-tight uppercase">Sourcing Cart</h1>
                <p class="text-[10px] text-stone-400 font-mono uppercase tracking-widest mt-0.5"><?= intl_cart_count() ?> item<?= intl_cart_count() !== 1 ? 's' : '' ?> in your cart</p>
              </div>
            </div>
          </div>
        </div>

        <?php if ($items): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div class="lg:col-span-2 space-y-3">
            <?php foreach ($items as $key => $item):
$slug = $item['slug'];
$pd = $cartProductData[$slug] ?? null;
$tiers = [];
if ($pd) {
    $disc = 1 - $restaurantDiscount/100;
    if ((float)$pd['tier_3kg_price'] > 0) $tiers[] = ['label'=>'3kg','price'=>round((float)$pd['tier_3kg_price'] * $disc, 2),'original'=>(float)$pd['tier_3kg_price'],'stock'=>(int)($pd['tier_3kg_stock']??10)];
    if ((float)$pd['tier_6kg_price'] > 0) $tiers[] = ['label'=>'6kg','price'=>round((float)$pd['tier_6kg_price'] * $disc, 2),'original'=>(float)$pd['tier_6kg_price'],'stock'=>(int)($pd['tier_6kg_stock']??10)];
    if ((float)$pd['tier_10kg_price'] > 0) $tiers[] = ['label'=>'10kg','price'=>round((float)$pd['tier_10kg_price'] * $disc, 2),'original'=>(float)$pd['tier_10kg_price'],'stock'=>(int)($pd['tier_10kg_stock']??10)];
}
// Find stock for current tier
$__maxQty = 999;
foreach ($tiers as $__t) {
    if ($__t['label'] === $item['tier_label']) { $__maxQty = (int)($__t['stock'] ?? 999); break; }
}
?><div class="bg-white border border-stone-200/80 rounded-2xl p-4 flex items-center gap-4 transition-all duration-200 hover:shadow-md cursor-pointer" data-key="<?= e($key) ?>" data-slug="<?= e($slug) ?>" data-current-tier="<?= e($item['tier_label'] ?? '') ?>" data-tiers='<?= json_encode($tiers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>' data-max-qty="<?= $__maxQty ?>" onclick="openCartEdit(this)">
              <div class="w-16 h-16 rounded-xl overflow-hidden bg-stone-100 flex-shrink-0">
                <img src="<?= e(intl_product_image($item['product_image'])) ?>" alt="<?= e($item['product_name']) ?>" class="w-full h-full object-cover" onerror="this.style.display='none';this.parentElement.innerHTML='<div style=width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.8rem>&#x1F41F</div>'">
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-display font-bold text-sm text-stone-900 truncate"><?= e($item['product_name']) ?></div>
                <div class="font-mono text-[11px] text-stone-400 mt-0.5">$<?= formatted_money((float)$item['unit_price']) ?> / <?= e($item['unit'] ?? 'unit') ?><?= !empty($item['tier_label']) ? ' (' . e($item['tier_label']) . ')' : '' ?><?php if ($__maxQty > 0 && $__maxQty < 999): ?> · <span class="text-amber-600 font-bold">Stock: <?= $__maxQty ?></span><?php endif; ?><?php if ($isRestaurantUser && $restaurantDiscount > 0): ?> <span style="color:#f43f5e;font-weight:700;">-<?= e(number_format($restaurantDiscount,0)) ?>%</span><?php endif; ?></div>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <div class="flex items-center border border-stone-200 rounded-lg overflow-hidden flex-shrink-0">
                  <button type="button" class="w-8 h-8 flex items-center justify-center text-stone-500 hover:bg-stone-100 text-sm font-bold cursor-pointer transition-all" onclick="event.stopPropagation();updateItem('<?= e($key) ?>',-1)">−</button>
                  <span class="qty-val w-8 h-8 flex items-center justify-center text-sm font-bold text-stone-800"><?= (int)$item['quantity'] ?></span>
                  <button type="button" class="w-8 h-8 flex items-center justify-center text-stone-500 hover:bg-stone-100 text-sm font-bold cursor-pointer transition-all" onclick="event.stopPropagation();updateItem('<?= e($key) ?>',1)">+</button>
                </div>
                <div class="text-right" style="min-width:90px;">
                  <div class="font-display font-bold text-base text-brand-blue item-subtotal">$<?= formatted_money((float)$item['subtotal']) ?></div>
                </div>
                <button type="button" class="w-8 h-8 flex items-center justify-center text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg text-sm cursor-pointer transition-all flex-shrink-0" onclick="event.stopPropagation();removeItem('<?= e($key) ?>')" title="Remove">✕</button>
              </div>
              <button type="button" class="w-8 h-8 flex items-center justify-center text-brand-blue hover:bg-blue-100 rounded-lg text-sm font-bold cursor-pointer transition-all flex-shrink-0" onclick="event.stopPropagation();openCartAddTier('<?= e($key) ?>')" title="Add another size">+</button>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="lg:col-span-1">
            <div class="bg-white border border-stone-200/80 rounded-2xl p-6 sticky top-28">
              <h3 class="font-display font-bold text-lg text-stone-900 mb-4">📦 Order Summary</h3>
              <div class="space-y-3 mb-4">
                <?php foreach ($items as $item): ?>
                <div class="flex justify-between text-sm">
                  <span class="text-stone-600 truncate mr-2"><?= e($item['product_name']) ?><?= !empty($item['tier_label']) ? ' (' . e($item['tier_label']) . ')' : '' ?> × <?= (int)$item['quantity'] ?></span>
                  <span class="font-semibold text-stone-800 flex-shrink-0">$<?= formatted_money((float)$item['subtotal']) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
              <div class="border-t border-stone-200 pt-3 flex justify-between items-center">
                <span class="font-display font-bold text-stone-900">Total</span>
                <span class="font-display font-black text-xl text-brand-blue">$<?= formatted_money($total) ?></span>
              </div>
              <div class="mt-4 bg-emerald-50 border border-emerald-200 rounded-xl p-3">
                <div class="flex justify-between text-sm">
                  <span class="text-emerald-700 font-semibold">💰 Wallet Balance</span>
                  <strong class="text-emerald-800">$<?= formatted_money($balance) ?></strong>
                </div>
              </div>
              <a href="<?= url_for('checkout') ?>" class="block w-full mt-4 text-center px-5 py-3.5 rounded-xl font-bold text-sm transition-all duration-200 active:scale-[0.98] no-underline <?= $balance < $total ? 'bg-stone-300 text-stone-500 cursor-not-allowed' : 'bg-stone-900 hover:bg-stone-800 text-stone-50 cursor-pointer' ?>">
                <?= $balance < $total ? '❌ Insufficient Balance' : '💳 Proceed to Checkout' ?>
              </a>
            </div>
          </div>
        </div>
      </div>

<!-- Edit Modal -->
<div id="cart-edit-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:99999;background:rgba(0,0,0,0.8);" onclick="if(event.target===this)closeCartEdit()">
  <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;" onclick="event.stopPropagation()">
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden" style="width:100%;max-width:760px;height:auto;max-height:90vh;display:flex;flex-direction:row;margin:0 24px;animation:modalIn 0.25s ease-out;">
      <div style="width:300px;flex-shrink:0;background:#e2e8f0;display:flex;align-items:center;justify-content:center;overflow:hidden;border-radius:24px 0 0 24px;">
        <img id="cart-edit-image" src="" alt="" style="width:100%;height:100%;object-fit:contain;display:block;padding:20px;">
      </div>
      <div style="flex:1;display:flex;flex-direction:column;min-width:0;padding:36px 40px;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
          <span id="cart-edit-category" class="font-mono text-[10px] bg-stone-900 text-stone-50 px-3 py-1.5 rounded-full uppercase tracking-wider font-bold"></span>
          <button onclick="closeCartEdit()" class="w-8 h-8 bg-stone-100 hover:bg-stone-200 text-stone-500 rounded-full text-base leading-none cursor-pointer flex items-center justify-center transition-all flex-shrink-0" style="line-height:1">✕</button>
        </div>
        <h2 id="cart-edit-name" class="font-display text-2xl font-bold text-stone-900 mb-3"></h2>
        <p id="cart-edit-description" class="text-stone-500 text-sm leading-relaxed mb-6" style="flex-shrink:0;"></p>
        <form id="cart-edit-form" style="flex:1;display:flex;flex-direction:column;">
          <input type="hidden" id="cart-edit-oldkey" value="">
          <div style="flex:1;">
            <label class="block font-bold text-sm text-stone-700 mb-4">📦 Select Size:</label>
            <div id="cart-edit-tiers" class="grid grid-cols-3 gap-4"></div>
          </div>
          <div style="margin-top:auto;">
            <div class="flex items-center gap-4 mb-3">
              <label class="font-semibold text-sm text-stone-700">Quantity:</label>
              <input type="number" id="cart-edit-qty" min="1" value="1" class="w-20 px-3 py-2 border border-stone-200 rounded-lg text-sm text-center outline-none" oninput="updateCartEditTotal()">
              <span id="cart-edit-total" class="text-sm font-bold text-stone-500 ml-auto"></span>
            </div>
            <div class="flex gap-4">
              <button type="button" onclick="saveCartEdit()" class="flex-1 px-6 py-4 bg-stone-900 hover:bg-stone-800 active:scale-[0.98] text-stone-50 rounded-xl font-bold text-sm cursor-pointer transition-all">💾 Update</button>
              <button type="button" onclick="closeCartEdit()" class="px-6 py-4 border border-stone-200 rounded-xl text-stone-500 hover:bg-stone-50 font-bold text-sm cursor-pointer transition-all">Cancel</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

      <?php else: ?>
        <div class="text-center py-32">
          <div class="text-7xl mb-4">🛒</div>
          <h2 class="font-display text-2xl font-bold text-stone-800 mb-2">Your cart is empty</h2>
          <p class="text-stone-400 text-sm mb-6">Looks like you haven't added any seafood yet.</p>
          <a href="<?= url_for('shop') ?>" class="inline-block px-6 py-3 bg-stone-900 hover:bg-stone-800 text-stone-50 rounded-xl font-bold text-sm transition-all active:scale-[0.98] no-underline">🧺 Browse Shop</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div>
<script src="js/app.js?v=<?= urlencode($__ifmAssetVersion('js/app.js')) ?>"></script>
<script>
// ── Cart edit modal ──
var cartEditTiers = [];
var _cartAddMode = false;
var _cartAddKey = '';

function openCartEdit(el) {
    var slug = el.getAttribute('data-slug');
    var key = el.getAttribute('data-key');
    var currentTier = el.getAttribute('data-current-tier');
    var tiers = JSON.parse(el.getAttribute('data-tiers') || '[]');
    cartEditTiers = tiers;

    // Hide nav when modal opens
    var h = document.getElementById('main-app-header');
    if (h) h.style.display = 'none';

    document.getElementById('cart-edit-oldkey').value = key;

    // Get product info from the row
    var name = el.querySelector('.font-display.font-bold.text-sm')?.textContent || '';
    var img = el.querySelector('img')?.src || '';
    var desc = el.querySelector('.font-mono.text-\\[11px\\]')?.textContent || '';

    document.getElementById('cart-edit-image').src = img;
    document.getElementById('cart-edit-name').textContent = name;
    document.getElementById('cart-edit-category').textContent = '';
    document.getElementById('cart-edit-description').textContent = 'Update your selection below.';

    // Build tier options with stock info
    var container = document.getElementById('cart-edit-tiers');
    container.innerHTML = '';
    if (tiers.length > 0) {
        tiers.forEach(function(t, i) {
            var isCurrent = t.label === currentTier;
            var disabled = t.stock <= 0;
            var label = document.createElement('label');
            label.className = 'flex flex-col items-center gap-1 border-2 rounded-xl p-3 cursor-pointer transition-all text-center ' + (isCurrent && !disabled ? 'border-stone-900 bg-stone-50' : 'border-stone-200') + (disabled ? ' opacity-50' : '');
            label.style.cursor = disabled ? 'not-allowed' : 'pointer';
            var priceHtml;
            if (t.original && t.original !== t.price) {
                priceHtml = '<div style="font-size:0.75rem;color:#94a3b8;text-decoration:line-through">$' + t.original.toFixed(2) + '</div>' +
                    '<div style="font-weight:800;font-size:1.1rem;color:#2563eb">$' + t.price.toFixed(2) + '</div>';
            } else {
                priceHtml = '<div style="font-weight:800;font-size:1.1rem;color:#2563eb">$' + t.price.toFixed(2) + '</div>';
            }
            var stockHtml;
            if (disabled) stockHtml = '<div style="color:#ef4444;font-size:0.65rem;font-weight:700;">✕ Sold Out</div>';
            else if (t.stock <= 5) stockHtml = '<div style="color:#f43f5e;font-size:0.65rem;font-weight:700;">⚠️ Only ' + t.stock + ' left</div>';
            else if (t.stock <= 10) stockHtml = '<div style="color:#d97706;font-size:0.65rem;font-weight:600;">⚠️ Low Stock</div>';
            else stockHtml = '<div style="color:#059669;font-size:0.65rem;">✦ In Stock</div>';
            label.innerHTML =
                (disabled ? '' : '<input type="radio" name="cart_edit_tier" value="' + t.label + '" ' + (isCurrent && !disabled ? 'checked' : '') + ' style="accent-color:#1c1917;width:14px;height:14px" onchange="selectCartEditTier(this)">') +
                '<div style="font-weight:700;font-size:0.85rem">' + t.label + '</div>' +
                priceHtml +
                stockHtml;
            container.appendChild(label);
        });
    } else {
        container.innerHTML = '<div class="col-span-3 text-center text-stone-400 text-sm py-4">No tier options</div>';
    }

    // Set current quantity
    var qtyEl = el.querySelector('.qty-val');
    document.getElementById('cart-edit-qty').value = qtyEl ? parseInt(qtyEl.textContent) : 1;
    // Set max qty from the checked tier's stock
    var checked = document.querySelector('[name="cart_edit_tier"]:checked');
    if (checked) {
        var idx = Array.from(document.querySelectorAll('[name="cart_edit_tier"]')).indexOf(checked);
        if (idx >= 0 && tiers[idx]) {
            var maxQ = tiers[idx].stock || 999;
            document.getElementById('cart-edit-qty').max = maxQ;
        }
    }

    document.getElementById('cart-edit-modal').style.display = 'flex';
    updateCartEditTotal();
}

function closeCartEdit() {
    document.getElementById('cart-edit-modal').style.display = 'none';
    // Show nav again
    var h = document.getElementById('main-app-header');
    if (h) h.style.display = '';
}

function openCartAddTier(key) {
    var el = document.querySelector('[data-key="'+key+'"]');
    if (!el) return;
    _cartAddMode = true;
    _cartAddKey = key;
    openCartEdit(el);
    // Uncheck current tier
    var currentTier = el.getAttribute('data-current-tier');
    document.querySelectorAll('[name="cart_edit_tier"]').forEach(function(r) {
        if (r.value === currentTier) {
            r.checked = false;
            r.closest('label').className = 'flex flex-col items-center gap-1 border-2 rounded-xl p-3 cursor-pointer transition-all text-center border-stone-200';
        }
    });
    // Change button text
    var btn = document.querySelector('#cart-edit-form .flex.gap-3 button:first-child');
    if (btn) btn.innerHTML = '➕ Add as New Item';
}

function selectCartEditTier(input) {
    document.querySelectorAll('#cart-edit-tiers label').forEach(function(l) { l.className = 'flex flex-col items-center gap-1 border-2 rounded-xl p-3 cursor-pointer transition-all text-center border-stone-200'; });
    input.closest('label').className = 'flex flex-col items-center gap-1 border-2 rounded-xl p-3 cursor-pointer transition-all text-center border-stone-900 bg-stone-50';
    // Update qty max based on selected tier's stock
    var qtyInput = document.getElementById('cart-edit-qty');
    var idx = Array.from(document.querySelectorAll('[name="cart_edit_tier"]')).indexOf(input);
    if (idx >= 0 && cartEditTiers[idx]) {
        var maxQ = cartEditTiers[idx].stock || 999;
        qtyInput.max = maxQ;
        if (parseInt(qtyInput.value) > maxQ) qtyInput.value = maxQ;
    }
    updateCartEditTotal();
}

function updateCartEditTotal() {
    var checked = document.querySelector('[name="cart_edit_tier"]:checked');
    var qty = parseInt(document.getElementById('cart-edit-qty').value) || 0;
    var totalEl = document.getElementById('cart-edit-total');
    if (checked && cartEditTiers.length > 0) {
        var idx = Array.from(document.querySelectorAll('[name="cart_edit_tier"]')).indexOf(checked);
        var price = cartEditTiers[idx] ? cartEditTiers[idx].price : 0;
        totalEl.textContent = 'Total: $' + (price * qty).toFixed(2);
    } else {
        totalEl.textContent = '';
    }
}

function saveCartEdit() {
    if (_cartAddMode) {
        var oldKey = _cartAddKey;
        var el = document.querySelector('[data-key="'+oldKey+'"]');
        if (!el) return;
        var slug = el.getAttribute('data-slug');
        var currentTier = el.getAttribute('data-current-tier');
        var newQty = parseInt(document.getElementById('cart-edit-qty').value) || 1;
        var checked = document.querySelector('[name="cart_edit_tier"]:checked');
        var newTier = checked ? checked.value : '';
        if (!newTier || newTier === currentTier) { showToast('⚠️ Please select a different size'); return; }
        // Check stock
        var idx = Array.from(document.querySelectorAll('[name="cart_edit_tier"]')).indexOf(checked);
        var maxStock = (idx >= 0 && cartEditTiers[idx]) ? (cartEditTiers[idx].stock || 0) : 0;
        if (newQty > maxStock) { showToast('⚠️ Only ' + maxStock + ' in stock for ' + newTier); return; }
        var f = new FormData();
        f.append('action', 'add_cart_item');
        f.append('slug', slug);
        f.append('tier_label', newTier);
        f.append('quantity', newQty);
        fetch(window.location.href, { method:'POST', body:f }).then(function(){ window.location.reload(); });
        return;
    }
    var oldKey = document.getElementById('cart-edit-oldkey').value;
    var newQty = parseInt(document.getElementById('cart-edit-qty').value) || 1;
    var checked = document.querySelector('[name="cart_edit_tier"]:checked');
    var newTier = checked ? checked.value : '';
    var slug = document.querySelector('[data-key="' + oldKey + '"]')?.getAttribute('data-slug') || '';
    if (!slug) return;
    var newKey = slug + (newTier ? '_' + newTier : '');

    var f = new FormData();
    f.append('action', 'update_cart_item');
    f.append('old_key', oldKey);
    f.append('slug', slug);
    f.append('tier_label', newTier);
    f.append('quantity', newQty);

    fetch(window.location.href, { method:'POST', body:f })
      .then(function(){ window.location.reload(); });
}

function showToast(msg) {
    var el = document.getElementById('shop-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'shop-toast';
        el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:999999;display:none;background:#1c1917;color:#fafaf9;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,0.15);border:1px solid rgba(255,255,255,0.06);max-width:320px;transition:opacity 0.3s';
        document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.display = 'block';
    el.style.opacity = '1';
    setTimeout(function(){ el.style.opacity = '0'; }, 2500);
    setTimeout(function(){ el.style.display = 'none'; }, 2800);
}

function findRow(key){return document.querySelector('[data-key="'+key+'"]');}
function updateItem(key,delta){var row=findRow(key);if(!row)return;var valEl=row.querySelector('.qty-val');var qty=parseInt(valEl.textContent)+delta;if(qty<=0){removeItem(key);return;}var maxQty=parseInt(row.getAttribute('data-max-qty'))||999;if(qty>maxQty){showToast('⚠️ Only '+maxQty+' in stock');return;}valEl.textContent=qty;updateQtyBackend(key,qty);recalcRow(row,key,qty);}
function removeItem(key){var row=findRow(key);if(row)row.style.opacity='0.3';var f=new FormData();f.append('action','remove');f.append('key',key);fetch(window.location.href,{method:'POST',body:f}).then(function(){window.location.reload();});}
function updateQtyBackend(key,qty){var f=new FormData();f.append('action','update_qty');f.append('key',key);f.append('quantity',qty);navigator.sendBeacon(window.location.href,f);}
function recalcRow(row,key,qty){var subEl=row.querySelector('.item-subtotal');var priceText=row.querySelector('.font-mono')?.textContent||'0';var price=parseFloat(priceText.replace('$',''))||0;var st=(price*qty).toFixed(2);subEl.textContent='$'+st;recalcTotal();}
function recalcTotal(){var total=0;document.querySelectorAll('.item-subtotal').forEach(function(el){total+=parseFloat(el.textContent.replace('$',''))||0;});var totalEl=document.querySelector('.border-t .font-black');if(totalEl)totalEl.textContent='$'+total.toFixed(2);var badge=document.getElementById('nav-cart-count');if(badge){var count=0;document.querySelectorAll('.qty-val').forEach(function(el){count+=parseInt(el.textContent)||0;});badge.textContent=count;badge.style.display=count>0?'flex':'none';}}
</script>
</body>
</html>
