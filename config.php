<?php
/**
 * Application configuration.
 * Edit the database credentials and SMTP settings to match your server.
 */

// ---- Database (XAMPP defaults) ----
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'honsam_meetings');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Application ----
define('APP_NAME', "Minister's Weekly Schedule");
define('MINISTRY_NAME', 'Office of the Minister for Relief, Disaster Preparedness and Refugees');
define('MINISTER_NAME', 'Hon. Sam Engola');
define('MINISTER_PHOTO', 'assets/img/min1.jpg'); // relative to BASE_URL
define('BASE_URL', '/honsammeetings'); // change if hosted at a different path

// Fully-qualified base URL used in outgoing emails (links must work from any
// device, not just this machine's browser). Update this once the app is
// hosted on a real domain.
define('SITE_URL', 'http://localhost' . BASE_URL);

// ---- Email reminders (sent via authenticated SMTP, see includes/mailer.php) ----
// Real credentials live in mail_secret.php (gitignored — never committed).
// Copy mail_secret.sample.php to mail_secret.php and fill in your own values.
if (file_exists(__DIR__ . '/mail_secret.php')) {
    require_once __DIR__ . '/mail_secret.php';
} else {
    // Safe fallbacks so the app still runs (with reminders disabled) if the
    // secret file hasn't been created yet.
    define('SMTP_HOST', '');
    define('SMTP_PORT', 587);
    define('SMTP_USERNAME', '');
    define('SMTP_PASSWORD', '');
    define('SMTP_FROM', 'no-reply@opm.go.ug');
    define('SMTP_FROM_NAME', "Minister's Office Scheduler");
    define('MAIL_CC_LIST', []);
}

define('REMINDER_LEAD_HOURS', 24); // default selected in the "set reminder" dropdown

// Selectable "remind me before" options shown on the meeting form: hours => label.
define('REMINDER_OPTIONS', [
    1   => '1 hour before',
    3   => '3 hours before',
    6   => '6 hours before',
    12  => '12 hours before',
    24  => '1 day before',
    48  => '2 days before',
    72  => '3 days before',
    168 => '1 week before',
]);

// ---- Session ----
define('SESSION_NAME', 'honsam_sess');

date_default_timezone_set('Africa/Kampala');
