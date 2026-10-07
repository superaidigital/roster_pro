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

// Mobile day view: keep one day in focus instead of squeezing 31 columns into a phone screen.
$mobile_day = isset($_GET['day']) ? (int)$_GET['day'] : ((date('Y-m') === $selected_month) ? (int)date('d') : 1);
$mobile_day = max(1, min($days_in_month, $mobile_day));
$mobile_date = "$year-$month-" . str_pad($mobile_day, 2, '0', STR_PAD_LEFT);

$mobile_shift_map = [];
if (!empty($shifts) && is_array($shifts)) {
    foreach ($shifts as $shift) {
        if (($shift['shift_date'] ?? '') === $mobile_date) {
            $mobile_shift_map[(int)$shift['user_id']] = (string)($shift['shift_type'] ?? '');
        }
    }
}

$mobile_leave_map = [];
if (!empty($leaves) && is_array($leaves)) {
    $mobile_ts = strtotime($mobile_date);
    foreach ($leaves as $leave) {
        $start_ts = strtotime((string)$leave['start_date']);
        $end_ts = strtotime((string)$leave['end_date']);
        if ($mobile_ts >= $start_ts && $mobile_ts <= $end_ts) {
            $suffix = (($leave['status'] ?? '') === 'APPROVED') ? '' : ' (รอ)';
            $mobile_leave_map[(int)$leave['user_id']] = trim((string)$leave['leave_type'] . $suffix);
        }
    }
}

require_once __DIR__ . '/../components/ui.php';

