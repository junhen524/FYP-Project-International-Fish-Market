<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};
require_once __DIR__ . '/includes/bootstrap.php';

$highlight = $_GET['highlight'] ?? '';
$cat = $_GET['category'] ?? '';
$search = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? '';
$products = intl_products($cat, $search, $sort);
$categories = intl_categories();
$user = intl_user();
$restaurantDiscount = intl_restaurant_discount(); // 0 if not restaurant

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $slug = trim($_POST['slug'] ?? '');
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $tierLabel = trim($_POST['tier_label'] ?? '');
    $product = dbGetRow("SELECT id, name, slug, export_price, tier_3kg_price, tier_6kg_price, tier_10kg_price, tier_3kg_stock, tier_6kg_stock, tier_10kg_stock, image_url, unit, description FROM product WHERE slug = ? AND is_active = TRUE", [$slug]);
    if ($product) {
        // Check tier stock
        if ($tierLabel) {
            $stockCol = match ($tierLabel) { '3kg' => 'tier_3kg_stock', '6kg' => 'tier_6kg_stock', '10kg' => 'tier_10kg_stock', default => null };
            if ($stockCol) {
                $avail = (int)$product[$stockCol];
                $cart = intl_cart_items();
                $existingQty = 0;
                foreach ($cart as $ck => $ci) {
                    if ($ci['slug'] === $slug && ($ci['tier_label'] ?? '') === $tierLabel) $existingQty += (int)$ci['quantity'];
                }
                if ($avail < ($qty + $existingQty)) {
                    set_flash('error', "Not enough $tierLabel stock. Available: $avail.");
                    header('Location: ' . url_for('shop', ['category' => $cat, 'q' => $search, 'sort' => $sort]));
                    exit;
                }
            }
        }
        // Determine price based on selected tier
        $unitPrice = (float)$product['export_price'];
        if ($tierLabel === '3kg' && (float)$product['tier_3kg_price'] > 0) $unitPrice = (float)$product['tier_3kg_price'];
        elseif ($tierLabel === '6kg' && (float)$product['tier_6kg_price'] > 0) $unitPrice = (float)$product['tier_6kg_price'];
        elseif ($tierLabel === '10kg' && (float)$product['tier_10kg_price'] > 0) $unitPrice = (float)$product['tier_10kg_price'];
        // Apply restaurant discount
        if ($restaurantDiscount > 0) $unitPrice = round($unitPrice * (1 - $restaurantDiscount / 100), 2);
        $cart = intl_cart_items(); $key = $slug . ($tierLabel ? '_' . $tierLabel : '');
        if (isset($cart[$key])) { $cart[$key]['quantity'] += $qty; $cart[$key]['subtotal'] = $cart[$key]['quantity'] * $unitPrice; }
        else { $cart[$key] = ['slug'=>$slug,'product_name'=>$product['name'],'product_image'=>$product['image_url']??'','unit_price'=>$unitPrice,'unit'=>$tierLabel ?: ($product['unit'] ?? 'kg'),'quantity'=>$qty,'subtotal'=>$qty*$unitPrice]; }
        $cart[$key]['tier_label'] = $tierLabel;
        intl_save_cart($cart);
        $isAjax = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        if (!$isAjax) set_flash('success', $product['name'] . ' added to cart!');
    }
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        echo json_encode(['ok'=>true]); exit;
    }
    header('Location: ' . url_for('shop', ['category' => $cat, 'q' => $search, 'sort' => $sort]));
    exit;
}
$flash = flash();
?><!doctype html>
<html lang="en" style="margin:0;padding:0;">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>International Fish Market - Shop</title>
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
<div class="max-w-6xl mx-auto space-y-8">

<?php if ($flash && isset($flash['success'])): ?>
<div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-xs font-mono font-semibold text-center"><?= e($flash['success']) ?></div>
<?php endif; ?>

<?php if ($restaurantDiscount > 0): ?>
<div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-xs font-mono font-semibold text-center flex items-center justify-center gap-2">
  🏪 <span>You're a Restaurant Partner! <strong><?= e(number_format($restaurantDiscount,0)) ?>% discount</strong> applied to all products.</span>
</div>
<?php endif; ?>

