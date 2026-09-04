<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-head">
      <span class="login-photo">
        <img class="photo-fit" src="<?= BASE_URL ?>/<?= MINISTER_PHOTO ?>" alt="<?= e(MINISTER_NAME) ?>"
             onerror="this.closest('.login-photo').style.display='none'">
      </span>
      <div class="ministry"><?= e(MINISTRY_NAME) ?></div>
      <h1><?= e(APP_NAME) ?></h1>
    </div>
    <div class="login-body">
      <p class="landing-tagline">Manage the Minister's meetings, trips, and weekly program in one place.</p>
      <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary">Log In</a>
    </div>
    <div class="login-foot">Authorized personnel only.</div>
  </div>
</div>
</body>
</html>
