<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$staff = $id > 0 ? find_staff($id) : null;
$ministryId = $staff['ministry_id'] ?? resolve_ministry_id();

if ($staff) {
    $stmt = db()->prepare('DELETE FROM staff WHERE id = ?');
    $stmt->execute([$id]);
    flash_set('success', 'Staff member removed.');
}

header('Location: ' . BASE_URL . '/staff.php' . ($ministryId ? ministry_qs($ministryId, '?') : ''));
exit;
