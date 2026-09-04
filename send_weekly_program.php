<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/pdf.php';
require_login();

$offset = isset($_GET['w']) ? (int) $_GET['w'] : 0;
[$monday, $friday] = week_range('today', $offset);

$weekMeetings = meetings_between($monday, $friday);
[$to, $cc] = reminder_recipients();

if (!$to && !$cc) {
    flash_set('error', 'No recipients configured (no users, no MAIL_CC_LIST).');
} else {
    $filename = 'Weekly-Program-' . $monday . '-to-' . $friday . '.pdf';
    $subject  = 'Weekly Program — ' . fmt_date_medium($monday) . ' to ' . fmt_date_medium($friday);
    $text     = "The Minister's weekly program for " . fmt_date_long($monday) . " to " . fmt_date_long($friday)
        . " is attached as a PDF.\n\n-- " . SMTP_FROM_NAME;

    try {
        $pdfBytes = build_weekly_program_pdf($monday, $friday);
        $html     = build_weekly_program_email_html($monday, $friday, count($weekMeetings));
        send_mail_smtp($to, $subject, $text, $cc, $html, [
            ['filename' => $filename, 'content' => $pdfBytes, 'mime' => 'application/pdf'],
        ]);
        flash_set('success', 'Weekly program PDF emailed to everyone.');
    } catch (SmtpMailError $e) {
        flash_set('error', 'Could not send the weekly program: ' . $e->getMessage());
    }
}

header('Location: ' . BASE_URL . '/schedule.php' . ($offset ? '?w=' . $offset : ''));
exit;
