<?php
// ที่อยู่ไฟล์: views/layouts/sidebar.php

require_once 'config/database.php';
$db = (new Database())->getConnection();

$c = isset($_GET['c']) ? $_GET['c'] : 'dashboard';
$a = isset($_GET['a']) ? $_GET['a'] : 'index';
// ตัดช่องว่างและทำเป็นตัวพิมพ์ใหญ่ป้องกันข้อผิดพลาด
$role = strtoupper(trim($_SESSION['user']['role'] ?? 'STAFF'));

$allowed_controllers = [];

try {
    // 🌟 ดึงข้อมูลทั้งหมดมาเช็คใน PHP เพื่อหลีกเลี่ยง Error กรณีหาคอลัมน์ is_active ไม่เจอ
    $stmt = $db->query("SELECT * FROM system_menus ORDER BY id ASC");
    $all_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_menus as $menu) {
        // 1. เช็คสถานะการเปิด/ปิดเมนู (รองรับชื่อคอลัมน์หลายแบบ)
        $is_active = $menu['is_active'] ?? $menu['status'] ?? $menu['active'] ?? 1;
        if ($is_active == 0 || $is_active === '0') {
            continue; 
        }

        // 2. ตรวจสอบสิทธิ์ (Role)
        $roles_str = strtoupper($menu['allowed_roles'] ?? $menu['roles'] ?? $menu['permission'] ?? '');
        $roles_array = array_map('trim', explode(',', $roles_str));
        
        // ถ้า User มีสิทธิ์ในเมนูนี้
        if (in_array($role, $roles_array)) {
            
            // 3. หาส่วนลิงก์ (รองรับทุกชื่อคอลัมน์ที่อาจจะมีในฐานข้อมูล)
            $link = $menu['path'] ?? $menu['menu_link'] ?? $menu['url'] ?? $menu['menu_url'] ?? $menu['link'] ?? $menu['route'] ?? '';
            $link = strtolower(trim($link));
            
            if (preg_match('/(?:^|[?&])c=([^&]+)/', $link, $matches)) {
                $allowed_controllers[] = $matches[1];
            } 
            else if (!empty($link) && strpos($link, '=') === false && strpos($link, '?') === false) {
                // เก็บค่าจาก Path เดี่ยวๆ (เช่น 'staff', 'settings')
                $allowed_controllers[] = trim($link, '/ ');
            }
            
            // 🌟 4. ระบบสำรองฉุกเฉิน (Failsafe): ถ้าพังจนหา Path ไม่เจอ ให้เดาสิทธิ์จาก "ชื่อเมนู"
            $name = $menu['menu_name'] ?? $menu['name'] ?? $menu['title'] ?? '';
            if (strpos($name, 'บุคลากร') !== false && strpos($name, 'จัดการ') !== false) $allowed_controllers[] = 'staff';
            if (strpos($name, 'ฐานข้อมูลบุคลากร') !== false) $allowed_controllers[] = 'users';
            if (strpos($name, 'ตั้งค่าระบบ') !== false) $allowed_controllers[] = 'settings';
            if (strpos($name, 'รพ.สต.') !== false || strpos($name, 'หน่วยบริการ') !== false) $allowed_controllers[] = 'hospitals';
            if (strpos($name, 'วันลา') !== false) $allowed_controllers[] = 'leave';
            if (strpos($name, 'ตาราง') !== false || strpos($name, 'ปฏิบัติงาน') !== false) $allowed_controllers[] = 'roster';
            if (strpos($name, 'ติดตาม') !== false || strpos($name, 'ส่งเวร') !== false) $allowed_controllers[] = 'report';
            if (strpos($name, 'ประวัติ') !== false && strpos($name, 'ใช้งาน') !== false) $allowed_controllers[] = 'logs';
            if (strpos($name, 'HR') !== false || strpos($name, 'บุคคล') !== false) $allowed_controllers[] = 'hr';
        }
    }
} catch (Exception $e) {
    // Fallback: หากตารางระบบเมนูมีปัญหา ให้โหลดสิทธิ์พื้นฐาน
    error_log("Sidebar Menu Query Error: " . $e->getMessage());
    if (in_array($role, ['SUPERADMIN', 'ADMIN'])) {
        $allowed_controllers = ['roster', 'report', 'leave', 'data43', 'staff', 'users', 'settings', 'logs', 'hospitals', 'hr'];
    } else if ($role === 'HR') {
        $allowed_controllers = ['staff', 'users', 'hr'];
    } else if ($role === 'DIRECTOR') {
        $allowed_controllers = ['roster', 'report', 'leave', 'data43', 'staff', 'settings', 'hr'];
    } else if ($role === 'SCHEDULER') {
        $allowed_controllers = ['roster', 'report', 'leave', 'data43', 'staff'];
    } else {
        $allowed_controllers = ['roster', 'leave', 'data43'];
    }
}

$allowed_controllers = array_unique($allowed_controllers);

// โมดูลนำส่งข้อมูล 43 แฟ้ม: ใช้ได้กับทุกบทบาทปฏิบัติงาน ยกเว้น HR
if ($role !== 'HR') {
    $allowed_controllers[] = 'data43';
}

// 🌟 HARDCODE OVERRIDE: จัดการสิทธิ์ HR ให้แน่ชัด
if ($role === 'HR') {
    // 🌟 HR จะไม่ได้รับอนุญาตให้เข้าถึงหน้าหลัก 'dashboard' ปกติ
    $allowed_controllers = ['staff', 'users', 'hr', 'profile'];
} else {
    // ตำแหน่งอื่น อนุญาตให้เข้าถึงหน้าพื้นฐานเสมอ
    $allowed_controllers = array_merge($allowed_controllers, ['dashboard', 'profile']);
}

$allowed_controllers = array_unique($allowed_controllers);
?>

