<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$m = find_meeting($id);

if (!$m) {
    flash_set('error', 'Meeting not found.');
} else {
    [$subject, $body] = build_reminder_email($m);
    $html = build_reminder_email_html($m);
    [$to, $cc] = reminder_recipients((int) $m['ministry_id']);

    if (!$to && !$cc) {
        flash_set('error', 'No recipients configured (no users, no MAIL_CC_LIST).');
    } else {
        try {
            send_mail_smtp($to, $subject, $body, $cc, $html);
            $stmt = db()->prepare('UPDATE meetings SET reminder_sent = 1 WHERE id = ?');
            $stmt->execute([$id]);
            flash_set('success', 'Reminder email sent for "' . $m['title'] . '".');
        } catch (SmtpMailError $e) {
            flash_set('error', 'Could not send reminder: ' . $e->getMessage());
        }
    }
}

$back = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/meetings.php');
header('Location: ' . $back);
exit;
