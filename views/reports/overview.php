<?php
// ที่อยู่ไฟล์: views/reports/overview.php

// 🌟 จำลองการรับค่าจาก Controller
$selected_month = $selected_month ?? date('m');
$selected_year = $selected_year ?? date('Y');
$hospitals_data = $hospitals_data ?? []; 

// ข้อมูลผู้ใช้งานปัจจุบัน
$current_role = strtoupper($_SESSION['user']['role'] ?? 'STAFF');
$my_hospital_id = $_SESSION['user']['hospital_id'] ?? 0;
$is_admin = in_array($current_role, ['ADMIN', 'SUPERADMIN']);

// 🌟 ระบบคำนวณสถิติภาพรวมจาก Data
$total_hospitals = 0;
$completed_hospitals = 0;
$total_staff = 0;
$on_duty_today = 0;
$on_leave_today = 0;
$total_budget = 0;

$my_hosp_data = null; // เก็บข้อมูลหน่วยงานของตัวเอง

foreach ($hospitals_data as $h) {
    // 🛑 ข้ามหน่วยงาน "ส่วนกลาง" หรือ ID = 0 ออกจากการคำนวณ
    if (empty($h['hospital_id']) || $h['hospital_id'] == '0' || mb_strpos($h['hospital_name'], 'ส่วนกลาง') !== false) {
        continue; 
    }
    
    // ดึงข้อมูลหน่วยงานของตัวเองมาเก็บไว้โชว์กล่องพิเศษ
    if ($h['hospital_id'] == $my_hospital_id) {
        $my_hosp_data = $h;
    }

    $total_hospitals++;
    $total_staff += ($h['total_staff'] ?? 0);
    $on_duty_today += ($h['on_duty_today'] ?? 0);
    $on_leave_today += ($h['on_leave_today'] ?? 0);
    $total_budget += ($h['total_estimated_cost'] ?? 0);
    
    if (in_array(($h['schedule_status'] ?? ''), ['APPROVED', 'SUBMITTED'])) {
        $completed_hospitals++;
    }
}

// คำนวณเปอร์เซ็นต์ความคืบหน้า
$completion_percent = $total_hospitals > 0 ? round(($completed_hospitals / $total_hospitals) * 100) : 0;
$progress_color = $completion_percent == 100 ? 'bg-success' : ($completion_percent >= 50 ? 'bg-primary' : 'bg-warning');

$thai_months = [
    "01"=>"มกราคม", "02"=>"กุมภาพันธ์", "03"=>"มีนาคม", "04"=>"เมษายน",
    "05"=>"พฤษภาคม", "06"=>"มิถุนายน", "07"=>"กรกฎาคม", "08"=>"สิงหาคม",
    "09"=>"กันยายน", "10"=>"ตุลาคม", "11"=>"พฤศจิกายน", "12"=>"ธันวาคม"
];
?>

