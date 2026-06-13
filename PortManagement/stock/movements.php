<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$movement_type = $_GET['type'] ?? '';
$date_preset = $_GET['preset'] ?? '30';
$pg = max(1, (int)($_GET['pg'] ?? 1));
$per_page = 6;

$date_filter = "NOW() - INTERVAL 30 DAY";
if ($date_preset === '1') $date_filter = "CURRENT_DATE";
elseif ($date_preset === '7') $date_filter = "NOW() - INTERVAL 7 DAY";
elseif ($date_preset === '90') $date_filter = "NOW() - INTERVAL 90 DAY";
elseif ($date_preset === 'all') $date_filter = "'1970-01-01'";

$movements = [];
$daily_summary = [];
$total_pages = 1;
try {
    $sql = "SELECT sm.id, p.name as product, sm.quantity, sm.notes,
                   sm.balance_before, sm.balance_after,
                   sm.movement_type as movement_type, sm.created_at as created_at
            FROM stock_move sm
            JOIN product p ON p.id = sm.product_id
            WHERE sm.created_at >= $date_filter";
    $params = [];
    if ($movement_type === 'in') {
        $sql .= " AND sm.movement_type IN ('receive', 'transfer_in', 'return')";
    } elseif ($movement_type === 'out') {
        $sql .= " AND sm.movement_type IN ('sale', 'transfer_out', 'damage', 'expired', 'adjustment')";
    }

    // Count total for pagination
    $countSql = preg_replace('/SELECT sm\.id.*?FROM/', 'SELECT COUNT(*) FROM', $sql);
    $total_count = (int)dbGetValue($countSql, $params);
    $total_pages = max(1, (int)ceil($total_count / $per_page));
    $offset = ($pg - 1) * $per_page;

    $sql .= " ORDER BY sm.created_at DESC LIMIT $per_page OFFSET $offset";
    $movements = dbGetAll($sql, $params);

    $daily_summary = dbGetAll(
        "SELECT DATE(created_at) as day,
                SUM(CASE WHEN movement_type IN ('receive', 'transfer_in', 'return') THEN quantity ELSE 0 END) as inbound,
                SUM(CASE WHEN movement_type IN ('sale', 'transfer_out', 'damage', 'expired', 'adjustment') THEN quantity ELSE 0 END) as outbound,
                COUNT(*) as total_movements
         FROM stock_move
         WHERE created_at >= $date_filter
         GROUP BY day ORDER BY day DESC LIMIT 10"
    );
} catch (Exception $e) { $movements = []; $daily_summary = []; }

$total_movements = count($movements);
$total_in = 0; $total_out = 0;
foreach ($movements as $m) {
    $mt = $m['movement_type'] ?? '';
    if (in_array($mt, ['receive', 'transfer_in', 'return'])) $total_in += (float)($m['quantity'] ?? 0);
    else $total_out += (float)($m['quantity'] ?? 0);
}

