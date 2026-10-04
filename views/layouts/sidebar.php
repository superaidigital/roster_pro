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
            $menu_controller = strtolower(trim((string)($menu['controller'] ?? '')));
            if ($menu_controller !== '') {
                $allowed_controllers[] = $menu_controller;
            }

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
        $allowed_controllers = ['roster', 'report', 'leave', 'staff', 'users', 'settings', 'logs', 'hospitals', 'hr'];
    } else if ($role === 'HR') {
        $allowed_controllers = ['staff', 'users', 'hr'];
    } else if ($role === 'DIRECTOR') {
        $allowed_controllers = ['roster', 'report', 'leave', 'staff', 'settings', 'hr'];
    } else if ($role === 'SCHEDULER') {
        $allowed_controllers = ['roster', 'report', 'leave', 'staff'];
    } else {
        $allowed_controllers = ['roster', 'leave'];
    }
}

$allowed_controllers = array_unique($allowed_controllers);

// 🌟 HARDCODE OVERRIDE: จัดการสิทธิ์ HR ให้แน่ชัด
if ($role === 'HR') {
    // 🌟 HR จะไม่ได้รับอนุญาตให้เข้าถึงหน้าหลัก 'dashboard' ปกติ
    $allowed_controllers = ['staff', 'users', 'hr', 'profile'];
} else {
    // ตำแหน่งอื่น อนุญาตให้เข้าถึงหน้าพื้นฐานเสมอ
    $allowed_controllers = array_merge($allowed_controllers, ['dashboard', 'profile', 'field']);
}

$allowed_controllers = array_unique($allowed_controllers);
?>
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
            <?php if (in_array('field', $allowed_controllers, true)): ?>
            <li class="sidebar-heading mt-2">งานภาคสนาม</li>
            <li class="nav-item field-dropdown-container">
                <a class="nav-link <?= ($c == 'field') ? '' : 'collapsed' ?> d-flex justify-content-between align-items-center"
                   data-bs-toggle="collapse"
                   href="#fieldMenu"
                   role="button"
                   aria-expanded="<?= ($c == 'field') ? 'true' : 'false' ?>">
                    <div>
                        <i class="bi bi-house-heart-fill text-success"></i>
                        <span class="sidebar-text">เยี่ยมบ้าน / งานชุมชน</span>
                    </div>
                    <i class="bi bi-chevron-down dropdown-arrow text-muted"></i>
                </a>
                <div class="collapse <?= ($c == 'field') ? 'show' : '' ?>" id="fieldMenu">
                    <ul class="sidebar-menu pb-0 mt-1 mb-2 p-0 position-relative" style="gap:2px;">
                        <li class="nav-item">
                            <a class="nav-link submenu-item <?= ($c == 'field' && $a == 'index') ? 'active' : '' ?>" href="index.php?c=field">
                                บันทึก / รายการเยี่ยมบ้าน
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link submenu-item <?= ($c == 'field' && $a == 'followups') ? 'active' : '' ?>" href="index.php?c=field&a=followups">
                                คิวติดตาม
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <?php endif; ?>

            <!-- 🌟 หมวดหมู่: การปฏิบัติงาน -->
            <?php if (in_array('roster', $allowed_controllers) || in_array('report', $allowed_controllers) || in_array('leave', $allowed_controllers)): ?>
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
                <li class="nav-item hr-dropdown-container">
                    <a class="nav-link <?= ($c == 'hr') ? '' : 'collapsed' ?> d-flex justify-content-between align-items-center" 
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
<aside id="desktopSidebar" class="rp-desktop-sidebar flex-column h-100" aria-label="เมนูหลัก">
    <a href="index.php?c=dashboard" class="rp-sidebar-brand text-decoration-none">
        <span class="rp-sidebar-brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
        <span class="rp-sidebar-brand-copy sidebar-text">
            <strong>Roster Pro</strong>
            <small>Primary Care Workspace</small>
        </span>
    </a>
    <!-- Script ป้องกันการกระพริบของเมนูตอนโหลดหน้าเว็บ -->
    <script>
        (function () {
            const collapsed = localStorage.getItem('sidebarState') === 'collapsed';
            const sidebar = document.getElementById('desktopSidebar');

            if (collapsed && sidebar) {
                sidebar.classList.add('collapsed');
                document.body.classList.add('rp-sidebar-collapsed');
            } else {
                document.body.classList.remove('rp-sidebar-collapsed');
            }
        })();
    </script>
    
    <div class="flex-grow-1 overflow-auto custom-scrollbar pb-3">
        <?php renderSidebarMenu($c, $a, $role, $allowed_controllers); ?>
    </div>
    
    <!-- ปุ่มออกจากระบบ (ล่างสุด) -->
    <div class="rp-sidebar-footer mt-auto p-3">
        <a href="index.php?c=auth&a=logout" class="nav-link d-flex align-items-center py-2 px-3 rounded-3 text-decoration-none" style="color: #ef4444; font-weight: bold;" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?');" onmouseover="this.style.backgroundColor='#fef2f2';" onmouseout="this.style.backgroundColor='transparent';">
            <i class="bi bi-box-arrow-left me-2 fs-5" style="color: #ef4444;"></i> <span class="sidebar-text">ออกจากระบบ</span>
        </a>
    </div>
