<?php
// ที่อยู่ไฟล์: views/roster/index.php

// 🌟 ส่วนที่ 1: จัดการตัวแปรพื้นฐานและฟังก์ชันคำนวณ
$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$selected_month = trim((string)($selected_month ?? date('Y-m')));
if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $selected_month)) {
    $selected_month = date('Y-m');
}
$exp = explode('-', $selected_month);
$year = $exp[0];
$month = $exp[1];
$display_month_text = $thai_months[(int)$month] . ' ' . ($year + 543);
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$isAdmin = in_array($_SESSION['user']['role'] ?? '', ['ADMIN', 'SUPERADMIN']);
$is_manager = in_array($_SESSION['user']['role'] ?? '', ['DIRECTOR', 'SCHEDULER', 'ADMIN', 'SUPERADMIN']);
$roster_status = strtoupper((string)($roster_status ?? 'DRAFT'));
$canEdit = ($is_manager && $roster_status === 'DRAFT' && (int)($hospital_id ?? 0) > 0);

// Workflow state is derived once here so the view never emits Undefined variable warnings.
$roster_workflow_map = [
    'DRAFT'        => ['step' => 1, 'progress' => 0,      'label' => 'กำลังจัดทำ'],
    'SUBMITTED'    => ['step' => 2, 'progress' => 33.333, 'label' => 'รอตรวจสอบ'],
    'REQUEST_EDIT' => ['step' => 2, 'progress' => 33.333, 'label' => 'ขอแก้ไข'],
    'APPROVED'     => ['step' => 3, 'progress' => 66.667, 'label' => 'อนุมัติแล้ว'],
    'LOCKED'       => ['step' => 3, 'progress' => 66.667, 'label' => 'ยืนยันแล้ว'],
];

$roster_workflow_state = $roster_workflow_map[$roster_status] ?? $roster_workflow_map['DRAFT'];
$roster_progress_step = (int)$roster_workflow_state['step'];
$roster_progress_percent = (float)$roster_workflow_state['progress'];
$roster_workflow_label = (string)$roster_workflow_state['label'];

function getShiftColorClass($shift_val) {
    $shift_val = trim((string)$shift_val);
    if (strpos($shift_val, '/') !== false) return 'text-primary';
    if ($shift_val == 'บ' || $shift_val == 'A') return 'text-warning text-dark';
    if ($shift_val == 'ร' || $shift_val == 'N') return 'text-success';
    if ($shift_val == 'ย' || $shift_val == 'O') return 'text-danger';
    if ($shift_val == 'ช' || $shift_val == 'M') return 'text-info';
    return 'text-dark';
}

function getBsColor($color_theme) {
    if ($color_theme == 'green') return 'success';
    if ($color_theme == 'purple') return 'danger';
    if ($color_theme == 'gray') return 'secondary';
    if ($color_theme == 'blue') return 'info';
    return 'primary';
}

// 🌟 ฟังก์ชัน: ให้ดึงเรทค่าตอบแทนจาก pay_rate_id 
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

// 🌟 ดึงข้อมูลวันหยุดล่วงหน้า เพื่อลดการคิวรี่ซ้ำซ้อนในลูป
$holiday_cache = [];
for ($i = 1; $i <= $days_in_month; $i++) {
    $d_str = "$year-$month-" . str_pad($i, 2, '0', STR_PAD_LEFT);
    $holiday_cache[$i] = isset($holidayModel) ? $holidayModel->isHoliday($d_str) : false;
}

// ============================================================================
// Roster UI V2: KPI + Coverage summary
// ============================================================================
$roster_staff_map = [];
$roster_visible_staff_ids = [];
foreach ($all_staff_for_sidebar as $staff) {
    $uid = (int)($staff['id'] ?? 0);
    if ($uid <= 0) continue;
    $roster_staff_map[$uid] = $staff;
    if ((int)($staff['hospital_id'] ?? 0) === (int)($hospital_id ?? 0)) {
        $roster_visible_staff_ids[$uid] = true;
    }
}

$roster_coverage = [];
for ($i = 1; $i <= $days_in_month; $i++) {
    $roster_coverage[$i] = ['บ' => 0, 'ร' => 0, 'ย' => 0, 'ช' => 0];
}

$roster_kpi_shift_count = 0;
$roster_kpi_estimated_pay = 0;
foreach (($shifts ?? []) as $shift) {
    $uid = (int)($shift['user_id'] ?? 0);
    $shift_date = (string)($shift['shift_date'] ?? '');
    if ($uid > 0) $roster_visible_staff_ids[$uid] = true;

    $day = (int)substr($shift_date, 8, 2);
    $parts = preg_split('/[\\/,\\s]+/', trim((string)($shift['shift_type'] ?? ''))) ?: [];
    $rates = isset($roster_staff_map[$uid]) ? calculatePayRatesPHP($roster_staff_map[$uid], $pay_rates_db ?? []) : ['ร' => 0, 'ย' => 0, 'บ' => 0];

    foreach ($parts as $part) {
        $type = ['A' => 'บ', 'N' => 'ร', 'O' => 'ย', 'M' => 'ช'][$part] ?? $part;
        if (!in_array($type, ['ช', 'บ', 'ร', 'ย'], true)) continue;
        $roster_kpi_shift_count++;
        if (isset($roster_coverage[$day][$type])) $roster_coverage[$day][$type]++;
        if (isset($rates[$type])) $roster_kpi_estimated_pay += (float)$rates[$type];
    }
}

