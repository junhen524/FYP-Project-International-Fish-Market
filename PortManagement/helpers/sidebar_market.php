<?php
/**
 * Unified sidebar for Market / Logistics pages.
 * Usage: require __DIR__ . '/../helpers/sidebar_market.php';
 * Set $sidebarActive to the matching key before including.
 */
$sidebarItems = [
    'dashboard' => ['/dashboard/analytics/market/', '📊', 'Dashboard'],
    'orders'    => ['/dashboard/analytics/market/orders/', '📋', 'Orders'],
    'users'     => ['/dashboard/analytics/market/users/', '👥', 'Users'],
    'topup'     => ['/dashboard/analytics/market/topup/', '💰', 'Top-Up'],
    'delivery'  => ['/logistics/', '🚛', 'Delivery'],
    'drivers'   => ['/logistics/drivers', '👤', 'Drivers'],
];
$sidebarActive = $sidebarActive ?? '';
?>
<nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🐟 Market</div>
    <?php foreach ($sidebarItems as $key => $item): ?>
    <a class="dash-sidebar-item<?= $sidebarActive === $key ? ' active' : '' ?>" href="<?= $item[0] ?>"><?= $item[1] ?> <?= $item[2] ?></a>
    <?php endforeach; ?>
</nav>
