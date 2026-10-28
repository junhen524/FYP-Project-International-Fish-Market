<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    if ($userId > 0 && $amount > 0) {
        try {
            $before = (float)dbGetValue("SELECT COALESCE(balance, 0) FROM market_user WHERE id = ?", [$userId]);
            $after = $before + $amount;
            // Update balance directly on market_user
            dbExecute("UPDATE market_user SET balance = ?, updated_at = NOW() WHERE id = ?", [$after, $userId]);
            dbExecute(
                "INSERT INTO market_wallet_txn (user_id, transaction_type, amount, balance_before, balance_after, description, status, created_at)
                 VALUES (?, 'topup', ?, ?, ?, 'Admin top-up', 'completed', NOW())",
                [$userId, $amount, $before, $after]
            );
            $message = 'Successfully topped up RM ' . number_format($amount, 2) . '.';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Select a user and enter an amount.';
    }
}

$search_q = $_GET['q'] ?? '';
$users = [];
try {
    $sql = "SELECT u.id, u.username, u.full_name, COALESCE(u.balance, 0) as balance "
          . "FROM market_user u";
    $params = [];
    if ($search_q) {
        $sql .= " WHERE LOWER(u.username) LIKE LOWER(?) OR LOWER(u.full_name) LIKE LOWER(?) OR LOWER(u.email) LIKE LOWER(?)";
        $params = array_merge($params, ["%$search_q%", "%$search_q%", "%$search_q%"]);
    }
    $sql .= " ORDER BY u.id";
    $users = dbGetAll($sql, $params);
} catch (Exception $e) {}

// ── Fetch topup transactions ──
$txnSearch = $_GET['txn_q'] ?? '';
$txns = [];
try {
    $sql = "SELECT wtx.*, mu.username, mu.full_name
            FROM market_wallet_txn wtx
            LEFT JOIN market_user mu ON mu.id = wtx.user_id
            WHERE wtx.transaction_type = 'topup'";
    $params = [];
    if ($txnSearch) {
        $sql .= " AND (LOWER(mu.username) LIKE LOWER(?) OR LOWER(mu.full_name) LIKE LOWER(?))";
        $p = "%$txnSearch%";
        $params[] = $p;
        $params[] = $p;
    }
    $sql .= " ORDER BY wtx.created_at DESC LIMIT 200";
    $txns = dbGetAll($sql, $params);
} catch (Exception $e) {}

$title = 'Market Top-Up';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.topup-table { width: 100%; border-collapse: collapse; }
.topup-table th { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); padding: 12px; border-bottom: 1px solid var(--border); }
.topup-table td { padding: 14px 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
.topup-table tr:hover td { background: rgba(255, 255, 255, 0.06); }
.topup-input { padding: 8px 12px; border-radius: 8px; background: rgba(255, 255, 255, 0.10); border: 1px solid var(--border); color: var(--text); width: 100px; font-size: 13px; }
.btn-topup { padding: 8px 18px; border-radius: 8px; border: none; background: var(--brand); color: #081225; font-weight: 700; font-size: 12px; cursor: pointer; }
.btn-topup:hover { filter: brightness(1.1); }
.txn-credit { color: #34d399; }
.txn-debit { color: #ef4444; }
.badge-stock { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; }
</style>
<div class="dash-layout">
<?php $sidebarActive = 'topup'; require __DIR__ . '/../helpers/sidebar_market.php'; ?>

  <div class="dash-content">
    <div class="card" style="margin-bottom:20px;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
          <h2 style="margin:0;">💰 Wallet Top-Up</h2>
          <p class="subtle" style="margin:4px 0 0;">Add balance to domestic user wallets</p>
        </div>
        <form method="get" style="display:flex;gap:6px;">
          <input type="search" name="q" placeholder="Search users..." value="<?= e($_GET['q'] ?? '') ?>" class="form-control" style="width:200px;">
          <button type="submit" class="btn btn-ghost" style="padding:10px 14px;font-size:13px;font-weight:700;">🔍 Search</button>
          <?php if (!empty($_GET['q'])): ?>
          <a href="/dashboard/analytics/market/topup/" class="btn btn-ghost" style="padding:8px 14px;">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <?php if ($message): ?><div class="msg-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg-error"><?= e($error) ?></div><?php endif; ?>

    <div class="card" style="overflow-x:auto;">
      <table class="topup-table">
        <thead><tr><th>User</th><th>Name</th><th>Current Balance</th><th>Top-Up Amount</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><strong><?= e($u['username'] ?? '') ?></strong></td>
            <td><?= e($u['full_name'] ?? '') ?></td>
            <td style="font-weight:700;">RM<?= number_format($u['balance'] ?? 0, 2) ?></td>
            <td>
              <form method="POST" style="display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                <input type="number" name="amount" class="topup-input" placeholder="0.00" step="0.01" min="1" required>
                <button class="btn-topup" type="submit">Top Up</button>
              </form>
            </td>
            <td></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?><tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted);">No users found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Transaction History -->
    <div class="card" style="margin-top:24px;">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:16px 20px;border-bottom:1px solid var(--border);">
        <div>
          <h3 style="margin:0;font-size:1rem;">📋 Top-Up History</h3>
          <p class="subtle" style="margin:2px 0 0;">All top-up transactions</p>
        </div>
        <form method="get" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
          <input type="search" name="txn_q" placeholder="Search user..." value="<?= e($txnSearch) ?>" style="padding:6px 10px;border-radius:6px;background:rgba(255,255,255,0.06);border:1px solid var(--border);color:var(--text);font-size:12px;width:160px;">
          <button type="submit" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;">🔍</button>
          <?php if ($txnSearch): ?>
          <a href="/dashboard/analytics/market/topup/" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;">Clear</a>
          <?php endif; ?>
        </form>
      </div>
      <div style="overflow-x:auto;">
        <table class="topup-table">
          <thead>
            <tr>
              <th>Date / Time</th>
              <th>User</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Balance Before</th>
              <th>Balance After</th>
              <th>Description</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($txns): ?>
            <?php foreach ($txns as $t):
              $type = $t['transaction_type'] ?? '';
              $typeIcon = $type === 'topup' ? '💰' : ($type === 'payment' ? '💳' : ($type === 'refund' ? '↩️' : ($type === 'withdrawal' ? '🏧' : '🔁')));
              $amt = (float)($t['amount'] ?? 0);
              $amtClass = in_array($type, ['topup', 'refund']) ? 'txn-credit' : 'txn-debit';
            ?>
            <tr>
              <td style="font-size:11px;white-space:nowrap;"><?= e(date('Y-m-d H:i', strtotime($t['created_at'] ?? ''))) ?></td>
              <td><strong><?= e($t['full_name'] ?: $t['username'] ?: '—') ?></strong></td>
              <td><?= $typeIcon ?> <?= e(ucfirst($type)) ?></td>
              <td class="<?= $amtClass ?>" style="font-weight:700;">RM<?= number_format($amt, 2) ?></td>
              <td style="color:var(--muted);">RM<?= number_format((float)($t['balance_before'] ?? 0), 2) ?></td>
              <td style="font-weight:600;">RM<?= number_format((float)($t['balance_after'] ?? 0), 2) ?></td>
              <td style="color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($t['description'] ?: '-') ?></td>
              <td><span class="badge-stock b-ok" style="padding:2px 8px;border-radius:12px;font-size:11px;"><?= e(ucfirst($t['status'] ?? 'completed')) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No transactions found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