<!-- Header row -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
<div class="flex items-center gap-3 flex-wrap">
<form method="get" class="flex border border-stone-200 rounded-xl overflow-hidden">
<input type="text" name="q" value="<?= e($search) ?>" placeholder="Search products..." class="px-3 py-2 text-sm border-none outline-none w-44 bg-white">
<button type="submit" class="px-4 py-2 bg-brand-blue text-white text-xs font-bold cursor-pointer hover:bg-sky-600 transition-colors">Search</button>
</form>
<select class="px-3 py-2 border border-stone-200 rounded-xl text-xs bg-white outline-none" onchange="location.href=this.value">
<option value="<?= url_for('shop', array_filter(['category'=>$cat,'q'=>$search])) ?>">Sort by</option>
<option value="<?= url_for('shop', array_filter(['category'=>$cat,'q'=>$search,'sort'=>'price_asc'])) ?>" <?= $sort==='price_asc'?'selected':'' ?>>Price: Low to High</option>
<option value="<?= url_for('shop', array_filter(['category'=>$cat,'q'=>$search,'sort'=>'price_desc'])) ?>" <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
</select>
</div>
<span class="font-mono text-[10px] text-stone-400 uppercase tracking-wider"><?= count($products) ?> products</span>
</div>

<!-- Category pills - matching recipes button style -->
<div class="flex gap-3 flex-wrap">
<a href="<?= url_for('shop', ['q'=>$search,'sort'=>$sort]) ?>" class="px-4 py-2 rounded-full border border-stone-200 text-xs font-semibold uppercase tracking-widest transition-all duration-150 bg-white text-stone-500 no-underline <?= !$cat ? '!bg-stone-900 !text-white !border-stone-900' : '' ?> hover:bg-stone-900 hover:text-white hover:border-stone-900">All</a>
<?php foreach ($categories as $c): ?>
<a href="<?= url_for('shop', ['category'=>$c['slug'],'q'=>$search,'sort'=>$sort]) ?>" class="px-4 py-2 rounded-full border border-stone-200 text-xs font-semibold uppercase tracking-widest transition-all duration-150 bg-white text-stone-500 no-underline <?= $cat===$c['slug'] ? '!bg-stone-900 !text-white !border-stone-900' : '' ?> hover:bg-stone-900 hover:text-white hover:border-stone-900"><?= e($c['name']) ?></a>
<?php endforeach; ?>
</div>

