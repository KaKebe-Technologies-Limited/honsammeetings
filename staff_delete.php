<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = db()->prepare('DELETE FROM staff WHERE id = ?');
    $stmt->execute([$id]);
    flash_set('success', 'Staff member removed.');
}

header('Location: ' . BASE_URL . '/staff.php');
exit;
