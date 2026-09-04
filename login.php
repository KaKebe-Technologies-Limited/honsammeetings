<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (attempt_login($username, $password)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    $error = 'Incorrect username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in &middot; <?= e(APP_NAME) ?></title>
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
      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Log in</button>
      </form>
    </div>
    <div class="login-foot">Authorized personnel only.</div>
  </div>
</div>
</body>
</html>
