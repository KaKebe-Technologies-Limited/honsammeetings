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
?>
<div class="meeting-card <?= $soon ? 'soon' : '' ?>">
  <div class="meeting-time">
    <?php if ($isTrip): ?>
      <span class="date"><?= e(fmt_date_range($m['meeting_date'], $m['end_date'] ?: $m['meeting_date'])) ?></span>
      <?php if ($m['start_time']): ?>Departs <?= e(fmt_time($m['start_time'])) ?><?php endif; ?>
    <?php else: ?>
      <span class="date"><?= e(fmt_date_short($m['meeting_date'])) ?></span>
      <?= e(fmt_time($m['start_time'])) ?> – <?= e(fmt_time($m['end_time'])) ?>
    <?php endif; ?>
    <span class="dur">⏱ <?= e(event_span_label($m)) ?></span>
  </div>
  <div class="meeting-body">
    <h3>
      <?= e($m['title']) ?>
      <?php if ($isTrip): ?><span class="pill pill-trip">✈ Trip</span><?php endif; ?>
      <?php if ($dayBadge): ?><span class="pill pill-day"><?= e($dayBadge) ?></span><?php endif; ?>
      <?php if ($soon): ?><span class="pill">Upcoming</span><?php endif; ?>
    </h3>
    <div class="meta"><?= $isTrip ? '✈️' : '📍' ?> <strong><?= e($m['venue']) ?></strong></div>
    <?php if ($m['attendees']): ?><div class="meta">👥 <?= e($m['attendees']) ?></div><?php endif; ?>
    <?php if ($m['agenda']): ?><div class="agenda"><?= nl2br(e($m['agenda'])) ?></div><?php endif; ?>
  </div>
  <div class="meeting-actions">
    <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/meeting_edit.php?id=<?= (int) $m['id'] ?>">Edit</a>
    <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/print_meeting.php?id=<?= (int) $m['id'] ?>" target="_blank">Print</a>
    <a class="btn btn-danger btn-sm" href="<?= BASE_URL ?>/meeting_delete.php?id=<?= (int) $m['id'] ?>"
       onclick="return confirm('Delete this <?= $isTrip ? 'trip' : 'meeting' ?>? This cannot be undone.');">Delete</a>
  </div>
</div>
