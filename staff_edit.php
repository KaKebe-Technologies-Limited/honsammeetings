<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id      = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$editing = $id > 0;
$error   = null;

$person = ['name' => '', 'active' => 1];

if ($editing) {
    $stmt = db()->prepare('SELECT * FROM staff WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        header('Location: ' . BASE_URL . '/staff.php');
        exit;
    }
    $person = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $isActive = isset($_POST['active']) ? 1 : 0;
    $person   = ['name' => $name, 'active' => $isActive];

    if ($name === '') {
        $error = 'Name is required.';
    } else {
        if ($editing) {
            $stmt = db()->prepare('UPDATE staff SET name = ?, active = ? WHERE id = ?');
            $stmt->execute([$name, $isActive, $id]);
        } else {
            $stmt = db()->prepare('INSERT INTO staff (name, active) VALUES (?, ?)');
            $stmt->execute([$name, $isActive]);
        }
        flash_set('success', ($editing ? 'Updated ' : 'Added ') . e($name) . '.');
        header('Location: ' . BASE_URL . '/staff.php');
        exit;
    }
}

$page_title = $editing ? 'Edit Staff' : 'Add Staff';
$active     = 'staff';

require __DIR__ . '/includes/header.php';
?>
<div class="page">
  <div class="page-head">
    <div>
      <h2><?= $editing ? 'Edit' : 'Add' ?> Staff</h2>
      <div class="sub">Only active staff show up in the meeting/trip team picker.</div>
    </div>
  </div>

  <div class="panel panel-pad">
    <?php if ($error): ?>
      <div class="alert alert-error">⚠ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

      <div class="form-grid">
        <div class="field full">
          <label>Name / Role *</label>
          <input type="text" name="name" required maxlength="150" value="<?= e($person['name']) ?>" placeholder="e.g. Commissioner Disaster">
        </div>
        <div class="field full">
          <label><input type="checkbox" name="active" value="1" <?= $person['active'] ? 'checked' : '' ?> style="width:auto;display:inline-block;margin-right:6px;"> Active (shown in the team picker)</label>
        </div>
      </div>

      <div class="btn-row" style="margin-top:8px;">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Save' ?></button>
        <a href="<?= BASE_URL ?>/staff.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
