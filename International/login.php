<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
require_once __DIR__ . '/includes/bootstrap.php';

$currentUser = intl_current_user();
$currentBalance = $currentUser ? intl_wallet_balance() : 0;
$currentDisplayName = $currentUser['full_name'] ?? $currentUser['company_name'] ?? $currentUser['username'] ?? 'User';

$error = '';
$success = '';
$userType = trim($_POST['user_type'] ?? 'sourcing');
$showRegisterTab = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $userType = trim($_POST['user_type'] ?? 'sourcing');

    if ($_POST['form_action'] === 'login') {
        if ($email && $password) {
            if ($userType === 'restaurant') {
                $user = dbGetRow("SELECT * FROM export_restaurant_user WHERE email = ? AND is_active = TRUE", [$email]);
            } else {
                $user = dbGetRow("SELECT * FROM export_user WHERE (email = ? OR username = ?) AND is_active = TRUE", [$email, $email]);
            }
            if ($user && password_verify($password, $user['password_hash'])) {
                if ($userType === 'restaurant') {
                    intl_restaurant_login((int)$user['id']);
                    dbExecute("UPDATE export_restaurant_user SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
                    set_flash('welcome', $user['company_name'] ?? 'Restaurant');
                } else {
                    intl_login((int)$user['id']);
                    dbExecute("UPDATE export_user SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
                    set_flash('welcome', $user['full_name'] ?? $user['username'] ?? 'User');
                }
                header('Location: ' . url_for('index'));
                exit;
            }
            $error = 'Invalid email or password.';
        } else {
            $error = 'Please fill in all fields.';
        }
    } elseif ($_POST['form_action'] === 'register') {
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        if ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
            $showRegisterTab = true;
        } elseif ($userType === 'restaurant') {
            $companyName = trim($_POST['company_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $countryCode = trim($_POST['country_code'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $regNo = trim($_POST['reg_no'] ?? '');
            if ($email && $password && $companyName && $regNo) {
                $existing = dbGetRow("SELECT id FROM export_restaurant_user WHERE email = ?", [$email]);
                if ($existing) {
                    $error = 'Email already registered.';
                    $showRegisterTab = true;
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    dbExecute("INSERT INTO export_restaurant_user (company_name, email, phone, country_code, address, reg_no, password_hash, discount_percent, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 5, NOW(), NOW())",
                        [$companyName, $email, $phone, $countryCode, $address, $regNo, $hash]);
                    $newId = (int)dbLastInsertId();
                    intl_restaurant_login($newId);
                    $success = 'registered_ok';
                }
            } else {
                $error = 'Please fill in all required fields.';
                $showRegisterTab = true;
            }
        } else {
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $countryCode = trim($_POST['country_code'] ?? '');
            $address = trim($_POST['address'] ?? '');
            if ($email && $password && $fullName && $username) {
                $existing = dbGetRow("SELECT id FROM export_user WHERE email = ? OR username = ?", [$email, $username]);
                if ($existing) {
                    $error = 'Email or username already registered.';
                    $showRegisterTab = true;
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    dbExecute("INSERT INTO export_user (username, email, password_hash, full_name, phone, country_code, address, account_status, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1, NOW(), NOW())",
                        [$username, $email, $hash, $fullName, $phone, $countryCode, $address]);
                    $newId = (int)dbLastInsertId();
                    intl_login($newId);
                    $success = 'registered_ok';
                }
            } else {
                $error = 'Please fill in all required fields.';
                $showRegisterTab = true;
            }
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/><title>International Fish Market Login</title><link rel="stylesheet" href="css/login.css?v=<?= urlencode(__ifmAssetVersion('css/login.css')) ?>"/><style>
#hero-steps{position:absolute;bottom:40px;left:50%;transform:translateX(-50%);z-index:10;width:100%;max-width:280px;opacity:0;transition:opacity 0.6s;background:rgba(0,0,0,0.55);backdrop-filter:blur(8px);border-radius:20px;padding:20px 24px;border:1px solid rgba(255,255,255,0.1)}
#hero-steps.visible{opacity:1}
.step-row{display:flex;align-items:center;gap:14px;margin-bottom:16px;transition:all 0.5s}
.step-row:last-child{margin-bottom:0}
.step-circle{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0;transition:all 0.5s}
.step-circle.active{background:#fff;color:#000;box-shadow:0 0 20px rgba(255,255,255,0.4);transform:scale(1.1)}
.step-circle.inactive{background:rgba(255,255,255,0.12);color:rgba(255,255,255,0.5);border:1px solid rgba(255,255,255,0.08)}
.step-circle.done{background:#fff;color:#000;box-shadow:0 0 20px rgba(255,255,255,0.3)}
.step-label{transition:all 0.5s}
.step-label .title{font-size:13px;font-weight:700;transition:color 0.5s;line-height:1.3}
.step-label .desc{font-size:11px;transition:color 0.5s;margin-top:2px}
.step-label.active .title{color:#fff;text-shadow:0 0 12px rgba(255,255,255,0.3)}
.step-label.active .desc{color:rgba(255,255,255,0.7)}
.step-label.inactive .title{color:rgba(255,255,255,0.45)}
.step-label.inactive .desc{color:rgba(255,255,255,0.25)}
@keyframes countdownBar{from{width:100%}to{width:0%}}
.pwd-wrapper{position:relative}
.pwd-wrapper input{padding-right:38px}
.pwd-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:6px;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#94a3b8;transition:all 0.2s}
.pwd-toggle:hover{color:#0369a1;background:rgba(3,105,161,0.08)}
</style></head><body style="margin:0;overflow-x:hidden"><div id="root"><div class="relative min-h-screen bg-bg-dark text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip"><header class="fixed top-0 left-0 w-full z-50 transition-all duration-300 bg-transparent py-5"><div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center"><div class="flex items-center space-x-2"><a href="<?= url_for('index') ?>" style="text-decoration:none;display:flex;align-items:center;gap:8px"><span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">INTERNATIONAL FISH MARKET</span><span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse"></span></a></div><nav class="hidden md:flex items-center space-x-6">
<a href="<?= url_for('index') ?>" style="display:flex;align-items:center;gap:4px;border:1px solid #e2e8f0;border-radius:8px;padding:4px 10px;text-decoration:none;transition:all 0.2s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#94a3b8"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
  <span style="font-weight:600;color:#475569;letter-spacing:0.5px;text-transform:uppercase;font-size:9px">Back</span>
</a>
</nav></div></header>
<div class="fixed inset-0 bg-slate-100/50 pt-20 pb-4 px-2 sm:px-4 md:px-6 flex items-center justify-center overflow-hidden z-40"><main class="w-full h-full max-h-[690px] max-w-7xl mx-auto flex bg-slate-50 border border-slate-200/80 rounded-[32px] overflow-hidden shadow-2xl p-2 md:p-3"><div class="hidden lg:flex relative w-[48%] flex-col justify-between p-12 rounded-3xl overflow-hidden shadow-2xl bg-slate-950">
<video autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover z-0"><source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260520_111942_8fc50f9e-4dfd-45c1-81bb-d93342a23d87.mp4" type="video/mp4"/></video>
<div id="hero-steps">
  <div class="step-row" id="step-1-row">
    <div class="step-circle active" id="step-1-circle">1</div>
    <div class="step-label active" id="step-1-label">
      <div class="title">Register</div>
      <div class="desc">Register form</div>
    </div>
  </div>
  <div class="step-row" id="step-2-row" style="opacity:0.4">
    <div class="step-circle inactive" id="step-2-circle">2</div>
    <div class="step-label inactive" id="step-2-label">
      <div class="title">Form Complete</div>
      <div class="desc">All fields filled correctly</div>
    </div>
  </div>
</div>
</div>
<div class="flex-1 flex flex-col items-center justify-start lg:justify-center py-2 px-3 sm:px-5 md:px-6 lg:px-8 overflow-y-auto bg-white rounded-3xl h-full"><div class="w-full max-w-sm">
<?php if ($error && $success !== 'registered_ok'): ?><div id="error-msg" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs font-mono cursor-pointer" onclick="this.style.display='none'"><?= e($error) ?></div><?php endif; ?>
<?php if ($success === 'registered_ok'): ?>
<div style="text-align:center;padding:40px 0">
  <div style="width:64px;height:64px;border-radius:50%;background:#0d9488;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:28px;color:#fff;box-shadow:0 0 40px rgba(13,148,136,0.3)">✓</div>
  <h2 class="font-display font-black text-xl text-[#0d9488]" style="margin-bottom:8px">Welcome Aboard!</h2>
  <p style="color:#64748b;font-size:14px">Your profile has been created. Redirecting in <strong id="redirect-countdown" style="color:#0d9488">3</strong>...</p>
</div>
<?php else: ?>
<div class="text-center mb-2"><h2 class="font-display font-black text-lg text-slate-900 tracking-tight uppercase" id="form-title">Sign In</h2><p class="text-slate-500 text-[10px] mt-0.5" id="form-subtitle">Access your secure space to manage active seafood bookings.</p></div>
<div class="grid grid-cols-2 p-0.5 bg-slate-100 border border-slate-200 rounded-xl mb-2">
  <button type="button" class="tab-btn py-1.5 text-[10px] font-mono uppercase tracking-wider font-bold rounded-lg transition-all cursor-pointer z-[2] relative bg-white text-brand-blue shadow-sm" data-tab="login">Sign In</button>
  <button type="button" class="tab-btn py-1.5 text-[10px] font-mono uppercase tracking-wider font-bold rounded-lg transition-all cursor-pointer z-[2] relative text-slate-500 hover:text-slate-900" data-tab="register">Register</button>
</div>
<form method="post" id="auth-form" novalidate><input type="hidden" name="form_action" id="form_action" value="login"><input type="hidden" name="user_type" id="user_type_input" value="sourcing">
<div id="login-fields" class="space-y-2">
  <div class="grid grid-cols-2 p-0.5 bg-slate-100 border border-slate-200 rounded-lg">
    <button type="button" class="sign-role-btn py-1 text-[9px] font-mono uppercase tracking-wider font-bold rounded-md transition-all cursor-pointer bg-white text-brand-blue shadow-sm" data-role="sourcing">User</button>
    <button type="button" class="sign-role-btn py-1 text-[9px] font-mono uppercase tracking-wider font-bold rounded-md transition-all cursor-pointer text-slate-500 hover:text-slate-900" data-role="restaurant">Restaurant</button>
  </div>
  <div><label class="font-mono text-[8px] uppercase tracking-wider text-slate-400 font-bold block mb-0.5">Email Address</label><input name="email" placeholder="you@example.com" required class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-9 px-3 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[11px]" type="email"></div>
  <div><label class="font-mono text-[8px] uppercase tracking-wider text-slate-400 font-bold block mb-0.5">Password</label><div class="pwd-wrapper"><input name="password" placeholder="••••••••••••" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-9 px-3 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[11px]" required type="password"><button type="button" class="pwd-toggle" onclick="togglePwd(this)" tabindex="-1"><svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button></div></div>
</div>
<div id="register-fields" style="display:none" class="space-y-2">
  <div class="text-center"><p class="font-mono text-[8px] uppercase tracking-wider text-slate-400 font-bold mb-1">I want to register as</p>
  <div class="grid grid-cols-2 gap-2">
    <button type="button" class="reg-role-btn py-1.5 text-[10px] font-mono uppercase tracking-wider font-bold rounded-lg transition-all cursor-pointer bg-white text-brand-blue shadow-sm border-2 border-brand-blue" data-role="sourcing">User</button>
    <button type="button" class="reg-role-btn py-1.5 text-[10px] font-mono uppercase tracking-wider font-bold rounded-lg transition-all cursor-pointer text-slate-500 hover:text-slate-900 border-2 border-slate-200 hover:border-slate-300" data-role="restaurant">Restaurant</button>
  </div></div>
  <!-- User -->
  <div id="sourcing-fields" class="space-y-1.5">
    <div class="grid grid-cols-2 gap-1.5">
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Full Name</label><input name="full_name" placeholder="John Doe" id="full-name-input" required class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]"></div>
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Username</label><input name="username" placeholder="johndoe" id="username-input" required class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]"></div>
    </div>
    <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Email</label><input name="email" placeholder="you@example.com" required class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]" type="email"></div>
    <div class="grid grid-cols-2 gap-1.5">
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Password</label><div class="pwd-wrapper"><input name="password" placeholder="Min 8 chars" id="reg-pwd" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]" required type="password"><button type="button" class="pwd-toggle" onclick="togglePwd(this)" tabindex="-1" style="right:4px;padding:4px"><svg class="eye-open" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-closed" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button></div></div>
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Confirm</label><div class="pwd-wrapper"><input name="confirm_password" placeholder="Repeat" id="reg-confirm-pwd" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]" required type="password"><button type="button" class="pwd-toggle" onclick="togglePwd(this)" tabindex="-1" style="right:4px;padding:4px"><svg class="eye-open" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-closed" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button></div></div>
    </div>
    <div id="pwd-match-msg" class="text-[9px] font-mono hidden"></div>
    <div class="grid grid-cols-2 gap-1.5">
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Phone</label><input name="phone" placeholder="+60 12 345 6789" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]"></div>
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Country</label>
        <select name="country_code" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2 text-slate-800 focus:outline-none transition-all font-mono text-[10px]">
          <option value="MY">Malaysia</option>
          <option value="SG">Singapore</option>
          <option value="JP">Japan</option>
          <option value="CN">China</option>
          <option value="US">United States</option>
          <option value="GB">United Kingdom</option>
          <option value="AU">Australia</option>
          <option value="KR">South Korea</option>
          <option value="TH">Thailand</option>
          <option value="VN">Vietnam</option>
        </select>
      </div>
    </div>
    <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Address</label><input name="address" placeholder="123 Shipping Street" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]"></div>
  </div>
  <!-- Restaurant -->
  <div id="restaurant-fields" style="display:none" class="space-y-1.5">
    <div class="grid grid-cols-2 gap-1.5">
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Company</label><input name="company_name" placeholder="Your Restaurant Inc." id="company-input" required class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]"></div>
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Reg No. (6)</label><input name="reg_no" placeholder="123456" maxlength="6" required class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]"></div>
    </div>
    <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Email</label><input name="email" placeholder="restaurant@example.com" required class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]" type="email"></div>
    <div class="grid grid-cols-2 gap-1.5">
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Password</label><div class="pwd-wrapper"><input name="password" placeholder="Min 8 chars" id="rest-pwd" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]" required type="password"><button type="button" class="pwd-toggle" onclick="togglePwd(this)" tabindex="-1" style="right:4px;padding:4px"><svg class="eye-open" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-closed" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button></div></div>
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Confirm</label><div class="pwd-wrapper"><input name="confirm_password" placeholder="Repeat" id="rest-confirm-pwd" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]" required type="password"><button type="button" class="pwd-toggle" onclick="togglePwd(this)" tabindex="-1" style="right:4px;padding:4px"><svg class="eye-open" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-closed" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button></div></div>
    </div>
    <div id="rest-pwd-match-msg" class="text-[9px] font-mono hidden"></div>
    <div class="grid grid-cols-2 gap-1.5">
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Phone</label><input name="phone" placeholder="+60 12 345 6789" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]"></div>
      <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Country</label>
        <select name="country_code" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2 text-slate-800 focus:outline-none transition-all font-mono text-[10px]">
          <option value="MY">Malaysia</option>
          <option value="SG">Singapore</option>
          <option value="JP">Japan</option>
          <option value="CN">China</option>
          <option value="US">United States</option>
          <option value="GB">United Kingdom</option>
          <option value="AU">Australia</option>
          <option value="KR">South Korea</option>
          <option value="TH">Thailand</option>
          <option value="VN">Vietnam</option>
        </select>
      </div>
    </div>
    <div><label class="font-mono text-[7px] uppercase tracking-wider text-slate-400 font-bold block">Address</label><input name="address" placeholder="123 Restaurant Street" class="w-full bg-slate-50 border border-slate-200 focus:border-[#0369a1] focus:ring-4 focus:ring-[#0369a1]/10 rounded-lg h-8 px-2.5 text-slate-800 placeholder:text-slate-400 focus:outline-none transition-all font-mono text-[10px]"></div>
  </div>
  <div class="text-[8px] text-slate-400 text-center">Min 8 characters for password</div>
