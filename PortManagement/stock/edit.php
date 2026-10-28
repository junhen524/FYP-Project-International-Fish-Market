<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$id = (int)($_GET['id'] ?? 0);
$product = null;
try { $product = dbGetRow("SELECT p.* FROM product p WHERE p.id = ?", [$id]); } catch (Exception $e) {}
$productNotFound = !$product;

$message = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$productNotFound) {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? 'fish';
    $freshness = $_POST['freshness'] ?? 'fresh';
    $domestic_price = (float)($_POST['domestic_price'] ?? 0);
    $export_price = (float)($_POST['export_price'] ?? 0);
    $tier_3kg_price = (float)($_POST['tier_3kg_price'] ?? 0);
    $tier_3kg_export = (float)($_POST['tier_3kg_export_price'] ?? 0);
    $tier_6kg_price = (float)($_POST['tier_6kg_price'] ?? 0);
    $tier_6kg_export = (float)($_POST['tier_6kg_export_price'] ?? 0);
    $tier_10kg_price = (float)($_POST['tier_10kg_price'] ?? 0);
    $tier_10kg_export = (float)($_POST['tier_10kg_export_price'] ?? 0);
    $origin = trim($_POST['origin'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = ($_POST['is_active'] ?? 'true') === 'true';

    if ($name && $domestic_price > 0) {
        try {
            dbExecute(
                "UPDATE product SET name = ?, category = ?, freshness = ?, domestic_price = ?, export_price = ?, 
                    tier_3kg_price = ?, tier_3kg_export_price = ?, tier_6kg_price = ?, tier_6kg_export_price = ?, tier_10kg_price = ?, tier_10kg_export_price = ?,
                    origin = ?, description = ?, is_active = ? WHERE id = ?",
                [$name, $category, $freshness, $domestic_price, $export_price,
                 $tier_3kg_price, $tier_3kg_export, $tier_6kg_price, $tier_6kg_export, $tier_10kg_price, $tier_10kg_export,
                 $origin, $description, $is_active ? 1 : 0, $id]
            );
            // Save unit stock
            $tier_3kg_stock = max(0, (int)($_POST['tier_3kg_stock'] ?? 0));
            $tier_6kg_stock = max(0, (int)($_POST['tier_6kg_stock'] ?? 0));
            $tier_10kg_stock = max(0, (int)($_POST['tier_10kg_stock'] ?? 0));
            dbExecute("UPDATE product SET tier_3kg_stock = ?, tier_6kg_stock = ?, tier_10kg_stock = ? WHERE id = ?", [$tier_3kg_stock, $tier_6kg_stock, $tier_10kg_stock, $id]);
            $message = "Product updated successfully.";
            $product = dbGetRow("SELECT p.* FROM product p WHERE p.id = ?", [$id]);
        } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
    } else { $error = 'Product name and domestic price are required.'; }
}

$title = 'Edit Product';
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
.form-textarea{width:100%;background:rgba(0,0,0,0.25);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:9px 12px;box-sizing:border-box;color:#fff;font-size:.9rem;transition:border-color .3s,box-shadow .3s;min-height:70px;resize:vertical;font-family:inherit}
.form-textarea:focus{outline:none;border-color:#00e5ff;box-shadow:0 0 10px rgba(0,229,255,0.2)}
.action-buttons{display:flex;justify-content:flex-end;gap:12px;border-top:1px solid rgba(255,255,255,0.05);padding-top:20px}
.btn-cancel{background:transparent;border:1px solid rgba(255,255,255,0.08);color:#b0bec5;padding:8px 20px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:600;transition:background .2s,color .2s;text-decoration:none;display:inline-flex;align-items:center}
.btn-cancel:hover{background:rgba(255,255,255,0.05);color:#fff;border-color:rgba(255,255,255,0.2)}
.btn-save{background:linear-gradient(135deg,#00e5ff,#0288d1);border:none;color:#fff;padding:8px 24px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:700;box-shadow:0 4px 15px rgba(2,136,209,0.2);transition:transform .2s,box-shadow .2s}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,229,255,0.35)}
.btn-delete-card{background:transparent;border:1px solid rgba(239,68,68,0.3);color:#ef4444;padding:8px 20px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:600;transition:background .2s,color .2s;text-decoration:none;display:inline-flex;align-items:center}
.btn-delete-card:hover{background:rgba(239,68,68,0.15)}

.modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);display:none;justify-content:center;align-items:center;z-index:1000}
.modal-overlay.active{display:flex}
.modal-content{background:#0f1a2e;border:1px solid var(--border);border-radius:16px;padding:24px;max-width:400px;width:90%}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.modal-title{font-size:18px;font-weight:700;margin:0}
.modal-close{background:none;border:none;color:var(--muted);font-size:24px;cursor:pointer;padding:0;line-height:1}
.modal-close:hover{color:var(--text)}
.btn-delete-modal{background:#ef4444;border:none;color:#fff;padding:8px 24px;border-radius:8px;cursor:pointer;font-size:.85rem;font-weight:700}
.btn-delete-modal:hover{filter:brightness(1.1)}
@media(max-width:768px){.edit-card{flex-direction:column}.edit-sidebar{width:100%;border-right:none;border-bottom:1px solid rgba(255,255,255,0.08)}.edit-form{width:100%}.form-grid{grid-template-columns:1fr}}
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
    <a class="dash-sidebar-item" href="/dashboard/stock/">📦 All</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/?category=fish">🐟 Fish</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/?category=shellfish">🦪 Shellfish</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/?category=crustacean">🦀 Crustacean</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/?category=cephalopod">🐙 Cephalopod</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/?category=mollusc">🦐 Mollusc</a>
    <div style="border-top:1px solid var(--border);margin:8px 14px;"></div>
    <a class="dash-sidebar-item" href="/dashboard/stock/movements/">📊 Movements</a>
    <a class="dash-sidebar-item active" href="#">✏️ Edit Product</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/add/">➕ Add Product</a>
  </nav>

  <div class="dash-content">
    <?php if ($message): ?><div style="max-width:820px;margin:0 auto 16px;padding:12px 16px;background:rgba(52,211,153,0.10);border:1px solid rgba(52,211,153,0.35);border-radius:10px;color:#34d399;font-size:.85rem;"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div style="max-width:820px;margin:0 auto 16px;padding:12px 16px;background:rgba(251,113,133,0.10);border:1px solid rgba(251,113,133,0.35);border-radius:10px;color:#fb7185;font-size:.85rem;"><?= e($error) ?></div><?php endif; ?>

    <?php if ($product): ?>
    <form method="POST" enctype="multipart/form-data" style="max-width:820px;margin:0 auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
          <h2 style="margin:0;font-size:1.3rem;">✏️ Edit Product</h2>
          <p class="subtle" style="margin:4px 0 0;">Modify product details, pricing and description</p>
        </div>
        <a href="/dashboard/stock/" class="btn btn-ghost" style="padding:8px 16px;">← Back</a>
      </div>

      <div class="edit-card">
        <div class="edit-sidebar">
          <div class="avatar-upload">
            <div class="avatar-preview">
              <?php if (!empty($product['image_url'])): ?>
                <img src="<?= e(imgUrl($product['image_url'])) ?>" alt="<?= e($product['name'] ?? '') ?>">
              <?php else: ?>
                📦
              <?php endif; ?>
            </div>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" onchange="previewAvatar(this)">
          </div>
          <h3><?= e($product['name'] ?? 'Product') ?></h3>
        </div>

        <div class="edit-form">
          <div>
            <div class="form-section-title">Product Info</div>
            <div class="form-grid">
              <div class="form-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" class="form-input" value="<?= e($product['name'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label for="origin">Origin</label>
                <input type="text" id="origin" name="origin" class="form-input" value="<?= e($product['origin'] ?? '') ?>" placeholder="e.g. Malaysia Waters">
              </div>
              <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-select">
                  <option value="fish" <?= ($product['category'] ?? '') === 'fish' ? 'selected' : '' ?>>🐟 Fish</option>
                  <option value="shellfish" <?= ($product['category'] ?? '') === 'shellfish' ? 'selected' : '' ?>>🦪 Shellfish</option>
                  <option value="crustacean" <?= ($product['category'] ?? '') === 'crustacean' ? 'selected' : '' ?>>🦀 Crab</option>
                  <option value="cephalopod" <?= ($product['category'] ?? '') === 'cephalopod' ? 'selected' : '' ?>>🐙 Cephalopod</option>
                  <option value="mollusc" <?= ($product['category'] ?? '') === 'mollusc' ? 'selected' : '' ?>>🦐 Prawn</option>
                </select>
              </div>
              <div class="form-group">
                <label for="freshness">Freshness</label>
                <select id="freshness" name="freshness" class="form-select">
                  <option value="fresh" <?= ($product['freshness'] ?? '') === 'fresh' ? 'selected' : '' ?>>Fresh</option>
                  <option value="live" <?= ($product['freshness'] ?? '') === 'live' ? 'selected' : '' ?>>Live</option>
                  <option value="chilled" <?= ($product['freshness'] ?? '') === 'chilled' ? 'selected' : '' ?>>Chilled</option>
                  <option value="frozen" <?= ($product['freshness'] ?? '') === 'frozen' ? 'selected' : '' ?>>Frozen</option>
                  <option value="processed" <?= ($product['freshness'] ?? '') === 'processed' ? 'selected' : '' ?>>Processed</option>
                  <option value="preserved" <?= ($product['freshness'] ?? '') === 'preserved' ? 'selected' : '' ?>>Preserved</option>
                </select>
              </div>
            </div>

            <div class="form-section-title">💲 Pricing (per unit)</div>

            <div style="margin-bottom:24px;">
              <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                <div style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(0,0,0,0.10);">
                  <div style="font-weight:700;font-size:.85rem;color:#00e5ff;text-align:center;margin-bottom:10px;">approx 3KG per unit</div>
                  <div class="form-group" style="margin-bottom:8px;">
                    <label for="t3_d">Domestic (RM)</label>
                    <input type="number" id="t3_d" name="tier_3kg_price" class="form-input" value="<?= e($product['tier_3kg_price'] ?? 0) ?>" step="0.01" min="0">
                  </div>
                  <div class="form-group">
                    <label for="t3_e">Export (USD)</label>
                    <input type="number" id="t3_e" name="tier_3kg_export_price" class="form-input" value="<?= e($product['tier_3kg_export_price'] ?? 0) ?>" step="0.01" min="0">
                  </div>
                </div>
                <div style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(0,0,0,0.10);">
                  <div style="font-weight:700;font-size:.85rem;color:#00e5ff;text-align:center;margin-bottom:10px;">approx 6KG per unit</div>
                  <div class="form-group" style="margin-bottom:8px;">
                    <label for="t6_d">Domestic (RM)</label>
                    <input type="number" id="t6_d" name="tier_6kg_price" class="form-input" value="<?= e($product['tier_6kg_price'] ?? 0) ?>" step="0.01" min="0">
                  </div>
                  <div class="form-group">
                    <label for="t6_e">Export (USD)</label>
                    <input type="number" id="t6_e" name="tier_6kg_export_price" class="form-input" value="<?= e($product['tier_6kg_export_price'] ?? 0) ?>" step="0.01" min="0">
                  </div>
                </div>
                <div style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(0,0,0,0.10);">
                  <div style="font-weight:700;font-size:.85rem;color:#00e5ff;text-align:center;margin-bottom:10px;">approx 10KG per unit</div>
                  <div class="form-group" style="margin-bottom:8px;">
                    <label for="t10_d">Domestic (RM)</label>
                    <input type="number" id="t10_d" name="tier_10kg_price" class="form-input" value="<?= e($product['tier_10kg_price'] ?? 0) ?>" step="0.01" min="0">
                  </div>
                  <div class="form-group">
                    <label for="t10_e">Export (USD)</label>
                    <input type="number" id="t10_e" name="tier_10kg_export_price" class="form-input" value="<?= e($product['tier_10kg_export_price'] ?? 0) ?>" step="0.01" min="0">
                  </div>
                </div>
              </div>
            </div>

            <div class="form-section-title">📦 Unit Stock</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;">
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;background:rgba(0,0,0,0.12);text-align:center;">
                <div style="font-weight:700;font-size:.9rem;color:#00e5ff;margin-bottom:6px;">3kg</div>
                <input type="number" name="tier_3kg_stock" class="form-input" value="<?= (int)($product['tier_3kg_stock'] ?? 0) ?>" step="1" min="0" placeholder="0" style="text-align:center;font-weight:700;">
              </div>
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;background:rgba(0,0,0,0.12);text-align:center;">
                <div style="font-weight:700;font-size:.9rem;color:#00e5ff;margin-bottom:6px;">6kg</div>
                <input type="number" name="tier_6kg_stock" class="form-input" value="<?= (int)($product['tier_6kg_stock'] ?? 0) ?>" step="1" min="0" placeholder="0" style="text-align:center;font-weight:700;">
              </div>
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;background:rgba(0,0,0,0.12);text-align:center;">
                <div style="font-weight:700;font-size:.9rem;color:#00e5ff;margin-bottom:6px;">10kg</div>
                <input type="number" name="tier_10kg_stock" class="form-input" value="<?= (int)($product['tier_10kg_stock'] ?? 0) ?>" step="1" min="0" placeholder="0" style="text-align:center;font-weight:700;">
              </div>
            </div>

            <div class="form-section-title">Status</div>
            <div class="form-grid">
              <div class="form-group">
                <label for="is_active">Status</label>
                <select id="is_active" name="is_active" class="form-select">
                  <option value="true" <?= ($product['is_active'] ?? '') == '1' || ($product['is_active'] ?? '') === 't' ? 'selected' : '' ?>>Active</option>
                  <option value="false" <?= ($product['is_active'] ?? '') == '0' || ($product['is_active'] ?? '') === 'f' ? 'selected' : '' ?>>Inactive</option>
                </select>
              </div>
            </div>

            <div class="form-section-title">Description</div>
            <div class="form-group">
              <textarea id="description" name="description" class="form-textarea" placeholder="Product description..."><?= e($product['description'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="action-buttons">
            <button type="button" onclick="showDeleteModal(<?= e($id) ?>, '<?= e(addslashes($product['name'] ?? '')) ?>')" class="btn-delete-card">�️ Delete</button>
            <a href="/dashboard/stock/" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-save">📦 Save Changes</button>
          </div>
        </div>
      </div>
    </form>

    <?php else: ?>
    <div style="max-width:820px;margin:0 auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
          <h2 style="margin:0;font-size:1.3rem;">✏️ Edit Product</h2>
          <p class="subtle" style="margin:4px 0 0;">Modify product details, pricing and description</p>
        </div>
        <a href="/dashboard/stock/" class="btn btn-ghost" style="padding:8px 16px;">← Back</a>
      </div>
      <div class="edit-card">
        <div class="edit-sidebar" style="width:100%;border-right:none;padding:60px 20px;">
          <div style="font-size:48px;margin-bottom:12px;">🔍</div>
          <h3>Product not found</h3>
          <p class="sidebar-email">This product may have been removed or the ID is invalid.</p>
          <a href="/dashboard/stock/" class="btn btn-ghost" style="margin-top:12px;padding:10px 24px;">← Back to Products</a>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($product): ?>
<div class="modal-overlay" id="deleteModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">🗑️ Delete Product</h3>
      <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
    </div>
    <div class="modal-body">
      <p>Are you sure you want to delete <strong id="deleteProductName"></strong>?</p>
      <p style="color:var(--muted);margin-top:8px;font-size:13px;">This action cannot be undone.</p>
    </div>
    <div class="modal-footer" style="display:flex;gap:12px;justify-content:flex-end;">
      <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
      <form method="POST" id="deleteForm" action="dashboard/stock/" style="display:inline;">
        <input type="hidden" name="delete_id" id="deleteId" value="">
        <button type="submit" class="btn-delete-modal">Delete</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function showDeleteModal(id, name) {
  document.getElementById('deleteProductName').textContent = name;
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('active');
}
document.getElementById('deleteModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});
</script>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
