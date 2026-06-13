<?php
$sq = trim((string)($_GET['q'] ?? ''));
$filtered = $sq !== '' ? search_orders($sq) : $orders;
$extraHead = admin_css();
$pageView = 'dashboard_orders';

// Pagination
$per_page = 10;
$total = count($filtered);
$total_pages = max(1, (int)ceil($total / $per_page));
$p = max(1, (int)($_GET['p'] ?? 1));
$offset = ($p - 1) * $per_page;
$page_orders = array_slice($filtered, $offset, $per_page);
?>
<style>
.order-row { cursor: pointer; }
.order-row td { transition: background .15s; }
.order-row:hover td { background: rgba(45,212,191,0.04); }
.items-row { display: none; }
.items-row.open { display: table-row; }
.items-cell { padding: 0 !important; }
.items-inner { padding: 12px 24px 16px; background: rgba(0,0,0,0.15); border-bottom: 1px solid var(--border); }
.items-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.items-table th { text-align: left; padding: 6px 10px; color: var(--muted); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid rgba(255,255,255,0.06); }
.items-table td { padding: 6px 10px; border-bottom: 1px solid rgba(255,255,255,0.04); }
.items-table tr:last-child td { border-bottom: none; }
.item-img { width: 36px; height: 36px; border-radius: 6px; object-fit: cover; background: rgba(255,255,255,0.06); }
.pagination { display: flex; justify-content: center; align-items: center; gap: 6px; padding: 16px 0; }
.pagination a { padding: 6px 14px; border-radius: 6px; background: rgba(255,255,255,0.06); color: var(--text); text-decoration: none; font-size: 13px; font-weight: 600; transition: .12s; }
.pagination a:hover { background: rgba(255,255,255,0.12); }
.pagination a.active { background: var(--brand); color: #081225; }
.pagination span { font-size: 13px; color: var(--muted); padding: 0 6px; }
</style>
<div class="page-header"><div class="container"><h1>📦 Order Management</h1><p>All orders from all customers</p></div></div>
<div class="container"><div class="admin-layout">
  <div class="admin-sidebar"><div class="admin-sidebar-header"><h3 style="color:var(--text);font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:1px">⚙ Management</h3></div><?= admin_sidebar('dashboard_orders') ?></div>
  <div>
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 style="font-weight:700;font-size:.95rem">📦 Orders (<?= $total ?>)</h3>
        <form method="get" action="<?= url_for('dashboard_orders') ?>" style="display:flex;gap:.5rem;align-items:center">
          <input type="hidden" name="page" value="dashboard_orders">
          <input type="text" name="q" class="admin-search" placeholder="Search orders…" value="<?= e($sq) ?>" style="width:220px">
          <button type="submit" class="btn btn-outline btn-sm">🔍</button>
          <?php if ($sq !== ''): ?><a href="<?= url_for('dashboard_orders') ?>" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
        </form>
      </div>
      <table><thead><tr><th>Order No.</th><th>Customer</th><th>Status</th><th>Amount</th><th>Date</th></tr></thead><tbody>
      <?php foreach ($page_orders as $order): $on = e($order['order_number']); ?>
        <tr class="order-row" onclick="toggleItems('<?= $on ?>')">
          <td>#<?= $on ?></td>
          <td><?= e($order['customer'] ?? '—') ?></td>
          <td><span class="badge badge-<?= e($order['status_class']) ?>"><?= e($order['status_label']) ?></span></td>
          <td>RM <?= e(formatted_money((float) $order['total_amount'])) ?></td>
          <td><?= e($order['created_at']) ?></td>
        </tr>
        <tr class="items-row" id="items-<?= $on ?>">
          <td colspan="5" class="items-cell">
            <div class="items-inner">
              <?php $items = $order['items'] ?? []; if (!empty($items)): ?>
              <table class="items-table">
                <thead><tr><th></th><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td>
                      <?php if (!empty($item['image'])): ?>
                        <img class="item-img" src="<?= e(product_image($item['image'])) ?>" alt="">
                      <?php else: ?>
                        <div class="item-img" style="display:inline-flex;align-items:center;justify-content:center;font-size:16px;">🐟</div>
                      <?php endif; ?>
                    </td>
                    <td style="font-weight:600;"><?= e($item['product_name'] ?? 'Product') ?></td>
                    <td><?= (int)($item['quantity'] ?? 0) ?></td>
                    <td>RM <?= e(formatted_money((float)($item['product_price'] ?? 0))) ?></td>
                    <td style="font-weight:700;">RM <?= e(formatted_money((float)($item['subtotal'] ?? 0))) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
              <?php else: ?>
                <div style="color:var(--muted);font-size:13px;">No item details available.</div>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php
          $url = fn($pg) => url_for('dashboard_orders', array_merge(
            $_GET,
            ['p' => $pg]
          ));
        ?>
        <?php if ($p > 1): ?><a href="<?= e($url(1)) ?>">««</a><a href="<?= e($url($p - 1)) ?>">«</a><?php endif; ?>
        <?php
          $start = max(1, $p - 2);
          $end = min($total_pages, $p + 2);
          for ($i = $start; $i <= $end; $i++):
        ?>
          <a href="<?= e($url($i)) ?>" class="<?= $i === $p ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($p < $total_pages): ?><a href="<?= e($url($p + 1)) ?>">»</a><a href="<?= e($url($total_pages)) ?>">»»</a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div></div>
<script>
function toggleItems(orderNum) {
  var row = document.getElementById('items-' + orderNum);
  if (row) {
    row.classList.toggle('open');
  }
}
</script>