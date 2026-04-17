<?php
// ที่อยู่ไฟล์: views/dashboard/index.php

require_once 'config/database.php';
require_once 'models/PayRateModel.php';
require_once 'models/ShiftModel.php';

// 🌟 สร้างการเชื่อมต่อ DB สำหรับ Dashboard
$db = (new Database())->getConnection();
$hospital_id = $_SESSION['user']['hospital_id'];
$user_id = $_SESSION['user']['id'];
$role = strtoupper($_SESSION['user']['role'] ?? 'STAFF');
$is_manager = in_array($role, ['DIRECTOR', 'SCHEDULER', 'ADMIN', 'SUPERADMIN']);

$current_month = date('Y-m');
$today = date('Y-m-d');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, date('m'), date('Y'));

// =========================================================
// 📊 1. ประมวลผลข้อมูลสำหรับภาพรวม (Manager)
// =========================================================
$total_staff = 0;
$total_shifts_count = 0;
$estimated_budget = 0;
$manager_pending_leaves = 0;
$manager_pending_swaps = 0;

// ตัวแปรสำหรับกราฟ (Manager)
$chart_dist_r = 0; $chart_dist_b = 0; $chart_dist_y = 0;
$top_staff_names = [];
$top_staff_counts = [];

if ($is_manager) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE hospital_id = ? AND role != 'SUPERADMIN'");
    $stmt->execute([$hospital_id]);
    $total_staff = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT s.shift_type, u.pay_rate_id 
                          FROM shifts s 
                          JOIN users u ON s.user_id = u.id 
                          WHERE s.hospital_id = ? AND s.shift_date LIKE ?");
    $stmt->execute([$hospital_id, $current_month . '%']);
    $all_shifts_this_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $payRateModel = new PayRateModel($db);
    $pay_rates_db = $payRateModel->getAllRates();

    if (!function_exists('getPayRateById')) {
        function getPayRateById($pay_rate_id, $rates_db) {
            if (!empty($pay_rate_id)) {
                foreach ($rates_db as $r) {
                    if ($r['id'] == $pay_rate_id) return ['r' => $r['rate_r'], 'y' => $r['rate_y'], 'b' => $r['rate_b']];
                }
            }
            return ['r' => 0, 'y' => 0, 'b' => 0];
        }
    }

    foreach ($all_shifts_this_month as $shift) {
        $val = trim(strtoupper($shift['shift_type']));
        $rates = getPayRateById($shift['pay_rate_id'], $pay_rates_db);
        
        if ($val === 'ร' || $val === 'N') { $total_shifts_count++; $estimated_budget += $rates['r']; $chart_dist_r++; }
        elseif ($val === 'ย' || $val === 'O') { $total_shifts_count++; $estimated_budget += $rates['y']; $chart_dist_y++; }
        elseif ($val === 'บ' || $val === 'A') { $total_shifts_count++; $estimated_budget += $rates['b']; $chart_dist_b++; }
        elseif ($val === 'บ/ร') { $total_shifts_count += 2; $estimated_budget += ($rates['b'] + $rates['r']); $chart_dist_b++; $chart_dist_r++; }
        elseif ($val === 'ย/บ') { $total_shifts_count += 2; $estimated_budget += ($rates['y'] + $rates['b']); $chart_dist_y++; $chart_dist_b++; }
    }

    // ข้อมูลสำหรับกราฟ Bar (Top 5 คนขึ้นเวรเยอะสุด)
    try {
        $stmt = $db->prepare("SELECT u.name, COUNT(s.id) as count_shift 
                              FROM shifts s JOIN users u ON s.user_id = u.id 
                              WHERE s.hospital_id = ? AND s.shift_date LIKE ? AND s.shift_type != '' AND s.shift_type != 'L'
                              GROUP BY s.user_id ORDER BY count_shift DESC LIMIT 5");
        $stmt->execute([$hospital_id, $current_month . '%']);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $ts) {
            $name_parts = explode(' ', trim($ts['name']));
            $top_staff_names[] = $name_parts[0]; // เอาแค่ชื่อหน้าให้กราฟไม่ล้น
            $top_staff_counts[] = $ts['count_shift'];
        }
    } catch (Exception $e) {}

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM leaves WHERE hospital_id = ? AND status = 'PENDING'");
        $stmt->execute([$hospital_id]);
        $manager_pending_leaves = $stmt->fetchColumn();
    } catch (Exception $e) {}

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM shift_swaps WHERE hospital_id = ? AND status = 'PENDING_DIRECTOR'");
        $stmt->execute([$hospital_id]);
        $manager_pending_swaps = $stmt->fetchColumn();
    } catch (Exception $e) {}
}

