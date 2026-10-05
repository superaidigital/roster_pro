<?php
/**
 * Shared security helpers for Roster Pro.
 *
 * Keep this file dependency-free so controllers can load it before rendering views.
 */

function security_is_production(): bool {
    return strtolower(trim((string)(getenv('APP_ENV') ?: 'development'))) === 'production';
}

function security_trusted_proxy_ips(): array {
    $raw = trim((string)(getenv('TRUSTED_PROXY_IPS') ?: ''));
    if ($raw === '') {
        return [];
    }

    $ips = [];
    foreach (explode(',', $raw) as $value) {
        $ip = trim($value);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $ips[] = $ip;
        }
    }
    return array_values(array_unique($ips));
}

function security_is_trusted_proxy_request(): bool {
    if (!filter_var(getenv('TRUST_PROXY_HEADERS') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
        return false;
    }

    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return $remote !== '' && in_array($remote, security_trusted_proxy_ips(), true);
}

function security_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (security_is_trusted_proxy_request() && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        return $proto === 'https';
    }
    return false;
}

function security_session_idle_timeout(): int {
    return max(300, min(86400, (int)(getenv('SESSION_IDLE_TIMEOUT_SECONDS') ?: 28800)));
}

function security_session_absolute_timeout(): int {
    return max(
        security_session_idle_timeout(),
        min(172800, (int)(getenv('SESSION_ABSOLUTE_TIMEOUT_SECONDS') ?: 43200))
    );
}

function security_session_regen_interval(): int {
    return max(300, min(7200, (int)(getenv('SESSION_REGEN_INTERVAL_SECONDS') ?: 900)));
}

function security_enforce_authenticated_session(): void {
    if (!isset($_SESSION['user'])) {
        return;
    }

    $now = time();
    $authAt = (int)($_SESSION['_auth_at'] ?? $now);
    $lastSeen = (int)($_SESSION['_last_seen_at'] ?? $now);
    $lastRegen = (int)($_SESSION['_last_regen_at'] ?? $now);

    $absoluteExpired = ($now - $authAt) > security_session_absolute_timeout();
    $idleExpired = ($now - $lastSeen) > security_session_idle_timeout();

    if ($absoluteExpired || $idleExpired) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['login_error'] = 'เซสชั่นหมดอายุ กรุณาเข้าสู่ระบบใหม่';
        return;
    }

    if (($now - $lastRegen) >= security_session_regen_interval()) {
        session_regenerate_id(true);
        $_SESSION['_last_regen_at'] = $now;
    }

    $_SESSION['_last_seen_at'] = $now;
}

function security_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        security_enforce_authenticated_session();
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');

    if (session_name() === 'PHPSESSID') {
        session_name('ROSTERSESSID');
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => security_is_production() || security_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
    security_enforce_authenticated_session();
}

function security_mark_authenticated_session(): void {
    security_start_session();
    $now = time();
    $_SESSION['_auth_at'] = $now;
    $_SESSION['_last_seen_at'] = $now;
    $_SESSION['_last_regen_at'] = $now;
}

function security_destroy_session(): void {
    security_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}

function security_csp_mode(): string {
    $mode = strtolower(trim((string)(getenv('CSP_MODE') ?: (security_is_production() ? 'report-only' : 'off'))));
    return in_array($mode, ['off', 'report-only', 'enforce'], true) ? $mode : 'off';
}

function security_csp_policy(): string {
    $directives = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "media-src 'self'",
        "worker-src 'self' blob:",
        "manifest-src 'self'",
    ];

    if (security_is_production()) {
        $directives[] = 'upgrade-insecure-requests';
    }

    $reportUri = trim((string)(getenv('CSP_REPORT_URI') ?: ''));
    if ($reportUri !== '' && str_starts_with($reportUri, '/')) {
        $directives[] = 'report-uri ' . $reportUri;
    }

    return implode('; ', $directives);
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
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(self), payment=(), usb=()");
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');

    $cspMode = security_csp_mode();
    if ($cspMode !== 'off') {
        $header = $cspMode === 'enforce'
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';
        header($header . ': ' . security_csp_policy());
    }

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

    if (!str_starts_with($path, 'index.php') && !str_starts_with($path, '/')) {
        return $fallback;
    }

    return $target;
}

function security_regenerate_session(): void {
    security_start_session();
    session_regenerate_id(true);
    $_SESSION['_last_regen_at'] = time();
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
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
}

function security_login_identity_fingerprint(string $username): string {
    $normalized = mb_strtolower(trim($username), 'UTF-8');
    return substr(hash('sha256', $normalized), 0, 24);
}

function security_login_failure_details(string $username): string {
    return 'พยายามเข้าสู่ระบบล้มเหลว credential_fingerprint:'
        . security_login_identity_fingerprint($username);
}

function security_check_login_rate_limit(
    PDO $db,
    string $username,
    int $maxAttempts = 5,
    int $windowSeconds = 900,
    int $maxIpAttempts = 25
): array {
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

    $ipStmt = $db->prepare(
        "SELECT COUNT(*) AS attempts, MAX(created_at) AS last_attempt
         FROM logs
         WHERE user_id = 0
           AND action = 'LOGIN'
           AND ip_address = ?
           AND created_at >= ?"
    );
    $ipStmt->execute([$ip, $since]);
    $ipRow = $ipStmt->fetch(PDO::FETCH_ASSOC) ?: ['attempts' => 0, 'last_attempt' => null];

    $attempts = (int)($row['attempts'] ?? 0);
    $ipAttempts = (int)($ipRow['attempts'] ?? 0);

    if ($attempts < $maxAttempts && $ipAttempts < $maxIpAttempts) {
        return [
            'allowed' => true,
            'attempts' => $attempts,
            'ip_attempts' => $ipAttempts,
            'retry_after' => 0,
        ];
    }

    $lastAttemptRaw = $attempts >= $maxAttempts
        ? ($row['last_attempt'] ?? null)
        : ($ipRow['last_attempt'] ?? null);
    $lastAttempt = !empty($lastAttemptRaw) ? strtotime((string)$lastAttemptRaw) : time();
    $retryAfter = max(1, ($lastAttempt + $windowSeconds) - time());

    return [
        'allowed' => false,
        'attempts' => $attempts,
        'ip_attempts' => $ipAttempts,
        'retry_after' => $retryAfter,
    ];
}

/**
 * Build a same-application absolute URL for printed documents and public verification links.
 */
function security_absolute_app_url(string $relativePath): string {
    $relativePath = ltrim($relativePath, '/');

    $configured = trim((string)(getenv('APP_BASE_URL') ?: ''));
    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
        $parts = parse_url($configured);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (in_array($scheme, ['http', 'https'], true)) {
            return rtrim($configured, '/') . '/' . $relativePath;
        }
    }

    $scheme = security_is_https() ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
        $host = 'localhost';
    }

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
    $prefix = $basePath !== '' ? $basePath . '/' : '/';

    return $scheme . '://' . $host . $prefix . $relativePath;
}
