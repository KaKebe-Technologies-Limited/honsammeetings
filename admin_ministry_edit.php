<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$id      = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$editing = $id > 0;
$error   = null;

$ministry = ['name' => '', 'minister_name' => '', 'minister_photo' => null];

if ($editing) {
    $stmt = db()->prepare('SELECT * FROM ministries WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        header('Location: ' . BASE_URL . '/admin_ministries.php');
        exit;
    }
    $ministry = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $ministerName = trim($_POST['minister_name'] ?? '');
    $ministry = ['name' => $name, 'minister_name' => $ministerName, 'minister_photo' => $ministry['minister_photo']];

    if ($name === '' || $ministerName === '') {
        $error = 'Ministry name and minister name are required.';
    } else {
        $photoPath = $ministry['minister_photo'];

        if (!empty($_FILES['minister_photo']['name'])) {
            $file = $_FILES['minister_photo'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Photo upload failed — please try again.';
            } elseif (!in_array($ext, $allowed, true)) {
                $error = 'Photo must be a JPG, PNG, or WEBP file.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Photo must be under 5MB.';
            } else {
                $dir = __DIR__ . '/assets/img/ministries';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = 'ministry_' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
                    $photoPath = 'assets/img/ministries/' . $filename;
                } else {
                    $error = 'Could not save the uploaded photo.';
                }
            }
        }

        if (!$error) {
            if ($editing) {
                $stmt = db()->prepare('UPDATE ministries SET name = ?, minister_name = ?, minister_photo = ? WHERE id = ?');
                $stmt->execute([$name, $ministerName, $photoPath, $id]);
                flash_set('success', 'Updated ' . e($name) . '.');
                header('Location: ' . BASE_URL . '/admin_ministries.php');
            } else {
                $stmt = db()->prepare('INSERT INTO ministries (name, minister_name, minister_photo) VALUES (?, ?, ?)');
                $stmt->execute([$name, $ministerName, $photoPath]);
                $newId = (int) db()->lastInsertId();
                flash_set('success', 'Added ' . e($name) . '. Now create its first user.');
                header('Location: ' . BASE_URL . '/admin_user_edit.php?ministry_id=' . $newId);
            }
            exit;
        }
    }
}

$page_title   = $editing ? 'Edit Ministry' : 'Add Ministry';
$admin_active = 'ministries';

require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-head">
  <div>
    <h1><?= $editing ? 'Edit' : 'Add' ?> Ministry</h1>
    <div class="sub">Each ministry's meetings, staff, and users are completely isolated from every other office.</div>
  </div>
</div>

<div class="admin-panel admin-panel-pad" style="max-width:640px;">
  <?php if ($error): ?>
    <div class="alert alert-error">⚠ <?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

    <div class="form-grid">
      <div class="field full">
        <label>Ministry Name *</label>
        <input type="text" name="name" required maxlength="200" value="<?= e($ministry['name']) ?>" placeholder="e.g. Office of the Minister for Health">
      </div>
      <div class="field full">
        <label>Minister Name *</label>
        <input type="text" name="minister_name" required maxlength="150" value="<?= e($ministry['minister_name']) ?>" placeholder="e.g. Hon. Jane Doe">
      </div>
      <div class="field full">
        <label>Minister Photo (optional)</label>
        <?php if (!empty($ministry['minister_photo'])): ?>
          <div style="margin-bottom:8px;"><img src="<?= BASE_URL ?>/<?= e($ministry['minister_photo']) ?>" alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--ug-yellow);"></div>
        <?php endif; ?>
        <input type="file" name="minister_photo" accept=".jpg,.jpeg,.png,.webp">
        <span class="hint">JPG, PNG, or WEBP, under 5MB. Leave blank to keep the current photo.</span>
      </div>
    </div>

    <div class="admin-actions" style="justify-content:flex-start;margin-top:10px;">
      <button type="submit" class="btn btn-yellow"><?= $editing ? 'Save Changes' : 'Save & Add First User' ?></button>
      <a href="<?= BASE_URL ?>/admin_ministries.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
