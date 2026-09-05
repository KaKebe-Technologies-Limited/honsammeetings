<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$m  = $id > 0 ? find_meeting($id) : null;

if ($m) {
    $stmt = db()->prepare('DELETE FROM meetings WHERE id = ?');
    $stmt->execute([$id]);
}

$back = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/meetings.php');
header('Location: ' . $back);
exit;
