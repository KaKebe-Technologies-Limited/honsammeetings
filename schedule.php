<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$ministryId = resolve_ministry_id();
if (!$ministryId) {
    redirect_no_ministry();
}

$page_title = 'Weekly Schedule';
$active     = 'schedule';

$offset = isset($_GET['w']) ? (int) $_GET['w'] : 0;
[$monday, $friday] = week_range('today', $offset);

$weekMeetings = meetings_between($monday, $friday, $ministryId);

$dueIds = array_column(meetings_in_reminder_window($ministryId), 'id');
$today  = date('Y-m-d');
$mqs    = ministry_qs($ministryId);

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
      <h2>Weekly Schedule</h2>
      <div class="sub"><?= e(fmt_date_long($monday)) ?> &ndash; <?= e(fmt_date_long($friday)) ?></div>
    </div>
    <div class="btn-row">
      <a href="<?= BASE_URL ?>/meeting_edit.php<?= ministry_qs($ministryId, '?') ?>" class="btn btn-gold">+ Add Meeting / Trip</a>
      <a href="<?= BASE_URL ?>/print_weekly.php?w=<?= $offset ?><?= $mqs ?>" target="_blank" class="btn btn-outline">🖨 Print This Week</a>
      <a href="<?= BASE_URL ?>/send_weekly_program.php?w=<?= $offset ?><?= $mqs ?>" class="btn btn-outline"
         onclick="return confirm('Email this week\'s program as a PDF to every registered user now?');">📧 Email Weekly Program (PDF)</a>
    </div>
  </div>

  <div class="week-nav">
    <a class="btn btn-outline btn-sm" href="?w=<?= $offset - 1 ?><?= $mqs ?>">← Previous week</a>
    <?php if ($offset !== 0): ?><a class="btn btn-outline btn-sm" href="?w=0<?= $mqs ?>">This week</a><?php endif; ?>
    <a class="btn btn-outline btn-sm" href="?w=<?= $offset + 1 ?><?= $mqs ?>">Next week →</a>
    <span class="range">Mon–Fri</span>
  </div>

  <?php
  $cursor = new DateTime($monday);
  for ($i = 0; $i < 5; $i++):
      $ymd = $cursor->format('Y-m-d');
      $dayMeetings = array_values(array_filter($weekMeetings, fn($m) => meeting_covers_day($m, $ymd)));
  ?>
    <div class="day-block">
      <div class="day-title"><?= e($cursor->format('l, jS F Y')) ?><?= $ymd === $today ? '  •  Today' : '' ?></div>
      <div class="day-body">
        <?php if (!$dayMeetings): ?>
          <div class="empty"><?= e(empty_day_label($ymd)) ?></div>
        <?php else: ?>
          <div style="padding:14px;">
          <?php foreach ($dayMeetings as $m):
              $soon = in_array($m['id'], $dueIds, true);
              $dayContext = $ymd;
              include __DIR__ . '/includes/meeting_card.php';
          endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php $cursor->modify('+1 day'); endfor; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
