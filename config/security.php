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

/**
 * Send safe-by-default headers for dynamic application pages.
 * Static assets are served directly by the web server and are unaffected.
 */
function security_send_headers(): void {
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(self)");
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');

    if (security_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Validate controller/action route tokens before they are used to build paths or method names.
 */
function security_is_valid_route_token(?string $value): bool {
    return is_string($value)
        && $value !== ''
        && preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value) === 1;
}

/**
 * Allow only local application redirects. Reject schemes, protocol-relative
 * URLs and control characters to prevent open-redirect/header injection bugs.
 */
function security_safe_local_redirect(?string $target, string $fallback = 'index.php?c=dashboard'): string {
    $target = trim((string)$target);
    if ($target === '' || preg_match('/[\r\n]/', $target)) {
        return $fallback;
    }

    if (str_starts_with($target, '//')) {
        return $fallback;
    }

    $parts = parse_url($target);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return $fallback;
    }

    $path = (string)($parts['path'] ?? '');
    if ($path === '') {
        return $fallback;
    }

    // Application links may be relative (index.php?...) or same-origin absolute paths (/roster_pro/index.php?...).
    if (!str_starts_with($path, 'index.php') && !str_starts_with($path, '/')) {
        return $fallback;
    }

    return $target;
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

function security_request_csrf_token(): ?string {
    if (isset($_POST['_csrf']) && is_string($_POST['_csrf'])) {
        return $_POST['_csrf'];
    }

    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    return is_string($header) && $header !== '' ? $header : null;
}

function security_is_valid_post_csrf(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && security_verify_csrf(security_request_csrf_token());
}

function security_client_ip(): string {
    // Use the socket peer address for security decisions. Do not trust arbitrary
    // X-Forwarded-For values unless the reverse proxy is explicitly trusted.
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
}

function security_login_failure_details(string $username): string {
    return "พยายามเข้าสู่ระบบล้มเหลว (รหัสผ่านผิด) Username: " . $username;
}

function security_check_login_rate_limit(PDO $db, string $username, int $maxAttempts = 5, int $windowSeconds = 900): array {
    $username = trim($username);
    $ip = security_client_ip();
    $since = date('Y-m-d H:i:s', time() - $windowSeconds);
    $details = security_login_failure_details($username);

    $stmt = $db->prepare(
        "SELECT COUNT(*) AS attempts, MAX(created_at) AS last_attempt
         FROM logs
         WHERE user_id = 0
           AND action = 'LOGIN'
           AND ip_address = ?
           AND details = ?
           AND created_at >= ?"
    );
    $stmt->execute([$ip, $details, $since]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['attempts' => 0, 'last_attempt' => null];

    $attempts = (int)($row['attempts'] ?? 0);
    if ($attempts < $maxAttempts) {
        return ['allowed' => true, 'attempts' => $attempts, 'retry_after' => 0];
    }

    $lastAttempt = !empty($row['last_attempt']) ? strtotime((string)$row['last_attempt']) : time();
    $retryAfter = max(1, ($lastAttempt + $windowSeconds) - time());

    return ['allowed' => false, 'attempts' => $attempts, 'retry_after' => $retryAfter];
}