</div>
<button type="submit" class="w-full h-9 bg-[#0369a1] hover:bg-[#0284c7] active:scale-[0.98] text-white text-[10px] font-display uppercase tracking-widest font-black rounded-lg transition-all shadow-md shadow-sky-900/10 cursor-pointer flex items-center justify-center gap-2 mt-1"><span id="submit-text">Sign In</span></button>
</form>
<div style="text-align:center;margin-top:6px"><span id="toggle-link" style="font-size:11px;color:#94a3b8;cursor:pointer;font-weight:500">First time? Create account</span></div>
<?php endif; ?>
</div></div></main></div></div></div>
<script>
var __currentTab = 'login';
var __registerStep = 1;
var __currentRole = 'sourcing';
var stepsEl = document.getElementById('hero-steps');
var sourcingFields = document.getElementById('sourcing-fields');
var restaurantFields = document.getElementById('restaurant-fields');
var loginFields = document.getElementById('login-fields');
var registerFields = document.getElementById('register-fields');

function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
function safeVal(el) { return el ? el.value : ''; }

// Always get the right field for the active role
function regEmail() { return qs('#' + __currentRole + '-fields [name="email"]'); }
function regPwd() { return qs('#' + __currentRole + '-fields [name="password"]'); }
function regConfirm() { return qs('#' + __currentRole + '-fields [name="confirm_password"]'); }
function pwdMsg() { return document.getElementById(__currentRole === 'restaurant' ? 'rest-pwd-match-msg' : 'pwd-match-msg'); }
function loginEmail() { return qs('#login-fields [name="email"]'); }
function loginPwd() { return qs('#login-fields [name="password"]'); }