<!-- Product grid -->
<?php if ($products): ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
<?php foreach ($products as $p):
$isHighlighted = $highlight && $p['slug'] === $highlight;
$tiers = [];
$disc = 1 - $restaurantDiscount/100;
if ((float)$p['tier_3kg_price'] > 0) $tiers[] = ['label'=>'3kg','price'=>round((float)$p['tier_3kg_price'] * $disc, 2),'original'=>(float)$p['tier_3kg_price']];
if ((float)$p['tier_6kg_price'] > 0) $tiers[] = ['label'=>'6kg','price'=>round((float)$p['tier_6kg_price'] * $disc, 2),'original'=>(float)$p['tier_6kg_price']];
if ((float)$p['tier_10kg_price'] > 0) $tiers[] = ['label'=>'10kg','price'=>round((float)$p['tier_10kg_price'] * $disc, 2),'original'=>(float)$p['tier_10kg_price']];
$stock3kg = (int)($p['tier_3kg_stock'] ?? 10);
$stock6kg = (int)($p['tier_6kg_stock'] ?? 10);
$stock10kg = (int)($p['tier_10kg_stock'] ?? 10);
?>
<div class="bg-white border <?= $isHighlighted ? 'border-amber-400 ring-2 ring-amber-300' : 'border-stone-200/80' ?> rounded-3xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col cursor-pointer group shop-card" data-slug="<?= e($p['slug']) ?>" data-name="<?= e($p['name']) ?>" data-category="<?= e($p['category'] ?: 'SEAFOOD') ?>" data-image="<?= e(intl_product_image($p['image_url'])) ?>" data-unit="<?= e($p['unit'] ?? 'kg') ?>" data-description="<?= e($p['description'] ?: '') ?>" data-tiers='<?= json_encode($tiers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>' data-stock3kg="<?= $stock3kg ?>" data-stock6kg="<?= $stock6kg ?>" data-stock10kg="<?= $stock10kg ?>" onclick="openShopModal(this)"><?php if ($isHighlighted): ?>
<div class="highlight-badge absolute top-3 right-3 z-10">
<span style="font-size:10px;font-weight:700;padding:4px 10px;background:#fbbf24;color:#1c1917;border-radius:20px;display:inline-flex;align-items:center;gap:4px">⭐ Selected</span>
</div>
<?php endif; ?>
<?php if ($p['image_url']): ?>
<div class="relative h-48 overflow-hidden bg-stone-100">
<img src="<?= e(intl_product_image($p['image_url'])) ?>" alt="<?= e($p['name']) ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
<div class="absolute inset-0 bg-gradient-to-t from-stone-950/40 via-transparent to-transparent"></div>
<div class="absolute top-4 left-4">
<span class="font-mono text-[8px] bg-stone-900/90 backdrop-blur-md text-stone-50 px-2 py-1 rounded-full uppercase tracking-wider font-extrabold border border-stone-800"><?= e($p['category'] ?: 'SEAFOOD') ?></span>
</div>
</div>
<?php else: ?>
<div class="relative h-48 overflow-hidden bg-stone-100 flex items-center justify-center text-5xl text-stone-300">
🐟
<div class="absolute top-4 left-4">
<span class="font-mono text-[8px] bg-stone-900/90 backdrop-blur-md text-stone-50 px-2 py-1 rounded-full uppercase tracking-wider font-extrabold border border-stone-800">🐟 SEAFOOD</span>
</div>
</div>
<?php endif; ?>
<div class="p-5 flex flex-col gap-2">
<div class="font-display font-bold text-base text-stone-900 prod-name"><?= e($p['name']) ?></div>
<div>
<?php if ($restaurantDiscount > 0):
  $origPrice = (float)$p['export_price'];
  $discPrice = round($origPrice * (1 - $restaurantDiscount / 100), 2);
?>
<span style="text-decoration:line-through;color:#94a3b8;font-size:14px;">$<?= formatted_money($origPrice) ?></span>
<span class="font-display font-black text-xl text-brand-blue">$<?= formatted_money($discPrice) ?></span>
<span class="font-mono text-xs text-rose-500 ml-1 font-bold">-<?= e(number_format($restaurantDiscount,0)) ?>%</span>
<?php else: ?>
<span class="font-display font-black text-xl text-brand-blue">$<?= formatted_money((float)$p['export_price']) ?></span>
<?php endif; ?>
<span class="font-mono text-xs text-stone-400">/ <?= e($p['unit']) ?></span>
</div>
<div class="font-mono text-[10px] uppercase tracking-widest 
  <?php 
    $totalStock = $stock3kg + $stock6kg + $stock10kg;
    if ($totalStock <= 0): echo 'text-rose-600';
    elseif ($totalStock <= 10): echo 'text-amber-600';
    else: echo 'text-emerald-600';
    endif;
  ?>">
  <?php 
    if ($totalStock <= 0): echo '✕ Out of Stock';
    elseif ($totalStock <= 10): echo '⚠️ Only ' . $totalStock . ' left';
    else: echo '✓ ' . $totalStock . ' in stock';
    endif;
  ?></div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p class="text-center text-stone-400 py-20">No products found.</p>
<?php endif; ?>

<style>
@keyframes modalIn { from { opacity:0; transform:scale(0.92); } to { opacity:1; transform:scale(1); } }
</style>
<script>
// ── Modal functions ──
var modalProductSlug = '';
var modalTiers = [];
var modalTierStockMap = {};

