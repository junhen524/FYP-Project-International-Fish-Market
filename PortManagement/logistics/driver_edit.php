<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect('/logistics/drivers/'); exit; }

$targetDriver = null;
try { $targetDriver = dbGetRow("SELECT d.*, p.name as port_name FROM market_drivers d LEFT JOIN ports p ON p.id = d.port_id WHERE d.id = ?", [$id]); } catch (Exception $e) {}
if (!$targetDriver) {
    redirect('/logistics/drivers/');
    exit;
}

$message = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $identification_no = $_POST['identification_no'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $port_id = (int)($_POST['port_id'] ?? 0);
    $license_no = $_POST['license_no'] ?? '';
    $vehicle_no = $_POST['vehicle_no'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    if ($name) {
        try {
            dbExecute("UPDATE market_drivers SET name = ?, phone = ?, port_id = ?, license_no = ?, vehicle_no = ?, identification_no = ?, is_active = ? WHERE id = ?",
                [$name, $phone, $port_id, $license_no, $vehicle_no, $identification_no, $is_active, $id]);
            $message = 'Driver updated successfully.';
            $targetDriver = dbGetRow("SELECT d.*, p.name as port_name FROM market_drivers d LEFT JOIN ports p ON p.id = d.port_id WHERE d.id = ?", [$id]);
        } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
    } else { $error = 'Name is required.'; }
}

$ports = [];
try { $ports = dbGetAll("SELECT id, name FROM ports ORDER BY id"); } catch (Exception $e) {}

$title = 'Edit Driver';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.edit-card{width:100%;max-width:820px;margin:0 auto;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:20px;backdrop-filter:blur(25px);-webkit-backdrop-filter:blur(25px);box-shadow:0 30px 60px rgba(0,0,0,0.4);display:flex;overflow:hidden}
.edit-sidebar{width:30%;background:rgba(0,0,0,0.15);border-right:1px solid rgba(255,255,255,0.08);padding:40px 20px;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;text-align:center}

.edit-sidebar h3{margin:0;font-size:1.2rem;font-weight:700;color:#fff}
.edit-sidebar .sidebar-email{font-size:.8rem;color:#b0bec5;margin:5px 0 25px}
.status-pill{display:inline-flex;align-items:center;gap:6px;background:rgba(0,230,118,0.08);border:1px solid #00e676;color:#00e676;padding:6px 14px;border-radius:20px;font-size:.8rem;font-weight:600;text-shadow:0 0 10px rgba(0,230,118,0.3)}
.status-pill.inactive{border-color:#b0bec5;color:#b0bec5;text-shadow:none}
.status-dot{width:8px;height:8px;background:#00e676;border-radius:50%;box-shadow:0 0 8px #00e676}
.status-dot.inactive{background:#b0bec5;box-shadow:none}
.edit-form{width:70%;padding:35px 30px;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between}
.form-section-title{font-size:.8rem;color:#00e5ff;text-transform:uppercase;letter-spacing:1px;margin:0 0 15px 0;border-bottom:1px solid rgba(255,255,255,0.05);padding-bottom:6px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px 20px;margin-bottom:25px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group label{font-size:.75rem;color:#b0bec5;text-transform:uppercase;letter-spacing:.5px}
.form-input,.form-select{width:100%;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:9px 12px;box-sizing:border-box;color:#fff;font-size:.9rem;transition:border-color .3s,box-shadow .3s}
.form-select{appearance:none;background-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23b0bec5' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:30px}
.form-input:focus,.form-select:focus{outline:none;border-color:#00e5ff;box-shadow:0 0 10px rgba(0,229,255,0.2)}
.action-buttons{display:flex;justify-content:flex-end;gap:12px;border-top:1px solid rgba(255,255,255,0.05);padding-top:20px}
.btn-cancel{background:transparent;border:1px solid rgba(255,255,255,0.08);color:#b0bec5;padding:8px 20px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:600;transition:background .2s,color .2s}
.btn-cancel:hover{background:rgba(255,255,255,0.05);color:#fff;border-color:rgba(255,255,255,0.2)}
.btn-save{background:linear-gradient(135deg,#00e5ff,#0288d1);border:none;color:#fff;padding:8px 24px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:700;box-shadow:0 4px 15px rgba(2,136,209,0.2);transition:transform .2s,box-shadow .2s}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,229,255,0.35)}
@media(max-width:768px){.edit-card{flex-direction:column}.edit-sidebar{width:100%;border-right:none;border-bottom:1px solid rgba(255,255,255,0.08)}.edit-form{width:100%}.form-grid{grid-template-columns:1fr}}
</style>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🐟 Market</div>
    <a class="dash-sidebar-item" href="/dashboard/analytics/market/">📊 Dashboard</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/market/orders/">📋 Orders</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/market/users/">👥 Users</a>
    <a class="dash-sidebar-item" href="/dashboard/analytics/market/topup/">💰 Top-Up</a>
    <a class="dash-sidebar-item" href="/logistics/">🚛 Delivery</a>
    <a class="dash-sidebar-item" href="/logistics/warehouse/">📦 Warehouse</a>
    <a class="dash-sidebar-item" href="/logistics/drivers/">👤 Drivers</a>
  </nav>

  <div class="dash-content">
    <?php if ($message): ?><div style="max-width:820px;margin:0 auto 16px;padding:12px 16px;background:rgba(52,211,153,0.10);border:1px solid rgba(52,211,153,0.35);border-radius:10px;color:#34d399;font-size:.85rem;"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div style="max-width:820px;margin:0 auto 16px;padding:12px 16px;background:rgba(251,113,133,0.10);border:1px solid rgba(251,113,133,0.35);border-radius:10px;color:#fb7185;font-size:.85rem;"><?= e($error) ?></div><?php endif; ?>

    <form method="post" style="max-width:820px;margin:0 auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
          <h2 style="margin:0;font-size:1.3rem;">✏️ Edit Driver</h2>
          <p class="subtle" style="margin:4px 0 0;">Local Delivery Driver</p>
        </div>
        <a href="/logistics/drivers/" class="btn btn-ghost" style="padding:8px 16px;">← Back to Drivers</a>
      </div>

      <div class="edit-card">
        <div class="edit-sidebar">
          <div class="avatar-upload">
            <div class="avatar-preview">🚛</div>
            <input type="file" accept="image/jpeg,image/png,image/webp" onchange="previewAvatar(this)">
          </div>
          <h3><?= e($targetDriver['name'] ?? 'Driver') ?></h3>
          <p class="sidebar-email"><?= e($targetDriver['phone'] ?? '') ?></p>
          <div class="status-pill<?= $targetDriver['is_active'] ? '' : ' inactive' ?>">
            <span class="status-dot<?= $targetDriver['is_active'] ? '' : ' inactive' ?>"></span>
            <?= $targetDriver['is_active'] ? 'Active' : 'Inactive' ?> Driver
          </div>
        </div>

        <div class="edit-form">
          <div>
            <div class="form-section-title">Driver Info</div>
            <div class="form-grid">
              <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-input" value="<?= e($targetDriver['name'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" class="form-input" value="<?= e($targetDriver['phone'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="identification_no">IC / Identification No</label>
                <input type="text" id="identification_no" name="identification_no" class="form-input" value="<?= e($targetDriver['identification_no'] ?? '') ?>" placeholder="Last 6 digits for verification">
              </div>
              <div class="form-group">
                <label for="port_id">Port</label>
                <select id="port_id" name="port_id" class="form-select">
                  <?php foreach ($ports as $p): ?>
                  <option value="<?= $p['id'] ?>" <?= ($targetDriver['port_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="vehicle_no">Vehicle No</label>
                <input type="text" id="vehicle_no" name="vehicle_no" class="form-input" value="<?= e($targetDriver['vehicle_no'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="license_no">License No</label>
                <input type="text" id="license_no" name="license_no" class="form-input" value="<?= e($targetDriver['license_no'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Status</label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.9rem;color:#fff;">
                  <input type="checkbox" name="is_active" value="1" <?= $targetDriver['is_active'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#00e5ff;">
                  Active Driver
                </label>
              </div>
            </div>
          </div>

          <div class="action-buttons">
            <a href="/logistics/drivers/" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-save">Save Changes</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../helpers/footer.php'; ?>