function togglePwd(btn) {
  var w = btn.closest('.pwd-wrapper'), i = w.querySelector('input'), o = w.querySelector('.eye-open'), c = w.querySelector('.eye-closed');
  if (i.type === 'password') { i.type = 'text'; o.style.display = 'none'; c.style.display = ''; }
  else { i.type = 'password'; o.style.display = ''; c.style.display = 'none'; }
}

function allFilled() {
  var e = safeVal(regEmail()).trim(), p = safeVal(regPwd()), cf = safeVal(regConfirm());
  if (!e || p.length < 8 || p !== cf || !cf) return false;
  if (__currentRole === 'sourcing') {
    var fn = qs('#full-name-input'), un = qs('#username-input'), addr = qs('#sourcing-fields [name="address"]');
    return fn && fn.value.trim() && un && un.value.trim() && addr && addr.value.trim();
  }
  var cn = qs('#company-input'), addr = qs('#restaurant-fields [name="address"]');
  return cn && cn.value.trim() && addr && addr.value.trim();
}

function showStep(n) {
  for (var i = 1; i <= 2; i++) {
    var r = document.getElementById('step-'+i+'-row'), c = document.getElementById('step-'+i+'-circle'), l = document.getElementById('step-'+i+'-label');
    if (i < n) {
      r.style.opacity = '1'; c.className = 'step-circle done'; l.className = 'step-label done';
    } else if (i === n) {
      r.style.opacity = '1'; c.className = 'step-circle active'; l.className = 'step-label active';
    } else {
      r.style.opacity = '0.4'; c.className = 'step-circle inactive'; l.className = 'step-label inactive';
    }
  }
  __registerStep = n;
}

