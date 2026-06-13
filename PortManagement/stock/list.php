<?php
require_once __DIR__ . '/../helpers/functions.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$search_q = $_GET['q'] ?? '';
$category_filter = $_GET['category'] ?? '';
$warehouse_filter = 0;
$delete_id = (int)($_POST['delete_id'] ?? 0);

if ($delete_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        dbExecute("UPDATE product SET is_active = FALSE WHERE id = ?", [$delete_id]);
        redirect('/dashboard/stock/');
        exit;
    } catch (Exception $e) {}
}

$products = []; $categories = []; $total_products = 0; $low_stock = 0; $out_of_stock = 0;
try {

    $sql = "SELECT p.id, p.name, p.category, p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price, p.image_url,
            p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock
            FROM product p
            WHERE p.is_active = TRUE";
    $params = [];
    $wheres = [];
    if ($search_q) { $wheres[] = "LOWER(p.name) LIKE LOWER(?)"; $params[] = "%$search_q%"; }
    if ($category_filter) { $wheres[] = "p.category = ?"; $params[] = $category_filter; }
    if ($wheres) $sql .= " AND " . implode(' AND ', $wheres);
    $sql .= " ORDER BY p.category, p.name";
    $products = dbGetAll($sql, $params);
    $categories = dbGetAll("SELECT DISTINCT category as cat FROM product WHERE is_active = TRUE ORDER BY cat");
    $total_products = count($products);

    $wh_dist = [];

    $tierInv = [];

    $productData = [];
    foreach ($products as $p) {
        $cat = $p['category'] ?? 'other';
        if (!isset($productData[$cat])) $productData[$cat] = [];
        $productData[$cat][] = $p;
    }

    // Build tier inventory lookup
    $tierInv = [];
    foreach ($products as $p) {
        $pid = $p['id'];
        $sum = (int)($p['tier_3kg_stock'] ?? 0) + (int)($p['tier_6kg_stock'] ?? 0) + (int)($p['tier_10kg_stock'] ?? 0);
        $p['_total_qty'] = $sum;
        $tierInv[$pid] = [
            'SMALL'  => (int)($p['tier_3kg_stock'] ?? 0),
            'MEDIUM' => (int)($p['tier_6kg_stock'] ?? 0),
            'LARGE'  => (int)($p['tier_10kg_stock'] ?? 0),
        ];
    }

    $low_stock = count(array_filter($products, function($p) {
        $q = (int)($p['tier_3kg_stock'] ?? 0) + (int)($p['tier_6kg_stock'] ?? 0) + (int)($p['tier_10kg_stock'] ?? 0);
        return $q <= 50 && $q > 20;
    }));
    $out_of_stock = count(array_filter($products, function($p) {
        $q = (int)($p['tier_3kg_stock'] ?? 0) + (int)($p['tier_6kg_stock'] ?? 0) + (int)($p['tier_10kg_stock'] ?? 0);
        return $q <= 20;
    }));
} catch (Exception $e) { $productData = []; $tierInv = []; }

function renderTierCell($tierData, $tierKey) {
    $qty = is_array($tierData) ? (int)($tierData[$tierKey] ?? 0) : 0;
    if ($qty <= 0) {
        return '<span style="color:var(--muted);font-size:12px;">—</span>';
    }
    $color = $tierKey === 'SMALL' ? '#34d399' : ($tierKey === 'MEDIUM' ? '#fbbf24' : '#ef4444');
    $html = '<div>';
    $html .= '<span style="font-size:18px;font-weight:800;color:' . $color . ';">' . $qty . '</span>';
    $html .= '<span style="font-size:10px;color:var(--muted);margin-left:4px;">Unit</span>';
    $html .= '</div>';
    return $html;
}

