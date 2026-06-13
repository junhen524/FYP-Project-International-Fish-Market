<?php
// ── Check admin marker (stored in PHPSESSID by admin.php on login) ──
$__uNavAdminMarker = $_SESSION['ifm_admin_marker'] ?? null;
$__uNavIsAdmin = $__uNavAdminMarker !== null;
$__uNavUser = intl_current_user();
$__uNavIsRestaurant = isset($_SESSION['ifm_restaurant_id']);
$__uNavBalance = 0;
$__uNavName = 'User';

if ($__uNavIsAdmin) {
    $__uNavName = $__uNavAdminMarker['name'] ?? 'Admin';
} elseif ($__uNavUser) {
    if ($__uNavIsRestaurant) {
        $__uNavName = $__uNavUser['company_name'] ?? $__uNavUser['full_name'] ?? $__uNavUser['username'] ?? 'User';
        $__uNavBalance = (float)dbGetValue("SELECT COALESCE(balance, 0) FROM export_wallets WHERE restaurant_id = ?", [$_SESSION['ifm_restaurant_id']]);
    } else {
        $__uNavName = $__uNavUser['full_name'] ?? $__uNavUser['username'] ?? 'User';
        $__uNavBalance = (float)dbGetValue("SELECT COALESCE(balance, 0) FROM export_wallets WHERE user_id = ?", [$__uNavUser['id']]);
    }
}
?>
<?php if ($__uNavIsAdmin): ?>
<div style="position:relative;display:inline-block" class="user-dropdown">
  <button style="display:flex;align-items:center;gap:6px;border:1px solid #e2e8f0;border-radius:8px;padding:4px 10px;cursor:pointer;background:transparent;transition:all 0.2s;font-family:inherit;font-size:11px" class="user-dropdown-btn" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'" title="Admin Panel">
    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#f59e0b"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    <span style="font-weight:600;color:#475569;letter-spacing:0.5px;text-transform:uppercase;font-size:9px"><?= e($__uNavName) ?></span>
    <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#94a3b8"><polyline points="6 9 12 15 18 9"/></svg>
  </button>
  <div style="position:absolute;right:0;top:100%;margin-top:6px;width:176px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.08);padding:6px 0;z-index:999;display:none;opacity:0;transform:translateY(-4px);transition:opacity 0.15s,transform 0.15s" class="user-dropdown-menu">
    <a href="<?= $__ifmBasePath ?>admin.php?logout=1" style="display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:11px;letter-spacing:0.5px;color:#ef4444;text-decoration:none;transition:all 0.1s" onmouseover="this.style.background='#fef2f2';this.style.color='#dc2626'" onmouseout="this.style.background='transparent';this.style.color='#ef4444'">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#fca5a5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span>
    </a>
  </div>
</div>
<?php elseif ($__uNavUser): ?>
<a href="<?= url_for('wallet') ?>" style="display:flex;align-items:center;gap:4px;border:1px solid #e2e8f0;border-radius:8px;padding:4px 10px;text-decoration:none;transition:all 0.2s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#10b981"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
  <span style="font-weight:600;color:#059669;letter-spacing:0.5px;text-transform:uppercase;font-size:9px">$<?= number_format($__uNavBalance, 2) ?></span>
</a>
<div style="position:relative;display:inline-block" class="user-dropdown">
  <button style="display:flex;align-items:center;gap:6px;border:1px solid #e2e8f0;border-radius:8px;padding:4px 10px;cursor:pointer;background:transparent;transition:all 0.2s;font-family:inherit;font-size:11px" class="user-dropdown-btn" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'" title="My Account">
    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#94a3b8"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span style="font-weight:600;color:#475569;letter-spacing:0.5px;text-transform:uppercase;font-size:9px"><?= e($__uNavName) ?></span>
    <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#94a3b8"><polyline points="6 9 12 15 18 9"/></svg>
  </button>
  <div style="position:absolute;right:0;top:100%;margin-top:6px;width:176px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.08);padding:6px 0;z-index:999;display:none;opacity:0;transform:translateY(-4px);transition:opacity 0.15s,transform 0.15s" class="user-dropdown-menu">
    <a href="<?= url_for('profile') ?>" style="display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:11px;letter-spacing:0.5px;color:#475569;text-decoration:none;transition:all 0.1s" onmouseover="this.style.background='#f8fafc';this.style.color='#0f172a'" onmouseout="this.style.background='transparent';this.style.color='#475569'">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#94a3b8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span>
    </a>
    <a href="<?= url_for('orders') ?>" style="display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:11px;letter-spacing:0.5px;color:#475569;text-decoration:none;transition:all 0.1s" onmouseover="this.style.background='#f8fafc';this.style.color='#0f172a'" onmouseout="this.style.background='transparent';this.style.color='#475569'">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#94a3b8"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg><span>My Orders</span>
    </a>
    <a href="<?= url_for('favorites') ?>" style="display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:11px;letter-spacing:0.5px;color:#475569;text-decoration:none;transition:all 0.1s" onmouseover="this.style.background='#f8fafc';this.style.color='#0f172a'" onmouseout="this.style.background='transparent';this.style.color='#475569'">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#eab308"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg><span>My Favorites</span>
    </a>
    <hr style="margin:4px 0;border:none;border-top:1px solid #f1f5f9">
    <a href="<?= url_for('logout') ?>" style="display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:11px;letter-spacing:0.5px;color:#ef4444;text-decoration:none;transition:all 0.1s" onmouseover="this.style.background='#fef2f2';this.style.color='#dc2626'" onmouseout="this.style.background='transparent';this.style.color='#ef4444'">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#fca5a5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span>
    </a>
  </div>
</div>
<?php else: ?>
<a href="<?= url_for('login') ?>" style="display:flex;align-items:center;gap:4px;border:1px solid #e2e8f0;border-radius:8px;padding:4px 10px;text-decoration:none;transition:all 0.2s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#94a3b8"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
  <span style="font-weight:600;color:#475569;letter-spacing:0.5px;text-transform:uppercase;font-size:9px">Login</span>
</a>
<?php endif; ?>
<script>
(function(){
  function setupDropdown() {
    document.querySelectorAll('.user-dropdown').forEach(function(dropdown) {
      var btn = dropdown.querySelector('.user-dropdown-btn');
      var menu = dropdown.querySelector('.user-dropdown-menu');
      if (!btn || !menu) return;
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelectorAll('.user-dropdown-menu').forEach(function(m) { if (m !== menu) m.style.display = 'none'; });
        var isOpen = menu.style.display === 'block';
        menu.style.display = isOpen ? 'none' : 'block';
        menu.style.opacity = isOpen ? '0' : '1';
        menu.style.transform = isOpen ? 'translateY(-4px)' : 'translateY(0)';
      });
    });
    document.addEventListener('click', function() {
      document.querySelectorAll('.user-dropdown-menu').forEach(function(m) { m.style.display = 'none'; m.style.opacity = '0'; m.style.transform = 'translateY(-4px)'; });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupDropdown);
  else setupDropdown();
})();
</script>