function updateSteps(tab) {
  if (tab === 'register') {
    stepsEl.classList.add('visible');
    showStep(allFilled() ? 2 : 1);
  } else {
    stepsEl.classList.remove('visible');
  }
}

// Called on every input in the active register section
function onRegInput() {
  if (__currentTab !== 'register') return;
  // Check password match
  var pv = safeVal(regPwd()), cv = safeVal(regConfirm()), msg = pwdMsg();
  if (msg) {
    if (!cv) { msg.className = 'text-[9px] font-mono hidden'; }
    else if (pv === cv) { msg.textContent = '✓ Passwords match'; msg.className = 'text-[9px] font-mono text-emerald-500'; }
    else { msg.textContent = '⚠ Passwords do not match'; msg.className = 'text-[9px] font-mono text-red-500'; }
  }
  // Update step
  if (allFilled() && __registerStep < 2) showStep(2);
  else if (!allFilled() && __registerStep >= 2) showStep(1);
}

function attachInputs() {
  var section = document.getElementById(__currentRole + '-fields');
  if (!section) return;
  var inputs = section.querySelectorAll('input');
  for (var i = 0; i < inputs.length; i++) inputs[i].addEventListener('input', onRegInput);
}

function getTitle() { return __currentTab === 'login' ? 'Sign In' : 'Create Account'; }
function getSubtitle() { return __currentTab === 'login' ? 'Access your secure space to manage active seafood bookings.' : 'Join the International Fish Market network.'; }

