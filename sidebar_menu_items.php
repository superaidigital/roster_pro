    <!-- หมวดหมู่: แดชบอร์ด -->
    <a href="index.php?c=dashboard" class="sidebar-nav-link ">
        <i class="bi bi-grid-1x2-fill"></i>
        <span class="sidebar-text">แดชบอร์ด</span>
    </a>

    <!-- หมวดหมู่: การปฏิบัติงาน -->
    <div class="sidebar-heading">การปฏิบัติงาน</div>
    
    <a href="index.php?c=roster" class="sidebar-nav-link ">
        <i class="bi bi-calendar3"></i>
        <span class="sidebar-text">ตารางปฏิบัติงาน</span>
    </a>

        <!-- สำหรับผู้จัดเวร / ผอ. / แอดมิน -->
    <a href="index.php?c=leave&a=approvals" class="sidebar-nav-link ">
        <i class="bi bi-envelope-paper-fill"></i>
        <span class="sidebar-text">อนุมัติการลา</span>
    </a>
    
    <!-- สำหรับทุกคน -->
    <a href="index.php?c=leave" class="sidebar-nav-link ">
        <i class="bi bi-calendar-minus-fill"></i>
        <span class="sidebar-text">ลางาน / สลับเวร</span>
    </a>

        <!-- หมวดหมู่: รายงานและสถิติ -->
    <div class="sidebar-heading">รายงานและสถิติ</div>
    
    <a href="index.php?c=report&a=overview" class="sidebar-nav-link ">
        <i class="bi bi-bar-chart-fill"></i>
        <span class="sidebar-text">ภาพรวมและรายงาน</span>
    </a>
    
        <!-- หมวดหมู่: การจัดการระบบ (เฉพาะส่วนกลาง) -->
    <div class="sidebar-heading">ผู้ดูแลระบบส่วนกลาง</div>

    <a href="index.php?c=users" class="sidebar-nav-link ">
        <i class="bi bi-people-fill"></i>
        <span class="sidebar-text">จัดการบุคลากรเครือข่าย</span>
    </a>

    <a href="index.php?c=settings&a=system" class="sidebar-nav-link ">
        <i class="bi bi-gear-fill"></i>
        <span class="sidebar-text">ตั้งค่าระบบส่วนกลาง</span>
    </a>

    <a href="index.php?c=logs&a=index" class="sidebar-nav-link ">
        <i class="bi bi-journal-text"></i>
        <span class="sidebar-text">ประวัติการใช้งานระบบ</span>
    </a>
    
    <!-- หมวดหมู่: บัญชีของฉัน -->
    <div class="sidebar-heading">บัญชีของฉัน</div>
    
    <a href="index.php?c=profile" class="sidebar-nav-link active">
        <i class="bi bi-person-circle"></i>
        <span class="sidebar-text">ข้อมูลส่วนตัว</span>
    </a>

    <a href="index.php?c=auth&a=logout" class="sidebar-nav-link text-danger mt-3" style="background-color: #fef2f2;">
        <i class="bi bi-box-arrow-left text-danger"></i>
        <span class="sidebar-text">ออกจากระบบ</span>
    </a>
