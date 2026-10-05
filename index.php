<?php
// ที่อยู่ไฟล์: index.php (ไฟล์นอกสุดของโปรเจกต์)

require_once 'config/security.php';
require_once 'lib/AppMonitor.php';
require_once 'lib/PerformanceMonitor.php';

// 🌟 1. เริ่มต้น Session และตั้งค่าพื้นฐาน
security_start_session();
security_send_headers();
AppMonitor::register();
PerformanceMonitor::register();

date_default_timezone_set('Asia/Bangkok');

// 🌟 2. รับค่า Controller (c) และ Action (a) จาก URL
$c = strtolower(trim((string)($_GET['c'] ?? 'auth')));
$a = strtolower(trim((string)($_GET['a'] ?? 'index')));

function renderRouteError(int $status, string $title, string $message): void {
    http_response_code($status);
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo "<!doctype html><html lang='th'><head><meta charset='utf-8'>"
       . "<meta name='viewport' content='width=device-width, initial-scale=1'>"
       . "<title>{$safeTitle}</title>"
       . "<style>body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#f8fafc;color:#334155}"
       . ".box{min-height:100vh;display:grid;place-items:center;padding:24px}.card{max-width:560px;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:28px;box-shadow:0 18px 48px rgba(15,23,42,.08)}"
       . "h1{margin:0 0 10px;color:#b91c1c;font-size:clamp(2rem,7vw,4.5rem)}p{line-height:1.65}a{display:inline-block;margin-top:12px;padding:10px 18px;border-radius:999px;background:#0f6cbd;color:#fff;text-decoration:none;font-weight:700}</style>"
       . "</head><body><main class='box'><section class='card'><h1>{$status}</h1><h2>{$safeTitle}</h2><p>{$safeMessage}</p>"
       . "<a href='index.php?c=dashboard'>กลับหน้าหลัก</a></section></main></body></html>";
    exit;
}

if (!security_is_valid_route_token($c) || !security_is_valid_route_token($a)) {
    renderRouteError(400, 'คำขอไม่ถูกต้อง', 'รูปแบบเส้นทางที่ร้องขอไม่ถูกต้อง');
}

// Public routes are intentionally narrow.
// Verification exposes only minimal official-document metadata; roster contents remain private.
$publicVerifyActions = ['index', 'revision'];
$isPublicRoute = $c === 'auth'
    || ($c === 'verify' && in_array($a, $publicVerifyActions, true))
    || ($c === 'health' && $a === 'index');

if (!$isPublicRoute && !isset($_SESSION['user'])) {
    header("Location: index.php?c=auth&a=index");
    exit;
}

// 🌟 3. ระบบนำทางแบบตรวจสอบเส้นทางและ callable method
$className = ucfirst($c) . 'Controller';
$controllerFile = 'controllers/' . $className . '.php';

if (!is_file($controllerFile)) {
    renderRouteError(404, 'ไม่พบหน้าเว็บ', 'ไม่พบ Controller สำหรับหน้าที่ร้องขอ');
}

require_once $controllerFile;

if (!class_exists($className, false)) {
    renderRouteError(500, 'ระบบทำงานผิดพลาด', 'โครงสร้าง Controller ไม่ถูกต้อง');
}

$controller = new $className();

// is_callable ป้องกันการเรียก private/protected helper โดยตรงผ่าน ?a=...
if (!is_callable([$controller, $a]) || str_starts_with($a, '__')) {
    renderRouteError(404, 'ไม่พบคำสั่ง', 'ไม่พบ Action ที่สามารถเรียกใช้งานได้');
}

$controller->$a();

