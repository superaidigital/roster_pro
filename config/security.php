<?php
/**
 * Shared security helpers for Roster Pro.
 *
 * Keep this file dependency-free so controllers can load it before rendering views.
 */

function security_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        return $proto === 'https';
    }
    return false;
}

function security_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => security_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function security_regenerate_session(): void {
    security_start_session();
    session_regenerate_id(true);
}

function security_csrf_token(): string {
    security_start_session();

    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function security_csrf_input(): string {
    return '<input type="hidden" name="_csrf" value="' .
        htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function security_verify_csrf(?string $token): bool {
    security_start_session();

    return is_string($token)
        && isset($_SESSION['_csrf_token'])
        && is_string($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}