function openShopModal(el) {
    var slug = el.getAttribute('data-slug');
    var name = el.getAttribute('data-name');
    var cat = el.getAttribute('data-category');
    var image = el.getAttribute('data-image');
    var desc = el.getAttribute('data-description');
    var tiers = JSON.parse(el.getAttribute('data-tiers') || '[]');
    var stock3kg = parseInt(el.getAttribute('data-stock3kg') || '10');
    var stock6kg = parseInt(el.getAttribute('data-stock6kg') || '10');
    var stock10kg = parseInt(el.getAttribute('data-stock10kg') || '10');
    modalTierStockMap = {'3kg': stock3kg, '6kg': stock6kg, '10kg': stock10kg};

    modalProductSlug = slug;
    modalTiers = tiers;

    document.getElementById('modal-slug').value = slug;
    document.getElementById('modal-image').src = image;
    document.getElementById('modal-image').alt = name;
    document.getElementById('modal-name').textContent = name;
    document.getElementById('modal-category').textContent = cat;
    document.getElementById('modal-description').textContent = desc || 'Premium quality seafood.';

    // Hide nav when modal opens
    var h = document.getElementById('main-app-header');
    if (h) h.style.display = 'none';

    // Stock info
    var totalStock = stock3kg + stock6kg + stock10kg;
    var stockHtml_total = '';
    if (totalStock <= 0) stockHtml_total = '<span style="color:#e11d48;font-weight:700;">✕ Out of Stock</span>';
    else if (totalStock <= 10) stockHtml_total = '<span style="color:#d97706;font-weight:700;">⚠️ Only ' + totalStock + ' units left</span>';
    else stockHtml_total = '<span style="color:#059669;font-weight:700;">✓ ' + totalStock + ' in stock</span>';
    document.getElementById('modal-stock').innerHTML = stockHtml_total + ' <span style="color:#94a3b8;font-size:0.65rem;">(3kg: ' + stock3kg + ' · 6kg: ' + stock6kg + ' · 10kg: ' + stock10kg + ')</span>';

    // Disable submit if no tier has stock
    var submitBtn = document.getElementById('modal-submit-btn');
    var qtyInput = document.querySelector('[name="quantity"]');
    var anyStock = Object.values(modalTierStockMap).some(function(v) { return v > 0; });
    if (!anyStock) { submitBtn.disabled = true; submitBtn.style.opacity = '0.4'; submitBtn.style.cursor = 'not-allowed'; if (qtyInput) qtyInput.disabled = true; }
    else { submitBtn.disabled = false; submitBtn.style.opacity = ''; submitBtn.style.cursor = ''; if (qtyInput) qtyInput.disabled = false; }

    var tiersContainer = document.getElementById('modal-tiers');
    tiersContainer.innerHTML = '';
    if (tiers.length > 0) {
        tiers.forEach(function(t, i) {
            var ts = modalTierStockMap[t.label] || 0;
            var disabled = ts <= 0;
            var label = document.createElement('label');
            label.className = 'flex flex-col items-center gap-1 border-2 rounded-xl p-3 cursor-pointer transition-all text-center ' + (i === 0 && !disabled ? 'border-stone-900 bg-stone-50' : 'border-stone-200') + (disabled ? ' opacity-50' : '');
            label.style.cursor = disabled ? 'not-allowed' : 'pointer';
            var priceHtml;
            if (t.original && t.original !== t.price) {
                priceHtml = '<div style="font-size:0.75rem;color:#94a3b8;text-decoration:line-through">$' + t.original.toFixed(2) + '</div>' +
                    '<div style="font-weight:800;font-size:1.1rem;color:#2563eb">$' + t.price.toFixed(2) + '</div>';
            } else {
                priceHtml = '<div style="font-weight:800;font-size:1.1rem;color:#2563eb">$' + t.price.toFixed(2) + '</div>';
            }
            var stockHtml;
            if (ts <= 0) stockHtml = '<div style="color:#ef4444;font-size:0.65rem;font-weight:700;">✕ Sold Out</div>';
            else if (ts <= 5) stockHtml = '<div style="color:#f43f5e;font-size:0.65rem;font-weight:700;">⚠️ Only ' + ts + ' left</div>';
            else if (ts <= 10) stockHtml = '<div style="color:#d97706;font-size:0.65rem;font-weight:600;">⚠️ Low Stock</div>';
            else stockHtml = '<div style="color:#059669;font-size:0.65rem;">✦ In Stock</div>';
            label.innerHTML =
                (disabled ? '' : '<input type="radio" name="tier_label" value="' + t.label + '" ' + (i === 0 && !disabled ? 'checked' : '') + ' style="accent-color:#1c1917;width:14px;height:14px" onchange="selectModalTier(this)">') +
                '<div style="font-weight:700;font-size:0.85rem">' + t.label + '</div>' +
                priceHtml +
                stockHtml;
            tiersContainer.appendChild(label);
        });
    } else {
        tiersContainer.innerHTML = '<div class="col-span-3 text-center text-stone-400 text-sm py-4">No tier options available</div>';
    }

    document.getElementById('shop-modal').style.display = 'flex';
    // Set initial max qty from the default-selected tier
    var firstChecked = document.querySelector('[name="tier_label"]:checked');
    if (firstChecked) {
        var qtyInp = document.querySelector('[name="quantity"]');
        var maxQ = modalTierStockMap[firstChecked.value] || 999;
        qtyInp.max = maxQ;
    }
    updateModalTotal();
}

