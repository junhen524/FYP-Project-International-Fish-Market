<?php $extraHead = '<style>
.order-detail-grid{display:grid;grid-template-columns:1fr 340px;gap:2rem;align-items:start}.detail-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.75rem;backdrop-filter:blur(8px);margin-bottom:1.5rem}.detail-card h3{font-family:var(--font-display);font-size:1.15rem;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:1px solid var(--border)}.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.item-row{display:flex;align-items:center;gap:1rem;padding:.9rem 0;border-bottom:1px solid var(--border)}.item-img{width:54px;height:54px;border-radius:8px;background:rgba(255,255,255,0.04);display:flex;align-items:center;justify-content:center;overflow:hidden}.item-img img{width:100%;height:100%;object-fit:cover}
/* Shipping Timeline */
.timeline{position:relative;padding-left:2rem}.timeline::before{content:"";position:absolute;left:8px;top:4px;bottom:4px;width:2px;background:rgba(255,255,255,0.1)}.timeline-item{position:relative;padding-bottom:1.5rem;padding-left:1rem}.timeline-item:last-child{padding-bottom:0}.timeline-dot{position:absolute;left:-1.65rem;top:4px;width:16px;height:16px;border-radius:50%;border:2px solid var(--border);background:var(--surface);display:flex;align-items:center;justify-content:center;font-size:8px;z-index:1}.timeline-dot.done{border-color:var(--brand);background:var(--brand)}.timeline-dot.current{border-color:var(--amber-light);background:rgba(251,191,36,0.2);animation:pulse 2s infinite}@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(251,191,36,0.4)}50%{box-shadow:0 0 0 6px rgba(251,191,36,0)}}
.timeline-time{font-size:.72rem;color:var(--muted);margin-top:2px}
@media (max-width:768px){.order-detail-grid{grid-template-columns:1fr}.info-grid{grid-template-columns:1fr}}
</style>'; ?>
<div class="page-header"><div class="container"><div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap"><div><h1>Order Details</h1><p>#<?= e($order['order_number']) ?></p></div><span class="badge badge-<?= e($order['status_class']) ?>" style="font-size:.9rem;padding:.4rem 1rem"><?= e($order['status_label']) ?></span></div></div></div>
<div class="container"><div style="margin-bottom:1rem"><a href="<?= url_for('orders') ?>" class="btn btn-ghost btn-sm">← Back to Orders</a></div>
<div class="order-detail-grid">
<div>
  <div class="detail-card"><h3>🧺 Order Items</h3>
  <?php foreach ($order['items'] as $item): ?>
    <div class="item-row"><div class="item-img"><?php if ($item['image']): ?><img src="<?= e(product_image($item['image'])) ?>" alt=""><?php else: ?>🐟<?php endif; ?></div><div><div style="font-weight:700;font-size:.95rem"><?= e($item['product_name']) ?></div><div style="font-size:.82rem;color:var(--muted)"><?= (int) $item['quantity'] ?> × RM <?= e(formatted_money((float) $item['product_price'])) ?></div></div><div style="margin-left:auto;font-weight:800;color:var(--amber-light)">RM <?= e(formatted_money((float) $item['subtotal'])) ?></div></div>
  <?php endforeach; ?>
  <div style="border-top:2px solid var(--border);margin-top:1rem;padding-top:1rem;display:flex;justify-content:space-between"><span style="font-weight:700">Order Total</span><span style="font-size:1.35rem;font-weight:900;color:var(--amber-light)">RM <?= e(formatted_money((float) $order['total_amount'])) ?></span></div></div>

  <div class="detail-card"><h3>📋 Order Info</h3>
  <div class="info-grid">
    <div><div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:.3rem">Order Number</div><div style="font-weight:700;font-family:monospace;font-size:1rem">#<?= e($order['order_number']) ?></div></div>
    <div><div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:.3rem">Date Placed</div><div><?= e($order['created_at']) ?></div></div>
    <div><div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:.3rem">Phone</div><div><?= e($order['phone']) ?></div></div>
    <div><div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:.3rem">Payment</div><div>💰 E-Wallet</div></div>
    <?php
    // Extract recipient name from shipping_address if present
    $shipAddr = $order['shipping_address'];
    $recipientName = '';
    if (str_starts_with($shipAddr, 'Recipient: ')) {
      $nl = strpos($shipAddr, "\n");
      if ($nl !== false) {
        $recipientName = substr($shipAddr, 10, $nl - 10);
        $shipAddr = substr($shipAddr, $nl + 1);
      } else {
        $recipientName = substr($shipAddr, 10);
        $shipAddr = '';
      }
    }
    ?>
    <?php if ($recipientName): ?>
    <div><div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:.3rem">Recipient</div><div style="font-weight:700"><?= e($recipientName) ?></div></div>
    <?php endif; ?>
    <div style="grid-column:1/-1"><div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:.3rem">Delivery Address</div><div><?= e($shipAddr ?: $order['shipping_address']) ?></div></div>
    <?php if ($order['note'] ?? $order['notes'] ?? false): ?><div style="grid-column:1/-1"><div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:.3rem">Notes</div><div><?= e($order['note'] ?? $order['notes']) ?></div></div><?php endif; ?>
  </div></div>
