<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$ministryId = resolve_ministry_id();
if (!$ministryId) {
    redirect_no_ministry();
}
$ministry = ministry_by_id($ministryId);

$offset = isset($_GET['w']) ? (int) $_GET['w'] : 0;
[$monday, $friday] = week_range('today', $offset);

$weekMeetings = meetings_between($monday, $friday, $ministryId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Weekly Schedule &middot; <?= e(fmt_date_short($monday)) ?>&ndash;<?= e(fmt_date_short($friday)) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
</head>
<body>
<div class="print-toolbar no-print">
  <button onclick="window.print()">🖨 Print</button>
</div>
<div class="sheet">
  <div class="doc-header">
    <?php if (!empty($ministry['minister_photo'])): ?>
    <span class="doc-header-photo-wrap">
      <img class="photo-fit" src="<?= BASE_URL ?>/<?= e($ministry['minister_photo']) ?>" alt="<?= e($ministry['minister_name'] ?? '') ?>"
           onerror="this.closest('.doc-header-photo-wrap').remove()">
    </span>
    <?php endif; ?>
    <div class="doc-header-text">
      <div class="ministry"><?= e($ministry['name'] ?? '') ?></div>
      <div class="minister"><?= e($ministry['minister_name'] ?? '') ?></div>
      <div class="title">Weekly Schedule</div>
    </div>
  </div>

  <div class="range-line">
    WEEKLY SCHEDULE &mdash; <?= strtoupper(e((new DateTime($monday))->format('jS F'))) ?> TO <?= strtoupper(e((new DateTime($friday))->format('jS F Y'))) ?>
  </div>

  <?php
  $cursor = new DateTime($monday);
  for ($i = 0; $i < 5; $i++):
      $ymd = $cursor->format('Y-m-d');
      $dayMeetings = array_values(array_filter($weekMeetings, fn($m) => meeting_covers_day($m, $ymd)));
  ?>
    <div class="day-title"><?= strtoupper(e($cursor->format('l, jS F Y'))) ?></div>
    <?php if (!$dayMeetings): ?>
      <div class="no-meetings"><?= e(empty_day_label($ymd)) ?></div>
    <?php else: ?>
      <table class="day-table">
        <?php foreach ($dayMeetings as $m): $isTrip = $m['event_type'] === 'trip'; $badge = trip_day_badge($m, $ymd); $team = array_column(meeting_team((int) $m['id']), 'name'); ?>
          <tr>
            <td class="time">
              <?php if ($isTrip): ?>
                TRIP<?php if ($badge): ?><br><span style="font-weight:400;font-size:9pt;"><?= e($badge) ?></span><?php endif; ?>
                <?php if ($m['start_time'] || $m['end_time']): ?>
                  <br><span style="font-weight:400;font-size:9pt;"><?= $m['start_time'] ? e(fmt_time($m['start_time'])) : '?' ?> &ndash; <?= $m['end_time'] ? e(fmt_time($m['end_time'])) : '?' ?></span>
                <?php endif; ?>
              <?php else: ?>
                <?= e(fmt_time($m['start_time'])) ?> &ndash; <?= e(fmt_time($m['end_time'])) ?>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-line"><strong><?= e($m['title']) ?></strong><?= $isTrip ? ' (' . e(fmt_date_range($m['meeting_date'], $m['end_date'] ?: $m['meeting_date'])) . ')' : '' ?></div>
              <div class="row-line">Venue: <?= e($m['venue']) ?></div>
              <?php if ($m['agenda']): ?><div class="row-line"><span class="agenda-label">Agenda:</span> <?= e($m['agenda']) ?></div><?php endif; ?>
              <?php if ($m['attendees']): ?><div class="row-line">Attendees: <?= e($m['attendees']) ?></div><?php endif; ?>
              <?php if (!empty($m['contact'])): ?><div class="row-line">Contact: <?= e($m['contact']) ?></div><?php endif; ?>
              <?php if ($team): ?><div class="row-line">Team: <?= e(join_names($team)) ?></div><?php endif; ?>
              <?php if (!empty($m['notes'])): ?><div class="row-line"><span class="agenda-label">Notes:</span> <?= e($m['notes']) ?></div><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  <?php $cursor->modify('+1 day'); endfor; ?>

  <div class="print-footer">Generated <?= date('jS F Y, g:i A') ?> &middot; <?= e(APP_NAME) ?></div>
</div>
</body>
</html>