function closeShopModal() {
    document.getElementById('shop-modal').style.display = 'none';
    // Show nav again
    var h = document.getElementById('main-app-header');
    if (h) h.style.display = '';
    // Clear highlight
    var u = new URL(window.location);
    u.searchParams.delete('highlight');
    window.history.replaceState({}, '', u);
    var slug = document.getElementById('modal-slug').value;
    var card = document.querySelector('.shop-card[data-slug="' + slug + '"]');
    if (card) {
        card.style.borderColor = '';
        card.style.border = '';
        card.style.boxShadow = '';
        var badge = card.querySelector('.highlight-badge');
        if (badge) badge.style.display = 'none';
    }
}

function selectModalTier(input) {
    document.querySelectorAll('#modal-tiers label').forEach(function(l) { l.className = 'flex flex-col items-center gap-1 border-2 rounded-xl p-3 cursor-pointer transition-all text-center border-stone-200'; });
    input.closest('label').className = 'flex flex-col items-center gap-1 border-2 rounded-xl p-3 cursor-pointer transition-all text-center border-stone-900 bg-stone-50';
    // Update qty max based on selected tier's stock
    var qtyInput = document.querySelector('[name="quantity"]');
    var maxQty = modalTierStockMap[input.value] || 999;
    qtyInput.max = maxQty;
    if (parseInt(qtyInput.value) > maxQty) qtyInput.value = maxQty;
    updateModalTotal();
}

function updateModalTotal() {
    var checked = document.querySelector('[name="tier_label"]:checked');
    var qtyInput = document.querySelector('[name="quantity"]');
    if (!qtyInput) return;
    var qty = parseInt(qtyInput.value) || 1;
    var totalEl = document.getElementById('modal-total');
    var stockMsg = document.getElementById('modal-stock-warn');
    if (checked && modalTiers.length > 0) {
        var idx = Array.from(document.querySelectorAll('[name="tier_label"]')).indexOf(checked);
        var price = modalTiers[idx] ? modalTiers[idx].price : 0;
        var maxQty = modalTierStockMap[checked.value] || 999;
        // Clamp qty to max stock
        if (qty > maxQty) {
            qty = maxQty;
            qtyInput.value = maxQty;
            if (stockMsg) stockMsg.textContent = '⚠️ Only ' + maxQty + ' available for ' + checked.value;
        } else {
            if (stockMsg) stockMsg.textContent = '';
        }
        totalEl.textContent = 'Total: $' + (price * qty).toFixed(2);
    } else {
        totalEl.textContent = '';
        if (stockMsg) stockMsg.textContent = '';
    }
}

// ── Modal form AJAX submit ──
document.addEventListener('DOMContentLoaded', function(){
  var form = document.getElementById('modal-form');
  if (!form) return;
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    var tierEl = document.querySelector('[name="tier_label"]:checked');
    if (tierEl) fd.set('tier_label', tierEl.value);
    var name = document.getElementById('modal-name').textContent;
    var tier = tierEl ? tierEl.value : '';
    var qty = document.querySelector('[name="quantity"]').value;
    fetch(window.location.href, { method:'POST', body:fd, headers:{'Accept':'application/json'} })
      .then(function() {
        // Update nav cart count
        var badge = document.getElementById('nav-cart-count');
        var cur = parseInt(badge.textContent) || 0;
        badge.textContent = cur + parseInt(qty);
        badge.style.display = 'flex';
        // Show success in the top-right of the modal
        var toast = document.getElementById('modal-toast');
        toast.textContent = '✓ ' + name + (tier ? ' (' + tier + ')' : '') + ' x' + qty + ' added';
        toast.style.display = 'block';
        setTimeout(function(){ toast.style.display = 'none'; }, 3000);
      });
});
});

