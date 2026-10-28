<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$message = ''; $error = '';
$countries = [];
try {
    $rows = dbGetAll("SELECT DISTINCT country_code FROM export_user WHERE country_code IS NOT NULL ORDER BY country_code");
    foreach ($rows as $r) { $countries[] = $r['country_code'] ?? ''; }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $business_type = $_POST['business_type'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $country_code = $_POST['country_code'] ?? '';
    $identification_no = trim($_POST['identification_no'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $email && $password && $country_code) {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            dbExecute("INSERT INTO export_user (username, email, password_hash, full_name, company_name, business_type, phone, country_code, identification_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$username, $email, $hashed, $full_name, $company_name, $business_type, $phone, $country_code, $identification_no]);
            $message = "User '$username' created successfully.";
        } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
    } else { $error = 'Username, email, password and country are required.'; }
}

$title = 'Add International User';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.edit-card{width:100%;max-width:820px;margin:0 auto;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:20px;backdrop-filter:blur(25px);-webkit-backdrop-filter:blur(25px);box-shadow:0 30px 60px rgba(0,0,0,0.4);display:flex;overflow:hidden}
.edit-sidebar{width:30%;background:rgba(0,0,0,0.15);border-right:1px solid rgba(255,255,255,0.08);padding:40px 20px;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;text-align:center}

.edit-sidebar h3{margin:0;font-size:1.2rem;font-weight:700;color:#fff}
.edit-sidebar .sidebar-email{font-size:.8rem;color:#b0bec5;margin:5px 0 25px}
.edit-form{width:70%;padding:35px 30px;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between}
.form-section-title{font-size:.8rem;color:#00e5ff;text-transform:uppercase;letter-spacing:1px;margin:0 0 15px 0;border-bottom:1px solid rgba(255,255,255,0.05);padding-bottom:6px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px 20px;margin-bottom:25px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group label{font-size:.75rem;color:#b0bec5;text-transform:uppercase;letter-spacing:.5px}
.form-input,.form-select{width:100%;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:9px 12px;box-sizing:border-box;color:#fff;font-size:.9rem;transition:border-color .3s,box-shadow .3s}
.form-select{appearance:none;background-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23b0bec5' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:30px}
.form-input:focus,.form-select:focus{outline:none;border-color:#00e5ff;box-shadow:0 0 10px rgba(0,229,255,0.2)}
.action-buttons{display:flex;justify-content:flex-end;gap:12px;border-top:1px solid rgba(255,255,255,0.05);padding-top:20px}
.btn-cancel{background:transparent;border:1px solid rgba(255,255,255,0.08);color:#b0bec5;padding:8px 20px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:600;transition:background .2s,color .2s;text-decoration:none;display:inline-flex;align-items:center}
.btn-cancel:hover{background:rgba(255,255,255,0.05);color:#fff;border-color:rgba(255,255,255,0.2)}
.btn-save{background:linear-gradient(135deg,#00e5ff,#0288d1);border:none;color:#fff;padding:8px 24px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:700;box-shadow:0 4px 15px rgba(2,136,209,0.2);transition:transform .2s,box-shadow .2s}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,229,255,0.35)}
@media(max-width:768px){.edit-card{flex-direction:column}.edit-sidebar{width:100%;border-right:none;border-bottom:1px solid rgba(255,255,255,0.08)}.edit-form{width:100%}.form-grid{grid-template-columns:1fr}}
</style>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🌍 International</div>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/">📊 Dashboard</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/orders/">📋 Orders</a>
    <a class="dash-sidebar-item active" href="/dashboard/analytics/international/users/">👥 Users</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/restaurants/">🏪 Restaurants</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/topup/">💰 Top-Up</a>
    <a class="dash-sidebar-item" href="/logistics/international/">🚢 Export Logistics</a>
    <a class="dash-sidebar-item" href="/logistics/export_driver/">👤 Intl. Drivers</a>
  </nav>

  <div class="dash-content">
    <?php if ($message): ?><div style="max-width:820px;margin:0 auto 16px;padding:12px 16px;background:rgba(52,211,153,0.10);border:1px solid rgba(52,211,153,0.35);border-radius:10px;color:#34d399;font-size:.85rem;"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div style="max-width:820px;margin:0 auto 16px;padding:12px 16px;background:rgba(251,113,133,0.10);border:1px solid rgba(251,113,133,0.35);border-radius:10px;color:#fb7185;font-size:.85rem;"><?= e($error) ?></div><?php endif; ?>

    <form method="post" style="max-width:820px;margin:0 auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
          <h2 style="margin:0;font-size:1.3rem;">➕ Add International User</h2>
          <p class="subtle" style="margin:4px 0 0;">Create a new export partner account</p>
        </div>
        <a href="/dashboard/analytics/international/users/" class="btn btn-ghost" style="padding:8px 16px;">← Back to Users</a>
      </div>

      <div class="edit-card">
        <div class="edit-sidebar">
          <div class="avatar-upload">
            <div class="avatar-preview">🌍</div>
            <input type="file" accept="image/jpeg,image/png,image/webp" onchange="previewAvatar(this)">
          </div>
          <h3>New User</h3>
          <p class="sidebar-email">Fill in the details below</p>
        </div>

        <div class="edit-form">
          <div>
            <div class="form-section-title">Account Info</div>
            <div class="form-grid">
              <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-input" required placeholder="e.g. sakura_import">
              </div>
              <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-input" required placeholder="e.g. info@sakura.com">
              </div>
              <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" required placeholder="Min 6 characters">
              </div>
              <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-input" placeholder="e.g. John Tan">
              </div>
              <div class="form-group">
                <label for="company_name">Company</label>
                <input type="text" id="company_name" name="company_name" class="form-input" placeholder="e.g. Sakura Imports Co.">
              </div>
              <div class="form-group">
                <label for="business_type">Business Type</label>
                <select id="business_type" name="business_type" class="form-select">
                  <option value="">Select Type</option>
                  <option value="importer">Importer</option>
                  <option value="restaurant">Restaurant</option>
                  <option value="distributor">Distributor</option>
                  <option value="wholesaler">Wholesaler</option>
                  <option value="retailer">Retailer</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="form-group">
                <label for="country_code">Country</label>
                <select id="country_code" name="country_code" class="form-select" required>
                  <option value="">Select Country</option>
                  <?php foreach ($countries as $c): ?>
                  <option value="<?= e($c) ?>"><?= e($c) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" class="form-input" placeholder="e.g. +81 3-1234 5678">
              </div>
              <div class="form-group">
                <label for="identification_no">IC / Identification No</label>
                <input type="text" id="identification_no" name="identification_no" class="form-input" placeholder="Last 6 digits for verification">
              </div>
            </div>
          </div>

          <div class="action-buttons">
            <a href="/dashboard/analytics/international/users/" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-save">➕ Add User</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../helpers/footer.php'; ?>
