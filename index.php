<?php
// ที่อยู่ไฟล์: index.php (ไฟล์นอกสุดของโปรเจกต์)

// 🌟 1. เริ่มต้น Session และตั้งค่าพื้นฐาน
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Bangkok');

// 🌟 2. รับค่า Controller (c) และ Action (a) จาก URL
$c = isset($_GET['c']) && !empty($_GET['c']) ? strtolower(trim($_GET['c'])) : 'auth';
$a = isset($_GET['a']) && !empty($_GET['a']) ? strtolower(trim($_GET['a'])) : 'index';

// ถ้าไม่ได้ล็อกอิน และพยายามเข้าหน้าอื่นที่ไม่ใช่ auth ให้เด้งกลับไปหน้า login
if ($c !== 'auth' && !isset($_SESSION['user'])) {
    header("Location: index.php?c=auth&a=index");
    exit;
}

// 🌟 3. ระบบนำทางอัจฉริยะ (Auto Routing)
// แปลงค่า c=swap เป็นคลาส SwapController และไฟล์ controllers/SwapController.php โดยอัตโนมัติ
$className = ucfirst($c) . 'Controller';
$controllerFile = 'controllers/' . $className . '.php';

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    
    if (class_exists($className)) {
        $controller = new $className();
        
        // รันฟังก์ชันตามค่า a
        if (method_exists($controller, $a)) {
            $controller->$a();
        } else if (method_exists($controller, 'index')) {
            $controller->index();
        } else {
            die("<div style='padding:30px; font-family:sans-serif;'><h2>⚠️ ระบบทำงานผิดพลาด</h2><p>พบไฟล์ <b>{$controllerFile}</b> แต่ไม่มีฟังก์ชัน <b>{$a}()</b> อยู่ภายในคลาส</p></div>");
        }
    } else {
        die("<div style='padding:30px; font-family:sans-serif;'><h2>⚠️ โค้ดผิดพลาด</h2><p>พบไฟล์ <b>{$controllerFile}</b> แต่ไม่พบคำสั่ง <code>class {$className} { ... }</code> อยู่ภายใน กรุณาตรวจสอบการคัดลอกโค้ด</p></div>");
    }
} else {
    // 🌟 4. กรณีหาไฟล์ไม่เจอ (แสดงหน้า 404 ชัดเจนว่าขาดไฟล์อะไร)
    echo "<div style='display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh; font-family:sans-serif; background-color:#f8fafc; color:#334155;'>
            <h1 style='color:#ef4444; font-size: 120px; margin:0; line-height:1;'>404</h1>
            <h2 style='margin-bottom: 10px;'>ไม่พบหน้าเว็บ (Page Not Found)</h2>
            <div style='background:#fee2e2; color:#b91c1c; padding:15px 25px; border-radius:10px; margin-bottom:20px; text-align:center;'>
                ไม่พบไฟล์: <b>{$controllerFile}</b> <br>
                <small>กรุณาสร้างไฟล์นี้ในโฟลเดอร์ controllers</small>
            </div>
            <a href='index.php?c=dashboard' style='padding:12px 30px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:30px; font-weight:bold; box-shadow:0 4px 10px rgba(13,110,253,0.3); transition:all 0.3s;'>กลับหน้าหลัก (แดชบอร์ด)</a>
          </div>";
    exit;
}
?>