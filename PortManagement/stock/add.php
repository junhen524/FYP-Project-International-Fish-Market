<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? 'fish';
    $freshness = $_POST['freshness'] ?? 'fresh';
    $domestic_price = (float)($_POST['domestic_price'] ?? 0);
    $export_price = (float)($_POST['export_price'] ?? 0);
    $origin = trim($_POST['origin'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $tier_3kg_price = (float)($_POST['tier_3kg_price'] ?? 0);
    $tier_3kg_export = (float)($_POST['tier_3kg_export_price'] ?? 0);
    $tier_6kg_price = (float)($_POST['tier_6kg_price'] ?? 0);
    $tier_6kg_export = (float)($_POST['tier_6kg_export_price'] ?? 0);
    $tier_10kg_price = (float)($_POST['tier_10kg_price'] ?? 0);
    $tier_10kg_export = (float)($_POST['tier_10kg_export_price'] ?? 0);
    $tier_3kg_stock = max(0, (int)($_POST['tier_3kg_stock'] ?? 0));
    $tier_6kg_stock = max(0, (int)($_POST['tier_6kg_stock'] ?? 0));
    $tier_10kg_stock = max(0, (int)($_POST['tier_10kg_stock'] ?? 0));
    $tier3 = $tier_3kg_price ?: $tier_3kg_export;
    $tier6 = $tier_6kg_price ?: $tier_6kg_export;
    $tier10 = $tier_10kg_price ?: $tier_10kg_export;
    $hasAnyPrice = $tier3 || $tier6 || $tier10;

    if ($name && $hasAnyPrice) {
        try {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name)) . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
            $nextProductId = (int)dbGetValue("SELECT COALESCE(MAX(product_id), 0) + 1 FROM product");

            dbExecute(
                "INSERT INTO product (product_id, name, slug, category, freshness, unit,
                    tier_3kg_price, tier_3kg_export_price, tier_6kg_price, tier_6kg_export_price, tier_10kg_price, tier_10kg_export_price,
                    tier_3kg_stock, tier_6kg_stock, tier_10kg_stock,
                    description, origin, image_url, is_active, received_date, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'fresh', 'kg',
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, '', 1, CURRENT_DATE, NOW(), NOW())",
                [$nextProductId, $name, $slug, $category,
                 $tier_3kg_price, $tier_3kg_export, $tier_6kg_price, $tier_6kg_export, $tier_10kg_price, $tier_10kg_export,
                 $tier_3kg_stock, $tier_6kg_stock, $tier_10kg_stock,
                 $description, $origin]
            );
            $newId = dbLastInsertId();
            $portName = $user['port_name'] ?? 'Main';
            $message = "Product '$name' added successfully. (Batch added to $portName warehouse)";
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Product name and domestic price are required.';
    }
}

