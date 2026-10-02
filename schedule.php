<?php
// ที่อยู่ไฟล์: views/profile/schedule.php

$selected_ym = $selected_ym ?? date('Y-m');
$my_shifts = $my_shifts ?? [];
$my_leaves = $my_leaves ?? [];
$raw_leaves = $raw_leaves ?? []; 
$holidays = $holidays ?? [];
$summary = $summary ?? ['บ' => 0, 'ร' => 0, 'ย' => 0, 'pay' => 0];

$exp = explode('-', $selected_ym);
$year = (int)$exp[0];
$month = (int)$exp[1];

$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$display_month_text = $thai_months[$month] . ' ' . ($year + 543);

$prev_ym = date('Y-m', strtotime($selected_ym . '-01 -1 month'));
$next_ym = date('Y-m', strtotime($selected_ym . '-01 +1 month'));

$first_day_ts = strtotime($selected_ym . '-01');
$days_in_month = date('t', $first_day_ts);
$start_day_of_week = date('w', $first_day_ts); 

function getFullThaiDate($date_str) {
    if (empty($date_str)) return '-';
    $thai_days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    $thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($date_str);
    $day_of_week = $thai_days[date('w', $ts)];
    $d = date('j', $ts);
    $m = $thai_months[date('n', $ts)];
    $y = date('Y', $ts) + 543;
    return "วัน{$day_of_week}ที่ {$d} {$m} {$y}";
}

// ฟังก์ชันแสดงวันที่แบบย่อ สำหรับใบลา
function getShortThaiDate($date_str) {
    if (empty($date_str)) return '-';
    $thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($date_str);
    $d = date('j', $ts);
    $m = $thai_months[date('n', $ts)];
    $y = date('Y', $ts) + 543;
    return "{$d} {$m} {$y}";
}

// 🌟 ดึงเฉพาะกะเวรและวันหยุดมาลงลิสต์ด้านขวา (ใบลาจะถูกแยกโชว์ต่างหาก)
$shift_events = [];
for ($day = 1; $day <= $days_in_month; $day++) {
    $date_str = sprintf("%04d-%02d-%02d", $year, $month, $day);
    $shift = $my_shifts[$date_str] ?? null;
    $holiday = $holidays[$date_str] ?? null;
    
    if ($shift || $holiday) {
        $shift_events[$date_str] = ['shift' => $shift, 'holiday' => $holiday];
    }
}

