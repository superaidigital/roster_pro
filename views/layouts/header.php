<?php
// ที่อยู่ไฟล์: views/layouts/header.php
require_once 'config/security.php';
security_start_session();

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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <!-- 🌟 ดึงชื่อแอปมาแสดงที่ชื่อแท็บเบราว์เซอร์ -->
    <title><?= htmlspecialchars($app_name) ?> - <?= htmlspecialchars($app_subtitle) ?></title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#f8fbfd">
    <script id="rp-theme-prepaint">
    (() => {
      try {
        const saved = localStorage.getItem('rp-theme');
        const theme = saved === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
      } catch (_) {
        document.documentElement.setAttribute('data-theme', 'light');
        document.documentElement.setAttribute('data-bs-theme', 'light');
      }
    })();
    </script>
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<meta name="csrf-token" content="<?= htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="public/css/style.css?v=20261004-health-v15">
    <link rel="stylesheet" href="public/css/ui-proportions.css?v=20261004-health-v15">
    <link rel="stylesheet" href="public/css/themes.css?v=20261004-theme-v4">
    <link rel="stylesheet" href="public/css/wizard.css?v=20261004-wizard-v3">
    <script src="public/js/responsive.js?v=20261004-health-v15" defer></script>
    <script src="public/js/progress.js?v=20261004-health-v15" defer></script>
    <script src="public/js/theme.js?v=20261004-theme-v4" defer></script>
    <script src="public/js/wizard.js?v=20261004-wizard-v3" defer></script>
</head>
<?php
$rpController = strtolower(trim($_GET['c'] ?? 'dashboard'));
$rpAction = strtolower(trim($_GET['a'] ?? 'index'));
$rpPageMap = [
    'dashboard' => ['หน้าภาพรวม', 'สรุปข้อมูลสำคัญและงานที่ต้องดำเนินการ', 'bi-grid-1x2-fill'],
    'roster' => ['ตารางปฏิบัติงาน', 'จัดเวร ตรวจสอบ และติดตามสถานะการอนุมัติ', 'bi-calendar3'],
    'field' => [
        $rpAction === 'followups' ? 'คิวติดตามงานเยี่ยมบ้าน' : 'เยี่ยมบ้านและงานชุมชน',
        $rpAction === 'followups'
            ? 'ติดตามงานครบกำหนด งานเกินกำหนด และรายการเสี่ยงสูง'
            : 'บันทึกงานภาคสนาม พิกัด สัญญาณชีพ และการติดตามผู้รับบริการ',
        $rpAction === 'followups' ? 'bi-list-check' : 'bi-house-heart-fill'
    ],
    'leave' => ['ระบบวันลา', 'ยื่นคำขอ ตรวจสอบสิทธิ์ และติดตามการอนุมัติ', 'bi-calendar2-minus-fill'],
    'report' => ['รายงานและติดตาม', 'ภาพรวมการส่งเวร ภาระงาน และข้อมูลประกอบการบริหาร', 'bi-bar-chart-line-fill'],
    'profile' => [$rpAction === 'schedule' ? 'ปฏิทินเวรของฉัน' : 'ข้อมูลส่วนบุคคล', $rpAction === 'schedule' ? 'ตรวจสอบวันเวรและกิจกรรมของคุณ' : 'จัดการข้อมูลประวัติและข้อมูลการทำงาน', $rpAction === 'schedule' ? 'bi-calendar-heart-fill' : 'bi-person-vcard-fill'],
    'staff' => ['บุคลากร', 'จัดการรายชื่อและข้อมูลบุคลากรในหน่วยบริการ', 'bi-people-fill'],
    'users' => ['ผู้ใช้งานและสิทธิ์', 'จัดการบัญชี สิทธิ์ และการเข้าถึงระบบ', 'bi-person-gear'],
    'hospitals' => ['หน่วยบริการ รพ.สต.', 'จัดการข้อมูลหน่วยบริการและเครือข่าย', 'bi-hospital-fill'],
    'settings' => ['ตั้งค่าระบบ', 'กำหนดค่าการใช้งานและข้อมูลส่วนกลาง', 'bi-sliders2'],
    'hr' => ['งานทรัพยากรบุคคล', 'ตรวจสอบและจัดการข้อมูลบุคลากร', 'bi-person-workspace'],
    'logs' => ['ประวัติการใช้งาน', 'ตรวจสอบกิจกรรมและเหตุการณ์ในระบบ', 'bi-clock-history'],
    'swap' => ['แลกเวร', 'ส่งคำขอและติดตามสถานะการแลกเวร', 'bi-arrow-left-right'],
    'notification' => ['การแจ้งเตือน', 'ติดตามรายการแจ้งเตือนและงานที่เกี่ยวข้อง', 'bi-bell-fill'],
];
$rpPage = $rpPageMap[$rpController] ?? ['Roster Pro', 'ระบบจัดการตารางปฏิบัติงาน', 'bi-window-stack'];
?>
<body>
<script id="rp-sidebar-prepaint-state">
    try {
        if (localStorage.getItem('sidebarState') === 'collapsed') {
            document.body.classList.add('rp-sidebar-collapsed');
        }
    } catch (e) {
        // Ignore storage restrictions and use the expanded layout.
    }