$title = 'Add Product';
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
    <a class="dash-sidebar-item active" href="/dashboard/stock/add/">➕ Add Product</a>
  </nav>

  <div class="dash-content">
    <?php if ($message): ?><div style="max-width:820px;margin:0 auto 16px;padding:12px 16px;background:rgba(52,211,153,0.10);border:1px solid rgba(52,211,153,0.35);border-radius:10px;color:#34d399;font-size:.85rem;"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div style="max-width:820px;margin:0 auto 16px;padding:12px 16px;background:rgba(251,113,133,0.10);border:1px solid rgba(251,113,133,0.35);border-radius:10px;color:#fb7185;font-size:.85rem;"><?= e($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" style="max-width:820px;margin:0 auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
          <h2 style="margin:0;font-size:1.3rem;">➕ Add Product</h2>
          <p class="subtle" style="margin:4px 0 0;">Add a product to the unified product catalog</p>
        </div>
        <a href="/dashboard/stock/" class="btn btn-ghost" style="padding:8px 16px;">← Back</a>
      </div>

      <div class="edit-card">
        <div class="edit-sidebar">
          <div class="avatar-upload">
            <div class="avatar-preview">📦</div>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" onchange="previewAvatar(this)">
          </div>
          <h3>New Product</h3>
          <p class="sidebar-email">Fill in the details below</p>
        </div>

        <div class="edit-form">
          <div>
            <div class="form-section-title">Product Info</div>
            <div class="form-grid">
              <div class="form-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" class="form-input" required placeholder="e.g. Whole Atlantic Salmon">
              </div>
              <div class="form-group">
                <label for="origin">Origin</label>
                <input type="text" id="origin" name="origin" class="form-input" placeholder="e.g. Norway">
              </div>
              <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-select">
                  <option value="fish">🐟 Fish</option>
                  <option value="shellfish">� Shellfish</option>
                  <option value="crustacean">🦀 Crab</option>
                  <option value="cephalopod">🐙 Cephalopod</option>
                  <option value="mollusc">🦐 Prawn</option>
                </select>
              </div>
              <div class="form-group">
                <label for="freshness">Freshness</label>
                <select id="freshness" name="freshness" class="form-select">
                  <option value="fresh">Fresh</option>
                  <option value="live">Live</option>
                  <option value="chilled">Chilled</option>
                  <option value="frozen">Frozen</option>
                  <option value="processed">Processed</option>
                  <option value="preserved">Preserved</option>
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
                    <input type="number" id="t3_d" name="tier_3kg_price" class="form-input" value="0" step="0.01" min="0">
                  </div>
                  <div class="form-group">
                    <label for="t3_e">Export (USD)</label>
                    <input type="number" id="t3_e" name="tier_3kg_export_price" class="form-input" value="0" step="0.01" min="0">
                  </div>
                </div>
                <div style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(0,0,0,0.10);">
                  <div style="font-weight:700;font-size:.85rem;color:#00e5ff;text-align:center;margin-bottom:10px;">approx 6KG per unit</div>
                  <div class="form-group" style="margin-bottom:8px;">
                    <label for="t6_d">Domestic (RM)</label>
                    <input type="number" id="t6_d" name="tier_6kg_price" class="form-input" value="0" step="0.01" min="0">
                  </div>
                  <div class="form-group">
                    <label for="t6_e">Export (USD)</label>
                    <input type="number" id="t6_e" name="tier_6kg_export_price" class="form-input" value="0" step="0.01" min="0">
                  </div>
                </div>
                <div style="border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;background:rgba(0,0,0,0.10);">
                  <div style="font-weight:700;font-size:.85rem;color:#00e5ff;text-align:center;margin-bottom:10px;">approx 10KG per unit</div>
                  <div class="form-group" style="margin-bottom:8px;">
                    <label for="t10_d">Domestic (RM)</label>
                    <input type="number" id="t10_d" name="tier_10kg_price" class="form-input" value="0" step="0.01" min="0">
                  </div>
                  <div class="form-group">
                    <label for="t10_e">Export (USD)</label>
                    <input type="number" id="t10_e" name="tier_10kg_export_price" class="form-input" value="0" step="0.01" min="0">
                  </div>
                </div>
              </div>
            </div>

            <div class="form-section-title">📦 Unit Stock</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:24px;">
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;background:rgba(0,0,0,0.12);text-align:center;">
                <div style="font-weight:700;font-size:.9rem;color:#00e5ff;margin-bottom:6px;">3kg</div>
                <input type="number" name="tier_3kg_stock" class="form-input" value="0" step="1" min="0" placeholder="0" style="text-align:center;font-weight:700;">
              </div>
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;background:rgba(0,0,0,0.12);text-align:center;">
                <div style="font-weight:700;font-size:.9rem;color:#00e5ff;margin-bottom:6px;">6kg</div>
                <input type="number" name="tier_6kg_stock" class="form-input" value="0" step="1" min="0" placeholder="0" style="text-align:center;font-weight:700;">
              </div>
              <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;background:rgba(0,0,0,0.12);text-align:center;">
                <div style="font-weight:700;font-size:.9rem;color:#00e5ff;margin-bottom:6px;">10kg</div>
                <input type="number" name="tier_10kg_stock" class="form-input" value="0" step="1" min="0" placeholder="0" style="text-align:center;font-weight:700;">
              </div>
            </div>

            <div class="form-section-title">Description</div>
            <div class="form-group">
              <textarea id="description" name="description" class="form-textarea" placeholder="Product description..."></textarea>
            </div>
          </div>

          <div class="action-buttons">
            <a href="/dashboard/stock/" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-save">➕ Add Product</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>


<?php require __DIR__ . '/../helpers/footer.php'; ?>
