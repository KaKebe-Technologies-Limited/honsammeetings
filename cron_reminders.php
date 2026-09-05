<?php
/**
 * Sends email reminders for meetings/trips currently inside their own
 * configured reminder window (set per-item on the Add/Edit form).
 *
 * Delivered via authenticated SMTP (includes/mailer.php) using the
 * credentials in mail_secret.php. Recipients: that ministry's own registered
 * users (users table, scoped by ministry_id) + the fixed platform-wide CC
 * list (MAIL_CC_LIST in mail_secret.php). Loops every ministry — see
 * includes/functions.php's meetings_in_reminder_window()/reminder_recipients().
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

// This runs without a logged-in session (CLI/cron), so it loops every
// ministry explicitly instead of relying on resolve_ministry_id().
$ministries = db()->query('SELECT id FROM ministries')->fetchAll();

$sent = 0;
$failed = 0;
$anyDue = false;

foreach ($ministries as $row) {
    $ministryId = (int) $row['id'];
    $due = meetings_in_reminder_window($ministryId, true); // unsent only
    if (!$due) {
        continue;
    }
    $anyDue = true;

    [$to, $cc] = reminder_recipients($ministryId);
    if (!$to && !$cc) {
        echo "Ministry #{$ministryId}: no recipients configured — skipping.\n";
        continue;
    }

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
}

if (!$anyDue) {
    echo "No new reminders to send.\n";
    exit;
}

echo "Done. {$sent} sent, {$failed} failed.\n";