<style>
    /* 🌟 CSS สำหรับ Sidebar */
    #desktopSidebar {
        width: 260px; min-width: 260px; max-width: 260px;
        flex-shrink: 0; height: 100%;
        background-color: var(--sidebar-bg, #ffffff); border-right: 1px solid #e2e8f0;
        overflow-y: auto; overflow-x: hidden;
        transition: width 0.3s ease; z-index: 1040;
    }
    #desktopSidebar.collapsed { width: 80px; min-width: 80px; max-width: 80px; }
    
    /* ซ่อนข้อความและลูกศรเวลาพับเมนู */
    #desktopSidebar.collapsed .sidebar-text, 
    #desktopSidebar.collapsed .sidebar-heading,
    #desktopSidebar.collapsed .dropdown-arrow { display: none !important; }
    
    /* จัดไอคอนให้อยู่กึ่งกลางเวลาพับเมนู */
    #desktopSidebar.collapsed .nav-link { justify-content: center !important; padding: 0.8rem 0 !important; }
    #desktopSidebar.collapsed .nav-link i { margin-right: 0 !important; font-size: 1.4rem !important; }
    
    /* ซ่อนเมนูย่อยเวลาพับ Sidebar */
    #desktopSidebar.collapsed .leave-dropdown-container ul,
    #desktopSidebar.collapsed .hr-dropdown-container ul { display: none !important; }

    .sidebar-menu { list-style: none; padding: 15px; margin: 0; display: flex; flex-direction: column; gap: 4px; }
    .nav-link { 
        display: flex; align-items: center; padding: 12px 15px; 
        color: #475569; border-radius: 10px; transition: all 0.2s ease; 
        font-weight: 500; text-decoration: none; white-space: nowrap;
    }
    .nav-link:hover { background-color: #f1f5f9; color: #0d6efd; }
    .nav-link.active { background-color: #eff6ff; color: #0d6efd; font-weight: 600; }
    .nav-link i { font-size: 1.25rem; margin-right: 12px; width: 24px; text-align: center; transition: transform 0.2s; }
    .nav-link:hover i { transform: scale(1.1); }
    
    .sidebar-heading { 
        font-size: 0.75rem; font-weight: 700; color: #94a3b8; 
        text-transform: uppercase; padding: 15px 15px 5px; letter-spacing: 0.5px; 
    }
    
    /* แอนิเมชันลูกศร Dropdown */
    .dropdown-arrow { transition: transform 0.3s ease; font-size: 0.8rem; }
    [aria-expanded="true"] .dropdown-arrow { transform: rotate(180deg); }
    
    /* สไตล์สำหรับเมนูย่อย */
    .submenu-item { padding: 8px 15px 8px 45px !important; font-size: 14px; }
    .submenu-item.active { background-color: transparent !important; color: #0d6efd; font-weight: 600; }
    .submenu-item.active::before {
        content: ''; position: absolute; left: 20px; width: 6px; height: 6px; 
        background-color: #0d6efd; border-radius: 50%;
    }
</style>

<style>
    /* =========================================================
       ROSTER PRO - MODERN SIDEBAR 2026
       ========================================================= */
    :root {
        --rp-sidebar-expanded: 260px;
        --rp-sidebar-collapsed: 78px;
        --rp-sidebar-dark: #0b2d40;
        --rp-sidebar-dark-2: #092638;
        --rp-icon-size: 42px;
        --rp-active: #14b8a6;
        --rp-active-soft: rgba(20, 184, 166, .14);
        --rp-sidebar-text: #d5e2eb;
        --rp-sidebar-muted: #7f98a8;
    }

    #desktopSidebar {
        width: var(--rp-sidebar-expanded);
        min-width: var(--rp-sidebar-expanded);
        max-width: var(--rp-sidebar-expanded);
        position: relative;
        background: linear-gradient(180deg, var(--rp-sidebar-dark) 0%, var(--rp-sidebar-dark-2) 100%) !important;
        border-right: 1px solid rgba(255,255,255,.05) !important;
        overflow: hidden !important;
        transition:
            width .25s cubic-bezier(.4,0,.2,1),
            min-width .25s cubic-bezier(.4,0,.2,1),
            max-width .25s cubic-bezier(.4,0,.2,1);
    }

    #desktopSidebar.collapsed {
        width: var(--rp-sidebar-collapsed);
        min-width: var(--rp-sidebar-collapsed);
        max-width: var(--rp-sidebar-collapsed);
    }

    #desktopSidebar .sidebar-scroll-area {
        overflow-y: auto;
        overflow-x: hidden;
    }

    #desktopSidebar .sidebar-menu {
        list-style: none;
        padding: 13px 10px;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    #desktopSidebar .nav-item {
        position: relative;
        width: 100%;
    }

    #desktopSidebar .nav-link {
        width: 100%;
        min-height: 50px;
        padding: 4px 8px;
        display: flex;
        align-items: center;
        border-radius: 13px;
        color: var(--rp-sidebar-text);
        background: transparent;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
        transition:
            background-color .2s ease,
            color .2s ease,
            transform .2s ease,
            box-shadow .2s ease;
    }

    #desktopSidebar .nav-link:hover {
        background: rgba(255,255,255,.065);
        color: #ffffff;
    }

    /* กล่อง Icon ทุกเมนูให้ขนาดเท่ากัน */
    #desktopSidebar .nav-link > i,
    #desktopSidebar .nav-link > div > i:first-child {
        width: var(--rp-icon-size) !important;
        min-width: var(--rp-icon-size) !important;
        height: var(--rp-icon-size) !important;
        margin: 0 11px 0 0 !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: rgba(255,255,255,.06);
        color: #a8bdca !important;
        font-size: 18px !important;
        line-height: 1 !important;
        transition:
            background .2s ease,
            color .2s ease,
            transform .2s ease,
            box-shadow .2s ease;
    }

    #desktopSidebar .nav-link > div {
        display: flex;
        align-items: center;
        min-width: 0;
        flex: 1;
    }

    #desktopSidebar .nav-link:hover > i,
    #desktopSidebar .nav-link:hover > div > i:first-child {
        background: rgba(255,255,255,.10);
        color: #ffffff !important;
        transform: translateY(-1px);
    }

    #desktopSidebar .nav-link.active {
        position: relative;
        background: linear-gradient(135deg, rgba(20,184,166,.20), rgba(6,182,212,.10));
        color: #ffffff;
        box-shadow:
            inset 0 0 0 1px rgba(45,212,191,.34),
            0 7px 18px rgba(0,0,0,.08);
    }

    #desktopSidebar .nav-link.active::before {
        content: '';
        position: absolute;
        left: -5px;
        top: 12px;
        bottom: 12px;
        width: 3px;
        background: #2dd4bf;
        border-radius: 0 4px 4px 0;
        box-shadow: 0 0 10px rgba(45,212,191,.55);
    }

    #desktopSidebar .nav-link.active > i,
    #desktopSidebar .nav-link.active > div > i:first-child {
        background: linear-gradient(135deg, #14b8a6, #06b6d4);
        color: #ffffff !important;
        box-shadow: 0 6px 15px rgba(20,184,166,.25);
    }

    #desktopSidebar .sidebar-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 14px;
        font-weight: 600;
    }

    #desktopSidebar .sidebar-heading {
        padding: 15px 11px 6px;
        color: #718697;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .08em;
    }

    #desktopSidebar .dropdown-arrow {
        margin-left: auto !important;
        margin-right: 5px !important;
        width: auto !important;
        min-width: auto !important;
        height: auto !important;
        background: transparent !important;
        color: #78909f !important;
        font-size: 11px !important;
        box-shadow: none !important;
        transition: transform .25s ease;
    }

    #desktopSidebar [aria-expanded="true"] .dropdown-arrow {
        transform: rotate(180deg);
    }

    /* Submenu ตอน Sidebar ขยาย */
    #desktopSidebar:not(.collapsed) .collapse .sidebar-menu {
        margin: 3px 0 4px 53px !important;
        padding: 4px !important;
        border-left: 1px solid rgba(255,255,255,.10);
    }

    #desktopSidebar:not(.collapsed) .submenu-item {
        min-height: 36px;
        padding: 7px 10px !important;
        border-radius: 9px;
        font-size: 12px;
        color: #9db0bd;
        background: transparent;
    }

    #desktopSidebar:not(.collapsed) .submenu-item:hover {
        background: rgba(255,255,255,.06);
        color: #ffffff;
    }

    #desktopSidebar:not(.collapsed) .submenu-item.active {
        color: #5eead4 !important;
        background: rgba(20,184,166,.08) !important;
    }

    #desktopSidebar .submenu-item.active::before {
        display: none;
    }

    /* =====================================================
       COLLAPSED SIDEBAR
       ===================================================== */
    #desktopSidebar.collapsed .sidebar-menu {
        padding: 12px 8px;
        align-items: center;
    }

    #desktopSidebar.collapsed .sidebar-text,
    #desktopSidebar.collapsed .sidebar-heading,
    #desktopSidebar.collapsed .dropdown-arrow {
        display: none !important;
    }

    #desktopSidebar.collapsed > .sidebar-scroll-area > .sidebar-menu > .nav-item {
        width: 54px;
        min-width: 54px;
    }

    #desktopSidebar.collapsed .sidebar-menu > .nav-item > .nav-link {
        width: 54px;
        height: 54px;
        min-height: 54px;
        padding: 6px !important;
        justify-content: center !important;
        border-radius: 14px;
    }

    #desktopSidebar.collapsed .nav-link > i,
    #desktopSidebar.collapsed .nav-link > div > i:first-child {
        width: 42px !important;
        min-width: 42px !important;
        height: 42px !important;
        margin: 0 !important;
        border-radius: 12px;
        font-size: 18px !important;
    }

    #desktopSidebar.collapsed .nav-link > div {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #desktopSidebar.collapsed .nav-link.active {
        background: rgba(20,184,166,.12);
    }

    /* ลบกฎเดิมที่ซ่อนเมนูย่อยทั้งหมดตอนพับ */
    #desktopSidebar.collapsed .leave-dropdown-container ul,
    #desktopSidebar.collapsed .hr-dropdown-container ul {
        display: flex !important;
    }

    /* Floating submenu - ใช้ fixed เพื่อไม่โดน scroll container ตัด */
    #desktopSidebar.collapsed .leave-dropdown-container > .collapse,
    #desktopSidebar.collapsed .hr-dropdown-container > .collapse {
        display: block !important;
        position: fixed;
        width: 238px;
        margin: 0 !important;
        padding: 8px;
        visibility: hidden;
        opacity: 0;
        pointer-events: none;
        transform: translateX(-6px);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow:
            0 18px 45px rgba(15,23,42,.18),
            0 3px 10px rgba(15,23,42,.06);
        z-index: 2000;
        transition:
            opacity .16s ease,
            transform .16s ease,
            visibility .16s ease;
    }

    #desktopSidebar.collapsed .leave-dropdown-container.flyout-open > .collapse,
    #desktopSidebar.collapsed .hr-dropdown-container.flyout-open > .collapse,
    #desktopSidebar.collapsed .leave-dropdown-container > .collapse:hover,
    #desktopSidebar.collapsed .hr-dropdown-container > .collapse:hover {
        visibility: visible;
        opacity: 1;
        pointer-events: auto;
        transform: translateX(0);
    }

    #desktopSidebar.collapsed .leave-dropdown-container > .collapse::before,
    #desktopSidebar.collapsed .hr-dropdown-container > .collapse::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 20px;
        width: 12px;
        height: 12px;
        background: #ffffff;
        border-left: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        transform: rotate(45deg);
    }

    #desktopSidebar.collapsed .leave-dropdown-container > .collapse .sidebar-menu,
    #desktopSidebar.collapsed .hr-dropdown-container > .collapse .sidebar-menu {
        width: 100%;
        margin: 0 !important;
        padding: 0 !important;
        gap: 3px;
        align-items: stretch;
    }

    #desktopSidebar.collapsed .leave-dropdown-container > .collapse .nav-item,
    #desktopSidebar.collapsed .hr-dropdown-container > .collapse .nav-item {
        width: 100% !important;
        min-width: 100% !important;
    }

    #desktopSidebar.collapsed .submenu-item {
        display: flex !important;
        width: 100% !important;
        min-height: 38px !important;
        height: auto !important;
        padding: 8px 11px !important;
        justify-content: flex-start !important;
        border-radius: 9px !important;
        color: #475569 !important;
        background: transparent;
        font-size: 13px;
        font-weight: 500;
    }

    #desktopSidebar.collapsed .submenu-item:hover {
        background: #f0fdfa !important;
        color: #0f766e !important;
        transform: none;
    }

    #desktopSidebar.collapsed .submenu-item.active {
        background: #ecfdf5 !important;
        color: #0f766e !important;
        font-weight: 700;
    }

    /* Footer Logout */
    #desktopSidebar > .mt-auto {
        background: transparent !important;
        border-color: rgba(255,255,255,.07) !important;
    }

    #desktopSidebar > .mt-auto .nav-link {
        color: #fda4af !important;
    }

    /* Mobile ยังคงเป็น Offcanvas เดิม */
    @media (max-width: 767.98px) {
        #mobileSidebar .sidebar-menu {
            padding: 12px;
        }

        #mobileSidebar .nav-link {
            min-height: 48px;
            border-radius: 12px;
        }
    }