function getShiftBadgeClass($shift) {
    if ($shift == 'บ') return 'bg-warning text-dark border border-warning';
    if ($shift == 'ร') return 'bg-success text-white border border-success';
    if ($shift == 'ย') return 'bg-danger text-white border border-danger';
    if ($shift == 'บ/ร' || $shift == 'ย/บ') return 'bg-primary text-white border border-primary';
    return 'bg-dark text-white border border-dark';
}
?>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
    
    .icon-box-lg { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }

    .calendar-grid-header { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-weight: 700; font-size: 13px; padding: 12px 0; background-color: #f8fafc; border-radius: 1.25rem 1.25rem 0 0; }
    .calendar-grid-body { display: grid; grid-template-columns: repeat(7, 1fr); background-color: #e2e8f0; gap: 1px; }

    .calendar-cell { background-color: #ffffff; min-height: 110px; padding: 8px; display: flex; flex-direction: column; transition: background-color 0.2s; overflow: hidden; }
    .calendar-cell:hover:not(.empty-cell) { background-color: #f8fafc; }
    .calendar-cell.empty-cell { background-color: #f8fafc; }
    .calendar-cell.is-holiday { background-color: #fef2f2; }
    .calendar-cell.is-today { background-color: #eff6ff; }

    .date-number { font-weight: 700; font-size: 14px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: #475569; margin-bottom: 4px; flex-shrink: 0; }

    .events-container { flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; }
    .events-container::-webkit-scrollbar { width: 3px; }
    .events-container::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }

    .event-badge { font-size: 11px; padding: 4px 6px; border-radius: 6px; display: block; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: left; }
    .event-badge-center { text-align: center; font-weight: 800; font-size: 13px; justify-content: center; }

    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }

    @media (max-width: 767.98px) {
        .calendar-grid-header { font-size: 11px; padding: 8px 0; }
        .calendar-cell { min-height: 85px; padding: 4px; }
        .date-number { font-size: 12px; width: 22px; height: 22px; }
        .event-badge { font-size: 9px; padding: 3px 4px; }
        .event-badge-center { font-size: 11px; }
    }
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div class="text-center text-md-start">
            <h2 class="h3 text-dark mb-1 fw-bold">
                <i class="bi bi-calendar-heart-fill text-danger me-2"></i> ปฏิทินเวรของฉัน
            </h2>
            <p class="text-muted mb-0" style="font-size: 14px;">ตรวจสอบตารางปฏิบัติงานและวันลาของคุณแบบส่วนตัว (พ.ศ. <?= $year + 543 ?>)</p>
        </div>
        
        <div class="d-flex align-items-center gap-2 bg-white p-2 rounded-pill shadow-sm border">
            <a href="index.php?c=profile&a=schedule&month=<?= $prev_ym ?>" class="btn btn-light rounded-circle shadow-none p-2 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;" title="เดือนก่อนหน้า">
                <i class="bi bi-chevron-left fw-bold"></i>
            </a>
            <div class="fw-bold text-primary px-3 text-center" style="min-width: 140px; font-size: 1.1rem;">
                <?= $display_month_text ?>
            </div>
            <a href="index.php?c=profile&a=schedule&month=<?= $next_ym ?>" class="btn btn-light rounded-circle shadow-none p-2 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;" title="เดือนถัดไป">
                <i class="bi bi-chevron-right fw-bold"></i>
            </a>
            <div class="vr mx-1"></div>
            <a href="index.php?c=profile&a=schedule&month=<?= date('Y-m') ?>" class="btn btn-outline-primary rounded-pill btn-sm fw-bold px-3 shadow-none">
                เดือนปัจจุบัน
            </a>
        </div>
    </div>

    <!-- 🌟 ส่วนสรุปยอด -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card card-modern stat-card border-success h-100" style="border-left: 4px solid !important;">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="icon-box-lg bg-success bg-opacity-10 text-success me-3"><i class="bi bi-moon-stars-fill"></i></div>
                    <div>
                        <div class="text-success fw-bold small text-uppercase opacity-75">เวรดึก (ร)</div>
                        <h3 class="mb-0 fw-bold text-dark"><?= floatval($summary['ร'] ?? 0) ?> <span class="fs-6 text-muted fw-normal">กะ</span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-modern stat-card border-warning h-100" style="border-left: 4px solid !important;">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="icon-box-lg bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-sunset-fill"></i></div>
                    <div>
                        <div class="text-warning fw-bold small text-uppercase opacity-75" style="color: #b45309 !important;">เวรบ่าย (บ)</div>
                        <h3 class="mb-0 fw-bold text-dark"><?= floatval($summary['บ'] ?? 0) ?> <span class="fs-6 text-muted fw-normal">กะ</span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-modern stat-card border-danger h-100" style="border-left: 4px solid !important;">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="icon-box-lg bg-danger bg-opacity-10 text-danger me-3"><i class="bi bi-brightness-high-fill"></i></div>
                    <div>
                        <div class="text-danger fw-bold small text-uppercase opacity-75">เวรหยุด (ย)</div>
                        <h3 class="mb-0 fw-bold text-dark"><?= floatval($summary['ย'] ?? 0) ?> <span class="fs-6 text-muted fw-normal">กะ</span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-modern stat-card border-primary h-100 shadow-sm" style="border-left: 4px solid !important; background-color: #f8fafc;">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="icon-box-lg bg-primary text-white me-3 shadow-sm"><i class="bi bi-wallet2"></i></div>
                    <div style="min-width: 0;">
                        <div class="text-primary fw-bold small text-uppercase opacity-75 text-truncate">ค่าตอบแทน(ประมาณ)</div>
                        <h3 class="mb-0 fw-bold text-primary text-truncate">฿ <?= number_format($summary['pay'] ?? 0) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        
        <!-- 📅 ฝั่งซ้าย: ปฏิทิน -->
        <div class="col-xl-8">
            <div class="card card-modern overflow-hidden h-100">
                <div class="card-body p-0">
                    <div class="calendar-grid-header border-bottom">
                        <div class="text-danger">อาทิตย์</div>
                        <div class="text-dark">จันทร์</div>
                        <div class="text-dark">อังคาร</div>
                        <div class="text-dark">พุธ</div>
                        <div class="text-dark">พฤหัสบดี</div>
                        <div class="text-dark">ศุกร์</div>
                        <div class="text-primary">เสาร์</div>
                    </div>
                    
                    <div class="calendar-grid-body">
                        <?php 
                        for ($i = 0; $i < $start_day_of_week; $i++) { echo '<div class="calendar-cell empty-cell"></div>'; }

                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date_str = sprintf("%04d-%02d-%02d", $year, $month, $day);
                            $current_dow = date('w', strtotime($date_str));
                            $is_weekend = ($current_dow == 0 || $current_dow == 6);
                            $is_today = ($date_str == date('Y-m-d'));
                            
                            $holiday_name = $holidays[$date_str] ?? null;
                            $shift = $my_shifts[$date_str] ?? null;
                            $leave = $my_leaves[$date_str] ?? null;

                            $cell_classes = ['calendar-cell'];
                            if ($is_today) $cell_classes[] = 'is-today border border-primary';
                            if ($is_weekend || $holiday_name) $cell_classes[] = 'is-holiday';
                            ?>
                            
                            <div class="<?= implode(' ', $cell_classes) ?>" title="<?= getFullThaiDate($date_str) ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="date-number <?= $is_today ? 'bg-primary text-white shadow-sm' : '' ?>"><?= $day ?></span>
                                    <?php if ($holiday_name): ?>
                                        <i class="bi bi-star-fill text-danger mt-1" style="font-size: 10px;" title="<?= htmlspecialchars($holiday_name) ?>"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="events-container">
                                    <?php if ($leave): ?>
                                        <?php 
                                            $leave_type = is_array($leave) ? $leave['type'] : $leave;
                                            $leave_status = is_array($leave) ? $leave['status'] : 'APPROVED';
                                            $bg = ($leave_status == 'APPROVED') ? 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25' : 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-50';
                                            $icon = ($leave_status == 'APPROVED') ? 'bi-envelope-paper-fill' : 'bi-hourglass-split';
                                        ?>
                                        <span class="event-badge <?= $bg ?> shadow-sm mb-1" title="<?= htmlspecialchars($leave_type) . ($leave_status == 'APPROVED' ? '' : ' (รออนุมัติ)') ?>">
                                            <i class="bi <?= $icon ?> me-1"></i><?= htmlspecialchars($leave_type) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($shift && (!$leave || $leave_status != 'APPROVED')): ?>
                                        <span class="event-badge event-badge-center <?= getShiftBadgeClass($shift) ?> shadow-sm">
                                            <?= htmlspecialchars($shift) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php 
                        } 
                        $end_day_of_week = date('w', strtotime($year . '-' . $month . '-' . $days_in_month));
                        for ($i = 0; $i < (6 - $end_day_of_week); $i++) { echo '<div class="calendar-cell empty-cell"></div>'; }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 📋 ฝั่งขวา: รายการสรุป (แยกวันลาเป็นช่วงวัน) -->
        <div class="col-xl-4">
            <div class="card card-modern h-100 d-flex flex-column">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-stars text-primary me-2"></i> กิจกรรมในเดือนนี้</h6>
                </div>
                <div class="card-body p-0 flex-grow-1 overflow-hidden">
                    <div class="list-group list-group-flush custom-scrollbar h-100" style="max-height: 550px; overflow-y: auto;">
                        
                        <!-- 🌟 1. แสดงวันลาแบบรวบยอด (ถ้ามี) -->
                        <?php if (!empty($raw_leaves)): ?>
                            <div class="px-3 py-2 bg-light border-bottom text-muted fw-bold small"><i class="bi bi-airplane me-1"></i> รายการลางาน</div>
                            <?php foreach ($raw_leaves as $l): 
                                $status_badge = $l['status'] == 'APPROVED' ? '<span class="badge bg-secondary"><i class="bi bi-check-circle me-1"></i>อนุมัติแล้ว</span>' : '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>รออนุมัติ</span>';
                                
                                // แปลงวันที่เป็นภาษาไทยแบบย่อ
                                $start_dt = getShortThaiDate($l['start_date']);
                                $end_dt = getShortThaiDate($l['end_date']);
                                $display_date = ($start_dt === $end_dt) ? $start_dt : "{$start_dt} - {$end_dt}";
                            ?>
                                <div class="list-group-item px-4 py-3 border-bottom bg-white">
                                    <div class="fw-bold text-dark mb-1" style="font-size: 14px;">
                                        <?= htmlspecialchars($l['leave_type']) ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted"><i class="bi bi-calendar-event me-1"></i> <?= $display_date ?></small>
                                        <?= $status_badge ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- 🌟 2. แสดงกะเวรและวันหยุดรายวัน (ถ้ามี) -->
                        <?php if (!empty($shift_events)): ?>
                            <div class="px-3 py-2 bg-light border-bottom text-muted fw-bold small mt-2"><i class="bi bi-clock-history me-1"></i> วันปฏิบัติงาน / วันหยุดประจำเดือน</div>
                            <?php foreach ($shift_events as $date_str => $evt): 
                                $is_past = strtotime($date_str) < strtotime(date('Y-m-d'));
                            ?>
                                <div class="list-group-item px-4 py-3 border-bottom <?= $is_past ? 'bg-light' : 'bg-white' ?>" <?= $is_past ? 'style="opacity: 0.6;"' : '' ?>>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="fw-bold <?= $is_past ? 'text-muted' : 'text-dark' ?>" style="font-size: 14px;">
                                            <i class="bi bi-calendar3 me-2 text-primary opacity-75"></i><?= getFullThaiDate($date_str) ?>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if ($evt['shift']): ?>
                                            <span class="badge <?= getShiftBadgeClass($evt['shift']) ?> px-2 py-1 shadow-sm" style="font-size: 12px;">
                                                เวรปฏิบัติงาน: <?= htmlspecialchars($evt['shift']) ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($evt['holiday']): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1" style="font-size: 11px;">
                                                <i class="bi bi-star-fill me-1"></i> <?= htmlspecialchars($evt['holiday']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- ถ้าเดือนนั้นว่างเปล่า ไม่มีอะไรเลย -->
                        <?php if (empty($raw_leaves) && empty($shift_events)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-minus fs-1 d-block mb-3 opacity-25"></i>
                                เดือนนี้ยังไม่มีตารางปฏิบัติงาน หรือวันลา
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- 🌟 คำอธิบายสัญลักษณ์ (Footer) -->
    <div class="card card-modern bg-white">
        <div class="card-body p-3 d-flex flex-wrap gap-4 justify-content-center text-muted border-top border-light" style="font-size: 13px;">
            <div class="d-flex align-items-center gap-2"><span class="badge bg-success" style="width: 25px;">ร</span> เวรรอ (ดึก)</div>
            <div class="d-flex align-items-center gap-2"><span class="badge bg-warning text-dark border border-warning" style="width: 25px;">บ</span> เวรบ่าย</div>
            <div class="d-flex align-items-center gap-2"><span class="badge bg-danger" style="width: 25px;">ย</span> เวรวันหยุด</div>
            <div class="d-flex align-items-center gap-2"><span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25" style="width: 25px;"><i class="bi bi-envelope-paper-fill"></i></span> ลางาน</div>
        </div>
    </div>
</div>