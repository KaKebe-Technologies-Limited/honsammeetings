<?php
/**
 * Shared shell for the platform-wide Super Admin area — a deliberately
 * different visual language (sidebar + Uganda flag colors) from the
 * per-ministry app's own black/gold header, since this is a different
 * audience (the platform operator, not a ministry office).
 * Expects (optionally) $page_title and $admin_active to be set first.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_super_admin();

$admin_active = $admin_active ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? 'Admin') ?> · <?= e(APP_NAME) ?> Admin</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-shell">
<aside class="admin-sidebar">
  <div class="admin-logo">
    <span class="admin-flag"><?= uganda_flag_svg() ?></span>
    <div>
      <div class="admin-logo-name"><?= e(APP_NAME) ?></div>
      <div class="admin-logo-tag">Super Admin</div>
    </div>
  </div>
  <nav class="admin-nav">
    <a href="<?= BASE_URL ?>/admin_dashboard.php" class="<?= $admin_active === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
    <a href="<?= BASE_URL ?>/admin_ministries.php" class="<?= $admin_active === 'ministries' ? 'active' : '' ?>">🏛 Ministries</a>
    <a href="<?= BASE_URL ?>/admin_users.php" class="<?= $admin_active === 'users' ? 'active' : '' ?>">👤 Users</a>
  </nav>
  <div class="admin-sidebar-foot">
    <a href="<?= BASE_URL ?>/logout.php">⏻ Log out</a>
  </div>
</aside>
<div class="admin-main">
  <header class="admin-topbar">
    <form class="admin-search" method="get" action="<?= BASE_URL ?>/admin_ministries.php">
      <input type="text" name="q" placeholder="Search ministries…">
    </form>
    <div class="admin-profile">
      <span class="admin-profile-name"><?= e(current_user()['full_name'] ?? current_user()['username']) ?></span>
      <span class="admin-profile-badge">Super Admin</span>
    </div>
  </header>
  <main class="admin-content">
    <?php if ($flash = flash_get()): ?>
      <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['text']) ?></div>
    <?php endif; ?>