$roster_kpi_staff_count = count($roster_visible_staff_ids);
$roster_kpi_coverage_ok_days = 0;
foreach ($roster_coverage as $coverage) {
    if (($coverage['บ'] ?? 0) >= 1 && ($coverage['ร'] ?? 0) >= 1) {
        $roster_kpi_coverage_ok_days++;
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .input-group-modern { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.2s; overflow: hidden; }
    .input-group-modern:focus-within { background-color: #ffffff; border-color: #3b82f6; box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15); }
    .input-group-modern .form-control, .input-group-modern .input-group-text { background-color: transparent; border: none; box-shadow: none; }
    
    .table-roster th { font-weight: 600; color: #475569; font-size: 13px; vertical-align: middle; }
    .table-roster td { vertical-align: middle; }
    .date-header-cell { transition: all 0.2s ease; }
    .date-header-cell:hover { background-color: #e0f2fe !important; color: #0284c7 !important; z-index: 10; box-shadow: inset 0 -2px 0 #38bdf8; }
    
    .shift-cell { font-size: 15px !important; font-weight: 800 !important; border-radius: 6px !important; transition: all 0.15s ease; background-color: transparent !important; width: 100%; height: 100%; }
    .shift-cell:hover { background-color: #f0f9ff !important; z-index: 5; box-shadow: inset 0 0 0 2px rgba(14,165,233,.35); }
    
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
    .pay-cell-clickable:hover { background-color: #dcfce7 !important; box-shadow: inset 0 0 0 2px rgba(34,197,94,.22); }

    .today-column { background-color: #f0fdf4 !important; border-left: 1px solid #bbf7d0 !important; border-right: 1px solid #bbf7d0 !important; }
    .holiday-column { background-color: #fff1f2 !important; } /* 🌟 พื้นหลังสีแดงอ่อนๆ สำหรับวันหยุด */

    @media (min-width: 992px) { .sticky-sidebar { position: sticky; top: 15px; align-self: flex-start; height: calc(100dvh - var(--rp-header-h, 4.5rem) - 2rem); overflow: hidden; } }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }

    .rp-roster-table-wrap { scroll-behavior: smooth; overscroll-behavior: contain; }
    .rp-roster-table-wrap td, .rp-roster-table-wrap th { white-space: nowrap; }
    .rp-roster-footer-actions .btn { min-height: 32px; }
    .rp-roster-panel-hidden { display: none !important; }
    .rp-roster-main-expanded { flex: 0 0 100% !important; max-width: 100% !important; width: 100% !important; }

    .rp-roster-kpi { border: 1px solid #e5edf3; border-radius: 1rem; background: #fff; box-shadow: 0 3px 14px rgba(15,23,42,.035); }
    .rp-roster-kpi-icon { width: 2.45rem; height: 2.45rem; display: grid; place-items: center; border-radius: .8rem; background: #f1f7fb; color: #0f6cbd; font-size: 1.05rem; }
    .rp-roster-kpi-value { color: #183247; font-size: 1.28rem; font-weight: 800; line-height: 1.05; }
    .rp-roster-kpi-label { color: #7a909f; font-size: .72rem; font-weight: 700; }
    .rp-roster-kpi-sub { color: #9aabb6; font-size: .64rem; margin-top: .12rem; }

    .rp-roster-tools { position: sticky; top: 0; z-index: 12; background: rgba(255,255,255,.96); backdrop-filter: blur(8px); }
    .rp-roster-filter-btn.active { color: #fff !important; background: #0f6cbd !important; border-color: #0f6cbd !important; }
    .rp-paint-btn.active { color: #fff !important; background: #172033 !important; border-color: #172033 !important; box-shadow: 0 0 0 .18rem rgba(23,32,51,.10); }
    .rp-paint-indicator { display: none; align-items: center; gap: .35rem; min-height: 1.8rem; padding: .25rem .55rem; border-radius: 999px; background: #eef7ff; color: #0f6cbd; font-size: .7rem; font-weight: 800; }
    .rp-paint-indicator.is-active { display: inline-flex; }
    .shift-cell.rp-paint-ready { cursor: crosshair !important; }

    .rp-coverage-mini { display: flex; justify-content: center; gap: 2px; margin-top: 3px; font-size: 7px; line-height: 1; font-weight: 800; }
    .rp-coverage-mini span { display: inline-flex; min-width: 16px; justify-content: center; padding: 2px 2px; border-radius: 4px; }
    .rp-coverage-mini.is-ok span { color: #15803d; background: #dcfce7; }
    .rp-coverage-mini.is-gap span { color: #b91c1c; background: #fee2e2; }
    .rp-coverage-mini.is-gap span.is-covered { color: #15803d; background: #dcfce7; }

    .roster-staff-row.rp-filter-hidden { display: none !important; }

    @media (max-width: 767.98px) {
        .table-roster th { font-size: 11px; }
        .shift-cell { font-size: 13px !important; }
        .rp-roster-table-wrap { max-height: 66vh !important; }
    }
</style>

<div class="w-100 bg-light p-3 p-md-4 min-vh-100 d-flex flex-column">
    <div class="container-fluid mx-auto flex-grow-1 d-flex flex-column">
        
        <!-- 🌟 Header & Controls -->
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
                    <div class="dropdown shadow-sm" style="width: 12rem;">
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

        <!-- 🌟 แจ้งเตือนข้อผิดพลาด/ความสำเร็จ -->
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <strong>สำเร็จ!</strong> <?= htmlspecialchars((string)$_SESSION['success_msg'], ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>ข้อผิดพลาด!</strong> <?= htmlspecialchars((string)$_SESSION['error_msg'], ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <!-- Roster UI V2: KPI Overview -->
        <div class="row g-2 g-md-3 mb-3" id="rosterKpiGrid">
            <div class="col-6 col-xl">
                <div class="rp-roster-kpi h-100 p-3 d-flex align-items-center gap-3">
                    <div class="rp-roster-kpi-icon"><i class="bi bi-people-fill"></i></div>
                    <div class="min-w-0">
                        <div class="rp-roster-kpi-value" id="kpiStaffCount"><?= number_format($roster_kpi_staff_count) ?></div>
                        <div class="rp-roster-kpi-label">บุคลากรในตาราง</div>
                        <div class="rp-roster-kpi-sub">รวมผู้ช่วยราชการที่มีเวร</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl">
                <div class="rp-roster-kpi h-100 p-3 d-flex align-items-center gap-3">
                    <div class="rp-roster-kpi-icon"><i class="bi bi-calendar2-check-fill"></i></div>
                    <div>
                        <div class="rp-roster-kpi-value" id="kpiShiftCount"><?= number_format($roster_kpi_shift_count) ?></div>
                        <div class="rp-roster-kpi-label">กะที่จัดแล้ว</div>
                        <div class="rp-roster-kpi-sub">นับกะควบแยกตามประเภท</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl">
                <div class="rp-roster-kpi h-100 p-3 d-flex align-items-center gap-3">
                    <div class="rp-roster-kpi-icon"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <div class="rp-roster-kpi-value"><span id="kpiCoverageDays"><?= number_format($roster_kpi_coverage_ok_days) ?></span><span class="fs-6 text-muted">/<?= $days_in_month ?></span></div>
                        <div class="rp-roster-kpi-label">วันครอบคลุม บ + ร</div>
                        <div class="rp-roster-kpi-sub">อย่างน้อยกะละ 1 คน</div>
                    </div>
                </div>
            </div>
            <?php if (($_SESSION['user']['role'] ?? '') !== 'STAFF'): ?>
            <div class="col-6 col-xl">
                <div class="rp-roster-kpi h-100 p-3 d-flex align-items-center gap-3">
                    <div class="rp-roster-kpi-icon"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="rp-roster-kpi-value" id="kpiEstimatedPay"><?= number_format($roster_kpi_estimated_pay, 0) ?></div>
                        <div class="rp-roster-kpi-label">ค่าตอบแทนประมาณการ</div>
                        <div class="rp-roster-kpi-sub">บาท · ตามเรทปัจจุบัน</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-12 col-sm-6 col-xl">
                <div class="rp-roster-kpi h-100 p-3 d-flex align-items-center gap-3">
                    <div class="rp-roster-kpi-icon"><i class="bi bi-activity"></i></div>
                    <div>
                        <div class="rp-roster-kpi-value"><span id="kpiErrorCount">–</span><span class="fs-6 text-danger"> / </span><span id="kpiWarningCount">–</span></div>
                        <div class="rp-roster-kpi-label">Error / Warning</div>
                        <div class="rp-roster-kpi-sub" id="kpiValidationState">กำลังตรวจสอบอัตโนมัติ</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🌟 แถบสถานะตารางเวร และ ปุ่มดำเนินการ Workflow -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 <?= in_array($roster_status, ['APPROVED', 'LOCKED'], true) ? 'bg-success bg-opacity-10 border-success' : ($roster_status == 'SUBMITTED' ? 'bg-info bg-opacity-10' : 'bg-warning bg-opacity-10') ?>" style="border-left: 4px solid !important;">
            <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div>
                        <?php if ($roster_status == 'APPROVED'): ?>
                            <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i> <strong class="text-success">สถานะ: อนุมัติแล้ว</strong> <span class="text-dark opacity-75">ตารางเวรเดือนนี้ได้รับการยืนยันความถูกต้องแล้ว</span>
                        <?php elseif ($roster_status == 'LOCKED'): ?>
                            <i class="bi bi-lock-fill fs-5 me-2 text-success"></i> <strong class="text-success">สถานะ: ล็อกตารางแล้ว</strong> <span class="text-dark opacity-75">ตารางเวรนี้ถูกยืนยันและไม่สามารถแก้ไขได้</span>
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
                            <?= security_csrf_input() ?>
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
                            <?= security_csrf_input() ?>
                            <input type="hidden" name="month_year" value="<?= $selected_month ?>">
                            <button type="submit" name="status" value="DRAFT" class="btn btn-sm btn-outline-danger fw-bold bg-white text-nowrap rounded-3" onclick="return confirm('ยืนยันการตีกลับ?');"><i class="bi bi-arrow-return-left me-1"></i> ตีกลับ</button>
                            <button type="submit" name="status" value="APPROVED" class="btn btn-sm btn-success fw-bold shadow-sm text-nowrap rounded-3" onclick="return confirm('อนุมัติตารางเวร?');"><i class="bi bi-check-circle-fill me-1"></i> อนุมัติเวร</button>
                        </form>
                    <?php endif; ?>

                    <!-- ผู้จัดเวร / ผอ. ขอแก้ไขตารางที่อนุมัติแล้ว -->
                    <?php if ($roster_status == 'APPROVED' && ($_SESSION['user']['role'] == 'SCHEDULER' || $_SESSION['user']['role'] == 'DIRECTOR')): ?>
                        <form action="index.php?c=ajax&a=request_edit" method="POST" class="m-0" onsubmit="return confirm('ส่งคำขอปลดล็อคตารางเวร?');">
                            <?= security_csrf_input() ?>
                            <input type="hidden" name="month_year" value="<?= $selected_month ?>">
                            <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold shadow-sm text-nowrap rounded-3"><i class="bi bi-unlock-fill me-1"></i> ขอแก้ไขตาราง</button>
                        </form>
                    <?php endif; ?>

                    <!-- กำลังจัดทำ (Draft) -->
                    <?php if ($roster_status == 'DRAFT' && ($_SESSION['user']['role'] == 'SCHEDULER' || $_SESSION['user']['role'] == 'DIRECTOR')): ?>
                        
                        <!-- 🌟 ปุ่มจัดเวรอัตโนมัติ -->
                        <button onclick="autoScheduleRoster()" class="btn btn-sm btn-outline-info fw-bold shadow-sm bg-white text-nowrap rounded-3" title="สุ่มรายชื่อบุคลากรทุกคน ครอบคลุมทุกตำแหน่งลงในตารางเวร">
                            <i class="bi bi-robot me-1"></i> จัดการเวรอัตโนมัติ
                        </button>
                        
                        <!-- 🌟 ปุ่มตรวจสอบตารางเวร -->
                        <button onclick="validateRoster()" class="btn btn-sm btn-outline-warning text-dark fw-bold shadow-sm bg-white text-nowrap rounded-3" title="ตรวจสอบวันว่าง / วันที่มีแต่ผู้ช่วย">
                            <i class="bi bi-shield-exclamation me-1"></i> ตรวจสอบตาราง
                        </button>

                        <button onclick="checkFatigueRules()" class="btn btn-sm btn-outline-danger fw-bold shadow-sm bg-white text-nowrap rounded-3" title="ตรวจสอบเวรชน / พักผ่อนไม่พอ">
                            <i class="bi bi-heart-pulse me-1"></i> เช็คความล้า
                        </button>

                        <button onclick="copyPreviousMonth('<?= $selected_month ?>')" class="btn btn-sm btn-outline-success fw-bold shadow-sm bg-white text-nowrap rounded-3">
                            <i class="bi bi-copy me-1"></i> คัดลอกเดือนก่อน
                        </button>
                        
                        <form action="index.php?c=roster&a=clear_roster" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการล้างตารางเวรทั้งหมดของเดือนนี้?');">
                            <?= security_csrf_input() ?>
                            <input type="hidden" name="month" value="<?= htmlspecialchars($selected_month, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary fw-bold shadow-sm bg-white text-nowrap rounded-3">
                            <i class="bi bi-eraser-fill me-1"></i> ล้างข้อมูล
                        </button>
                        </form>

                        <div class="vr mx-1"></div>

                        <!-- 🌟 เปลี่ยนให้เรียกใช้ submitForApproval(event, this) แทน confirm ธรรมดา -->
                        <form action="index.php?c=ajax&a=change_status" method="POST" class="m-0" onsubmit="submitForApproval(event, this);">
                            <?= security_csrf_input() ?>
                            <input type="hidden" name="month_year" value="<?= $selected_month ?>">
                            <input type="hidden" name="status" value="SUBMITTED">
                            <button type="submit" class="btn btn-sm btn-dark fw-bold shadow-sm px-4 text-nowrap rounded-3"><i class="bi bi-send-fill me-1"></i> ส่งอนุมัติ</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="rp-workflow-card mx-3 mb-3 rp-roster-workflow">
                <div class="rp-workflow-head">
                    <div class="rp-workflow-heading">
                        <span class="rp-workflow-heading-icon" aria-hidden="true">
                            <i class="bi bi-diagram-3-fill"></i>
                        </span>
                        <div>
                            <div class="rp-workflow-title">ขั้นตอนการจัดตารางเวร</div>
                            <div class="rp-workflow-subtitle">ติดตามสถานะการจัดทำ ตรวจสอบ และอนุมัติตารางเวร</div>
                        </div>
                    </div>
                    <span class="rp-workflow-state <?= $roster_status === 'REQUEST_EDIT' ? 'is-warning' : ($roster_status === 'APPROVED' ? 'is-success' : '') ?>">
                        <i class="bi <?= $roster_status === 'APPROVED' ? 'bi-check-circle-fill' : ($roster_status === 'REQUEST_EDIT' ? 'bi-exclamation-circle-fill' : 'bi-clock-history') ?>"></i>
                        <?= htmlspecialchars($roster_workflow_label, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="rp-workflow <?= $roster_status === 'REQUEST_EDIT' ? 'rp-workflow--warning' : '' ?> <?= $roster_status === 'APPROVED' ? 'rp-workflow--approved' : '' ?>"
                     style="--rp-workflow-count:3; --rp-workflow-progress:<?= htmlspecialchars((string)$roster_progress_percent, ENT_QUOTES, 'UTF-8') ?>%;"
                     role="list"
                     aria-label="ขั้นตอนการจัดตารางเวร">
                    <?php
                    $roster_steps = [
                        1 => ['icon' => 'bi-calendar2-week-fill', 'label' => 'จัดทำตาราง', 'desc' => 'บันทึกและปรับเวร'],
                        2 => ['icon' => 'bi-send-check-fill', 'label' => $roster_status === 'REQUEST_EDIT' ? 'ขอแก้ไข' : 'ส่งตรวจสอบ', 'desc' => $roster_status === 'REQUEST_EDIT' ? 'รออนุญาตให้แก้ไข' : 'ส่งให้ผู้ตรวจสอบ'],
                        3 => ['icon' => 'bi-patch-check-fill', 'label' => 'อนุมัติ', 'desc' => 'ยืนยันตารางพร้อมใช้']
                    ];
                    foreach ($roster_steps as $stepNo => $stepData):
                        $stepClass = '';
                        if ($stepNo < $roster_progress_step) $stepClass = 'is-complete';
                        elseif ($stepNo === $roster_progress_step) $stepClass = 'is-current';
                    ?>
                        <div class="rp-workflow-step <?= $stepClass ?>" role="listitem">
                            <div class="rp-workflow-dot" aria-hidden="true"><i class="bi <?= $stepData['icon'] ?>"></i></div>
                            <div class="rp-workflow-label"><?= htmlspecialchars($stepData['label'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="rp-workflow-desc"><?= htmlspecialchars($stepData['desc'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 🌟 แสดงผลลัพธ์การตรวจสอบตารางเวรทั่วไป -->
            <div id="rosterWarnings" class="px-3 pb-3" style="display: none;"></div>
        </div>

        <div class="row g-3 flex-grow-1">
            <!-- 🌟 ตารางเวรหลัก -->
            <div class="col-xl-9 col-lg-8 d-flex flex-column" id="rosterMainColumn" style="transition: all 0.3s ease;">
                <div class="card card-modern overflow-hidden mb-4 flex-grow-1">
                    <div class="card-body p-0 d-flex flex-column">
                        <div class="rp-roster-tools border-bottom p-2 p-md-3">
                            <div class="d-flex flex-column flex-xxl-row justify-content-between gap-2">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="small fw-bold text-muted me-1"><i class="bi bi-funnel me-1"></i>กรอง:</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill rp-roster-filter-btn active" data-roster-filter="all">ทั้งหมด</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill rp-roster-filter-btn" data-roster-filter="empty"><i class="bi bi-calendar-x me-1"></i>ยังไม่มีเวร</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill rp-roster-filter-btn" data-roster-filter="night"><i class="bi bi-moon-stars me-1"></i>มีเวรดึก</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill rp-roster-filter-btn" data-roster-filter="leave"><i class="bi bi-calendar2-minus me-1"></i>มีวันลา</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill rp-roster-filter-btn" data-roster-filter="external"><i class="bi bi-arrow-left-right me-1"></i>ช่วยราชการ</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill rp-roster-filter-btn" data-roster-filter="fatigue"><i class="bi bi-heart-pulse me-1"></i>จุดเสี่ยง</button>
                                </div>

                                <?php if ($canEdit): ?>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="small fw-bold text-muted me-1"><i class="bi bi-brush-fill me-1"></i>Quick Paint:</span>
                                    <button type="button" class="btn btn-sm btn-outline-warning fw-bold rp-paint-btn" data-paint-shift="บ" data-paint-class="text-warning text-dark">บ</button>
                                    <button type="button" class="btn btn-sm btn-outline-success fw-bold rp-paint-btn" data-paint-shift="ร" data-paint-class="text-success">ร</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger fw-bold rp-paint-btn" data-paint-shift="ย" data-paint-class="text-danger">ย</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold rp-paint-btn" data-paint-shift="บ/ร" data-paint-class="text-primary">บ/ร</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rp-paint-btn" data-paint-shift="" data-paint-class="text-dark"><i class="bi bi-eraser-fill"></i></button>
                                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" id="btnPaintOff">ปิดโหมด</button>
                                    <span class="rp-paint-indicator" id="paintModeIndicator"><i class="bi bi-brush-fill"></i><span>โหมดระบายเวร</span></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="table-responsive flex-grow-1 custom-scrollbar rp-roster-table-wrap" id="rosterTableScroll" style="max-height: 70vh;">
                            <table class="table table-bordered table-hover table-roster mb-0 text-center" id="rosterTable" style="min-width: 56rem;">
                                <thead class="sticky-top" style="z-index: 10;">
                                    <tr>
                                        <th rowspan="2" class="align-middle shadow-sm bg-white" style="min-width: 12rem; left: 0; position: sticky; z-index: 11; border-right: 2px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">รายชื่อเจ้าหน้าที่</th>
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
                                            $holidayName = $holiday_cache[$i];
                                            $is_holiday_flag = $holidayName ? 'true' : 'false';
                                            $h_name = $holidayName ? htmlspecialchars($holidayName, ENT_QUOTES) : '';
                                        ?>
                                            <th class="<?= $is_current_day ? 'bg-primary text-white shadow-sm' : ($is_weekend || $holidayName ? 'text-danger bg-light' : 'bg-light') ?> date-header-cell border-bottom" 
                                                data-roster-date="<?= $current_date_str ?>"
                                                style="min-width: 42px; cursor: pointer; position: relative;"
                                                onclick="openHolidayInfoModal('<?= $current_date_str ?>', <?= $is_holiday_flag ?>, '<?= $h_name ?>')"
                                                title="<?= $holidayName ? 'วันหยุด: '.$holidayName : 'คลิกเพื่อเสนอวันหยุด' ?>">
                                                <?= $i ?>
                                                <?php
                                                    $coverage = $roster_coverage[$i] ?? ['บ' => 0, 'ร' => 0];
                                                    $coverage_b = (int)($coverage['บ'] ?? 0);
                                                    $coverage_r = (int)($coverage['ร'] ?? 0);
                                                    $coverage_ok = ($coverage_b >= 1 && $coverage_r >= 1);
                                                ?>
                                                <div class="rp-coverage-mini <?= $coverage_ok ? 'is-ok' : 'is-gap' ?>" data-coverage-date="<?= $current_date_str ?>" title="ความครอบคลุม: บ <?= $coverage_b ?> คน / ร <?= $coverage_r ?> คน">
                                                    <span class="<?= $coverage_b >= 1 ? 'is-covered' : '' ?>">บ<?= $coverage_b ?></span>
                                                    <span class="<?= $coverage_r >= 1 ? 'is-covered' : '' ?>">ร<?= $coverage_r ?></span>
                                                </div>
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
                                        <tr class="roster-staff-row" id="row-staff-<?= htmlspecialchars($staff['id']) ?>" data-id="<?= htmlspecialchars($staff['id']) ?>" data-is-external="<?= $is_external ? 'true' : 'false' ?>" style="<?= $is_visible ? '' : 'display: none;' ?>">
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
                                                
                                                // ไฮไลท์คอลัมน์วันนี้ และ วันหยุด
                                                $is_current_day = ($full_date == date('Y-m-d'));
                                                $day_of_week = date('N', strtotime($full_date));
                                                $is_weekend_or_holiday = ($day_of_week == 6 || $day_of_week == 7 || $holiday_cache[$i]);
                                                
                                                $td_bg_class = '';
                                                if ($is_current_day) {
                                                    $td_bg_class = 'today-column';
                                                } elseif ($is_weekend_or_holiday) {
                                                    $td_bg_class = 'holiday-column';
                                                }
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
                    
                    <div class="card-footer bg-white border-top p-3 text-muted d-flex flex-wrap justify-content-between align-items-center gap-2" style="font-size: 12px;">
                        <div>
                            <i class="bi bi-info-circle text-primary me-1"></i>
                            <strong>สัญลักษณ์:</strong>
                            <span class="fw-bold text-warning text-dark mx-1">บ</span> = บ่าย,
                            <span class="fw-bold text-success mx-1">ร</span> = ดึก,
                            <span class="fw-bold text-danger mx-1">ย</span> = ปฏิบัติงานวันหยุด,
                            <span class="fw-bold text-primary mx-1">บ/ร</span> = กะควบ
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 rp-roster-footer-actions">
                            <?php if ($selected_month === date('Y-m')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="btnRosterToday">
                                <i class="bi bi-crosshair2 me-1"></i> ไปวันนี้
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-none d-lg-inline-flex align-items-center" id="btnToggleRosterStaff">
                                <i class="bi bi-layout-sidebar-inset-reverse me-1"></i>
                                <span>ซ่อนแถบรายชื่อ</span>
                            </button>
                            <span class="text-primary fw-bold d-none d-md-inline">
                                <i class="bi bi-mouse2 me-1"></i> ดับเบิลคลิกเพื่อลบเวร
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🌟 แถบรายชื่อบุคลากร (Sidebar) -->
            <div class="col-xl-3 col-lg-4 sticky-sidebar" id="rosterStaffColumn">
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
                             data-username="<?= htmlspecialchars($staff['name']) ?>"
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
                                            $raw_types = preg_split('/[\\/,\\s]+/', trim((string)$s['shift_type'])) ?: [];
                                            foreach ($raw_types as $raw_type) {
                                                $type = ['A' => 'บ', 'N' => 'ร', 'O' => 'ย', 'M' => 'ช'][$raw_type] ?? $raw_type;
                                                if ($type === 'ร') $sum_r++;
                                                elseif ($type === 'ย') $sum_y++;
                                                elseif ($type === 'บ') $sum_b++;
                                            }
                                        }
                                    }
                                }
                                $totalShift = $sum_r + $sum_y + $sum_b;

                                $pay = 0;
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

// 💡 ใช้ JavaScript ล้วนคำนวณหาปี-เดือนปัจจุบัน ป้องกัน Syntax Error ของเบราว์เซอร์
const _d = new Date();
const _defaultMonth = _d.getFullYear() + '-' + String(_d.getMonth() + 1).padStart(2, '0');
const currentMonthYear = new URLSearchParams(window.location.search).get('month') || _defaultMonth;
const targetHospId = '<?= htmlspecialchars($hospital_id ?? $_SESSION['user']['hospital_id'] ?? '') ?>';

const rosterCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function rosterApiFetch(url, options = {}) {
    const requestOptions = { ...options };
    const method = String(requestOptions.method || 'GET').toUpperCase();
    const headers = new Headers(requestOptions.headers || {});

    if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && rosterCsrfToken) {
        headers.set('X-CSRF-Token', rosterCsrfToken);
    }

    requestOptions.headers = headers;
    return window.fetch(url, requestOptions);
}

let currentCellBtn = null;
let shiftModal = null; 
let payCalcModal = null; 
let holidayInfoModal = null;
let selectedHolidayDate = '';
let paintShiftValue = null;
let paintColorClass = 'text-dark';
let validationKpiTimer = null;

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

    // Roster row quick filters
    document.querySelectorAll('.roster-staff-row').forEach(row => {
        row.dataset.rosterBaseVisible = row.style.display === 'none' ? 'false' : 'true';
    });

    document.querySelectorAll('.rp-roster-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.rp-roster-filter-btn').forEach(item => item.classList.remove('active'));
            btn.classList.add('active');
            applyRosterRowFilter(btn.dataset.rosterFilter || 'all');
        });
    });

    // Quick Paint: click a shift once, then click cells to fill quickly.
    document.querySelectorAll('.rp-paint-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            setPaintMode(btn.dataset.paintShift ?? '', btn.dataset.paintClass || 'text-dark', btn);
        });
    });
    document.getElementById('btnPaintOff')?.addEventListener('click', () => setPaintMode(null, 'text-dark', null));

    refreshRosterKpis();
    refreshValidationKpis();

    const btnRosterToday = document.getElementById('btnRosterToday');
    const rosterTableScroll = document.getElementById('rosterTableScroll');
    if (btnRosterToday && rosterTableScroll) {
        btnRosterToday.addEventListener('click', () => {
            const today = new Date();
            const todayKey = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
            const target = document.querySelector(`[data-roster-date="${todayKey}"]`);
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        });
    }

    const btnToggleRosterStaff = document.getElementById('btnToggleRosterStaff');
    const rosterMainColumn = document.getElementById('rosterMainColumn');
    const rosterStaffColumn = document.getElementById('rosterStaffColumn');
    if (btnToggleRosterStaff && rosterMainColumn && rosterStaffColumn) {
        btnToggleRosterStaff.addEventListener('click', () => {
            const hidden = rosterStaffColumn.classList.toggle('rp-roster-panel-hidden');
            rosterMainColumn.classList.toggle('rp-roster-main-expanded', hidden);
            const label = btnToggleRosterStaff.querySelector('span');
            const icon = btnToggleRosterStaff.querySelector('i');
            if (label) label.textContent = hidden ? 'แสดงแถบรายชื่อ' : 'ซ่อนแถบรายชื่อ';
            if (icon) {
                icon.classList.toggle('bi-layout-sidebar-inset-reverse', !hidden);
                icon.classList.toggle('bi-layout-sidebar-inset', hidden);
            }
        });
    }

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

                rosterApiFetch('index.php?c=ajax&a=update_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        order: orderData,
                        month_year: currentMonthYear,
                        hosp_id: targetHospId
                    })
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
            staffRow.dataset.rosterBaseVisible = 'true';
            const summaryRow = document.getElementById('summary-row-' + userId);
            if (summaryRow) summaryRow.style.display = '';
            showToast('success', 'เพิ่มบุคลากรลงในตารางเวรแล้ว (จัดเวรได้เลย)');
            staffRow.classList.add('bg-success', 'bg-opacity-10');
            setTimeout(() => staffRow.classList.remove('bg-success', 'bg-opacity-10'), 2000);
            refreshRosterKpis();
        } else {
            showToast('warning', 'บุคลากรท่านนี้มีรายชื่ออยู่ในตารางเวรอยู่แล้ว');
        }
    }
}

// ==========================================
// Roster UI V2 helpers
// ==========================================
function parseRosterShiftTypes(value) {
    const aliases = { A: 'บ', N: 'ร', O: 'ย', M: 'ช' };
    return String(value || '').trim().split(/[\\/,\\s]+/).filter(Boolean).map(v => aliases[v] || v);
}

function getRosterShiftColorClass(value) {
    const types = parseRosterShiftTypes(value);
    if (types.length > 1) return 'text-primary';
    if (types.includes('บ')) return 'text-warning text-dark';
    if (types.includes('ร')) return 'text-success';
    if (types.includes('ย')) return 'text-danger';
    if (types.includes('ช')) return 'text-info';
    return 'text-dark';
}

function setPaintMode(value, colorClass = 'text-dark', sourceButton = null) {
    paintShiftValue = value;
    paintColorClass = colorClass;

    document.querySelectorAll('.rp-paint-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.shift-cell[data-date]').forEach(cell => cell.classList.toggle('rp-paint-ready', value !== null));

    if (sourceButton && value !== null) sourceButton.classList.add('active');

    const indicator = document.getElementById('paintModeIndicator');
    if (indicator) {
        indicator.classList.toggle('is-active', value !== null);
        const textEl = indicator.querySelector('span');
        if (textEl) textEl.textContent = value === null ? 'โหมดระบายเวร' : (value === '' ? 'โหมดลบเวร' : `ระบายเวร: ${value}`);
    }
}

function applyRosterRowFilter(filter) {
    document.querySelectorAll('.roster-staff-row').forEach(row => {
        if (row.dataset.rosterBaseVisible === 'false') {
            row.classList.add('rp-filter-hidden');
            return;
        }

        const cells = Array.from(row.querySelectorAll('.shift-cell[data-date]'));
        const shiftTypes = cells.flatMap(cell => parseRosterShiftTypes(cell.innerText));
        let match = true;

        if (filter === 'empty') match = shiftTypes.length === 0;
        else if (filter === 'night') match = shiftTypes.includes('ร');
        else if (filter === 'leave') match = !!row.querySelector('.leave-badge-cell');
        else if (filter === 'external') match = row.dataset.isExternal === 'true';
        else if (filter === 'fatigue') match = !!row.querySelector('.fatigue-warn');

        row.classList.toggle('rp-filter-hidden', !match);
    });
}

function updateCoverageForDate(dateStr) {
    const coverageEl = document.querySelector(`.rp-coverage-mini[data-coverage-date="${dateStr}"]`);
    if (!coverageEl) return;

    let b = 0;
    let r = 0;
    document.querySelectorAll(`.shift-cell[data-date="${dateStr}"]`).forEach(cell => {
        const types = parseRosterShiftTypes(cell.innerText);
        if (types.includes('บ')) b++;
        if (types.includes('ร')) r++;
    });

    const spans = coverageEl.querySelectorAll('span');
    if (spans[0]) {
        spans[0].textContent = `บ${b}`;
        spans[0].classList.toggle('is-covered', b >= 1);
    }
    if (spans[1]) {
        spans[1].textContent = `ร${r}`;
        spans[1].classList.toggle('is-covered', r >= 1);
    }

    const ok = b >= 1 && r >= 1;
    coverageEl.classList.toggle('is-ok', ok);
    coverageEl.classList.toggle('is-gap', !ok);
    coverageEl.title = `ความครอบคลุม: บ ${b} คน / ร ${r} คน`;

    const coverageKpi = document.getElementById('kpiCoverageDays');
    if (coverageKpi) coverageKpi.textContent = document.querySelectorAll('.rp-coverage-mini.is-ok').length.toLocaleString();
}

function refreshRosterKpis() {
    const baseRows = Array.from(document.querySelectorAll('.roster-staff-row')).filter(row => row.dataset.rosterBaseVisible !== 'false' && row.style.display !== 'none');
    const staffKpi = document.getElementById('kpiStaffCount');
    if (staffKpi) staffKpi.textContent = baseRows.length.toLocaleString();

    let shifts = 0;
    baseRows.forEach(row => {
        row.querySelectorAll('.shift-cell[data-date]').forEach(cell => {
            shifts += parseRosterShiftTypes(cell.innerText).filter(type => ['ช', 'บ', 'ร', 'ย'].includes(type)).length;
        });
    });
    const shiftKpi = document.getElementById('kpiShiftCount');
    if (shiftKpi) shiftKpi.textContent = shifts.toLocaleString();

    const payKpi = document.getElementById('kpiEstimatedPay');
    const grandPay = document.getElementById('grand-total-pay');
    if (payKpi && grandPay) payKpi.textContent = (grandPay.innerText || '0').replace(/[^0-9.-]/g, '') === '' ? '0' : Number((grandPay.innerText || '0').replace(/,/g, '')).toLocaleString();
}

function refreshValidationKpis() {
    if (!targetHospId) return;
    rosterApiFetch(`index.php?c=ajax&a=validate_roster&month=${currentMonthYear}&hosp_id=${targetHospId}`)
        .then(res => res.json())
        .then(data => {
            const err = document.getElementById('kpiErrorCount');
            const warn = document.getElementById('kpiWarningCount');
            const state = document.getElementById('kpiValidationState');
            if (data.status !== 'success') {
                if (state) state.textContent = 'ตรวจสอบไม่สำเร็จ';
                return;
            }
            const errors = Array.isArray(data.errors) ? data.errors.length : (data.has_error ? (data.warnings?.length || 0) : 0);
            const warnings = Array.isArray(data.advisories) ? data.advisories.length : (data.has_error ? 0 : (data.warnings?.length || 0));
            if (err) err.textContent = errors.toLocaleString();
            if (warn) warn.textContent = warnings.toLocaleString();
            if (state) state.textContent = errors > 0 ? 'มีรายการที่ต้องแก้ไข' : (warnings > 0 ? 'มีข้อควรตรวจสอบ' : 'ผ่านการตรวจสอบ');
        })
        .catch(() => {
            const state = document.getElementById('kpiValidationState');
            if (state) state.textContent = 'เชื่อมต่อตรวจสอบไม่ได้';
        });
}

function scheduleValidationKpiRefresh() {
    clearTimeout(validationKpiTimer);
    validationKpiTimer = setTimeout(refreshValidationKpis, 650);
}

// ==========================================
// 🌟 ฟังก์ชันคำนวณและ UI การจัดเวร
// ==========================================
function showPayCalculation(el) {
    if (!payCalcModal) payCalcModal = new bootstrap.Modal(document.getElementById('payCalcModal'));
    document.getElementById('calcStaffName').innerText = el.getAttribute('data-name');
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
    currentCellBtn = btn;

    if (paintShiftValue !== null) {
        saveShift(paintShiftValue, paintColorClass);
        return;
    }

    if (!shiftModal) shiftModal = new bootstrap.Modal(document.getElementById('shiftSelectorModal'));
    const dateStr = btn.getAttribute('data-date');
    const parts = dateStr.split('-');
    const thMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    document.getElementById('shiftModalDate').innerText = `วันที่ ${parseInt(parts[2], 10)} ${thMonths[parseInt(parts[1], 10)-1]} ${parseInt(parts[0])+543}`;
    shiftModal.show();
}

function saveShift(shiftValue, colorClass) {
    if (!currentCellBtn) return;
    const staffId = currentCellBtn.getAttribute('data-staff-id');
    const payRateId = currentCellBtn.getAttribute('data-staff-payrate');
    const dateStr = currentCellBtn.getAttribute('data-date');
    
    currentCellBtn.innerText = shiftValue;
    currentCellBtn.className = `btn w-100 h-100 p-0 border-0 shadow-none hover-cell shift-cell ${colorClass}`;
    if (shiftModal) shiftModal.hide();
    
    recalculateRowSummary(staffId, payRateId);
    
    const indicator = currentCellBtn.nextElementSibling;
    if (indicator) indicator.classList.remove('d-none');
    
    rosterApiFetch('index.php?c=ajax&a=save_shift', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ user_id: staffId, date: dateStr, shift_type: shiftValue, hosp_id: targetHospId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            if (typeof data.shift_type === 'string') {
                currentCellBtn.innerText = data.shift_type;
                currentCellBtn.className = `btn w-100 h-100 p-0 border-0 shadow-none hover-cell shift-cell ${getRosterShiftColorClass(data.shift_type)} ${paintShiftValue !== null ? 'rp-paint-ready' : ''}`;
            }
            if (indicator) setTimeout(() => indicator.classList.add('d-none'), 1500);
            updateCoverageForDate(dateStr);
            refreshRosterKpis();
            scheduleValidationKpiRefresh();
        } else {
            alert('Error: ' + (data.message || 'ไม่สามารถบันทึกเวรได้'));
            window.location.reload();
        }
    })
    .catch(() => {
        alert('ไม่สามารถเชื่อมต่อเพื่อบันทึกเวรได้ กรุณาลองใหม่');
        window.location.reload();
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
            promises.push(rosterApiFetch('index.php?c=ajax&a=save_shift', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ user_id: staffId, date: cell.getAttribute('data-date'), shift_type: '', hosp_id: targetHospId })
            }).then(res => res.json()));
        }
    });

    Promise.all(promises).then(results => {
        if (results.every(r => r.status === 'success') || promises.length === 0) {
            cells.forEach(cell => {
                cell.innerText = '';
                cell.className = 'btn w-100 h-100 p-0 border-0 shadow-none hover-cell shift-cell text-dark';
            });
            
            const payRateId = row.querySelector('.shift-cell').getAttribute('data-staff-payrate');
            recalculateRowSummary(staffId, payRateId);
            
            row.style.display = 'none'; row.style.opacity = '1';
            row.dataset.rosterBaseVisible = 'false';
            const summaryRow = document.getElementById('summary-row-' + staffId);
            if (summaryRow) summaryRow.style.display = 'none';
            updateGrandTotals();
            cells.forEach(cell => updateCoverageForDate(cell.getAttribute('data-date')));
            refreshRosterKpis();
            scheduleValidationKpiRefresh();
        } else { alert('ลบข้อมูลไม่สำเร็จบางส่วน'); row.style.opacity = '1'; }
    });
}

function checkFatigueRules() {
    let warningCount = 0;
    const aliases = { A: 'บ', N: 'ร', O: 'ย', M: 'ช' };
    const parseTypes = (value) => value.trim().split(/[\\/,\\s]+/).filter(Boolean).map(v => aliases[v] || v);
    const isWork = (value) => parseTypes(value).some(v => ['ช', 'บ', 'ร', 'ย'].includes(v));

    document.querySelectorAll('.shift-cell').forEach(cell => {
        cell.classList.remove('fatigue-warn');
        if (cell.dataset.fatigueTitle === '1') {
            cell.removeAttribute('title');
            delete cell.dataset.fatigueTitle;
        }
    });

    document.querySelectorAll('.roster-staff-row').forEach(row => {
        const cells = Array.from(row.querySelectorAll('.shift-cell[data-date]'));
        let consecutiveDays = 0;

        for (let i = 0; i < cells.length; i++) {
            const val = cells[i].innerText.trim();
            const nextVal = (i + 1 < cells.length) ? cells[i + 1].innerText.trim() : '';
            const types = parseTypes(val);

            if (isWork(val)) {
                consecutiveDays++;
                if (consecutiveDays > 6) {
                    cells[i].classList.add('fatigue-warn');
                    cells[i].setAttribute('title', '⚠️ ปฏิบัติงานติดต่อกันเกิน 6 วัน');
                    cells[i].dataset.fatigueTitle = '1';
                    warningCount++;
                }
            } else {
                consecutiveDays = 0;
            }

            if (types.includes('ร') && isWork(nextVal)) {
                if (cells[i + 1]) {
                    cells[i + 1].classList.add('fatigue-warn');
                    cells[i + 1].setAttribute('title', '⚠️ มีเวรต่อหลังเวรดึก ควรตรวจสอบเวลาพัก');
                    cells[i + 1].dataset.fatigueTitle = '1';
                    warningCount++;
                }
            }
        }
    });

    const activeFilter = document.querySelector('.rp-roster-filter-btn.active')?.dataset.rosterFilter;
    if (activeFilter === 'fatigue') applyRosterRowFilter('fatigue');

    Swal.fire({
        icon: warningCount > 0 ? 'warning' : 'success',
        title: warningCount > 0 ? 'พบจุดเสี่ยงความเหนื่อยล้า' : 'ไม่พบจุดเสี่ยงจากการตรวจเบื้องต้น',
        text: warningCount > 0 ? `พบทั้งหมด ${warningCount} จุด ระบบทำเครื่องหมายไว้ในตารางแล้ว` : 'ควรใช้ปุ่ม “ตรวจสอบตาราง” เพื่อตรวจเงื่อนไขทั้งหมดอีกครั้ง',
        confirmButtonText: 'ตกลง'
    });
}

function getPayRates(payRateId) {
    let r = { r: 0, y: 0, b: 0, name: 'ไม่มีกลุ่ม' };
    if (!payRateId) return r; 
    
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

function recalculateRowSummary(staffId, payRateId) {
    let sumR = 0, sumY = 0, sumB = 0;
    document.querySelectorAll(`.shift-cell[data-staff-id="${staffId}"]`).forEach(cell => {
        const aliases = { A: 'บ', N: 'ร', O: 'ย', M: 'ช' };
        const types = cell.innerText.trim().split(/[\\/,\\s]+/).filter(Boolean).map(v => aliases[v] || v);
        types.forEach(type => {
            if (type === 'ร') sumR++;
            else if (type === 'ย') sumY++;
            else if (type === 'บ') sumB++;
        });
    });
    
    if(document.getElementById(`modal-sum-r-${staffId}`)) document.getElementById(`modal-sum-r-${staffId}`).innerText = sumR;
    if(document.getElementById(`modal-sum-y-${staffId}`)) document.getElementById(`modal-sum-y-${staffId}`).innerText = sumY;
    if(document.getElementById(`modal-sum-b-${staffId}`)) document.getElementById(`modal-sum-b-${staffId}`).innerText = sumB;
    if(document.getElementById(`modal-sum-total-${staffId}`)) document.getElementById(`modal-sum-total-${staffId}`).innerText = sumR + sumY + sumB;

    if(!isApprovedSnapshot) {
        const rates = getPayRates(payRateId);
        const totalPay = (sumR * rates.r) + (sumY * rates.y) + (sumB * rates.b);
        const payCell = document.getElementById(`modal-sum-pay-${staffId}`);
        if(payCell) {
            payCell.setAttribute('data-sum-r', sumR); payCell.setAttribute('data-sum-y', sumY);
            payCell.setAttribute('data-sum-b', sumB); payCell.setAttribute('data-total', totalPay);
            payCell.setAttribute('data-group-name', rates.name);
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
        rosterApiFetch('index.php?c=ajax&a=copy_roster_previous', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ target_month: currentMonth, hosp_id: targetHospId }) })
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
    rosterApiFetch('index.php?c=ajax&a=request_holiday', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ date: selectedHolidayDate, name: hName, hosp_id: targetHospId }) })
    .then(r => r.json()).then(d => {
        if (d.status === 'success') { alert('ส่งคำขอสำเร็จ!'); holidayInfoModal.hide(); } else alert('Error: ' + d.message);
    });
}

// ==========================================
// 🤖 ฟังก์ชันจัดการเวรอัตโนมัติ & ตรวจสอบความถูกต้องตาราง
// ==========================================
function autoScheduleRoster() {
    Swal.fire({
        title: 'จัดการเวรอัตโนมัติ?',
        text: "ระบบจะทำการสุ่มจัดเวรอัตโนมัติให้กับบุคลากรทุกคน ครอบคลุมทุกตำแหน่งในสังกัด (ไม่บันทึกทับเวรเดิมที่มีอยู่)",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ใช่, จัดการเลย',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'กำลังประมวลผล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
            rosterApiFetch('index.php?c=ajax&a=auto_schedule', {
                method: 'POST',
                body: JSON.stringify({ month_year: currentMonthYear, hosp_id: targetHospId }),
                headers: { 'Content-Type': 'application/json' }
            }).then(res => res.json()).then(data => {
                if(data.status === 'success') {
                    Swal.fire('สำเร็จ', data.message, 'success').then(() => window.location.reload());
                } else {
                    Swal.fire('ผิดพลาด', data.message, 'error');
                }
            });
        }
    });
}

function validateRoster() {
    Swal.fire({ title: 'กำลังตรวจสอบ...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
    rosterApiFetch(`index.php?c=ajax&a=validate_roster&month=${currentMonthYear}&hosp_id=${targetHospId}`)
    .then(res => res.json())
    .then(data => {
        Swal.close();
        const warningContainer = document.getElementById('rosterWarnings');
        
        if (data.status === 'success') {
            const hardErrors = Array.isArray(data.errors) ? data.errors : (data.has_error ? data.warnings : []);
            const advisories = Array.isArray(data.advisories) ? data.advisories : (data.has_error ? [] : data.warnings);
            warningContainer.style.display = 'block';

            if (hardErrors.length === 0 && advisories.length === 0) {
                warningContainer.innerHTML = `<div class="alert alert-success border-0 shadow-sm mb-0"><i class="bi bi-check-circle-fill me-2"></i> ตารางเวรผ่านการตรวจสอบ ไม่พบข้อผิดพลาดหรือคำเตือน</div>`;
            } else {
                let html = '';
                if (hardErrors.length > 0) {
                    html += `<div class="alert alert-danger border-0 shadow-sm"><h6 class="fw-bold"><i class="bi bi-x-octagon-fill me-2"></i> ต้องแก้ไขก่อนส่งอนุมัติ ${hardErrors.length} รายการ</h6><ul class="mb-0">`;
                    hardErrors.forEach(item => html += `<li>${item}</li>`);
                    html += `</ul></div>`;
                }
                if (advisories.length > 0) {
                    html += `<div class="alert alert-warning border-0 shadow-sm mb-0"><h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> ข้อควรตรวจสอบ ${advisories.length} รายการ</h6><ul class="mb-0">`;
                    advisories.forEach(item => html += `<li>${item}</li>`);
                    html += `</ul></div>`;
                }
                warningContainer.innerHTML = html;
            }
        }
    });
}

// ==========================================
// 🛡️ ฟังก์ชันตรวจสอบก่อนส่งอนุมัติ (Pre-submit Validation)
// ==========================================
function submitForApproval(e, formElement) {
    e.preventDefault();
    Swal.fire({ title: 'กำลังตรวจสอบตารางก่อนส่งอนุมัติ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    rosterApiFetch(`index.php?c=ajax&a=validate_roster&month=${currentMonthYear}&hosp_id=${targetHospId}`)
    .then(res => res.json())
    .then(data => {
        Swal.close();
        if (data.status !== 'success') {
            Swal.fire('ผิดพลาด', 'ไม่สามารถตรวจสอบตารางได้: ' + (data.message || 'Unknown error'), 'error');
            return;
        }

        const hardErrors = Array.isArray(data.errors) ? data.errors : (data.has_error ? data.warnings : []);
        const advisories = Array.isArray(data.advisories) ? data.advisories : (data.has_error ? [] : data.warnings);

        if (hardErrors.length > 0) {
            let html = `<div class="alert alert-danger border-0 text-start"><strong>พบข้อผิดพลาดที่ต้องแก้ไข ${hardErrors.length} รายการ</strong><ul class="mb-0 mt-2 small">`;
            hardErrors.forEach(item => html += `<li>${item}</li>`);
            html += '</ul></div><p class="mb-0">ระบบจะยังไม่ส่งอนุมัติจนกว่าจะผ่านเงื่อนไขที่จำเป็น</p>';
            Swal.fire({
                title: 'ยังส่งอนุมัติไม่ได้',
                html,
                icon: 'error',
                confirmButtonText: 'กลับไปแก้ไข'
            });
            return;
        }

        if (advisories.length > 0) {
            let html = `<div class="alert alert-warning border-0 text-start"><strong>มีข้อควรตรวจสอบ ${advisories.length} รายการ</strong><ul class="mb-0 mt-2 small">`;
            advisories.forEach(item => html += `<li>${item}</li>`);
            html += '</ul></div><p class="mb-0">สามารถส่งอนุมัติได้ หากผู้จัดเวรตรวจสอบและยืนยันแล้ว</p>';
            Swal.fire({
                title: 'ยืนยันส่งอนุมัติ?',
                html,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ตรวจแล้ว ส่งอนุมัติ',
                cancelButtonText: 'กลับไปตรวจสอบ'
            }).then(result => {
                if (result.isConfirmed) formElement.submit();
            });
            return;
        }

        Swal.fire({
            title: 'ยืนยันการส่งอนุมัติ?',
            text: 'ตารางเวรผ่านการตรวจสอบแล้ว',
            icon: 'success',
            showCancelButton: true,
            confirmButtonText: 'ส่งอนุมัติเลย',
            cancelButtonText: 'ยกเลิก'
        }).then(result => {
            if (result.isConfirmed) formElement.submit();
        });
    })
    .catch(() => Swal.fire('ผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error'));
}

// ==========================================
// 🌟 ฟังก์ชันส่งออกตารางเป็น Excel
// ==========================================
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