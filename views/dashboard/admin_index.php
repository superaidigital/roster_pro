<?php
// ที่อยู่ไฟล์: views/dashboard/admin_index.php
// รับค่าตัวแปรมาจาก DashboardController.php

require_once 'config/database.php';
$db = (new Database())->getConnection();

// ==========================================
// 📊 ดึงและคำนวณข้อมูลจากฐานข้อมูลโดยตรง (Real-time)
// ==========================================
$today_date = date('Y-m-d');
// 🌟 เงื่อนไขสำหรับกรองทุกหน่วยงาน (ยกเว้นส่วนกลาง)
$hosp_condition = "(h.name NOT LIKE '%ส่วนกลาง%' AND h.id != 0)";

$stats = [];

// 1. จำนวนหน่วยบริการทั้งหมด (ยกเว้นส่วนกลาง)
$stats['total_hospitals'] = $db->query("SELECT COUNT(*) FROM hospitals h WHERE $hosp_condition")->fetchColumn() ?: 0;

// 2. จำนวนบุคลากรทั้งหมด (ยกเว้นแอดมิน และส่วนกลาง)
$stats['total_staff'] = $db->query("
    SELECT COUNT(u.id) FROM users u 
    JOIN hospitals h ON u.hospital_id = h.id 
    WHERE u.role NOT IN ('SUPERADMIN', 'ADMIN') AND $hosp_condition
")->fetchColumn() ?: 0;

// 3. ขึ้นเวรวันนี้ (เชื่อมกับตาราง shifts)
$stmt_duty = $db->prepare("
    SELECT COUNT(DISTINCT s.user_id) FROM shifts s 
    JOIN hospitals h ON s.hospital_id = h.id 
    WHERE s.shift_date = ? AND s.shift_type NOT IN ('', 'L', 'O', 'OFF', 'ย') AND $hosp_condition
");
$stmt_duty->execute([$today_date]);
$stats['staff_on_duty_today'] = $stmt_duty->fetchColumn() ?: 0;

// 4. ลางานวันนี้ (เชื่อมกับตาราง leave_requests สังกัด รพ.สต.)
$stmt_leave = $db->prepare("
    SELECT COUNT(DISTINCT lr.user_id) FROM leave_requests lr 
    JOIN users u ON lr.user_id = u.id 
    JOIN hospitals h ON u.hospital_id = h.id 
    WHERE lr.status = 'APPROVED' AND ? BETWEEN lr.start_date AND lr.end_date AND $hosp_condition
");
$stmt_leave->execute([$today_date]);
$stats['staff_on_leave_today'] = $stmt_leave->fetchColumn() ?: 0;

$size_counts = $size_counts ?? ['S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0];
$submitted_count = $submitted_count ?? 0;
$pending_count = $pending_count ?? 0;
$hospitals = $hospitals ?? [];
$pending_leave_count = $pending_leave_count ?? 0;

$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$month_th = $thai_months[(int)date('m')] . ' ' . (date('Y') + 543);
$next_month_th = $thai_months[(int)date('m', strtotime('+1 month'))] . ' ' . (date('Y', strtotime('+1 month')) + 543);

// 🌟 อัปเดตข้อมูลราย รพ.สต. เพื่อใช้สร้างพิกัดแผนที่ (Map Popups)
foreach ($hospitals as &$h) {
    $hid = $h['id'];
    
    // จำนวนบุคลากรใน รพ.สต.
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE hospital_id = ? AND role NOT IN ('SUPERADMIN', 'ADMIN')");
    $stmt->execute([$hid]);
    $h['total_staff'] = $stmt->fetchColumn() ?: 0;

    // ขึ้นเวรวันนี้
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM shifts WHERE hospital_id = ? AND shift_date = ? AND shift_type NOT IN ('', 'L', 'O', 'OFF', 'ย')");
    $stmt->execute([$hid, $today_date]);
    $h['on_duty_today'] = $stmt->fetchColumn() ?: 0;
    
    // ลางานวันนี้
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT lr.user_id) FROM leave_requests lr 
        JOIN users u ON lr.user_id = u.id 
        WHERE u.hospital_id = ? AND lr.status = 'APPROVED' AND ? BETWEEN lr.start_date AND lr.end_date
    ");
    $stmt->execute([$hid, $today_date]);
    $h['on_leave_today'] = $stmt->fetchColumn() ?: 0;
}
unset($h);

// คัดแยกหน่วยบริการตามสถานะเพื่อนำไปแสดงลิสต์
$pending_hospitals = [];
$submitted_hospitals = [];
foreach($hospitals as $h) {
    if(($h['roster_status'] ?? 'pending') === 'submitted') {
        $submitted_hospitals[] = $h;
    } else {
        $pending_hospitals[] = $h;
    }
}

// 🌟 ดึงข้อมูลประวัติการใช้งาน (Logs) ล่าสุด 5 รายการจากฐานข้อมูลโดยตรง
$recent_activities = [];
try {
    $stmt_logs = $db->query("
        SELECT l.*, u.name as user_name 
        FROM logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        ORDER BY l.created_at DESC 
        LIMIT 5
    ");
    $logs_data = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($logs_data as $log) {
        $color = 'secondary'; 
        $icon = 'bi-info-circle';
        $act = strtoupper($log['action'] ?? '');
        
        // กำหนดสีและไอคอนตามประเภทกิจกรรม
        if ($act == 'CREATE') { $color = 'success'; $icon = 'bi-plus-circle-fill'; }
        elseif ($act == 'UPDATE') { $color = 'primary'; $icon = 'bi-pencil-square'; }
        elseif ($act == 'DELETE') { $color = 'danger'; $icon = 'bi-trash-fill'; }
        elseif ($act == 'LOGIN') { $color = 'info'; $icon = 'bi-box-arrow-in-right'; }
        elseif ($act == 'DOWNLOAD') { $color = 'warning'; $icon = 'bi-download'; }
        elseif ($act == 'CANCEL') { $color = 'secondary'; $icon = 'bi-x-circle-fill'; }

        // คำนวณเวลา (Time Ago)
        $time_diff = time() - strtotime($log['created_at']);
        if ($time_diff < 60) $time_str = 'เมื่อกี้';
        elseif ($time_diff < 3600) $time_str = floor($time_diff/60) . ' นาทีที่แล้ว';
        elseif ($time_diff < 86400) $time_str = floor($time_diff/3600) . ' ชม.ที่แล้ว';
        else $time_str = date('d/m/Y', strtotime($log['created_at']));

        $recent_activities[] = [
            'color' => $color,
            'icon'  => $icon,
            'title' => $log['details'],
            'user'  => $log['user_name'] ?? 'ระบบ (System)',
            'time'  => $time_str
        ];
    }
} catch (Exception $e) {
    // กรณีที่ยังไม่มีตาราง logs
}
?>

<!-- 🌟 นำเข้า CSS/JS ที่จำเป็น -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* ==========================================================================
       🌟 Modern UI Dashboard Styles สำหรับ Admin
       ========================================================================== */
    body { background-color: #f4f6f9; }

    /* สไตล์การ์ดหลัก */
    .modern-card { border: none; border-radius: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: #fff; transition: all 0.3s ease; }
    .modern-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
    
    /* สไตล์ KPI Cards */
    .stat-card { border: none; border-radius: 1.5rem; overflow: hidden; position: relative; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1; color: #fff; box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important; }
    .stat-card::after { content: ''; position: absolute; top: -30px; right: -30px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
    .stat-card::before { content: ''; position: absolute; bottom: -20px; left: -20px; width: 100px; height: 100px; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
    
    .bg-grad-admin-1 { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); } /* Dark */
    .bg-grad-admin-2 { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); } /* Blue */
    .bg-grad-admin-3 { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); } /* Purple */
    .bg-grad-admin-4 { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); } /* Orange */
    .bg-grad-admin-5 { background: linear-gradient(135deg, #10b981 0%, #047857 100%); } /* Green */

    .stat-icon { position: absolute; right: 15px; bottom: 10px; font-size: 5rem; opacity: 0.15; transform: rotate(-10deg); transition: transform 0.3s; }
    .stat-card:hover .stat-icon { transform: scale(1.1) rotate(0deg) translateY(-10px); opacity: 0.25; }

    /* Timeline ประวัติการใช้งาน */
    .timeline-wrapper { position: relative; padding-left: 20px; }
    .timeline-item { position: relative; padding-bottom: 1.5rem; border-left: 2px solid #e2e8f0; padding-left: 1.5rem; }
    .timeline-item:last-child { border-left-color: transparent; padding-bottom: 0; }
    .timeline-badge { position: absolute; left: -9px; top: 0; width: 16px; height: 16px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 1px #cbd5e1; }
    
    /* Quick Action Cards */
    .quick-action-card { display: flex; align-items: center; padding: 1.2rem; border-radius: 1rem; text-decoration: none; transition: all 0.2s; background-color: #fff; border: 1px solid #f1f5f9; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
    .quick-action-card:hover { transform: translateX(5px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); border-color: #e2e8f0; }
    .qa-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 15px; }

    /* 🌟 แผนที่ */
    .gis-map-container { width: 100%; height: 100%; min-height: 400px; border-radius: 1rem; z-index: 1; border: 1px solid #e2e8f0; }
    .custom-div-icon { background: transparent; border: none; }
    .marker-pin { width: 36px; height: 36px; border-radius: 50% 50% 50% 0; position: absolute; transform: rotate(-45deg); left: 50%; top: 50%; margin: -18px 0 0 -18px; box-shadow: 0 4px 10px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; border: 2px solid #fff; transition: transform 0.2s; }
    .marker-pin:hover { transform: rotate(-45deg) scale(1.15); z-index: 1000; }
    .marker-icon { transform: rotate(45deg); color: white; font-size: 16px; }
    .marker-submitted { background-color: #059669; } 
    .marker-pending { background-color: #e11d48; } 

    /* กราฟ Container */
    .chart-container { position: relative; height: 260px; width: 100%; display: flex; align-items: center; justify-content: center; }
    
    .list-group-item-modern { border-left: none; border-right: none; border-top: none; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
    .list-group-item-modern:last-child { border-bottom: none; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    
    <!-- 🌟 Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 border-bottom pb-3 border-secondary border-opacity-10">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 56px; height: 56px;">
                <i class="bi bi-shield-check fs-3"></i>
            </div>
            <div>
                <h3 class="fw-bolder text-dark mb-0">ศูนย์ควบคุม (Admin Command Center)</h3>
                <p class="text-muted mb-0 fw-medium" style="font-size: 14px;">ระบบเฝ้าระวังและวิเคราะห์ข้อมูลภาพรวม ประจำวันที่ <?= date('d/m/Y') ?></p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-light border shadow-sm rounded-pill fw-bold text-dark px-4" onclick="window.location.reload();">
                <i class="bi bi-arrow-clockwise me-1"></i> อัปเดตข้อมูล
            </button>
        </div>
    </div>

    <!-- 🌟 1. KPI Cards (ภาพรวมทั้งจังหวัด/เครือข่าย) -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-grad-admin-2 h-100">
                <div class="card-body p-4">
                    <div class="text-white opacity-75 fw-bold mb-1" style="font-size: 13px; text-transform: uppercase;">หน่วยบริการทั้งหมด</div>
                    <h2 class="fw-bolder mb-0 display-5"><?= number_format($stats['total_hospitals']) ?> <span class="fs-6 fw-normal opacity-75">แห่ง</span></h2>
                    <i class="bi bi-hospital stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-grad-admin-3 h-100">
                <div class="card-body p-4">
                    <div class="text-white opacity-75 fw-bold mb-1" style="font-size: 13px; text-transform: uppercase;">บุคลากรทั้งหมด</div>
                    <h2 class="fw-bolder mb-0 display-5"><?= number_format($stats['total_staff']) ?> <span class="fs-6 fw-normal opacity-75">คน</span></h2>
                    <i class="bi bi-people-fill stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-grad-admin-4 h-100 <?= $pending_leave_count > 0 ? 'border border-2 border-warning' : '' ?> d-flex flex-column">
                <div class="card-body p-4 flex-grow-1">
                    <div class="text-white opacity-75 fw-bold mb-1" style="font-size: 13px; text-transform: uppercase;">ลางาน / ขาดราชการ วันนี้</div>
                    <h2 class="fw-bolder mb-0 display-5"><?= number_format($stats['staff_on_leave_today']) ?> <span class="fs-6 fw-normal opacity-75">คน</span></h2>
                    <i class="bi bi-person-dash-fill stat-icon"></i>
                </div>
                <?php if($pending_leave_count > 0): ?>
                    <div class="bg-dark bg-opacity-25 px-3 py-2 text-white" style="font-size: 12px; z-index: 2;">
                        <i class="bi bi-info-circle me-1"></i> มีคำร้องลางานรออนุมัติ <?= $pending_leave_count ?> รายการ
                        <a href="index.php?c=leave&a=approvals" class="text-white text-decoration-underline ms-1 fw-bold">ตรวจสอบ</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-grad-admin-5 h-100">
                <div class="card-body p-4">
                    <div class="text-white opacity-75 fw-bold mb-1" style="font-size: 13px; text-transform: uppercase;">ผู้ปฏิบัติงานวันนี้</div>
                    <h2 class="fw-bolder mb-0 display-5"><?= number_format($stats['staff_on_duty_today']) ?> <span class="fs-6 fw-normal opacity-75">คน</span></h2>
                    <i class="bi bi-person-workspace stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 2. Map & Pending Rosters (พื้นที่ความสำคัญสูง) -->
    <div class="row g-4 mb-4">
        <!-- แผนที่ GIS -->
        <div class="col-xl-8 col-lg-7 d-flex flex-column">
            <div class="card modern-card flex-grow-1">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bolder text-dark mb-0"><i class="bi bi-map-fill text-success me-2 fs-5 align-middle"></i> พิกัดและสถานะการส่งเวร (เดือน <?= $next_month_th ?>)</h6>
                </div>
                <div class="card-body p-3">
                    <div id="gisMap" class="gis-map-container"></div>
                    <div class="mt-3 text-center">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill me-2"><i class="bi bi-check-circle-fill me-1"></i> ส่งตารางเวรแล้ว</span>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-x-circle-fill me-1"></i> ยังไม่ส่งตารางเวร</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- รายชื่อ รพ.สต. ที่ยังไม่ส่งเวร (เพื่อการติดตามงาน) -->
        <div class="col-xl-4 col-lg-5 d-flex flex-column">
            <div class="card modern-card flex-grow-1">
                <div class="card-header bg-danger bg-opacity-10 border-bottom border-danger border-opacity-25 p-4">
                    <h6 class="fw-bolder text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> ต้องติดตาม (ยังไม่ส่งเวรเดือนถัดไป)</h6>
                </div>
                <div class="card-body p-0 overflow-auto custom-scrollbar" style="max-height: 480px;">
                    <?php if(empty($pending_hospitals)): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-check-circle fs-1 text-success opacity-50 mb-3 d-block"></i>
                            <h6 class="fw-bold">ยอดเยี่ยมมาก!</h6>
                            <p class="small mb-0">ทุกหน่วยบริการส่งตารางเวรครบแล้ว</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush px-3">
                            <?php foreach($pending_hospitals as $ph): ?>
                                <li class="list-group-item list-group-item-modern d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 14px;"><?= htmlspecialchars($ph['name']) ?></div>
                                        <div class="text-muted" style="font-size: 11px;"><i class="bi bi-person-badge me-1"></i> ผอ. <?= htmlspecialchars($ph['director']) ?></div>
                                    </div>
                                    <div class="text-end">
                                        <a href="tel:<?= htmlspecialchars($ph['phone']) ?>" class="btn btn-sm btn-outline-danger rounded-circle shadow-sm" title="โทรติดตาม"><i class="bi bi-telephone-fill"></i></a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-top text-center p-3">
                    <span class="text-danger fw-bold fs-5"><?= count($pending_hospitals) ?></span> <span class="text-muted small">แห่งที่ต้องติดตาม</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 3. Analytics Charts -->
    <div class="row g-4 mb-4">
        <!-- Chart 1: สัดส่วนการส่งเวร -->
        <div class="col-xl-4 col-md-6">
            <div class="card modern-card h-100">
                <div class="card-header bg-white border-bottom p-4">
                    <h6 class="fw-bolder text-dark mb-0"><i class="bi bi-pie-chart-fill text-primary me-2"></i> ภาพรวมการส่งเวรเดือนหน้า</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="rosterChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 2: ขนาดหน่วยบริการ -->
        <div class="col-xl-4 col-md-6">
            <div class="card modern-card h-100">
                <div class="card-header bg-white border-bottom p-4">
                    <h6 class="fw-bolder text-dark mb-0"><i class="bi bi-pentagon-fill text-warning me-2"></i> สัดส่วนขนาดหน่วยบริการ (Size)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="sizeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 3: อันดับคนเยอะสุด -->
        <div class="col-xl-4 col-md-12">
            <div class="card modern-card h-100">
                <div class="card-header bg-white border-bottom p-4">
                    <h6 class="fw-bolder text-dark mb-0"><i class="bi bi-bar-chart-fill text-info me-2"></i> 5 อันดับหน่วยงานบุคลากรเยอะสุด</h6>
                </div>
                <div class="card-body d-flex align-items-center">
                    <div class="chart-container w-100" style="height: 250px;">
                        <canvas id="topStaffChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 4. แถบเครื่องมือลัด & ประวัติ -->
    <div class="row g-4">
        <div class="col-xl-7 col-lg-6">
            <div class="card modern-card h-100">
                <div class="card-header bg-white border-bottom p-4">
                    <h6 class="fw-bolder text-dark mb-0"><i class="bi bi-lightning-charge-fill text-warning me-2"></i> จัดการระบบอย่างรวดเร็ว (Quick Actions)</h6>
                </div>
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="index.php?c=hospitals" class="quick-action-card">
                                <div class="qa-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-building-fill-gear"></i></div>
                                <div class="text-start">
                                    <h6 class="fw-bold text-dark mb-0">จัดการ รพ.สต.</h6>
                                    <small class="text-muted">เพิ่ม/แก้ไข ข้อมูลพิกัดและขนาด</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="index.php?c=users" class="quick-action-card">
                                <div class="qa-icon bg-success bg-opacity-10 text-success"><i class="bi bi-database-gear"></i></div>
                                <div class="text-start">
                                    <h6 class="fw-bold text-dark mb-0">ฐานข้อมูลบุคลากร</h6>
                                    <small class="text-muted">แก้ไขสิทธิ์และข้อมูลผู้ใช้ทั้งหมด</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="index.php?c=leave&a=settings" class="quick-action-card">
                                <div class="qa-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-envelope-paper-fill"></i></div>
                                <div class="text-start">
                                    <h6 class="fw-bold text-dark mb-0">ตั้งค่าระเบียบการลา</h6>
                                    <small class="text-muted">กำหนดโควตาและสิทธิวันลา</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="index.php?c=settings&a=shift_types" class="quick-action-card">
                                <div class="qa-icon bg-warning bg-opacity-10 text-warning text-dark"><i class="bi bi-cash-coin"></i></div>
                                <div class="text-start">
                                    <h6 class="fw-bold text-dark mb-0">กลุ่มสายงาน/เรทเงิน</h6>
                                    <small class="text-muted">จัดการเรทค่าตอบแทนส่วนกลาง</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5 col-lg-6">
            <div class="card modern-card h-100">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bolder text-dark mb-0"><i class="bi bi-activity text-danger me-2"></i> ความเคลื่อนไหวล่าสุด</h6>
                    <a href="index.php?c=logs" class="btn btn-sm btn-light border fw-bold text-primary rounded-pill px-3">ดูทั้งหมด</a>
                </div>
                <div class="card-body p-4">
                    <?php if(empty($recent_activities)): ?>
                        <div class="text-center text-muted py-4">ไม่มีประวัติการใช้งาน</div>
                    <?php else: ?>
                        <div class="timeline-wrapper">
                            <?php foreach($recent_activities as $idx => $act): 
                                if($idx > 4) break; // โชว์แค่ 5 รายการ
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-badge bg-<?= $act['color'] ?>"></div>
                                <div class="fw-bold text-dark mb-1" style="font-size: 14px;"><?= htmlspecialchars($act['title']) ?></div>
                                <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 11px;">
                                    <span><i class="bi <?= $act['icon'] ?> me-1 text-<?= $act['color'] ?>"></i> <?= htmlspecialchars($act['user']) ?></span>
                                    <span><i class="bi bi-clock me-1"></i><?= $act['time'] ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 🌟 Scripts ควบคุมแผนที่และกราฟ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ตั้งค่าฟอนต์มาตรฐาน Chart.js
    Chart.defaults.font.family = "'Kanit', 'Prompt', sans-serif";
    Chart.defaults.color = '#64748b';

    // ข้อมูลจาก PHP
    const hospitalsData = <?= json_encode($hospitals) ?>;
    const submittedCount = <?= $submitted_count ?>;
    const pendingCount = <?= $pending_count ?>;
    const sizeCounts = <?= json_encode($size_counts) ?>;

    // ==========================================
    // 🗺️ 1. จัดการแผนที่ Leaflet (GIS Map)
    // ==========================================
    const mapContainer = document.getElementById('gisMap');
    if (mapContainer && hospitalsData && hospitalsData.length > 0) {
        let centerLat = 15.7981, centerLng = 104.1481; // ค่าตั้งต้น ยโสธร
        const validHospitals = hospitalsData.filter(h => h.lat && h.lng);
        if (validHospitals.length > 0) { centerLat = validHospitals[0].lat; centerLng = validHospitals[0].lng; }

        const map = L.map('gisMap', { scrollWheelZoom: false }).setView([centerLat, centerLng], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OS' }).addTo(map);

        setTimeout(() => map.invalidateSize(), 500);

        const createCustomIcon = (status) => {
            let bgColorClass = status === 'submitted' ? 'marker-submitted' : 'marker-pending';
            return L.divIcon({
                className: 'custom-div-icon',
                html: `<div class='marker-pin ${bgColorClass}'><i class='bi bi-hospital marker-icon'></i></div>`,
                iconSize: [30, 42], iconAnchor: [15, 42], popupAnchor: [0, -35]
            });
        };

        const markers = [];
        validHospitals.forEach(function(hosp) {
            const marker = L.marker([hosp.lat, hosp.lng], {icon: createCustomIcon(hosp.roster_status)}).addTo(map);
            const statusBadge = hosp.roster_status === 'submitted' 
                ? '<span class="badge bg-success"><i class="bi bi-check-circle"></i> ส่งแล้ว</span>' 
                : '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> ยังไม่ส่ง</span>';
            
            // 🌟 อัปเดต Map Popup ให้แสดงผู้ปฏิบัติงานและวันลาแบบ Real-time
            const popupContent = `
                <div class="p-3 text-center border-bottom bg-light" style="border-radius: 12px 12px 0 0;">
                    <h6 class="fw-bolder mb-1 text-dark">${hosp.name}</h6>
                    <small class="text-muted">ขนาด: ${hosp.size} | ผอ. ${hosp.director}</small>
                </div>
                <div class="p-3 bg-white text-center">
                    <div class="mb-3">${statusBadge}</div>
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <div class="flex-fill"><strong class="d-block text-dark fs-5">${hosp.total_staff}</strong><small class="text-muted" style="font-size: 11px;">บุคลากร</small></div>
                        <div class="flex-fill border-start border-end px-2"><strong class="d-block text-success fs-5">${hosp.on_duty_today}</strong><small class="text-muted" style="font-size: 11px;">ขึ้นเวรวันนี้</small></div>
                        <div class="flex-fill"><strong class="d-block text-warning fs-5">${hosp.on_leave_today}</strong><small class="text-muted" style="font-size: 11px;">ลาพัก/หยุด</small></div>
                    </div>
                    <a href="index.php?c=roster&hospital_id=${hosp.id}" class="btn btn-sm btn-primary w-100 rounded-pill fw-bold">จัดการเวร</a>
                </div>
            `;
            marker.bindPopup(popupContent);
            markers.push(marker);
        });

        if(markers.length > 0) {
            const group = new L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    // ==========================================
    // 📊 2. จัดการกราฟ Chart.js
    // ==========================================
    
    // กราฟที่ 1: Doughnut (สถานะส่งเวร)
    const ctx1 = document.getElementById('rosterChart');
    if (ctx1) {
        new Chart(ctx1.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['ส่งตารางแล้ว', 'ยังไม่ส่งตาราง'],
                datasets: [{
                    data: [submittedCount, pendingCount],
                    backgroundColor: ['#10b981', '#f43f5e'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '75%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } }
            }
        });
    }

    // กราฟที่ 2: Polar Area (ขนาดหน่วยบริการ)
    const ctx2 = document.getElementById('sizeChart');
    if (ctx2) {
        new Chart(ctx2.getContext('2d'), {
            type: 'polarArea',
            data: {
                labels: ['ขนาด S', 'ขนาด M', 'ขนาด L', 'ขนาด XL'],
                datasets: [{
                    data: [sizeCounts['S'], sizeCounts['M'], sizeCounts['L'], sizeCounts['XL']],
                    backgroundColor: ['rgba(59, 130, 246, 0.7)', 'rgba(16, 185, 129, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(239, 68, 68, 0.7)'],
                    borderWidth: 2, borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { usePointStyle: true } } }
            }
        });
    }

    // กราฟที่ 3: Bar Chart (Top 5 โรงพยาบาลตามคนเยอะสุด)
    const ctx3 = document.getElementById('topStaffChart');
    if (ctx3 && hospitalsData.length > 0) {
        // ประมวลผลหา Top 5 ใน JS เลย
        const sortedHosp = [...hospitalsData].sort((a, b) => b.total_staff - a.total_staff).slice(0, 5);
        const topNames = sortedHosp.map(h => h.name.replace('โรงพยาบาลส่งเสริมสุขภาพตำบล', '').replace('รพ.สต.', '').trim());
        const topCounts = sortedHosp.map(h => h.total_staff);

        new Chart(ctx3.getContext('2d'), {
            type: 'bar',
            data: {
                labels: topNames.length > 0 ? topNames : ['-'],
                datasets: [{
                    label: 'จำนวนบุคลากร (คน)',
                    data: topCounts.length > 0 ? topCounts : [0],
                    backgroundColor: 'rgba(99, 102, 241, 0.85)',
                    borderRadius: 8, barThickness: 30
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: { 
                    y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }
});
</script>