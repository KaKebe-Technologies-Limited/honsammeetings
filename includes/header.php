<?php
/**
 * Shared page header. Expects (optionally) $page_title and $active to be set
 * before including this file.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$active   = $active ?? '';
$dueSoon  = is_logged_in() ? meetings_in_reminder_window() : [];
$dueCount = count($dueSoon);
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
    <span class="brand-photo">
      <img class="photo-fit" src="<?= BASE_URL ?>/<?= MINISTER_PHOTO ?>" alt="<?= e(MINISTER_NAME) ?>"
           onerror="this.closest('.brand-photo').replaceWith(Object.assign(document.createElement('div'),{className:'brand-photo-fallback',textContent:'SE'}))">
    </span>
    <div class="brand-text">
      <div class="ministry"><?= e(MINISTRY_NAME) ?></div>
      <h1><?= e(APP_NAME) ?></h1>
      <div class="minister-name"><?= e(MINISTER_NAME) ?></div>
    </div>
    <div class="brand-spacer"></div>
  </div>
</header>

<?php if (is_logged_in()): ?>
<nav class="site-nav">
  <div class="container">
    <a href="<?= BASE_URL ?>/index.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="<?= BASE_URL ?>/schedule.php" class="<?= $active === 'schedule' ? 'active' : '' ?>">Weekly Schedule</a>
    <a href="<?= BASE_URL ?>/meetings.php" class="<?= $active === 'meetings' ? 'active' : '' ?>">All Meetings</a>
    <a href="<?= BASE_URL ?>/meeting_edit.php" class="<?= $active === 'add' ? 'active' : '' ?>">+ Add Meeting</a>
    <a href="<?= BASE_URL ?>/staff.php" class="<?= $active === 'staff' ? 'active' : '' ?>">Staff</a>
    <div class="nav-right">
      <?php if ($dueCount > 0): ?>
        <a href="<?= BASE_URL ?>/index.php" title="Meetings/trips due for a reminder">
          🔔 <span class="badge-alert"><?= $dueCount ?></span>
        </a>
      <?php endif; ?>
      <span class="who">Signed in as <strong><?= e(current_user()['full_name'] ?? current_user()['username']) ?></strong></span>
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline btn-sm">Log out</a>
    </div>
  </div>
</nav>
<?php endif; ?>
