<?php
$revenue = array_sum(array_map(fn(array $order): float => (float) $order['total_amount'], $orders));
$pageView = 'dashboard';
$extraHead = admin_css();

// Group orders by status for filter cards
$statusGroups = [];
foreach ($orders as $o) {
    $s = $o['status'];
    if (!isset($statusGroups[$s])) $statusGroups[$s] = ['cnt' => 0, 'total' => 0.0];
    $statusGroups[$s]['cnt']++;
    $statusGroups[$s]['total'] += (float)$o['total_amount'];
}
?>
<div class="page-header"><div class="container"><h1>⚙️ Admin Dashboard</h1><p>Product, order and user management</p></div></div>
<div class="container"><div class="admin-layout">
  <div class="admin-sidebar"><div class="admin-sidebar-header"><h3 style="color:var(--text);font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:1px">⚙ Management</h3></div><?= admin_sidebar('dashboard') ?></div>
  <div>
    <div class="stat-cards">
      <div class="stat-card"><div style="width:52px;height:52px;border-radius:14px;background:rgba(45,212,191,0.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem">💰</div><div><div style="font-size:1.6rem;font-weight:900;color:var(--brand-light)">RM <?= e(formatted_money($revenue)) ?></div><div style="font-size:.78rem;color:var(--muted);margin-top:4px;font-weight:600;text-transform:uppercase">Total Revenue</div></div></div>
      <div class="stat-card"><div style="width:52px;height:52px;border-radius:14px;background:rgba(251,113,133,0.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem">📦</div><div><div style="font-size:1.6rem;font-weight:900;color:var(--coral)"><?= count($orders) ?></div><div style="font-size:.78rem;color:var(--muted);margin-top:4px;font-weight:600;text-transform:uppercase">Total Orders</div></div></div>
      <div class="stat-card"><div style="width:52px;height:52px;border-radius:14px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem">🐟</div><div><div style="font-size:1.6rem;font-weight:900;color:var(--amber-light)"><?= count($allProducts) ?></div><div style="font-size:.78rem;color:var(--muted);margin-top:4px;font-weight:600;text-transform:uppercase">Products</div></div></div>
      <div class="stat-card"><div style="width:52px;height:52px;border-radius:14px;background:rgba(96,165,250,0.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem">👥</div><div><div style="font-size:1.6rem;font-weight:900;color:#93c5fd"><?= count(all_users()) ?></div><div style="font-size:.78rem;color:var(--muted);margin-top:4px;font-weight:600;text-transform:uppercase">Users</div></div></div>
    </div>
    <div class="admin-card">
      <div class="admin-card-header"><h3 style="font-weight:700;font-size:.95rem">📦 Order Status</h3></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.5rem;margin-bottom:1rem;padding:0 1rem" id="order-status-filters">
        <div class="status-filter active" data-status="all" onclick="filterOrders('all')" style="border:2px solid var(--brand);border-radius:var(--radius-sm);padding:.6rem .8rem;cursor:pointer;background:var(--brand);color:#0a0e1a;transition:.12s;text-align:center">
          <div style="font-size:1.2rem;font-weight:900"><?= count($orders) ?></div>
          <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px">All Orders</div>
        </div>
        <?php foreach ($statusGroups as $status => $sg):
          $label = match($status){'pending'=>'Pending','confirmed'=>'Confirmed','shipping'=>'Shipping','delivered'=>'Delivered','completed'=>'Completed','cancelled'=>'Cancelled',default=>ucfirst($status)};
          $accent = match($status){'pending'=>'#f59e0b','confirmed'=>'#60a5fa','shipping'=>'#2dd4bf','delivered'=>'#10b981','completed'=>'#10b981','cancelled'=>'#fb7185',default=>'#94a3b8'};
        ?>
        <div class="status-filter" data-status="<?= e($status) ?>" onclick="filterOrders('<?= e($status) ?>')" style="border:2px solid var(--border);border-radius:var(--radius-sm);padding:.6rem .8rem;cursor:pointer;background:var(--surface);transition:.12s;text-align:center">
          <div style="font-size:1.2rem;font-weight:900;color:<?= $accent ?>"><?= $sg['cnt'] ?></div>
          <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)"><?= $label ?></div>
          <div style="font-size:.7rem;font-weight:800;color:var(--amber-light)">RM<?= number_format($sg['total'], 0) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="admin-card-header" style="border-top:1px solid var(--border);padding-top:1rem">
        <h3 style="font-weight:700;font-size:.95rem">📦 Orders</h3>
        <span style="font-size:.75rem;color:var(--muted)" id="orders-count">Showing <?= count($orders) ?> orders</span>
      </div>
      <table><thead><tr><th>Order No.</th><th>Customer</th><th>Status</th><th>Amount</th><th>Date</th></tr></thead><tbody id="orders-tbody">
      <?php foreach ($orders as $order): ?>
        <tr class="order-row" data-status="<?= e($order['status']) ?>">
          <td>#<?= e($order['order_number']) ?></td>
          <td><?= e($order['customer'] ?? '—') ?></td>
          <td><span class="badge badge-<?= e($order['status_class']) ?>"><?= e($order['status_label']) ?></span></td>
          <td>RM <?= e(formatted_money((float) $order['total_amount'])) ?></td>
          <td><?= e($order['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>
</div></div>

<script>
function filterOrders(status) {
  document.querySelectorAll('.status-filter').forEach(function(el) {
    if (el.getAttribute('data-status') === status) {
      el.style.borderColor = 'var(--brand)';
      el.style.background = 'var(--brand)';
      el.style.color = '#0a0e1a';
      var divs = el.querySelectorAll('div');
      if (divs.length > 1) divs[1].style.color = '#0a0e1a';
    } else {
      el.style.borderColor = 'var(--border)';
      el.style.background = 'var(--surface)';
      el.style.color = '';
      var divs = el.querySelectorAll('div');
      if (divs.length > 1) divs[1].style.color = 'var(--muted)';
    }
  });
  var visibleCount = 0;
  document.querySelectorAll('.order-row').forEach(function(row) {
    var rowStatus = row.getAttribute('data-status');
    if (status === 'all' || rowStatus === status) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });
  var countEl = document.getElementById('orders-count');
  if (countEl) {
    var label = status === 'all' ? 'all statuses' : status;
    countEl.textContent = 'Showing ' + visibleCount + ' orders in ' + label;
  }
}
</script>
