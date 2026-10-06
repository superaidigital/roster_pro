<?php
// ที่อยู่ไฟล์: views/dashboard/staff_index.php

$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$current_month_th = $thai_months[(int)date('m')] . ' ' . (date('Y') + 543);
$today_th = date('d') . ' ' . $thai_months[(int)date('m')] . ' ' . (date('Y') + 543);

$hospital_name = $hospital_name ?? 'หน่วยบริการ';
$upcoming_shifts = $upcoming_shifts ?? [];
$my_pending_leaves = $my_pending_leaves ?? 0;
$my_pending_swaps = $my_pending_swaps ?? 0;
$roster_status = $roster_status ?? 'NOT_STARTED';

require_once __DIR__ . '/../components/ui.php';

// จัดการสีของตารางเวร
$status_color = 'secondary';
$status_text = 'ยังไม่เริ่มจัด';
$status_icon = 'bi-dash-circle-dotted';

if ($roster_status == 'APPROVED') {
    $status_color = 'success'; $status_text = 'อนุมัติแล้ว'; $status_icon = 'bi-check-circle-fill';
} elseif ($roster_status == 'SUBMITTED') {
    $status_color = 'primary'; $status_text = 'รออนุมัติ'; $status_icon = 'bi-send-fill';
} elseif ($roster_status == 'DRAFT' || $roster_status == 'REQUEST_EDIT') {
    $status_color = 'warning text-dark'; $status_text = 'กำลังจัดทำ'; $status_icon = 'bi-pencil-square';
}
?>