$title = 'Stock Management';
$extra_head = '';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.stock-table th { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); padding: 12px; border-bottom: 1px solid var(--border); }
.stock-table td { padding: 14px 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
.stock-table tr { transition: all 0.3s ease; }
.stock-table tr:hover td { background: rgba(255,255,255,0.06); }
.stock-table tr.deleting td { opacity: 0.5; }
.image-preview { width: 60px; height: 60px; border-radius: 10px; overflow: hidden; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; border: none; }
.image-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
.b-critical { background: rgba(239,68,68,0.15); color: #ef4444; }
.b-low { background: rgba(253,186,116,0.15); color: #fbbf24; }
.b-ok { background: rgba(52,211,153,0.15); color: var(--ok); }
.edit-btn { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none; background: rgba(96,165,250,0.12); color: #60a5fa; border: 1px solid rgba(96,165,250,0.2); transition: .12s; white-space: nowrap; }
.edit-btn:hover { background: rgba(96,165,250,0.2); }
.delete-btn { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none; background: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); transition: .12s; white-space: nowrap; cursor: pointer; }
.delete-btn:hover { background: rgba(239,68,68,0.2); }
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); display: none; justify-content: center; align-items: center; z-index: 1000; }
.modal-overlay.active { display: flex; }
.modal-content { background: #0f1a2e; border: 1px solid var(--border); border-radius: 16px; padding: 24px; max-width: 400px; width: 90%; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.modal-title { font-size: 18px; font-weight: 700; margin: 0; }
.modal-close { background: none; border: none; color: var(--muted); font-size: 24px; cursor: pointer; padding: 0; line-height: 1; }
.modal-close:hover { color: var(--text); }
.btn-cancel { padding: 10px 24px; border-radius: 10px; border: 1px solid var(--border); background: transparent; color: var(--text); font-weight: 700; cursor: pointer; transition: .12s; }
.btn-cancel:hover { background: rgba(255,255,255,0.06); }
.btn-delete-modal { padding: 10px 24px; border-radius: 10px; border: none; background: #ef4444; color: #fff; font-weight: 700; cursor: pointer; transition: .12s; }
.btn-delete-modal:hover { filter: brightness(1.1); }
</style>
<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🏭 Warehouse</div>
    <a class="dash-sidebar-item active" href="/dashboard/stock/">📋 Stock</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/port/1/">🏭 Port Klang</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/port/2/">🏭 Penang Port</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/port/3/">🏭 Johor Port</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/port/4/">🏭 Kuantan Port</a>
    <div style="border-top:1px solid var(--border);margin:8px 14px;"></div>
    <a class="dash-sidebar-item" href="/dashboard/stock/">📦 All</a>
    <a class="dash-sidebar-item <?= $category_filter === 'fish' ? 'active' : '' ?>" href="/dashboard/stock/?category=fish">🐟 Fish</a>
    <a class="dash-sidebar-item <?= $category_filter === 'shellfish' ? 'active' : '' ?>" href="/dashboard/stock/?category=shellfish">🦪 Shellfish</a>
    <a class="dash-sidebar-item <?= $category_filter === 'crustacean' ? 'active' : '' ?>" href="/dashboard/stock/?category=crustacean">🦀 Crustacean</a>
    <a class="dash-sidebar-item <?= $category_filter === 'cephalopod' ? 'active' : '' ?>" href="/dashboard/stock/?category=cephalopod">🐙 Cephalopod</a>
    <a class="dash-sidebar-item <?= $category_filter === 'mollusc' ? 'active' : '' ?>" href="/dashboard/stock/?category=mollusc">🦐 Mollusc</a>
    <div style="border-top:1px solid var(--border);margin:8px 14px;"></div>
    <a class="dash-sidebar-item" href="/dashboard/stock/movements/">📊 Movements</a>
    <a class="dash-sidebar-item" href="/dashboard/stock/add/">➕ Add Product</a>
  </nav>

  <div class="dash-content">
    <div class="card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
          <h2 style="margin:0;">📦 Stock Management</h2>
          <p class="subtle" style="margin:4px 0 0;">Monitor and manage inventory across all products</p>
        </div>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <div style="display:flex;gap:8px;align-items:center;">
            <span style="font-size:12px;color:var(--muted);">Category:</span>
            <select id="category-filter" onchange="filterChange()" style="padding:8px 12px;border-radius:8px;background:rgba(255,255,255,0.06);border:1px solid var(--border);color:var(--text);font-size:12px;">
              <option value="">All</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= e($c['cat']) ?>" <?= $category_filter === $c['cat'] ? 'selected' : '' ?>>
                <?php
                  $cat = $c['cat'] ?? '';
                  $catIcon = '📦';
                  if ($cat === 'fish') $catIcon = '🐟';
                  elseif ($cat === 'shellfish') $catIcon = '🦪';
                  elseif ($cat === 'crustacean') $catIcon = '🦀';
                  elseif ($cat === 'cephalopod') $catIcon = '🐙';
                  elseif ($cat === 'mollusc') $catIcon = '🦐';
                  echo $catIcon . ' ' . ucfirst($cat);
                ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="pill" style="padding:8px 14px;"><span style="color:#ef4444;">🔴 Critical</span></div>
          <div class="pill" style="padding:8px 14px;"><span style="color:#fbbf24;">🟡 Low Stock</span></div>
          <div class="pill" style="padding:8px 14px;"><span style="color:#34d399;">🟢 In Stock</span></div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px;padding:12px 16px;">
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between;">
        <form method="get" style="display:flex;gap:6px;align-items:center;">
          <?php if ($category_filter): ?><input type="hidden" name="category" value="<?= e($category_filter) ?>"><?php endif; ?>
          <input type="search" name="q" placeholder="Search products..." value="<?= e($search_q) ?>" class="form-control" style="width:200px;">
          <button type="submit" class="btn btn-ghost" style="padding:8px 12px;font-size:12px;font-weight:700;">🔍</button>
          <?php if ($search_q): ?>
          <a href="/dashboard/stock/<?= $category_filter ? '?category='.urlencode($category_filter) : '' ?>" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="card" style="overflow-x:auto;">
      <table class="stock-table" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th style="width:40px;"></th>
            <th>Product</th>
            <th>Category</th>
            <th>Price (RM)</th>
            <th>Units (kg/unit)</th>
            <th style="width:160px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($productData)): ?>
          <?php
            $catOrder = ['fish','shellfish','crustacean','cephalopod','mollusc'];
            $catIcons = ['fish'=>'🐟','shellfish'=>'🦪','crustacean'=>'🦀','cephalopod'=>'🐙','mollusc'=>'🦐'];
            $catLabels = ['fish'=>'Fish','shellfish'=>'Shellfish','crustacean'=>'Crab','cephalopod'=>'Cephalopod','mollusc'=>'Prawn'];
            foreach ($catOrder as $catKey):
              if (!isset($productData[$catKey])) continue;
          ?>
          <tr style="background:rgba(45,212,191,0.08);">
            <td colspan="9" style="padding:10px 16px;font-size:13px;font-weight:700;">
              <span style="font-size:16px;margin-right:8px;"><?= e($catIcons[$catKey] ?? '📦') ?></span>
              <?= e($catLabels[$catKey] ?? ucfirst($catKey)) ?>
            </td>
          </tr>
          <?php foreach ($productData[$catKey] as $p): ?>
          <?php
            $qty = (int)($p['tier_3kg_stock'] ?? 0) + (int)($p['tier_6kg_stock'] ?? 0) + (int)($p['tier_10kg_stock'] ?? 0);
            if ($qty < 20) { $stCls = 'b-critical'; $stLabel = 'Critical'; }
            elseif ($qty <= 50) { $stCls = 'b-low'; $stLabel = 'Low'; }
            else { $stCls = 'b-ok'; $stLabel = 'In Stock'; }

            $pid = $p['id'];
            $pname = $p['name'] ?? '';
            $unitTiers = getProductTiers($pid);
          ?>
          <tr data-product-id="<?= e($p['id']) ?>">
            <td>
              <div class="image-preview">
                <?php if (!empty($p['image_url'])): ?>
                  <img src="<?= e(imgUrl($p['image_url'])) ?>" alt="<?= e($p['name'] ?? '') ?>" loading="lazy">
                <?php else: ?>
                  <span style="opacity:0.3;">📦</span>
                <?php endif; ?>
              </div>
            </td>
            <td><strong><?= e($pname) ?></strong></td>
            <td style="color:var(--muted);"><?= e(ucfirst($p['category'] ?? '')) ?: '—' ?></td>
            <td style="font-weight:600;">RM<?= number_format($p['tier_3kg_price'] ?? $p['tier_6kg_price'] ?? $p['tier_10kg_price'] ?? 0, 0) ?></td>
            <td><?= renderTierBadge($unitTiers) ?></td>
            <td><span class="badge-stock <?= e($stCls) ?>" style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;"><?= e($stLabel) ?></span></td>
            <td>
              <div style="display:flex;gap:6px;">
                <a href="/dashboard/stock/edit/?id=<?= e($p['id']) ?>" class="edit-btn">✏️ Edit</a>
                <button type="button" class="delete-btn" onclick="showDeleteModal(<?= e($p['id']) ?>, '<?= e(addslashes($pname)) ?>')">🗑️</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endforeach; ?>
          <?php else: ?>
          <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted);">No products found. <a href="/dashboard/stock/add/" style="color:var(--brand);text-decoration:underline;">Add a product first.</a></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">🗑️ Delete Product</h3>
      <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
    </div>
    <div class="modal-body">
      <p>Are you sure you want to delete <strong id="deleteProductName"></strong>?</p>
      <p style="color: var(--muted); margin-top: 8px; font-size: 13px;">This action cannot be undone.</p>
    </div>
    <div class="modal-footer" style="display:flex;gap:12px;justify-content:flex-end;">
      <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
      <form method="POST" id="deleteForm" style="display:inline;">
        <input type="hidden" name="delete_id" id="deleteId" value="">
        <button type="submit" class="btn-delete-modal">Delete</button>
      </form>
    </div>
  </div>
</div>

<script>
let currentProductId = null;
function showDeleteModal(id, name) {
  currentProductId = id;
  document.getElementById('deleteProductName').textContent = name;
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteModal').classList.add('active');
}
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('active');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});

function filterChange() {
  const cat = document.getElementById('category-filter').value;
  const params = [];
  if (cat) params.push('category=' + encodeURIComponent(cat));
  window.location = '/dashboard/stock/' + (params.length ? '?' + params.join('&') : '');
}
</script>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
