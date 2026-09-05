<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title    = 'Admin Dashboard';
$admin_active  = 'dashboard';

$stats       = admin_stats();
$chart       = admin_meetings_per_month(6);
$maxCount    = max(1, max(array_column($chart, 'count')));
$topMinistries = admin_top_ministries(3);
$maxTop      = max(1, max(array_column($topMinistries, 'meeting_count') ?: [1]));
$ministries  = admin_ministries_overview();

require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-head">
  <div>
    <h1>Platform Overview</h1>
    <div class="sub">Every ministry office on <?= e(APP_NAME) ?>, at a glance.</div>
  </div>
  <a href="<?= BASE_URL ?>/admin_ministry_edit.php" class="btn btn-yellow">+ Add Ministry</a>
</div>

<div class="admin-stat-row">
  <div class="admin-stat-card">
    <div class="num"><?= $stats['ministries'] ?></div>
    <div class="label">Ministries on the platform</div>
  </div>
  <div class="admin-stat-card accent-red">
    <div class="num"><?= $stats['users'] ?></div>
    <div class="label">Registered users</div>
  </div>
  <div class="admin-stat-card">
    <div class="num"><?= $stats['meetings_this_week'] ?></div>
    <div class="label">Meetings/trips this week (all offices)</div>
  </div>
</div>

<div class="admin-progress-row">
  <?php foreach ($topMinistries as $tm): ?>
    <div class="admin-progress-card">
      <div class="name"><?= e($tm['name']) ?></div>
      <div class="meta"><?= (int) $tm['meeting_count'] ?> meeting<?= (int) $tm['meeting_count'] === 1 ? '' : 's' ?> this month</div>
      <div class="admin-progress-track">
        <div class="admin-progress-fill" style="width:<?= round(($tm['meeting_count'] / $maxTop) * 100) ?>%"></div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$topMinistries): ?>
    <div class="admin-progress-card"><div class="name">No ministries yet</div><div class="meta">Add one to get started.</div></div>
  <?php endif; ?>
</div>

<div class="admin-grid">
  <div>
    <div class="admin-panel admin-panel-pad" style="margin-bottom:18px;">
      <div class="admin-card-title">Meetings scheduled per month <span>Last 6 months, all ministries</span></div>
      <div class="admin-chart-bars">
        <?php foreach ($chart as $c): ?>
          <div class="admin-chart-bar">
            <div class="val"><?= $c['count'] ?></div>
            <div class="bar" style="height:<?= $c['count'] > 0 ? max(6, round(($c['count'] / $maxCount) * 130)) : 3 ?>px;"></div>
            <div class="lbl"><?= e($c['label']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="admin-panel admin-panel-pad">
      <div class="admin-card-title">Ministries <a href="<?= BASE_URL ?>/admin_ministries.php">Manage all →</a></div>
      <?php if (!$ministries): ?>
        <div class="empty">No ministries added yet.</div>
      <?php else: ?>
        <?php foreach ($ministries as $m): ?>
          <div class="admin-ministry-row">
            <span class="admin-ministry-photo">
              <?php if (!empty($m['minister_photo'])): ?>
                <img src="<?= BASE_URL ?>/<?= e($m['minister_photo']) ?>" alt="<?= e($m['minister_name']) ?>" onerror="this.parentElement.textContent='<?= e(initials_from_name($m['minister_name'])) ?>'">
              <?php else: ?>
                <?= e(initials_from_name($m['minister_name'])) ?>
              <?php endif; ?>
            </span>
            <div class="admin-ministry-info">
              <div class="name"><?= e($m['name']) ?></div>
              <div class="meta"><?= e($m['minister_name']) ?> · <?= (int) $m['meetings_this_week'] ?> meeting<?= (int) $m['meetings_this_week'] === 1 ? '' : 's' ?> this week</div>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/index.php?ministry_id=<?= (int) $m['id'] ?>">View schedule</a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="admin-flag-panel">
    <div class="flag-large" style="aspect-ratio:3/2;"><?= uganda_flag_svg() ?></div>
    <h3><?= e(APP_NAME) ?></h3>
    <p>A shared scheduling platform for Ministry offices across the Government of Uganda.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