// Disable hidden section inputs on submit so they don't override visible ones
var authForm = document.getElementById('auth-form');
if (authForm) authForm.addEventListener('submit', function() {
  if (__currentTab === 'login') {
    document.querySelectorAll('#register-fields input, #register-fields select').forEach(function(el) { el.disabled = true; });
    document.querySelectorAll('#login-fields input').forEach(function(el) { el.disabled = false; });
  } else {
    document.querySelectorAll('#login-fields input').forEach(function(el) { el.disabled = true; });
    // Only enable the active role section, disable the other
    var activeSectionId = __currentRole + '-fields';
    document.querySelectorAll('#sourcing-fields input, #sourcing-fields select, #restaurant-fields input, #restaurant-fields select').forEach(function(el) {
      el.disabled = !el.closest('#' + activeSectionId);
    });
  }
});

// --- Tab switching ---
var tabLogin = qs('.tab-btn[data-tab="login"]');
var tabRegister = qs('.tab-btn[data-tab="register"]');
if (tabLogin) tabLogin.addEventListener('click', function(){ switchTab('login'); });
if (tabRegister) tabRegister.addEventListener('click', function(){ switchTab('register'); });

function switchTab(tab) {
  __currentTab = tab;
  document.getElementById('form_action').value = tab;
  loginFields.style.display = tab === 'login' ? 'block' : 'none';
  registerFields.style.display = tab === 'register' ? 'block' : 'none';
  document.getElementById('submit-text').textContent = getTitle();
  document.getElementById('form-title').textContent = getTitle();
  document.getElementById('form-subtitle').textContent = getSubtitle();
  var link = document.getElementById('toggle-link');
  link.textContent = tab === 'login' ? 'First time? Create account' : 'Already have an account? Sign in';
  // Update active tab style
  document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('bg-white','text-brand-blue','shadow-md'); b.classList.add('text-slate-500'); });
  var active = qs('.tab-btn[data-tab="'+tab+'"]');
  if (active) { active.classList.add('bg-white','text-brand-blue','shadow-md'); active.classList.remove('text-slate-500'); }
  // Reset sign-in role to first one when switching to login tab
  if (tab === 'login') {
    var firstRole = qs('.sign-role-btn');
    if (firstRole) {
      document.querySelectorAll('.sign-role-btn').forEach(function(b){ b.classList.remove('bg-white','text-brand-blue','shadow-sm'); b.classList.add('text-slate-500'); });
      firstRole.classList.add('bg-white','text-brand-blue','shadow-sm'); firstRole.classList.remove('text-slate-500');
      __currentRole = firstRole.dataset.role;
      qs('#user_type_input').value = __currentRole;
    }
  }
  updateSteps(tab);
  if (tab === 'register') attachInputs();
}