</script>
<div id="rpGlobalProgress" class="rp-global-progress" role="progressbar" aria-label="สถานะการประมวลผล" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
    <div class="rp-global-progress-track">
        <div id="rpGlobalProgressBar" class="rp-global-progress-bar"></div>
    </div>
    <div id="rpGlobalProgressLabel" class="rp-global-progress-label" aria-hidden="true"></div>
</div>
<div id="rpProgressLive" class="visually-hidden" aria-live="polite" aria-atomic="true"></div>

<!-- 🌟 1. Top Navbar -->
<nav class="top-navbar w-100 d-flex align-items-center justify-content-between px-3 px-md-4">
    <div class="rp-topbar-left d-flex align-items-center gap-2 gap-md-3 min-w-0">
        <button class="nav-icon-btn rp-mobile-menu-btn" id="mobileSidebarToggleBtn" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="เปิดเมนูนำทาง">
            <i class="bi bi-list fs-4"></i>
        </button>
        <button class="nav-icon-btn rp-desktop-menu-btn" id="sidebarToggleBtn" type="button" aria-label="ย่อหรือขยายเมนูด้านข้าง">
            <i class="bi bi-list fs-4"></i>
        </button>
        
        <div class="rp-page-context d-flex align-items-center gap-3 min-w-0">
            <div class="rp-page-icon d-none d-sm-grid">
                <i class="bi <?= htmlspecialchars($rpPage[2], ENT_QUOTES, 'UTF-8') ?>"></i>
            </div>
            <div class="min-w-0">
                <div class="rp-page-kicker">ROSTER PRO WORKSPACE</div>
                <h1 class="rp-page-title mb-0"><?= htmlspecialchars($rpPage[0], ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="rp-page-subtitle d-none d-md-block"><?= htmlspecialchars($rpPage[1], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
    </div>

    <div class="rp-topbar-actions d-flex align-items-center gap-1 gap-md-2">
        <?php if(isset($_SESSION['user'])): ?>
        <span class="rp-system-online d-none d-xl-inline-flex">ออนไลน์</span>
        
        <button type="button"
                class="nav-icon-btn rp-theme-toggle"
                data-rp-theme-toggle
                aria-pressed="false"
                aria-label="เปลี่ยนเป็นโหมดมืด"
                title="โหมดมืด">
            <i class="bi bi-moon-stars-fill" data-rp-theme-icon></i>
        </button>

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
                        <form action="index.php?c=notification&a=read_all" method="POST" class="m-0" onsubmit="return confirm('ยืนยันทำเครื่องหมายอ่านแล้วทั้งหมด?');">
                            <?= security_csrf_input() ?>
                            <button type="submit" class="btn btn-link text-decoration-none text-muted fw-bold small d-block py-2 w-100 text-start border-0 bg-transparent" style="transition: color 0.2s;" onmouseover="this.classList.add('text-primary'); this.classList.remove('text-muted')" onmouseout="this.classList.add('text-muted'); this.classList.remove('text-primary')">
                            <i class="bi bi-check2-all me-1"></i> ทำเครื่องหมายว่าอ่านแล้ว
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="vr d-none d-sm-block bg-secondary opacity-25 mx-2" style="width: 2px; height: 30px;"></div>

        <!-- 👤 Profile Dropdown -->
        <div class="dropdown">
            <a href="#" class="profile-pill" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar"><?= mb_substr($_SESSION['user']['name'], 0, 1, 'UTF-8') ?></div>
                <div class="rp-profile-meta d-none d-md-block text-start lh-1 pe-2">
                    <div class="fw-bold text-dark" style="font-size: 14px;"><?= htmlspecialchars($_SESSION['user']['name']) ?></div>
                    <div class="text-primary fw-bold" style="font-size: 11px;"><?= htmlspecialchars($_SESSION['user']['role']) ?></div>
                </div>
                <i class="rp-profile-chevron bi bi-chevron-down d-none d-md-block text-muted me-2" style="font-size: 12px;"></i>
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
                <li><a class="dropdown-item text-danger fw-bold py-2" href="index.php?c=auth&a=logout"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</nav>

<!-- 🌟 PWA Toast -->
<div id="pwaInstallToast" class="pwa-toast">
    <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center fs-4 shadow-sm" style="width: 45px; height: 45px;"><i class="bi bi-app-indicator"></i></div>
    <div class="flex-grow-1">
        <h6 class="fw-bold mb-1" style="font-size: 15px;">ติดตั้ง <?= htmlspecialchars($app_name) ?></h6>
        <div class="text-muted" style="font-size: 12px;">เพิ่มลงหน้าจอหลักเพื่อใช้งานเต็มจอ</div>
    </div>
    <div class="d-flex flex-column gap-2">
        <button id="btnInstallPwa" class="btn btn-sm btn-primary fw-bold rounded-pill px-3 shadow-sm">ติดตั้ง</button>
        <button id="btnDismissPwa" class="btn btn-sm btn-light text-muted rounded-pill px-3 border" style="font-size: 11px;">ภายหลัง</button>
    </div>
</div>

<script>
    // ==========================================
    // 🌟 PWA & Notifications & DOM Setup
    // ==========================================
    // 1. ลงทะเบียน Service Worker (Production only)
    // บน localhost ปิด Service Worker และล้าง cache อัตโนมัติ เพื่อไม่ให้ cache เก่ารบกวนการพัฒนา
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', async () => {
            const isLocalDev = ['localhost', '127.0.0.1'].includes(window.location.hostname);

            if (isLocalDev) {
                try {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    await Promise.all(registrations.map(registration => registration.unregister()));

                    if ('caches' in window) {
                        const cacheNames = await caches.keys();
                        await Promise.all(
                            cacheNames
                                .filter(name => name.toLowerCase().startsWith('roster'))
                                .map(name => caches.delete(name))
                        );
                    }

                    console.info('Roster Pro dev mode: Service Worker และ cache เก่าถูกปิดบน localhost');
                } catch (err) {
                    console.warn('ไม่สามารถล้าง Service Worker ในโหมดพัฒนาได้:', err);
                }
                return;
            }

            navigator.serviceWorker.register('sw.js?v=6', { updateViaCache: 'none' })
                .then(registration => registration.update())
                .then(() => console.log('ServiceWorker ใช้งานได้'))
                .catch(err => console.log('ServiceWorker ใช้งานไม่ได้:', err));
        });
    }

    // 2. จัดการหน้าต่าง Install PWA
    let deferredPrompt;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault(); 
        deferredPrompt = e;
        if(!sessionStorage.getItem('pwaDismissed')) {
            setTimeout(() => { 
                const toast = document.getElementById('pwaInstallToast');
                if(toast) toast.classList.add('show'); 
            }, 3000);
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
        if(btnDismiss) {
            btnDismiss.addEventListener('click', () => {
                document.getElementById('pwaInstallToast').classList.remove('show');
                sessionStorage.setItem('pwaDismissed', 'true');
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
<div class="app-shell">
    <!-- 💡 ไฟล์ sidebar.php จะถูกแทรกต่อจากบรรทัดนี้ -->