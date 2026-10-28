<?php $extraHead = '<style>
.product-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:start}.product-img-box{background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;font-size:6rem}.product-info{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:2rem}.product-info-cat{font-size:.8rem;color:var(--brand);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:.75rem}.product-info-name{font-family:var(--font-display);font-size:2rem;line-height:1.2;margin-bottom:1rem}.product-info-price{font-size:2.2rem;font-weight:900;color:var(--amber-light);display:flex;align-items:baseline;gap:.4rem;margin-bottom:1.5rem}.product-info-price span{font-size:1rem;color:var(--muted);font-weight:400}.info-row{display:flex;gap:1rem;margin-bottom:1rem;flex-wrap:wrap}.info-chip{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,0.06);border:1px solid var(--border);padding:.4rem .9rem;border-radius:30px;font-size:.82rem;font-weight:600}.product-desc{color:var(--muted);font-size:.92rem;line-height:1.7;margin-bottom:1.5rem;border-top:1px solid var(--border);padding-top:1.2rem}.tier-option{display:block;border:2px solid var(--border);border-radius:var(--radius-sm);padding:.75rem;cursor:pointer;transition:.12s;background:rgba(255,255,255,0.03)}.tier-option:hover,.tier-option.selected{border-color:var(--brand)}.related-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;margin-top:1.25rem}
.tier-stock-badge{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding:2px 8px;border-radius:20px;margin-top:2px}
.stock-warning{font-size:.75rem;font-weight:700;color:#ef4444;display:none;margin-left:.5rem}
.qty-stock-info{font-size:.78rem;color:var(--muted);margin-left:8px}
@media (max-width:768px){.product-detail-grid{grid-template-columns:1fr}}
</style>'; ?>
<div class="container section">
  <div style="margin-bottom:1.5rem;font-size:.85rem;color:var(--muted)">
    <a href="<?= url_for('home') ?>" style="color:var(--muted);text-decoration:none">Home</a> → 
    <a href="<?= url_for('shop') ?>" style="color:var(--muted);text-decoration:none">Shop</a> → 
    <a href="<?= url_for('shop', ['category' => $product['category_slug']]) ?>" style="color:var(--muted);text-decoration:none"><?= e($product['category_name']) ?></a> → 
    <strong><?= e($product['name']) ?></strong>
  </div>
  <div class="product-detail-grid">
    <div><?php if ($product['image']): ?><img src="<?= e(product_image($product['image'])) ?>" alt="<?= e($product['name']) ?>" style="width:100%;border-radius:var(--radius);object-fit:cover;aspect-ratio:4/3"><?php else: ?><div class="product-img-box">🐟</div><?php endif; ?></div>
    <div class="product-info">
      <?php if ($product['is_featured']): ?><span class="badge badge-warning" style="margin-bottom:.75rem">⭐ Featured</span><?php endif; ?>
      <div class="product-info-cat"><?= e($product['category_name']) ?></div>
      <h1 class="product-info-name"><?= e($product['name']) ?></h1>
      <div class="info-row">
        <?php if ($product['origin']): ?><span class="info-chip">📍 <?= e($product['origin']) ?></span><?php endif; ?>
        <span class="info-chip" style="<?php $ps = (int) $product['stock']; echo $ps <= 0 ? 'color:#ef4444' : ($ps <= 10 ? 'color:#fbbf24' : ''); ?>"><?php if ($ps <= 0): ?>✕ Out of Stock<?php elseif ($ps <= 10): ?>⚠️ Only <?= $ps ?> Units left<?php else: ?>✓ In Stock<?php endif; ?></span>
      </div>
      <div class="product-desc"><?= e($product['description']) ?></div>
      <form method="post">
        <input type="hidden" name="action" value="add_to_cart">
        <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
        <?php if (!empty($product['tiers'])): ?>
        <div style="margin-bottom:1.5rem;">
          <label style="display:block;font-weight:700;font-size:.95rem;margin-bottom:.75rem;">📦 Select Size:</label>
          <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.5rem;">
            <?php
            $first = true;
            foreach ($product['tiers'] as $tier):
              $ts = (int)$tier['stock'];
              $disabled = $ts <= 0;
            ?>
            <label style="display:flex;flex-direction:column;align-items:center;gap:4px;border:2px solid var(--border);border-radius:var(--radius-sm);padding:.6rem .4rem;cursor:<?= $disabled ? 'not-allowed' : 'pointer' ?>;transition:.12s;background:rgba(255,255,255,<?= $disabled ? '0.02' : '0.08' ?>);text-align:center;<?= $disabled ? 'opacity:0.5;' : '' ?>" onmouseover="<?= $disabled ? '' : "this.style.borderColor='var(--brand)'" ?>" onmouseout="this.style.borderColor='var(--border)'">
              <input type="radio" name="tier_id" value="<?= e($tier['id']) ?>" <?= $first && !$disabled ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?> style="accent-color:var(--brand);width:15px;height:15px">
              <div style="font-weight:700;font-size:.85rem;"><?= e($tier['label']) ?> approx</div>
              <div style="font-weight:800;font-size:1.1rem;color:var(--amber-light);">RM<?= number_format((float)$tier['price'], 2) ?></div>
              <?php if ($ts <= 0): ?>
              <div class="tier-stock-badge" style="background:rgba(239,68,68,0.15);color:#ef4444;">✕ Sold Out</div>
              <?php elseif ($ts <= 10): ?>
              <div class="tier-stock-badge" style="background:rgba(217,119,6,0.15);color:#fbbf24;">⚠️ Only <?= $ts ?> left</div>
              <?php else: ?>
              <div class="tier-stock-badge" style="background:rgba(5,150,105,0.15);color:#059669;">✓ In Stock</div>
              <?php endif; ?>
            </label>
            <?php $first = false; endforeach; ?>
          </div>
          <div style="display:flex;align-items:center;gap:.75rem;margin-top:1rem;">
            <label style="font-weight:600;font-size:.9rem;">Quantity:</label>
            <input type="number" name="quantity" min="1" value="1" style="width:80px;padding:.5rem;border:1px solid var(--border);border-radius:var(--radius-sm);background:rgba(10,14,26,0.4);color:var(--text);" id="qtyInput">
            <span style="font-size:.85rem;color:var(--muted);" id="estTotal2"></span>
            <span class="stock-warning" id="stockWarning">⚠️ Max stock reached</span>
            <span class="qty-stock-info" id="qtyStockInfo"></span>
          </div>
        </div>
        <?php else: ?>
        <div class="form-group">
          <label class="form-label">Quantity</label>
          <input type="number" class="form-control" name="quantity" min="1" value="1" style="width:100px">
        </div>
        <?php endif; ?>
        <div style="display:flex;gap:1rem;flex-wrap:wrap">
          <button type="submit" class="btn btn-primary btn-lg" id="add-to-cart-btn">🧺 Add to Cart</button>
          <a href="<?= url_for('shop') ?>" class="btn btn-outline btn-lg">← Back</a>
        </div>
      </form>
      <script>
      (function(){
        var radios = document.querySelectorAll('[name="tier_id"]');
        var qtyInput = document.getElementById('qtyInput');
        var estEl = document.getElementById('estTotal2');
        var warningEl = document.getElementById('stockWarning');
        var infoEl = document.getElementById('qtyStockInfo');
        var submitBtn = document.getElementById('add-to-cart-btn');
        var prices = {};
        var stocks = {};
        <?php foreach ($product['tiers'] as $tier): ?>
        prices['<?= e($tier['id']) ?>'] = <?= (float)$tier['price'] ?>;
        stocks['<?= e($tier['id']) ?>'] = <?= (int)$tier['stock'] ?>;
        <?php endforeach; ?>
        function update() {
          var checked = document.querySelector('[name="tier_id"]:checked');
          var qty = parseInt(qtyInput.value) || 0;
          if (checked && prices[checked.value] !== undefined) {
            var maxQty = stocks[checked.value] || 0;
            var capped = false;
            if (qty > maxQty) { qty = maxQty; capped = true; }
            if (qty < 1) qty = 1;
            qtyInput.value = qty;
            estEl.textContent = 'Total: RM' + (prices[checked.value] * qty).toFixed(2);
            // Stock limit warning
            if (maxQty > 0 && qty >= maxQty) {
              warningEl.style.display = 'inline';
            } else {
              warningEl.style.display = 'none';
            }
            // Stock info text
            infoEl.textContent = '(Available: ' + maxQty + ')';
            // Disable button if sold out
            if (maxQty <= 0) {
              submitBtn.disabled = true;
              submitBtn.style.opacity = '0.4';
              submitBtn.style.cursor = 'not-allowed';
            } else {
              submitBtn.disabled = false;
              submitBtn.style.opacity = '';
              submitBtn.style.cursor = '';
            }
          } else {
            estEl.textContent = '';
            warningEl.style.display = 'none';
            infoEl.textContent = '';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.4';
            submitBtn.style.cursor = 'not-allowed';
          }
        }
        radios.forEach(function(r) { r.addEventListener('change', update); });
        qtyInput.addEventListener('input', update);
        qtyInput.addEventListener('blur', function() {
          if (parseInt(this.value) < 1) this.value = 1;
          update();
        });
        update();
      })();
      </script>
    </div>
  </div>
  <?php if (!empty($related)): ?>
  <div style="margin-top:4rem;">
    <h2 style="font-size:1.4rem;font-weight:800;margin-bottom:1rem;">🔄 Related Products</h2>
    <div class="related-grid">
      <?php foreach ($related as $r): ?>
      <a href="<?= url_for('product', ['slug' => $r['slug']]) ?>" style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;text-decoration:none;color:var(--text);transition:.15s;">
        <div style="aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;font-size:3rem;background:rgba(255,255,255,0.03);"><?= $r['image'] ? '<img src="'.e(product_image($r['image'])).'" alt="'.e($r['name']).'" style="width:100%;height:100%;object-fit:cover;">' : '🐟' ?></div>
        <div style="padding:.75rem;">
          <div style="font-size:.8rem;color:var(--muted);"><?= e($r['category_name']) ?></div>
          <div style="font-weight:700;font-size:.95rem;"><?= e($r['name']) ?></div>
          <div style="font-weight:800;color:var(--amber-light);">RM<?= e(formatted_money((float) $r['price'])) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