<div class="rp-page">

    <?php
    ob_start();
    ?>
        <span class="rp-badge rp-badge--info">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <?= rp_e($today_th) ?>
        </span>
    <?php
    $staff_header_actions = ob_get_clean();

    rp_page_header(
        'หน้าหลักของฉัน',
        'สังกัด ' . $hospital_name . ' · ดูเวร คำขอ และงานที่เกี่ยวข้องได้จากจุดเดียว',
        $staff_header_actions,
        'Roster Pro'
    );
    ?>

    <section class="rp-section" aria-labelledby="myStatusTitle">
        <?php rp_section_header('สถานะของฉัน', 'สรุปข้อมูลสำคัญที่ควรรู้วันนี้'); ?>
        <div class="rp-grid rp-grid--4" id="myStatusTitle">
            <?php rp_stat_card('เวรที่กำลังจะถึง', count($upcoming_shifts) . ' กะ', 'bi-calendar-check', 'primary', 'index.php?c=roster'); ?>
            <?php rp_stat_card('ใบลารอพิจารณา', (int)$my_pending_leaves . ' รายการ', 'bi-envelope-paper', $my_pending_leaves > 0 ? 'danger' : 'primary', 'index.php?c=leave'); ?>
            <?php rp_stat_card('แลกเวรรอพิจารณา', (int)$my_pending_swaps . ' รายการ', 'bi-arrow-left-right', $my_pending_swaps > 0 ? 'warning' : 'primary', 'index.php?c=swap'); ?>
            <?php rp_stat_card('สถานะตารางเวร', $status_text, $status_icon, $roster_status === 'APPROVED' ? 'success' : ($roster_status === 'SUBMITTED' ? 'info' : 'warning'), 'index.php?c=roster'); ?>
        </div>
    </section>

    <section class="rp-section" aria-labelledby="staffQuickTitle">
        <?php rp_section_header('ทำรายการด่วน', 'งานที่ใช้บ่อยในแต่ละวัน'); ?>
        <div class="rp-grid rp-grid--4 rp-action-grid-mobile" id="staffQuickTitle">
            <?php rp_action_card('ดูตารางเวร', 'ตรวจเวรทั้งหมด', 'bi-calendar-range', 'index.php?c=roster', 'primary'); ?>
            <?php rp_action_card('ยื่นใบลา', 'ตรวจสิทธิ์และส่งคำขอ', 'bi-calendar-minus', 'index.php?c=leave', 'danger', (int)$my_pending_leaves); ?>
            <?php rp_action_card('ขอแลกเวร', 'ส่งและติดตามคำขอ', 'bi-arrow-repeat', 'index.php?c=swap', 'warning', (int)$my_pending_swaps); ?>
            <?php rp_action_card('ข้อมูลส่วนตัว', 'โปรไฟล์และการตั้งค่า', 'bi-person-badge', 'index.php?c=profile', 'info'); ?>
        </div>
    </section>

    <section class="rp-section" aria-labelledby="todayWorkTitle">
        <?php rp_section_header('วันนี้และเวรถัดไป', 'ข้อมูลที่ต้องใช้ในการวางแผนการทำงานของคุณ'); ?>
        <div class="rp-dashboard-grid" id="todayWorkTitle">
            <div class="rp-card rp-dashboard-card rp-dashboard-span-7">
                <div class="rp-card__header">
                    <h2 class="rp-card__title"><i class="bi bi-alarm text-success me-2"></i>เวรที่กำลังจะถึง</h2>
                    <a href="index.php?c=roster" class="rp-btn rp-btn--secondary rp-btn--sm">ดูทั้งหมด</a>
                </div>
                <div class="rp-card__body">
                    <?php if(empty($upcoming_shifts)): ?>
                        <?php rp_empty_state('bi-calendar-x', 'ยังไม่มีเวรที่กำหนดไว้', 'เมื่อมีการจัดเวร รายการที่กำลังจะถึงจะแสดงในส่วนนี้', 'ดูตารางเวร', 'index.php?c=roster'); ?>
                    <?php else: ?>
                        <div class="rp-shift-list">
                            <?php foreach($upcoming_shifts as $shift):
                                $date_ts = strtotime($shift['shift_date']);
                                $day_num = date('d', $date_ts);
                                $month_name = $thai_months[(int)date('m', $date_ts)];
                                $is_today = ($shift['shift_date'] === date('Y-m-d'));
                                $shift_type = $shift['shift_type'];
                            ?>
                                <div class="rp-shift-item <?= $is_today ? 'rp-shift-item--today' : '' ?>">
                                    <div class="rp-shift-item__date">
                                        <span class="rp-shift-item__day"><?= rp_e($day_num) ?></span>
                                        <span class="rp-shift-item__month"><?= rp_e($month_name) ?></span>
                                    </div>
                                    <div>
                                        <h3 class="rp-shift-item__title">กะการทำงาน: <?= rp_e($shift_type) ?></h3>
                                        <div class="rp-shift-item__meta">
                                            <?= $is_today ? 'วันนี้ · อยู่ในแผนปฏิบัติงาน' : 'รอเข้าปฏิบัติงาน' ?>
                                        </div>
                                    </div>
                                    <span class="rp-badge <?= $is_today ? 'rp-badge--info' : 'rp-badge--neutral' ?>">
                                        <?= $is_today ? 'วันนี้' : 'กำลังจะถึง' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rp-card rp-dashboard-card rp-dashboard-span-5">
                <div class="rp-card__header">
                    <h2 class="rp-card__title"><i class="bi bi-info-circle-fill text-primary me-2"></i>ข้อมูลสำคัญ</h2>
                </div>
                <div class="rp-card__body">
                    <div class="rp-alert rp-alert--info mb-3">
                        <span class="rp-alert__icon"><i class="bi bi-calendar-check"></i></span>
                        <div class="rp-alert__content">
                            <strong>สถานะตารางเวร: <?= rp_e($status_text) ?></strong>
                            <div class="mt-1">หากตารางยังไม่อนุมัติ ข้อมูลอาจมีการเปลี่ยนแปลงได้</div>
                        </div>
                    </div>

                    <div class="rp-stack">
                        <a href="index.php?c=leave" class="rp-action-card rp-action-card--danger">
                            <span class="rp-action-card__icon"><i class="bi bi-calendar-minus"></i></span>
                            <span class="rp-action-card__copy">
                                <span class="rp-action-card__title">ยื่นใบลา</span>
                                <span class="rp-action-card__subtitle">ตรวจสิทธิ์และส่งคำขอลา</span>
                            </span>
                        </a>

                        <a href="index.php?c=swap" class="rp-action-card rp-action-card--warning">
                            <span class="rp-action-card__icon"><i class="bi bi-arrow-repeat"></i></span>
                            <span class="rp-action-card__copy">
                                <span class="rp-action-card__title">ขอแลกเวร</span>
                                <span class="rp-action-card__subtitle">สร้างและติดตามคำขอสลับเวร</span>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>