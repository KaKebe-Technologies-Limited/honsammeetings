<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$id      = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$editing = $id > 0;
$error   = null;

$user = [
    'full_name'   => '',
    'username'    => '',
    'email'       => '',
    'role'        => 'office_admin',
    'ministry_id' => isset($_GET['ministry_id']) ? (int) $_GET['ministry_id'] : null,
];

if ($editing) {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        header('Location: ' . BASE_URL . '/admin_users.php');
        exit;
    }
    $user = $found;
}

$ministries = db()->query('SELECT id, name FROM ministries ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = ($_POST['role'] ?? '') === 'super_admin' ? 'super_admin' : 'office_admin';
    $ministryId = $role === 'office_admin' ? (int) ($_POST['ministry_id'] ?? 0) : null;
    $password = $_POST['password'] ?? '';

    $user = ['full_name' => $fullName, 'username' => $username, 'email' => $email, 'role' => $role, 'ministry_id' => $ministryId];

    if ($fullName === '' || $username === '' || $email === '') {
        $error = 'Full name, username, and email are required.';
    } elseif ($role === 'office_admin' && !$ministryId) {
        $error = 'Please choose a ministry for an Office Admin.';
    } elseif (!$editing && $password === '') {
        $error = 'A password is required for a new user.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            $error = 'That username is already taken.';
        } else {
            if ($editing) {
                if ($password !== '') {
                    $stmt = db()->prepare('UPDATE users SET full_name=?, username=?, email=?, role=?, ministry_id=?, password_hash=? WHERE id=?');
                    $stmt->execute([$fullName, $username, $email, $role, $ministryId, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = db()->prepare('UPDATE users SET full_name=?, username=?, email=?, role=?, ministry_id=? WHERE id=?');
                    $stmt->execute([$fullName, $username, $email, $role, $ministryId, $id]);
                }
                flash_set('success', 'Updated ' . e($fullName) . '.' . ($password !== '' ? ' Password was reset.' : ''));
            } else {
                $stmt = db()->prepare('INSERT INTO users (full_name, username, email, role, ministry_id, password_hash) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$fullName, $username, $email, $role, $ministryId, password_hash($password, PASSWORD_DEFAULT)]);
                flash_set('success', 'Created ' . e($fullName) . '.');
            }
            header('Location: ' . BASE_URL . '/admin_users.php');
            exit;
        }
    }
}

$page_title   = $editing ? 'Edit User' : 'Add User';
$admin_active = 'users';

require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-head">
  <div>
    <h1><?= $editing ? 'Edit User' : 'Add User' ?></h1>
    <div class="sub">Only Super Admin can set roles, assign ministries, and reset passwords.</div>
  </div>
</div>

<div class="admin-panel admin-panel-pad" style="max-width:640px;">
  <?php if ($error): ?>
    <div class="alert alert-error">⚠ <?= e($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

    <div class="form-grid">
      <div class="field full">
        <label>Full Name *</label>
        <input type="text" name="full_name" required maxlength="100" value="<?= e($user['full_name']) ?>">
      </div>
      <div class="field">
        <label>Username *</label>
        <input type="text" name="username" required maxlength="50" value="<?= e($user['username']) ?>">
      </div>
      <div class="field">
        <label>Email *</label>
        <input type="email" name="email" required maxlength="150" value="<?= e($user['email']) ?>">
      </div>
      <div class="field">
        <label>Role *</label>
        <select name="role" id="roleSelect">
          <option value="office_admin" <?= $user['role'] === 'office_admin' ? 'selected' : '' ?>>Office Admin</option>
          <option value="super_admin" <?= $user['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
        </select>
      </div>
      <div class="field" id="ministryField">
        <label>Ministry *</label>
        <select name="ministry_id">
          <option value="">— Choose a ministry —</option>
          <?php foreach ($ministries as $mi): ?>
            <option value="<?= (int) $mi['id'] ?>" <?= (int) ($user['ministry_id'] ?? 0) === (int) $mi['id'] ? 'selected' : '' ?>><?= e($mi['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field full">
        <label><?= $editing ? 'Reset Password (optional)' : 'Password *' ?></label>
        <input type="password" name="password" <?= $editing ? '' : 'required' ?> placeholder="<?= $editing ? 'Leave blank to keep the current password' : '' ?>">
        <?php if ($editing): ?><span class="hint">Only Super Admin can do this — Office Admins have no way to reset another user's password.</span><?php endif; ?>
      </div>
    </div>

    <div class="admin-actions" style="justify-content:flex-start;margin-top:10px;">
      <button type="submit" class="btn btn-yellow"><?= $editing ? 'Save Changes' : 'Create User' ?></button>
      <a href="<?= BASE_URL ?>/admin_users.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<script>
(function () {
  var roleSelect = document.getElementById('roleSelect');
  var ministryField = document.getElementById('ministryField');
  function apply() {
    ministryField.hidden = roleSelect.value === 'super_admin';
  }
  roleSelect.addEventListener('change', apply);
  apply();
})();
</script>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