</div>

<div>
  <div class="detail-card"><h3>🚚 Shipping Timeline</h3>
  <?php
  $timeline = [
    ['label' => 'Order Placed', 'icon' => '📝', 'key' => 'created_at'],
    ['label' => 'Driver Assigned & Pickup', 'icon' => '👤', 'key' => 'loaded'],
    ['label' => 'In Transit', 'icon' => '🚚', 'key' => 'in_transit'],
    ['label' => 'Delivered', 'icon' => '🎉', 'key' => 'completed'],
  ];
  $status = $order['status'];
  $shipStatus = $order['shipment_status'] ?? null;
  $currentIndex = -1;
  ?>
  <div class="timeline">
  <?php foreach ($timeline as $i => $step):
    // Determine if this step is done
    $isDone = false;
    if ($status === 'cancelled' || $status === 'refunded') {
      $isDone = ($i === 0);
    } else {
      $key = $step['key'];
      if ($key === 'created_at') {
        $isDone = true; // Order Placed is always done
      } elseif ($key === 'loaded') {
        $isDone = ($shipStatus === 'loaded' || $shipStatus === 'in_transit' || $shipStatus === 'delivered');
      } elseif ($key === 'in_transit') {
        $isDone = ($shipStatus === 'in_transit' || $shipStatus === 'delivered');
      } elseif ($key === 'completed') {
        $isDone = ($status === 'completed');
      }
    }
    $isCurrent = !$isDone && ($currentIndex < 0) && !in_array($status, ['cancelled','refunded']);
    if ($isCurrent) $currentIndex = $i;
    $dotClass = $isDone ? 'done' : ($isCurrent ? 'current' : '');
    // Determine timestamp for this step
    $ts = null;
    if ($isDone) {
        if ($i === 0) $ts = $order['created_at'] ?? null;
        else $ts = $order['updated_at'] ?? null;
    }
  ?>
    <div class="timeline-item">
      <div class="timeline-dot <?= $dotClass ?>"><?= $isDone ? '✓' : ($isCurrent ? '●' : '') ?></div>
      <div style="display:flex;align-items:center;gap:.5rem"><span style="font-size:1rem"><?= $step['icon'] ?></span><span style="font-weight:<?= $isDone||$isCurrent ? '700' : '400' ?>;color:<?= $isDone ? 'var(--brand-light)' : ($isCurrent ? 'var(--amber-light)' : 'var(--muted)') ?>"><?= $step['label'] ?></span></div>
      <?php if ($ts): ?><div class="timeline-time"><?= e($ts) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($status === 'cancelled'): ?>
    <div class="timeline-item">
      <div class="timeline-dot" style="border-color:#ef4444;background:#ef4444">✕</div>
      <div style="font-weight:700;color:#ef4444">❌ Order Cancelled</div>
    </div>
  <?php elseif ($status === 'refunded'): ?>
    <div class="timeline-item">
      <div class="timeline-dot" style="border-color:#f59e0b;background:rgba(245,158,11,0.2)">↩</div>
      <div style="font-weight:700;color:#f59e0b">↩️ Refunded</div>
    </div>
  <?php endif; ?>
  </div></div>

  <div class="detail-card"><h3>📦 Delivery Details</h3>
  <div style="display:flex;flex-direction:column;gap:.75rem">
    <div style="display:flex;justify-content:space-between;font-size:.88rem"><span style="color:var(--muted)">Status</span><span style="font-weight:700;color:<?= $status === 'completed' ? 'var(--brand-light)' : ($status === 'cancelled' ? '#ef4444' : 'var(--amber-light)') ?>"><?= e($order['status_label']) ?></span></div>
    <div style="display:flex;justify-content:space-between;font-size:.88rem"><span style="color:var(--muted)">Ordered At</span><span><?= e($order['created_at']) ?></span></div>
    <?php if (in_array($status, ['confirmed','shipping','completed'])): ?>
    <div style="display:flex;justify-content:space-between;font-size:.88rem"><span style="color:var(--muted)">Paid At</span><span><?= e($order['paid_at'] ?? $order['updated_at'] ?? '—') ?></span></div>
    <?php endif; ?>
    <?php if ($recipientName): ?>
    <div style="display:flex;justify-content:space-between;font-size:.88rem"><span style="color:var(--muted)">Recipient</span><span style="font-weight:700"><?= e($recipientName) ?></span></div>
    <?php endif; ?>
    <div style="display:flex;justify-content:space-between;font-size:.88rem"><span style="color:var(--muted)">Delivery To</span><span style="text-align:right;max-width:200px"><?= e($shipAddr ?: $order['shipping_address']) ?></span></div>
    <div style="display:flex;justify-content:space-between;font-size:.88rem"><span style="color:var(--muted)">Contact</span><span><?= e($order['phone']) ?></span></div>
  </div></div>

  <a href="<?= url_for('shop') ?>" class="btn btn-primary btn-block" style="margin-top:1rem">🛒 Continue Shopping</a>
</div>
</div></div>
