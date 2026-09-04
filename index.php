<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$page_title = 'Dashboard';
$active     = 'dashboard';

[$monday, $sunday] = week_range();
$thisWeek  = meetings_between($monday, $sunday);
$dueSoon   = meetings_in_reminder_window();
$dueIds    = array_column($dueSoon, 'id');
$upcoming  = upcoming_meetings(8);

$totalUpcoming = (int) db()->query('SELECT COUNT(*) FROM meetings WHERE IFNULL(end_date, meeting_date) >= CURDATE()')->fetchColumn();

require __DIR__ . '/includes/header.php';
?>
<div class="page">
  <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Saved successfully.</div>
  <?php endif; ?>

  <div class="page-head">
    <div>
      <h2>Welcome, <?= e(current_user()['full_name'] ?? current_user()['username']) ?></h2>
      <div class="sub">Here is what's on the Minister's calendar.</div>
    </div>
    <div class="btn-row">
      <a href="<?= BASE_URL ?>/meeting_edit.php" class="btn btn-gold">+ Add Meeting / Trip</a>
      <a href="<?= BASE_URL ?>/print_weekly.php" target="_blank" class="btn btn-outline">🖨 Print Weekly Schedule</a>
    </div>
  </div>

  <div class="stat-row">
    <div class="stat accent">
      <div class="num"><?= count($thisWeek) ?></div>
      <div class="label">Meetings/trips this week</div>
    </div>
    <div class="stat <?= count($dueSoon) ? 'alert' : '' ?>">
      <div class="num"><?= count($dueSoon) ?></div>
      <div class="label">Due for a reminder</div>
    </div>
    <div class="stat">
      <div class="num"><?= $totalUpcoming ?></div>
      <div class="label">Total upcoming</div>
    </div>
  </div>

  <?php if ($dueSoon): ?>
  <div class="alert alert-info">
    🔔 <strong><?= count($dueSoon) ?></strong> item<?= count($dueSoon) === 1 ? '' : 's' ?> currently inside their reminder window.
  </div>
  <?php endif; ?>

  <div class="page-head" style="margin-bottom:10px;">
    <h2 style="font-size:18px;">Upcoming</h2>
    <a href="<?= BASE_URL ?>/schedule.php" class="btn btn-outline btn-sm">View Weekly Schedule →</a>
  </div>

  <?php if (!$upcoming): ?>
    <div class="panel empty">Nothing upcoming. <a href="<?= BASE_URL ?>/meeting_edit.php">Add one now</a>.</div>
  <?php else: ?>
    <?php foreach ($upcoming as $m):
        $soon = in_array($m['id'], $dueIds, true);
        $dayContext = null;
        include __DIR__ . '/includes/meeting_card.php';
    endforeach; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