</aside>

<!-- ========================================== -->
<!-- 🌟 2. Mobile Sidebar (Offcanvas) -->
<!-- ========================================== -->
<div class="offcanvas offcanvas-start border-0 shadow" tabindex="-1" id="mobileSidebar" aria-label="เมนูหลักบนมือถือ">
    <div class="offcanvas-header rp-mobile-sidebar-header px-4 py-3">
        <div class="rp-mobile-sidebar-brand">
            <span class="rp-sidebar-brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
            <span>
                <strong>Roster Pro</strong>
                <small>Primary Care Workspace</small>
            </span>
        </div>
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

    function syncSidebarState(collapsed) {
        if (!desktopSidebar) return;

        desktopSidebar.classList.toggle('collapsed', collapsed);
        document.body.classList.toggle('rp-sidebar-collapsed', collapsed);
        localStorage.setItem('sidebarState', collapsed ? 'collapsed' : 'expanded');

        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggleBtn.setAttribute(
                'aria-label',
                collapsed ? 'ขยายเมนูด้านข้าง' : 'ย่อเมนูด้านข้าง'
            );
        }

        desktopSidebar.querySelectorAll('.sidebar-menu .nav-link, .rp-sidebar-footer .nav-link').forEach(link => {
            const label = link.querySelector('.sidebar-text');
            const text = label ? label.textContent.replace(/\s+/g, ' ').trim() : '';

            if (collapsed && text) {
                link.setAttribute('title', text);
                if (!link.hasAttribute('aria-label')) {
                    link.setAttribute('aria-label', text);
                }
            } else {
                link.removeAttribute('title');
            }
        });

        if (collapsed) {
            ['fieldMenu', 'leaveMenu', 'hrMenu'].forEach(menuId => {
                const menuElement = document.getElementById(menuId);

                if (menuElement && menuElement.classList.contains('show')) {
                    if (window.bootstrap && bootstrap.Collapse) {
                        bootstrap.Collapse
                            .getOrCreateInstance(menuElement, { toggle: false })
                            .hide();
                    } else {
                        menuElement.classList.remove('show');
                    }
                }

                const menuBtn = document.querySelector(`[href="#${menuId}"]`);
                if (menuBtn) {
                    menuBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }

    syncSidebarState(localStorage.getItem('sidebarState') === 'collapsed');

    const syncSidebarMode = () => {
        if (window.innerWidth < 1024) {
            document.body.classList.remove('rp-sidebar-collapsed');
        } else {
            document.body.classList.toggle('rp-sidebar-collapsed', desktopSidebar?.classList.contains('collapsed'));
        }
    };

    window.addEventListener('resize', syncSidebarMode);
    syncSidebarMode();

    if (toggleBtn && desktopSidebar) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            syncSidebarState(!desktopSidebar.classList.contains('collapsed'));
        });
    }
});
</script>

<!-- ========================================== -->
<!-- 🌟 4. เปิดพื้นที่ Main Content (ส่วนแสดงผลข้อมูล) -->
<!-- ========================================== -->
<main class="app-main flex-grow-1 position-relative custom-scrollbar">