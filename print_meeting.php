<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$m = find_meeting($id);

if (!$m) {
    header('Location: ' . BASE_URL . '/meetings.php');
    exit;
}
$isTrip = $m['event_type'] === 'trip';
$team = array_column(meeting_team($id), 'name');
$ministry = ministry_by_id((int) $m['ministry_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meeting Details &middot; <?= e($m['title']) ?></title>
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
      <div class="title"><?= $isTrip ? 'Trip Detail' : 'Meeting Detail' ?></div>
    </div>
  </div>

  <div class="meeting-detail">
    <h2 style="margin-bottom:4px;"><?= e($m['title']) ?><?= $isTrip ? ' <span style="font-size:10pt;font-weight:600;">(Trip)</span>' : '' ?></h2>
    <div class="text-muted" style="margin-bottom:6px;">
      <?= $isTrip ? e(fmt_date_range($m['meeting_date'], $m['end_date'] ?: $m['meeting_date'])) : e(fmt_date_long($m['meeting_date'])) ?>
    </div>

    <dl>
      <?php if ($isTrip): ?>
        <dt>Start</dt><dd><?= e(fmt_date_long($m['meeting_date'])) ?><?= $m['start_time'] ? ' at ' . e(fmt_time($m['start_time'])) : '' ?></dd>
        <dt>End</dt><dd><?= e(fmt_date_long($m['end_date'] ?: $m['meeting_date'])) ?><?= $m['end_time'] ? ' at ' . e(fmt_time($m['end_time'])) : '' ?></dd>
        <dt>Length</dt><dd><?= e(trip_length_label($m)) ?></dd>
        <dt>Venue</dt><dd><?= e($m['venue']) ?></dd>
      <?php else: ?>
        <dt>Date</dt><dd><?= e(fmt_date_long($m['meeting_date'])) ?></dd>
        <dt>Time</dt><dd><?= e(fmt_time($m['start_time'])) ?> &ndash; <?= e(fmt_time($m['end_time'])) ?> (<?= duration_label($m['start_time'], $m['end_time']) ?>)</dd>
        <dt>Venue</dt><dd><?= e($m['venue']) ?></dd>
      <?php endif; ?>
      <?php if ($m['attendees']): ?><dt>Attendees</dt><dd><?= e($m['attendees']) ?></dd><?php endif; ?>
      <?php if (!empty($m['contact'])): ?><dt>Contact</dt><dd><?= e($m['contact']) ?></dd><?php endif; ?>
      <?php if ($team): ?><dt>Accompanying Team</dt><dd><?= e(join_names($team)) ?></dd><?php endif; ?>
    </dl>

    <?php if ($m['agenda']): ?>
      <div style="margin-top:14px;">
        <dt style="font-weight:700;">Agenda / Description</dt>
        <div class="agenda-block"><?= e($m['agenda']) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($m['notes'])): ?>
      <div style="margin-top:14px;">
        <dt style="font-weight:700;">Notes</dt>
        <div class="agenda-block notes-block"><?= e($m['notes']) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="print-footer">Generated <?= date('jS F Y, g:i A') ?> &middot; <?= e(APP_NAME) ?></div>
</div>
</body>
</html>