</style>

<style>
    /* =========================================================
       ROSTER PRO - SIDEBAR V2
       Equal icon grid + clean collapsed rail + generic flyout
       ========================================================= */
    :root {
        --rp-sidebar-expanded: 260px;
        --rp-sidebar-collapsed: 76px;
        --rp-menu-icon: 42px;
    }

    #desktopSidebar {
        width: var(--rp-sidebar-expanded) !important;
        min-width: var(--rp-sidebar-expanded) !important;
        max-width: var(--rp-sidebar-expanded) !important;
        background: linear-gradient(180deg,#0b2d40 0%,#092638 100%) !important;
        border-right: 1px solid rgba(255,255,255,.05) !important;
    }

    #desktopSidebar.collapsed {
        width: var(--rp-sidebar-collapsed) !important;
        min-width: var(--rp-sidebar-collapsed) !important;
        max-width: var(--rp-sidebar-collapsed) !important;
    }

    #desktopSidebar .sidebar-scroll-area {
        padding-top: 9px;
    }

    #desktopSidebar .sidebar-menu {
        gap: 6px;
        padding: 10px 9px 14px;
    }

    #desktopSidebar .sidebar-heading {
        margin: 4px 0 0;
        padding: 13px 9px 5px;
        font-size: 9px;
        color: #607f91;
        letter-spacing: .09em;
    }

    #desktopSidebar .nav-link {
        min-height: 50px;
        padding: 4px 7px !important;
        border-radius: 12px;
        gap: 0;
    }

    /* ทุก Icon ใช้กรอบเดียวกัน */
    #desktopSidebar .nav-link > i:not(.dropdown-arrow),
    #desktopSidebar .nav-link > div > i:first-child {
        width: var(--rp-menu-icon) !important;
        min-width: var(--rp-menu-icon) !important;
        height: var(--rp-menu-icon) !important;
        margin: 0 10px 0 0 !important;
        border-radius: 11px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: rgba(255,255,255,.055) !important;
        color: #9db5c3 !important;
        font-size: 17px !important;
        line-height: 1 !important;
        flex: 0 0 var(--rp-menu-icon);
    }

    #desktopSidebar .nav-link:hover > i:not(.dropdown-arrow),
    #desktopSidebar .nav-link:hover > div > i:first-child {
        background: rgba(255,255,255,.10) !important;
        color: #ffffff !important;
        transform: translateY(-1px);
    }

    #desktopSidebar .nav-link.active {
        background: linear-gradient(135deg,rgba(20,184,166,.12),rgba(14,165,233,.06)) !important;
        color: #ffffff !important;
        box-shadow: inset 0 0 0 1px rgba(45,212,191,.16) !important;
    }

    /* Active indicator แบบบาง ไม่เป็นกรอบสองชั้น */
    #desktopSidebar .nav-link.active::before {
        content: '';
        position: absolute;
        left: 1px;
        top: 13px;
        bottom: 13px;
        width: 2px;
        border-radius: 4px;
        background: #2dd4bf;
        box-shadow: none;
    }

    #desktopSidebar .nav-link.active > i:not(.dropdown-arrow),
    #desktopSidebar .nav-link.active > div > i:first-child {
        background: linear-gradient(135deg,#0f766e,#0891b2) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(8,145,178,.16);
    }

    #desktopSidebar .sidebar-text {
        font-size: 13px;
        font-weight: 600;
    }

    #desktopSidebar .dropdown-arrow {
        width: 20px !important;
        min-width: 20px !important;
        height: 20px !important;
        margin: 0 2px 0 auto !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: transparent !important;
        color: #6e8999 !important;
        font-size: 10px !important;
        flex: 0 0 20px;
    }

    /* Expanded submenu */
    #desktopSidebar:not(.collapsed) .sidebar-dropdown-container > .collapse .sidebar-menu {
        margin: 2px 0 5px 51px !important;
        padding: 4px 0 4px 8px !important;
        align-items: stretch !important;
        border-left: 1px solid rgba(255,255,255,.10);
    }

    #desktopSidebar:not(.collapsed) .sidebar-dropdown-container .submenu-item {
        min-height: 34px;
        padding: 6px 10px !important;
        border-radius: 8px !important;
        color: #9eb1bd !important;
        font-size: 12px;
    }

    #desktopSidebar:not(.collapsed) .sidebar-dropdown-container .submenu-item:hover {
        color: #ffffff !important;
        background: rgba(255,255,255,.06) !important;
    }

    #desktopSidebar:not(.collapsed) .sidebar-dropdown-container .submenu-item.active {
        color: #5eead4 !important;
        background: rgba(20,184,166,.09) !important;
    }

    /* Collapsed rail */
    #desktopSidebar.collapsed .sidebar-menu {
        padding-left: 8px !important;
        padding-right: 8px !important;
        align-items: center;
    }

    #desktopSidebar.collapsed .sidebar-heading,
    #desktopSidebar.collapsed .sidebar-text,
    #desktopSidebar.collapsed .dropdown-arrow {
        display: none !important;
    }

    #desktopSidebar.collapsed > .sidebar-scroll-area > .sidebar-menu > .nav-item {
        width: 52px !important;
        min-width: 52px !important;
        max-width: 52px !important;
    }

    #desktopSidebar.collapsed > .sidebar-scroll-area > .sidebar-menu > .nav-item > .nav-link {
        width: 52px !important;
        min-width: 52px !important;
        height: 52px !important;
        min-height: 52px !important;
        padding: 5px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 13px !important;
    }

    #desktopSidebar.collapsed .nav-link > i:not(.dropdown-arrow),
    #desktopSidebar.collapsed .nav-link > div > i:first-child {
        width: var(--rp-menu-icon) !important;
        min-width: var(--rp-menu-icon) !important;
        height: var(--rp-menu-icon) !important;
        margin: 0 !important;
        flex: 0 0 var(--rp-menu-icon) !important;
    }

    #desktopSidebar.collapsed .nav-link > div {
        width: var(--rp-menu-icon) !important;
        min-width: var(--rp-menu-icon) !important;
        height: var(--rp-menu-icon) !important;
        flex: 0 0 var(--rp-menu-icon) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    /* Active ตอนย่อ: ใช้เฉพาะ icon + indicator เล็ก */
    #desktopSidebar.collapsed .nav-link.active {
        background: rgba(20,184,166,.07) !important;
        box-shadow: inset 0 0 0 1px rgba(45,212,191,.10) !important;
    }

    #desktopSidebar.collapsed .nav-link.active::before {
        left: -1px;
        top: 15px;
        bottom: 15px;
        width: 2px;
    }

    /* บอกผู้ใช้ว่าเมนูนี้มี Submenu เมื่อ Sidebar ถูกย่อ */
    #desktopSidebar.collapsed .sidebar-dropdown-container > .nav-link::after {
        content: '›';
        position: absolute;
        top: 5px;
        right: 2px;
        width: 15px;
        height: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #164e63;
        color: #67e8f9;
        border: 1px solid rgba(103,232,249,.34);
        font-family: Arial, sans-serif;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        box-shadow: 0 2px 5px rgba(0,0,0,.12);
        z-index: 3;
        transition: transform .18s ease, background .18s ease;
    }

    #desktopSidebar.collapsed .sidebar-dropdown-container:hover > .nav-link::after,
    #desktopSidebar.collapsed .sidebar-dropdown-container.flyout-open > .nav-link::after {
        transform: translateX(2px);
        background: #0e7490;
        color: #ffffff;
    }

    /* Generic floating submenu when collapsed */
    #desktopSidebar.collapsed .sidebar-dropdown-container > .collapse {
        display: block !important;
        position: fixed !important;
        width: 240px;
        margin: 0 !important;
        padding: 9px !important;
        visibility: hidden;
        opacity: 0;
        pointer-events: none;
        transform: translateX(-6px) scale(.985);
        transform-origin: left top;
        background: rgba(255,255,255,.985);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid #dce5ee;
        border-radius: 14px;
        box-shadow: 0 18px 42px rgba(15,23,42,.16), 0 3px 10px rgba(15,23,42,.05);
        z-index: 2000;
        transition: opacity .16s ease, transform .16s ease, visibility .16s ease;
    }

    #desktopSidebar.collapsed .sidebar-dropdown-container.flyout-open > .collapse,
    #desktopSidebar.collapsed .sidebar-dropdown-container > .collapse:hover {
        visibility: visible;
        opacity: 1;
        pointer-events: auto;
        transform: translateX(0) scale(1);
    }

    #desktopSidebar.collapsed .sidebar-dropdown-container > .collapse::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 19px;
        width: 12px;
        height: 12px;
        background: #ffffff;
        border-left: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        transform: rotate(45deg);
    }

    #desktopSidebar.collapsed .sidebar-dropdown-container > .collapse .sidebar-menu {
        width: 100%;
        margin: 0 !important;
        padding: 0 !important;
        gap: 3px !important;
        align-items: stretch !important;
    }

    #desktopSidebar.collapsed .sidebar-dropdown-container > .collapse .nav-item {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
    }

    #desktopSidebar.collapsed .sidebar-dropdown-container .submenu-item {
        width: 100% !important;
        min-height: 38px !important;
        height: auto !important;
        padding: 8px 11px !important;
        justify-content: flex-start !important;
        border-radius: 9px !important;
        color: #475569 !important;
        background: transparent !important;
        font-size: 13px !important;
        font-weight: 500;
    }

    #desktopSidebar.collapsed .sidebar-dropdown-container .submenu-item:hover {
        background: #f0fdfa !important;
        color: #0f766e !important;
    }

    #desktopSidebar.collapsed .sidebar-dropdown-container .submenu-item.active {
        background: #ecfdf5 !important;
        color: #0f766e !important;
        font-weight: 700;
    }

    /* Logout ให้อยู่ grid เดียวกับเมนู */
    #desktopSidebar > .mt-auto {
        padding: 10px 9px !important;
        background: transparent !important;
        border-color: rgba(255,255,255,.07) !important;
    }

    #desktopSidebar > .mt-auto .nav-link {
        min-height: 50px;
        padding: 4px 7px !important;
        color: #fda4af !important;
    }

    #desktopSidebar.collapsed > .mt-auto .nav-link {
        width: 52px !important;
        min-width: 52px !important;
        height: 52px !important;
        padding: 5px !important;
        justify-content: center !important;
    }

    #desktopSidebar.collapsed > .mt-auto .nav-link > i {
        width: var(--rp-menu-icon) !important;
        min-width: var(--rp-menu-icon) !important;
        height: var(--rp-menu-icon) !important;
        margin: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 11px !important;
        background: rgba(244,63,94,.10) !important;
        color: #fb7185 !important;
        font-size: 17px !important;
    }
