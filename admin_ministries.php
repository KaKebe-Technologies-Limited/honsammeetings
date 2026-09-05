<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title   = 'Ministries';
$admin_active = 'ministries';

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT mi.*,
        (SELECT COUNT(*) FROM users u WHERE u.ministry_id = mi.id) AS user_count,
        (SELECT COUNT(*) FROM meetings m WHERE m.ministry_id = mi.id) AS meeting_count
        FROM ministries mi';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE mi.name LIKE ? OR mi.minister_name LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$sql .= ' ORDER BY mi.name ASC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$ministries = $stmt->fetchAll();

require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-head">
  <div>
    <h1>Ministries</h1>
    <div class="sub"><?= count($ministries) ?> office<?= count($ministries) === 1 ? '' : 's' ?> on the platform</div>
  </div>
  <a href="<?= BASE_URL ?>/admin_ministry_edit.php" class="btn btn-yellow">+ Add Ministry</a>
</div>

<?php if (!$ministries): ?>
  <div class="admin-panel admin-panel-pad"><div class="empty">No ministries found. <a href="<?= BASE_URL ?>/admin_ministry_edit.php">Add one now</a>.</div></div>
<?php else: ?>
  <div class="admin-panel admin-panel-pad">
    <table class="admin-table">
      <thead>
        <tr><th>Ministry</th><th>Minister</th><th>Users</th><th>Meetings</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($ministries as $m): ?>
          <tr>
            <td><?= e($m['name']) ?></td>
            <td><?= e($m['minister_name']) ?></td>
            <td><?= (int) $m['user_count'] ?></td>
            <td><?= (int) $m['meeting_count'] ?></td>
            <td class="admin-actions">
              <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/index.php?ministry_id=<?= (int) $m['id'] ?>">View</a>
              <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin_ministry_edit.php?id=<?= (int) $m['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
