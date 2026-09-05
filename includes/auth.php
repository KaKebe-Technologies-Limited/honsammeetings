<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/** Gate for the platform-wide admin area — a super_admin session is required. */
function require_super_admin(): void
{
    require_login();
    if ((current_user()['role'] ?? '') !== 'super_admin') {
        http_response_code(403);
        exit('Forbidden — Super Admin access only.');
    }
}

/**
 * Which ministry's data the current request should read/write.
 * office_admin is always locked to their own ministry — $_GET is never
 * trusted here, since this is the tenant security boundary between offices.
 * super_admin may pass ?ministry_id= to view/manage a specific office;
 * returns null if super_admin hasn't chosen one yet.
 */
function resolve_ministry_id(): ?int
{
    $user = current_user();
    if (!$user) {
        return null;
    }
    if (($user['role'] ?? 'office_admin') === 'super_admin') {
        return isset($_GET['ministry_id']) ? (int) $_GET['ministry_id'] : null;
    }
    return $user['ministry_id'] ?? null;
}

/**
 * Attempt to log a user in. Returns true on success.
 */
function attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'          => (int) $user['id'],
            'username'    => $user['username'],
            'full_name'   => $user['full_name'],
            'email'       => $user['email'],
            'role'        => $user['role'] ?? 'office_admin',
            'ministry_id' => isset($user['ministry_id']) ? (int) $user['ministry_id'] : null,
        ];
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

/** CSRF helpers */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Invalid or expired form token. Please go back and try again.');
    }
}

/** One-shot flash message, shown on the next page load then cleared. */
function flash_set(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
