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
        
        // 1. เช็ค Maintenance Mode
        $is_maintenance = $sys_settings['maintenance_mode'] ?? '0';
        if ($is_maintenance === '1' && !in_array($_SESSION['user']['role'], ['SUPERADMIN', 'ADMIN'])) {
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
            $stmt_status->execute([$_SESSION['user']['id']]);
            $user_status = $stmt_status->fetchColumn();

            // ถ้ายูสเซอร์ถูกลบ หรือ is_active กลายเป็น 0 ให้ทำลาย Session ทิ้ง (เตะออก)
            if ($user_status === false || $user_status == '0') {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(150%);
            background: rgba(255, 255, 255, 0.95); padding: 12px 16px; border-radius: 1rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12); display: flex; align-items: center; gap: 15px;
            z-index: 1060; transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            width: 90%; max-width: 380px; border: 1px solid #e2e8f0;
        }
        .pwa-toast.show { transform: translateX(-50%) translateY(0); }
    </style>
</head>
<body>

<!-- 🌟 1. Top Navbar -->
<nav class="top-navbar w-100 d-flex align-items-center justify-content-between px-3 px-md-4">
    <div class="d-flex align-items-center gap-2 gap-md-3">
        <button class="nav-icon-btn d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="bi bi-list fs-4"></i>
        </button>
        <button class="nav-icon-btn d-none d-md-flex" id="sidebarToggleBtn" type="button">
            <i class="bi bi-list fs-4"></i>
        </button>
        
        <!-- 🌟 โลโก้และชื่อระบบ -->
        <a href="index.php?c=dashboard" class="text-decoration-none d-flex align-items-center gap-2 ps-1">
            <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width:40px; height:40px;">
                <i class="bi bi-calendar2-check-fill fs-5"></i>
            </div>
            <div class="d-none d-sm-block">
                <!-- ชื่อหลัก -->
                <h5 class="mb-0 fw-bold text-primary" style="line-height: 1.2; letter-spacing: -0.5px;">
                    <?= htmlspecialchars($app_name) ?>
                </h5>
                <!-- ชื่อย่อย (Subtitle) -->
                <div class="text-muted fw-medium" style="font-size: 11px; letter-spacing: 0.3px; line-height: 1;">
                    <?= htmlspecialchars($app_subtitle) ?>
                </div>
            </div>
        </a>
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