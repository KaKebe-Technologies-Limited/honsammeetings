<?php
/**
 * Renders one meeting/trip card.
 * Expects: $m (meeting row).
 * Optional (set by caller before each include, reset every iteration):
 *   $soon       bool    highlight as "due soon"
 *   $dayContext ?string Y-m-d — when set for a trip, shows "Day X of Y" for that date
 */
$soon = $soon ?? false;
$dayContext = $dayContext ?? null;
$isTrip = $m['event_type'] === 'trip';
$dayBadge = $isTrip ? trip_day_badge($m, $dayContext ?? $m['meeting_date']) : null;
$team = array_column(meeting_team((int) $m['id']), 'name');
$hasClash = meeting_has_clash($m);
?>
<div class="meeting-card <?= $soon ? 'soon' : '' ?>">
  <div class="meeting-time">
    <?php if ($isTrip): ?>
      <span class="date"><?= e(fmt_date_range($m['meeting_date'], $m['end_date'] ?: $m['meeting_date'])) ?></span>
      <?php if ($m['start_time'] && $m['end_time']): ?>
        <?= e(fmt_time($m['start_time'])) ?> – <?= e(fmt_time($m['end_time'])) ?>
      <?php elseif ($m['start_time']): ?>
        Start <?= e(fmt_time($m['start_time'])) ?>
      <?php elseif ($m['end_time']): ?>
        End <?= e(fmt_time($m['end_time'])) ?>
      <?php endif; ?>
    <?php else: ?>
      <span class="date"><?= e(fmt_date_short($m['meeting_date'])) ?></span>
      <?= e(fmt_time($m['start_time'])) ?> – <?= e(fmt_time($m['end_time'])) ?>
    <?php endif; ?>
    <span class="dur">⏱ <?= e(event_span_label($m)) ?></span>
  </div>
  <div class="meeting-body">
    <h3>
      <?= e($m['title']) ?>
      <?php if ($isTrip): ?><span class="pill pill-trip">💼 Trip</span><?php endif; ?>
      <?php if ($dayBadge): ?><span class="pill pill-day"><?= e($dayBadge) ?></span><?php endif; ?>
      <?php if ($soon): ?><span class="pill">Upcoming</span><?php endif; ?>
      <?php if ($hasClash): ?><span class="pill pill-clash">⚠ Clash</span><?php endif; ?>
    </h3>
    <div class="meta"><?= $isTrip ? '💼' : '📍' ?> <strong><?= e($m['venue']) ?></strong></div>
    <?php if ($m['attendees']): ?><div class="meta">👥 <?= e($m['attendees']) ?></div><?php endif; ?>
    <?php if (!empty($m['contact'])): ?><div class="meta">☎ <?= e($m['contact']) ?></div><?php endif; ?>
    <?php if ($team): ?><div class="meta">👔 Team: <?= e(join_names($team)) ?></div><?php endif; ?>
    <?php if ($m['agenda']): ?><div class="agenda"><?= nl2br(e($m['agenda'])) ?></div><?php endif; ?>
    <?php if (!empty($m['notes'])): ?><div class="notes">⚠ <?= nl2br(e($m['notes'])) ?></div><?php endif; ?>
  </div>
  <div class="meeting-actions">
    <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/meeting_edit.php?id=<?= (int) $m['id'] ?><?= ministry_qs((int) $m['ministry_id']) ?>">Edit</a>
    <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/print_meeting.php?id=<?= (int) $m['id'] ?><?= ministry_qs((int) $m['ministry_id']) ?>" target="_blank">Print</a>
    <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/meeting_remind.php?id=<?= (int) $m['id'] ?><?= ministry_qs((int) $m['ministry_id']) ?>"
       onclick="return confirm('Send an email reminder for this <?= $isTrip ? 'trip' : 'meeting' ?> now?');">🔔 Remind</a>
    <a class="btn btn-danger btn-sm" href="<?= BASE_URL ?>/meeting_delete.php?id=<?= (int) $m['id'] ?><?= ministry_qs((int) $m['ministry_id']) ?>"
       onclick="return confirm('Delete this <?= $isTrip ? 'trip' : 'meeting' ?>? This cannot be undone.');">Delete</a>
  </div>
</div>
