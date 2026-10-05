<?php
// ที่อยู่ไฟล์: views/layouts/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$app_name = "Roster Pro"; // ค่าเริ่มต้นกรณีหาฐานข้อมูลไม่เจอ
$app_subtitle = "ระบบจัดการตารางปฏิบัติงานและลางาน"; // 🌟 ค่าเริ่มต้นของชื่อย่อย

// ========================================================
// 🛑 ดึงตั้งค่าระบบจากฐานข้อมูล และตรวจสอบ Maintenance Mode
// ========================================================
require_once 'config/database.php';

// 🌟 นำเข้า LogsController เพื่อเก็บบันทึกประวัติกรณีถูกบังคับเตะออกจากระบบ
if (file_exists('controllers/LogsController.php')) {
    require_once 'controllers/LogsController.php';
}

try {
    $db_check = (new Database())->getConnection();
    
    // 🌟 ดึงข้อมูลตั้งค่าระบบทั้งหมด
    $stmt_settings = $db_check->query("SELECT setting_key, setting_value FROM system_settings");
    $sys_settings = [];
    while ($row = $stmt_settings->fetch(PDO::FETCH_ASSOC)) {
        $sys_settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // 🌟 กำหนดค่าชื่อแอปพลิเคชัน (ดึงจากฐานข้อมูล)
    if (!empty($sys_settings['app_name'])) {
        $app_name = $sys_settings['app_name'];
    }

    // 🌟 กำหนดค่าชื่อย่อย (ดึงจากฐานข้อมูล)
    if (!empty($sys_settings['app_subtitle'])) {
        $app_subtitle = $sys_settings['app_subtitle'];
    }
    
    // 🚨 ตรวจสอบโหมดปิดปรับปรุงระบบ และ สถานะการระงับบัญชี (เฉพาะเมื่อมีการล็อกอิน)
    if (isset($_SESSION['user'])) {
        
        $current_user_id = $_SESSION['user']['id'];
        
        // 1. เช็ค Maintenance Mode
        $is_maintenance = $sys_settings['maintenance_mode'] ?? '0';
        if ($is_maintenance === '1' && !in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN'])) {
            
            // 📝 บันทึก Log ก่อนล้าง Session
            if (class_exists('LogsController')) {
                LogsController::addLog($db_check, $current_user_id, 'LOGOUT', "ถูกบังคับออกจากระบบ (เข้าสู่ Maintenance Mode)");
            }
            
            session_unset();
            session_destroy();
            session_start(); 
            $_SESSION['error_msg'] = "🚧 ขณะนี้ระบบกำลังอยู่ในช่วงปิดปรับปรุง (Maintenance Mode) ขออภัยในความไม่สะดวกครับ";
            header("Location: index.php");
            exit;
        }

        // 2. 🌟 เช็คสถานะการระงับบัญชี (is_active) แบบ Real-time
        try {
            $stmt_status = $db_check->prepare("SELECT is_active FROM users WHERE id = ?");
            $stmt_status->execute([$current_user_id]);
            $user_status = $stmt_status->fetchColumn();

            // ถ้ายูสเซอร์ถูกลบ หรือ is_active กลายเป็น 0 ให้ทำลาย Session ทิ้ง (เตะออก)
            if ($user_status === false || $user_status == '0') {
                
                // 📝 บันทึก Log ก่อนล้าง Session
                if (class_exists('LogsController')) {
                    LogsController::addLog($db_check, $current_user_id, 'LOGOUT', "ถูกบังคับออกจากระบบ (บัญชีถูกระงับหรือลบออกจากฐานข้อมูล)");
                }
                
                session_unset();
                session_destroy();
                session_start(); 
                $_SESSION['login_error'] = "⛔ เซสชั่นหมดอายุ หรือบัญชีของคุณถูกระงับการใช้งานโดยผู้ดูแลระบบ";
                header("Location: index.php");
                exit;
            }
        } catch (Exception $e) {
            // ข้ามไปหากตาราง/คอลัมน์ยังไม่สมบูรณ์
        }
    }
} catch (Exception $e) {
    // ข้ามไปหากตาราง system_settings ยังไม่ถูกสร้าง
}

// 🌟 ดึงข้อมูลการแจ้งเตือน (Notifications) สำหรับโชว์ที่กระดิ่ง
$unread_count = 0;
$latest_notifications = [];

if (isset($_SESSION['user'])) {
    if (file_exists('models/NotificationModel.php')) {
        require_once 'models/NotificationModel.php';
        try {
            $notifModel = new NotificationModel($db_check);
            $user_id = $_SESSION['user']['id'];
            
            // ดึงจำนวนที่ยังไม่อ่าน
            $unread_count = $notifModel->getUnreadCount($user_id);
            // ดึงรายการล่าสุดมาโชว์แค่ 5 รายการใน Dropdown
            $latest_notifications = $notifModel->getUserNotifications($user_id, 5); 
        } catch (Exception $e) {
            // ปล่อยผ่านไปเพื่อไม่ให้ Header พัง
        }
    }
}

// ========================================================
// 🌟 Context ของหน้าปัจจุบันสำหรับ Topbar
// ========================================================
$current_controller = strtolower($_GET['c'] ?? 'dashboard');
$current_action = strtolower($_GET['a'] ?? 'index');

$page_context_map = [
    'dashboard' => ['หน้าภาพรวม', 'สรุปข้อมูลสำคัญและงานที่ต้องดำเนินการ', 'bi-grid-1x2-fill'],
    'roster' => ['ตารางปฏิบัติงาน', 'จัดเวร ตรวจสอบ และติดตามสถานะการอนุมัติ', 'bi-calendar3'],
    'report' => ['ติดตามการส่งเวร', 'ตรวจสอบสถานะและรายงานการจัดเวร', 'bi-graph-up-arrow'],
    'leave' => ['ระบบจัดการวันลา', 'ยื่นลา อนุมัติ และตรวจสอบสิทธิ์วันลา', 'bi-calendar2-check'],
    'staff' => ['จัดการบุคลากร', 'จัดการข้อมูลและสถานะบุคลากร', 'bi-people-fill'],
    'users' => ['ฐานข้อมูลบุคลากร', 'จัดการบัญชีผู้ใช้และข้อมูลบุคลากร', 'bi-database-fill-gear'],
    'hr' => ['ระบบงานบุคคล', 'บริหารข้อมูลบุคลากรและรายงานฝ่ายบุคคล', 'bi-person-vcard-fill'],
    'settings' => ['ตั้งค่าระบบ', 'จัดการค่าพื้นฐานและการทำงานของระบบ', 'bi-sliders'],
    'hospitals' => ['จัดการหน่วยบริการ', 'บริหารข้อมูล รพ.สต. และหน่วยบริการ', 'bi-building-fill'],
    'profile' => ['ข้อมูลของฉัน', 'โปรไฟล์และตารางปฏิบัติงานส่วนบุคคล', 'bi-person-circle'],
    'swap' => ['คำขอแลกเวร', 'ติดตามและจัดการคำขอแลกเวร', 'bi-arrow-left-right'],
    'notification' => ['การแจ้งเตือน', 'ติดตามรายการแจ้งเตือนล่าสุด', 'bi-bell-fill'],
];

$page_context = $page_context_map[$current_controller] ?? ['Roster Pro', 'ระบบบริหารจัดการตารางปฏิบัติงาน', 'bi-window-stack'];

if ($current_controller === 'profile' && $current_action === 'schedule') {
    $page_context = ['ปฏิทินเวรของฉัน', 'ตรวจสอบตารางปฏิบัติงานส่วนบุคคล', 'bi-calendar-heart-fill'];
}

$header_page_title = $page_context[0];
$header_page_subtitle = $page_context[1];
$header_page_icon = $page_context[2];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

    <script>
    // Central CSRF client helper:
    // - POST forms receive a hidden _csrf field at submit time.
    // - same-origin fetch/XHR-style requests receive X-CSRF-Token automatically.
    (() => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const token = meta ? meta.content : '';
        if (!token) return;

        const ensureFormToken = (form) => {
            if (!(form instanceof HTMLFormElement)) return;
            if ((form.method || 'get').toLowerCase() !== 'post') return;

            let input = form.querySelector('input[name="_csrf"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_csrf';
                form.appendChild(input);
            }
            input.value = token;
        };

        // Cover normal user submits and forms created after page load.
        document.addEventListener('submit', (event) => {
            ensureFormToken(event.target);
        }, true);

        // Cover legacy code that calls form.submit() directly (which normally
        // bypasses the submit event).
        const nativeFormSubmit = HTMLFormElement.prototype.submit;
        HTMLFormElement.prototype.submit = function() {
            ensureFormToken(this);
            return nativeFormSubmit.call(this);
        };

        // Pre-populate existing forms so browser-native and library submits work.
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(ensureFormToken);
        });

        const nativeFetch = window.fetch.bind(window);
        window.fetch = (input, init = {}) => {
            const requestUrl = input instanceof Request ? input.url : String(input);
            const url = new URL(requestUrl, window.location.href);
            const method = String(init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();

            if (url.origin === window.location.origin && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
                const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
                headers.set('X-CSRF-Token', token);
                init = { ...init, headers };
            }

            return nativeFetch(input, init);
        };
    })();
    </script>

    <!-- 🌟 ดึงชื่อแอปมาแสดงที่ชื่อแท็บเบราว์เซอร์ -->
    <title><?= htmlspecialchars($app_name) ?> - <?= htmlspecialchars($app_subtitle) ?></title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        :root {
            --primary-color: #0d6efd;
            --sidebar-bg: #ffffff;
            --navbar-height: 70px;
        }

        /* 🌟 บังคับความสูงเต็มจอ และซ่อน Scrollbar ของ Body เพื่อให้เลื่อนได้เฉพาะ <main> */
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background-color: #f4f6f9;
            height: 100vh;
            overflow: hidden;
            margin: 0; padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4, h5, h6, .fw-bold { font-family: 'Kanit', sans-serif; }

        .top-navbar {
            background-color: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            height: var(--navbar-height);
            z-index: 1050;
        }

        .nav-icon-btn {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #64748b; background-color: transparent; border: none;
            transition: all 0.2s; cursor: pointer; position: relative;
        }
        .nav-icon-btn:hover { background-color: #f1f5f9; color: var(--primary-color); }

        .notif-badge {
            position: absolute; top: 2px; right: 2px;
            background-color: #ef4444; color: white;
            font-size: 0.65rem; font-weight: bold;
            padding: 0.2em 0.5em; border-radius: 50rem;
            border: 2px solid #ffffff; display: none;
        }

        /* 🌟 Notification Dropdown Styles */
        .dropdown-menu-notif {
            width: 350px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border-radius: 1rem;
        }
        .notif-item {
            border-bottom: 1px solid #f1f5f9;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background-color: #f8fafc !important; }

        .profile-pill {
            display: flex; align-items: center; gap: 10px;
            padding: 4px 14px 4px 4px; border-radius: 50rem;
            text-decoration: none; color: #1e293b; transition: all 0.2s;
            cursor: pointer;
        }
        .profile-pill:hover, .profile-pill[aria-expanded="true"] {
            background-color: #ffffff; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .user-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white; display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 1.1rem;
        }

        /* 🌟 Custom Scrollbars */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
        
        .pwa-toast {
            position: fixed;
            right: 18px;
            bottom: 18px;
            left: auto;
            width: min(360px, calc(100vw - 32px));
            padding: 12px;
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr) auto;
            align-items: center;
            gap: 11px;
            background: rgba(255,255,255,.98);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 16px 40px rgba(15,23,42,.16);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            z-index: 1060;
            opacity: 0;
            visibility: hidden;
            transform: translateY(22px) scale(.98);
            pointer-events: none;
            transition: opacity .22s ease, transform .22s ease, visibility .22s ease;
        }

        .pwa-toast.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .pwa-toast-icon {
            width: 44px;
            height: 44px;
            border-radius: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg,#0ea5e9,#2563eb);
            color: #ffffff;
            font-size: 20px;
            box-shadow: 0 8px 18px rgba(37,99,235,.18);
        }

        .pwa-toast-copy {
            min-width: 0;
        }

        .pwa-toast-title {
            margin: 0;
            color: #0f172a;
            font-family: 'Kanit', sans-serif;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
        }

        .pwa-toast-subtitle {
            margin-top: 3px;
            color: #64748b;
            font-size: 11px;
            line-height: 1.35;
        }

        .pwa-toast-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pwa-toast-actions .btn {
            min-height: 34px;
            padding: 5px 10px;
            border-radius: 10px !important;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        @media (max-width: 575.98px) {
            .pwa-toast {
                right: 12px;
                bottom: 12px;
                width: calc(100vw - 24px);
                grid-template-columns: 40px minmax(0, 1fr);
            }

            .pwa-toast-icon {
                width: 40px;
                height: 40px;
            }

            .pwa-toast-actions {
                grid-column: 1 / -1;
                justify-content: flex-end;
            }
        }

        /* =========================================================
           ROSTER PRO - MODERN TOPBAR / SIDEBAR TOGGLE
           ========================================================= */
        .top-navbar {
            min-height: var(--navbar-height);
            padding-left: 14px !important;
            padding-right: 20px !important;
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid #e8eef5;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.03);
        }

        .nav-icon-btn {
            width: 42px;
            min-width: 42px;
            height: 42px;
            padding: 0;
            border: 1px solid #dbe5ef;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            color: #475569;
            font-size: 18px;
            transition:
                background-color .2s ease,
                border-color .2s ease,
                color .2s ease,
                transform .2s ease,
                box-shadow .2s ease;
        }

        .nav-icon-btn > i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .nav-icon-btn:hover {
            background: #f0f9ff;
            border-color: #bae6fd;
            color: #0284c7;
            box-shadow: 0 5px 14px rgba(14, 165, 233, 0.10);
            transform: translateY(-1px);
        }

        .nav-icon-btn:active {
            transform: scale(.96);
        }

        .sidebar-toggle-btn {
            background: #f8fafc;
        }

        .sidebar-toggle-btn:hover {
            background: #ecfeff;
            border-color: #a5f3fc;
            color: #0891b2;
        }

        .sidebar-toggle-btn i {
            font-size: 18px !important;
        }

        @media (max-width: 767.98px) {
            .top-navbar {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }

            .nav-icon-btn {
                width: 40px;
                min-width: 40px;
                height: 40px;
                border-radius: 11px;
            }
        }

        /* =========================================================
           ROSTER PRO - LAYOUT V2
           Brand zone + contextual topbar + boundary toggle
           ========================================================= */
        :root {
            --rp-sidebar-expanded: 260px;
            --rp-sidebar-collapsed: 76px;
            --rp-sidebar-brand-bg: #0b2d40;
            --rp-sidebar-brand-bg-2: #092638;
        }

        .top-navbar {
            padding: 0 !important;
            display: flex !important;
            align-items: stretch !important;
            background: #ffffff;
            overflow: visible;
        }

        .topbar-brand-zone {
            width: var(--rp-sidebar-expanded);
            min-width: var(--rp-sidebar-expanded);
            height: var(--navbar-height);
            padding: 0 16px;
            display: flex;
            align-items: center;
            background: linear-gradient(180deg, var(--rp-sidebar-brand-bg) 0%, var(--rp-sidebar-brand-bg-2) 100%);
            border-right: 1px solid rgba(255,255,255,.05);
            transition: width .25s cubic-bezier(.4,0,.2,1), min-width .25s cubic-bezier(.4,0,.2,1);
        }

        .topbar-brand-link {
            width: 100%;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 11px;
            text-decoration: none;
        }

        .topbar-brand-logo {
            width: 42px;
            min-width: 42px;
            height: 42px;
            border-radius: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg,#2dd4bf 0%,#22d3ee 100%);
            color: #073044;
            box-shadow: 0 8px 20px rgba(45,212,191,.18);
            font-size: 19px;
        }

        .topbar-brand-copy {
            min-width: 0;
            overflow: hidden;
            transition: opacity .18s ease, width .18s ease;
        }

        .topbar-brand-name {
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.15;
            white-space: nowrap;
        }

        .topbar-brand-subtitle {
            margin-top: 3px;
            color: #89a4b4;
            font-size: 9px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .topbar-main {
            min-width: 0;
            flex: 1;
            height: var(--navbar-height);
            padding: 0 14px 0 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .topbar-left {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-context {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-context-icon {
            width: 36px;
            min-width: 36px;
            height: 36px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
            font-size: 16px;
        }

        .topbar-context-copy {
            min-width: 0;
        }

        .topbar-context-title {
            margin: 0;
            color: #172033;
            font-family: 'Kanit', sans-serif;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .topbar-context-subtitle {
            margin-top: 3px;
            max-width: 520px;
            color: #7b8798;
            font-size: 10px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-toggle-btn {
            position: relative;
            z-index: 2;
            border-color: #d6e0ea;
            box-shadow: 0 4px 12px rgba(15,23,42,.06);
        }

        .sidebar-toggle-btn::after {
            content: '';
            position: absolute;
            left: -11px;
            top: 50%;
            width: 1px;
            height: 32px;
            transform: translateY(-50%);
            background: #e5eaf0;
        }

        body.sidebar-collapsed-ui .topbar-brand-zone {
            width: var(--rp-sidebar-collapsed);
            min-width: var(--rp-sidebar-collapsed);
            padding-left: 10px;
            padding-right: 10px;
            justify-content: center;
        }

        body.sidebar-collapsed-ui .topbar-brand-link {
            justify-content: center;
        }

        body.sidebar-collapsed-ui .topbar-brand-copy {
            display: none;
        }

        @media (max-width: 991.98px) {
            .topbar-context-subtitle { display: none; }
            .topbar-context-title { max-width: 220px; }
        }

        @media (max-width: 767.98px) {
            .topbar-brand-zone {
                display: none;
            }

            .topbar-main {
                width: 100%;
                height: var(--navbar-height);
                padding: 0 10px;
            }

            .topbar-context-icon {
                display: none;
            }

            .topbar-context-title {
                max-width: 170px;
                font-size: 14px;
            }
        }

        @media (max-width: 575.98px) {
            .topbar-context-copy { display: none; }
            .topbar-main { gap: 8px; }
        }
    </style>
</head>
<body>
<script>
    // ใช้สถานะ Sidebar ก่อนวาด Topbar เพื่อลดอาการกระพริบของ Layout
    if (localStorage.getItem('sidebarState') === 'collapsed') {
        document.body.classList.add('sidebar-collapsed-ui');
    }
</script>

<!-- 🌟 1. Top Navbar -->
<nav class="top-navbar w-100">
    <!-- Brand zone: จัดแนวให้ตรงกับ Sidebar -->
    <div class="topbar-brand-zone d-none d-md-flex">
        <a href="index.php?c=dashboard" class="topbar-brand-link">
            <span class="topbar-brand-logo"><i class="bi bi-heart-pulse-fill"></i></span>
            <span class="topbar-brand-copy">
                <span class="topbar-brand-name d-block"><?= htmlspecialchars($app_name) ?></span>
                <span class="topbar-brand-subtitle d-block"><?= htmlspecialchars($app_subtitle) ?></span>
            </span>
        </a>
    </div>

    <div class="topbar-main">
        <div class="topbar-left">
            <button class="nav-icon-btn d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" title="เปิดเมนู">
                <i class="bi bi-list fs-4"></i>
            </button>

            <button class="nav-icon-btn sidebar-toggle-btn d-none d-md-flex" id="sidebarToggleBtn" type="button" title="ย่อ/ขยายเมนู" aria-label="ย่อหรือขยายเมนูด้านข้าง">
                <i class="bi bi-layout-sidebar-inset" id="sidebarToggleIcon"></i>
            </button>

            <div class="topbar-context">
                <span class="topbar-context-icon"><i class="bi <?= htmlspecialchars($header_page_icon) ?>"></i></span>
                <span class="topbar-context-copy">
                    <span class="topbar-context-title d-block"><?= htmlspecialchars($header_page_title) ?></span>
                    <span class="topbar-context-subtitle d-block"><?= htmlspecialchars($header_page_subtitle) ?></span>
                </span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-1 gap-md-2">
        <?php if(isset($_SESSION['user'])): ?>
        
        <!-- 🔔 Notification Dropdown -->
        <div class="dropdown">
            <button class="nav-icon-btn position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell-fill fs-5"></i>
                <?php if($unread_count > 0): ?>
                    <span class="notif-badge" id="notifBadge" style="display: block;"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                <?php else: ?>
                    <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
                <?php endif; ?>
            </button>
            
            <div class="dropdown-menu dropdown-menu-end dropdown-menu-notif p-0 shadow border mt-2">
                <div class="notif-header bg-light p-3 border-bottom d-flex justify-content-between align-items-center" style="border-radius: 1rem 1rem 0 0;">
                    <h6 class="mb-0 fw-bolder text-dark"><i class="bi bi-bell-fill text-primary me-2"></i> แจ้งเตือน</h6>
                    <a href="index.php?c=notification" class="text-decoration-none small fw-bold text-primary hover-shadow">ดูทั้งหมด</a>
                </div>
                
                <div class="custom-scrollbar" style="max-height: 350px; overflow-y: auto; overflow-x: hidden; background-color: #fff;">
                    <?php if (empty($latest_notifications)): ?>
                        <!-- 🌟 กรณีที่ไม่มีการแจ้งเตือนเลย -->
                        <div class="text-center py-5">
                            <i class="bi bi-bell-slash fs-1 text-muted opacity-25 d-block mb-3"></i>
                            <p class="text-muted fw-medium small mb-3">ไม่มีการแจ้งเตือนใหม่ในขณะนี้</p>
                            <a href="index.php?c=notification" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">ดูประวัติทั้งหมด</a>
                        </div>
                    <?php else: ?>
                        <!-- 🌟 วนลูปแสดงการแจ้งเตือนล่าสุด 5 รายการ -->
                        <?php foreach ($latest_notifications as $notif): 
                            $is_read = $notif['is_read'] == 1;
                            $link = !empty($notif['link']) ? "index.php?c=notification&a=read&id={$notif['id']}&url=" . urlencode($notif['link']) : "index.php?c=notification&a=read&id={$notif['id']}";
                            
                            // ตกแต่งสีไอคอนตามประเภท
                            $type = strtoupper($notif['type'] ?? 'INFO');
                            $icon = 'bi-info-circle-fill'; $color = 'primary';
                            if ($type == 'SUCCESS' || $type == 'APPROVED') { $icon = 'bi-check-circle-fill'; $color = 'success'; }
                            elseif ($type == 'WARNING' || $type == 'PENDING') { $icon = 'bi-exclamation-triangle-fill'; $color = 'warning text-dark'; }
                            elseif ($type == 'DANGER' || $type == 'REJECTED') { $icon = 'bi-x-circle-fill'; $color = 'danger'; }
                            elseif ($type == 'SWAP') { $icon = 'bi-arrow-left-right'; $color = 'info text-dark'; }
                            elseif ($type == 'LEAVE') { $icon = 'bi-person-dash-fill'; $color = 'warning text-dark'; }
                        ?>
                            <a href="<?= $link ?>" class="text-decoration-none text-dark d-block">
                                <div class="p-3 d-flex align-items-start <?= !$is_read ? 'bg-primary bg-opacity-10' : 'bg-white' ?> notif-item" style="transition: all 0.2s;">
                                    <div class="bg-<?= $color ?> bg-opacity-10 text-<?= str_replace(' text-dark', '', $color) ?> rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                                        <i class="bi <?= $icon ?>"></i>
                                    </div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <div class="fw-bolder <?= !$is_read ? 'text-dark' : 'text-secondary' ?>" style="font-size: 13.5px; line-height: 1.3;">
                                                <?= htmlspecialchars($notif['title']) ?>
                                            </div>
                                            <small class="text-muted ms-2 text-nowrap" style="font-size: 10px;"><i class="bi bi-clock me-1"></i><?= date('d/m H:i', strtotime($notif['created_at'])) ?></small>
                                        </div>
                                        <p class="mb-0 text-muted" style="font-size: 12.5px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            <?= htmlspecialchars($notif['message']) ?>
                                        </p>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($latest_notifications)): ?>
                    <!-- ปุ่ม Footer ทำเครื่องหมายอ่านแล้ว -->
                    <div class="p-2 border-top bg-light text-center" style="border-radius: 0 0 1rem 1rem;">
                        <a href="index.php?c=notification&a=read_all" class="text-decoration-none text-muted fw-bold small d-block py-2" style="transition: color 0.2s;" onmouseover="this.classList.add('text-primary'); this.classList.remove('text-muted')" onmouseout="this.classList.add('text-muted'); this.classList.remove('text-primary')" onclick="return confirm('ยืนยันทำเครื่องหมายอ่านแล้วทั้งหมด?');">
                            <i class="bi bi-check2-all me-1"></i> ทำเครื่องหมายว่าอ่านแล้ว
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="vr d-none d-sm-block bg-secondary opacity-25 mx-2" style="width: 2px; height: 30px;"></div>

        <!-- 👤 Profile Dropdown -->
        <div class="dropdown">
            <a href="#" class="profile-pill" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar"><?= mb_substr($_SESSION['user']['name'], 0, 1, 'UTF-8') ?></div>
                <div class="d-none d-md-block text-start lh-1 pe-2">
                    <div class="fw-bold text-dark" style="font-size: 14px;"><?= htmlspecialchars($_SESSION['user']['name']) ?></div>
                    <div class="text-primary fw-bold" style="font-size: 11px;"><?= htmlspecialchars($_SESSION['user']['role']) ?></div>
                </div>
                <i class="bi bi-chevron-down d-none d-md-block text-muted me-2" style="font-size: 12px;"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2">
                <li class="px-3 py-2 border-bottom mb-2 d-md-none bg-light">
                    <div class="fw-bold text-dark" style="font-size: 14px;"><?= htmlspecialchars($_SESSION['user']['name']) ?></div>
                    <div class="text-primary fw-bold" style="font-size: 11px;"><?= htmlspecialchars($_SESSION['user']['role']) ?></div>
                </li>
                <li><a class="dropdown-item py-2" href="index.php?c=profile"><i class="bi bi-person-circle text-primary me-2"></i> โปรไฟล์ของฉัน</a></li>
                <li><a class="dropdown-item py-2" href="index.php?c=profile&a=schedule"><i class="bi bi-calendar-week text-success me-2"></i> ตารางเวรของฉัน</a></li>
                <?php if (in_array($_SESSION['user']['role'], ['ADMIN', 'SUPERADMIN'])): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2" href="index.php?c=settings&a=system"><i class="bi bi-gear text-secondary me-2"></i> ตั้งค่าระบบ</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="index.php?c=auth&a=logout" method="POST" class="m-0">
                        <button type="submit" class="dropdown-item text-danger fw-bold py-2">
                            <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
                        </button>
                    </form>
                </li>
            </ul>
        </div>
        <?php endif; ?>
        </div>
    </div>
</nav>

<!-- 🌟 Compact PWA Install Toast -->
<div id="pwaInstallToast" class="pwa-toast" role="status" aria-live="polite">
    <div class="pwa-toast-icon"><i class="bi bi-app-indicator"></i></div>
    <div class="pwa-toast-copy">
        <div class="pwa-toast-title">ติดตั้ง <?= htmlspecialchars($app_name) ?></div>
        <div class="pwa-toast-subtitle">เพิ่มไว้บนหน้าจอเพื่อเปิดใช้งานได้สะดวกขึ้น</div>
    </div>
    <div class="pwa-toast-actions">
        <button id="btnDismissPwa" class="btn btn-light border">ภายหลัง</button>
        <button id="btnInstallPwa" class="btn btn-primary shadow-sm">ติดตั้ง</button>
    </div>
</div>

<script>
    // ==========================================
    // 🌟 PWA & Notifications & DOM Setup
    // ==========================================
    
    // 1. ลงทะเบียน Service Worker (จำเป็นสำหรับ PWA)
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(registration => console.log('ServiceWorker ใช้งานได้! Scope: ', registration.scope))
                .catch(err => console.log('ServiceWorker ใช้งานไม่ได้: ', err));
        });
    }

    // 2. จัดการหน้าต่าง Install PWA
    let deferredPrompt;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        const dismissedAt = parseInt(localStorage.getItem('pwaDismissedAt') || '0', 10);
        const sevenDays = 7 * 24 * 60 * 60 * 1000;
        const canShow = !dismissedAt || (Date.now() - dismissedAt) > sevenDays;

        if (canShow) {
            setTimeout(() => {
                const toast = document.getElementById('pwaInstallToast');
                if (toast) toast.classList.add('show');
            }, 2500);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => { document.body.appendChild(modal); });

        const btnInstall = document.getElementById('btnInstallPwa');
        if(btnInstall) {
            btnInstall.addEventListener('click', async () => {
                document.getElementById('pwaInstallToast').classList.remove('show');
                if (deferredPrompt) { 
                    deferredPrompt.prompt(); 
                    deferredPrompt = null; 
                }
            });
        }

        const btnDismiss = document.getElementById('btnDismissPwa');
        if (btnDismiss) {
            btnDismiss.addEventListener('click', () => {
                const toast = document.getElementById('pwaInstallToast');
                if (toast) toast.classList.remove('show');
                localStorage.setItem('pwaDismissedAt', String(Date.now()));
            });
        }
    });

    // 3. ระบบเช็คการแจ้งเตือน Real-time
    <?php if(isset($_SESSION['user'])): ?>
    function checkNewNotifications() {
        fetch('index.php?c=ajax&a=check_new_notif').then(res => res.json()).then(data => {
            if(data.status === 'success') {
                const badge = document.getElementById('notifBadge');
                if(badge) {
                    if(data.unread_count > 0) { 
                        badge.innerText = data.unread_count > 99 ? '99+' : data.unread_count; 
                        badge.style.display = 'block'; 
                    } else { 
                        badge.style.display = 'none'; 
                    }
                }
            }
        }).catch(() => {});
    }
    
    // เช็คข้อความแจ้งเตือนใหม่ทุกๆ 1 นาทีแบบเบื้องหลัง (Background check)
    setInterval(checkNewNotifications, 60000);
    <?php endif; ?>
</script>

<!-- 🌟 2. Layout Wrapper: ล็อกความสูงเพื่อป้องกันเลย์เอาท์แตก -->
<div class="d-flex w-100 overflow-hidden" style="height: calc(100vh - 70px);">
    <!-- 💡 ไฟล์ sidebar.php จะถูกแทรกต่อจากบรรทัดนี้ -->