<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};
require_once __DIR__ . '/includes/bootstrap.php';
intl_require_login();

$user = intl_current_user();

// ── Handle delete card (must be before any output) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_card' && isset($_POST['card_last4']) && $user) {
    $last4 = $_POST['card_last4'];
    $walletRow = intl_wallet_restaurant_id()
        ? dbGetRow("SELECT id, saved_cards FROM export_wallets WHERE restaurant_id = ?", [intl_wallet_restaurant_id()])
        : dbGetRow("SELECT id, saved_cards FROM export_wallets WHERE user_id = ?", [$user['id']]);
    if ($walletRow && $walletRow['saved_cards']) {
        $cards = json_decode($walletRow['saved_cards'], true);
        if (is_array($cards)) {
            $cards = array_values(array_filter($cards, function($c) use ($last4) {
                return ($c['last4'] ?? '') !== $last4;
            }));
            dbExecute("UPDATE export_wallets SET saved_cards = ? WHERE id = ?", [json_encode($cards), $walletRow['id']]);
        }
    }
    header('Location: ' . url_for('wallet'));
    exit;
}

$walletId = intl_ensure_wallet();
$balance = intl_wallet_balance();
$txns = dbGetAll("SELECT * FROM export_wallet_txn WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 20", [$walletId]);
$walletRow = dbGetRow("SELECT * FROM export_wallets WHERE id = ?", [$walletId]);
$savedCards = [];
if ($walletRow && $walletRow['saved_cards']) {
    $decoded = json_decode($walletRow['saved_cards'], true);
    if (is_array($decoded)) $savedCards = $decoded;
}

