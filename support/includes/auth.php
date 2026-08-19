<?php
/**
 * Original Concepts Support System
 * Authentication & Session Management
 */

if (!defined('OC_SYSTEM')) {
    die('Direct access not permitted.');
}

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $user = dbRow('SELECT * FROM users WHERE id = ? AND is_active = 1', [$_SESSION['user_id']]);
    }
    return $user ?: null;
}

function currentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function hasRole(string ...$roles): bool
{
    $user = currentUser();
    return $user && in_array($user['role'], $roles);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect(BASE_URL . '/auth/login.php');
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();
    if (!hasRole(...$roles)) {
        redirect(BASE_URL . '/index.php?error=forbidden');
    }
}

function login(string $email, string $password): bool
{
    $user = dbRow('SELECT * FROM users WHERE email = ? AND is_active = 1', [strtolower(trim($email))]);

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];

    // Update last login
    dbUpdate('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}
