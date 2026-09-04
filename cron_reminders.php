<?php
/**
 * Sends email reminders for meetings/trips currently inside their own
 * configured reminder window (set per-item on the Add/Edit form).
 *
 * Delivered via authenticated SMTP (includes/mailer.php) using the
 * credentials in mail_secret.php. Recipients: every registered user's
 * email (users table) + the fixed CC list (MAIL_CC_LIST in mail_secret.php).
 *
 * Run this on a schedule (every 15–30 minutes) so reminders go out inside
 * each item's own lead time. On Windows/XAMPP, use Task Scheduler:
 *   Program: C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\honsammeetings\cron_reminders.php
 *
 * On Linux, a crontab entry (every 15 minutes):
 *   15-minute crontab schedule expression, then: php /path/to/cron_reminders.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

// Allow from CLI freely; if hit over the web, require a shared secret so it
// can't be triggered by anyone browsing to the URL.
if (PHP_SAPI !== 'cli') {
    $secret = getenv('CRON_SECRET') ?: null;
    if (!$secret || ($_GET['key'] ?? '') !== $secret) {
        http_response_code(403);
        exit("Forbidden. Run this script via CLI/Task Scheduler, or set CRON_SECRET and pass ?key=...\n");
    }
    header('Content-Type: text/plain');
}

$due = meetings_in_reminder_window(true); // unsent only

if (!$due) {
    echo "No new reminders to send.\n";
    exit;
}

[$to, $cc] = reminder_recipients();

if (!$to && !$cc) {
    echo "No recipients configured (no users, no MAIL_CC_LIST) — nothing to send.\n";
    exit;
}

$sent = 0;
$failed = 0;

foreach ($due as $m) {
    [$subject, $body] = build_reminder_email($m);
    $html = build_reminder_email_html($m);

    try {
        send_mail_smtp($to, $subject, $body, $cc, $html);
        $stmt = db()->prepare('UPDATE meetings SET reminder_sent = 1 WHERE id = ?');
        $stmt->execute([$m['id']]);
        $sent++;
        echo "Sent: {$m['title']} ({$m['meeting_date']})\n";
    } catch (SmtpMailError $e) {
        $failed++;
        echo "FAILED: {$m['title']} — " . $e->getMessage() . "\n";
    }
}

echo "Done. {$sent} sent, {$failed} failed.\n";
