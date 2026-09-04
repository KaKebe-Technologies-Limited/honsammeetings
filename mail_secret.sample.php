<?php
/**
 * Copy this file to mail_secret.php (same folder) and fill in real values.
 * mail_secret.php is listed in .gitignore — it is never committed.
 *
 * For Gmail: use an App Password (Google Account → Security → 2-Step
 * Verification → App passwords), NOT your normal login password.
 */

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);           // STARTTLS
define('SMTP_USERNAME', 'you@gmail.com');
define('SMTP_PASSWORD', 'your16charapppassword'); // no spaces
define('SMTP_FROM', SMTP_USERNAME);
define('SMTP_FROM_NAME', "Minister's Office Scheduler");

// Always CC'd on every reminder email, in addition to registered users.
define('MAIL_CC_LIST', ['ot.sedrick@gmail.com']);