// --- Sign-in role ---
document.querySelectorAll('.sign-role-btn').forEach(function(btn){
  btn.addEventListener('click',function(){
    document.querySelectorAll('.sign-role-btn').forEach(function(b){ b.classList.remove('bg-white','text-brand-blue','shadow-sm'); b.classList.add('text-slate-500'); });
    this.classList.add('bg-white','text-brand-blue','shadow-sm'); this.classList.remove('text-slate-500');
    __currentRole = this.dataset.role;
    qs('#user_type_input').value = __currentRole;
  });
});

// --- Register role ---
document.querySelectorAll('.reg-role-btn').forEach(function(btn){
  btn.addEventListener('click',function(){
    document.querySelectorAll('.reg-role-btn').forEach(function(b){
      b.classList.remove('bg-white','text-brand-blue','shadow-sm','border-brand-blue');
      b.classList.add('text-slate-500','border-slate-200');
    });
    this.classList.add('bg-white','text-brand-blue','shadow-sm','border-brand-blue');
    this.classList.remove('text-slate-500','border-slate-200');
    __currentRole = this.dataset.role;
    qs('#user_type_input').value = __currentRole;
    sourcingFields.style.display = __currentRole === 'sourcing' ? 'block' : 'none';
    restaurantFields.style.display = __currentRole === 'restaurant' ? 'block' : 'none';
    attachInputs();
    if (__currentTab === 'register') showStep(1);
  });
});

// --- Toggle link ---
var toggleLink = document.getElementById('toggle-link');
if (toggleLink) toggleLink.addEventListener('click', function(){
  switchTab(__currentTab === 'login' ? 'register' : 'login');
});

<?php if ($success === 'registered_ok'): ?>
(function(){
  showStep(2);
  var remaining = 3;
  var countdown = document.getElementById('redirect-countdown');
  var targetUrl = <?= json_encode(url_for('index')) ?>;
  if (countdown) countdown.textContent = remaining;
  var timer = setInterval(function(){
    remaining -= 1;
    if (countdown) countdown.textContent = Math.max(remaining, 1);
    if (remaining <= 0) {
      clearInterval(timer);
      window.location.href = targetUrl;
    }
  }, 1000);
})();
<?php elseif ($showRegisterTab): ?>
(function(){ switchTab('register'); var rb = qs('.reg-role-btn[data-role="<?= e($userType) ?>"]'); if (rb) rb.click(); })();
<?php endif; ?>
updateSteps('<?= $success === 'registered_ok' ? 'register' : ($showRegisterTab ? 'register' : 'login') ?>');
</script>
<script src="js/app.js?v=<?= urlencode(__ifmAssetVersion('js/app.js')) ?>"></script>
</body></html>
