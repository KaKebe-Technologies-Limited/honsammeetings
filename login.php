<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

function post_login_redirect(): string
{
    return (current_user()['role'] ?? '') === 'super_admin'
        ? BASE_URL . '/admin_dashboard.php'
        : BASE_URL . '/index.php';
}

if (is_logged_in()) {
    header('Location: ' . post_login_redirect());
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (attempt_login($username, $password)) {
        header('Location: ' . post_login_redirect());
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
      <div class="flag-badge"><?= uganda_flag_svg() ?></div>
      <div class="ministry">Government of Uganda</div>
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