// ── Auto-open modal & scroll to highlighted product ──
(function(){
  var slug = new URLSearchParams(window.location.search).get('highlight');
  if (slug) {
    var card = document.querySelector('[data-slug="' + slug + '"]');
    if (card) {
      setTimeout(function(){
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        openShopModal(card);
      }, 300);
    }
  }
})();
</script>

</div>
</div>
</div>
</div>
</div>

<!-- Modal (outside all containers so position:fixed works properly) -->
<div id="shop-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:99999;background:rgba(0,0,0,0.8);" onclick="if(event.target===this)closeShopModal()">
  <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;" onclick="event.stopPropagation()">
    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden" style="width:100%;max-width:1400px;height:auto;max-height:90vh;display:flex;flex-direction:row;margin:0 24px;animation:modalIn 0.25s ease-out;">
      <div style="width:400px;flex-shrink:0;background:#e2e8f0;display:flex;align-items:center;justify-content:center;overflow:hidden;border-radius:24px 0 0 24px;">
        <img id="modal-image" src="" alt="" style="width:100%;height:100%;object-fit:contain;display:block;padding:20px;">
      </div>
      <div style="flex:1;display:flex;flex-direction:column;min-width:0;padding:36px 40px;position:relative;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
          <span id="modal-category" class="font-mono text-[10px] bg-stone-900 text-stone-50 px-3 py-1.5 rounded-full uppercase tracking-wider font-bold"></span>
          <button onclick="closeShopModal()" class="w-8 h-8 bg-stone-100 hover:bg-stone-200 text-stone-500 rounded-full text-base leading-none cursor-pointer flex items-center justify-center transition-all flex-shrink-0" style="line-height:1">✕</button>
        </div>
        <div id="modal-toast" style="display:none;position:absolute;top:16px;right:60px;background:#059669;color:#fff;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,0.12);z-index:10;"></div>
        <h2 id="modal-name" class="font-display text-2xl font-bold text-stone-900 mb-3"></h2>
        <div id="modal-stock" class="mb-4 text-xs font-mono"></div>
        <p id="modal-description" class="text-stone-500 text-sm leading-relaxed mb-6" style="flex-shrink:0;"></p>
        <form id="modal-form" method="post" style="flex:1;display:flex;flex-direction:column;">
          <input type="hidden" name="action" value="add_to_cart">
          <input type="hidden" name="slug" id="modal-slug" value="">
          <div style="flex:1;">
            <label class="block font-bold text-sm text-stone-700 mb-4">📦 Select Size:</label>
            <div id="modal-tiers" class="grid grid-cols-3 gap-4"></div>
          </div>
          <div style="margin-top:auto;">
            <div class="flex items-center gap-4 mb-3">
              <label class="font-semibold text-sm text-stone-700">Quantity:</label>
              <input type="number" name="quantity" min="1" value="1" class="w-20 px-3 py-2 border border-stone-200 rounded-lg text-sm text-center outline-none" oninput="updateModalTotal()">
              <span id="modal-total" class="text-sm font-bold text-stone-500 ml-auto"></span>
            </div>
            <div id="modal-stock-warn" style="font-size:11px;color:#d97706;font-weight:600;min-height:18px;margin-bottom:16px;"></div>
            <div class="flex gap-4">
              <button type="submit" id="modal-submit-btn" class="flex-1 px-6 py-4 bg-stone-900 hover:bg-stone-800 active:scale-[0.98] text-stone-50 rounded-xl font-bold text-sm cursor-pointer transition-all">🧺 Add to Cart</button>
              <button type="button" onclick="closeShopModal()" class="px-6 py-4 border border-stone-200 rounded-xl text-stone-500 hover:bg-stone-50 font-bold text-sm cursor-pointer transition-all">Cancel</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="js/app.js?v=<?= urlencode($__ifmAssetVersion('js/app.js')) ?>"></script>
</body>
</html>