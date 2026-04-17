<?php
// ที่อยู่ไฟล์: views/roster/index.php

// 🌟 ส่วนที่ 1: จัดการตัวแปรพื้นฐานและฟังก์ชันคำนวณ
$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$selected_month = $selected_month ?? date('Y-m');
$exp = explode('-', $selected_month);
$year = $exp[0];
$month = $exp[1];
$display_month_text = $thai_months[(int)$month] . ' ' . ($year + 543);
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$isAdmin = in_array($_SESSION['user']['role'] ?? '', ['ADMIN', 'SUPERADMIN']);
$is_manager = in_array($_SESSION['user']['role'] ?? '', ['DIRECTOR', 'SCHEDULER', 'ADMIN', 'SUPERADMIN']);
$roster_status = $roster_status ?? 'DRAFT';
$canEdit = ($is_manager && $roster_status !== 'APPROVED');

function getShiftColorClass($shift_val) {
    if ($shift_val == 'บ' || $shift_val == 'A') return 'text-warning text-dark';
    if ($shift_val == 'ร' || $shift_val == 'N') return 'text-success';
    if ($shift_val == 'ย' || $shift_val == 'O') return 'text-danger';
    if ($shift_val == 'บ/ร' || $shift_val == 'ย/บ') return 'text-primary';
    if ($shift_val == 'M') return 'text-info';
    return 'text-dark';
}

function getBsColor($color_theme) {
    if ($color_theme == 'green') return 'success';
    if ($color_theme == 'purple') return 'danger';
    if ($color_theme == 'gray') return 'secondary';
    if ($color_theme == 'blue') return 'info';
    return 'primary';
}

// 🌟 อัปเดตฟังก์ชัน: ให้ดึงเรทค่าตอบแทนจาก pay_rate_id 
function calculatePayRatesPHP($staff, $pay_rates_db) {
    if (!empty($staff['pay_rate_id']) && !empty($pay_rates_db)) {
        foreach ($pay_rates_db as $group) {
            if ($group['id'] == $staff['pay_rate_id']) {
                return ['ร' => $group['rate_r'], 'ย' => $group['rate_y'], 'บ' => $group['rate_b']];
            }
        }
    }
    return ['ร' => 0, 'ย' => 0, 'บ' => 0];
}

$hospital_names = [];
$filtered_hospitals = [];
if (isset($hospitals_list)) {
    foreach ($hospitals_list as $h) {
        // 🌟 แสดงทุกหน่วยงาน ยกเว้น "ส่วนกลาง"
        if (mb_strpos($h['name'], 'ส่วนกลาง') === false && $h['id'] != 0) {
            $filtered_hospitals[] = $h;
            $hospital_names[$h['id']] = $h['name'];
        }
    }
    $hospitals_list = $filtered_hospitals; 
}