$title = 'Stock Movements';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.preset-tag { display: inline-block; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; background: rgba(255,255,255,0.06); color: var(--muted); transition: .12s; border: 1px solid transparent; }
.preset-tag:hover { background: rgba(255,255,255,0.10); color: var(--text); }
.preset-tag.active { background: var(--brand); color: #081225; }
.type-tag { display: inline-block; padding: 8px 20px; font-size: 13px; font-weight: 700; text-decoration: none; border-radius: 8px; transition: .12s; }
.type-tag:hover { background: rgba(255,255,255,0.06); }
.type-tag.active { background: rgba(45,212,191,0.12); color: var(--brand); }
.summary-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 16px; }
.summary-card { background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: 10px; padding: 16px; }
.summary-card .label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin-bottom: 4px; }
.summary-card .value { font-size: 22px; font-weight: 800; }
.day-card { background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: 10px; padding: 12px 16px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
.day-card .day-label { font-weight: 700; font-size: 13px; }
.day-card .day-details { display: flex; gap: 16px; font-size: 12px; color: var(--muted); }
.bar-container { display: flex; align-items: center; gap: 8px; flex: 1; max-width: 180px; }
.bar-bg { height: 6px; border-radius: 3px; flex: 1; background: rgba(255,255,255,0.08); overflow: hidden; }
.bar-fill-in { height: 100%; background: var(--brand); border-radius: 3px; }
.bar-fill-out { height: 100%; background: #fbbf24; border-radius: 3px; }
</style>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🏭 Warehouse</div>
    <a class="dash-sidebar-item" href="/dashboard/stock/">📋 Stock</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/port/1/">🏭 Port Klang</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/port/2/">🏭 Penang Port</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/port/3/">🏭 Johor Port</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/port/4/">🏭 Kuantan Port</a>
    <div style="border-top:1px solid var(--border);margin:8px 14px;"></div>
    <a class="dash-sidebar-item active" href="/dashboard/stock/movements/">📊 Movements</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/add/">➕ Add Product</a>
  </nav>

  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <h2 style="margin:0;">📊 Stock Movements</h2>
      <p class="subtle" style="margin:4px 0 0;">Inventory movement tracking — inbound, outbound, daily summaries</p>
    </div>

    <div class="summary-grid">
      <div class="summary-card"><div class="label">Total Movements</div><div class="value" style="color:var(--text);"><?= e($total_movements) ?></div></div>
      <div class="summary-card"><div class="label">Inbound</div><div class="value" style="color:var(--brand);"><?= number_format($total_in, 2) ?> kg</div></div>
      <div class="summary-card"><div class="label">Outbound</div><div class="value" style="color:#fbbf24;"><?= number_format($total_out, 2) ?> kg</div></div>
    </div>

    <div class="card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;gap:4px;">
          <a href="?type=in&preset=<?= e($date_preset) ?>" class="type-tag <?= $movement_type === 'in' ? 'active' : '' ?>">📥 IN</a>
          <a href="?type=out&preset=<?= e($date_preset) ?>" class="type-tag <?= $movement_type === 'out' ? 'active' : '' ?>">📤 OUT</a>
          <a href="?preset=<?= e($date_preset) ?>" class="type-tag <?= !$movement_type ? 'active' : '' ?>">All</a>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <a href="?preset=1<?= $movement_type ? '&type='.urlencode($movement_type) : '' ?>" class="preset-tag <?= $date_preset === '1' ? 'active' : '' ?>">Today</a>
          <a href="?preset=7<?= $movement_type ? '&type='.urlencode($movement_type) : '' ?>" class="preset-tag <?= $date_preset === '7' ? 'active' : '' ?>">7 Days</a>
          <a href="?preset=30<?= $movement_type ? '&type='.urlencode($movement_type) : '' ?>" class="preset-tag <?= $date_preset === '30' ? 'active' : '' ?>">30 Days</a>
          <a href="?preset=90<?= $movement_type ? '&type='.urlencode($movement_type) : '' ?>" class="preset-tag <?= $date_preset === '90' ? 'active' : '' ?>">90 Days</a>
          <a href="?preset=all<?= $movement_type ? '&type='.urlencode($movement_type) : '' ?>" class="preset-tag <?= $date_preset === 'all' ? 'active' : '' ?>">All Time</a>
        </div>
      </div>
    </div>

    <div class="card" style="overflow-x:auto;margin-bottom:16px;">
        <h3 style="margin:0 0 16px;font-size:15px;">� Movement Log</h3>
        <table class="activity-table">
            <thead><tr><th>Product</th><th>Qty</th><th>Type</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($movements as $m): ?>
              <?php
                $mt = $m['movement_type'] ?? '';
                $isIn = in_array($mt, ['receive', 'transfer_in', 'return']);
                $typeLabel = match($mt) {
                  'receive' => '📥 Receive',
                  'transfer_in' => '📥 Transfer In',
                  'return' => '📥 Return',
                  'sale' => '📤 Sale',
                  'transfer_out' => '📤 Transfer Out',
                  'damage' => '📤 Damage',
                  'expired' => '📤 Expired',
                  'adjustment' => '⚖️ Adjustment',
                  default => '📤 Out'
                };
              ?>
              <tr>
                <td><strong><?= e($m['product'] ?? '') ?></strong></td>
                <td style="font-weight:700;"><?= number_format((float)($m['quantity'] ?? 0), 2) ?> kg</td>
                <td>
                  <span class="status-badge <?= $isIn ? 'badge-ok' : '' ?>" style="<?= !$isIn ? 'background:rgba(96,165,250,0.12);color:#60a5fa;' : '' ?>"><?= $typeLabel ?></span>
                </td>
                <td style="color:var(--muted);font-size:12px;"><?= e(substr($m['created_at'] ?? '', 0, 16)) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($movements)): ?><tr><td colspan="5" style="text-align:center;padding:30px;color:var(--muted);">No movements recorded.</td></tr><?php endif; ?>
            </tbody>
          </table>
          <?php if ($total_pages > 1): ?>
          <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:16px;padding-top:12px;border-top:1px solid var(--border);">
            <?php
              $currentPath = strtok($_SERVER['REQUEST_URI'], '?');
              $buildUrl = fn($p) => $currentPath . '?' . http_build_query(array_merge($_GET, ['pg' => $p]));
            ?>
            <?php if ($pg > 1): ?>
              <a href="<?= e($buildUrl(1)) ?>" style="padding:6px 12px;border-radius:6px;background:rgba(255,255,255,0.06);color:var(--text);text-decoration:none;font-size:12px;">««</a>
              <a href="<?= e($buildUrl($pg - 1)) ?>" style="padding:6px 12px;border-radius:6px;background:rgba(255,255,255,0.06);color:var(--text);text-decoration:none;font-size:12px;">«</a>
            <?php endif; ?>
            <span style="font-size:13px;color:var(--muted);padding:0 8px;">Page <?= $pg ?> / <?= $total_pages ?></span>
            <?php if ($pg < $total_pages): ?>
              <a href="<?= e($buildUrl($pg + 1)) ?>" style="padding:6px 12px;border-radius:6px;background:rgba(255,255,255,0.06);color:var(--text);text-decoration:none;font-size:12px;">»</a>
              <a href="<?= e($buildUrl($total_pages)) ?>" style="padding:6px 12px;border-radius:6px;background:rgba(255,255,255,0.06);color:var(--text);text-decoration:none;font-size:12px;">»»</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
      </div>

      <div class="card" style="overflow-x:auto;">
        <h3 style="margin:0 0 16px;font-size:15px;">� Daily Summary</h3>
        <?php if (!empty($daily_summary)): ?>
        <?php foreach ($daily_summary as $d): ?>
        <?php
          $inVal = (float)($d['inbound'] ?? 0);
          $outVal = (float)($d['outbound'] ?? 0);
          $totalVal = $inVal + $outVal;
          $inPct = $totalVal > 0 ? ($inVal / $totalVal) * 100 : 0;
          $outPct = $totalVal > 0 ? ($outVal / $totalVal) * 100 : 0;
        ?>
        <div class="day-card">
          <div class="day-label"><?= e($d['day'] ?? '') ?></div>
          <div class="bar-container">
            <span style="font-size:11px;font-weight:700;color:var(--brand);min-width:50px;text-align:right;"><?= number_format($inVal, 1) ?></span>
            <div class="bar-bg"><div class="bar-fill-in" style="width:<?= e($inPct) ?>%;"></div></div>
          </div>
          <div class="bar-container">
            <span style="font-size:11px;font-weight:700;color:#fbbf24;min-width:50px;text-align:right;"><?= number_format($outVal, 1) ?></span>
            <div class="bar-bg"><div class="bar-fill-out" style="width:<?= e($outPct) ?>%;"></div></div>
          </div>
          <div class="day-details">
            <span><?= e($d['total_movements'] ?? 0) ?> entries</span>
          </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;padding:30px;color:var(--muted);">No movement data for selected period.</div>
        <?php endif; ?>
      </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
