<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title   = 'Users';
$admin_active = 'users';

$users = db()->query(
    'SELECT u.*, mi.name AS ministry_name
     FROM users u
     LEFT JOIN ministries mi ON mi.id = u.ministry_id
     ORDER BY u.role ASC, mi.name ASC, u.full_name ASC'
)->fetchAll();

require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-head">
  <div>
    <h1>Users</h1>
    <div class="sub"><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?> across every ministry — password resets happen only here.</div>
  </div>
  <a href="<?= BASE_URL ?>/admin_user_edit.php" class="btn btn-yellow">+ Add User</a>
</div>

<?php if (!$users): ?>
  <div class="admin-panel admin-panel-pad"><div class="empty">No users found.</div></div>
<?php else: ?>
  <div class="admin-panel admin-panel-pad">
    <table class="admin-table">
      <thead>
        <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Ministry</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= e($u['full_name']) ?></td>
            <td><?= e($u['username']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="pill <?= $u['role'] === 'super_admin' ? 'pill-super' : 'pill-office' ?>"><?= $u['role'] === 'super_admin' ? 'Super Admin' : 'Office Admin' ?></span></td>
            <td><?= e($u['ministry_name'] ?? '—') ?></td>
            <td class="admin-actions">
              <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin_user_edit.php?id=<?= (int) $u['id'] ?>">Edit / Reset Password</a>
              <?php if ((int) $u['id'] !== (int) current_user()['id']): ?>
                <a class="btn btn-red btn-sm" href="<?= BASE_URL ?>/admin_user_delete.php?id=<?= (int) $u['id'] ?>"
                   onclick="return confirm('Remove <?= e(addslashes($u['full_name'])) ?>? This cannot be undone.');">Delete</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