$all_staff_for_sidebar = $all_staff_for_sidebar ?? $staff_list ?? [];
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .input-group-modern { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.2s; overflow: hidden; }
    .input-group-modern:focus-within { background-color: #ffffff; border-color: #3b82f6; box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15); }
    .input-group-modern .form-control, .input-group-modern .input-group-text { background-color: transparent; border: none; box-shadow: none; }
    
    .table-roster th { font-weight: 600; color: #475569; font-size: 13px; vertical-align: middle; }
    .table-roster td { vertical-align: middle; }
    .date-header-cell { transition: all 0.2s ease; }
    .date-header-cell:hover { background-color: #e0f2fe !important; color: #0284c7 !important; transform: translateY(-2px); z-index: 10; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-radius: 6px; }
    
    .shift-cell { font-size: 15px !important; font-weight: 800 !important; border-radius: 6px !important; transition: all 0.15s ease; background-color: transparent !important; width: 100%; height: 100%; }
    .shift-cell:hover { background-color: #f0f9ff !important; transform: scale(1.15); z-index: 5; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    
    .leave-badge-cell { font-size: 10px; padding: 2px 5px; border-radius: 4px; line-height: 1.2; margin-bottom: 2px; display: inline-block; max-width: 95%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    
    .draggable-staff { cursor: grab; transition: all 0.2s; border: 1px solid transparent; }
    .draggable-staff:hover { border-color: #bfdbfe !important; background-color: #f0f9ff !important; transform: translateX(-3px); }
    .draggable-staff:active { cursor: grabbing; transform: scale(0.98); }
    
    .drag-handle { cursor: grab; }
    .drag-handle:active { cursor: grabbing !important; color: #0d6efd !important; }
    .sortable-ghost { background-color: #eff6ff !important; opacity: 0.9; }
    .sortable-ghost td { background-color: #eff6ff !important; border-top: 1px dashed #3b82f6; border-bottom: 1px dashed #3b82f6; }
    
    .fatigue-warn { border: 2px solid #ef4444 !important; background-color: #fef2f2 !important; position: relative; animation: blinkWarning 1s infinite alternate; z-index: 10; }
    @keyframes blinkWarning { from { box-shadow: 0 0 0px #ef4444; } to { box-shadow: 0 0 8px #ef4444; } }

    .pay-cell-clickable { transition: all 0.2s; cursor: pointer; }
    .pay-cell-clickable:hover { background-color: #dcfce7 !important; transform: scale(1.05); border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

    .today-column { background-color: #f0fdf4 !important; border-left: 1px solid #bbf7d0 !important; border-right: 1px solid #bbf7d0 !important; }

    @media (min-width: 992px) { .sticky-sidebar { position: sticky; top: 15px; align-self: flex-start; height: calc(100vh - 110px); overflow: hidden; } }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
</style>

<div class="w-100 bg-light p-3 p-md-4 min-vh-100 d-flex flex-column">
    <div class="container-fluid max-w-7xl mx-auto flex-grow-1 d-flex flex-column">
        
        <!-- 🌟 Header & Controls (จัดรูปแบบใหม่ให้สมส่วน ไม่ซ้อนทับกัน) -->
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center mb-4 gap-3 bg-white p-3 p-md-4 rounded-4 shadow-sm border-0">
            <div style="min-width: 0;" class="flex-shrink-0">
                <h4 class="fw-bold text-dark mb-1 text-truncate">
                    <i class="bi bi-calendar3 text-primary me-2"></i> ตารางปฏิบัติงาน (Roster)
                </h4>
                <p class="text-muted mb-0 text-truncate" style="font-size: 14px;">หน่วยบริการ: <span class="fw-bold text-primary"><?= htmlspecialchars($hospital_name ?? '') ?></span></p>
            </div>
            
            <div class="d-flex flex-wrap align-items-center justify-content-xl-end gap-2 flex-grow-1">
                
                <!-- 🌟 ปุ่มขอแลกเวร -->
                <a href="index.php?c=swap" class="btn btn-warning rounded-pill shadow-sm fw-bold px-3 text-dark hover-shadow d-flex align-items-center" title="ระบบขอแลกเวร/เปลี่ยนเวร" style="height: 40px;">
                    <i class="bi bi-arrow-left-right me-1"></i> <span class="d-none d-sm-inline">ขอแลกเวร</span>
                </a>

                <form method="GET" action="index.php" id="filterFormRoster" class="d-flex flex-wrap gap-2 mb-0 align-items-center">
                    <input type="hidden" name="c" value="roster">
                    <input type="hidden" name="a" value="index">
                    
                    <?php if ($isAdmin): ?>
                    <div class="dropdown shadow-sm" style="width: 220px;">
                        <button class="btn d-flex justify-content-between align-items-center bg-white border border-secondary border-opacity-25 w-100 rounded-pill px-3" type="button" id="hospDropdown" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="true" style="height: 40px;">
                            <div class="d-flex align-items-center gap-2 text-truncate" style="min-width: 0;">
                                <i class="bi bi-hospital text-danger flex-shrink-0"></i>
                                <span class="fw-bold text-dark text-truncate" style="font-size: 13.5px;"><?= htmlspecialchars($hospital_name ?? '') ?></span>
                            </div>
                            <i class="bi bi-chevron-down text-muted ms-2 flex-shrink-0" style="font-size: 12px;"></i>
                        </button>
                        <div class="dropdown-menu shadow w-100 p-0 border-0 rounded-3 overflow-hidden" aria-labelledby="hospDropdown">
                            <div class="p-2 bg-light border-bottom sticky-top" style="z-index: 10;">
                                <div class="input-group input-group-sm input-group-modern">
                                    <span class="input-group-text"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" class="form-control" id="hospSearchInput" placeholder="ค้นหา รพ.สต. ...">
                                </div>
                            </div>
                            <ul class="list-unstyled mb-0 overflow-auto custom-scrollbar" style="max-height: 280px;" id="hospOptionList">
                                <?php foreach ($hospitals_list as $h): ?>
                                    <li>
                                        <a class="dropdown-item hosp-option py-2 text-wrap lh-sm <?= $h['id'] == ($hospital_id??0) ? 'active bg-primary text-white fw-bold' : 'text-dark' ?>" href="#" data-val="<?= $h['id'] ?>" style="font-size: 13.5px;">
                                            <?= htmlspecialchars($h['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <input type="hidden" name="hospital_id" id="selectedHospInput" value="<?= htmlspecialchars($hospital_id??'') ?>">
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="hospital_id" value="<?= $hospital_id ?? '' ?>">
                    <?php endif; ?>

                    <?php
                    $current_y = (int)date('Y');
                    $sel_y = (int)substr($selected_month, 0, 4);
                    $start_y = min($current_y - 1, $sel_y - 1);
                    $end_y = max($current_y + 2, $sel_y + 2);
                    $months_options = [];
                    for ($y = $start_y; $y <= $end_y; $y++) {
                        for ($m = 1; $m <= 12; $m++) {
                            $val = sprintf("%04d-%02d", $y, $m);
                            $label = $thai_months[$m] . " " . ($y + 543);
                            $months_options[$val] = $label;
                        }
                    }
                    ?>
                    <div class="dropdown shadow-sm" style="width: 170px;">
                        <button class="btn d-flex justify-content-between align-items-center bg-white border border-secondary border-opacity-25 w-100 rounded-pill px-3" type="button" id="monthDropdown" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="true" style="height: 40px;">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-calendar-month text-primary"></i>
                                <span class="fw-bold text-dark" style="font-size: 13.5px;"><?= $display_month_text ?></span>
                            </div>
                            <i class="bi bi-chevron-down text-muted ms-1" style="font-size: 12px;"></i>
                        </button>
                        <div class="dropdown-menu shadow w-100 p-0 border-0 rounded-3 overflow-hidden" aria-labelledby="monthDropdown">
                            <div class="p-2 bg-light border-bottom sticky-top" style="z-index: 10;">
                                <div class="input-group input-group-sm input-group-modern">
                                    <span class="input-group-text"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" class="form-control" id="monthSearchInput" placeholder="ค้นหาเดือน, ปี...">
                                </div>
                            </div>
                            <ul class="list-unstyled mb-0 overflow-auto custom-scrollbar" style="max-height: 280px;" id="monthOptionList">
                                <?php foreach ($months_options as $val => $label): ?>
                                    <li>
                                        <a class="dropdown-item month-option py-2 <?= $val == $selected_month ? 'active bg-primary text-white fw-bold' : 'text-dark' ?>" href="#" data-val="<?= $val ?>" style="font-size: 13.5px;">
                                            <?= $label ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <input type="hidden" name="month" id="selectedMonthInput" value="<?= htmlspecialchars($selected_month) ?>">
                    </div>
                </form>

                <div class="vr mx-1 d-none d-md-block opacity-25"></div>

                <!-- 🌟 กลุ่มปุ่มส่งออก -->
                <div class="btn-group shadow-sm rounded-pill overflow-hidden" style="height: 40px;">
                    <a href="index.php?c=roster&a=export_word&month=<?= $selected_month ?>&hospital_id=<?= urlencode($hospital_id??'') ?>" 
                       class="btn btn-primary fw-bold d-flex align-items-center gap-2 px-3 border-0">
                        <i class="bi bi-printer-fill fs-6"></i> <span class="d-none d-sm-inline">พิมพ์</span>
                    </a>
                    <div class="vr bg-white opacity-25"></div>
                    <button onclick="exportTableToExcelClean('rosterTable', 'ตารางเวร_<?= $selected_month ?>')" class="btn btn-success fw-bold d-flex align-items-center gap-2 px-3 border-0">
                        <i class="bi bi-file-earmark-excel-fill fs-6"></i> <span class="d-none d-sm-inline">Excel</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 🌟 แจ้งเตือน -->
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <strong>สำเร็จ!</strong> <?= $_SESSION['success_msg'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>ข้อผิดพลาด!</strong> <?= $_SESSION['error_msg'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <!-- 🌟 แถบสถานะตารางเวร และ ปุ่มดำเนินการ Workflow -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 <?= $roster_status == 'APPROVED' ? 'bg-success bg-opacity-10 border-success' : ($roster_status == 'SUBMITTED' ? 'bg-info bg-opacity-10' : 'bg-warning bg-opacity-10') ?>" style="border-left: 4px solid !important;">
            <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div>
                        <?php if ($roster_status == 'APPROVED'): ?>
                            <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i> <strong class="text-success">สถานะ: อนุมัติแล้ว</strong> <span class="text-dark opacity-75">ตารางเวรเดือนนี้ได้รับการยืนยันความถูกต้องแล้ว</span>
                        <?php elseif ($roster_status == 'SUBMITTED'): ?>
                            <i class="bi bi-send-fill fs-5 me-2 text-primary"></i> <strong class="text-primary">สถานะ: รอพิจารณา</strong> <span class="text-dark opacity-75">ส่งถึงผู้อำนวยการแล้ว เพื่อรอการตรวจสอบ</span>
                        <?php elseif ($roster_status == 'REQUEST_EDIT'): ?>
                            <i class="bi bi-unlock-fill fs-5 me-2 text-danger"></i> <strong class="text-danger">สถานะ: ขอปลดล็อค (แก้ไข)</strong> <span class="text-dark opacity-75">ส่งคำขอไปยังส่วนกลางแล้ว</span>
                        <?php else: ?>
                            <i class="bi bi-pencil-square fs-5 me-2 text-warning text-dark"></i> <strong class="text-dark">สถานะ: กำลังจัดทำ (Draft)</strong> <span class="text-dark opacity-75">คุณสามารถเพิ่ม/ลดเวร หรือดึงคนนอกมาช่วยได้</span>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn-sm shadow-sm text-nowrap rounded-pill px-3" style="background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); color: white; font-weight: bold;" data-bs-toggle="modal" data-bs-target="#summaryModal">
                        <i class="bi bi-bar-chart-fill me-1"></i> สรุปยอดเดือนนี้
                    </button>
                </div>
                
                <div class="d-flex flex-wrap gap-2">
                    <!-- ควบคุม Workflow สำหรับ ADMIN -->
                    <?php if (($roster_status == 'APPROVED' || $roster_status == 'REQUEST_EDIT') && $isAdmin): ?>
                        <form action="index.php?c=ajax&a=change_status" method="POST" class="m-0 d-flex gap-2">
                            <input type="hidden" name="month_year" value="<?= $selected_month ?>">
                            <input type="hidden" name="hospital_id" value="<?= $hospital_id??'' ?>">
                            
                            <?php if ($roster_status == 'REQUEST_EDIT'): ?>
                                <button type="submit" name="status" value="APPROVED" class="btn btn-sm btn-outline-secondary fw-bold bg-white text-nowrap rounded-3" onclick="return confirm('ปฏิเสธคำขอ?');"><i class="bi bi-x-circle me-1"></i> ปฏิเสธคำขอ</button>
                                <button type="submit" name="status" value="DRAFT" class="btn btn-sm btn-warning fw-bold text-dark shadow-sm text-nowrap rounded-3" onclick="return confirm('ปลดล็อคเป็น DRAFT?');"><i class="bi bi-unlock-fill me-1"></i> อนุมัติให้แก้ไข</button>
                            <?php else: ?>
                                <input type="hidden" name="status" value="DRAFT">
                                <button type="submit" class="btn btn-sm btn-outline-danger fw-bold bg-white text-nowrap rounded-3" onclick="return confirm('ยืนยันตีกลับตารางเวรให้แก้ไข?');"><i class="bi bi-unlock-fill me-1"></i> ตีกลับให้แก้ไข</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>

                    <!-- ผอ. (DIRECTOR) ตรวจสอบและอนุมัติ -->
                    <?php if ($roster_status == 'SUBMITTED' && $_SESSION['user']['role'] == 'DIRECTOR'): ?>
                        <form action="index.php?c=ajax&a=change_status" method="POST" class="m-0 d-flex gap-2">
                            <input type="hidden" name="month_year" value="<?= $selected_month ?>">
                            <button type="submit" name="status" value="DRAFT" class="btn btn-sm btn-outline-danger fw-bold bg-white text-nowrap rounded-3" onclick="return confirm('ยืนยันการตีกลับ?');"><i class="bi bi-arrow-return-left me-1"></i> ตีกลับ</button>
                            <button type="submit" name="status" value="APPROVED" class="btn btn-sm btn-success fw-bold shadow-sm text-nowrap rounded-3" onclick="return confirm('อนุมัติตารางเวร?');"><i class="bi bi-check-circle-fill me-1"></i> อนุมัติเวร</button>
                        </form>
                    <?php endif; ?>

                    <!-- ผู้จัดเวร / ผอ. ขอแก้ไขตารางที่อนุมัติแล้ว -->
                    <?php if ($roster_status == 'APPROVED' && ($_SESSION['user']['role'] == 'SCHEDULER' || $_SESSION['user']['role'] == 'DIRECTOR')): ?>
                        <form action="index.php?c=ajax&a=request_edit" method="POST" class="m-0" onsubmit="return confirm('ส่งคำขอปลดล็อคตารางเวร?');">
                            <input type="hidden" name="month_year" value="<?= $selected_month ?>">
                            <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm text-nowrap rounded-3"><i class="bi bi-unlock-fill me-1"></i> ขอแก้ไขตาราง</button>
                        </form>
                    <?php endif; ?>

                    <!-- กำลังจัดทำ (Draft) -->
                    <?php if ($roster_status == 'DRAFT' && ($_SESSION['user']['role'] == 'SCHEDULER' || $_SESSION['user']['role'] == 'DIRECTOR')): ?>
                        <button onclick="checkFatigueRules()" class="btn btn-sm btn-outline-danger fw-bold shadow-sm bg-white text-nowrap rounded-3" title="ตรวจสอบเวรชน / พักผ่อนไม่พอ">
                            <i class="bi bi-shield-exclamation me-1"></i> ตรวจสอบความล้า
                        </button>

                        <button onclick="copyPreviousMonth('<?= $selected_month ?>')" class="btn btn-sm btn-outline-success fw-bold shadow-sm bg-white text-nowrap rounded-3">
                            <i class="bi bi-copy me-1"></i> คัดลอกเดือนก่อน
                        </button>

                        <a href="javascript:void(0)" onclick="confirmAction('index.php?c=roster&a=randomize_roster&month=<?= $selected_month ?>', 'ระบบจะทำการล้างเวรเก่าและสุ่มจัดเวรใหม่ทั้งหมดให้ทุกคน\nคุณต้องการดำเนินการต่อหรือไม่?', this)" class="btn btn-sm btn-outline-primary fw-bold shadow-sm bg-white text-nowrap rounded-3">
                            <i class="bi bi-dice-5-fill me-1"></i> สุ่มเวร
                        </a>
                        
                        <a href="javascript:void(0)" onclick="confirmAction('index.php?c=roster&a=clear_roster&month=<?= $selected_month ?>', 'ยืนยันการล้างตารางเวรทั้งหมดของเดือนนี้?', this)" class="btn btn-sm btn-outline-secondary fw-bold shadow-sm bg-white text-nowrap rounded-3">
                            <i class="bi bi-eraser-fill me-1"></i> ล้างข้อมูล
                        </a>

                        <div class="vr mx-1"></div>

                        <form action="index.php?c=ajax&a=change_status" method="POST" class="m-0" onsubmit="return confirm('ส่งตารางเวรขอพิจารณาอนุมัติ?');">
                            <input type="hidden" name="month_year" value="<?= $selected_month ?>">
                            <input type="hidden" name="status" value="SUBMITTED">
                            <button type="submit" class="btn btn-sm btn-dark fw-bold shadow-sm px-4 text-nowrap rounded-3"><i class="bi bi-send-fill me-1"></i> ส่งอนุมัติ</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-3 flex-grow-1">
            <!-- 🌟 ตารางเวรหลัก -->
            <div class="col-xl-9 col-lg-8 d-flex flex-column" style="transition: all 0.3s ease;">
                <div class="card card-modern overflow-hidden mb-4 flex-grow-1">
                    <div class="card-body p-0 d-flex flex-column">
                        <div class="table-responsive flex-grow-1 custom-scrollbar" style="max-height: 70vh;">
                            <table class="table table-bordered table-hover table-roster mb-0 text-center" id="rosterTable" style="min-width: 1000px;">
                                <thead class="sticky-top" style="z-index: 10;">
                                    <tr>
                                        <th rowspan="2" class="align-middle shadow-sm bg-white" style="min-width: 220px; left: 0; position: sticky; z-index: 11; border-right: 2px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">รายชื่อเจ้าหน้าที่</th>
                                        <th colspan="<?= $days_in_month ?>" class="bg-light border-bottom text-dark">วันที่ปฏิบัติงาน เดือน <?= $display_month_text ?></th>
                                    </tr>
                                    <tr>
                                        <?php for ($i=1; $i<=$days_in_month; $i++): 
                                            $current_date_str = "$year-$month-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                                            $timestamp = strtotime($current_date_str);
                                            $day_of_week = date('N', $timestamp);
                                            $is_weekend = ($day_of_week == 6 || $day_of_week == 7);
                                            $is_current_day = ($current_date_str == date('Y-m-d'));
                                            
                                            // เช็ควันหยุดนักขัตฤกษ์
                                            $holidayName = isset($holidayModel) ? $holidayModel->isHoliday($current_date_str) : false;
                                            $is_holiday_flag = $holidayName ? 'true' : 'false';
                                            $h_name = $holidayName ? htmlspecialchars($holidayName, ENT_QUOTES) : '';
                                        ?>
                                            <th class="<?= $is_current_day ? 'bg-primary text-white shadow-sm' : ($is_weekend || $holidayName ? 'text-danger bg-light' : 'bg-light') ?> date-header-cell border-bottom" 
                                                style="min-width: 42px; cursor: pointer; position: relative;"
                                                onclick="openHolidayInfoModal('<?= $current_date_str ?>', <?= $is_holiday_flag ?>, '<?= $h_name ?>')"
                                                title="<?= $holidayName ? 'วันหยุด: '.$holidayName : 'คลิกเพื่อเสนอวันหยุด' ?>">
                                                <?= $i ?>
                                                <?php if ($holidayName): ?>
                                                    <div class="<?= $is_current_day ? 'text-warning' : 'text-danger' ?> mt-1" style="font-size: 8px;"><i class="bi bi-star-fill"></i></div>
                                                <?php endif; ?>
                                            </th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                
                                <tbody id="rosterTableBody">
                                    <?php 
                                    if (empty($all_staff_for_sidebar)): ?>
                                        <tr>
                                            <td colspan="<?= $days_in_month + 1 ?>" class="py-5 text-center text-muted">
                                                <i class="bi bi-people fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                                ยังไม่พบข้อมูลบุคลากรในหน่วยบริการนี้<br>
                                                <?php if ($_SESSION['user']['role'] === 'SCHEDULER' || $_SESSION['user']['role'] === 'DIRECTOR'): ?>
                                                    <a href="index.php?c=staff&a=index" class="btn btn-primary mt-3 rounded-pill px-4 fw-bold shadow-sm">
                                                        <i class="bi bi-person-plus-fill me-1"></i> ไปเพิ่มบุคลากร
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($all_staff_for_sidebar as $staff): 
                                            $is_external = (isset($staff['hospital_id']) && $staff['hospital_id'] != ($hospital_id??0));
                                            $has_shift = false;
                                            $my_shifts = [];
                                            
                                            if (isset($shifts) && is_array($shifts)) {
                                                foreach ($shifts as $s) {
                                                    if ($s['user_id'] == $staff['id']) {
                                                        $has_shift = true;
                                                        $day = (int)date('d', strtotime($s['shift_date'] ?? $s['duty_date'] ?? ''));
                                                        $my_shifts[$day] = ['val' => $s['shift_type'], 'id' => $s['id']];
                                                    }
                                                }
                                            }
                                            
                                            $is_visible = (!$is_external || $has_shift);

                                            // ข้อมูลวันลา
                                            $my_leaves_on_days = [];
                                            if (isset($leaves) && is_array($leaves)) {
                                                foreach ($leaves as $l) {
                                                    if ($l['user_id'] == $staff['id']) {
                                                        $start_ts = strtotime($l['start_date']);
                                                        $end_ts = strtotime($l['end_date']);
                                                        $current_month_ts = strtotime($selected_month . '-01');
                                                        $end_month_ts = strtotime(date('Y-m-t', $current_month_ts));
                                                        
                                                        if ($start_ts <= $end_month_ts && $end_ts >= $current_month_ts) {
                                                            for ($t = $start_ts; $t <= $end_ts; $t += 86400) {
                                                                if (date('Y-m', $t) === $selected_month) {
                                                                    $leave_day = (int)date('d', $t);
                                                                    $status_text = ($l['status'] == 'APPROVED') ? '' : '(รอ)';
                                                                    $my_leaves_on_days[$leave_day] = trim(mb_substr($l['leave_type'], 0, 5) . ($status_text? '..' : '')); 
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        ?>
                                        <!-- 🌟 แนบ data-id ไว้ให้ SortableJS -->
                                        <tr class="roster-staff-row" id="row-staff-<?= htmlspecialchars($staff['id']) ?>" data-id="<?= htmlspecialchars($staff['id']) ?>" style="<?= $is_visible ? '' : 'display: none;' ?>">
                                            <td class="text-start px-3 shadow-sm bg-white" style="left: 0; position: sticky; z-index: 5; border-right: 2px solid #e2e8f0; <?php if($is_external) echo 'background-color: #fef2f2 !important;'; ?>">
                                                <div class="fw-bold text-dark d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center text-truncate pe-2">
                                                        <?php if ($canEdit && !$is_external): ?>
                                                            <i class="bi bi-grip-vertical text-muted drag-handle me-1 flex-shrink-0" style="cursor: grab;" title="ลากเพื่อสลับตำแหน่ง"></i>
                                                        <?php endif; ?>
                                                        <span class="text-truncate" style="font-size: 14.5px;"><?= htmlspecialchars($staff['name']) ?></span>
                                                    </div>
                                                    <div class="d-flex align-items-center flex-shrink-0">
                                                        <?php if ($is_external): ?>
                                                            <span class="badge bg-danger ms-1" style="font-size: 9px;">ช่วยราชการ</span>
                                                        <?php endif; ?>
                                                        <?php if ($canEdit): ?>
                                                            <i class="bi bi-person-x-fill text-danger ms-2 btn-remove-staff" style="cursor: pointer; font-size: 14px;" title="นำออกจากตารางเวร" onclick="removeStaffFromRoster('<?= $staff['id'] ?>', '<?= htmlspecialchars($staff['name'], ENT_QUOTES) ?>')"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="text-muted text-truncate <?= ($canEdit && !$is_external) ? 'ms-4' : '' ?>" style="font-size: 11px;">
                                                    <?= htmlspecialchars($staff['type']) ?>
                                                    <!-- 🌟 แจ้งเตือนคนยังไม่จัดกลุ่มสายงาน -->
                                                    <?php if(empty($staff['pay_rate_id'])): ?>
                                                        <span class="text-danger fw-bold ms-1" title="ยังไม่จัดกลุ่ม"><i class="bi bi-exclamation-triangle-fill"></i></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <?php for ($i=1; $i<=$days_in_month; $i++): 
                                                $full_date = "$year-$month-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                                                $shift_item = isset($my_shifts[$i]) ? $my_shifts[$i] : '';
                                                $shift_val = is_array($shift_item) ? $shift_item['val'] : $shift_item;
                                                $shift_id = is_array($shift_item) ? $shift_item['id'] : null;
                                                $color_class = getShiftColorClass($shift_val);

                                                $leave_txt = isset($my_leaves_on_days[$i]) ? $my_leaves_on_days[$i] : null;
                                                $is_approved_leave = ($leave_txt && strpos($leave_txt, '..') === false);
                                                
                                                // ไฮไลท์คอลัมน์วันนี้
                                                $is_current_day = ($full_date == date('Y-m-d'));
                                                $td_bg_class = $is_current_day ? 'today-column' : '';
                                            ?>
                                                <td class="p-0 text-center border-start-0 border-end-0 border-bottom <?= $td_bg_class ?>" style="height: 52px; position: relative; border-left: 1px solid #f1f5f9 !important;">
                                                    
                                                    <?php if ($leave_txt): ?>
                                                        <div class="position-absolute w-100 d-flex justify-content-center" style="top: 3px; left: 0; z-index: 2;">
                                                            <?php $badge_color = strpos($leave_txt, '..') !== false ? 'bg-warning text-dark' : 'bg-secondary text-white'; ?>
                                                            <span class="leave-badge-cell <?= $badge_color ?> shadow-sm" title="<?= htmlspecialchars($leave_txt) ?>">
                                                                <?= htmlspecialchars($leave_txt) ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($canEdit && !$is_approved_leave): ?>
                                                        <!-- โหมดแก้ไข -->
                                                        <!-- 🌟 เพิ่ม data-staff-payrate สำหรับส่งไปให้ JavaScript คำนวณเงิน -->
                                                        <button type="button" class="btn w-100 h-100 p-0 border-0 shadow-none hover-cell shift-cell <?= $color_class ?>" 
                                                                style="padding-top: <?= $leave_txt ? '15px' : '0' ?> !important;"
                                                                data-staff-id="<?= $staff['id'] ?>"
                                                                data-staff-type="<?= htmlspecialchars($staff['type']) ?>"
                                                                data-staff-payrate="<?= htmlspecialchars($staff['pay_rate_id'] ?? '') ?>"
                                                                data-date="<?= $full_date ?>"
                                                                onclick="openShiftModal(this)"
                                                                ondblclick="saveShift('', 'text-dark'); event.stopPropagation();" title="คลิก 1 ครั้งเพื่อเลือกเวร / ดับเบิลคลิกเพื่อลบเวร">
                                                            <?= htmlspecialchars($shift_val) ?>
                                                        </button>
                                                        <span class="position-absolute top-0 end-0 p-1 d-none save-indicator" style="font-size: 8px; color: #10b981; z-index:3;"><i class="bi bi-cloud-check-fill"></i></span>
                                                    
                                                    <?php else: ?>
                                                        <!-- โหมดอ่านอย่างเดียว -->
                                                        <div class="w-100 h-100 d-flex justify-content-center align-items-center shift-cell <?= $color_class ?>" 
                                                             style="padding-top: <?= $leave_txt ? '15px' : '0' ?> !important; <?= $is_approved_leave ? 'opacity: 0.6;' : '' ?>">
                                                            <?= htmlspecialchars($shift_val) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <!-- 🌟 Drop Zone สำหรับลากคนนอกมาลงตาราง -->
                                    <?php if ($canEdit): ?>
                                    <tr id="dropZoneRow" ondragover="allowDrop(event)" ondrop="dropStaff(event)" ondragleave="dragLeave(event)" class="bg-light bg-opacity-75">
                                        <td colspan="<?= $days_in_month + 1 ?>" class="py-4 text-center text-primary" style="border: 2px dashed #a5b4fc; transition: all 0.2s;">
                                            <i class="bi bi-person-down fs-3 d-block mb-1 opacity-75"></i>
                                            <span class="fw-bold fs-6">ลากรายชื่อเจ้าหน้าที่จากแถบด้านขวามาวางที่บริเวณนี้</span>
                                            <div class="text-muted mt-1" style="font-size: 12px;">เพื่อเพิ่มผู้ปฏิบัติงานนอกสังกัด ลงในตารางเวรเดือนนี้</div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-white border-top p-3 text-muted d-flex flex-wrap justify-content-between align-items-center" style="font-size: 12px;">
                        <div>
                            <i class="bi bi-info-circle text-primary me-1"></i> 
                            <strong>สัญลักษณ์:</strong> <span class="fw-bold text-warning text-dark mx-1">บ</span> = บ่าย, <span class="fw-bold text-success mx-1">ร</span> = ดึก, <span class="fw-bold text-danger mx-1">ย</span> = วันหยุด
                        </div>
                        <div class="text-primary fw-bold">
                            <i class="bi bi-mouse2 me-1"></i> ดับเบิลคลิกที่ช่องเพื่อลบเวรอย่างรวดเร็ว
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🌟 แถบรายชื่อบุคลากร (Sidebar) -->
            <div class="col-xl-3 col-lg-4 sticky-sidebar">
                <div class="card card-modern p-0 d-flex flex-column position-relative h-100">
                    
                    <?php if (!$canEdit): ?>
                    <div class="bg-warning bg-opacity-25 text-dark p-2 text-center border-bottom d-flex align-items-center justify-content-center gap-2" style="font-size: 13px; font-weight: bold; border-radius: 1.25rem 1.25rem 0 0;">
                        <i class="bi bi-lock-fill text-danger"></i> 
                        <?php 
                            if ($roster_status == 'SUBMITTED') echo "รออนุมัติ (แก้ไขไม่ได้)";
                            elseif ($roster_status == 'APPROVED') echo "อนุมัติแล้ว (แก้ไขไม่ได้)";
                            elseif ($roster_status == 'REQUEST_EDIT') echo "ส่งคำขอแก้ไขแล้ว รอแอดมินปลดล็อค";
                            else echo "โหมดดูข้อมูล (อ่านได้อย่างเดียว)";
                        ?>
                    </div>
                    <?php endif; ?>

                    <div class="p-3 border-bottom bg-light <?= $canEdit ? 'rounded-top-4' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold <?= !$canEdit ? 'text-muted' : 'text-dark' ?> mb-0 d-flex align-items-center">
                                <i class="bi bi-people-fill <?= !$canEdit ? 'text-muted' : 'text-primary' ?> me-2"></i> <?= $canEdit ? 'เลือกบุคลากรเข้าเวร' : 'รายชื่อ/สถิติบุคลากร' ?>
                            </h6>
                        </div>
                        
                        <div class="mb-2">
                            <select id="staffHospitalFilter" class="form-select form-select-sm shadow-sm border-primary border-opacity-25 font-monospace fw-bold text-primary rounded-3">
                                <option value="own" selected>🔹 บุคลากรในสังกัด รพ.สต.</option>
                                <option value="external">🔸 บุคลากรช่วยราชการ</option>
                            </select>
                        </div>

                        <div class="input-group input-group-sm input-group-modern mt-2">
                            <span class="input-group-text"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="staffSearch" class="form-control" placeholder="ค้นหาชื่อ หรือตำแหน่ง...">
                        </div>
                    </div>
                    
                    <div class="flex-grow-1 overflow-auto p-3 bg-light custom-scrollbar rounded-bottom-4" id="staffListContainer">
                        <?php 
                        foreach ($all_staff_for_sidebar as $staff): 
                            $is_external = (isset($staff['hospital_id']) && $staff['hospital_id'] != ($hospital_id??0));
                            $bs_color = getBsColor($staff['color_theme']);
                        ?>
                        <!-- 🌟 แนบ pay_rate_id ไว้เผื่อดึงผ่าน JS -->
                        <div class="card mb-2 shadow-sm border-0 rounded-3 staff-card draggable-staff" 
                             draggable="<?= $canEdit ? 'true' : 'false' ?>"
                             <?= $canEdit ? 'ondragstart="drag(event)"' : '' ?>
                             style="<?= $is_external ? 'display: none;' : '' ?>"
                             data-userid="<?= $staff['id'] ?>" 
                             data-username="<?= $staff['name'] ?>"
                             data-payrateid="<?= $staff['pay_rate_id'] ?? '' ?>"
                             data-is-external="<?= $is_external ? 'true' : 'false' ?>">
                            
                            <div class="card-body p-2 d-flex align-items-center">
                                <div class="bg-<?= $bs_color ?> bg-opacity-10 text-<?= $bs_color ?> rounded-circle d-flex justify-content-center align-items-center fw-bold me-3 flex-shrink-0" style="width: 38px; height: 38px; font-size:15px;">
                                    <?= mb_substr($staff['name'], 0, 1, 'UTF-8') ?>
                                </div>
                                <div class="flex-grow-1 text-truncate">
                                    <h6 class="mb-0 fw-bold text-dark staff-name text-truncate" style="font-size: 13px;" title="<?= htmlspecialchars($staff['name']) ?>">
                                        <?= htmlspecialchars($staff['name']) ?>
                                    </h6>
                                    <div class="text-muted text-truncate staff-position" style="font-size: 11px;" title="<?= htmlspecialchars($staff['type']) ?>">
                                        <?= htmlspecialchars($staff['type']) ?>
                                        <?php if ($is_external): ?>
                                            <span class="text-danger ms-1 fw-bold">(ที่อื่น)</span>
                                        <?php endif; ?>
                                        <!-- 🌟 แจ้งเตือนคนยังไม่จัดกลุ่มสายงาน -->
                                        <?php if (empty($staff['pay_rate_id'])): ?>
                                            <span class="text-danger fw-bold ms-1" title="ยังไม่ได้ระบุกลุ่มค่าตอบแทน"><i class="bi bi-exclamation-triangle-fill"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ================= 🌟 Modal เลือกรหัสเวร (Shift Selector) ================= -->
<?php if ($canEdit): ?>
<div class="modal fade" id="shiftSelectorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-hand-index-thumb text-primary me-2"></i> เลือกกะปฏิบัติงาน</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3 pb-4">
                <div class="text-center mb-3">
                    <div class="badge bg-primary bg-opacity-10 text-primary fw-bold px-3 py-2 rounded-pill" id="shiftModalDate" style="font-size: 14px;"></div>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-warning text-dark fw-bold border-2 rounded-3" onclick="saveShift('บ', 'text-warning text-dark')">บ นอกเวลาบ่าย</button>
                    <button class="btn btn-outline-success fw-bold border-2 rounded-3" onclick="saveShift('ร', 'text-success')">ร On call (ดึก)</button>
                    <button class="btn btn-outline-danger fw-bold border-2 rounded-3" onclick="saveShift('ย', 'text-danger')">ย วันหยุดราชการ</button>
                    <div class="row g-2 mt-1">
                        <div class="col-6"><button class="btn btn-outline-primary w-100 fw-bold border-2 rounded-3" onclick="saveShift('บ/ร', 'text-primary')">บ/ร</button></div>
                        <div class="col-6"><button class="btn btn-outline-primary w-100 fw-bold border-2 rounded-3" onclick="saveShift('ย/บ', 'text-primary')">ย/บ</button></div>
                    </div>
                    <hr class="my-2 opacity-10">
                    <button class="btn btn-light text-secondary fw-bold border rounded-3" onclick="saveShift('', 'text-dark')">ลบข้อมูลเวร (ว่าง)</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================= 🌟 Modal สรุปยอดเดือนนี้ ================= -->
<div class="modal fade" id="summaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 bg-light pb-3 rounded-top-4">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-bar-chart-fill text-primary me-2"></i> สรุปยอดการปฏิบัติงานและค่าตอบแทน
                    <span class="fs-6 text-muted ms-2 fw-normal">(เดือน <?= $display_month_text ?>)</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0 align-middle text-center">
                        <thead class="table-light text-secondary" style="font-size: 13px;">
                            <tr>
                                <th class="text-start px-4 align-middle" rowspan="2">ชื่อ - สกุล</th>
                                <th class="text-start align-middle" rowspan="2">ตำแหน่ง / ต้นสังกัด</th>
                                
                                <th class="py-3 align-top" style="color: #059669; background-color: #ecfdf5;">
                                    <div class="mb-1">เวรรอ (ร)</div>
                                    <div class="text-muted fw-normal mb-1" style="font-size: 10px;"><i class="bi bi-clock"></i> 20.31-08.29 น.</div>
                                    <div class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25 w-100" style="font-size: 10px;">ตามเรทบุคคล</div>
                                </th>
                                <th class="py-3 align-top" style="color: #dc2626; background-color: #fef2f2;">
                                    <div class="mb-1">วันหยุด (ย)</div>
                                    <div class="text-muted fw-normal mb-1" style="font-size: 10px;"><i class="bi bi-clock"></i> 08.30-16.30 น.</div>
                                    <div class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25 w-100" style="font-size: 10px;">ตามเรทบุคคล</div>
                                </th>
                                <th class="py-3 align-top" style="color: #4f46e5; background-color: #f8fafc;">
                                    <div class="mb-1">เวรบ่าย (บ)</div>
                                    <div class="text-muted fw-normal mb-1" style="font-size: 10px;"><i class="bi bi-clock"></i> 16.31-20.30 น.</div>
                                    <div class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 w-100" style="font-size: 10px;">ตามเรทบุคคล</div>
                                </th>

                                <th class="align-middle fw-bold bg-light text-dark border-start" rowspan="2">รวมทั้งหมด (กะ)</th>
                                <th class="align-middle fw-bold bg-success text-white border-start" rowspan="2" style="width: 140px;">ค่าตอบแทน (บาท)</th>
                            </tr>
                        </thead>
                        <tbody id="summaryTableBody">
                            <?php 
                            $total_r = 0; $total_y = 0; $total_b = 0; $total_all = 0; $total_pay_all = 0;
                            
                            foreach ($all_staff_for_sidebar as $staff): 
                                $is_external = (isset($staff['hospital_id']) && $staff['hospital_id'] != ($hospital_id??0));
                                
                                $sum_r = 0; $sum_y = 0; $sum_b = 0;
                                if (isset($shifts)) {
                                    foreach ($shifts as $s) {
                                        if ($s['user_id'] == $staff['id']) {
                                            $val = $s['shift_type'];
                                            if ($val === 'ร' || $val === 'N') $sum_r++;
                                            elseif ($val === 'ย' || $val === 'O') $sum_y++;
                                            elseif ($val === 'บ' || $val === 'A') $sum_b++;
                                            elseif ($val === 'บ/ร') { $sum_b++; $sum_r++; }
                                            elseif ($val === 'ย/บ') { $sum_y++; $sum_b++; }
                                        }
                                    }
                                }
                                $totalShift = $sum_r + $sum_y + $sum_b;

                                $pay = 0;
                                // 🌟 ใช้งานฟังก์ชันใหม่โดยส่ง $staff เข้าไปเช็ค pay_rate_id ตรงๆ
                                $rates = calculatePayRatesPHP($staff, $pay_rates_db ?? []);
                                
                                if (isset($pay_snapshot) && isset($pay_snapshot[$staff['id']])) {
                                    $pay = $pay_snapshot[$staff['id']]['pay'];
                                } else {
                                    $pay = ($sum_r * $rates['ร']) + ($sum_y * $rates['ย']) + ($sum_b * $rates['บ']);
                                }

                                // เช็คสิทธิ์เพื่อซ่อนเงินคนอื่น
                                $is_own_row = ($staff['id'] == $_SESSION['user']['id']);
                                $show_pay = ($_SESSION['user']['role'] !== 'STAFF' || $is_own_row);
                                
                                if ($_SESSION['user']['role'] !== 'STAFF') {
                                    $total_pay_all += $pay;
                                }

                                $is_visible = (!$is_external || $totalShift > 0);
                                $total_r += $sum_r; $total_y += $sum_y; $total_b += $sum_b; $total_all += $totalShift;

                                // 🌟 ค้นหาชื่อกลุ่มสายงานจาก $pay_rates_db เพื่อป้องกันกรณีที่ Controller ไม่ได้ JOIN ข้อมูลมาให้
                                $group_name_val = 'ไม่มีกลุ่ม';
                                if (!empty($staff['pay_rate_id']) && !empty($pay_rates_db)) {
                                    foreach ($pay_rates_db as $pr) {
                                        if ($pr['id'] == $staff['pay_rate_id']) {
                                            $group_name_val = $pr['name'] ?? $pr['keywords'] ?? 'กลุ่มที่ ' . $pr['id'];
                                            break;
                                        }
                                    }
                                }
                                if (!empty($staff['pay_rate_name'])) {
                                    $group_name_val = $staff['pay_rate_name'];
                                }
                                $group_name_attr = htmlspecialchars($group_name_val);
                                
                                // 🌟 สร้าง Data Attributes ส่งไปแสดงผลใน Modal
                                $data_attr = sprintf(
                                    'data-name="%s" data-group-name="%s" data-sum-r="%d" data-rate-r="%d" data-sum-y="%d" data-rate-y="%d" data-sum-b="%d" data-rate-b="%d" data-total="%d"',
                                    htmlspecialchars($staff['name']),
                                    $group_name_attr,
                                    $sum_r, $rates['ร'],
                                    $sum_y, $rates['ย'],
                                    $sum_b, $rates['บ'],
                                    $pay
                                );
                            ?>
                            <tr class="<?= $is_external ? 'bg-danger bg-opacity-10' : '' ?> summary-staff-row" id="summary-row-<?= htmlspecialchars($staff['id']) ?>" style="<?= $is_visible ? '' : 'display: none;' ?>">
                                <td class="text-start px-4 fw-medium text-dark">
                                    <?= htmlspecialchars($staff['name']) ?>
                                    <?php if ($is_external): ?>
                                        <span class="badge bg-danger text-white ms-1" style="font-size: 9px; font-weight: normal;">ช่วยราชการ</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-start text-muted" style="font-size: 12px;">
                                    <?= htmlspecialchars($staff['type']) ?><br>
                                    <?php if ($is_external): ?>
                                        <span class="text-danger fw-bold"><i class="bi bi-building"></i> สังกัดอื่น</span>
                                    <?php else: ?>
                                        <span class="text-success"><i class="bi bi-house-door"></i> ในสังกัด</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle fs-6" style="color: #059669;" id="modal-sum-r-<?= $staff['id'] ?>"><?= $sum_r ?></td>
                                <td class="align-middle fs-6" style="color: #dc2626;" id="modal-sum-y-<?= $staff['id'] ?>"><?= $sum_y ?></td>
                                <td class="align-middle fs-6" style="color: #4f46e5;" id="modal-sum-b-<?= $staff['id'] ?>"><?= $sum_b ?></td>
                                <td class="align-middle fw-bold text-dark bg-light border-start fs-5" id="modal-sum-total-<?= $staff['id'] ?>"><?= $totalShift ?></td>
                                
                                <td class="align-middle fw-bold text-success bg-success bg-opacity-10 border-start fs-5 text-end pe-4 <?= $show_pay ? 'pay-cell-clickable' : '' ?>" 
                                    id="modal-sum-pay-<?= $staff['id'] ?>"
                                    <?= $show_pay ? "onclick='showPayCalculation(this)' $data_attr" : "" ?> title="คลิกเพื่อดูรายละเอียด">
                                    <?= $show_pay ? number_format($pay) . ' <i class="bi bi-info-circle text-muted ms-1" style="font-size: 12px;"></i>' : '<i class="bi bi-lock-fill text-muted opacity-50" style="font-size: 16px;" title="ปกปิดข้อมูล"></i>' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold text-dark">
                            <tr>
                                <td colspan="2" class="text-end px-4 py-3">รวมยอดสรุปทั้งหมด</td>
                                <td class="py-3 fs-6" style="color: #059669;" id="grand-total-r"><?= $total_r ?></td>
                                <td class="py-3 fs-6" style="color: #dc2626;" id="grand-total-y"><?= $total_y ?></td>
                                <td class="py-3 fs-6" style="color: #4f46e5;" id="grand-total-b"><?= $total_b ?></td>
                                <td class="py-3 text-dark border-start fs-5" id="grand-total-all"><?= $total_all ?></td>
                                <td class="py-3 text-success border-start fs-4 text-end pe-4" id="grand-total-pay">
                                    <?= $_SESSION['user']['role'] !== 'STAFF' ? number_format($total_pay_all) : '<i class="bi bi-lock-fill text-muted opacity-50" title="ปกปิดข้อมูล"></i>' ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary fw-bold rounded-pill px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= 🌟 Modal รายละเอียดค่าตอบแทน ================= -->
<div class="modal fade" id="payCalcModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 bg-light rounded-top-4 pb-2">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-calculator text-primary me-2"></i> รายละเอียดค่าตอบแทน</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2 pb-4">
                <!-- 🌟 เพิ่มการแสดงชื่อกลุ่มสายงานเพื่อให้ผู้ใช้มั่นใจว่าระบบคำนวณถูกต้อง -->
                <div class="text-center mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill shadow-sm" id="calcStaffName" style="font-size: 13px;">ชื่อพนักงาน</span>
                    <div class="mt-2">
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" id="calcGroupName" style="font-size: 11px;">กลุ่มสายงาน</span>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                    <span class="text-muted"><span class="badge bg-success me-1">ร</span> เวรดึก (<span id="calcSumR" class="fw-bold text-dark">0</span>)</span>
                    <span><span id="calcRateR" class="text-muted">0</span> = <span id="calcTotalR" class="fw-bold text-success">0</span> ฿</span>
                </div>
                
                <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                    <span class="text-muted"><span class="badge bg-warning text-dark me-1">บ</span> เวรบ่าย (<span id="calcSumB" class="fw-bold text-dark">0</span>)</span>
                    <span><span id="calcRateB" class="text-muted">0</span> = <span id="calcTotalB" class="fw-bold text-success">0</span> ฿</span>
                </div>
                
                <div class="d-flex justify-content-between mb-3 small border-bottom pb-2">
                    <span class="text-muted"><span class="badge bg-danger me-1">ย</span> วันหยุด (<span id="calcSumY" class="fw-bold text-dark">0</span>)</span>
                    <span><span id="calcRateY" class="text-muted">0</span> = <span id="calcTotalY" class="fw-bold text-success">0</span> ฿</span>
                </div>
                
                <div class="d-flex justify-content-between pt-2 border-top border-2">
                    <strong class="text-dark">รวมทั้งสิ้น</strong>
                    <strong class="text-primary fs-5"><span id="calcGrandTotal">0</span> ฿</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= 🌟 Modal ข้อมูลวันหยุด ================= -->
<div class="modal fade" id="holidayInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-event text-primary me-2"></i> ข้อมูลวันหยุด</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3 pb-4 text-center">
                <div class="text-primary fw-bold mb-3" id="hiModalDate" style="font-size: 15px;"></div>
                
                <div id="hiExistingBlock" style="display: none;">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3 mb-3 border border-danger border-opacity-25">
                        <i class="bi bi-star-fill fs-1 mb-2 d-block opacity-75"></i>
                        <div class="fw-bold fs-5" id="hiName"></div>
                    </div>
                    <button type="button" class="btn btn-light w-100 fw-bold border rounded-pill shadow-sm" data-bs-dismiss="modal">รับทราบ</button>
                </div>

                <div id="hiRequestBlock" style="display: none;">
                    <div class="text-muted mb-3" style="font-size: 13px;">
                        <i class="bi bi-info-circle me-1"></i> วันนี้ไม่มีในปฏิทินวันหยุด<br>ต้องการเสนอแอดมินให้ตั้งเป็นวันหยุดหรือไม่?
                    </div>
                    <input type="text" id="hiRequestName" class="form-control text-center fw-bold mb-3 shadow-sm rounded-pill" placeholder="ระบุชื่อวันหยุด (เช่น งานประเพณี)">
                    <button type="button" class="btn btn-primary w-100 fw-bold shadow-sm rounded-pill" onclick="submitHolidayRequest()">
                        <i class="bi bi-send me-1"></i> เสนอให้ส่วนกลางอนุมัติ
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= Scripts การทำงานหลัก ================= -->
<script>
// 🌟 นำเข้าฐานข้อมูลเรทเงินจาก PHP ลง JavaScript
const payRatesDB = <?php echo json_encode($pay_rates_db ?? []); ?>;
const isApprovedSnapshot = <?= ($roster_status == 'APPROVED' && isset($pay_snapshot)) ? 'true' : 'false' ?>;

let currentCellBtn = null;
let shiftModal = null; 
let payCalcModal = null; 
let holidayInfoModal = null;
let selectedHolidayDate = '';

document.addEventListener('DOMContentLoaded', function() {
    
    // 🌟 ระบบค้นหาหน่วยบริการและเดือน (Dropdown)
    const setupDropdownSearch = (inputId, optionClass, hiddenInputId, formId) => {
        const searchInput = document.getElementById(inputId);
        const options = document.querySelectorAll(`.${optionClass}`);
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const filter = this.value.toLowerCase().trim();
                options.forEach(opt => {
                    opt.parentElement.style.display = opt.textContent.toLowerCase().includes(filter) ? '' : 'none';
                });
            });
        }
        options.forEach(opt => {
            opt.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById(hiddenInputId).value = this.getAttribute('data-val');
                document.getElementById(formId).submit();
            });
        });
    };
    setupDropdownSearch('hospSearchInput', 'hosp-option', 'selectedHospInput', 'filterFormRoster');
    setupDropdownSearch('monthSearchInput', 'month-option', 'selectedMonthInput', 'filterFormRoster');

    // 🌟 ระบบค้นหารายชื่อบุคลากรด้านขวามือ
    const staffSearch = document.getElementById('staffSearch');
    const staffHospitalFilter = document.getElementById('staffHospitalFilter');
    const staffCards = document.querySelectorAll('.staff-card');

    function applyFilters() {
        if (!staffSearch) return;
        const term = staffSearch.value.toLowerCase().trim();
        const showType = staffHospitalFilter.value;

        staffCards.forEach(card => {
            const name = card.querySelector('.staff-name').textContent.toLowerCase();
            const position = card.querySelector('.staff-position').textContent.toLowerCase();
            const isExternal = card.getAttribute('data-is-external') === 'true';
            
            let matchType = false;
            if (showType === 'own' && !isExternal) matchType = true;
            if (showType === 'external' && isExternal) matchType = true;

            if (matchType && (name.includes(term) || position.includes(term))) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }
    if (staffSearch) staffSearch.addEventListener('input', applyFilters);
    if (staffHospitalFilter) staffHospitalFilter.addEventListener('change', applyFilters);

    // 🌟 ระบบ SortableJS (ลากสลับตำแหน่ง)
    const rosterTableBody = document.getElementById('rosterTableBody');
    if (typeof Sortable !== 'undefined' && rosterTableBody) {
        new Sortable(rosterTableBody, {
            handle: '.drag-handle', 
            animation: 150,
            ghostClass: 'sortable-ghost', 
            filter: '#dropZoneRow', 
            swapThreshold: 0.65,
            onEnd: function (evt) {
                const orderedRows = Array.from(rosterTableBody.querySelectorAll('.roster-staff-row'));
                const orderData = orderedRows.map((row, index) => {
                    return { id: row.getAttribute('data-id'), order: index };
                });

                fetch('index.php?c=ajax&a=update_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: orderData })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status !== 'success') alert('เกิดข้อผิดพลาดในการบันทึกลำดับ: ' + data.message);
                }).catch(err => console.error(err));
            }
        });
    }
});

// ==========================================
// 🌟 Native Drag & Drop (ดึงคนนอกมาช่วยเวร)
// ==========================================
function drag(ev) {
    ev.dataTransfer.setData("text/plain", ev.target.getAttribute('data-userid'));
}
function allowDrop(ev) {
    ev.preventDefault();
    const dropZone = document.getElementById('dropZoneRow');
    if (dropZone) dropZone.classList.add('bg-primary', 'bg-opacity-10');
}
function dragLeave(ev) {
    const dropZone = document.getElementById('dropZoneRow');
    if (dropZone) dropZone.classList.remove('bg-primary', 'bg-opacity-10');
}
function dropStaff(ev) {
    ev.preventDefault();
    const dropZone = document.getElementById('dropZoneRow');
    if (dropZone) dropZone.classList.remove('bg-primary', 'bg-opacity-10');
    
    const userId = ev.dataTransfer.getData("text/plain");
    if (!userId) return;

    const staffRow = document.getElementById('row-staff-' + userId);
    if (staffRow) {
        if (staffRow.style.display === 'none') {
            staffRow.style.display = '';
            const summaryRow = document.getElementById('summary-row-' + userId);
            if (summaryRow) summaryRow.style.display = '';
            showToast('success', 'เพิ่มบุคลากรลงในตารางเวรแล้ว (จัดเวรได้เลย)');
            staffRow.classList.add('bg-success', 'bg-opacity-10');
            setTimeout(() => staffRow.classList.remove('bg-success', 'bg-opacity-10'), 2000);
        } else {
            showToast('warning', 'บุคลากรท่านนี้มีรายชื่ออยู่ในตารางเวรอยู่แล้ว');
        }
    }
}

// ==========================================
// 🌟 ฟังก์ชันคำนวณและ UI การจัดเวร
// ==========================================
function showPayCalculation(el) {
    if (!payCalcModal) payCalcModal = new bootstrap.Modal(document.getElementById('payCalcModal'));
    document.getElementById('calcStaffName').innerText = el.getAttribute('data-name');
    
    // 🌟 แสดงชื่อกลุ่มสายงานใน Modal
    document.getElementById('calcGroupName').innerText = 'กลุ่ม: ' + (el.getAttribute('data-group-name') || 'ไม่ระบุ');

    ['R', 'Y', 'B'].forEach(type => {
        const sum = parseInt(el.getAttribute(`data-sum-${type.toLowerCase()}`));
        const rate = parseInt(el.getAttribute(`data-rate-${type.toLowerCase()}`));
        document.getElementById(`calcSum${type}`).innerText = sum;
        document.getElementById(`calcRate${type}`).innerText = `× ${rate}`;
        document.getElementById(`calcTotal${type}`).innerText = (sum * rate).toLocaleString();
    });
    document.getElementById('calcGrandTotal').innerText = parseInt(el.getAttribute('data-total')).toLocaleString();
    payCalcModal.show();
}

function openShiftModal(btn) {
    if (!shiftModal) shiftModal = new bootstrap.Modal(document.getElementById('shiftSelectorModal'));
    currentCellBtn = btn;
    const dateStr = btn.getAttribute('data-date');
    const parts = dateStr.split('-');
    const thMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    document.getElementById('shiftModalDate').innerText = `วันที่ ${parseInt(parts[2], 10)} ${thMonths[parseInt(parts[1], 10)-1]} ${parseInt(parts[0])+543}`;
    shiftModal.show();
}

function saveShift(shiftValue, colorClass) {
    if (!currentCellBtn) return;
    const staffId = currentCellBtn.getAttribute('data-staff-id');
    
    // 🌟 ดึง ID กลุ่มค่าตอบแทนมาจากปุ่ม (data attribute)
    const payRateId = currentCellBtn.getAttribute('data-staff-payrate');
    
    const dateStr = currentCellBtn.getAttribute('data-date');
    
    currentCellBtn.innerText = shiftValue;
    currentCellBtn.className = `btn w-100 h-100 p-0 border-0 shadow-none hover-cell shift-cell ${colorClass}`;
    if (shiftModal) shiftModal.hide();
    
    recalculateRowSummary(staffId, payRateId);
    
    const indicator = currentCellBtn.nextElementSibling;
    if (indicator) indicator.classList.remove('d-none');
    
    fetch('index.php?c=ajax&a=save_shift', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ user_id: staffId, date: dateStr, shift_type: shiftValue })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            if (indicator) setTimeout(() => indicator.classList.add('d-none'), 1500);
        } else {
            alert('Error: ' + data.message); window.location.reload();
        }
    });
}

function removeStaffFromRoster(staffId, staffName) {
    if (!confirm(`ยืนยันการนำ "${staffName}" ออกจากตารางเวร?\n(ระบบจะล้างข้อมูลเวรเดือนนี้ของบุคคลนี้ทั้งหมด)`)) return;
    const row = document.getElementById('row-staff-' + staffId);
    if (row) row.style.opacity = '0.5'; 

    const cells = document.querySelectorAll(`.shift-cell[data-staff-id="${staffId}"]`);
    let promises = [];

    cells.forEach(cell => {
        if (cell.innerText.trim() !== '') {
            promises.push(fetch('index.php?c=ajax&a=save_shift', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ user_id: staffId, date: cell.getAttribute('data-date'), shift_type: '' })
            }).then(res => res.json()));
        }
    });

    Promise.all(promises).then(results => {
        if (results.every(r => r.status === 'success') || promises.length === 0) {
            cells.forEach(cell => {
                cell.innerText = '';
                cell.className = 'btn w-100 h-100 p-0 border-0 shadow-none hover-cell shift-cell text-dark';
            });
            
            // 🌟 ดึง ID กลุ่มค่าตอบแทนเพื่อรีคำนวณหลังลบ
            const payRateId = row.querySelector('.shift-cell').getAttribute('data-staff-payrate');
            recalculateRowSummary(staffId, payRateId);
            
            row.style.display = 'none'; row.style.opacity = '1';
            const summaryRow = document.getElementById('summary-row-' + staffId);
            if (summaryRow) summaryRow.style.display = 'none';
            updateGrandTotals();
        } else { alert('ลบข้อมูลไม่สำเร็จบางส่วน'); row.style.opacity = '1'; }
    });
}

function checkFatigueRules() {
    let warningCount = 0;
    document.querySelectorAll('.shift-cell').forEach(c => c.classList.remove('fatigue-warn'));
    document.querySelectorAll('.roster-staff-row').forEach(row => {
        const cells = Array.from(row.querySelectorAll('.shift-cell'));
        let consecutiveDays = 0;
        for (let i = 0; i < cells.length; i++) {
            const val = cells[i].innerText.trim();
            const nextVal = (i + 1 < cells.length) ? cells[i+1].innerText.trim() : '';
            if (val !== '' && val !== 'OFF' && val !== 'ย') {
                consecutiveDays++;
                if (consecutiveDays > 7) { cells[i].classList.add('fatigue-warn'); cells[i].setAttribute('title', '⚠️ ทำงานติดกันเกิน 7 วัน'); warningCount++; }
            } else consecutiveDays = 0;
            if (val.includes('ร') && nextVal.includes('M')) { cells[i+1].classList.add('fatigue-warn'); cells[i+1].setAttribute('title', '⚠️ ลงดึกต่อเช้า'); warningCount++; }
        }
    });
    alert(warningCount > 0 ? `ตรวจพบจุดเสี่ยงความเหนื่อยล้า ${warningCount} จุด! (กระพริบสีแดง)` : 'ตารางเวรนี้ผ่านเกณฑ์ความปลอดภัย!');
}

// 🌟 อัปเดตฟังก์ชันดึงเรทเงิน: ใช้ pay_rate_id ค้นหาตรงๆ ทันที
function getPayRates(payRateId) {
    let r = { r: 0, y: 0, b: 0, name: 'ไม่มีกลุ่ม' };
    if (!payRateId) return r; // ถ้ายังไม่จัดกลุ่มก็ให้เป็น 0
    
    for (let group of payRatesDB) {
        if (group.id == payRateId) {
            return { 
                r: parseInt(group.rate_r)||0, 
                y: parseInt(group.rate_y)||0, 
                b: parseInt(group.rate_b)||0,
                name: group.name || group.keywords || 'กลุ่มที่ ' + group.id 
            };
        }
    }
    return r;
}

// 🌟 อัปเดตฟังก์ชัน: รับค่า payRateId เข้ามาทำงาน
function recalculateRowSummary(staffId, payRateId) {
    let sumR = 0, sumY = 0, sumB = 0;
    document.querySelectorAll(`.shift-cell[data-staff-id="${staffId}"]`).forEach(cell => {
        let val = cell.innerText.trim();
        if (val === 'ร' || val === 'N') sumR++; else if (val === 'ย' || val === 'O') sumY++; else if (val === 'บ' || val === 'A') sumB++;
        else if (val === 'บ/ร') { sumB++; sumR++; } else if (val === 'ย/บ') { sumY++; sumB++; }
    });
    
    if(document.getElementById(`modal-sum-r-${staffId}`)) document.getElementById(`modal-sum-r-${staffId}`).innerText = sumR;
    if(document.getElementById(`modal-sum-y-${staffId}`)) document.getElementById(`modal-sum-y-${staffId}`).innerText = sumY;
    if(document.getElementById(`modal-sum-b-${staffId}`)) document.getElementById(`modal-sum-b-${staffId}`).innerText = sumB;
    if(document.getElementById(`modal-sum-total-${staffId}`)) document.getElementById(`modal-sum-total-${staffId}`).innerText = sumR + sumY + sumB;

    if(!isApprovedSnapshot) {
        // 🌟 เรียกใช้ฟังก์ชันหาเรทเงินใหม่
        const rates = getPayRates(payRateId);
        
        const totalPay = (sumR * rates.r) + (sumY * rates.y) + (sumB * rates.b);
        const payCell = document.getElementById(`modal-sum-pay-${staffId}`);
        if(payCell) {
            payCell.setAttribute('data-sum-r', sumR); payCell.setAttribute('data-sum-y', sumY);
            payCell.setAttribute('data-sum-b', sumB); payCell.setAttribute('data-total', totalPay);
            payCell.setAttribute('data-group-name', rates.name); // 🌟 อัปเดตชื่อกลุ่ม
            payCell.innerHTML = totalPay.toLocaleString() + ' <i class="bi bi-info-circle text-muted ms-1" style="font-size: 12px;"></i>';
        }
    }
    updateGrandTotals();
}

function updateGrandTotals() {
    let grandR = 0, grandY = 0, grandB = 0, grandTotal = 0, grandPay = 0;
    document.querySelectorAll('.summary-staff-row').forEach(row => {
        if (row.style.display !== 'none') {
            const id = row.getAttribute('id').replace('summary-row-', '');
            grandR += parseInt(document.getElementById(`modal-sum-r-${id}`)?.innerText||0);
            grandY += parseInt(document.getElementById(`modal-sum-y-${id}`)?.innerText||0);
            grandB += parseInt(document.getElementById(`modal-sum-b-${id}`)?.innerText||0);
            grandTotal += parseInt(document.getElementById(`modal-sum-total-${id}`)?.innerText||0);
            <?php if ($_SESSION['user']['role'] !== 'STAFF'): ?>
            grandPay += parseInt((document.getElementById(`modal-sum-pay-${id}`)?.innerText||'0').replace(/,/g,''))||0;
            <?php endif; ?>
        }
    });
    if(document.getElementById('grand-total-r')) document.getElementById('grand-total-r').innerText = grandR;
    if(document.getElementById('grand-total-y')) document.getElementById('grand-total-y').innerText = grandY;
    if(document.getElementById('grand-total-b')) document.getElementById('grand-total-b').innerText = grandB;
    if(document.getElementById('grand-total-all')) document.getElementById('grand-total-all').innerText = grandTotal;
    <?php if ($_SESSION['user']['role'] !== 'STAFF'): ?>
    if(document.getElementById('grand-total-pay')) document.getElementById('grand-total-pay').innerText = grandPay.toLocaleString();
    <?php endif; ?>
}

function showToast(type, message) {
    const toastHtml = `<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1055;">
        <div class="toast align-items-center text-bg-${type} border-0 show shadow-lg" role="alert"><div class="d-flex">
        <div class="toast-body fw-bold"><i class="bi bi-info-circle-fill me-2"></i> ${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
        </div></div></div>`;
    document.body.insertAdjacentHTML('beforeend', toastHtml);
    setTimeout(() => { const t = document.querySelector('.toast-container'); if(t) t.remove(); }, 3000);
}

function copyPreviousMonth(currentMonth) {
    if(confirm('ระบบจะดึงแพทเทิร์นตารางเวรจาก "เดือนก่อนหน้า" มาทับข้อมูลเดือนปัจจุบันทั้งหมด\n\nยืนยันการดำเนินการหรือไม่?')) {
        fetch('index.php?c=ajax&a=copy_roster_previous', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ target_month: currentMonth }) })
        .then(res => res.json()).then(data => {
            if(data.status === 'success') { alert('คัดลอกตารางสำเร็จ!'); window.location.reload(); } else alert('Error: ' + data.message);
        });
    }
}

function confirmAction(url, message, btnObj = null) { 
    if (confirm(message)) { 
        if (btnObj) { btnObj.innerHTML = '<span class="spinner-border spinner-border-sm"></span> รอสักครู่...'; btnObj.classList.add('disabled'); }
        window.location.href = url; 
    } 
}

function openHolidayInfoModal(dateStr, isHoliday, holidayName) {
    if (!holidayInfoModal) holidayInfoModal = new bootstrap.Modal(document.getElementById('holidayInfoModal'));
    selectedHolidayDate = dateStr;
    const p = dateStr.split('-');
    const dObj = new Date(p[0], parseInt(p[1])-1, p[2]);
    const mName = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'][dObj.getMonth()];
    document.getElementById('hiModalDate').innerText = `วัน${['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'][dObj.getDay()]} ที่ ${dObj.getDate()} เดือน ${mName} ปี ${dObj.getFullYear()+543}`;
    
    if (isHoliday || dObj.getDay() === 0 || dObj.getDay() === 6) {
        document.getElementById('hiExistingBlock').style.display = 'block'; document.getElementById('hiRequestBlock').style.display = 'none';
        document.getElementById('hiName').innerText = (dObj.getDay()===0||dObj.getDay()===6) ? (isHoliday ? holidayName+"\n(และเสาร์-อาทิตย์)" : "เสาร์-อาทิตย์") : holidayName;
    } else {
        document.getElementById('hiExistingBlock').style.display = 'none'; document.getElementById('hiRequestBlock').style.display = 'block'; document.getElementById('hiRequestName').value = '';
    }
    holidayInfoModal.show();
}

function submitHolidayRequest() {
    const hName = document.getElementById('hiRequestName').value.trim();
    if (!hName) return alert('กรุณาระบุชื่อวันหยุด');
    fetch('index.php?c=ajax&a=request_holiday', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ date: selectedHolidayDate, name: hName }) })
    .then(r => r.json()).then(d => {
        if (d.status === 'success') { alert('ส่งคำขอสำเร็จ!'); holidayInfoModal.hide(); } else alert('Error: ' + d.message);
    });
}

// 🌟 ฟังก์ชันส่งออกตารางเป็น Excel
function exportTableToExcelClean(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel;charset=utf-8';
    var tableSelect = document.getElementById(tableID);
    
    var tableClone = tableSelect.cloneNode(true);
    
    var unwantedElements = tableClone.querySelectorAll('.drag-handle, .btn-remove-staff, .save-indicator, .bi');
    unwantedElements.forEach(el => el.remove());
    
    var buttons = tableClone.querySelectorAll('button');
    buttons.forEach(btn => {
        var parent = btn.parentNode;
        parent.innerHTML = btn.innerText.trim();
    });

    var cells = tableClone.querySelectorAll('td, th');
    cells.forEach(function(cell) {
        var text = cell.innerText || cell.textContent;
        cell.innerHTML = text.trim();
        cell.style.border = "1px solid black";
        cell.style.verticalAlign = "middle";
        cell.style.textAlign = "center";
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