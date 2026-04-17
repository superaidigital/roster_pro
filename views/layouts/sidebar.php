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
            continue; // ถ้าเมนูปิดอยู่ ข้ามไปเลย
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
        }
    }
} catch (Exception $e) {
    // Fallback: หากตารางระบบเมนูมีปัญหา ให้โหลดสิทธิ์พื้นฐาน
    error_log("Sidebar Menu Query Error: " . $e->getMessage());
    if (in_array($role, ['SUPERADMIN', 'ADMIN'])) {
        $allowed_controllers = ['roster', 'report', 'leave', 'staff', 'users', 'settings', 'logs', 'hospitals'];
    } else if ($role === 'DIRECTOR') {
        $allowed_controllers = ['roster', 'report', 'leave', 'staff', 'settings'];
    } else if ($role === 'SCHEDULER') {
        $allowed_controllers = ['roster', 'report', 'leave', 'staff'];
    } else {
        $allowed_controllers = ['roster', 'leave'];
    }
}

// กำจัดชื่อ Controller ที่ซ้ำกัน
$allowed_controllers = array_unique($allowed_controllers);

// อนุญาตให้เข้าถึงหน้าพื้นฐานเสมอ (ป้องกันการโดน Lockout)
$allowed_controllers = array_merge($allowed_controllers, ['dashboard', 'profile']);
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
    #desktopSidebar.collapsed .leave-dropdown-container ul { display: none !important; }

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

<?php
// ฟังก์ชันสร้างเมนูด้านซ้าย เพื่อเรียกใช้ซ้ำทั้งแบบ Desktop และ Mobile
if (!function_exists('renderSidebarMenu')) {
    function renderSidebarMenu($c, $a, $role, $allowed_controllers) {
        ?>
        <ul class="sidebar-menu">
            
            <!-- 🌟 หมวดหมู่: แดชบอร์ดสถิติ -->
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

            <!-- 🌟 หมวดหมู่: การปฏิบัติงาน -->
            <?php if (in_array('roster', $allowed_controllers) || in_array('report', $allowed_controllers) || in_array('leave', $allowed_controllers)): ?>
            <li class="sidebar-heading mt-2">การปฏิบัติงาน</li>
            
                <?php if (in_array('roster', $allowed_controllers)): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($c == 'roster') ? 'active' : '' ?>" href="index.php?c=roster">
                        <i class="bi bi-calendar3 text-info"></i> <span class="sidebar-text">ตารางปฏิบัติงาน (เวร)</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array('report', $allowed_controllers) || in_array($role, ['SUPERADMIN', 'ADMIN', 'DIRECTOR', 'SCHEDULER'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($c == 'report' && $a == 'overview') ? 'active' : '' ?>" href="index.php?c=report&a=overview">
                        <i class="bi bi-bar-chart-line-fill text-success"></i> <span class="sidebar-text">ติดตามการส่งเวร</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- 🌟 ระบบวันลา (แบบมี Dropdown) -->
                <?php if (in_array('leave', $allowed_controllers)): ?>
                <li class="nav-item leave-dropdown-container">
                    <a class="nav-link <?= ($c == 'leave') ? '' : 'collapsed' ?> d-flex justify-content-between align-items-center" 
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
                                        จัดการวันลาสะสม (พักผ่อน)
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

            <!-- 🌟 หมวดหมู่: การจัดการภายใน -->
            <?php if (in_array('staff', $allowed_controllers) || in_array('users', $allowed_controllers)): ?>
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
<aside id="desktopSidebar" class="d-none d-md-flex flex-column h-100 bg-white">
    <!-- Script ป้องกันการกระพริบของเมนูตอนโหลดหน้าเว็บ -->
    <script>
        if (localStorage.getItem('sidebarState') === 'collapsed') {
            document.getElementById('desktopSidebar').classList.add('collapsed');
        }
    </script>
    
    <div class="flex-grow-1 overflow-auto custom-scrollbar pb-3">
        <?php renderSidebarMenu($c, $a, $role, $allowed_controllers); ?>
    </div>
    
    <!-- ปุ่มออกจากระบบ (ล่างสุด) -->
    <div class="mt-auto p-3 border-top bg-white">
        <a href="index.php?c=auth&a=logout" class="nav-link d-flex align-items-center py-2 px-3 rounded-3 text-decoration-none" style="color: #ef4444; font-weight: bold;" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?');" onmouseover="this.style.backgroundColor='#fef2f2';" onmouseout="this.style.backgroundColor='transparent';">
            <i class="bi bi-box-arrow-left me-2 fs-5" style="color: #ef4444;"></i> <span class="sidebar-text">ออกจากระบบ</span>
        </a>
    </div>
</aside>

<!-- ========================================== -->
<!-- 🌟 2. Mobile Sidebar (Offcanvas) -->
<!-- ========================================== -->
<div class="offcanvas offcanvas-start border-0 shadow" tabindex="-1" id="mobileSidebar" style="width: 280px;">
    <div class="offcanvas-header border-bottom px-4 py-3">
        <h5 class="offcanvas-title fw-bold d-flex align-items-center text-primary">
            <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center me-2 shadow-sm" style="width: 32px; height: 32px;">
                <i class="bi bi-calendar2-check-fill fs-6"></i>
            </div>
            Roster<span class="text-dark">Pro</span>
        </h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column custom-scrollbar pb-4">
        <?php renderSidebarMenu($c, $a, $role, $allowed_controllers); ?>
    </div>
</div>

<!-- ========================================== -->
<!-- 🌟 3. JavaScript ควบคุมพฤติกรรม Sidebar -->
<!-- ========================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const desktopSidebar = document.getElementById('desktopSidebar');
    
    if (toggleBtn && desktopSidebar) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            desktopSidebar.classList.toggle('collapsed');
            
            // บันทึกสถานะลงใน Browser
            if (desktopSidebar.classList.contains('collapsed')) {
                localStorage.setItem('sidebarState', 'collapsed');
                // สั่งปิดเมนู Dropdown อัตโนมัติเวลาพับ Sidebar
                const leaveMenu = document.getElementById('leaveMenu');
                if(leaveMenu && leaveMenu.classList.contains('show')) {
                    const bsCollapse = new bootstrap.Collapse(leaveMenu, {toggle: false});
                    bsCollapse.hide();
                    
                    // ปรับสถานะลูกศร
                    const leaveBtn = document.querySelector('[href="#leaveMenu"]');
                    if(leaveBtn) leaveBtn.setAttribute('aria-expanded', 'false');
                }
            } else {
                localStorage.setItem('sidebarState', 'expanded');
            }
        });
    }
});
</script>

<!-- ========================================== -->
<!-- 🌟 4. เปิดพื้นที่ Main Content (ส่วนแสดงผลข้อมูล) -->
<!-- ========================================== -->
<main class="flex-grow-1 position-relative overflow-y-auto custom-scrollbar" style="background-color: #f4f6f9; padding: 1.5rem; height: 100%;">