<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect('/dashboard/analytics/international/restaurants/'); exit; }

$targetUser = null;
try { $targetUser = dbGetRow("SELECT id, company_name, email, phone, country_code, address, reg_no, account_status FROM export_restaurant_user WHERE id = ?", [$id]); } catch (Exception $e) {}
if (!$targetUser) {
    redirect('/dashboard/analytics/international/restaurants/');
    exit;
}

$message = ''; $error = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        dbExecute("DELETE FROM export_restaurant_user WHERE id = ?", [$id]);
        $_SESSION['_restaurant_msg'] = 'Restaurant deleted.';
        redirect('/dashboard/analytics/international/restaurants/');
        exit;
    } catch (Exception $e) { $error = 'Delete failed: ' . $e->getMessage(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delete') {
    $company_name = $_POST['company_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $country_code = $_POST['country_code'] ?? '';
    $address = $_POST['address'] ?? '';
    $reg_no = $_POST['reg_no'] ?? '';
    $account_status = $_POST['account_status'] ?? 'active';
    if ($company_name && $email) {
        try {
            dbExecute("UPDATE export_restaurant_user SET company_name = ?, email = ?, phone = ?, country_code = ?, address = ?, reg_no = ?, account_status = ? WHERE id = ?",
                [$company_name, $email, $phone, $country_code, $address, $reg_no, $account_status, $id]);
            $message = 'Restaurant updated successfully.';
            $_SESSION['_restaurant_msg'] = $message;
            redirect('/dashboard/analytics/international/restaurants/');
            exit;
        } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
    } else { $error = 'Company name and email are required.'; }
}

$countries = [];
try {
    $rows = dbGetAll("SELECT DISTINCT country_code FROM export_restaurant_user WHERE country_code IS NOT NULL ORDER BY country_code");
    foreach ($rows as $r) { $countries[] = $r['country_code'] ?? ''; }
} catch (Exception $e) {}

$title = 'Edit International Restaurant';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.edit-card{width:100%;max-width:820px;margin:0 auto;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:20px;backdrop-filter:blur(25px);-webkit-backdrop-filter:blur(25px);box-shadow:0 30px 60px rgba(0,0,0,0.4);display:flex;overflow:hidden}
.edit-sidebar{width:30%;background:rgba(0,0,0,0.15);border-right:1px solid rgba(255,255,255,0.08);padding:40px 20px;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;text-align:center}
.edit-sidebar h3{margin:0;font-size:1.2rem;font-weight:700;color:#fff}
.edit-sidebar .sidebar-email{font-size:.8rem;color:#b0bec5;margin:5px 0 25px}
.status-pill{display:inline-flex;align-items:center;gap:6px;background:rgba(0,230,118,0.08);border:1px solid #00e676;color:#00e676;padding:6px 14px;border-radius:20px;font-size:.8rem;font-weight:600;text-shadow:0 0 10px rgba(0,230,118,0.3)}
.status-dot{width:8px;height:8px;background:#00e676;border-radius:50%;box-shadow:0 0 8px #00e676}
.edit-form{width:70%;padding:35px 30px;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between}
.form-section-title{font-size:.8rem;color:#00e5ff;text-transform:uppercase;letter-spacing:1px;margin:0 0 15px 0;border-bottom:1px solid rgba(255,255,255,0.05);padding-bottom:6px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px 20px;margin-bottom:25px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group label{font-size:.75rem;color:#b0bec5;text-transform:uppercase;letter-spacing:.5px}
.form-input,.form-select{width:100%;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:9px 12px;box-sizing:border-box;color:#fff;font-size:.9rem;transition:border-color .3s,box-shadow .3s}
</style>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🌍 International</div>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/">📊 Dashboard</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/orders/">📋 Orders</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/users/">👥 Users</a>
    <a class="dash-sidebar-item active" href="/dashboard/analytics/international/restaurants/">🏪 Restaurants</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/international/topup/">💰 Top-Up</a>
    <a class="dash-sidebar-item" href="/logistics/international/">🚢 Export Logistics</a>
    <a class="dash-sidebar-item" href="/logistics/export_driver/">👤 Intl. Drivers</a>
  </nav>

  <div class="dash-content">
    <div class="edit-card">
      <div class="edit-sidebar">
        <h3>🏪 <?= e($targetUser['company_name'] ?? '') ?></h3>
        <div class="sidebar-email"><?= e($targetUser['email'] ?? '') ?></div>
        <div class="status-pill"><span class="status-dot"></span><?= e(ucfirst($targetUser['account_status'] ?? 'active')) ?></div>
      </div>

      <div class="edit-form">
        <?php if ($message): ?><div class="msg-success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg-error"><?= e($error) ?></div><?php endif; ?>

        <form method="POST">
          <div class="form-section-title">🏪 Restaurant Information</div>
          <div class="form-grid">
            <div class="form-group"><label>Company Name</label><input type="text" name="company_name" class="form-input" value="<?= e($targetUser['company_name'] ?? '') ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-input" value="<?= e($targetUser['email'] ?? '') ?>" required></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-input" value="<?= e($targetUser['phone'] ?? '') ?>"></div>
            <div class="form-group"><label>Reg No.</label><input type="text" name="reg_no" class="form-input" value="<?= e($targetUser['reg_no'] ?? '') ?>"></div>
            <div class="form-group"><label>Country</label>
              <select name="country_code" class="form-select">
                <option value="">—</option>
                <?php foreach ($countries as $c): ?>
                <option value="<?= e($c) ?>" <?= ($targetUser['country_code'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Status</label>
              <select name="account_status" class="form-select">
                <option value="active" <?= ($targetUser['account_status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($targetUser['account_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="suspended" <?= ($targetUser['account_status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
              </select>
            </div>
          </div>

          <div class="form-section-title">📍 Address</div>
          <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1"><label>Address</label><textarea name="address" class="form-input" rows="3"><?= e($targetUser['address'] ?? '') ?></textarea></div>
          </div>

          <button type="submit" style="width:100%;padding:12px;border:none;border-radius:10px;background:var(--brand);color:#081225;font-size:14px;font-weight:800;cursor:pointer;margin-top:10px;">💾 Save Changes</button>
          <a href="/dashboard/analytics/international/restaurants/" style="display:block;text-align:center;margin-top:10px;color:var(--muted);font-size:12px;">← Back to Restaurants</a>
        </form>

        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this restaurant? This cannot be undone.');" style="margin-top:20px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.08);">
          <input type="hidden" name="action" value="delete">
          <button type="submit" style="width:100%;padding:12px;border:none;border-radius:10px;background:rgba(239,68,68,0.15);color:#ef4444;font-size:14px;font-weight:800;cursor:pointer;border:1px solid rgba(239,68,68,0.3);">🗑️ Delete Restaurant</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
