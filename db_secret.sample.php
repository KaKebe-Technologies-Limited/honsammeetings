<?php
/**
 * Live/production environment overrides — DO NOT COMMIT (listed in
 * .gitignore). Copy this file to db_secret.php ON THE LIVE SERVER ONLY and
 * fill in the real values.
 *
 * Never create db_secret.php on a local dev machine — config.php checks for
 * this file's existence to decide whether to use local XAMPP defaults or
 * these production settings, so having it locally would point local testing
 * at the live database.
 */

define('DB_HOST', 'localhost'); // typical for shared hosting — change if your host gives a different DB host
define('DB_NAME', 'your_live_db_name');
define('DB_USER', 'your_live_db_user');
define('DB_PASS', 'Op323@sche');

define('BASE_URL', ''); // e.g. '' if hosted at the domain root, '/honsammeetings' if in a subfolder
define('SITE_URL', 'https://your-domain.example');
