<?php
declare(strict_types=1);

/**
 * Roster Pro Front Controller
 *
 * Security goals:
 * - one controlled entry point for all MVC requests
 * - strict session cookie settings
 * - controller allow-list (prevents arbitrary file inclusion)
 * - safe action name validation
 * - generic production error responses
 */

date_default_timezone_set('Asia/Bangkok');

// Harden PHP sessions before session_start().
$isHttps = (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Baseline security headers. CSP is intentionally not forced here because the
// current UI still uses several inline scripts/styles and third-party CDNs.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// Explicit allow-list prevents path traversal / arbitrary controller loading.
$controllers = [
    'auth'         => ['file' => 'controllers/AuthController.php',         'class' => 'AuthController'],
    'dashboard'    => ['file' => 'controllers/DashboardController.php',    'class' => 'DashboardController'],
    'roster'       => ['file' => 'controllers/RosterController.php',       'class' => 'RosterController'],
    'ajax'         => ['file' => 'controllers/AjaxController.php',         'class' => 'AjaxController'],
    'leave'        => ['file' => 'controllers/LeaveController.php',        'class' => 'LeaveController'],
    'swap'         => ['file' => 'controllers/SwapController.php',         'class' => 'SwapController'],
    'users'        => ['file' => 'controllers/UsersController.php',        'class' => 'UsersController'],
    'staff'        => ['file' => 'controllers/StaffController.php',        'class' => 'StaffController'],
    'profile'      => ['file' => 'controllers/ProfileController.php',      'class' => 'ProfileController'],
    'hr'           => ['file' => 'controllers/HrController.php',           'class' => 'HrController'],
    'report'       => ['file' => 'controllers/ReportController.php',       'class' => 'ReportController'],
    'notification' => ['file' => 'controllers/NotificationController.php', 'class' => 'NotificationController'],
    'settings'     => ['file' => 'controllers/SettingsController.php',     'class' => 'SettingsController'],
    'hospitals'    => ['file' => 'controllers/HospitalsController.php',    'class' => 'HospitalsController'],
    'logs'         => ['file' => 'controllers/LogsController.php',         'class' => 'LogsController'],
];

$controllerKey = strtolower(trim((string) ($_GET['c'] ?? 'auth')));
$action = strtolower(trim((string) ($_GET['a'] ?? 'index')));

if (
    !preg_match('/^[a-z][a-z0-9_]*$/', $controllerKey)
    || !preg_match('/^[a-z][a-z0-9_]*$/', $action)
    || !isset($controllers[$controllerKey])
) {
    http_response_code(404);
    exit('ไม่พบหน้าที่ร้องขอ');
}

$route = $controllers[$controllerKey];
$controllerFile = __DIR__ . DIRECTORY_SEPARATOR . $route['file'];

if (!is_file($controllerFile)) {
    error_log("Router error: controller file not found: {$controllerFile}");
    http_response_code(500);
    exit('ระบบไม่สามารถเปิดหน้าที่ร้องขอได้');
}

require_once $controllerFile;

$className = $route['class'];
if (!class_exists($className)) {
    error_log("Router error: controller class not found: {$className}");
    http_response_code(500);
    exit('ระบบไม่สามารถเปิดหน้าที่ร้องขอได้');
}

try {
    $method = new ReflectionMethod($className, $action);

    // Only expose public instance methods as HTTP actions.
    if (
        !$method->isPublic()
        || $method->isStatic()
        || str_starts_with($action, '_')
        || $method->getDeclaringClass()->getName() !== $className
    ) {
        throw new ReflectionException('Action is not publicly routable');
    }

    $controller = new $className();
    $controller->{$action}();
} catch (ReflectionException $e) {
    http_response_code(404);
    exit('ไม่พบหน้าที่ร้องขอ');
} catch (Throwable $e) {
    // Never leak stack traces, SQL, paths, tokens, or server internals to users.
    error_log(sprintf(
        'Unhandled application error [%s::%s]: %s in %s:%d',
        $className,
        $action,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    http_response_code(500);
    exit('เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่อีกครั้ง');
}