// =========================================================
// 📊 2. ประมวลผลข้อมูลส่วนตัว (Staff)
// =========================================================
$my_shifts_count = 0;
$my_pending_leaves = 0;
$my_pending_swaps = 0;

// ตัวแปรสำหรับกราฟ (Staff)
$my_chart_r = 0; $my_chart_b = 0; $my_chart_y = 0;

$stmt = $db->prepare("SELECT shift_type, shift_date FROM shifts WHERE user_id = ? AND shift_date LIKE ?");
$stmt->execute([$user_id, $current_month . '%']);
$my_shifts_this_month = $stmt->fetchAll(PDO::FETCH_ASSOC);
$my_working_days_set = []; // เก็บวันที่ทำงานไปแล้วเพื่อคำนวณวันหยุด

foreach($my_shifts_this_month as $s) {
    $val = trim(strtoupper($s['shift_type']));
    $date = $s['shift_date'];
    
    if (in_array($val, ['ร','N','ย','O','บ','A','บ/ร','ย/บ'])) {
        $my_working_days_set[$date] = true; // นับว่าวันนี้ทำงาน
    }

    if ($val === 'ร' || $val === 'N') { $my_shifts_count++; $my_chart_r++; }
    elseif ($val === 'บ' || $val === 'A') { $my_shifts_count++; $my_chart_b++; }
    elseif ($val === 'ย' || $val === 'O') { $my_shifts_count++; $my_chart_y++; }
    elseif ($val === 'บ/ร') { $my_shifts_count += 2; $my_chart_b++; $my_chart_r++; }
    elseif ($val === 'ย/บ') { $my_shifts_count += 2; $my_chart_y++; $my_chart_b++; }
}

$my_total_working_days = count($my_working_days_set);
$my_rest_days = $days_in_month - $my_total_working_days;

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM leaves WHERE user_id = ? AND status = 'PENDING'");
    $stmt->execute([$user_id]);
    $my_pending_leaves = $stmt->fetchColumn();
} catch (Exception $e) {}

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM shift_swaps WHERE target_user_id = ? AND status = 'PENDING_TARGET'");
    $stmt->execute([$user_id]);
    $my_pending_swaps = $stmt->fetchColumn();
} catch (Exception $e) {}

$stmt = $db->prepare("SELECT shift_date, shift_type FROM shifts WHERE user_id = ? AND shift_date >= ? ORDER BY shift_date ASC LIMIT 5");
$stmt->execute([$user_id, $today]);
$my_upcoming_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$shiftModel = new ShiftModel($db);
$roster_status = $shiftModel->getRosterStatus($hospital_id, $current_month);

$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$month_th = $thai_months[(int)date('m')] . ' ' . (date('Y') + 543);
?>

