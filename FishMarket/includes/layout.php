<?php
require_once APP_ROOT . '/includes/bg_shared.php';
$noSharedBg = $noSharedBg ?? false;
$title = $title ?? 'Fish Market';
$extraHead = $extraHead ?? '';
$extraScripts = $extraScripts ?? '';
ob_start();
require APP_ROOT . '/pages/' . $view . '.php';
$content = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0e1a;
            --surface: rgba(255,255,255,0.05);
            --surface-2: rgba(255,255,255,0.10);
            --text: #eef2ff;
            --muted: rgba(238,242,255,0.65);
            --border: rgba(255,255,255,0.10);
            --brand: #2dd4bf;
            --brand-dark: #0d9488;
            --brand-light: #5eead4;
            --amber: #f59e0b;
            --amber-light: #fbbf24;
            --coral: #fb7185;
            --radius: 16px;
            --radius-sm: 8px;
            --font-display: 'Playfair Display', serif;
            --font-body: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: var(--font-body);
            background:
                radial-gradient(ellipse 1200px 700px at 10% 15%, rgba(45,212,191,0.12), transparent 60%),
                radial-gradient(ellipse 900px 600px at 92% 18%, rgba(245,158,11,0.08), transparent 55%),
                radial-gradient(ellipse 800px 700px at 50% 110%, rgba(45,212,191,0.06), transparent 60%),
                var(--bg);
            background-attachment: fixed;
            color: var(--text);
        }
        .navbar {
            position: sticky; top: 0; z-index: 100; backdrop-filter: blur(14px);
            background: rgba(10,14,26,0.75); border-bottom: 1px solid var(--border);
            padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; height: 64px;
        }
        .navbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .brand-icon {
            width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            display: flex; align-items: center; justify-content: center; font-size: 18px;
        }
        .brand-logo-img {
            width: 40px; height: 40px; display: block; flex-shrink: 0;
            filter: drop-shadow(0 10px 24px rgba(45,212,191,0.22));
        }
        .brand-text { font-family: var(--font-display); font-size: 1.25rem; color: white; line-height: 1; }
        .brand-sub { font-size: 0.55rem; color: var(--brand-light); font-weight: 500; letter-spacing: 2px; text-transform: uppercase; display: block; opacity: 0.8; }
        .navbar-nav { display: flex; align-items: center; gap: 2px; list-style: none; }
        .navbar-nav a { color: var(--muted); text-decoration: none; padding: 0.45rem 0.85rem; border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .navbar-nav a:hover { color: var(--text); background: var(--surface); }
        .navbar-nav a.active { color: var(--brand); }
        .nav-profile-section { margin-left: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .cart-badge { background: var(--amber); color: #000; font-size: 0.6rem; font-weight: 800; padding: 2px 6px; border-radius: 20px; min-width: 18px; text-align: center; }
        .nav-user-chip {
            display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text) !important; background: var(--surface) !important;
            border: 1px solid var(--border) !important; border-radius: 999px !important; padding: 0.3rem 0.75rem !important;
            font-size: 0.78rem !important; font-weight: 600 !important;
        }
        .nav-user-chip .wallet { color: var(--amber-light); font-weight: 700; }
        .nav-user-chip .divider { color: rgba(255,255,255,0.25); }
        .btn-nav-login {
            background: linear-gradient(135deg, var(--brand), var(--brand-dark)) !important; color: #081225 !important; font-weight: 700 !important;
            padding: 0.45rem 1.1rem !important; border-radius: var(--radius-sm) !important;
        }
        .btn-nav-logout {
            background: rgba(255,255,255,0.08) !important; color: rgba(255,255,255,0.65) !important; font-weight: 600 !important;
            padding: 0.45rem 1rem !important; border-radius: var(--radius-sm) !important; border: 1px solid rgba(255,255,255,0.1) !important;
            cursor: pointer !important; font-size: 0.78rem !important; transition: all 0.2s !important; letter-spacing: 0.02em !important;
        }
        .btn-nav-logout:hover { background: rgba(251,113,133,0.15) !important; color: #fca5a5 !important; border-color: rgba(251,113,133,0.3) !important; }
        .messages-wrapper { position: fixed; top: 72px; right: 1.5rem; z-index: 200; display: flex; flex-direction: column; gap: 0.5rem; max-width: 340px; }
        .alert { padding: 0.85rem 1rem; border-radius: var(--radius-sm); font-size: 0.85rem; display: flex; align-items: center; gap: 0.6rem; backdrop-filter: blur(12px); }
        .alert-success { background: rgba(45,212,191,0.15); border: 1px solid rgba(45,212,191,0.3); color: var(--brand-light); }
        .alert-info { background: rgba(96,165,250,0.15); border: 1px solid rgba(96,165,250,0.3); color: #93c5fd; }
        .alert-warning { background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); color: var(--amber-light); }
        .alert-error { background: rgba(251,113,133,0.15); border: 1px solid rgba(251,113,133,0.3); color: var(--coral); }
        .alert-close { margin-left: auto; cursor: pointer; opacity: 0.6; border: none; background: none; font-size: 1rem; color: inherit; }
        main { min-height: calc(100vh - 64px - 200px); }
        footer { background: rgba(10,14,26,0.8); border-top: 1px solid var(--border); padding: 2.5rem 2rem 1.5rem; margin-top: 4rem; }
        .footer-grid { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 2rem; padding-bottom: 2rem; border-bottom: 1px solid var(--border); }
        .footer-brand p { margin-top: 0.75rem; font-size: 0.85rem; line-height: 1.6; color: var(--muted); }
        .footer-col h4 { font-size: 0.8rem; font-weight: 700; margin-bottom: 1rem; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); }
        .footer-col a { display: block; color: rgba(238,242,255,0.5); text-decoration: none; font-size: 0.85rem; padding: 3px 0; }
        .footer-col a:hover { color: var(--brand); }
        .footer-bottom { max-width: 1200px; margin: 1.25rem auto 0; text-align: center; font-size: 0.8rem; color: var(--muted); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }
        .section { padding: 3rem 0; }
        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.3rem; border-radius: var(--radius-sm); font-size: 0.875rem; font-weight: 600; cursor: pointer; border: 1px solid transparent; text-decoration: none; transition: all .15s; }
        .btn-primary { background: linear-gradient(135deg, var(--brand), var(--brand-dark)); color: #081225; }
        .btn-amber { background: linear-gradient(135deg, var(--amber), #d97706); color: #000; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-ghost { background: transparent; color: var(--muted); border-color: transparent; }
        .btn-sm { padding: 0.35rem 0.85rem; font-size: 0.8rem; }
        .btn-lg { padding: 0.75rem 1.8rem; font-size: 0.95rem; }
        .btn-block { width: 100%; justify-content: center; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-success { background: rgba(45,212,191,0.15); color: var(--brand); border: 1px solid rgba(45,212,191,0.2); }
        .badge-warning { background: rgba(245,158,11,0.15); color: var(--amber); border: 1px solid rgba(245,158,11,0.2); }
        .badge-info { background: rgba(96,165,250,0.15); color: #93c5fd; border: 1px solid rgba(96,165,250,0.2); }
        .badge-primary { background: rgba(45,212,191,0.1); color: var(--brand); border: 1px solid rgba(45,212,191,0.15); }
        .badge-danger { background: rgba(251,113,133,0.15); color: var(--coral); border: 1px solid rgba(251,113,133,0.2); }
        .product-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden;
            text-decoration: none; color: inherit; display: block; position: relative; backdrop-filter: blur(8px);
        }
        .product-card:hover { transform: translateY(-4px); border-color: rgba(45,212,191,0.3); box-shadow: 0 16px 40px rgba(0,0,0,0.3); }
        .product-card-img { width: 100%; aspect-ratio: 4/3; object-fit: cover; background: rgba(255,255,255,0.04); display: flex; align-items: center; justify-content: center; font-size: 3.5rem; }
        .product-card-body { padding: 1rem 1.1rem; }
        .product-card-cat { font-size: 0.7rem; color: var(--brand); font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.3rem; }
        .product-card-name { font-weight: 700; font-size: 1rem; margin-bottom: 0.4rem; line-height: 1.3; color: var(--text); }
        .product-card-price { font-size: 1.15rem; font-weight: 800; color: var(--amber-light); }
        .product-card-unit { font-size: 0.75rem; color: var(--muted); font-weight: 400; }
        .product-card-stock { font-size: 0.75rem; color: var(--brand); font-weight: 500; margin-top: 0.25rem; }
        .product-card-stock.low { color: var(--amber); }
        .product-card-stock.out { color: var(--coral); }
        .featured-badge { position: absolute; top: 10px; left: 10px; background: rgba(245,158,11,0.9); color: #000; font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; }
        .form-group { margin-bottom: 1.1rem; }
        .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--muted); margin-bottom: 0.4rem; }
        .form-control {
            width: 100%; padding: 0.65rem 0.9rem; border: 1px solid var(--border); border-radius: var(--radius-sm);
            font-size: 0.9rem; color: var(--text); background: rgba(10,14,26,0.4);
        }
        .page-header {
            background: linear-gradient(135deg, rgba(10,14,26,0.9), rgba(13,148,136,0.2));
            border-bottom: 1px solid var(--border); color: white; padding: 1.2rem 0; margin-bottom: 1.5rem;
        }
        .page-header h1 { font-family: var(--font-display); font-size: 1.4rem; margin:0; }
        .page-header p { color: var(--muted); margin-top: 0.25rem; font-size:0.85rem; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: rgba(10,14,26,0.5); }
        thead th { padding: 0.9rem 1.2rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; text-align: left; color: var(--muted); border-bottom: 1px solid var(--border); }
        tbody tr { border-bottom: 1px solid var(--border); }
        tbody td { padding: 0.9rem 1.2rem; font-size: 0.85rem; color: var(--text); }
        .nav-clock { display: flex; align-items: center; gap: 6px; padding: 0.3rem 0.65rem; background: var(--surface); border: 1px solid var(--border); border-radius: 999px; font-size: 0.72rem; font-weight: 600; color: #4fc3f7; margin-right: 4px; }
        /* AI chatbot temporarily disabled
        .ai-chatbot-btn { ... }
        .ai-chatbox { ... }
        */
        @media (max-width: 768px) {
            .navbar { padding: 0 1rem; height: auto; min-height: 64px; flex-wrap: wrap; gap: 0.75rem; }
            .navbar-nav { flex-wrap: wrap; justify-content: flex-end; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
    <?= $extraHead ?>
</head>
<body>
    <?php if (!$noSharedBg) echo $bgSharedHTML; ?>
    <?php if (!$noSharedBg): ?><div class="page-wrapper"><?php endif; ?>
    <nav class="navbar">
        <a class="navbar-brand" href="<?= url_for('home') ?>">
            <img class="brand-logo-img" src="<?= e(asset_url('assets/fish-market-logo.svg')) ?>" alt="Fish Market logo">
            <div>
                <div class="brand-text">Fish Market</div>
                <span class="brand-sub">Port-Connected Commerce</span>
            </div>
        </a>
        <ul class="navbar-nav">
            <li class="nav-clock"><span>&#128336;</span><span id="clockDate"></span><span>|</span><span id="clockTime"></span></li>
            <li><a class="<?= $page === 'home' ? 'active' : '' ?>" href="<?= url_for('home') ?>">Home</a></li>
            <li><a class="<?= $page === 'shop' ? 'active' : '' ?>" href="<?= url_for('shop') ?>">Shop</a></li>
            <li><a class="<?= $page === 'cart' ? 'active' : '' ?>" href="<?= url_for('cart') ?>">Cart<?php if (cart_count() > 0): ?><span class="cart-badge"><?= cart_count() ?></span><?php endif; ?></a></li>
<?php if (isFmLoggedIn()): ?>
            <li><a class="<?= $page === 'orders' ? 'active' : '' ?>" href="<?= url_for('orders') ?>">Orders</a></li>
<?php if (isFmAdmin()): ?>
            <li><a class="<?= in_array($page, ['dashboard', 'dashboard_products', 'dashboard_orders', 'dashboard_users'], true) ? 'active' : '' ?>" href="<?= url_for('dashboard') ?>">Dashboard</a></li>
<?php endif; ?>
            <li class="nav-profile-section"><a class="nav-user-chip" href="<?= url_for('profile') ?>"><span class="wallet">RM <?= e(formatted_money((float) $profile['wallet_balance'])) ?></span><span class="divider">|</span><span><?= e($profile['full_name']) ?></span></a></li>
            <li><form method="post" action="<?= url_for('home') ?>" style="display:inline"><input type="hidden" name="action" value="logout"><button type="submit" class="btn-nav-logout">Logout</button></form></li>
<?php else: ?>
            <li style="margin-left: 1rem;"><a class="btn-nav-login" href="<?= url_for('login') ?>">Login</a></li>
<?php endif; ?>
        </ul>
    </nav>

    <?php if ($flashes): ?>
        <div class="messages-wrapper" id="msgWrapper">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>">
                    <span><?= e($flash['message']) ?></span>
                    <button class="alert-close" onclick="this.parentElement.remove()">Ã—</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <main><?= $content ?></main>

    <footer>
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="navbar-brand" href="<?= url_for('home') ?>" style="display:inline-flex">
                    <img class="brand-logo-img" src="<?= e(asset_url('assets/fish-market-logo.svg')) ?>" alt="Fish Market logo">
                    <div>
                        <div class="brand-text">Fish Market</div>
                        <span class="brand-sub">Port-Connected Commerce</span>
                    </div>
                </a>
                <p>Fishing boats land catch at the port, inventory is recorded there, and Fish Market shows the live available stock for customer orders.</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <a href="<?= url_for('home') ?>">Home</a>
                <a href="<?= url_for('shop') ?>">Shop</a>
                <a href="<?= url_for('orders') ?>">My Orders</a>
                <a href="<?= url_for('profile') ?>">Profile</a>
            </div>
            <div class="footer-col">
                <h4>Categories</h4>
                <?php foreach (array_slice(categories(), 0, 4) as $category): ?>
                    <a href="<?= url_for('shop', ['category' => $category['slug']]) ?>"><?= e($category['name']) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="footer-col">
                <h4>Contact Us</h4>
                <a href="#">Melaka, Malaysia</a>
                <a href="#">+60 12-345 6789</a>
                <a href="#">info@fishmarket.my</a>
            </div>
        </div>
        <div class="footer-bottom"><p>&copy; 2024 Fish Market. All rights reserved.</p></div>
    </footer>

    <?php /* AI chatbot temporarily disabled
    <button id="aiChatbotBtn" class="ai-chatbot-btn" type="button" title="AI Chat">&#129302;</button>
    <div id="aiChatbox" class="ai-chatbox" aria-live="polite">
        <div class="ai-chatbox-header">
            <span>AI Assistant</span>
            <button id="aiChatClose" class="alert-close" type="button">Ã—</button>
        </div>
        <div id="aiChatMessages" class="ai-chatbox-messages">
            <div class="ai-msg ai-msg-bot">UI preserved. Connect your own Supabase or API endpoint later for live assistant replies.</div>
        </div>
        <form id="aiChatForm" class="ai-chatbox-input">
            <input id="aiChatInput" type="text" placeholder="Ask a question..." autocomplete="off" />
            <button id="aiChatSend" type="submit">Send</button>
        </form>
    </div>
    */ ?>

    <script>
        <?php /* AI chatbot JS temporarily disabled
        (function () {
            const btn = document.getElementById('aiChatbotBtn');
            const box = document.getElementById('aiChatbox');
            const close = document.getElementById('aiChatClose');
            const form = document.getElementById('aiChatForm');
            const input = document.getElementById('aiChatInput');
            const messages = document.getElementById('aiChatMessages');
            btn.addEventListener('click', () => box.classList.toggle('show'));
            close.addEventListener('click', () => box.classList.remove('show'));
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                const text = input.value.trim();
                if (!text) return;
                const user = document.createElement('div');
                user.className = 'ai-msg ai-msg-user';
                user.textContent = text;
                messages.appendChild(user);
                const reply = document.createElement('div');
                reply.className = 'ai-msg ai-msg-bot';
                reply.textContent = 'This PHP build keeps the original interface. Replace this stub with your own API call later.';
                messages.appendChild(reply);
                input.value = '';
                messages.scrollTop = messages.scrollHeight;
            });
        })();
        */ ?>
        (function () {
            function updateClock() {
                const now = new Date();
                const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                const h = now.getHours() % 12 || 12;
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');
                document.getElementById('clockDate').textContent = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()];
                document.getElementById('clockTime').textContent = h + ':' + m + ':' + s + ' ' + (now.getHours() >= 12 ? 'PM' : 'AM');
            }
            updateClock();
            setInterval(updateClock, 1000);
        })();
    </script>
    <?= $extraScripts ?>
    <?php if (!$noSharedBg): ?></div><?php endif; ?>
</body>
</html>