<style>
    /* ==========================================================================
       🌟 Premium Modern UI Styles สำหรับหน้า Overview (Role-Based)
       ========================================================================== */
    body { background-color: #f8fafc; }
    
    .animate-fade-in { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .kpi-card { border: none; border-radius: 1.25rem; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: all 0.3s ease; position: relative; overflow: hidden; }
    .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.06); }
    .kpi-icon-wrapper { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.7rem; }
    
    .custom-progress { height: 8px; border-radius: 50rem; background-color: #f1f5f9; overflow: hidden; }
    .custom-progress-bar { height: 100%; border-radius: 50rem; transition: width 1s ease-in-out; }

    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .table-modern th { font-weight: 700; color: #64748b; font-size: 12.5px; background-color: #f8fafc; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding: 1.2rem 1rem; letter-spacing: 0.5px; }
    .table-modern td { vertical-align: middle; font-size: 14.5px; border-bottom: 1px solid #f1f5f9; padding: 1.2rem 1rem; transition: all 0.2s; }
    .table-modern tbody tr:hover td { background-color: #f8fafc; }
    
    /* 🌟 ไฮไลท์พิเศษสำหรับหน่วยงานของตัวเอง */
    .row-my-hospital td { background-color: #f0f9ff !important; border-top: 2px solid #bae6fd; border-bottom: 2px solid #bae6fd; }
    .row-my-hospital:hover td { background-color: #e0f2fe !important; }
    .my-hosp-badge { font-size: 10px; background: #0ea5e9; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 8px; font-weight: bold; vertical-align: middle; }

    .status-badge { padding: 0.4rem 0.8rem; border-radius: 8px; font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 5px; }
    .status-approved { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .status-submitted { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .status-pending { background-color: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
    .status-draft { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    .search-filter-group { border: 1px solid #e2e8f0; border-radius: 50rem; overflow: hidden; background: #fff; transition: all 0.2s; }
    .search-filter-group:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .search-filter-group input, .search-filter-group select { border: none; box-shadow: none; background: transparent; }
    .search-filter-group input:focus, .search-filter-group select:focus { outline: none; box-shadow: none; }
    
    .privacy-blur { filter: blur(4px); user-select: none; opacity: 0.5; transition: 0.2s; }
    .privacy-blur:hover { filter: blur(0px); opacity: 1; }
    
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100">

    <!-- 🌟 ส่วนหัว และ ตัวกรอง -->
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center mb-4 gap-3 animate-fade-in">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-4 d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 60px; height: 60px;">
                <i class="bi bi-bar-chart-line-fill fs-3"></i>
            </div>
            <div>
                <h3 class="fw-bolder text-dark mb-1" style="letter-spacing: -0.5px;">ภาพรวมเครือข่าย (Network Overview)</h3>
                <p class="text-muted mb-0 fw-medium" style="font-size: 14px;">แดชบอร์ดสรุปสถานะการปฏิบัติงานระดับอำเภอ <span class="badge bg-secondary ms-1"><?= $current_role ?> MODE</span></p>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center bg-white p-2 rounded-pill shadow-sm border">
            <form action="index.php" method="GET" class="d-flex gap-2 mb-0 align-items-center">
                <input type="hidden" name="c" value="reports">
                <input type="hidden" name="a" value="overview">
                
                <i class="bi bi-calendar-event text-primary ms-3"></i>
                <select name="month" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark px-1 cursor-pointer" onchange="this.form.submit()" style="width: 110px;">
                    <?php foreach($thai_months as $m_num => $m_name): ?>
                        <option value="<?= $m_num ?>" <?= $selected_month == $m_num ? 'selected' : '' ?>><?= $m_name ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select name="year" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark px-1 cursor-pointer" style="width: 70px;" onchange="this.form.submit()">
                    <?php for($i = date('Y')-2; $i <= date('Y')+1; $i++): ?>
                        <option value="<?= $i ?>" <?= $selected_year == $i ? 'selected' : '' ?>><?= $i + 543 ?></option>
                    <?php endfor; ?>
                </select>
            </form>

            <div class="vr mx-1 opacity-25"></div>

            <button class="btn btn-light rounded-circle text-secondary hover-shadow" onclick="window.print()" title="พิมพ์รายงาน">
                <i class="bi bi-printer-fill"></i>
            </button>
            <?php if($is_admin): ?>
            <button class="btn btn-success rounded-pill fw-bold shadow-sm px-3" title="ส่งออกข้อมูลเป็น Excel" onclick="exportTableToExcel('overviewTable', 'ภาพรวมเครือข่าย_<?= $thai_months[$selected_month] ?>')">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Excel
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php 
    // 🌟 Personalized Alert (สำหรับระดับหน่วยงาน ที่จัดเวรของตัวเอง)
    if (!$is_admin && $my_hosp_data): 
        $my_status = strtoupper($my_hosp_data['schedule_status'] ?? '');
        $alert_class = 'alert-secondary'; $alert_icon = 'bi-info-circle'; $alert_msg = 'สถานะตารางเวรของหน่วยงานคุณ';
        
        if ($my_status === 'DRAFT') { $alert_class = 'alert-warning border-warning'; $alert_icon = 'bi-pencil-square'; $alert_msg = 'ตารางเวรเดือนนี้ยังจัดไม่เสร็จ (Draft) กรุณาจัดเวรและกดส่งขออนุมัติ'; }
        elseif ($my_status === 'SUBMITTED') { $alert_class = 'alert-info border-info'; $alert_icon = 'bi-send-fill'; $alert_msg = 'ส่งตารางเวรแล้ว กำลังรอส่วนกลางพิจารณาอนุมัติ'; }
        elseif ($my_status === 'APPROVED') { $alert_class = 'alert-success border-success'; $alert_icon = 'bi-check-circle-fill'; $alert_msg = 'ตารางเวรของท่าน <strong>ได้รับการอนุมัติเรียบร้อยแล้ว</strong>'; }
    ?>
    <div class="alert <?= $alert_class ?> bg-white shadow-sm border-start border-4 rounded-4 mb-4 d-flex align-items-center justify-content-between p-4 animate-fade-in">
        <div class="d-flex align-items-center gap-3">
            <div class="fs-1 text-<?= str_replace('alert-', '', $alert_class) ?> opacity-75"><i class="bi <?= $alert_icon ?>"></i></div>
            <div>
                <h5 class="fw-bold mb-1 text-dark">สถานะหน่วยงานของท่าน (<?= htmlspecialchars($my_hosp_data['hospital_name']) ?>)</h5>
                <p class="mb-0 text-muted"><?= $alert_msg ?></p>
            </div>
        </div>
        <a href="index.php?c=roster&hospital_id=<?= $my_hospital_id ?>&month=<?= $selected_year ?>-<?= $selected_month ?>" class="btn btn-<?= str_replace('alert-', '', $alert_class) ?> rounded-pill fw-bold px-4 shadow-sm">
            ไปยังตารางเวร <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <?php endif; ?>

    <!-- 🌟 Top KPI Cards -->
    <div class="row g-4 mb-4 animate-fade-in" style="animation-delay: 0.1s;">
        <!-- 1. ความคืบหน้าการส่งเวร -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-4 h-100 border-bottom border-primary border-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <p class="text-muted fw-bold mb-1" style="font-size: 13px; text-transform:uppercase;">ความคืบหน้าภาพรวม</p>
                        <h2 class="fw-bolder text-dark mb-0"><?= $completed_hospitals ?> <span class="fs-6 text-muted fw-normal">/ <?= $total_hospitals ?> แห่ง</span></h2>
                    </div>
                    <div class="kpi-icon-wrapper bg-primary bg-opacity-10 text-primary"><i class="bi bi-building-check"></i></div>
                </div>
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted" style="font-size: 12px;">ส่งแล้ว</span>
                        <span class="fw-bold text-primary" style="font-size: 12px;"><?= $completion_percent ?>%</span>
                    </div>
                    <div class="custom-progress"><div class="custom-progress-bar <?= $progress_color ?>" style="width: <?= $completion_percent ?>%;"></div></div>
                </div>
            </div>
        </div>

        <!-- 2. บุคลากรรวม & ปฏิบัติงานวันนี้ -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-4 h-100 border-bottom border-success border-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted fw-bold mb-1" style="font-size: 13px; text-transform:uppercase;">ผู้ปฏิบัติงานวันนี้</p>
                        <h2 class="fw-bolder text-success mb-0"><?= number_format($on_duty_today) ?> <span class="fs-6 text-muted fw-normal">คน</span></h2>
                    </div>
                    <div class="kpi-icon-wrapper bg-success bg-opacity-10 text-success"><i class="bi bi-person-workspace"></i></div>
                </div>
                <div class="mt-3 pt-3 border-top d-flex justify-content-between">
                    <span class="text-muted small">จากบุคลากรทั้งหมด</span>
                    <span class="fw-bold text-dark small"><?= number_format($total_staff) ?> คน</span>
                </div>
            </div>
        </div>

        <!-- 3. ลางาน/ไม่มาวันนี้ -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-4 h-100 border-bottom border-danger border-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted fw-bold mb-1" style="font-size: 13px; text-transform:uppercase;">ลางาน / ขาดราชการ</p>
                        <h2 class="fw-bolder text-danger mb-0"><?= number_format($on_leave_today) ?> <span class="fs-6 text-muted fw-normal">คน</span></h2>
                    </div>
                    <div class="kpi-icon-wrapper bg-danger bg-opacity-10 text-danger"><i class="bi bi-person-dash-fill"></i></div>
                </div>
                <div class="mt-3 pt-3 border-top d-flex justify-content-between">
                    <span class="text-muted small">สัดส่วนคนลางาน</span>
                    <span class="fw-bold text-danger small"><?= $total_staff > 0 ? round(($on_leave_today / $total_staff) * 100, 1) : 0 ?>%</span>
                </div>
            </div>
        </div>

        <!-- 4. งบประมาณรวม -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card p-4 h-100 border-bottom border-warning border-4" style="background: linear-gradient(to right, #fff, #fffbeb);">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted fw-bold mb-1" style="font-size: 13px; text-transform:uppercase;">
                            <?= $is_admin ? 'ประมาณการเบิกจ่ายรวม' : 'งบประมาณของหน่วยท่าน' ?>
                        </p>
                        <?php if ($is_admin): ?>
                            <h3 class="fw-bolder text-dark mb-0">฿<?= number_format($total_budget) ?></h3>
                        <?php else: ?>
                            <h3 class="fw-bolder text-dark mb-0 text-primary">฿<?= number_format($my_hosp_data['total_estimated_cost'] ?? 0) ?></h3>
                        <?php endif; ?>
                    </div>
                    <div class="kpi-icon-wrapper bg-warning bg-opacity-25 text-warning text-dark shadow-sm"><i class="bi bi-cash-coin"></i></div>
                </div>
                <div class="mt-3 pt-3 border-top border-warning border-opacity-25 d-flex justify-content-between">
                    <span class="text-muted small"><?= $is_admin ? 'รวมทุกหน่วยงาน (ที่ส่งเวรแล้ว)' : 'ประมาณการจากตารางเวร' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 Main Table: แยกตามหน่วยบริการ -->
    <div class="card card-modern flex-grow-1 d-flex flex-column animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <h6 class="mb-0 fw-bolder text-dark"><i class="bi bi-list-columns-reverse text-primary me-2"></i> สรุปสถานะแยกตามหน่วยบริการ / สังกัด</h6>
            
            <div class="search-filter-group d-flex align-items-center ps-3 pe-1 py-1" style="min-width: 320px;">
                <i class="bi bi-search text-muted"></i>
                <input type="text" id="searchInput" class="form-control form-control-sm px-2" placeholder="พิมพ์ค้นหาชื่อหน่วยงาน...">
                <div class="vr mx-2 opacity-25"></div>
                <select id="statusFilter" class="form-select form-select-sm fw-bold text-secondary" style="width: auto; cursor: pointer;">
                    <option value="all">ทุกสถานะ</option>
                    <option value="APPROVED">อนุมัติแล้ว</option>
                    <option value="SUBMITTED">รออนุมัติ</option>
                    <option value="DRAFT">กำลังจัดทำ</option>
                    <option value="NOT_STARTED">ยังไม่เริ่ม</option>
                </select>
            </div>
        </div>

        <div class="table-responsive custom-scrollbar flex-grow-1">
            <table class="table table-modern mb-0 align-middle" id="overviewTable">
                <thead>
                    <tr>
                        <th class="ps-4">หน่วยบริการ / หน่วยงาน</th>
                        <th class="text-center">กำลังคน</th>
                        <th class="text-center">เวรวันนี้</th>
                        <th>สถานะการจัดเวร</th>
                        <th class="text-end">ค่าตอบแทน (บาท)</th>
                        <th class="pe-4 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="hospitalsTableBody">
                    <?php 
                    $has_data = false;
                    
                    // 🌟 จัดเรียงให้หน่วยงานของตัวเอง (ถ้ามี) ขึ้นมาอยู่บรรทัดแรกสุดเสมอ
                    usort($hospitals_data, function($a, $b) use ($my_hospital_id) {
                        if ($a['hospital_id'] == $my_hospital_id) return -1;
                        if ($b['hospital_id'] == $my_hospital_id) return 1;
                        return strcmp($a['hospital_name'], $b['hospital_name']);
                    });

                    if (!empty($hospitals_data)): 
                        foreach ($hospitals_data as $h): 
                            
                            // 🛑 ข้ามหน่วยงาน "ส่วนกลาง" เด็ดขาด
                            if (empty($h['hospital_id']) || $h['hospital_id'] == '0' || mb_strpos($h['hospital_name'], 'ส่วนกลาง') !== false) continue;
                            
                            $has_data = true;
                            $is_my_hosp = ($h['hospital_id'] == $my_hospital_id);

                            // จัดการสไตล์สถานะ
                            $status_raw = strtoupper($h['schedule_status'] ?? '');
                            $status_class = 'status-draft'; $status_icon = 'bi-file-earmark-x'; $status_text = 'ยังไม่เริ่มจัดเวร';
                            
                            // 🌟 Contextual Actions: ออกแบบปุ่มตาม Role และ Status
                            $btn_text = 'ดูข้อมูล';
                            $btn_class = 'btn-outline-secondary text-dark border-secondary border-opacity-50';
                            $btn_icon = 'bi-eye';
                            $row_opacity = '1';
                            
                            if ($status_raw === 'APPROVED') {
                                $status_class = 'status-approved'; $status_icon = 'bi-check-circle-fill'; $status_text = 'อนุมัติแล้ว';
                                $btn_text = 'ดูตารางเวร'; $btn_class = 'btn-success'; $btn_icon = 'bi-search';
                            } elseif ($status_raw === 'SUBMITTED') {
                                $status_class = 'status-submitted'; $status_icon = 'bi-send-fill'; $status_text = 'ส่งแล้ว (รอตรวจ)';
                                if ($is_admin) { $btn_text = 'ตรวจสอบ'; $btn_class = 'btn-primary'; $btn_icon = 'bi-search'; }
                                elseif ($is_my_hosp && $current_role === 'DIRECTOR') { $btn_text = 'พิจารณาอนุมัติ'; $btn_class = 'btn-success'; $btn_icon = 'bi-check-circle-fill'; }
                                elseif ($is_my_hosp) { $btn_text = 'ดูความคืบหน้า'; $btn_class = 'btn-info text-white'; $btn_icon = 'bi-eye'; }
                            } elseif ($status_raw === 'PENDING') {
                                $status_class = 'status-pending'; $status_icon = 'bi-clock-fill'; $status_text = 'รอพิจารณา';
                                if ($is_admin || ($is_my_hosp && $current_role === 'DIRECTOR')) { $btn_text = 'ตรวจสอบ/พิจารณา'; $btn_class = 'btn-primary'; $btn_icon = 'bi-search'; }
                            } elseif ($status_raw === 'DRAFT' || $status_raw === 'REQUEST_EDIT') {
                                $status_class = 'status-draft'; $status_icon = 'bi-pencil-square'; $status_text = 'กำลังจัดทำ/แก้ไข';
                                if ($is_my_hosp && in_array($current_role, ['SCHEDULER', 'DIRECTOR'])) {
                                    $btn_text = 'แก้ไขตารางเวร'; $btn_class = 'btn-warning text-dark border-warning'; $btn_icon = 'bi-pencil-square';
                                } else {
                                    $btn_text = 'ดูความคืบหน้า'; $btn_class = 'btn-outline-primary'; $btn_icon = 'bi-eye';
                                }
                            } else {
                                $status_raw = 'NOT_STARTED';
                                $row_opacity = '0.7'; 
                                if ($is_my_hosp && in_array($current_role, ['SCHEDULER', 'DIRECTOR'])) {
                                    $btn_text = 'สร้างตารางเวร'; $btn_class = 'btn-primary'; $btn_icon = 'bi-plus-circle'; $row_opacity = '1';
                                }
                            }
                    ?>
                        <tr class="hosp-row <?= $is_my_hosp ? 'row-my-hospital' : '' ?>" data-status="<?= $status_raw ?>" style="opacity: <?= $row_opacity ?>;">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light text-primary border rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 42px; height: 42px;">
                                        <i class="bi <?= $is_my_hosp ? 'bi-house-heart-fill text-primary' : 'bi-building' ?> fs-5"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark hosp-name">
                                            <?= htmlspecialchars($h['hospital_name']) ?>
                                            <?= $is_my_hosp ? '<span class="my-hosp-badge shadow-sm">หน่วยงานของคุณ</span>' : '' ?>
                                        </h6>
                                        <div class="text-muted d-flex align-items-center gap-2 mt-1" style="font-size: 11px;">
                                            <span><i class="bi bi-geo-alt-fill text-danger opacity-75"></i> อ.<?= htmlspecialchars($h['district'] ?? '-') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="fw-bolder text-dark" style="font-size: 16px;"><?= number_format($h['total_staff'] ?? 0) ?></div>
                                <div class="text-muted" style="font-size: 11px;">คน</div>
                            </td>
                            <td class="text-center">
                                <?php if(($h['on_duty_today'] ?? 0) > 0): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2 fw-bold" style="font-size: 13px;">
                                        <i class="bi bi-person-check-fill me-1"></i> <?= $h['on_duty_today'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $status_class ?> shadow-sm">
                                    <i class="bi <?= $status_icon ?>"></i> <?= $status_text ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <?php 
                                // 🌟 Privacy Filter: ปกปิดงบประมาณสำหรับคนอื่นที่ไม่ใช่ Admin
                                if (!$is_admin && !$is_my_hosp): ?>
                                    <span class="text-muted small px-2 py-1 bg-light rounded border"><i class="bi bi-lock-fill"></i> ปกปิดข้อมูล</span>
                                <?php elseif (($h['total_estimated_cost'] ?? 0) > 0): ?>
                                    <div class="fw-bolder text-primary" style="font-size: 15px;"><?= number_format($h['total_estimated_cost'] ?? 0) ?> ฿</div>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-center">
                                <a href="index.php?c=roster&hospital_id=<?= $h['hospital_id'] ?>&month=<?= $selected_year ?>-<?= $selected_month ?>" 
                                   class="btn btn-sm <?= $btn_class ?> rounded-pill fw-bold shadow-sm px-3 hover-shadow text-nowrap">
                                    <?= $btn_text ?> <i class="bi <?= $btn_icon ?> ms-1"></i>
                                </a>
                            </td>
                        </tr>
                    <?php 
                        endforeach; 
                    endif; 
                    
                    if (!$has_data):
                    ?>
                        <tr id="emptyStateRow">
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted d-flex flex-column align-items-center">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                                        <i class="bi bi-building-slash fs-2 opacity-50"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark">ไม่พบข้อมูลหน่วยบริการ</h6>
                                    <p class="small mb-0">ลองปรับเปลี่ยนเงื่อนไขการค้นหา หรือยังไม่มีข้อมูลในเดือนที่เลือก</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <!-- แถวซ่อนสำหรับกรณี Search ไม่เจอ -->
                    <tr id="noResultRow" style="display: none;">
                        <td colspan="6" class="text-center py-5">
                            <div class="text-muted d-flex flex-column align-items-center">
                                <i class="bi bi-search fs-2 opacity-50 mb-2"></i>
                                <h6 class="fw-bold">ไม่พบข้อมูลที่ตรงกับเงื่อนไขการค้นหา</h6>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- 🌟 ระบบแบ่งหน้า (Pagination) -->
        <div class="p-3 border-top bg-light d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3" id="paginationWrapper" style="display: none; border-radius: 0 0 1.25rem 1.25rem;">
            <div class="text-muted small fw-medium" id="paginationInfo">แสดงข้อมูล...</div>
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0 d-none d-sm-block">แสดง:</label>
                <select id="rowsPerPageSelect" class="form-select form-select-sm text-secondary border shadow-sm" style="width: auto; cursor: pointer;">
                    <option value="10" selected>10 แห่ง</option>
                    <option value="25">25 แห่ง</option>
                    <option value="50">50 แห่ง</option>
                    <option value="all">ทั้งหมด</option>
                </select>
                <nav><ul class="pagination pagination-sm mb-0 shadow-sm" id="paginationControls"></ul></nav>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 🌟 ระบบค้นหาอัจฉริยะ และ แบ่งหน้า
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const tableBody = document.getElementById('hospitalsTableBody');
    const paginationWrapper = document.getElementById('paginationWrapper');
    const paginationControls = document.getElementById('paginationControls');
    const paginationInfo = document.getElementById('paginationInfo');
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');

    if (!tableBody) return;

    const allRows = Array.from(document.querySelectorAll('.hosp-row'));
    let currentPage = 1;
    let rowsPerPage = 10;
    let filteredRows = [...allRows];

    function updateTable() {
        const term = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const status = statusFilter ? statusFilter.value : 'all';
        let found = false;
        
        filteredRows = allRows.filter(row => {
            const name = row.querySelector('.hosp-name').textContent.toLowerCase();
            const rowStatus = row.getAttribute('data-status');
            
            const matchSearch = name.includes(term);
            const matchStatus = (status === 'all') || (rowStatus === status);
            
            if (matchSearch && matchStatus) {
                row.setAttribute('data-filtered', 'true');
                return true;
            } else {
                row.setAttribute('data-filtered', 'false');
                return false;
            }
        });

        const totalRows = filteredRows.length;
        const totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(totalRows / rowsPerPage);

        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = rowsPerPage === 'all' ? 0 : (currentPage - 1) * rowsPerPage;
        const endIdx = rowsPerPage === 'all' ? totalRows : startIdx + rowsPerPage;

        allRows.forEach(row => row.style.display = 'none');
        filteredRows.slice(startIdx, endIdx).forEach(row => row.style.display = '');

        const noResultRow = document.getElementById('noResultRow');
        const emptyStateRow = document.getElementById('emptyStateRow');
        
        if (emptyStateRow) {
            if (paginationWrapper) paginationWrapper.style.display = 'none';
            return; 
        }

        if (totalRows === 0 && allRows.length > 0) {
            if (noResultRow) noResultRow.style.display = '';
            if (paginationWrapper) paginationWrapper.style.display = 'none';
        } else {
            if (noResultRow) noResultRow.style.display = 'none';
            if (paginationWrapper) {
                paginationWrapper.style.display = allRows.length > 0 ? 'flex' : 'none';
                if(paginationInfo) paginationInfo.innerHTML = `แสดง <b>${startIdx + 1}</b> ถึง <b>${Math.min(endIdx, totalRows)}</b> จาก <b>${totalRows}</b> แห่ง`;
            }
        }

        if (paginationControls && totalPages > 0) {
            paginationControls.innerHTML = '';
            if (totalPages > 1) {
                let prevLi = document.createElement('li');
                prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
                prevLi.innerHTML = `<a class="page-link shadow-none" style="cursor:pointer;">&laquo;</a>`;
                if (currentPage > 1) { prevLi.onclick = (e) => { e.preventDefault(); currentPage--; updateTable(); }; }
                paginationControls.appendChild(prevLi);

                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);
                
                if (startPage > 1) {
                    let firstLi = document.createElement('li');
                    firstLi.className = `page-item`;
                    firstLi.innerHTML = `<a class="page-link shadow-none" style="cursor:pointer;">1</a>`;
                    firstLi.onclick = (e) => { e.preventDefault(); currentPage = 1; updateTable(); };
                    paginationControls.appendChild(firstLi);
                    if (startPage > 2) paginationControls.innerHTML += `<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>`;
                }

                for (let i = startPage; i <= endPage; i++) {
                    let li = document.createElement('li');
                    li.className = `page-item ${currentPage === i ? 'active' : ''}`;
                    li.innerHTML = `<a class="page-link shadow-none" style="cursor:pointer;">${i}</a>`;
                    li.onclick = (e) => { e.preventDefault(); currentPage = i; updateTable(); };
                    paginationControls.appendChild(li);
                }

                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) paginationControls.innerHTML += `<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>`;
                    let lastLi = document.createElement('li');
                    lastLi.className = `page-item`;
                    lastLi.innerHTML = `<a class="page-link shadow-none" style="cursor:pointer;">${totalPages}</a>`;
                    lastLi.onclick = (e) => { e.preventDefault(); currentPage = totalPages; updateTable(); };
                    paginationControls.appendChild(lastLi);
                }

                let nextLi = document.createElement('li');
                nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
                nextLi.innerHTML = `<a class="page-link shadow-none" style="cursor:pointer;">&raquo;</a>`;
                if (currentPage < totalPages) { nextLi.onclick = (e) => { e.preventDefault(); currentPage++; updateTable(); }; }
                paginationControls.appendChild(nextLi);
            }
        }
    }

    if (searchInput) searchInput.addEventListener('input', () => { currentPage = 1; updateTable(); });
    if (statusFilter) statusFilter.addEventListener('change', () => { currentPage = 1; updateTable(); });
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', function() {
            rowsPerPage = this.value === 'all' ? 'all' : parseInt(this.value);
            currentPage = 1; updateTable();
        });
    }

    updateTable(); 
});

function exportTableToExcel(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel;charset=utf-8';
    var tableSelect = document.getElementById(tableID);
    
    var tableClone = tableSelect.cloneNode(true);
    
    var ths = tableClone.querySelectorAll('thead tr th');
    if(ths.length > 0) ths[ths.length - 1].remove();

    var trs = tableClone.querySelectorAll('tbody tr');
    trs.forEach(tr => {
        if(tr.id !== 'noResultRow' && tr.id !== 'emptyStateRow' && tr.getAttribute('data-filtered') !== 'false') {
            tr.style.display = ''; 
            var tds = tr.querySelectorAll('td');
            if(tds.length > 0) tds[tds.length - 1].remove();
        } else {
            tr.remove(); 
        }
    });

    var tableHTML = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body>' + tableClone.outerHTML + '</body></html>';
    
    filename = filename ? filename + '.xls' : 'excel_data.xls';
    downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    
    if (navigator.msSaveOrOpenBlob){
        var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
        navigator.msSaveOrOpenBlob( blob, filename);
    } else {
        downloadLink.href = 'data:' + dataType + ', ' + encodeURIComponent(tableHTML);
        downloadLink.download = filename;
        downloadLink.click();
    }
    document.body.removeChild(downloadLink);
}
</script>