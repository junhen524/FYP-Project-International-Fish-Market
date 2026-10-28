<?php
$sq = trim((string)($_GET['q'] ?? ''));
$filtered = $sq !== '' ? search_products($sq) : $allProducts;
$extraHead = admin_css();
$pageView = 'dashboard_products';

// Group products by category for filter cards
$catGroups = [];
foreach ($filtered as $p) {
    $c = $p['category_slug'] ?? 'uncategorized';
    if (!isset($catGroups[$c])) $catGroups[$c] = ['cnt' => 0, 'name' => $p['category_name'] ?? ucfirst($c)];
    $catGroups[$c]['cnt']++;
}
ksort($catGroups);
?>
<div class="page-header"><div class="container"><h1>🐟 Product Management</h1><p>Browse, search and manage products</p></div></div>
<div class="container"><div class="admin-layout">
  <div class="admin-sidebar"><div class="admin-sidebar-header"><h3 style="color:var(--text);font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:1px">⚙ Management</h3></div><?= admin_sidebar('dashboard_products') ?></div>
  <div>
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 style="font-weight:700;font-size:.95rem">🐟 Products (<?= count($filtered) ?>)</h3>
        <form method="get" action="<?= url_for('dashboard_products') ?>" style="display:flex;gap:.5rem;align-items:center">
          <input type="hidden" name="page" value="dashboard_products">
          <input type="text" name="q" class="admin-search" placeholder="Search products…" value="<?= e($sq) ?>" style="width:220px">
          <button type="submit" class="btn btn-outline btn-sm">🔍</button>
          <?php if ($sq !== ''): ?><a href="<?= url_for('dashboard_products') ?>" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
        </form>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1rem;padding:0 1rem" id="product-cat-filters">
        <div class="cat-filter" data-cat="all" onclick="filterProductsByCat('all')" style="border:2px solid var(--brand);border-radius:var(--radius-sm);padding:.35rem .75rem;cursor:pointer;background:var(--brand);color:#0a0e1a;transition:.12s;font-weight:700;font-size:.78rem">
          All (<?= count($filtered) ?>)
        </div>
        <?php foreach ($catGroups as $slug => $cg): ?>
        <div class="cat-filter" data-cat="<?= e($slug) ?>" onclick="filterProductsByCat('<?= e($slug) ?>')" style="border:2px solid var(--border);border-radius:var(--radius-sm);padding:.35rem .75rem;cursor:pointer;background:var(--surface);transition:.12s;font-weight:600;font-size:.78rem;color:var(--muted)">
          <?= e($cg['name']) ?> (<?= $cg['cnt'] ?>)
        </div>
        <?php endforeach; ?>
      </div>
      <div style="overflow-x:auto"><table style="font-size:.8rem;white-space:nowrap"><thead><tr>
        <th>Image</th><th>Name</th><th>Category</th>
        <th style="text-align:center">3kg (Price / Stock)</th>
        <th style="text-align:center">6kg (Price / Stock)</th>
        <th style="text-align:center">10kg (Price / Stock)</th>
      </tr></thead><tbody id="products-tbody">
      <?php foreach ($filtered as $p):
        $tiers = $p['tiers'] ?? [];
        $t3 = $tiers[0] ?? null;
        $t6 = $tiers[1] ?? null;
        $t10 = $tiers[2] ?? null;
        $pCat = $p['category_slug'] ?? 'uncategorized';
      ?>
        <tr class="product-row" data-cat="<?= e($pCat) ?>">
          <td><?php if ($p['image']): ?><img src="<?= e(product_image($p['image'])) ?>" alt="<?= e($p['name']) ?>" style="width:48px;height:48px;border-radius:8px;object-fit:cover"><?php else: ?><div style="width:48px;height:48px;border-radius:8px;background:var(--surface);display:flex;align-items:center;justify-content:center;font-size:1.3rem">🐟</div><?php endif; ?></td>
          <td style="font-weight:600"><?= e($p['name']) ?></td>
          <td><span style="font-size:.7rem;color:var(--muted)"><?= e($p['category_name'] ?? '—') ?></span></td>
          <td style="text-align:center"><?php if ($t3): ?><span style="font-weight:600">RM<?= number_format((float)$t3['price'], 0) ?></span> / <?= (int)$t3['stock'] ?> Unit<?php else: ?>-<?php endif; ?></td>
          <td style="text-align:center"><?php if ($t6): ?><span style="font-weight:600">RM<?= number_format((float)$t6['price'], 0) ?></span> / <?= (int)$t6['stock'] ?> Unit<?php else: ?>-<?php endif; ?></td>
          <td style="text-align:center"><?php if ($t10): ?><span style="font-weight:600">RM<?= number_format((float)$t10['price'], 0) ?></span> / <?= (int)$t10['stock'] ?> Unit<?php else: ?>-<?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>
</div></div>

<script>
function filterProductsByCat(cat) {
  document.querySelectorAll('.cat-filter').forEach(function(el) {
    if (el.getAttribute('data-cat') === cat) {
      el.style.borderColor = 'var(--brand)';
      el.style.background = 'var(--brand)';
      el.style.color = '#0a0e1a';
    } else {
      el.style.borderColor = 'var(--border)';
      el.style.background = 'var(--surface)';
      el.style.color = 'var(--muted)';
    }
  });
  document.querySelectorAll('.product-row').forEach(function(row) {
    var rowCat = row.getAttribute('data-cat');
    if (cat === 'all' || rowCat === cat) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}
</script>
