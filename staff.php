<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$ministryId = resolve_ministry_id();
if (!$ministryId) {
    redirect_no_ministry();
}

$page_title = 'Staff';
$active     = 'staff';

$stmt = db()->prepare('SELECT * FROM staff WHERE ministry_id = ? ORDER BY active DESC, name ASC');
$stmt->execute([$ministryId]);
$staff = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="page">
  <?php if ($flash = flash_get()): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['text']) ?></div>
  <?php endif; ?>

  <div class="page-head">
    <div>
      <h2>Staff</h2>
      <div class="sub">Manage who can be picked as the accompanying team on a meeting or trip.</div>
    </div>
    <a href="<?= BASE_URL ?>/staff_edit.php<?= ministry_qs($ministryId, '?') ?>" class="btn btn-gold">+ Add Staff</a>
  </div>

  <?php if (!$staff): ?>
    <div class="panel empty">No staff added yet. <a href="<?= BASE_URL ?>/staff_edit.php<?= ministry_qs($ministryId, '?') ?>">Add one now</a>.</div>
  <?php else: ?>
    <div class="panel panel-pad">
      <table class="staff-table">
        <thead>
          <tr><th>Name</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($staff as $s): ?>
            <tr>
              <td><?= e($s['name']) ?></td>
              <td><?= $s['active'] ? '<span class="pill pill-active">Active</span>' : '<span class="pill">Inactive</span>' ?></td>
              <td class="staff-actions">
                <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/staff_edit.php?id=<?= (int) $s['id'] ?><?= ministry_qs($ministryId) ?>">Edit</a>
                <a class="btn btn-danger btn-sm" href="<?= BASE_URL ?>/staff_delete.php?id=<?= (int) $s['id'] ?><?= ministry_qs($ministryId) ?>"
                   onclick="return confirm('Remove <?= e(addslashes($s['name'])) ?> from staff? This also removes them from any meetings they were added to.');">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
