<?php
// Roster Pro front controller / application router.

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

date_default_timezone_set('Asia/Bangkok');

$c = strtolower(trim((string)($_GET['c'] ?? 'auth')));
$a = strtolower(trim((string)($_GET['a'] ?? 'index')));

function roster_render_route_error(int $status, string $title, string $message): void
{
    http_response_code($status);

    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo "<!doctype html><html lang='th'><head><meta charset='utf-8'>"
       . "<meta name='viewport' content='width=device-width,initial-scale=1'>"
       . "<title>{$safeTitle}</title>"
       . "<style>"
       . "body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#f8fafc;color:#334155}"
       . ".wrap{min-height:100vh;display:grid;place-items:center;padding:24px}"
       . ".card{width:min(560px,100%);box-sizing:border-box;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:28px;box-shadow:0 18px 48px rgba(15,23,42,.08)}"
       . "h1{margin:0 0 8px;color:#b91c1c;font-size:clamp(2rem,7vw,4rem)}"
       . "h2{margin:0 0 10px;color:#0f172a}p{line-height:1.65}"
       . "a{display:inline-block;margin-top:12px;padding:10px 18px;border-radius:999px;background:#1677ff;color:#fff;text-decoration:none;font-weight:700}"
       . "</style></head><body><main class='wrap'><section class='card'>"
       . "<h1>{$status}</h1><h2>{$safeTitle}</h2><p>{$safeMessage}</p>"
       . "<a href='index.php?c=dashboard&a=index'>กลับหน้าหลัก</a>"
       . "</section></main></body></html>";
    exit;
}

$routePattern = '/^[a-z][a-z0-9_]{0,63}$/';
if (!preg_match($routePattern, $c) || !preg_match($routePattern, $a)) {
    roster_render_route_error(400, 'คำขอไม่ถูกต้อง', 'รูปแบบ Controller หรือ Action ไม่ถูกต้อง');
}

// Keep public routes minimal. LINE webhook validates X-Line-Signature in its controller.
$publicRoutes = [
    'auth' => ['index', 'login', 'logout'],
    'linewebhook' => ['index'],
];

$isPublicRoute = isset($publicRoutes[$c]) && in_array($a, $publicRoutes[$c], true);

if (!$isPublicRoute && !isset($_SESSION['user'])) {
    header('Location: index.php?c=auth&a=index');
    exit;
}

$className = ucfirst($c) . 'Controller';
$controllerFile = __DIR__ . '/controllers/' . $className . '.php';

if (!is_file($controllerFile)) {
    roster_render_route_error(404, 'ไม่พบหน้าเว็บ', 'ไม่พบ Controller สำหรับหน้าที่ร้องขอ');
}

require_once $controllerFile;

if (!class_exists($className, false)) {
    roster_render_route_error(500, 'ระบบทำงานผิดพลาด', 'โครงสร้าง Controller ไม่ถูกต้อง');
}

$controller = new $className();

if (!is_callable([$controller, $a]) || str_starts_with($a, '__')) {
    roster_render_route_error(404, 'ไม่พบคำสั่ง', 'ไม่พบ Action ที่สามารถเรียกใช้งานได้');
}

$controller->$a();
