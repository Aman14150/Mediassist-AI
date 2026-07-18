<?php
declare(strict_types=1);

function require_local_staff_access(): void
{
    $address = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!in_array($address, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        exit;
    }
}

function start_staff_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('medicare_staff');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function staff_logged_in(): bool
{
    start_staff_session();
    return ($_SESSION['staff_authenticated'] ?? false) === true;
}

function require_staff_login(): void
{
    if (!staff_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    start_staff_session();
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(string $token): void
{
    if (!hash_equals(csrf_token(), $token)) {
        throw new RuntimeException('Invalid form token.');
    }
}
