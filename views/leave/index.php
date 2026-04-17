<?php
// ที่อยู่ไฟล์: views/leave/index.php

// รับค่าประเภทการลาจาก URL ที่ส่งมาจาก Sidebar (ถ้ามี)
$selected_leave_type_req = isset($_GET['type']) ? trim($_GET['type']) : '';
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m'); // รับค่าเดือนเพื่อฟิลเตอร์ประวัติการลา

// 🌟 ฟังก์ชันแปลงวันที่เป็นรูปแบบไทยย่อ (สำหรับแสดงในตารางประวัติการลา)
function getShortThaiDateLeave($date_str) {
    if (empty($date_str)) return '-';
    $thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($date_str);
    $d = date('j', $ts);
    $m = $thai_months[(int)date('n', $ts)];
    $y = date('Y', $ts) + 543;
    return "{$d} {$m} {$y}";
}

// 🌟 กำหนดรูปแบบหน้าจอให้สอดคล้องกับเมนูที่เลือก
$page_title = 'ระบบจัดการวันลา';
$page_icon = 'bi-envelope-paper-heart';
$page_theme = 'primary';

if ($selected_leave_type_req == 'ลาพักผ่อน') {
    $page_title = 'ยื่นลาพักผ่อน';
    $page_icon = 'bi-brightness-high';
    $page_theme = 'success';
} elseif ($selected_leave_type_req == 'ลากิจส่วนตัว') {
    $page_title = 'ยื่นลากิจส่วนตัว';
    $page_icon = 'bi-briefcase';
    $page_theme = 'warning';
} elseif ($selected_leave_type_req == 'ลาป่วย') {
    $page_title = 'ยื่นลาป่วย';
    $page_icon = 'bi-bandaid';
    $page_theme = 'danger';
} elseif ($selected_leave_type_req != '') {
    $page_title = 'ยื่น' . htmlspecialchars($selected_leave_type_req);
    $page_icon = 'bi-file-earmark-text';
    $page_theme = 'primary';
} else {
    // กรณีไม่ได้เลือกประเภท (ยื่นลาอื่นๆ / ประวัติ)
    $page_title = 'ยื่นลาอื่นๆ / ประวัติการลา';
    $page_icon = 'bi-clock-history';
    $page_theme = 'secondary';
}
?>
<!-- นำเข้า CSS ของ Flatpickr สำหรับปฏิทิน -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    /* ========================================== */
    /* 🌟 Modern UI Styles สำหรับระบบจัดการวันลา */
    /* ========================================== */
    .card-modern {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 0.25rem 1.25rem rgba(0, 0, 0, 0.04);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        background: #ffffff;
    }
    .card-modern:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.08);
    }
    
    .card-stat {
        border: none;
        border-radius: 1rem;
        background: #ffffff;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        border-left: 5px solid;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card-stat:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    .card-stat.border-primary { border-left-color: #3b82f6; }
    .card-stat.border-danger { border-left-color: #ef4444; }
    .card-stat.border-warning { border-left-color: #f59e0b; }
    .card-stat.border-success { border-left-color: #10b981; }

    .icon-box { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
    .icon-box-sm { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .progress-thin { height: 8px; border-radius: 4px; background-color: #f1f5f9; overflow: hidden; }

    .input-group-modern { border: 1px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.2s; background-color: #f8fafc; overflow: hidden; }
    .input-group-modern:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15); background-color: #ffffff; }
    .input-group-modern .input-group-text, .input-group-modern .form-control, .input-group-modern .form-select { border: none; background: transparent; }
    .input-group-modern .form-control:focus, .input-group-modern .form-select:focus { box-shadow: none; }

    .form-select-modern, .form-control-modern { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.6rem 1rem; transition: all 0.2s; }
    .form-select-modern:focus, .form-control-modern:focus { background-color: #ffffff; border-color: #3b82f6; box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15); }

    .alert-modern { border: none; border-left: 4px solid; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
    .alert-modern.alert-success { border-left-color: #10b981; background-color: #ecfdf5; color: #065f46; }
    .alert-modern.alert-danger { border-left-color: #ef4444; background-color: #fef2f2; color: #991b1b; }

    .flatpickr-input[readonly] { cursor: pointer; }
    
    .btn-soft-primary { background-color: #eff6ff; color: #2563eb; border: none; }
    .btn-soft-primary:hover { background-color: #dbeafe; color: #1d4ed8; }
    .btn-soft-danger { background-color: #fef2f2; color: #dc2626; border: none; }
    .btn-soft-danger:hover { background-color: #fee2e2; color: #b91c1c; }
    
    .btn-gradient-primary { background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); color: white; border: none; transition: opacity 0.2s; }
    .btn-gradient-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; transition: opacity 0.2s; }
    .btn-gradient-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none; transition: opacity 0.2s; }
    .btn-gradient-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; transition: opacity 0.2s; }
    .btn-gradient-secondary { background: linear-gradient(135deg, #64748b 0%, #475569 100%); color: white; border: none; transition: opacity 0.2s; }
    
    .btn-gradient-primary:hover, .btn-gradient-success:hover, .btn-gradient-warning:hover, .btn-gradient-danger:hover, .btn-gradient-secondary:hover { 
        opacity: 0.9; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="mb-4">
        <h2 class="fw-bold text-dark d-flex align-items-center mb-1">
            <div class="icon-box-sm bg-<?= $page_theme ?> bg-opacity-10 text-<?= $page_theme ?> me-3">
                <i class="bi <?= $page_icon ?>"></i>
            </div>
            <?= $page_title ?>
        </h2>
        <p class="text-muted ms-5 ps-2 mb-0">ส่งแบบฟอร์มขออนุญาตลาออนไลน์ และตรวจสอบประวัติการลา</p>
    </div>

    <!-- แจ้งเตือนสถานะต่างๆ -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-modern alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i> <?= $_SESSION['success_msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); endif; ?>
        
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-modern alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i> <?= $_SESSION['error_msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); endif; ?>

    <div class="row g-4 mb-5">
        <!-- ========================================== -->
        <!-- 🌟 ส่วนที่ 1: ฟอร์มยื่นใบลา -->
        <!-- ========================================== -->
        <div class="col-lg-4">
            <div class="card card-modern h-100">
                <div class="card-header bg-white py-4 border-bottom px-4 d-flex align-items-center">
                    <div class="icon-box-sm bg-<?= $page_theme ?> bg-opacity-10 text-<?= $page_theme ?> me-3">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <h5 class="mb-0 fw-bold text-dark">
                        <?= $selected_leave_type_req ? 'แบบฟอร์ม' . htmlspecialchars($selected_leave_type_req) : 'ยื่นแบบฟอร์มขอลา' ?>
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="index.php?c=leave&a=request" method="POST" id="leaveForm" enctype="multipart/form-data">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">ประเภทการลา <span class="text-danger">*</span></label>
                            <select name="leave_type_id" id="leave_type" class="form-select form-select-modern" required>
                                <option value="">-- กรุณาเลือกประเภทการลา --</option>
                                <?php foreach($leave_types as $type): 
                                    $is_selected = ($selected_leave_type_req === $type['leave_type']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $type['id'] ?>" data-name="<?= htmlspecialchars($type['leave_type']) ?>" <?= $is_selected ?>>
                                        <?= htmlspecialchars($type['leave_type']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label fw-bold text-secondary small text-uppercase">ตั้งแต่วันที่ <span class="text-danger">*</span></label>
                                <div class="input-group-modern d-flex align-items-center bg-white">
                                    <span class="ps-3 text-primary"><i class="bi bi-calendar-event"></i></span>
                                    <input type="text" name="start_date" id="start_date" class="form-control px-2 fw-medium" required placeholder="คลิกเลือก" readonly style="background-color: transparent;">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-bold text-secondary small text-uppercase">ถึงวันที่ <span class="text-danger">*</span></label>
                                <div class="input-group-modern d-flex align-items-center bg-white">
                                    <span class="ps-3 text-danger"><i class="bi bi-calendar-check"></i></span>
                                    <input type="text" name="end_date" id="end_date" class="form-control px-2 fw-medium" required placeholder="คลิกเลือก" readonly style="background-color: transparent;">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">เหตุผลการลา <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control form-control-modern" rows="3" placeholder="ระบุเหตุผลที่ชัดเจน เช่น พักผ่อนประจำปี, ป่วยเป็นไข้..." required></textarea>
                        </div>

                        <!-- แจ้งเตือนอัปโหลดใบรับรองแพทย์ -->
                        <div class="mb-4" id="med_cert_section" style="display: none;">
                            <div class="alert alert-modern alert-danger px-4 py-3 mb-0 border-0 bg-danger bg-opacity-10 text-danger rounded-4 shadow-sm">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i> 
                                    <strong>ลาป่วยตั้งแต่ 3 วันทำการขึ้นไป</strong>
                                </div>
                                <label class="form-label small text-dark fw-bold mb-2">โปรดแนบไฟล์ใบรับรองแพทย์ (JPG, PNG, PDF)</label>
                                <input class="form-control form-control-sm border-danger border-opacity-25 shadow-sm rounded-3 bg-white" type="file" name="med_cert_file" id="med_cert_file" accept=".jpg,.jpeg,.png,.pdf">
                            </div>
                        </div>

                        <button type="submit" id="btnSubmitLeave" class="btn btn-gradient-<?= $page_theme ?> w-100 fw-bold rounded-pill py-3 mt-2 shadow-sm d-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-send-fill"></i> <span id="btnSubmitText">ยืนยันการส่งใบลา</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 🌟 ส่วนที่ 2: ประวัติการลา และ การจัดการคำขอ -->
        <!-- ========================================== -->
        <div class="col-lg-8">
            <div class="card card-modern h-100">
                <div class="card-header bg-white py-3 border-bottom px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center mt-1">
                        <div class="icon-box-sm bg-secondary bg-opacity-10 text-secondary me-3">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h5 class="mb-0 fw-bold text-dark">ประวัติการลาของฉัน</h5>
                    </div>
                    
                    <!-- ส่วนกรองเดือน และช่องค้นหา -->
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <form action="index.php" method="GET" class="m-0">
                            <input type="hidden" name="c" value="leave">
                            <input type="hidden" name="a" value="index">
                            <?php if(!empty($selected_leave_type_req)): ?>
                                <input type="hidden" name="type" value="<?= htmlspecialchars($selected_leave_type_req) ?>">
                            <?php endif; ?>
                            
                            <div class="input-group input-group-sm shadow-sm rounded-pill overflow-hidden">
                                <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-calendar-range"></i></span>
                                <select name="month" class="form-select border-0 bg-white fw-bold text-primary shadow-none pe-4" style="font-size: 13px; cursor: pointer; min-width: 140px;" onchange="this.form.submit()">
                                    <?php 
                                        $current_y = (int)date('Y');
                                        $sel_y = (int)substr($selected_month, 0, 4);
                                        $start_y = min($current_y - 1, $sel_y - 1);
                                        $end_y = max($current_y + 1, $sel_y + 1);
                                        $thai_m_list = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
                                        
                                        for ($y = $end_y; $y >= $start_y; $y--) {
                                            for ($m = 12; $m >= 1; $m--) {
                                                $val = sprintf("%04d-%02d", $y, $m);
                                                $label = $thai_m_list[$m] . ' ' . ($y + 543);
                                                $selected = ($val === $selected_month) ? 'selected' : '';
                                                echo "<option value=\"{$val}\" {$selected}>{$label}</option>";
                                            }
                                        }
                                    ?>
                                </select>
                            </div>
                        </form>

                        <div class="input-group input-group-sm shadow-sm rounded-pill overflow-hidden" style="max-width: 160px;">
                            <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="leaveHistorySearch" class="form-control border-0" placeholder="ค้นหาประวัติ..." style="font-size: 13px;">
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive h-100 custom-scrollbar">
                        <table class="table table-hover align-middle mb-0 border-0" id="leaveHistoryTable">
                            <thead class="table-light text-secondary sticky-top" style="z-index: 5;">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase" style="font-size: 12px; font-weight: 700;">ประเภทการลา</th>
                                    <th class="py-3 text-uppercase" style="font-size: 12px; font-weight: 700;">ช่วงวันที่</th>
                                    <th class="text-center py-3 text-uppercase" style="font-size: 12px; font-weight: 700;">จำนวนวัน</th>
                                    <th class="py-3 text-uppercase" style="font-size: 12px; font-weight: 700;">เหตุผล</th>
                                    <th class="text-center pe-4 py-3 text-uppercase" style="width: 150px; font-size: 12px; font-weight: 700;">สถานะ/ดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0" id="leaveHistoryBody">
                                <?php 
                                // 🌟 กรองประวัติ: แสดงเดือนที่เลือก + (ใบลาที่ PENDING หรือ CANCEL_REQUESTED)
                                $my_history = array_filter($my_leaves ?? [], function($l) use ($user_id, $selected_month) {
                                    $leave_month = substr($l['start_date'], 0, 7);
                                    // หากถูกยกเลิกแล้วและไม่ได้อยู่ในเดือนที่เลือก ให้ซ่อนไว้
                                    return $l['user_id'] == $user_id && ($leave_month == $selected_month || in_array($l['status'], ['PENDING', 'CANCEL_REQUESTED']));
                                });
                                ?>
                                <?php if (empty($my_history)): ?>
                                    <tr id="emptyHistoryRow">
                                        <td colspan="5" class="text-center py-5">
                                            <div class="d-inline-flex justify-content-center align-items-center rounded-circle bg-light mb-3" style="width: 80px; height: 80px;"><i class="bi bi-folder-x text-muted opacity-50" style="font-size: 2.5rem;"></i></div>
                                            <h6 class="fw-bold text-secondary">ไม่มีประวัติการลาในเดือนนี้</h6><p class="text-muted small mb-0">ลองเลือกเดือนอื่นจากตัวกรองด้านขวาบน เพื่อดูประวัติย้อนหลัง</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($my_history as $leave): ?>
                                    <tr class="leave-row">
                                        <td class="ps-4 py-3 leave-type-cell">
                                            <div class="d-flex flex-wrap align-items-center mb-1">
                                                <span class="fw-bold text-dark d-block" style="font-size: 14.5px;"><?= htmlspecialchars($leave['leave_type']) ?></span>
                                                <?php if(substr($leave['start_date'], 0, 7) != $selected_month && in_array($leave['status'], ['PENDING', 'CANCEL_REQUESTED'])): ?>
                                                    <span class="badge bg-warning text-dark border border-warning ms-2 rounded-pill shadow-sm" style="font-size: 10px; padding: 2px 6px;">ข้ามเดือน</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if(!empty($leave['med_cert_path'])): ?>
                                                <a href="<?= htmlspecialchars($leave['med_cert_path']) ?>" target="_blank" class="badge bg-info bg-opacity-10 text-info text-decoration-none mt-1 border border-info border-opacity-25" style="font-size: 10px;"><i class="bi bi-paperclip"></i> ดูใบรับรอง</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3">
                                            <span class="small bg-light px-2 py-1 rounded-2 border text-nowrap fw-medium text-secondary" style="font-size: 12px;">
                                                <?php 
                                                    $start_dt = getShortThaiDateLeave($leave['start_date']); $end_dt = getShortThaiDateLeave($leave['end_date']);
                                                    echo ($start_dt === $end_dt) ? $start_dt : "{$start_dt} - {$end_dt}";
                                                ?>
                                            </span>
                                        </td>
                                        <td class="text-center py-3"><span class="fw-bold text-primary" style="font-size: 1.1rem;"><?= floatval($leave['num_days']) ?></span></td>
                                        <td class="py-3 leave-reason-cell"><div class="text-truncate text-muted small" style="max-width: 180px;" title="<?= htmlspecialchars($leave['reason']) ?>"><?= htmlspecialchars($leave['reason']) ?></div></td>
                                        <td class="text-center pe-4 py-3 leave-status-cell">
                                            <!-- 🌟 ป้ายสถานะ -->
                                            <?php 
                                                if ($leave['status'] == 'APPROVED') { echo '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-block mb-2 py-1 px-2 rounded-pill w-100"><i class="bi bi-check-circle me-1"></i> อนุมัติแล้ว</span>'; } 
                                                elseif ($leave['status'] == 'REJECTED') { echo '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 d-block mb-2 py-1 px-2 rounded-pill w-100"><i class="bi bi-x-circle me-1"></i> ไม่อนุมัติ</span>'; } 
                                                elseif ($leave['status'] == 'CANCELLED') { echo '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 d-block mb-2 py-1 px-2 rounded-pill w-100"><i class="bi bi-slash-circle me-1"></i> ยกเลิกแล้ว</span>'; } 
                                                elseif ($leave['status'] == 'PENDING') { echo '<span class="badge bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50 d-block mb-2 py-1 px-2 rounded-pill w-100"><i class="bi bi-hourglass-split me-1"></i> รอพิจารณา</span>'; } 
                                                elseif ($leave['status'] == 'CANCEL_REQUESTED') { echo '<span class="badge bg-warning text-dark border border-warning d-block mb-2 py-1 px-2 rounded-pill w-100 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i> รออนุมัติยกเลิก</span>'; }
                                                else { echo '<span class="badge bg-light text-dark border d-block mb-2 py-1 px-2 rounded-pill w-100">สถานะไม่ทราบ</span>'; }
                                            ?>
                                            
                                            <!-- ปุ่มเครื่องมือ -->
                                            <div class="d-flex gap-2 justify-content-center">
                                                <a href="index.php?c=leave&a=print&id=<?= $leave['id'] ?>" target="_blank" class="btn btn-soft-primary btn-sm flex-fill rounded-3 fw-bold" style="font-size: 11px;" title="พิมพ์ฟอร์มใบลา"><i class="bi bi-printer"></i> พิมพ์</a>
                                                
                                                <?php if (in_array($leave['status'], ['PENDING', 'APPROVED'])): ?>
                                                    <form action="index.php?c=leave&a=cancel" method="POST" class="flex-fill m-0" onsubmit="return confirm('<?= ($leave['status']=='APPROVED') ? 'ใบลาฉบับนี้ถูกอนุมัติไปแล้ว\n\nการยกเลิกจะต้องรอให้หัวหน้าอนุมัติการยกเลิกก่อน ระบบจึงจะคืนโควตาวันลาให้ ยืนยันการส่งคำขอยกเลิกใช่หรือไม่?' : 'คุณแน่ใจหรือไม่ที่จะยกเลิกคำขอใบลาฉบับนี้?' ?>');">
                                                        <input type="hidden" name="request_id" value="<?= $leave['id'] ?>">
                                                        <button type="submit" class="btn btn-soft-danger btn-sm w-100 rounded-3 fw-bold" style="font-size: 11px;" title="ยกเลิกคำขอนี้"><i class="bi bi-x-circle"></i> ยกเลิก</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 🌟 ส่วนที่ 3: กระเป๋าวันลาคงเหลือ -->
    <!-- ========================================== -->
    <hr class="text-muted opacity-25 mt-2 mb-4">
    <div class="d-flex align-items-center mb-3 px-2">
        <div class="icon-box-sm bg-info bg-opacity-10 text-info me-3"><i class="bi bi-wallet2"></i></div><h4 class="mb-0 fw-bold text-dark">กระเป๋าสิทธิ์วันลาคงเหลือของคุณ</h4>
    </div>

    <div class="row g-3 mb-4">
        <?php if (!empty($leave_balances)): ?>
            <?php 
                $has_shown_card = false;
                foreach ($leave_balances as $balance): 
                    if (!empty($selected_leave_type_req) && $balance['leave_type_name'] != $selected_leave_type_req) continue;
                    $has_shown_card = true;

                    $color_theme = 'primary'; $icon = 'bi-calendar2-check';
                    if ($balance['leave_type_name'] == 'ลาป่วย') { $color_theme = 'danger'; $icon = 'bi-bandaid'; }
                    if ($balance['leave_type_name'] == 'ลากิจส่วนตัว') { $color_theme = 'warning'; $icon = 'bi-briefcase'; }
                    if ($balance['leave_type_name'] == 'ลาพักผ่อน') { $color_theme = 'success'; $icon = 'bi-brightness-high'; }

                    $total_allowable = floatval($balance['total_allowable']); $used_days = floatval($balance['used_days']); $remaining = floatval($balance['remaining']);
                    $percent_used = $total_allowable > 0 ? ($used_days / $total_allowable) * 100 : 0;
            ?>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-stat border-<?= $color_theme ?> h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-muted fw-bold mb-0 text-uppercase" style="font-size: 13px; letter-spacing: 0.5px;"><?= htmlspecialchars($balance['leave_type_name']) ?></h6>
                                <div class="icon-box bg-<?= $color_theme ?> bg-opacity-10 text-<?= $color_theme ?> shadow-sm"><i class="bi <?= $icon ?>"></i></div>
                            </div>
                            <div class="mb-3">
                                <span class="fw-bold text-dark" style="font-size: 2.2rem; line-height: 1;"><?= $remaining ?></span><span class="text-muted ms-1 fw-medium">/ <?= $total_allowable ?> วัน</span>
                            </div>
                            <div class="progress progress-thin mb-2"><div class="progress-bar bg-<?= $color_theme ?>" role="progressbar" style="width: <?= $percent_used ?>%" aria-valuenow="<?= $percent_used ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                            <div class="d-flex justify-content-between text-muted" style="font-size: 12px;">
                                <span>ใช้ไป: <span class="fw-bold text-dark"><?= $used_days ?></span></span>
                                <?php if($balance['carried_over_days'] > 0): ?>
                                    <span class="text-info fw-bold"><i class="bi bi-arrow-up-right-circle"></i> ยอดยกมา <?= floatval($balance['carried_over_days']) ?></span>
                                <?php else: ?><span><?= round($percent_used) ?>%</span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$has_shown_card && !empty($selected_leave_type_req)): ?>
                <div class="col-12"><div class="alert alert-modern alert-warning px-4 py-3 d-flex align-items-center shadow-sm"><i class="bi bi-exclamation-circle-fill fs-4 text-warning me-3"></i> <div><h6 class="fw-bold mb-1 text-dark">ไม่พบข้อมูลโควตา "<?= htmlspecialchars($selected_leave_type_req) ?>"</h6><p class="mb-0 small text-muted">คุณอาจไม่มีสิทธิ์ในประเภทการลานี้ หรือระบบยังไม่ได้กำหนดโควตาให้ กรุณาติดต่อผู้ดูแลระบบ</p></div></div></div>
            <?php endif; ?>
        <?php else: ?>
            <div class="col-12"><div class="alert alert-modern alert-info px-4 py-3 d-flex align-items-center shadow-sm"><i class="bi bi-info-circle-fill fs-4 text-info me-3"></i> <div><h6 class="fw-bold mb-1">ยังไม่มีข้อมูลบัญชีวันลา</h6><p class="mb-0 small text-muted">ระบบกำลังประมวลผลกระเป๋าวันลาของคุณ กรุณาติดต่อผู้ดูแลระบบหากไม่พบข้อมูลเกิน 24 ชั่วโมง</p></div></div></div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchLeaveInput = document.getElementById('leaveHistorySearch'); const leaveTableBody = document.getElementById('leaveHistoryBody');
    if (searchLeaveInput && leaveTableBody) {
        searchLeaveInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase().trim(); const rows = leaveTableBody.querySelectorAll('.leave-row');
            rows.forEach(row => {
                const typeText = row.querySelector('.leave-type-cell')?.textContent.toLowerCase() || '';
                const reasonText = row.querySelector('.leave-reason-cell')?.textContent.toLowerCase() || '';
                const statusText = row.querySelector('.leave-status-cell')?.textContent.toLowerCase() || '';
                if (typeText.includes(filter) || reasonText.includes(filter) || statusText.includes(filter)) { row.style.display = ''; } else { row.style.display = 'none'; }
            });
        });
    }

    const leaveTypeSelect = document.getElementById('leave_type'); const medCertSection = document.getElementById('med_cert_section');
    const medCertInput = document.getElementById('med_cert_file'); const leaveForm = document.getElementById('leaveForm');
    const employeeType = "<?= $employee_type ?? '' ?>"; const holidaysList = <?= isset($json_holidays) ? $json_holidays : '[]' ?>;

    function calculateWorkingDays(startDateStr, endDateStr) {
        if (!startDateStr || !endDateStr) return 0;
        const start = new Date(startDateStr); const end = new Date(endDateStr); let count = 0; let curDate = new Date(start.getTime());
        while (curDate <= end) {
            const dayOfWeek = curDate.getDay(); const dateString = curDate.getFullYear() + "-" + String(curDate.getMonth() + 1).padStart(2, '0') + "-" + String(curDate.getDate()).padStart(2, '0');
            if (dayOfWeek !== 0 && dayOfWeek !== 6 && !holidaysList.includes(dateString)) { count++; }
            curDate.setDate(curDate.getDate() + 1);
        }
        return count;
    }

    Array.from(leaveTypeSelect.options).forEach(opt => {
        const name = opt.getAttribute('data-name') || ''; if (!name) return;
        if (employeeType.includes('ทั่วไป')) { if (name === 'ลากิจส่วนตัว' || name === 'ลาพักผ่อน' || name.includes('อุปสมบท') || name.includes('ภริยาคลอด') || name.includes('ศึกษา') || name.includes('ระหว่างประเทศ') || name.includes('ติดตามคู่สมรส') || name.includes('ฟื้นฟู')) { opt.disabled = true; opt.text += ' (ไม่มีสิทธิ)'; } } 
        else if (employeeType.includes('ภารกิจ')) { if (name.includes('ภริยาคลอด') || name.includes('ศึกษา') || name.includes('ระหว่างประเทศ') || name.includes('ติดตามคู่สมรส') || name.includes('ฟื้นฟู')) { opt.disabled = true; opt.text += ' (ไม่มีสิทธิ)'; } }
    });

    function checkMedicalCertRequired() {
        const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex]; const leaveName = selectedOption ? selectedOption.getAttribute('data-name') : '';
        const start = document.querySelector('input[name="start_date"]').value; const end = document.querySelector('input[name="end_date"]').value;
        if (leaveName === 'ลาป่วย' && start && end) {
            if (calculateWorkingDays(start, end) >= 3) { medCertSection.style.display = 'block'; medCertInput.required = true; } else { medCertSection.style.display = 'none'; medCertInput.required = false; }
        } else { medCertSection.style.display = 'none'; medCertInput.required = false; }
    }

    function changeYearToBuddhist(selectedDates, dateStr, instance) {
        const fp = instance || this; if (!fp || !fp.currentYearElement) return;
        let beYear = fp.currentYear + 543; fp.currentYearElement.value = beYear;
        if (fp.calendarContainer) {
            let yearInputs = fp.calendarContainer.querySelectorAll('.cur-year');
            yearInputs.forEach(function(input) {
                input.value = beYear;
                if (!input.dataset.hooked) {
                    input.dataset.hooked = "true";
                    input.addEventListener('input', function(e) { if (this.value.length === 4) { fp.changeYear(parseInt(this.value) - 543); } });
                }
            });
        }
    }

    const ThaiLocale = { weekdays: { shorthand: ["อา", "จ", "อ", "พ", "พฤ", "ศ", "ส"], longhand: ["อาทิตย์", "จันทร์", "อังคาร", "พุธ", "พฤหัสบดี", "ศุกร์", "เสาร์"], }, months: { shorthand: ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."], longhand: ["มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"], }, firstDayOfWeek: 1, rangeSeparator: " ถึง ", scrollTitle: "เลื่อนเพื่อเพิ่มลด", toggleTitle: "คลิกเพื่อเปลี่ยน", yearAriaLabel: "ปี", monthAriaLabel: "เดือน", };
    const flatpickrConfig = {
        altInput: true, altFormat: "j F Y", dateFormat: "Y-m-d", locale: ThaiLocale, disableMobile: "true",
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            if (dayElem.dateObj.getDay() === 0 || dayElem.dateObj.getDay() === 6) { dayElem.style.color = '#dc2626'; dayElem.style.fontWeight = 'bold'; }
            let dateString = dayElem.dateObj.getFullYear() + "-" + String(dayElem.dateObj.getMonth() + 1).padStart(2, '0') + "-" + String(dayElem.dateObj.getDate()).padStart(2, '0');
            if (holidaysList.includes(dateString)) { dayElem.style.backgroundColor = '#fef2f2'; dayElem.style.color = '#dc2626'; dayElem.style.fontWeight = 'bold'; dayElem.style.border = '1px solid #dc2626'; dayElem.style.borderRadius = '50%'; dayElem.title = "วันหยุดนักขัตฤกษ์"; }
        },
        formatDate: function(date, format, locale) {
            if (format === "j F Y" || format === "j M Y" || format === "d/m/Y") {
                const thaiMonthsFull = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
                const thaiMonthsShort = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                const mArray = format.includes('F') ? thaiMonthsFull : thaiMonthsShort; return `${date.getDate()} ${mArray[date.getMonth()]} ${date.getFullYear() + 543}`;
            }
            return flatpickr.formatDate(date, format, locale);
        },
        onReady: changeYearToBuddhist, onOpen: changeYearToBuddhist, onValueUpdate: changeYearToBuddhist, onYearChange: changeYearToBuddhist, onMonthChange: changeYearToBuddhist, onDraw: changeYearToBuddhist,
        onChange: function(selectedDates, dateStr, instance) { changeYearToBuddhist(selectedDates, dateStr, instance); setTimeout(checkMedicalCertRequired, 50); if (instance.element.id === 'start_date') { endPicker.set('minDate', dateStr); const currentEnd = document.querySelector('input[name="end_date"]').value; if (!currentEnd || new Date(currentEnd) < new Date(dateStr)) { endPicker.setDate(dateStr); } } }
    };

    const startPicker = flatpickr("#start_date", flatpickrConfig); const endPicker = flatpickr("#end_date", flatpickrConfig);
    leaveTypeSelect.addEventListener('change', checkMedicalCertRequired); if (leaveTypeSelect.value !== '') checkMedicalCertRequired();
    if (leaveForm) { leaveForm.addEventListener('submit', function(e) { const btnSubmit = document.getElementById('btnSubmitLeave'); const btnText = document.getElementById('btnSubmitText'); if (btnSubmit.classList.contains('disabled')) { e.preventDefault(); return; } btnSubmit.classList.add('disabled'); btnSubmit.style.opacity = '0.7'; btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังส่งข้อมูล...'; }); }
});
</script>