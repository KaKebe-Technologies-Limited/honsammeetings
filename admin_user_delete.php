<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_super_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id > 0 && $id !== (int) current_user()['id']) {
    $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    flash_set('success', 'User removed.');
}

header('Location: ' . BASE_URL . '/admin_users.php');
exit;