if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$roster_csrf_token = $_SESSION['csrf_token'];
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
    .holiday-column { background-color: #fff1f2 !important; } /* 🌟 พื้นหลังสีแดงอ่อนๆ สำหรับวันหยุด */

    @media (min-width: 992px) { .sticky-sidebar { position: sticky; top: 15px; align-self: flex-start; height: calc(100vh - 110px); overflow: hidden; } }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
</style>
<link rel="stylesheet" href="public/css/roster-board.css?v=2">

<div class="w-100 min-vh-100 d-flex flex-column">
    <div class="rp-page flex-grow-1 d-flex flex-column">
        
        <?php
        ob_start();
        ?>
            <a href="index.php?c=swap" class="rp-btn rp-btn--secondary">
                <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                ขอแลกเวร
            </a>
            <a href="index.php?c=roster&a=export_word&month=<?= urlencode($selected_month) ?>&hospital_id=<?= urlencode($hospital_id ?? '') ?>" class="rp-btn rp-btn--primary">
                <i class="bi bi-printer-fill" aria-hidden="true"></i>
                พิมพ์
            </a>
            <button type="button" onclick="exportTableToExcelClean('rosterTable', 'ตารางเวร_<?= htmlspecialchars($selected_month, ENT_QUOTES, 'UTF-8') ?>')" class="rp-btn rp-btn--success">
                <i class="bi bi-file-earmark-excel-fill" aria-hidden="true"></i>
                Excel
            </button>
        <?php
        $roster_actions = ob_get_clean();
        rp_page_header(
            'ตารางปฏิบัติงาน',
            'หน่วยบริการ: ' . ($hospital_name ?? '') . ' · ' . $display_month_text,
            $roster_actions,
            'Roster'
        );
        ?>

        <div class="rp-roster-toolbar" aria-label="ตัวกรองตารางเวร">
            <form method="GET" action="index.php" id="filterFormRoster" class="rp-roster-toolbar__filters mb-0">
                <input type="hidden" name="c" value="roster">
                <input type="hidden" name="a" value="index">

                <?php if ($isAdmin): ?>
                    <select name="hospital_id" class="rp-control" onchange="this.form.submit()" aria-label="เลือกหน่วยบริการ">
                        <?php foreach ($hospitals_list as $h): ?>
                            <option value="<?= (int)$h['id'] ?>" <?= (int)$h['id'] === (int)($hospital_id ?? 0) ? 'selected' : '' ?>>
                                <?= rp_e($h['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="hospital_id" value="<?= (int)($hospital_id ?? 0) ?>">
                <?php endif; ?>

                <input type="month" name="month" class="rp-control" value="<?= rp_e($selected_month) ?>" onchange="this.form.submit()" aria-label="เลือกเดือน">

                <?php if ($selected_month !== date('Y-m')): ?>
                    <a href="index.php?c=roster&a=index&month=<?= urlencode(date('Y-m')) ?>&hospital_id=<?= urlencode($hospital_id ?? '') ?>" class="rp-btn rp-btn--secondary rp-btn--sm">
                        <i class="bi bi-calendar-event" aria-hidden="true"></i>
                        เดือนปัจจุบัน
                    </a>
                <?php endif; ?>
            </form>

            <div class="rp-roster-toolbar__actions">
                <button type="button" class="rp-btn rp-btn--secondary rp-btn--sm" data-bs-toggle="modal" data-bs-target="#summaryModal">
                    <i class="bi bi-bar-chart-fill" aria-hidden="true"></i>
                    สรุปยอดเดือนนี้
                </button>
                <button type="button" class="rp-btn rp-btn--primary rp-btn--sm" id="rosterFullscreenBtn" aria-controls="rosterWorkspace" aria-pressed="false">
                    <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
                    <span>ขยายตารางเต็มจอ</span>
                </button>
            </div>
        </div>

        <!-- Feedback -->
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="rp-alert rp-alert--success" role="status" aria-live="polite">
                <span class="rp-alert__icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></span>
                <div class="rp-alert__content"><?= rp_e($_SESSION['success_msg']) ?></div>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="rp-alert rp-alert--danger" role="alert">
                <span class="rp-alert__icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></span>
                <div class="rp-alert__content"><?= rp_e($_SESSION['error_msg']) ?></div>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <?php
        $status_tone = 'warning';
        $status_icon = 'bi-pencil-square';
        $status_title = 'กำลังจัดทำ';
        $status_description = 'สามารถเพิ่ม ลด หรือปรับเวรได้ก่อนส่งพิจารณา';

        if ($roster_status === 'APPROVED') {
            $status_tone = 'success';
            $status_icon = 'bi-check-circle-fill';
            $status_title = 'อนุมัติแล้ว';
            $status_description = 'ตารางเวรเดือนนี้ได้รับการยืนยันและอยู่ในโหมดอ่านอย่างเดียว';
        } elseif ($roster_status === 'SUBMITTED') {
            $status_tone = 'info';
            $status_icon = 'bi-send-fill';
            $status_title = 'รอพิจารณา';
            $status_description = 'ตารางถูกส่งให้ผู้อำนวยการตรวจสอบแล้ว';
        } elseif ($roster_status === 'REQUEST_EDIT') {
            $status_tone = 'danger';
            $status_icon = 'bi-unlock-fill';
            $status_title = 'รออนุมัติแก้ไข';
            $status_description = 'ส่งคำขอปลดล็อกตารางที่อนุมัติแล้ว และกำลังรอการดำเนินการ';
        }
        ?>

        <div class="rp-status-panel rp-status-panel--<?= rp_e($status_tone) ?> rp-roster-status">
            <div class="rp-roster-status__main">
                <h2 class="rp-roster-status__title">
                    <i class="bi <?= rp_e($status_icon) ?>" aria-hidden="true"></i>
                    สถานะ: <?= rp_e($status_title) ?>
                </h2>
                <p class="rp-roster-status__description"><?= rp_e($status_description) ?></p>
            </div>

            <div class="rp-roster-status__actions">
                <?php if (($roster_status === 'APPROVED' || $roster_status === 'REQUEST_EDIT') && $isAdmin): ?>
                    <form action="index.php?c=ajax&a=change_status" method="POST" class="d-flex gap-2 flex-wrap m-0">
                        <input type="hidden" name="csrf_token" value="<?= rp_e($roster_csrf_token) ?>">
                        <input type="hidden" name="month_year" value="<?= rp_e($selected_month) ?>">
                        <input type="hidden" name="hospital_id" value="<?= rp_e($hospital_id ?? '') ?>">
                        <?php if ($roster_status === 'REQUEST_EDIT'): ?>
                            <button type="submit" name="status" value="APPROVED" class="rp-btn rp-btn--secondary rp-btn--sm" onclick="return confirm('ปฏิเสธคำขอแก้ไข?');">
                                <i class="bi bi-x-circle"></i> ปฏิเสธ
                            </button>
                            <button type="submit" name="status" value="DRAFT" class="rp-btn rp-btn--warning rp-btn--sm" onclick="return confirm('อนุมัติให้แก้ไขตาราง?');">
                                <i class="bi bi-unlock-fill"></i> อนุมัติให้แก้ไข
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="status" value="DRAFT">
                            <button type="submit" class="rp-btn rp-btn--danger rp-btn--sm" onclick="return confirm('ยืนยันตีกลับตารางเวรให้แก้ไข?');">
                                <i class="bi bi-arrow-return-left"></i> ตีกลับให้แก้ไข
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <?php if ($roster_status === 'SUBMITTED' && ($_SESSION['user']['role'] ?? '') === 'DIRECTOR'): ?>
                    <form action="index.php?c=ajax&a=change_status" method="POST" class="d-flex gap-2 flex-wrap m-0">
                        <input type="hidden" name="csrf_token" value="<?= rp_e($roster_csrf_token) ?>">
                        <input type="hidden" name="month_year" value="<?= rp_e($selected_month) ?>">
                        <button type="submit" name="status" value="DRAFT" class="rp-btn rp-btn--danger rp-btn--sm" onclick="return confirm('ยืนยันการตีกลับ?');">
                            <i class="bi bi-arrow-return-left"></i> ตีกลับ
                        </button>
                        <button type="submit" name="status" value="APPROVED" class="rp-btn rp-btn--success rp-btn--sm" onclick="return confirm('อนุมัติตารางเวร?');">
                            <i class="bi bi-check-circle-fill"></i> อนุมัติเวร
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($roster_status === 'APPROVED' && in_array($_SESSION['user']['role'] ?? '', ['SCHEDULER', 'DIRECTOR'], true)): ?>
                    <form action="index.php?c=ajax&a=request_edit" method="POST" class="m-0" onsubmit="return confirm('ส่งคำขอปลดล็อกตารางเวร?');">
                        <input type="hidden" name="csrf_token" value="<?= rp_e($roster_csrf_token) ?>">
                        <input type="hidden" name="month_year" value="<?= rp_e($selected_month) ?>">
                        <button type="submit" class="rp-btn rp-btn--warning rp-btn--sm">
                            <i class="bi bi-unlock-fill"></i> ขอแก้ไขตาราง
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($roster_status === 'DRAFT' && in_array($_SESSION['user']['role'] ?? '', ['SCHEDULER', 'DIRECTOR'], true)): ?>
                    <button type="button" onclick="autoScheduleRoster()" class="rp-btn rp-btn--secondary rp-btn--sm" title="จัดเวรอัตโนมัติ">
                        <i class="bi bi-robot"></i> จัดอัตโนมัติ
                    </button>
                    <button type="button" onclick="validateRoster()" class="rp-btn rp-btn--secondary rp-btn--sm">
                        <i class="bi bi-shield-check"></i> ตรวจตาราง
                    </button>
                    <button type="button" onclick="checkFatigueRules()" class="rp-btn rp-btn--secondary rp-btn--sm">
                        <i class="bi bi-heart-pulse"></i> เช็กความล้า
                    </button>
                    <button type="button" onclick="copyPreviousMonth('<?= rp_e($selected_month) ?>')" class="rp-btn rp-btn--secondary rp-btn--sm">
                        <i class="bi bi-copy"></i> คัดลอกเดือนก่อน
                    </button>
                    <a href="javascript:void(0)" onclick="confirmAction('index.php?c=roster&a=clear_roster&month=<?= rp_e($selected_month) ?>', 'ยืนยันการล้างตารางเวรทั้งหมดของเดือนนี้?', this)" class="rp-btn rp-btn--secondary rp-btn--sm">
                        <i class="bi bi-eraser-fill"></i> ล้างข้อมูล
                    </a>
                    <form action="index.php?c=ajax&a=change_status" method="POST" class="m-0" onsubmit="submitForApproval(event, this);">
                        <input type="hidden" name="csrf_token" value="<?= rp_e($roster_csrf_token) ?>">
                        <input type="hidden" name="month_year" value="<?= rp_e($selected_month) ?>">
                        <input type="hidden" name="status" value="SUBMITTED">
                        <button type="submit" class="rp-btn rp-btn--primary rp-btn--sm">
                            <i class="bi bi-send-fill"></i> ส่งอนุมัติ
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div id="rosterWarnings" class="w-100" style="display:none;"></div>
        </div>

        <!-- Mobile day view -->
        <section class="d-lg-none mb-3" aria-labelledby="mobileRosterTitle">
            <div class="rp-card">
                <div class="rp-card__header">
                    <div>
                        <h2 id="mobileRosterTitle" class="rp-card__title">เวรประจำวันที่ <?= (int)$mobile_day ?> <?= rp_e($thai_months[(int)$month]) ?></h2>
                        <p class="rp-section-description mb-0">เลือกวันที่ด้านล่างเพื่อดูเวรแบบอ่านง่ายบนมือถือ</p>
                    </div>
                    <?php if ($holiday_cache[$mobile_day]): ?>
                        <span class="rp-badge rp-badge--danger">วันหยุด</span>
                    <?php endif; ?>
                </div>
                <div class="rp-card__body">
                    <nav class="rp-roster-day-tabs" aria-label="เลือกวันที่">
                        <?php for ($d = 1; $d <= $days_in_month; $d++):
                            $day_date = "$year-$month-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                            $dow = (int)date('N', strtotime($day_date));
                            $day_holiday = $holiday_cache[$d] || $dow >= 6;
                            $day_url = 'index.php?c=roster&a=index&month=' . urlencode($selected_month)
                                . '&hospital_id=' . urlencode($hospital_id ?? '')
                                . '&day=' . $d;
                        ?>
                            <a href="<?= rp_e($day_url) ?>" class="rp-roster-day-tab <?= $d === $mobile_day ? 'rp-roster-day-tab--active' : '' ?> <?= $day_holiday ? 'rp-roster-day-tab--holiday' : '' ?>" <?= $d === $mobile_day ? 'aria-current="date"' : '' ?>>
                                <?= $d ?>
                            </a>
                        <?php endfor; ?>
                    </nav>

                    <div class="rp-mobile-roster-list">
                        <?php
                        $mobile_visible_count = 0;
                        foreach ($all_staff_for_sidebar as $staff):
                            $staff_id = (int)$staff['id'];
                            $is_external = isset($staff['hospital_id']) && (int)$staff['hospital_id'] !== (int)($hospital_id ?? 0);
                            $shift_value = $mobile_shift_map[$staff_id] ?? '';
                            if ($is_external && $shift_value === '') continue;
                            $mobile_visible_count++;
                            $leave_value = $mobile_leave_map[$staff_id] ?? '';
                        ?>
                            <div class="rp-mobile-roster-item">
                                <div>
                                    <h3 class="rp-mobile-roster-item__name"><?= rp_e($staff['name']) ?></h3>
                                    <div class="rp-mobile-roster-item__meta">
                                        <?= rp_e($staff['type'] ?? '') ?>
                                        <?php if ($leave_value !== ''): ?>
                                            · <span class="text-danger"><?= rp_e($leave_value) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($canEdit && $leave_value === ''): ?>
                                    <button type="button"
                                            class="rp-mobile-shift-btn <?= rp_e(getShiftColorClass($shift_value)) ?>"
                                            data-staff-id="<?= $staff_id ?>"
                                            data-staff-payrate="<?= rp_e($staff['pay_rate_id'] ?? '') ?>"
                                            data-date="<?= rp_e($mobile_date) ?>"
                                            onclick="openMobileShiftEditor(this)"
                                            aria-label="แก้ไขเวรของ <?= rp_e($staff['name']) ?>">
                                        <?= $shift_value !== '' ? rp_e($shift_value) : '—' ?>
                                    </button>
                                <?php else: ?>
                                    <span class="rp-mobile-shift-value <?= rp_e(getShiftColorClass($shift_value)) ?>">
                                        <?= $shift_value !== '' ? rp_e($shift_value) : '—' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($mobile_visible_count === 0): ?>
                            <?php rp_empty_state('bi-people', 'ยังไม่มีบุคลากรในวันนี้', 'ยังไม่มีรายการบุคลากรหรือเวรสำหรับวันที่เลือก'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <div class="rp-roster-workspace d-none d-lg-grid" id="rosterWorkspace">
            <div class="rp-roster-focusbar" aria-label="เครื่องมือโหมดจัดเวรเต็มจอ">
                <div class="rp-roster-focusbar__copy">
                    <div class="rp-roster-focusbar__title">
                        <i class="bi bi-calendar3"></i>
                        ตารางปฏิบัติงาน · <?= rp_e($display_month_text) ?>
                    </div>
                    <div class="rp-roster-focusbar__meta"><?= rp_e($hospital_name ?? '') ?></div>
                </div>
                <div class="rp-roster-focusbar__actions">
                    <button type="button"
                            class="rp-btn rp-btn--secondary rp-btn--sm"
                            id="rosterStaffPanelToggle"
                            aria-pressed="false"
                            onclick="toggleRosterStaffPanel(); return false;">
                        <i class="bi bi-people-fill"></i>
                        <span>ซ่อนรายชื่อ</span>
                    </button>
                    <button type="button"
                            class="rp-btn rp-btn--danger rp-btn--sm"
                            id="rosterFullscreenExit"
                            onclick="exitRosterNativeFullscreen(); return false;">
                        <i class="bi bi-fullscreen-exit"></i>
                        ออกจากเต็มจอ
                    </button>
                </div>
            </div>

            <!-- 🌟 ตารางเวรหลัก -->
            <div class="rp-roster-board">
                <div class="rp-card overflow-hidden">
                    <div class="p-0 d-flex flex-column">
                        <div class="rp-roster-table-wrap custom-scrollbar">
                            <table class="table table-bordered table-hover table-roster rp-roster-table text-center" id="rosterTable" style="--rp-roster-days:<?= (int)$days_in_month ?>;">
                                <thead class="sticky-top" style="z-index: 10;">
                                    <tr>
                                        <th rowspan="2" class="align-middle rp-roster-person-head">รายชื่อเจ้าหน้าที่</th>
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
                                            <th class="rp-roster-date <?= $is_current_day ? 'rp-roster-date--today' : (($is_weekend || $holidayName) ? 'rp-roster-date--holiday' : '') ?>" 
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
                                            <td class="rp-roster-person-cell text-start px-3 shadow-sm bg-white" style="left: 0; position: sticky; z-index: 5; border-right: 2px solid #e2e8f0; <?php if($is_external) echo 'background-color: #fef2f2 !important;'; ?>">
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
                                                <td class="rp-roster-cell text-center <?= $is_current_day ? 'rp-roster-cell--today' : ($is_weekend_or_holiday ? 'rp-roster-cell--holiday' : '') ?>">
                                                    
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
                                    <tr id="dropZoneRow" ondragover="allowDrop(event)" ondrop="dropStaff(event)" ondragleave="dragLeave(event)" class="rp-roster-dropzone">
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
                    
                    <div class="rp-roster-legend">
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
            <aside class="rp-staff-panel">
                <div class="rp-card overflow-hidden">
                    
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

                    <div class="rp-staff-panel__head">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold <?= !$canEdit ? 'text-muted' : 'text-dark' ?> mb-0 d-flex align-items-center">
                                <i class="bi bi-people-fill <?= !$canEdit ? 'text-muted' : 'text-primary' ?> me-2"></i> <?= $canEdit ? 'เลือกบุคลากรเข้าเวร' : 'รายชื่อ/สถิติบุคลากร' ?>
                            </h6>
                        </div>
                        
                        <div class="mb-2">
                            <select id="staffHospitalFilter" class="rp-control">
                                <option value="own" selected>🔹 บุคลากรในสังกัด รพ.สต.</option>
                                <option value="external">🔸 บุคลากรช่วยราชการ</option>
                            </select>
                        </div>

                        <div class="input-group mt-2">
                            <span class="input-group-text"><i class="bi bi-search text-muted"></i></span>
                            <input type="search" id="staffSearch" class="rp-control" placeholder="ค้นหาชื่อ หรือตำแหน่ง..." aria-label="ค้นหาบุคลากร">
                        </div>
                    </div>
                    
                    <div class="rp-staff-panel__list custom-scrollbar" id="staffListContainer">
                        <?php 
                        foreach ($all_staff_for_sidebar as $staff): 
                            $is_external = (isset($staff['hospital_id']) && $staff['hospital_id'] != ($hospital_id??0));
                            $bs_color = getBsColor($staff['color_theme']);
                        ?>
                        <!-- 🌟 แนบ pay_rate_id ไว้เผื่อดึงผ่าน JS -->
                        <div class="rp-staff-pick staff-card draggable-staff" 
                             draggable="<?= $canEdit ? 'true' : 'false' ?>"
                             <?= $canEdit ? 'ondragstart="drag(event)"' : '' ?>
                             style="<?= $is_external ? 'display: none;' : '' ?>"
                             data-userid="<?= $staff['id'] ?>" 
                             data-username="<?= htmlspecialchars($staff['name']) ?>"
                             data-payrateid="<?= $staff['pay_rate_id'] ?? '' ?>"
                             data-is-external="<?= $is_external ? 'true' : 'false' ?>">
                            
                            <div class="d-flex align-items-center w-100">
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
        </aside>
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
const ROSTER_CSRF = <?= json_encode($roster_csrf_token, JSON_UNESCAPED_SLASHES) ?>;
// 🌟 นำเข้าฐานข้อมูลเรทเงินจาก PHP ลง JavaScript
const payRatesDB = <?php echo json_encode($pay_rates_db ?? []); ?>;
const isApprovedSnapshot = <?= ($roster_status == 'APPROVED' && isset($pay_snapshot)) ? 'true' : 'false' ?>;

// 💡 ใช้ JavaScript ล้วนคำนวณหาปี-เดือนปัจจุบัน ป้องกัน Syntax Error ของเบราว์เซอร์
const _d = new Date();
const _defaultMonth = _d.getFullYear() + '-' + String(_d.getMonth() + 1).padStart(2, '0');
const currentMonthYear = new URLSearchParams(window.location.search).get('month') || _defaultMonth;
const targetHospId = '<?= htmlspecialchars($hospital_id ?? $_SESSION['user']['hospital_id'] ?? '') ?>';

let currentCellBtn = null;
let currentMobileShiftBtn = null;
let shiftModal = null; 
let payCalcModal = null; 
let holidayInfoModal = null;
let selectedHolidayDate = '';

function setRosterStaffPanelState(hidden) {
    const workspace = document.getElementById('rosterWorkspace');
    const button = document.getElementById('rosterStaffPanelToggle');
    if (!workspace) return;

    workspace.classList.toggle('is-staff-hidden', hidden);

    if (button) {
        button.setAttribute('aria-pressed', hidden ? 'true' : 'false');
        const label = button.querySelector('span');
        const icon = button.querySelector('i');
        if (label) label.textContent = hidden ? 'แสดงรายชื่อ' : 'ซ่อนรายชื่อ';
        if (icon) icon.className = hidden ? 'bi bi-people' : 'bi bi-people-fill';
    }
}

function syncRosterFullscreenUi(enabled) {
    const workspace = document.getElementById('rosterWorkspace');
    const trigger = document.getElementById('rosterFullscreenBtn');
    if (!workspace) return;

    workspace.classList.toggle('is-fullscreen', enabled);
    document.body.classList.toggle('rp-roster-focus-mode', enabled);

    if (trigger) {
        trigger.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        const icon = trigger.querySelector('i');
        const label = trigger.querySelector('span');
        if (icon) icon.className = enabled ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        if (label) label.textContent = enabled ? 'ออกจากเต็มจอ' : 'ขยายตารางเต็มจอ';
    }

    if (enabled) {
        document.getElementById('pwaInstallToast')?.classList.remove('show');
        setRosterStaffPanelState(window.innerWidth < 1180);
    } else {
        setRosterStaffPanelState(false);
    }
}

async function enterRosterNativeFullscreen() {
    const workspace = document.getElementById('rosterWorkspace');
    if (!workspace) return;

    try {
        if (!document.fullscreenElement && workspace.requestFullscreen) {
            await workspace.requestFullscreen();
        } else {
            syncRosterFullscreenUi(true);
        }
    } catch (error) {
        console.warn('Native fullscreen unavailable, using focus layout fallback.', error);
        syncRosterFullscreenUi(true);
    }
}

async function exitRosterNativeFullscreen() {
    try {
        if (document.fullscreenElement && document.exitFullscreen) {
            await document.exitFullscreen();
            return;
        }
    } catch (error) {
        console.warn('Unable to exit native fullscreen.', error);
    }

    // Fallback when native fullscreen is unavailable or has already ended.
    syncRosterFullscreenUi(false);
}

function setRosterFocusMode(enabled) {
    if (enabled) {
        enterRosterNativeFullscreen();
    } else {
        exitRosterNativeFullscreen();
    }
}

function toggleRosterStaffPanel() {
    const workspace = document.getElementById('rosterWorkspace');
    if (!workspace) return;
    setRosterStaffPanelState(!workspace.classList.contains('is-staff-hidden'));
}

document.addEventListener('fullscreenchange', function() {
    const workspace = document.getElementById('rosterWorkspace');
    const isRosterFullscreen = !!workspace && document.fullscreenElement === workspace;

    if (isRosterFullscreen) {
        syncRosterFullscreenUi(true);
    } else if (workspace?.classList.contains('is-fullscreen')) {
        syncRosterFullscreenUi(false);
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const fullscreenBtn = document.getElementById('rosterFullscreenBtn');

    fullscreenBtn?.addEventListener('click', function(event) {
        event.preventDefault();
        if (document.fullscreenElement) {
            exitRosterNativeFullscreen();
        } else {
            enterRosterNativeFullscreen();
        }
    });

    // Browser handles Esc natively. This fallback only handles CSS focus mode.
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' &&
            !document.fullscreenElement &&
            document.getElementById('rosterWorkspace')?.classList.contains('is-fullscreen')) {
            syncRosterFullscreenUi(false);
        }
    });
    
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
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': ROSTER_CSRF },
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

function openMobileShiftEditor(btn) {
    currentMobileShiftBtn = btn;
    const staffId = btn.getAttribute('data-staff-id');
    const dateStr = btn.getAttribute('data-date');
    const desktopCell = document.querySelector('.shift-cell[data-staff-id="' + staffId + '"][data-date="' + dateStr + '"]');

    if (desktopCell) {
        openShiftModal(desktopCell);
        return;
    }

    currentCellBtn = btn;
    if (!shiftModal) shiftModal = new bootstrap.Modal(document.getElementById('shiftSelectorModal'));
    const parts = dateStr.split('-');
    const thMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    document.getElementById('shiftModalDate').innerText = `วันที่ ${parseInt(parts[2], 10)} ${thMonths[parseInt(parts[1], 10)-1]} ${parseInt(parts[0])+543}`;
    shiftModal.show();
}

function openShiftModal(btn) {
    currentMobileShiftBtn = null;
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
    const payRateId = currentCellBtn.getAttribute('data-staff-payrate');
    const dateStr = currentCellBtn.getAttribute('data-date');
    
    currentCellBtn.innerText = shiftValue;

    if (currentCellBtn.classList.contains('rp-mobile-shift-btn')) {
        currentCellBtn.className = `rp-mobile-shift-btn ${colorClass}`;
    } else {
        currentCellBtn.className = `btn w-100 h-100 p-0 border-0 shadow-none hover-cell shift-cell ${colorClass}`;
    }

    if (currentMobileShiftBtn) {
        currentMobileShiftBtn.innerText = shiftValue || '—';
        currentMobileShiftBtn.className = `rp-mobile-shift-btn ${colorClass}`;
    }
    if (shiftModal) shiftModal.hide();
    
    recalculateRowSummary(staffId, payRateId);
    
    const indicator = currentCellBtn.nextElementSibling;
    if (indicator) indicator.classList.remove('d-none');
    
    fetch('index.php?c=ajax&a=save_shift', {
        method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': ROSTER_CSRF},
        body: JSON.stringify({ user_id: staffId, date: dateStr, shift_type: shiftValue, hosp_id: targetHospId })
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
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': ROSTER_CSRF},
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
        let val = cell.innerText.trim();
        if (val === 'ร' || val === 'N') sumR++; else if (val === 'ย' || val === 'O') sumY++; else if (val === 'บ' || val === 'A') sumB++;
        else if (val === 'บ/ร') { sumB++; sumR++; } else if (val === 'ย/บ') { sumY++; sumB++; }
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
        fetch('index.php?c=ajax&a=copy_roster_previous', { method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': ROSTER_CSRF}, body: JSON.stringify({ target_month: currentMonth, hosp_id: targetHospId }) })
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
    fetch('index.php?c=ajax&a=request_holiday', { method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': ROSTER_CSRF}, body: JSON.stringify({ date: selectedHolidayDate, name: hName, hosp_id: targetHospId }) })
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
            fetch('index.php?c=ajax&a=auto_schedule', {
                method: 'POST',
                body: JSON.stringify({ month_year: currentMonthYear, hosp_id: targetHospId }),
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': ROSTER_CSRF }
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
    fetch(`index.php?c=ajax&a=validate_roster&month=${currentMonthYear}&hosp_id=${targetHospId}`)
    .then(res => res.json())
    .then(data => {
        Swal.close();
        const warningContainer = document.getElementById('rosterWarnings');
        
        if (data.status === 'success') {
            if (data.warnings.length === 0) {
                warningContainer.style.display = 'block';
                warningContainer.innerHTML = `<div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i> ตารางเวรสมบูรณ์ ไม่มีข้อผิดพลาดครับ</div>`;
            } else {
                let html = `<div class="alert alert-danger border-0 shadow-sm"><h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> พบข้อควรระวัง ${data.warnings.length} รายการ:</h6><ul class="mb-0">`;
                data.warnings.forEach(w => html += `<li>${w}</li>`);
                html += `</ul></div>`;
                warningContainer.style.display = 'block';
                warningContainer.innerHTML = html;
            }
        }
    });
}

// ==========================================
// 🛡️ ฟังก์ชันตรวจสอบก่อนส่งอนุมัติ (Pre-submit Validation)
// ==========================================
function submitForApproval(e, formElement) {
    e.preventDefault(); // ป้องกันการส่งฟอร์มทันที
    
    Swal.fire({ title: 'กำลังตรวจสอบตารางก่อนส่งอนุมัติ...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
    
    fetch(`index.php?c=ajax&a=validate_roster&month=${currentMonthYear}&hosp_id=${targetHospId}`)
    .then(res => res.json())
    .then(data => {
        Swal.close();
        if (data.status === 'success') {
            if (data.warnings.length === 0) {
                // หากไม่มี Error ให้ถามยืนยันปกติ
                Swal.fire({
                    title: 'ยืนยันการส่งอนุมัติ?',
                    text: "ตารางเวรสมบูรณ์ ไม่มีข้อผิดพลาด",
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'ส่งอนุมัติเลย',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        formElement.submit();
                    }
                });
            } else {
                // หากพบ Error ให้แสดงรายการขึ้นมาก่อน
                let html = `<div class="alert alert-danger border-0 shadow-sm text-start"><h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> พบข้อควรระวัง ${data.warnings.length} รายการ:</h6><ul class="mb-0 small">`;
                data.warnings.forEach(w => html += `<li>${w}</li>`);
                html += `</ul></div><p class="mt-3 fs-6">คุณแน่ใจหรือไม่ว่าต้องการส่งตารางเวรนี้เพื่อพิจารณาอนุมัติ?</p>`;
                
                Swal.fire({
                    title: 'พบข้อควรระวังในตารางเวร!',
                    html: html,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ยืนยันส่งอนุมัติ (ละเว้นคำเตือน)',
                    cancelButtonText: 'กลับไปแก้ไข'
                }).then((result) => {
                    if (result.isConfirmed) {
                        formElement.submit();
                    }
                });
            }
        } else {
            Swal.fire('ผิดพลาด', 'ไม่สามารถตรวจสอบตารางได้: ' + data.message, 'error');
        }
    })
    .catch(err => {
        Swal.fire('ผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
    });
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