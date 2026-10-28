<?php $sq = trim((string)($_GET['q'] ?? '')); $all = $sq !== '' ? search_users($sq) : ($allUsers ?? all_users()); $extraHead = admin_css(); $pageView = 'dashboard_users'; ?>
<div class="page-header"><div class="container"><h1>👥 User Management</h1><p>All registered market users</p></div></div>
<div class="container"><div class="admin-layout">
  <div class="admin-sidebar"><div class="admin-sidebar-header"><h3 style="color:var(--text);font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:1px">⚙ Management</h3></div><?= admin_sidebar('dashboard_users') ?></div>
  <div>
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 style="font-weight:700;font-size:.95rem">👥 Users (<?= count($all) ?>)</h3>
        <form method="get" action="<?= url_for('dashboard_users') ?>" style="display:flex;gap:.5rem;align-items:center">
          <input type="hidden" name="page" value="dashboard_users">
          <input type="text" name="q" class="admin-search" placeholder="Search users…" value="<?= e($sq) ?>" style="width:220px">
          <button type="submit" class="btn btn-outline btn-sm">🔍</button>
          <?php if ($sq !== ''): ?><a href="<?= url_for('dashboard_users') ?>" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
        </form>
      </div>
      <table><thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Wallet</th><th>Status</th><th>Joined</th><th>Last Login</th></tr></thead><tbody>
      <?php foreach ($all as $u): ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td style="font-weight:600"><?= e($u['username'] ?? '—') ?></td>
          <td><?= e($u['email'] ?? '—') ?></td>
          <td><?= e($u['full_name'] ?? '—') ?></td>
          <td><span class="badge badge-<?= ($u['role'] ?? 'customer') === 'admin' ? 'success' : 'info' ?>"><?= e($u['role'] ?? 'customer') ?></span></td>
          <td style="font-weight:600;color:var(--brand-light)">RM <?= e(formatted_money((float) ($u['wallet_balance'] ?? 0))) ?></td>
          <td><?= !empty($u['is_active']) ? '<span style="color:var(--brand-light)">Active</span>' : '<span style="color:var(--coral)">Inactive</span>' ?></td>
          <td><?= $u['created_at'] ? date('d M Y', strtotime($u['created_at'])) : '—' ?></td>
          <td><?= $u['last_login_at'] ? date('d M Y', strtotime($u['last_login_at'])) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>
</div></div>
