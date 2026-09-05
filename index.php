<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) {
    require __DIR__ . '/login.php';
    exit;
}

$ministryId = resolve_ministry_id();
if (!$ministryId) {
    redirect_no_ministry();
}

$page_title = 'Dashboard';
$active     = 'dashboard';

[$monday, $friday] = week_range();
$thisWeek  = meetings_between($monday, $friday, $ministryId);
$dueSoon   = meetings_in_reminder_window($ministryId);
$dueIds    = array_column($dueSoon, 'id');

$today = date('Y-m-d');
$now   = date('H:i:s');
$thisWeekAhead = array_values(array_filter($thisWeek, function ($m) use ($today, $now) {
    $endsOn = $m['end_date'] ?: $m['meeting_date'];
    if ($endsOn !== $today) {
        return $endsOn >= $today;
    }
    return !$m['end_time'] || $m['end_time'] >= $now;
}));

$stmt = db()->prepare('SELECT COUNT(*) FROM meetings WHERE ministry_id = ? AND IFNULL(end_date, meeting_date) >= CURDATE()');
$stmt->execute([$ministryId]);
$totalUpcoming = (int) $stmt->fetchColumn();

require __DIR__ . '/includes/header.php';
?>
<div class="page">
  <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Saved successfully.</div>
  <?php endif; ?>
  <?php if ($flash = flash_get()): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['text']) ?></div>
  <?php endif; ?>

  <div class="page-head">
    <div>
      <h2>Welcome, <?= e(current_user()['full_name'] ?? current_user()['username']) ?></h2>
      <div class="sub">Here is what's on the Minister's calendar.</div>
    </div>
    <div class="btn-row">
      <a href="<?= BASE_URL ?>/meeting_edit.php<?= ministry_qs($ministryId, '?') ?>" class="btn btn-gold">+ Add Meeting / Trip</a>
      <a href="<?= BASE_URL ?>/print_weekly.php<?= ministry_qs($ministryId, '?') ?>" target="_blank" class="btn btn-outline">🖨 Print Weekly Schedule</a>
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
    <div>
      <h2 style="font-size:18px;">This Week</h2>
      <div class="sub"><?= e(fmt_date_medium($monday)) ?> &ndash; <?= e(fmt_date_medium($friday)) ?></div>
    </div>
    <a href="<?= BASE_URL ?>/schedule.php<?= ministry_qs($ministryId, '?') ?>" class="btn btn-outline btn-sm">View Weekly Schedule →</a>
  </div>

  <?php if (!$thisWeekAhead): ?>
    <div class="panel empty">Nothing left on the calendar this week. <a href="<?= BASE_URL ?>/meeting_edit.php<?= ministry_qs($ministryId, '?') ?>">Add one now</a>.</div>
  <?php else: ?>
    <?php foreach ($thisWeekAhead as $m):
        $soon = in_array($m['id'], $dueIds, true);
        $dayContext = null;
        include __DIR__ . '/includes/meeting_card.php';
    endforeach; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
