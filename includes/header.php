<?php
/**
 * Shared page header. Expects (optionally) $page_title and $active to be set
 * before including this file.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$active     = $active ?? '';
$ministryId = is_logged_in() ? resolve_ministry_id() : null;
$dueSoon    = $ministryId ? meetings_in_reminder_window($ministryId) : [];
$dueCount   = count($dueSoon);
$ministry   = $ministryId ? ministry_by_id($ministryId) : null;
$brandName  = $ministry['name'] ?? APP_NAME;
$brandMinister = $ministry['minister_name'] ?? '';
$brandInitials = e(initials_from_name($brandMinister ?: APP_NAME));
// A super_admin's chosen ?ministry_id= must survive every nav click — office_admin
// never needs this since resolve_ministry_id() locks them to their own ministry anyway.
$navQS = ((current_user()['role'] ?? '') === 'super_admin' && $ministryId) ? '?ministry_id=' . $ministryId : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="brand-bar">
    <?php if (!empty($ministry['minister_photo'])): ?>
    <span class="brand-photo">
      <img class="photo-fit" src="<?= BASE_URL ?>/<?= e($ministry['minister_photo']) ?>" alt="<?= e($brandMinister) ?>"
           onerror="this.closest('.brand-photo').replaceWith(Object.assign(document.createElement('div'),{className:'brand-photo-fallback',textContent:'<?= $brandInitials ?>'}))">
    </span>
    <?php else: ?>
      <div class="brand-photo-fallback"><?= $brandInitials ?></div>
    <?php endif; ?>
    <div class="brand-text">
      <div class="ministry"><?= e($brandName) ?></div>
      <h1><?= e(APP_NAME) ?></h1>
      <?php if ($brandMinister): ?><div class="minister-name"><?= e($brandMinister) ?></div><?php endif; ?>
    </div>
    <div class="brand-spacer"></div>
    <?php if ((current_user()['role'] ?? '') === 'super_admin'): ?>
      <a href="<?= BASE_URL ?>/admin_dashboard.php" class="btn btn-outline btn-sm" style="align-self:center;">← Admin Dashboard</a>
    <?php endif; ?>
  </div>
</header>

<?php if (is_logged_in()): ?>
<nav class="site-nav">
  <div class="container">
    <a href="<?= BASE_URL ?>/index.php<?= $navQS ?>" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="<?= BASE_URL ?>/schedule.php<?= $navQS ?>" class="<?= $active === 'schedule' ? 'active' : '' ?>">Weekly Schedule</a>
    <a href="<?= BASE_URL ?>/meetings.php<?= $navQS ?>" class="<?= $active === 'meetings' ? 'active' : '' ?>">All Meetings</a>
    <a href="<?= BASE_URL ?>/meeting_edit.php<?= $navQS ?>" class="<?= $active === 'add' ? 'active' : '' ?>">+ Add Meeting</a>
    <a href="<?= BASE_URL ?>/staff.php<?= $navQS ?>" class="<?= $active === 'staff' ? 'active' : '' ?>">Staff</a>
    <div class="nav-right">
      <?php if ($dueCount > 0): ?>
        <a href="<?= BASE_URL ?>/index.php<?= $navQS ?>" title="Meetings/trips due for a reminder">
          🔔 <span class="badge-alert"><?= $dueCount ?></span>
        </a>
      <?php endif; ?>
      <span class="who">Signed in as <strong><?= e(current_user()['full_name'] ?? current_user()['username']) ?></strong></span>
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline btn-sm">Log out</a>
    </div>
  </div>
</nav>
<?php endif; ?>
