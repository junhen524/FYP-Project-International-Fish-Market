<?php
require_once __DIR__ . '/../helpers/functions.php';

// ── Compute base path dynamically (works from any host/IP) ──
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($basePath === '') $basePath = '';

$error = $_GET['error'] ?? '';
$redirect = $_GET['redirect'] ?? '';

if (isLoggedIn()) {
    header('Location: ' . $basePath . '/dashboard/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    $user = authenticateUser($username, $password);
    if ($user) {
        loginUser($user['id'], $remember);
        $redirect_url = $redirect ?: $basePath . '/dashboard/';
        header("Location: $redirect_url");
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Fishery Management System</title>
<base href="<?= $basePath ?>/">
<link rel="stylesheet" href="static/css/app.css">
<style>
.login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg); padding: 20px; }
.login-container { width: 100%; max-width: 420px; }
.login-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 40px 36px; backdrop-filter: blur(20px); }
.login-logo { text-align: center; margin-bottom: 32px; }
.login-logo .icon { font-size: 48px; margin-bottom: 8px; }
.login-logo h1 { font-size: 22px; font-weight: 800; margin: 0; }
.login-logo p { font-size: 13px; color: var(--muted); margin: 6px 0 0; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); margin-bottom: 8px; }
.form-group input { width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--border); background: rgba(11,18,32,0.5); color: var(--text); font-size: 14px; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
.form-group input:focus { border-color: var(--brand); }
.form-group .input-icon-wrap { position: relative; }
.form-group .input-icon-wrap .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 16px; }
.form-group .input-icon-wrap input { padding-left: 44px; }
.remember-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.remember-row label { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); cursor: pointer; }
.remember-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--brand); }
.login-btn { width: 100%; padding: 14px; border: none; border-radius: 12px; background: var(--brand); color: #081225; font-size: 15px; font-weight: 800; cursor: pointer; transition: all 0.2s; }
.login-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(45,212,191,0.3); }
.login-footer { text-align: center; margin-top: 24px; color: var(--muted); font-size: 12px; }
.login-footer a { color: var(--brand); text-decoration: none; font-weight: 600; }
.login-footer a:hover { text-decoration: underline; }
.alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; }
.alert-error { background: rgba(251,113,133,0.10); border: 1px solid rgba(251,113,133,0.25); color: #fb7185; }
.system-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; background: rgba(45,212,191,0.10); border: 1px solid rgba(45,212,191,0.20); font-size: 11px; font-weight: 600; color: var(--brand); margin-top: 16px; }
</style>
</head>
<body class="login-page">
<div class="login-container">
  <div class="login-card">
    <div class="login-logo">
      <div class="icon">🐟</div>
      <h1>Fishery Management</h1>
      <p>Sign in to your account to continue</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label>Username or Email</label>
        <div class="input-icon-wrap">
          <span class="input-icon">👤</span>
          <input type="text" name="username" placeholder="Enter your username" required autofocus>
        </div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="input-icon-wrap">
          <span class="input-icon">🔑</span>
          <input type="password" name="password" placeholder="Enter your password" required>
        </div>
      </div>

      <div class="remember-row">
        <label>
          <input type="checkbox" name="remember" value="1"> Remember me
        </label>
        <a href="#" style="color:var(--brand);font-size:12px;font-weight:600;text-decoration:none;">Forgot password?</a>
      </div>

      <button type="submit" class="login-btn">Sign In</button>
    </form>

    <div style="text-align:center;">
      <div class="system-badge">🔒 Secure Login · SSL Encrypted</div>
    </div>

    <div class="login-footer">
      &copy; 2026 Fishery Management System
    </div>
  </div>
</div>
</body>
</html>