</style>

<?php
// ฟังก์ชันสร้างเมนูด้านซ้าย เพื่อเรียกใช้ซ้ำทั้งแบบ Desktop และ Mobile
if (!function_exists('renderSidebarMenu')) {
    function renderSidebarMenu($c, $a, $role, $allowed_controllers) {
        ?>
        <ul class="sidebar-menu">
            
            <!-- 🌟 ซ่อนเมนูหน้าหลักและปฏิทินเวร สำหรับ HR 🌟 -->
            <?php if ($role !== 'HR'): ?>
            <li class="sidebar-heading">แดชบอร์ดสถิติ</li>
            <li class="nav-item">
                <a class="nav-link <?= ($c == 'dashboard') ? 'active' : '' ?>" href="index.php?c=dashboard">
                    <i class="bi bi-grid-1x2-fill text-primary"></i> <span class="sidebar-text">หน้าหลัก (Dashboard)</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($c == 'profile' && $a == 'schedule') ? 'active' : '' ?>" href="index.php?c=profile&a=schedule">
                    <i class="bi bi-calendar-heart-fill text-danger"></i> <span class="sidebar-text">ปฏิทินเวรของฉัน</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- 🌟 หมวดหมู่: การปฏิบัติงาน -->
            <?php if (in_array('roster', $allowed_controllers) || in_array('report', $allowed_controllers) || in_array('leave', $allowed_controllers) || in_array('data43', $allowed_controllers)): ?>
            <li class="sidebar-heading <?= $role === 'HR' ? '' : 'mt-2' ?>">การปฏิบัติงาน</li>
            
                <?php if (in_array('roster', $allowed_controllers)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($c == 'roster') ? 'active' : '' ?>" href="index.php?c=roster">
                        <i class="bi bi-calendar3 text-info"></i> <span class="sidebar-text">ตารางปฏิบัติงาน (เวร)</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array('report', $allowed_controllers)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($c == 'report' && $a == 'overview') ? 'active' : '' ?>" href="index.php?c=report&a=overview">
                        <i class="bi bi-bar-chart-line-fill text-success"></i> <span class="sidebar-text">ติดตามการส่งเวร</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array('data43', $allowed_controllers)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($c == 'data43') ? 'active' : '' ?>" href="index.php?c=data43&a=index">
                        <i class="bi bi-file-earmark-zip-fill text-warning"></i>
                        <span class="sidebar-text">นำส่งข้อมูล 43 แฟ้ม</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- 🌟 ระบบวันลา (แบบมี Dropdown) -->
                <?php if (in_array('leave', $allowed_controllers)): ?>
                <li class="nav-item sidebar-dropdown-container leave-dropdown-container">
                    <a class="nav-link <?= ($c == 'leave') ? 'active' : 'collapsed' ?> d-flex justify-content-between align-items-center" 
                        data-bs-toggle="collapse" href="#leaveMenu" role="button" aria-expanded="<?= ($c == 'leave') ? 'true' : 'false' ?>">
                        <div><i class="bi bi-envelope-paper-fill text-warning"></i> <span class="sidebar-text">ระบบจัดการวันลา</span></div>
                        <i class="bi bi-chevron-down dropdown-arrow text-muted"></i>
                    </a>
                    <div class="collapse <?= ($c == 'leave') ? 'show' : '' ?>" id="leaveMenu">
                        <ul class="sidebar-menu pb-0 mt-1 mb-2 p-0 position-relative" style="gap: 2px;">
                            
                            <li class="nav-item">
                                <a class="nav-link submenu-item <?= ($c == 'leave' && $a == 'index') ? 'active' : '' ?>" href="index.php?c=leave&a=index">
                                    ยื่นใบลา / ประวัติ
                                </a>
                            </li>
                            
                            <?php if (in_array($role, ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER'])): ?>
                                <li class="nav-item">
                                    <a class="nav-link submenu-item <?= ($c == 'leave' && $a == 'approvals') ? 'active' : '' ?>" href="index.php?c=leave&a=approvals">
                                        อนุมัติการลา
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link submenu-item <?= ($c == 'leave' && $a == 'manage') ? 'active' : '' ?>" href="index.php?c=leave&a=manage">
                                        จัดการวันลารายบุคคล
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link submenu-item <?= ($c == 'leave' && $a == 'balances') ? 'active' : '' ?>" href="index.php?c=leave&a=balances">
                                        จัดการวันลาสะสม
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link submenu-item <?= ($c == 'leave' && $a == 'report') ? 'active' : '' ?>" href="index.php?c=leave&a=report">
                                        รายงานสรุปวันลา
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if (in_array($role, ['SUPERADMIN', 'ADMIN'])): ?>
                                <li class="nav-item">
                                    <a class="nav-link submenu-item <?= ($c == 'leave' && $a == 'templates') ? 'active' : '' ?>" href="index.php?c=leave&a=templates">
                                        แบบฟอร์มวันลา
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link submenu-item <?= ($c == 'leave' && $a == 'settings') ? 'active' : '' ?>" href="index.php?c=leave&a=settings">
                                        ตั้งค่าระเบียบการลา
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </li>
                <?php endif; ?>
            <?php endif; ?>

            <!-- 🌟 หมวดหมู่: การจัดการภายใน (รวมระบบ HR ไว้ที่นี่) -->
            <?php if (in_array('staff', $allowed_controllers) || in_array('users', $allowed_controllers) || in_array('hr', $allowed_controllers)): ?>
            <li class="sidebar-heading mt-2">การจัดการภายใน</li>
                
                <?php if (in_array('staff', $allowed_controllers)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($c == 'staff') ? 'active' : '' ?>" href="index.php?c=staff">
                        <i class="bi bi-people-fill text-secondary"></i> <span class="sidebar-text">จัดการบุคลากร</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array('users', $allowed_controllers)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($c == 'users') ? 'active' : '' ?>" href="index.php?c=users">
                        <i class="bi bi-database-gear text-dark"></i> <span class="sidebar-text">ฐานข้อมูลบุคลากร</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- 🌟 ระบบ HR (แบบมี Dropdown) -->
                <?php if (in_array('hr', $allowed_controllers)): ?>
                <li class="nav-item sidebar-dropdown-container hr-dropdown-container">
                    <a class="nav-link <?= ($c == 'hr') ? 'active' : 'collapsed' ?> d-flex justify-content-between align-items-center" 
                        data-bs-toggle="collapse" href="#hrMenu" role="button" aria-expanded="<?= ($c == 'hr') ? 'true' : 'false' ?>">
                        <div><i class="bi bi-person-bounding-box text-danger"></i> <span class="sidebar-text">ระบบงานบุคคล (HR)</span></div>
                        <i class="bi bi-chevron-down dropdown-arrow text-muted"></i>
                    </a>
                    <div class="collapse <?= ($c == 'hr') ? 'show' : '' ?>" id="hrMenu">
                        <ul class="sidebar-menu pb-0 mt-1 mb-2 p-0 position-relative" style="gap: 2px;">
                            <li class="nav-item">
                                <a class="nav-link submenu-item <?= ($c == 'hr' && $a == 'dashboard') ? 'active' : '' ?>" href="index.php?c=hr&a=dashboard">
                                    แดชบอร์ดฝ่ายบุคคล
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link submenu-item <?= ($c == 'hr' && $a == 'payroll') ? 'active' : '' ?>" href="index.php?c=hr&a=payroll">
                                    รายงานค่าตอบแทน
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link submenu-item <?= ($c == 'hr' && $a == 'inactive') ? 'active' : '' ?>" href="index.php?c=hr&a=inactive">
                                    ทำเนียบผู้พ้นสภาพ
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link submenu-item <?= ($c == 'hr' && $a == 'completeness') ? 'active' : '' ?>" href="index.php?c=hr&a=completeness">
                                    ตรวจสอบข้อมูล
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <?php endif; ?>

            <?php endif; ?>

            <!-- 🌟 หมวดหมู่: ระบบส่วนกลาง -->
            <?php if (in_array('settings', $allowed_controllers) || in_array('logs', $allowed_controllers) || in_array('hospitals', $allowed_controllers)): ?>
            <li class="sidebar-heading mt-2">ระบบส่วนกลาง</li>
            
                <?php if (in_array('settings', $allowed_controllers)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($c == 'settings') ? 'active' : '' ?>" href="index.php?c=settings">
                        <i class="bi bi-gear-fill text-secondary"></i> <span class="sidebar-text">ตั้งค่าหน่วยบริการ/ระบบ</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (in_array('logs', $allowed_controllers)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($c == 'logs') ? 'active' : '' ?>" href="index.php?c=logs">
                        <i class="bi bi-journal-text text-secondary"></i> <span class="sidebar-text">ประวัติการใช้งาน</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array('hospitals', $allowed_controllers)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($c == 'hospitals') ? 'active' : '' ?>" href="index.php?c=hospitals">
                        <i class="bi bi-building-fill text-primary"></i> <span class="sidebar-text">จัดการ รพ.สต. ทั้งหมด</span>
                    </a>
                </li>
                <?php endif; ?>
            <?php endif; ?>
            
        </ul>
        <?php
    }
}
?>

<!-- ========================================== -->
<!-- 🌟 1. Desktop Sidebar -->
<!-- ========================================== -->
<aside id="desktopSidebar" class="d-none d-md-flex flex-column h-100" aria-label="เมนูหลัก">
    <!-- Script ป้องกันการกระพริบของเมนูตอนโหลดหน้าเว็บ -->
    <script>
        if (localStorage.getItem('sidebarState') === 'collapsed') {
            document.getElementById('desktopSidebar').classList.add('collapsed');
        }
    </script>
    
    <div class="flex-grow-1 custom-scrollbar pb-3 sidebar-scroll-area">
        <?php renderSidebarMenu($c, $a, $role, $allowed_controllers); ?>
    </div>
    
    <!-- ปุ่มออกจากระบบ (ล่างสุด) -->
    <div class="mt-auto p-3 border-top bg-white">
        <a href="index.php?c=auth&a=logout" class="nav-link d-flex align-items-center text-decoration-none" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?');" title="ออกจากระบบ">
            <i class="bi bi-box-arrow-left"></i> <span class="sidebar-text">ออกจากระบบ</span>
        </a>
    </div>
</aside>

<!-- ========================================== -->
<!-- 🌟 2. Mobile Sidebar (Offcanvas) -->
<!-- ========================================== -->
<div class="offcanvas offcanvas-start border-0 shadow" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header border-bottom px-4 py-3">
        <h5 class="offcanvas-title fw-bold d-flex align-items-center text-primary" id="mobileSidebarLabel">
            <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center me-2 shadow-sm" style="width: 32px; height: 32px;">
                <i class="bi bi-calendar2-check-fill fs-6"></i>
            </div>
            Roster<span class="text-dark">Pro</span>
        </h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas" aria-label="ปิดเมนู"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column custom-scrollbar pb-4">
        <?php renderSidebarMenu($c, $a, $role, $allowed_controllers); ?>
    </div>
</div>

<!-- ========================================== -->
<!-- 🌟 3. JavaScript ควบคุมพฤติกรรม Sidebar -->
<!-- ========================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('desktopSidebar');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const toggleIcon = document.getElementById('sidebarToggleIcon');

    if (!sidebar) return;

    function updateSidebarIcon() {
        const isCollapsed = sidebar.classList.contains('collapsed');

        document.body.classList.toggle('sidebar-collapsed-ui', isCollapsed);

        if (toggleIcon) {
            toggleIcon.className = isCollapsed
                ? 'bi bi-layout-sidebar-inset-reverse'
                : 'bi bi-layout-sidebar-inset';
        }

        // Tooltip แบบ native สำหรับ Sidebar ที่ย่อ
        sidebar.querySelectorAll('.sidebar-menu > .nav-item > .nav-link').forEach(function (link) {
            const label = link.querySelector('.sidebar-text')?.textContent.trim();
            if (!label) return;

            if (isCollapsed) {
                link.setAttribute('title', label);
            } else if (!link.classList.contains('submenu-item')) {
                link.removeAttribute('title');
            }
        });
    }

    function closeBootstrapSubmenus() {
        sidebar.querySelectorAll('.sidebar-dropdown-container > .collapse').forEach(function (element) {
            const menuId = element.id;
            if (!menuId) return;
            element.classList.remove('show');

            const trigger = sidebar.querySelector('[href="#' + menuId + '"]');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
                trigger.classList.add('collapsed');
            }
        });
    }

    function positionFlyout(container) {
        if (!sidebar.classList.contains('collapsed')) return;

        const flyout = container.querySelector(':scope > .collapse');
        if (!flyout) return;

        const itemRect = container.getBoundingClientRect();
        const sidebarRect = sidebar.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const estimatedHeight = Math.min(flyout.scrollHeight || 280, 420);

        let top = itemRect.top;
        if (top + estimatedHeight > viewportHeight - 12) {
            top = Math.max(12, viewportHeight - estimatedHeight - 12);
        }

        flyout.style.left = (sidebarRect.right + 8) + 'px';
        flyout.style.top = top + 'px';
        flyout.style.maxHeight = Math.max(180, viewportHeight - top - 12) + 'px';
        flyout.style.overflowY = 'auto';
    }

    updateSidebarIcon();

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();

            sidebar.classList.toggle('collapsed');
            const isCollapsed = sidebar.classList.contains('collapsed');

            localStorage.setItem(
                'sidebarState',
                isCollapsed ? 'collapsed' : 'expanded'
            );

            sidebar.querySelectorAll('.flyout-open').forEach(function (item) {
                item.classList.remove('flyout-open');
            });

            if (isCollapsed) {
                closeBootstrapSubmenus();
            }

            updateSidebarIcon();
        });
    }

    const flyoutContainers = sidebar.querySelectorAll('.sidebar-dropdown-container');

    flyoutContainers.forEach(function (container) {
        const trigger = container.querySelector(':scope > .nav-link');
        const flyout = container.querySelector(':scope > .collapse');
        let closeTimer = null;

        function openFlyout() {
            if (!sidebar.classList.contains('collapsed')) return;

            if (closeTimer) {
                clearTimeout(closeTimer);
                closeTimer = null;
            }

            positionFlyout(container);
            container.classList.add('flyout-open');
        }

        function scheduleClose() {
            if (!sidebar.classList.contains('collapsed')) return;

            closeTimer = setTimeout(function () {
                container.classList.remove('flyout-open');
            }, 120);
        }

        container.addEventListener('mouseenter', openFlyout);
        container.addEventListener('mouseleave', scheduleClose);

        // Keyboard users: focus should expose the same flyout as hover.
        container.addEventListener('focusin', function () {
            if (sidebar.classList.contains('collapsed')) {
                openFlyout();
            }
        });

        container.addEventListener('focusout', function (event) {
            if (!sidebar.classList.contains('collapsed')) return;
            if (!container.contains(event.relatedTarget)) {
                scheduleClose();
            }
        });

        container.addEventListener('keydown', function (event) {
            if (!sidebar.classList.contains('collapsed')) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                container.classList.remove('flyout-open');
                if (trigger) trigger.focus();
            }
        });

        if (flyout) {
            flyout.addEventListener('mouseenter', function () {
                if (closeTimer) {
                    clearTimeout(closeTimer);
                    closeTimer = null;
                }
                container.classList.add('flyout-open');
            });

            flyout.addEventListener('mouseleave', scheduleClose);
        }

        if (trigger) {
            trigger.addEventListener('click', function (event) {
                if (sidebar.classList.contains('collapsed')) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    openFlyout();
                    return false;
                }
            }, true);
        }
    });

    window.addEventListener('resize', function () {
        sidebar.querySelectorAll('.flyout-open').forEach(function (container) {
            positionFlyout(container);
        });
    });

    const scrollArea = sidebar.querySelector('.sidebar-scroll-area');
    if (scrollArea) {
        scrollArea.addEventListener('scroll', function () {
            sidebar.querySelectorAll('.flyout-open').forEach(function (container) {
                positionFlyout(container);
            });
        }, { passive: true });
    }
});
</script>

<!-- ========================================== -->
<!-- 🌟 4. เปิดพื้นที่ Main Content (ส่วนแสดงผลข้อมูล) -->
<!-- ========================================== -->
<main id="rpMainContent" class="flex-grow-1 position-relative custom-scrollbar rp-main-content" tabindex="-1">