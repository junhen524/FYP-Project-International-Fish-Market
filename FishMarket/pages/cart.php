<?php $extraHead = '<style>
.cart-layout{display:grid;grid-template-columns:1fr 340px;gap:2rem;align-items:start}.cart-table-wrap,.summary-card,.empty-cart{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);backdrop-filter:blur(8px)}.cart-item-row{display:grid;grid-template-columns:80px 1fr 140px auto auto;align-items:center;gap:1.2rem;padding:1.2rem 1.5rem;border-bottom:1px solid var(--border)}.cart-item-img{width:72px;height:72px;border-radius:var(--radius-sm);background:rgba(255,255,255,0.04);display:flex;align-items:center;justify-content:center;overflow:hidden}.cart-item-img img{width:100%;height:100%;object-fit:cover}.summary-card{padding:1.75rem}.summary-row{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;font-size:.9rem;color:var(--text)}.summary-total{font-size:1.35rem;font-weight:900;border-top:1px solid var(--border);padding-top:.75rem;margin-top:.5rem}.empty-cart{text-align:center;padding:5rem 2rem}
.qty-stepper{display:inline-flex;align-items:center;gap:0;border:1px solid var(--border);border-radius:8px;overflow:hidden;background:rgba(10,14,26,0.4)}.qty-stepper button{width:34px;height:36px;border:none;background:rgba(255,255,255,0.06);color:var(--text);font-size:1.1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.1s}.qty-stepper button:hover{background:rgba(255,255,255,0.12)}.qty-stepper .qty-display{width:40px;height:36px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.95rem;background:rgba(0,0,0,0.2);color:var(--text)}
@media (max-width:768px){.cart-layout{grid-template-columns:1fr}.cart-item-row{grid-template-columns:60px 1fr}}
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
  var saveTimer = {};

  function getMaxQty(key) {
    var inp = document.querySelector(".qty-input[data-key=\"" + key + "\"]");
    return inp ? parseInt(inp.dataset.maxQty) || 999 : 999;
  }

  function updateAll() {
    var total = 0;
    var index = 0;
    document.querySelectorAll(".qty-input").forEach(function(inp) {
      var qt = parseInt(inp.value) || 0;
      var up = parseFloat(inp.dataset.unitPrice);
      var key = inp.dataset.key;
      var maxQty = parseInt(inp.dataset.maxQty) || 999;
      // Clamp
      if (qt > maxQty) { qt = maxQty; inp.value = qt; }
      var st = qt * up;
      total += st;

      // Update display
      var disp = document.querySelector(".qty-display[data-key=\"" + key + "\"]");
      if (disp) disp.textContent = qt;

      // Update plus button disabled state
      var plusBtn = document.querySelector(".qty-plus[data-key=\"" + key + "\"]");
      if (plusBtn) {
        plusBtn.disabled = qt >= maxQty;
        plusBtn.style.opacity = qt >= maxQty ? "0.3" : "1";
        plusBtn.title = qt >= maxQty ? "Max stock reached" : "";
      }

      // Update item subtotal
      var subEl = document.querySelector(".item-subtotal[data-key=\"" + key + "\"]");
      if (subEl) subEl.textContent = "RM " + st.toFixed(2);

      // Update summary rows
      var rows = document.querySelectorAll(".summary-row:not(.summary-total)");
      if (rows[index]) {
        rows[index].querySelector("span:first-child").textContent = (inp.dataset.productName || "Item") + " \u00d7 " + qt;
        rows[index].querySelector("span:last-child").textContent = "RM " + st.toFixed(2);
      }
      index++;
    });
    var totalEl = document.querySelector(".summary-total span:last-child");
    if (totalEl) totalEl.textContent = "RM " + total.toFixed(2);
  }

  // Stepper buttons
  document.querySelectorAll(".qty-minus, .qty-plus").forEach(function(btn) {
    btn.addEventListener("click", function() {
      var key = this.dataset.key;
      var inp = document.querySelector(".qty-input[data-key=\"" + key + "\"]");
      if (!inp) return;
      var qty = parseInt(inp.value) || 0;
      var maxQty = parseInt(inp.dataset.maxQty) || 999;
      if (this.classList.contains("qty-minus") && qty > 0) qty--;
      if (this.classList.contains("qty-plus") && qty < maxQty) qty++;
      inp.value = qty;
      updateAll();

      // Auto-save
      if (saveTimer[key]) clearTimeout(saveTimer[key]);
      saveTimer[key] = setTimeout(function() {
        var form = new FormData();
        form.append("action", "update_cart_qty");
        form.append("key", key);
        form.append("quantity", qty);
        navigator.sendBeacon("index.php", form);
      }, 400);
    });
  });

  updateAll();
});
</script>'; ?>
<div class="page-header"><div class="container"><h1>🧺 My Cart</h1><p>Review your items and proceed to checkout</p></div></div>
<div class="container"><?php if ($cart['items']): ?><div class="cart-layout"><div><div class="cart-table-wrap"><div style="padding:1.2rem 1.5rem;border-bottom:1px solid var(--border);font-weight:700;font-size:.85rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted)">Cart Items (<?= cart_count() ?>)</div><?php foreach ($cart['items'] as $item):
// Get max stock for this item's tier
$__maxQty = 999;
if ($item['tier_id']) {
    foreach (($item['product']['tiers'] ?? []) as $__t) {
        if ($__t['id'] === $item['tier_id']) { $__maxQty = (int)($__t['stock'] ?? 999); break; }
    }
}
?><div class="cart-item-row"><div class="cart-item-img"><?php if ($item['product']['image']): ?><img src="<?= e(product_image($item['product']['image'])) ?>" alt="<?= e($item['product']['name']) ?>"><?php else: ?>🐟<?php endif; ?></div><div><div style="font-weight:700;font-size:.95rem"><a href="<?= url_for('product', ['slug' => $item['product']['slug']]) ?>" style="text-decoration:none;color:inherit"><?= e($item['product']['name']) ?></a></div><div style="color:var(--muted);font-size:.85rem"><?= e($item['tier_label']) ?> unit · RM<?= number_format((float)$item['unit_price'], 2) ?><?php if ($__maxQty > 0 && $__maxQty < 999): ?> · <span style="color:var(--coral)">Stock: <?= $__maxQty ?></span><?php endif; ?></div></div><div class="qty-stepper"><button type="button" class="qty-minus" data-key="<?= e($item['key']) ?>">−</button><span class="qty-display" data-key="<?= e($item['key']) ?>"><?= (int)$item['quantity'] ?></span><button type="button" class="qty-plus" data-key="<?= e($item['key']) ?>">+</button></div>
<input type="hidden" class="qty-input" value="<?= (int) $item['quantity'] ?>" data-unit-price="<?= (float)$item['unit_price'] ?>" data-product-name="<?= e($item['product']['name']) ?>" data-key="<?= e($item['key']) ?>" data-max-qty="<?= $__maxQty ?>"><div class="item-subtotal" data-key="<?= e($item['key']) ?>" style="font-size:1rem;font-weight:800;color:var(--amber-light)">RM <?= e(formatted_money((float) $item['subtotal'])) ?></div><form method="post" style="margin:0"><input type="hidden" name="action" value="remove_cart"><input type="hidden" name="key" value="<?= e($item['key']) ?>"><button type="submit" class="btn btn-sm" style="background:rgba(255,80,80,0.12);color:#ef4444;border:1px solid rgba(255,80,80,0.25);border-radius:8px;padding:6px 12px;cursor:pointer;font-weight:600;font-size:.82rem">✕ Remove</button></form></div><?php endforeach; ?></div><div style="margin-top:1rem"><a href="<?= url_for('shop') ?>" class="btn btn-ghost">← Continue Shopping</a></div></div><div><div class="summary-card"><h3 style="font-family:var(--font-display);font-size:1.25rem;margin-bottom:1.25rem">Order Summary</h3><?php foreach ($cart['items'] as $item): ?><div class="summary-row"><span><?= e($item['product']['name']) ?> × <?= (int) $item['quantity'] ?></span><span>RM <?= e(formatted_money((float) $item['subtotal'])) ?></span></div><?php endforeach; ?><div class="summary-row summary-total"><span>Total</span><span style="color:var(--coral)">RM <?= e(formatted_money((float) $cart['total'])) ?></span></div><div style="background:rgba(45,212,191,0.08);border:1px solid rgba(45,212,191,0.15);border-radius:var(--radius-sm);padding:.9rem;margin:1rem 0;font-size:.85rem"><div style="display:flex;justify-content:space-between"><span>💰 Wallet Balance</span><strong>RM <?= e(formatted_money((float) demo_profile()['wallet_balance'])) ?></strong></div></div><a href="<?= url_for('checkout') ?>" class="btn btn-primary btn-lg btn-block">💳 Proceed to Checkout</a></div></div></div><?php else: ?><div class="empty-cart"><div style="font-size:5rem;margin-bottom:1.5rem">🧺</div><h2 style="font-family:var(--font-display);font-size:1.5rem;margin-bottom:.75rem">Your Cart is Empty</h2><p style="color:var(--muted);margin-bottom:2rem">Head to the shop and add some fresh items!</p><a href="<?= url_for('shop') ?>" class="btn btn-primary btn-lg">🛒 Start Shopping</a></div><?php endif; ?></div>
