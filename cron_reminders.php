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

$to = array_values(array_filter(array_column(db()->query('SELECT email FROM users')->fetchAll(), 'email')));
$cc = defined('MAIL_CC_LIST') ? MAIL_CC_LIST : [];

if (!$to && !$cc) {
    echo "No recipients configured (no users, no MAIL_CC_LIST) — nothing to send.\n";
    exit;
}

$sent = 0;
$failed = 0;

foreach ($due as $m) {
    $isTrip = $m['event_type'] === 'trip';

    if ($isTrip) {
        $subject = 'Reminder: Trip — ' . $m['title'] . ' (' . fmt_date_short($m['meeting_date']) . ')';
        $body = "TRIP REMINDER\n\n"
            . "Title:        {$m['title']}\n"
            . "Departure:    " . fmt_date_long($m['meeting_date']) . ($m['start_time'] ? ' at ' . fmt_time($m['start_time']) : '') . "\n"
            . "Return:       " . fmt_date_long($m['end_date'] ?: $m['meeting_date']) . ($m['end_time'] ? ' at ' . fmt_time($m['end_time']) : '') . "\n"
            . "Length:       " . trip_length_label($m) . "\n"
            . "Destination:  {$m['venue']}\n"
            . ($m['attendees'] ? "Attendees:    {$m['attendees']}\n" : '')
            . ($m['agenda'] ? "\nPurpose:\n{$m['agenda']}\n" : '');
    } else {
        $subject = 'Reminder: ' . $m['title'] . ' — ' . fmt_date_short($m['meeting_date']);
        $body = "MEETING REMINDER\n\n"
            . "Title:  {$m['title']}\n"
            . "Date:   " . fmt_date_long($m['meeting_date']) . "\n"
            . "Time:   " . fmt_time($m['start_time']) . " - " . fmt_time($m['end_time']) . "\n"
            . "Venue:  {$m['venue']}\n"
            . ($m['attendees'] ? "Attendees: {$m['attendees']}\n" : '')
            . ($m['agenda'] ? "\nAgenda:\n{$m['agenda']}\n" : '');
    }
    $body .= "\n-- " . SMTP_FROM_NAME;

    try {
        send_mail_smtp($to, $subject, $body, $cc);
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
