<?php
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../services/VesselSimulationService.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';
$pageBaseUrl = baseUrl() . '/vessels/trips/';

// ── Load data ──
VesselSimulationService::tickSimulation();
$totalAllTrips = (int)dbGetValue("SELECT COUNT(*) FROM fishing_trips");
$tripsPerPage = 25; // 5 batches × 5 trips each
$totalPages = max(1, (int)ceil($totalAllTrips / $tripsPerPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $tripsPerPage;
$trips = dbGetAll("SELECT t.*, v.name as vessel_name, z.name as zone_name FROM fishing_trips t JOIN vessels v ON v.id = t.vessel_id JOIN fishery_zone z ON z.id = t.zone_id ORDER BY t.id DESC LIMIT " . (int)$tripsPerPage . " OFFSET " . (int)$offset);

$totalCatches = (int)dbGetValue("SELECT COUNT(*) FROM trip_catches");
$acceptedCatches = (int)dbGetValue("SELECT COUNT(*) FROM trip_catches WHERE status = 'accepted'");
$rejectedCatches = (int)dbGetValue("SELECT COUNT(*) FROM trip_catches WHERE status = 'rejected'");
$pendingSort = (int)dbGetValue("SELECT COUNT(*) FROM trip_catches WHERE status = 'landed'");
$totalTripsCompleted = (int)dbGetValue("SELECT COUNT(*) FROM fishing_trips WHERE status = 'completed'");
$avgAcceptRate = $totalCatches > 0 ? round(($acceptedCatches / $totalCatches) * 100) : 70;

$catchForTrip = [];
foreach ($trips as $t) {
    $tc = dbGetAll("SELECT tc.*, p.name as product_name FROM trip_catches tc JOIN product p ON p.id = tc.product_id WHERE tc.trip_id = ? ORDER BY tc.id", [$t['id']]);
    $catchForTrip[$t['id']] = !empty($tc) ? $tc : [];
}

function fmtTime($dt) { return $dt ? date('d M H:i', strtotime($dt)) : '--:--'; }
function tripDuration($start, $end) {
    if (!$start || !$end) return '--';
    $mins = round((strtotime($end) - strtotime($start)) / 60);
    if ($mins < 60) return "{$mins}m";
    return floor($mins / 60) . "h " . ($mins % 60) . "m";
}

$title = 'Trip Log & Sorting';
$extra_head = '<style>
.stats-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:12px;margin-bottom:20px; }
.stat-card { background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:12px;padding:14px 16px; }
.stat-card .num { font-size:1.4rem;font-weight:800; }
.stat-card .label { font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-top:2px; }
.stat-card.accepted .num { color:#34d399; }
.stat-card.rejected .num { color:#f87171; }
.stat-card.pending .num { color:#fbbf24; }

.trip-card { background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:14px;margin-bottom:14px;overflow:hidden; }
.trip-header { display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.06);cursor:pointer;transition:.12s; }
.trip-header:hover { background:rgba(255,255,255,0.03); }
.trip-header .vessel { font-weight:700;font-size:.95rem; }
.trip-header .vessel small { font-weight:400;color:var(--muted);margin-left:8px;font-size:.8rem; }
.trip-header .meta { display:flex;gap:12px;align-items:center; }
.trip-header .meta span { font-size:.75rem;color:var(--muted); }

/* ── Batch-level card ── */
.batch-card { background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:16px;margin-bottom:20px;overflow:hidden; }
.batch-header { display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:rgba(0,0,0,0.15);cursor:pointer;transition:.12s;user-select:none; }
.batch-header:hover { background:rgba(0,0,0,0.25); }
.batch-header .batch-title { font-weight:800;font-size:.95rem;display:flex;align-items:center;gap:10px; }
.batch-header .batch-title .batch-num { color:var(--brand); }
.batch-header .batch-meta { display:flex;gap:14px;align-items:center;font-size:.72rem;color:var(--muted); }
.batch-body { display:none; }
.batch-body.open { display:block; }

/* ── Sub-trip inside batch ── */
.sub-trip { border-bottom:1px solid rgba(255,255,255,0.05); }
.sub-trip:last-child { border-bottom:none; }
.sub-trip .trip-header { padding:10px 20px 10px 30px; }
.sub-trip .timeline { padding:8px 20px 8px 30px; }
.sub-trip .sort-summary { padding:6px 20px 6px 30px; }
.sub-trip .collapse-body.open { padding-left:12px; }

.trip-status { font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:3px 10px;border-radius:20px; }
.trip-status.outgoing { background:rgba(59,130,246,0.2);color:#60a5fa; }
.trip-status.fishing { background:rgba(5,150,105,0.2);color:#34d399; }
.trip-status.returning { background:rgba(217,119,6,0.2);color:#fbbf24; }
.trip-status.completed { background:rgba(100,116,139,0.2);color:#94a3b8; }

.timeline { display:flex;gap:0;padding:14px 18px;background:rgba(0,0,0,0.15);border-bottom:1px solid rgba(255,255,255,0.06); }
.tl-item { flex:1;text-align:center;position:relative; }
.tl-item:not(:last-child)::after { content:"";position:absolute;top:18px;left:60%;width:80%;height:2px;background:rgba(255,255,255,0.1); }
.tl-item.completed:not(:last-child)::after { background:#10b981; }
.tl-dot { width:14px;height:14px;border-radius:50%;background:rgba(255,255,255,0.1);margin:0 auto 6px; }
.tl-item.completed .tl-dot { background:#10b981; }
.tl-item.active .tl-dot { background:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.3); }
.tl-item .tl-label { font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted); }
.tl-item .tl-time { font-size:.72rem;font-weight:600;color:var(--text);margin-top:2px; }
.tl-item.completed .tl-time { color:#059669; }

.catches-table { width:100%;border-collapse:collapse;font-size:.82rem; }
.catches-table th { text-align:left;padding:8px 18px;font-size:.62rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);border-bottom:1px solid rgba(255,255,255,0.08);background:rgba(0,0,0,0.15); }
.catches-table td { padding:8px 18px;border-bottom:1px solid rgba(255,255,255,0.04); }
.badge { display:inline-block;font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:2px 10px;border-radius:20px; }
.badge-accepted { background:rgba(5,150,105,0.2);color:#34d399; }
.badge-rejected { background:rgba(220,38,38,0.2);color:#f87171; }
.badge-landed { background:rgba(217,119,6,0.2);color:#fbbf24; }

.filter-tabs { display:flex;gap:4px;margin-bottom:18px;flex-wrap:wrap; }
.filter-tab { padding:5px 14px;border:1px solid rgba(255,255,255,0.1);border-radius:20px;font-size:.75rem;font-weight:600;cursor:pointer;transition:.12s;background:rgba(255,255,255,0.04);color:#94a3b8; }
.filter-tab:hover { border-color:rgba(255,255,255,0.2); }
.filter-tab.active { background:rgba(45,212,191,0.12);border-color:var(--brand);color:var(--brand); }

.sort-summary { display:flex;gap:12px;padding:8px 18px;background:rgba(0,0,0,0.1); }
.sort-summary span { font-size:.72rem;font-weight:600; }
.sort-summary .ok { color:#34d399; }
.sort-summary .no { color:#f87171; }
.collapse-body { display:none; }
.collapse-body.open { display:block; }
</style>';
require __DIR__ . '/../helpers/header.php';
?>

<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🚢 Fleet</div>
    <a class="dash-sidebar-item" href="vessels/">🗺️ Fleet Dashboard</a>
    <a class="dash-sidebar-item active" href="vessels/trips/">🎣 Trip Log & Sorting</a>
  </nav>

  <div class="dash-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <div>
        <h2 style="margin:0;font-size:18px;">🎣 Trip Log & 🏭 Sorting</h2>
        <?php
        // ── Compute batches: newest trips first (DESC), globally unique batch numbers ──
        $batches = [];
        $totalBatchesGlobal = max(1, (int)ceil($totalAllTrips / 5));
        if (!empty($trips)) {
            $rawBatches = array_chunk($trips, 5);
            foreach ($rawBatches as $i => $batch) {
                $bStatuses = array_column($batch, 'status');
                $allComplete = count(array_filter($bStatuses, fn($s) => $s === 'completed')) === count($batch);
                $globalBatchPos = floor(($offset + $i * 5) / 5);  // 0-based group index globally
                $batchNum = $totalBatchesGlobal - (int)$globalBatchPos;
                $batches[] = ['trips' => $batch, 'allComplete' => $allComplete, 'batchIdx' => $batchNum];
            }
        }
        $batchIdx = 0;
        ?>
        <p class="subtle" style="margin:2px 0 0;"><?= count($batches) ?> batches, <?= $avgAcceptRate ?>% acceptance rate</p>
      </div>
    </div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card accepted"><div class="num"><?= $acceptedCatches ?></div><div class="label">✅ Accepted → Stock</div></div>
  <div class="stat-card rejected"><div class="num"><?= $rejectedCatches ?></div><div class="label">❌ Rejected</div></div>
  <?php if ($pendingSort > 0): ?>
  <div class="stat-card pending"><div class="num"><?= $pendingSort ?></div><div class="label">⏳ Pending Sort</div></div>
  <?php endif; ?>
  <div class="stat-card"><div class="num"><?= $totalCatches ?></div><div class="label">📦 Total Items</div></div>
  <div class="stat-card"><div class="num"><?= $totalTripsCompleted ?></div><div class="label">🚢 Trips Done</div></div>
  <div class="stat-card"><div class="num"><?= $avgAcceptRate ?>%</div><div class="label">📊 Accept Rate</div></div>
</div>

<!-- Filter tabs -->
<div class="filter-tabs">
  <span class="filter-tab active" data-status="all" onclick="filterTrips('all')">All (<?= count($batches) ?>)</span>
  <span class="filter-tab" data-status="outgoing" onclick="filterTrips('outgoing')">🚀 Outgoing</span>
  <span class="filter-tab" data-status="fishing" onclick="filterTrips('fishing')">🎣 Fishing</span>
  <span class="filter-tab" data-status="returning" onclick="filterTrips('returning')">🏁 Returning</span>
</div>

<?php if (empty($batches)): ?>
<div style="text-align:center;padding:4rem;color:var(--muted);">Trips will appear when vessels go to sea.</div>
<?php else: ?>
<div id="trips-container">
<?php foreach ($batches as $batchInfo):
  $batch = $batchInfo['trips'];
  $allComplete = $batchInfo['allComplete'];
  $batchIdx = $batchInfo['batchIdx'];
  $bIds = array_column($batch, 'id');
  $bStatuses = array_column($batch, 'status');
  $bAccepted = 0; $bRejected = 0; $bPending = 0; $bTotal = 0;
  foreach ($bIds as $bid) {
    if (!empty($catchForTrip[$bid])) {
      foreach ($catchForTrip[$bid] as $c) {
        $bTotal++;
        if ($c['status'] === 'accepted') $bAccepted++;
        elseif ($c['status'] === 'rejected') $bRejected++;
        else $bPending++;
      }
    }
  }
  // Determine batch status
  $allComplete = count(array_filter($bStatuses, fn($s) => $s === 'completed')) === count($batch);
  $anyFishing = count(array_filter($bStatuses, fn($s) => $s === 'fishing' || $s === 'outgoing' || $s === 'returning')) > 0;
  $batchStatus = $allComplete ? 'completed' : ($anyFishing ? 'active' : 'docked');
  $firstTrip = $batch[0];
  $batchStarted = $firstTrip['started_at'] ? fmtTime($firstTrip['started_at']) : '—';
  $batchVesselList = implode(', ', array_column($batch, 'vessel_name'));
?>
<div class="batch-card" data-batch-idx="<?= $batchIdx ?>">
  <!-- Batch Header (click to toggle all 5 vessels) -->
  <div class="batch-header" onclick="this.nextElementSibling.classList.toggle('open')">
    <div class="batch-title">
      <span class="batch-num">⚓ Batch #<?= $batchIdx ?></span>
      <span style="font-weight:400;color:var(--muted);font-size:.82rem;"><?= count($batch) ?> vessels · <?= e($batchVesselList) ?></span>
    </div>
    <div class="batch-meta">
      <span>🚀 <?= $batchStarted ?></span>
      <span>📦 <?= $bTotal ?> items</span>
      <?php if ($bAccepted > 0): ?><span style="color:#34d399">✅ <?= $bAccepted ?></span><?php endif; ?>
      <?php if ($bRejected > 0): ?><span style="color:#f87171">❌ <?= $bRejected ?></span><?php endif; ?>
      <span class="trip-status <?= $allComplete ? 'completed' : 'active' ?>"><?= $allComplete ? '✅ Completed' : '🔄 In Progress' ?></span>
      <span style="font-size:1.2rem;color:var(--muted)">▾</span>
    </div>
  </div>

  <!-- Batch Body: expanded for active, collapsed for completed -->
  <div class="batch-body<?= !$allComplete ? ' open' : '' ?>">
  <?php foreach ($batch as $t):
    $tid = $t['id'];
    $status = $t['status'];
    $catches = $catchForTrip[$tid] ?? [];
    $numAccepted = count(array_filter($catches, fn($c) => $c['status'] === 'accepted'));
    $numRejected = count(array_filter($catches, fn($c) => $c['status'] === 'rejected'));
    $numPending = count(array_filter($catches, fn($c) => $c['status'] === 'landed'));

    $tlOutgoing = $t['started_at'] ? 'completed' : '';
    $tlFishing = $t['fishing_start'] ? 'completed' : '';
    $tlReturning = $t['fishing_end'] ? 'completed' : '';
    $tlCompleted = $t['completed_at'] ? 'completed' : '';
    if (!$tlOutgoing && $status === 'outgoing') $tlOutgoing = 'active';
    if (!$tlFishing && $status === 'fishing') $tlFishing = 'active';
    if (!$tlReturning && $status === 'returning') $tlReturning = 'active';
    if (!$tlCompleted && $status === 'completed') $tlCompleted = 'active';
  ?>
  <div class="sub-trip trip-card" data-status="<?= e($status) ?>" data-trip-id="<?= $t['id'] ?>">
    <!-- Vessel Header -->
    <div class="trip-header" onclick="this.parentElement.querySelector('.collapse-body').classList.toggle('open')">
      <div class="vessel">
        🚢 <?= e($t['vessel_name']) ?>
        <small>• <?= e($t['zone_name'] ?: 'Unknown') ?></small>
        <small style="color:var(--muted);font-weight:400;">#<?= $t['id'] ?></small>
      </div>
      <div class="meta">
        <span>⏱ <span class="trip-duration" data-start="<?= $t['started_at'] ?>" data-end="<?= $t['completed_at'] ?? $t['fishing_end'] ?? '' ?>"><?= tripDuration($t['started_at'], $t['completed_at'] ?? $t['fishing_end'] ?? date('Y-m-d H:i:s')) ?></span></span>
        <span class="trip-status <?= e($status) ?>"><?= e(ucfirst($status)) ?></span>
        <span style="font-size:1.2rem;color:var(--muted)">▾</span>
      </div>
    </div>

    <!-- Timeline -->
    <div class="timeline">
      <div class="tl-item <?= $tlOutgoing ?>">
        <div class="tl-dot"></div><div class="tl-label">Depart</div>
        <div class="tl-time"><?= fmtTime($t['started_at']) ?></div>
      </div>
      <div class="tl-item <?= $tlFishing ?>">
        <div class="tl-dot"></div><div class="tl-label">Fishing Start</div>
        <div class="tl-time"><?= fmtTime($t['fishing_start']) ?></div>
      </div>
      <div class="tl-item <?= $tlReturning ?>">
        <div class="tl-dot"></div><div class="tl-label">Fishing End</div>
        <div class="tl-time"><?= fmtTime($t['fishing_end']) ?></div>
      </div>
      <div class="tl-item <?= $tlCompleted ?>">
        <div class="tl-dot"></div><div class="tl-label">Return / Unload</div>
        <div class="tl-time"><?= fmtTime($t['completed_at']) ?></div>
      </div>
    </div>

    <!-- Sorting summary -->
    <?php if (!empty($catches)): ?>
    <div class="sort-summary">
      <span class="ok">✅ <?= $numAccepted ?> accepted → stock</span>
      <span class="no">❌ <?= $numRejected ?> rejected</span>
      <?php if ($numPending > 0): ?><span style="color:#fbbf24">⏳ <?= $numPending ?> pending</span><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Collapsible catch details -->
    <div class="collapse-body<?= $status === 'completed' ? ' open' : '' ?>">
      <?php if ($status === 'completed' && !empty($catches)): ?>
      <table class="catches-table">
        <thead><tr><th>Fish</th><th>Weight (kg)</th><th>Result</th><th>Detail</th></tr></thead>
        <tbody>
          <?php foreach ($catches as $c):
            $detail = '';
            if ($c['status'] === 'rejected' && !empty($c['reject_reason'])) {
              $detail = e($c['reject_reason']);
            } elseif ($c['status'] === 'accepted') {
              $detail = 'Stored in stock tier';
            }
          ?>
          <tr>
            <td style="font-weight:600"><?= e($c['product_name'] ?? 'Unknown Fish') ?></td>
            <td><?= number_format((float)$c['estimated_weight_kg'], 1) ?> kg</td>
            <td>
              <?php if ($c['status'] === 'accepted'): ?>
              <span class="badge badge-accepted">✅ Accepted</span>
              <?php elseif ($c['status'] === 'rejected'): ?>
              <span class="badge badge-rejected">❌ Rejected</span>
              <?php else: ?>
              <span class="badge badge-landed">⏳ Landed</span>
              <?php endif; ?>
            </td>
            <td style="color:#94a3b8;font-size:.72rem;max-width:280px;line-height:1.4"><?= $detail ?: '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div style="padding:1rem 1.25rem;color:var(--muted);font-size:.8rem;text-align:center"><?= $status === 'completed' ? 'No catch records for this trip.' : 'Sorting results after vessel returns and unloads.' ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Pagination: 5 batches per page -->
<div style="display:flex;align-items:center;justify-content:center;gap:6px;padding:20px 0 10px;flex-wrap:wrap">
  <?php if ($page > 1): ?>
  <a href="<?= $pageBaseUrl ?>?page=<?= $page-1 ?>" style="padding:6px 14px;border:1px solid rgba(255,255,255,0.12);border-radius:8px;font-size:.78rem;font-weight:600;text-decoration:none;color:#94a3b8;transition:.12s;">← Prev</a>
  <?php endif; ?>
  <?php
  $startPage = max(1, $page - 2);
  $endPage = min($totalPages, $page + 2);
  if ($startPage > 1) echo '<span style="color:var(--muted);font-size:.78rem;">…</span>';
  for ($p = $startPage; $p <= $endPage; $p++):
  ?>
  <a href="<?= $pageBaseUrl ?>?page=<?= $p ?>" style="padding:6px 13px;border:1px solid <?= $p === $page ? 'var(--brand)' : 'rgba(255,255,255,0.12)' ?>;border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none;color:<?= $p === $page ? 'var(--brand)' : '#94a3b8' ?>;background:<?= $p === $page ? 'rgba(45,212,191,0.08)' : 'transparent' ?>;transition:.12s;"><?= $p ?></a>
  <?php endfor; ?>
  <?php if ($endPage < $totalPages) echo '<span style="color:var(--muted);font-size:.78rem;">…</span>'; ?>
  <?php if ($page < $totalPages): ?>
  <a href="<?= $pageBaseUrl ?>?page=<?= $page+1 ?>" style="padding:6px 14px;border:1px solid rgba(255,255,255,0.12);border-radius:8px;font-size:.78rem;font-weight:600;text-decoration:none;color:#94a3b8;transition:.12s;">Next →</a>
  <?php endif; ?>
  <span style="color:var(--muted);font-size:.72rem;margin-left:8px;">Page <?= $page ?> of <?= $totalPages ?></span>
</div>
<?php endif; ?>

<script>
// ── Base path for API calls ──
var baseUrl = window.location.pathname.split('/FisherySystem/')[0] + '/FisherySystem/PortManagement';

function filterTrips(status) {
  document.querySelectorAll('.filter-tab').forEach(function(t) { t.classList.remove('active'); });
  var activeTab = document.querySelector('.filter-tab[data-status="' + status + '"]');
  if (activeTab) activeTab.classList.add('active');
  // Show/hide batch cards — match by sub-trip status or completed header
  document.querySelectorAll('.batch-card').forEach(function(batch) {
    if (status === 'all') {
      batch.style.display = '';
      return;
    }
    var hasMatch = false;
    batch.querySelectorAll('.sub-trip').forEach(function(card) {
      if (card.getAttribute('data-status') === status) hasMatch = true;
    });
    // Also match "completed" filter via batch header badge
    if (!hasMatch && status === 'completed') {
      var badge = batch.querySelector('.trip-status.completed');
      if (badge) hasMatch = true;
    }
    batch.style.display = hasMatch ? '' : 'none';
  });
}

// ── Auto-tick simulation every 30s — no tick on page load ──
function tickSim() {
  fetch(baseUrl + '/vessels/api/tick/').catch(function() {});
}
// First tick after 30s — no immediate tick (prevents instant departure on refresh)
setInterval(tickSim, 30000);

// ── Refresh ALL trip data every 30s — catches, statuses, durations ──
function refreshTrips() {
  fetch(window.location.href)
    .then(function(r) { return r.text(); })
    .then(function(html) {
      var parser = new DOMParser();
      var doc = parser.parseFromString(html, 'text/html');
      var newContainer = doc.querySelector('#trips-container');
      var container = document.querySelector('#trips-container');
      if (!container || !newContainer) return;
      // Replace entire container content (batch structure handles it)
      container.innerHTML = newContainer.innerHTML;
      updateDurations();
    })
    .catch(function() {});
}
setInterval(refreshTrips, 30000);

// ── Live update elapsed time every 10s (uses real wall clock, not ticks) ──
function updateDurations() {
  document.querySelectorAll('.trip-duration').forEach(function(el) {
    var start = el.getAttribute('data-start');
    var end = el.getAttribute('data-end');
    if (!start) return;
    var d1 = new Date(start.replace(' ', 'T'));
    var d2 = end ? new Date(end.replace(' ', 'T')) : new Date();
    var diffSec = Math.floor((d2 - d1) / 1000);
    if (diffSec < 0) diffSec = 0;
    var mins = Math.floor(diffSec / 60);
    var secs = diffSec % 60;
    el.textContent = mins < 60 ? mins + 'm ' + secs + 's' : Math.floor(mins / 60) + 'h ' + (mins % 60) + 'm';
  });
}
setInterval(updateDurations, 10000);
updateDurations();

// ── ⚠ NO automatic full page reload — AJAX handles all updates ──
</script>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