// ── Handle top-up ──
$msg = $_GET['msg'] ?? '';
$msgType = $_GET['msgType'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'topup') {
    $amount = (float)($_POST['amount'] ?? 0);
    $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $rememberCard = isset($_POST['remember_card']) && $_POST['remember_card'] === '1';
    $errors = [];
    if ($amount <= 0) $errors[] = 'Please select a valid amount.';
    if (!preg_match('/^\d{16}$/', $cardNumber)) $errors[] = 'Please enter a valid 16-digit card number.';
    if (empty($errors)) {
        $before = (float)dbGetValue("SELECT COALESCE(balance,0) FROM export_wallets WHERE id = ?", [$walletId]);
        $after = $before + $amount;
        dbExecute("UPDATE export_wallets SET balance = ?, updated_at = NOW() WHERE id = ?", [$after, $walletId]);
        $last4 = substr($cardNumber, -4);
        $desc = "Card top-up (****$last4)";
        dbExecute("INSERT INTO export_wallet_txn (wallet_id, transaction_type, amount, balance_before, balance_after, description, status, created_at) VALUES (?, 'topup', ?, ?, ?, ?, 'completed', NOW())", [$walletId, $amount, $before, $after, $desc]);
        // Save card if requested
        if ($rememberCard && $user) {
            $last4 = substr($cardNumber, -4);
            $exists = false;
            foreach ($savedCards as $sc) {
                if ($sc['last4'] === $last4) { $exists = true; break; }
            }
            if (!$exists) {
                $savedCards[] = ['last4' => $last4, 'number' => $cardNumber, 'brand' => 'Card'];
                dbExecute("UPDATE export_wallets SET saved_cards = ? WHERE id = ?", [json_encode($savedCards), $walletId]);
            }
        }
        header('Location: ' . url_for('wallet') . '?msg=' . urlencode('Top-up successful! $' . number_format($amount, 2) . ' added.') . '&msgType=success');
        exit;
    } else {
        $msg = implode(' ', $errors);
        $msgType = 'error';
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width,initial-scale=1" name="viewport">
<title>Wallet — International Fish Market</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{'brand-blue':'#0369a1','stone-150':'#e8e5e0','stone-250':'#d6d2cb','stone-350':'#b8b2a8','amber-350':'#d9995b'},fontFamily:{display:['Inter','system-ui','sans-serif'],mono:['JetBrains Mono','monospace']}}}}
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/wallet.css?v=<?= urlencode($__ifmAssetVersion('css/wallet.css')) ?>"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;overflow-x:hidden}
body{overflow-x:hidden;font-family:'Inter',system-ui,-apple-system,sans-serif}
.font-display{font-family:'Inter',system-ui,-apple-system,sans-serif;letter-spacing:-0.03em}
.card-input{font-family:'JetBrains Mono',monospace;letter-spacing:0.15em;font-size:18px;text-align:center;border:2px solid #d6d2cb;border-radius:12px;padding:14px 16px;width:100%;transition:border-color .2s;outline:none;background:#fff}
.card-input:focus{border-color:#0d9488;box-shadow:0 0 0 3px rgba(13,148,136,0.1)}
.card-input::placeholder{letter-spacing:0;font-size:14px;color:#cbd5e1}
.toast{position:fixed;top:24px;right:24px;z-index:9999;padding:14px 20px;border-radius:12px;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;box-shadow:0 8px 30px rgba(0,0,0,0.12);max-width:400px;animation:toastIn .3s ease;cursor:pointer}
.toast-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.toast-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
@keyframes toastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
@keyframes toastOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(40px)}}
</style>
</head>
<body style="margin: 0; overflow-x: hidden;">
<div id="root">
  <div class="relative min-h-screen bg-transparent text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip">
    <header id="main-app-header" class="fixed top-0 left-0 w-full z-50 transition-all duration-300 bg-transparent py-5">
      <div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center">
        <a href="<?= url_for('index') ?>" class="cursor-pointer flex items-center space-x-2 group" id="brand-logo-trigger">
          <span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">INTERNATIONAL FISH MARKET</span>
          <span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse"></span>
        </a>
        <?php require __DIR__ . '/includes/nav_bar.php'; ?>
      </div>
    </header>
    <div id="subpage-viewport">
      <div class="min-h-screen bg-stone-100/50 pt-28 pb-24 px-4 md:px-8">
        <div class="max-w-6xl mx-auto space-y-8">

          <!-- Header -->
          <div class="border-b border-stone-250 pb-5 flex items-center space-x-3">
            <div class="p-2 bg-stone-900 text-stone-50 rounded-xl shadow-lg">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
            </div>
            <div>
              <h1 class="font-display font-black text-2xl text-slate-900 tracking-tight uppercase">Wallet</h1>
              <p class="text-[10px] text-stone-500 font-mono uppercase tracking-widest mt-0.5">Manage your balance &amp; top up</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Balance Card -->
            <div class="md:col-span-1 bg-white rounded-2xl p-6 shadow-sm border border-stone-200/80">
              <div class="font-mono text-[10px] text-stone-500 uppercase tracking-widest font-bold">Current Balance</div>
              <div class="font-display font-black text-4xl mt-2 tracking-tight text-slate-900">$<?= formatted_money($balance) ?></div>
              <div class="font-mono text-[10px] text-stone-500 mt-1 uppercase">USD</div>
              <div class="mt-6 pt-4 border-t border-stone-200/80">
                <div class="font-mono text-[9px] text-stone-500 uppercase tracking-widest">Recent top-ups</div>
                <div class="font-mono text-lg font-bold mt-1 text-emerald-600">
                  $<?= formatted_money((float)dbGetValue("SELECT COALESCE(SUM(amount),0) FROM export_wallet_txn WHERE wallet_id = ? AND transaction_type='topup' AND status='completed'", [$walletId])) ?>
                </div>
              </div>
            </div>

            <!-- Top-up Card -->
            <div class="md:col-span-2 bg-white border border-stone-200/80 rounded-2xl p-6 shadow-sm">
              <h2 class="font-display font-black text-sm text-stone-900 uppercase tracking-wider mb-4">💳 Top Up with Visa Card</h2>
              <form method="post" id="topup-form">
                <input type="hidden" name="action" value="topup">
                <input type="hidden" name="amount" id="topup-amount" value="">

                <!-- Amount Quick Pick -->
                <div class="mb-4">
                  <div class="font-mono text-[10px] text-stone-400 uppercase tracking-widest font-bold mb-2">Select Amount</div>
                  <div class="flex flex-wrap gap-2">
                    <?php foreach ([100, 200, 500, 1000, 2500, 5000] as $opt): ?>
                    <button type="button" onclick="selectAmount(this, <?= $opt ?>)" class="amount-btn px-4 py-2 border border-stone-200 rounded-lg text-xs font-bold font-mono text-stone-600 hover:border-emerald-400 hover:text-emerald-600 transition-all cursor-pointer" style="background:transparent">$<?= number_format($opt) ?></button>
                    <?php endforeach; ?>
                  </div>
                </div>

                <!-- Card Number -->
                <div class="mb-4">
                  <div class="font-mono text-[10px] text-stone-400 uppercase tracking-widest font-bold mb-2">Card Number</div>
                  <div style="position:relative">
                    <input type="text" id="card-number" name="card_number" class="card-input" placeholder="xxxx xxxx xxxx xxxx" maxlength="19" autocomplete="off" inputmode="numeric" oninput="formatCard(this)">
                    <div id="card-icon" style="position:absolute;right:16px;top:50%;transform:translateY(-50%);opacity:0.3">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </div>
                  </div>
                  <div id="card-status" class="font-mono text-[10px] mt-1 h-4"></div>
                </div>

                <!-- Remember Card -->
                <div class="mb-5 flex items-center space-x-2">
                  <input type="checkbox" id="remember-card" name="remember_card" value="1" class="w-4 h-4 rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
                  <label for="remember-card" class="font-mono text-[10px] text-stone-500 uppercase tracking-wider font-bold cursor-pointer select-none">Remember this card for future top-ups</label>
                </div>

                <!-- Submit -->
                <button type="submit" id="topup-submit" disabled class="w-full py-3 bg-stone-900 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed hover:bg-stone-800">Top Up Now</button>
              </form>
            </div>
          </div>

          <!-- Saved Cards -->
          <?php if ($savedCards): ?>
          <div class="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-sm">
            <h2 class="font-display font-black text-sm text-stone-900 uppercase tracking-wider mb-4">💳 Saved Cards</h2>
            <div class="flex flex-wrap gap-3">
              <?php foreach ($savedCards as $sc):
                $fullNum = $sc['number'] ?? ('000000000000' . ($sc['last4'] ?? ''));
                $displayNum = '•••• •••• •••• ' . ($sc['last4'] ?? '');
              ?>
              <div class="flex items-center gap-3 px-4 py-3 bg-stone-50 border border-stone-200 rounded-xl cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/30 transition-all" onclick="fillCard('<?= e($fullNum) ?>')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <div style="flex:1">
                  <div class="font-mono text-xs font-bold text-stone-800"><?= e($displayNum) ?></div>
                  <div class="font-mono text-[8px] text-emerald-500 uppercase tracking-wider">Click to fill</div>
                </div>
                <form method="post" style="display:inline" onclick="event.stopPropagation()">
                  <input type="hidden" name="card_last4" value="<?= e($sc['last4'] ?? '') ?>">
                  <button type="submit" name="action" value="delete_card" class="text-stone-400 hover:text-red-500 transition-all p-1 cursor-pointer" title="Remove card" onclick="return confirm('Remove this card?')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  </button>
                </form>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Transactions -->
          <div class="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-sm">
            <h2 class="font-display font-black text-sm text-stone-900 uppercase tracking-wider mb-4">📋 Recent Transactions</h2>
            <?php if ($txns): ?>
            <div style="overflow-x:auto">
              <table class="w-full" style="border-collapse:collapse;font-size:12px">
                <thead>
                  <tr class="font-mono text-[9px] text-stone-400 uppercase tracking-widest border-b border-stone-200">
                    <th style="text-align:left;padding:8px 10px;font-weight:700">Date</th>
                    <th style="text-align:left;padding:8px 10px;font-weight:700">Type</th>
                    <th style="text-align:right;padding:8px 10px;font-weight:700">Amount</th>
                    <th style="text-align:right;padding:8px 10px;font-weight:700">Balance</th>
                    <th style="text-align:left;padding:8px 10px;font-weight:700">Description</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($txns as $t): ?>
                  <tr class="border-b border-stone-100 hover:bg-stone-50/50 transition-all">
                    <td style="padding:10px;color:#64748b;font-family:monospace;font-size:10px;white-space:nowrap"><?= e($t['created_at']) ?></td>
                    <td style="padding:10px;text-transform:capitalize">
                      <span class="font-mono text-[10px] font-bold uppercase tracking-wider <?= $t['transaction_type']==='topup'?'text-emerald-600':'text-red-600' ?>"><?= e($t['transaction_type']) ?></span>
                    </td>
                    <td style="padding:10px;text-align:right;font-weight:700;font-family:monospace;font-size:12px" class="<?= $t['transaction_type']==='topup'||$t['transaction_type']==='refund'?'text-emerald-600':'text-red-600' ?>">
                      <?= ($t['transaction_type']==='topup'||$t['transaction_type']==='refund'?'+':'-') ?>$<?= formatted_money((float)$t['amount']) ?>
                    </td>
                    <td style="padding:10px;text-align:right;font-family:monospace;font-size:11px;color:#475569">$<?= formatted_money((float)$t['balance_after']) ?></td>
                    <td style="padding:10px;color:#94a3b8;font-size:11px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($t['description'] ?: '-') ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
            <p class="text-stone-400 font-mono text-xs py-6 text-center">No transactions yet.</p>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script>
// ── Mobile hamburger menu ──
document.addEventListener('DOMContentLoaded', function() {
  var ham = document.getElementById('mobile-menu-hamburger');
  var menu = document.getElementById('desktop-nav-menu');
  if (ham && menu) {
    var open = false;
    ham.addEventListener('click', function() {
      open = !open;
      menu.style.display = open ? 'flex' : '';
      menu.style.flexDirection = open ? 'column' : '';
      menu.style.position = open ? 'absolute' : '';
      menu.style.top = open ? '60px' : '';
      menu.style.left = open ? '0' : '';
      menu.style.width = open ? '100%' : '';
      menu.style.background = open ? '#fafaf9' : '';
      menu.style.padding = open ? '20px' : '';
      menu.style.gap = open ? '16px' : '';
    });
  }
});

// ── Wallet functions ──
function fillCard(fullNum) {
  var inp = document.getElementById('card-number');
  var v = fullNum.replace(/[^0-9]/g, '').slice(0, 16);
  var fmt = '';
  for (var i = 0; i < v.length; i++) {
    if (i > 0 && i % 4 === 0) fmt += ' ';
    fmt += v[i];
  }
  inp.value = fmt;
  document.getElementById('card-status').innerHTML = '<span style="color:#10b981">✓ Using saved card (•••• ' + v.slice(-4) + ')</span>';
  validateForm();
  inp.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function selectAmount(btn, amt) {
  document.querySelectorAll('.amount-btn').forEach(b => {
    b.style.background = 'transparent';
    b.style.borderColor = '#d6d2cb';
    b.style.color = '#78716c';
  });
  btn.style.background = '#ecfdf5';
  btn.style.borderColor = '#0d9488';
  btn.style.color = '#0d9488';
  document.getElementById('topup-amount').value = amt;
  validateForm();
}

function formatCard(inp) {
  var v = inp.value.replace(/[^0-9]/g, '').slice(0, 16);
  var fmt = '';
  for (var i = 0; i < v.length; i++) {
    if (i > 0 && i % 4 === 0) fmt += ' ';
    fmt += v[i];
  }
  inp.value = fmt;
  validateCard(v);
  validateForm();
}

function validateCard(num) {
  var status = document.getElementById('card-status');
  if (num.length === 0) { status.textContent = ''; return; }
  if (num.length < 16) { status.innerHTML = '<span style="color:#f59e0b">' + num.length + '/16 digits</span>'; return; }
  status.innerHTML = '<span style="color:#10b981">✓ Valid card number</span>';
}

function validateForm() {
  var amt = document.getElementById('topup-amount').value;
  var cn = document.getElementById('card-number').value.replace(/[^0-9]/g, '');
  var btn = document.getElementById('topup-submit');
  btn.disabled = !(parseFloat(amt) > 0 && cn.length === 16);
}
</script>
<?php if ($msg): ?>
<div class="toast toast-<?= $msgType ?>" onclick="this.style.animation='toastOut .3s ease forwards';setTimeout(function(){this.style.display='none'}.bind(this),300)"><?= e($msg) ?></div>
<script>(function(){var t=document.querySelector('.toast');if(t){setTimeout(function(){t.style.animation='toastOut .3s ease forwards';setTimeout(function(){t.style.display='none'},300)},3000)}})();</script>
<?php endif; ?>
<script src="js/app.js?v=<?= urlencode($__ifmAssetVersion('js/app.js')) ?>"></script>
</body>
</html>