<!-- 🌟 โหลดไลบรารี Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .dashboard-card { border: none; border-radius: 1.5rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; position: relative; background: #fff; z-index: 1; }
    .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.08) !important; }
    
    .stat-card::before {
        content: ''; position: absolute; top: -20px; right: -20px; width: 120px; height: 120px;
        background: radial-gradient(circle, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0) 70%);
        border-radius: 50%; z-index: -1; pointer-events: none;
    }

    .icon-circle { width: 55px; height: 55px; border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 26px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: transform 0.3s; }
    .dashboard-card:hover .icon-circle { transform: scale(1.1) rotate(5deg); }

    .bg-grad-primary { background: linear-gradient(135deg, #eff6ff 0%, #bfdbfe 100%); color: #1d4ed8; }
    .bg-grad-success { background: linear-gradient(135deg, #f0fdf4 0%, #bbf7d0 100%); color: #15803d; }
    .bg-grad-warning { background: linear-gradient(135deg, #fffbeb 0%, #fde68a 100%); color: #b45309; }
    .bg-grad-danger  { background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); color: #b91c1c; }
    .bg-grad-purple  { background: linear-gradient(135deg, #faf5ff 0%, #e9d5ff 100%); color: #7e22ce; }

    .quick-action-btn { display: flex; align-items: center; gap: 15px; padding: 1.2rem; border-radius: 1rem; border: 1px solid rgba(0,0,0,0.05); text-decoration: none; transition: all 0.2s; background: #fff; }
    .quick-action-btn:hover { background: #f8fafc; border-color: #cbd5e1; transform: translateX(5px); }
    .quick-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

    .timeline-item { position: relative; padding-left: 30px; margin-bottom: 20px; }
    .timeline-item::before { content: ''; position: absolute; left: 7px; top: 0; bottom: -20px; width: 2px; background-color: #e2e8f0; }
    .timeline-item:last-child::before { display: none; }
    .timeline-badge { position: absolute; left: -1px; top: 2px; width: 18px; height: 18px; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 0 0 1px #cbd5e1; }
    
    /* สไตล์สำหรับ Canvas ของกราฟ */
    .chart-container { position: relative; height: 240px; width: 100%; display: flex; justify-content: center; align-items: center; }
</style>

<div class="container-fluid px-3 px-md-4 py-4 bg-light min-vh-100">
    
    <!-- 🌟 Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom border-2 border-opacity-10 border-primary gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white shadow-sm border rounded-circle d-flex align-items-center justify-content-center text-primary" style="width: 60px; height: 60px; font-size: 28px;">👋</div>
            <div>
                <h3 class="fw-bolder text-dark mb-1">สวัสดี, <?= htmlspecialchars($_SESSION['user']['name']) ?></h3>
                <p class="text-muted mb-0 fw-medium">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 rounded-pill me-1"><?= $role ?></span>
                    ระบบจัดการตารางปฏิบัติงาน (Roster Pro)
                </p>
            </div>
        </div>
        <div class="text-start text-md-end bg-white p-3 rounded-4 shadow-sm border">
            <div class="fs-6 fw-bold text-dark"><i class="bi bi-calendar2-check text-primary me-2"></i><?= date('d') ?> <?= $thai_months[(int)date('m')] ?> <?= date('Y') + 543 ?></div>
        </div>
    </div>

    <!-- 🌟 Widget สถิติ 4 ช่อง -->
    <div class="row g-3 mb-4">
        <?php if ($is_manager): ?>
            <!-- มุมมองผู้บริหาร -->
            <div class="col-xl-3 col-sm-6"><div class="card dashboard-card stat-card bg-grad-primary shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="fw-bolder mb-0"><?= number_format($total_staff) ?></h2><div class="fw-semibold opacity-75" style="font-size: 13px;">บุคลากรในสังกัด</div></div><div class="icon-circle bg-white text-primary"><i class="bi bi-people-fill"></i></div></div></div></div></div>
            <div class="col-xl-3 col-sm-6"><div class="card dashboard-card stat-card bg-grad-purple shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="fw-bolder mb-0"><?= number_format($total_shifts_count) ?></h2><div class="fw-semibold opacity-75" style="font-size: 13px;">กะเวรทั้งหมดเดือนนี้</div></div><div class="icon-circle bg-white" style="color: #7e22ce;"><i class="bi bi-calendar-check-fill"></i></div></div></div></div></div>
            <div class="col-xl-3 col-sm-6"><div class="card dashboard-card stat-card bg-grad-warning shadow-sm h-100 <?= ($manager_pending_leaves > 0 || $manager_pending_swaps > 0) ? 'border border-warning border-2' : '' ?>"><div class="card-body"><div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="fw-bolder mb-0"><?= number_format($manager_pending_leaves + $manager_pending_swaps) ?></h2><div class="fw-semibold opacity-75" style="font-size: 13px;">คำร้องรออนุมัติ</div></div><div class="icon-circle bg-white text-warning"><i class="bi bi-envelope-exclamation-fill"></i></div></div><?php if($manager_pending_leaves > 0 || $manager_pending_swaps > 0): ?><div class="small fw-bold opacity-75 mt-2 border-top pt-2 border-warning border-opacity-25"><i class="bi bi-circle-fill" style="font-size:6px;"></i> ลา: <?= $manager_pending_leaves ?> | <i class="bi bi-circle-fill" style="font-size:6px;"></i> แลกเวร: <?= $manager_pending_swaps ?></div><?php endif; ?></div></div></div>
            <div class="col-xl-3 col-sm-6"><div class="card dashboard-card stat-card bg-grad-success shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start mb-3"><div><h3 class="fw-bolder mb-0">฿<?= number_format($estimated_budget) ?></h3><div class="fw-semibold opacity-75" style="font-size: 13px;">งบประมาณเดือนนี้</div></div><div class="icon-circle bg-white text-success"><i class="bi bi-cash-coin"></i></div></div></div></div></div>
        <?php else: ?>
            <!-- มุมมอง Staff -->
            <div class="col-xl-3 col-sm-6"><div class="card dashboard-card stat-card bg-grad-primary shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="fw-bolder mb-0"><?= number_format($my_shifts_count) ?> <span class="fs-6 fw-normal opacity-75">กะ</span></h2><div class="fw-semibold opacity-75" style="font-size: 13px;">เวรของฉันเดือนนี้</div></div><div class="icon-circle bg-white text-primary"><i class="bi bi-person-workspace"></i></div></div></div></div></div>
            <div class="col-xl-3 col-sm-6"><div class="card dashboard-card stat-card bg-grad-warning shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="fw-bolder mb-0"><?= number_format($my_pending_swaps) ?> <span class="fs-6 fw-normal opacity-75">รายการ</span></h2><div class="fw-semibold opacity-75" style="font-size: 13px;">เพื่อนขอแลกเวร (รอรับ)</div></div><div class="icon-circle bg-white text-warning"><i class="bi bi-arrow-left-right"></i></div></div><?php if($my_pending_swaps > 0): ?><a href="index.php?c=swap" class="stretched-link"></a><?php endif; ?></div></div></div>
            <div class="col-xl-3 col-sm-6"><div class="card dashboard-card stat-card bg-grad-danger shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="fw-bolder mb-0"><?= number_format($my_pending_leaves) ?> <span class="fs-6 fw-normal opacity-75">ใบ</span></h2><div class="fw-semibold opacity-75" style="font-size: 13px;">การลางาน (รออนุมัติ)</div></div><div class="icon-circle bg-white text-danger"><i class="bi bi-file-earmark-medical"></i></div></div></div></div></div>
            <div class="col-xl-3 col-sm-6"><div class="card dashboard-card stat-card bg-grad-success shadow-sm h-100"><div class="card-body d-flex flex-column justify-content-center"><div class="d-flex align-items-center gap-3"><div class="icon-circle bg-white text-success flex-shrink-0"><i class="bi bi-calendar-check-fill"></i></div><div><h6 class="fw-bolder mb-1">ตารางเวรประกาศแล้ว</h6><a href="index.php?c=roster" class="btn btn-sm btn-light text-success fw-bold rounded-pill px-3 mt-1 shadow-sm">ดูตารางรวม</a></div></div></div></div></div>
        <?php endif; ?>
    </div>

    <!-- 🌟 โซนกราฟสถิติ (Charts Area) -->
    <div class="row g-4 mb-4">
        <!-- กราฟที่ 1 -->
        <div class="col-lg-6">
            <div class="card dashboard-card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-pie-chart-fill text-primary me-2"></i> 
                        <?= $is_manager ? 'สัดส่วนประเภทเวร (ภาพรวมหน่วยบริการ)' : 'สัดส่วนประเภทเวรของฉัน' ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="shiftTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- กราฟที่ 2 -->
        <div class="col-lg-6">
            <div class="card dashboard-card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <?php if ($is_manager): ?>
                            <i class="bi bi-bar-chart-line-fill text-purple me-2" style="color: #7e22ce;"></i> บุคลากรที่ขึ้นเวรสูงสุด 5 อันดับแรก (Workload)
                        <?php else: ?>
                            <i class="bi bi-heart-pulse-fill text-danger me-2"></i> ความสมดุลการทำงาน (Work-Life Balance)
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="secondaryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 โซนเมนูลัด & Upcoming Shifts -->
    <div class="row g-4">
        <div class="col-lg-8">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-lightning-charge-fill text-warning me-2"></i> เมนูลัด (Quick Actions)</h5>
            <div class="row g-3">
                <?php if ($is_manager): ?>
                    <div class="col-md-6"><a href="index.php?c=roster&a=index" class="quick-action-btn shadow-sm"><div class="quick-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calendar3"></i></div><div><h6 class="fw-bold text-dark mb-1">จัดการตารางเวร</h6><small class="text-muted">จัดเวร / ตรวจสอบความถูกต้อง</small></div></a></div>
                    <div class="col-md-6"><a href="index.php?c=leave&a=approvals" class="quick-action-btn shadow-sm position-relative"><?php if($manager_pending_leaves > 0): ?><span class="position-absolute top-0 end-0 mt-2 me-2 badge bg-danger rounded-pill shadow-sm"><?= $manager_pending_leaves ?></span><?php endif; ?><div class="quick-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-check2-square"></i></div><div><h6 class="fw-bold text-dark mb-1">อนุมัติการลางาน</h6><small class="text-muted">ตรวจสอบและอนุมัติใบลา</small></div></a></div>
                    <div class="col-md-6"><a href="index.php?c=swap" class="quick-action-btn shadow-sm position-relative"><?php if($manager_pending_swaps > 0): ?><span class="position-absolute top-0 end-0 mt-2 me-2 badge bg-danger rounded-pill shadow-sm"><?= $manager_pending_swaps ?></span><?php endif; ?><div class="quick-icon bg-warning bg-opacity-10 text-warning text-dark"><i class="bi bi-arrow-left-right"></i></div><div><h6 class="fw-bold text-dark mb-1">อนุมัติแลกเวร</h6><small class="text-muted">คำขอสลับเวรจากเจ้าหน้าที่</small></div></a></div>
                    <div class="col-md-6"><a href="index.php?c=report&a=overview" class="quick-action-btn shadow-sm"><div class="quick-icon bg-success bg-opacity-10 text-success"><i class="bi bi-bar-chart-line-fill"></i></div><div><h6 class="fw-bold text-dark mb-1">สรุปการส่งเวร</h6><small class="text-muted">ดูรายงานและสถานะของแต่ละเดือน</small></div></a></div>
                <?php else: ?>
                    <div class="col-md-6"><a href="index.php?c=profile&a=schedule" class="quick-action-btn shadow-sm"><div class="quick-icon bg-success bg-opacity-10 text-success"><i class="bi bi-calendar-heart-fill"></i></div><div><h6 class="fw-bold text-dark mb-1">ตารางเวรส่วนตัว</h6><small class="text-muted">ดูปฏิทินเฉพาะเวรของฉัน</small></div></a></div>
                    <div class="col-md-6"><a href="index.php?c=leave&a=index" class="quick-action-btn shadow-sm"><div class="quick-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-file-earmark-medical"></i></div><div><h6 class="fw-bold text-dark mb-1">ยื่นใบลา</h6><small class="text-muted">ลาพักผ่อน / ป่วย / ลากิจ</small></div></a></div>
                    <div class="col-md-6"><a href="index.php?c=swap" class="quick-action-btn shadow-sm position-relative"><?php if($my_pending_swaps > 0): ?><span class="position-absolute top-0 end-0 mt-2 me-2 badge bg-danger rounded-pill shadow-sm"><?= $my_pending_swaps ?></span><?php endif; ?><div class="quick-icon bg-warning bg-opacity-10 text-warning text-dark"><i class="bi bi-arrow-left-right"></i></div><div><h6 class="fw-bold text-dark mb-1">ขอแลกเวร/เปลี่ยนเวร</h6><small class="text-muted">สลับตารางเวรกับเพื่อนร่วมงาน</small></div></a></div>
                    <div class="col-md-6"><a href="index.php?c=profile" class="quick-action-btn shadow-sm"><div class="quick-icon bg-info bg-opacity-10 text-info"><i class="bi bi-person-circle"></i></div><div><h6 class="fw-bold text-dark mb-1">แก้ไขข้อมูลส่วนตัว</h6><small class="text-muted">เปลี่ยนรหัสผ่าน / ข้อมูลติดต่อ</small></div></a></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ฝั่งขวา: เวรที่กำลังจะถึง -->
        <div class="col-lg-4">
            <div class="card dashboard-card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-primary me-2"></i> เวรปฏิบัติงานของฉัน 5 วันถัดไป</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($my_upcoming_shifts)): ?>
                        <div class="text-center py-5 text-muted">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;"><i class="bi bi-emoji-smile fs-1 text-secondary opacity-50"></i></div>
                            <h6 class="fw-bold text-dark">พักผ่อนให้เต็มที่!</h6>
                            <p class="small mb-0">คุณไม่มีเวรปฏิบัติงานในเร็วๆ นี้</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline mt-2">
                            <?php foreach ($my_upcoming_shifts as $index => $myshift): 
                                $date_obj = new DateTime($myshift['shift_date']);
                                $thai_date = $date_obj->format('d') . ' ' . $thai_months[(int)$date_obj->format('m')];
                                $s_val = strtoupper(trim($myshift['shift_type']));
                                $s_bg = 'bg-secondary'; $s_label = $s_val;
                                if ($s_val == 'ร') { $s_bg = 'bg-success'; $s_label = 'เวรดึก (On call)'; }
                                elseif ($s_val == 'บ') { $s_bg = 'bg-primary'; $s_label = 'เวรบ่าย'; }
                                elseif ($s_val == 'ย') { $s_bg = 'bg-danger'; $s_label = 'วันหยุดราชการ'; }
                                elseif ($s_val == 'บ/ร') { $s_bg = 'bg-info text-dark'; $s_label = 'เวรบ่าย + ดึก'; }
                            ?>
                                <div class="timeline-item">
                                    <div class="timeline-badge <?= $index === 0 ? 'bg-warning' : 'bg-primary' ?>"></div>
                                    <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded-3 shadow-sm border border-white">
                                        <div>
                                            <h6 class="fw-bold mb-1 <?= $index === 0 ? 'text-dark' : 'text-secondary' ?>" style="font-size: 14px;"><i class="bi bi-calendar-event me-1"></i> วันที่ <?= $thai_date ?></h6>
                                            <span class="badge <?= $s_bg ?> rounded-pill px-2"><?= htmlspecialchars($s_label) ?></span>
                                        </div>
                                        <?php if ($index === 0 && $myshift['shift_date'] == $today): ?><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill">วันนี้</span><?php endif; ?>
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

<!-- 🌟 Script สำหรับเรนเดอร์กราฟ Chart.js -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ตั้งค่าฟอนต์มาตรฐานให้ Chart.js
    Chart.defaults.font.family = "'Prompt', 'Kanit', sans-serif";
    Chart.defaults.color = '#64748b';

    const isManager = <?= $is_manager ? 'true' : 'false' ?>;

    // ==========================================
    // 📊 กราฟที่ 1: โดนัท (Doughnut) สัดส่วนประเภทเวร
    // ==========================================
    const distData = isManager 
        ? [<?= $chart_dist_r ?>, <?= $chart_dist_b ?>, <?= $chart_dist_y ?>] 
        : [<?= $my_chart_r ?>, <?= $my_chart_b ?>, <?= $my_chart_y ?>];
        
    const ctx1 = document.getElementById('shiftTypeChart').getContext('2d');
    new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: ['เวรดึก (ร)', 'เวรบ่าย (บ)', 'วันหยุด (ย)'],
            datasets: [{
                data: distData,
                backgroundColor: ['#16a34a', '#2563eb', '#dc2626'],
                hoverOffset: 10,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { weight: 'bold' } } }
            }
        }
    });

    // ==========================================
    // 📊 กราฟที่ 2: Bar Chart (Manager) หรือ Pie Chart (Staff)
    // ==========================================
    const ctx2 = document.getElementById('secondaryChart').getContext('2d');
    
    if (isManager) {
        // Manager: กราฟแท่ง Top 5 Workload
        const topNames = <?php echo json_encode($top_staff_names); ?>;
        const topCounts = <?php echo json_encode($top_staff_counts); ?>;
        
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: topNames.length > 0 ? topNames : ['ไม่มีข้อมูล'],
                datasets: [{
                    label: 'จำนวนกะ (กะ)',
                    data: topCounts.length > 0 ? topCounts : [0],
                    backgroundColor: 'rgba(126, 34, 206, 0.7)',
                    borderColor: '#7e22ce',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 5 }, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { padding: 12, cornerRadius: 8 }
                }
            }
        });
    } else {
        // Staff: กราฟพาย Work-Life Balance
        const workDays = <?= $my_total_working_days ?>;
        const restDays = <?= $my_rest_days ?>;

        new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: ['วันทำงาน', 'วันพักผ่อน'],
                datasets: [{
                    data: [workDays, restDays],
                    backgroundColor: ['#f59e0b', '#10b981'],
                    hoverOffset: 10,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { weight: 'bold' } } }
                }
            }
        });
    }
});
</script>