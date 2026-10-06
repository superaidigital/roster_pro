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

require_once 'views/components/ui.php';

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

<style>
    body { background-color: #f4f6f9; font-family: 'Sarabun', sans-serif; }
    
    .staff-card {
        border: none; border-radius: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease; background: #fff; overflow: hidden;
    }
    .staff-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    
    .quick-action-btn { transition: all 0.2s; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 1rem; color: #475569; font-weight: 600; text-align: center; padding: 20px 10px; text-decoration: none; display: block; }
    .quick-action-btn i { font-size: 32px; display: block; margin-bottom: 10px; }
    .quick-action-btn:hover { background: #fff; border-color: #3b82f6; color: #3b82f6; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.1); transform: translateY(-2px); }

    .shift-box { border-left: 4px solid #3b82f6; background-color: #f8fafc; border-radius: 0.5rem; transition: background-color 0.2s; }
    .shift-box:hover { background-color: #eff6ff; border-left-color: #2563eb; }
</style>

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

    <div class="row g-4">
        <!-- 🌟 เมนูลัด (Quick Actions) -->
        <div class="col-xl-8 col-lg-7">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-lightning-charge-fill text-warning me-2"></i> เมนูการใช้งานด่วน</h5>
            <div class="row g-3">
                <div class="col-md-4 col-6">
                    <a href="index.php?c=roster" class="quick-action-btn">
                        <i class="bi bi-calendar-range text-primary"></i>ดูตารางเวรทั้งหมด
                    </a>
                </div>
                <div class="col-md-4 col-6">
                    <a href="index.php?c=leave" class="quick-action-btn position-relative">
                        <i class="bi bi-calendar-minus text-danger"></i>เขียนใบลาหยุด
                        <?php if($my_pending_leaves > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow"><?= $my_pending_leaves ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="col-md-4 col-6">
                    <a href="index.php?c=swap" class="quick-action-btn position-relative">
                        <i class="bi bi-arrow-repeat text-warning"></i>ขอแลกเวร/เปลี่ยนเวร
                        <?php if($my_pending_swaps > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark shadow"><?= $my_pending_swaps ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="col-md-4 col-6">
                    <a href="index.php?c=profile" class="quick-action-btn">
                        <i class="bi bi-person-badge text-info"></i>ข้อมูลส่วนตัว
                    </a>
                </div>
            </div>

            <!-- ข้อความประกาศหรือตารางเวรภาพรวม -->
            <div class="card staff-card mt-4 border-0 bg-primary bg-opacity-10">
                <div class="card-body p-4 d-flex align-items-center">
                    <i class="bi bi-info-circle-fill fs-1 text-primary opacity-50 me-3"></i>
                    <div>
                        <h6 class="fw-bold text-primary mb-1">การดูตารางเวร</h6>
                        <p class="text-muted small mb-0">หากตารางเวรยังอยู่ในสถานะ <b>กำลังจัดทำ</b> ข้อมูลที่แสดงอาจมีการเปลี่ยนแปลงได้ โปรดยึดข้อมูลเมื่อตารางเปลี่ยนเป็น <b>อนุมัติแล้ว</b> เท่านั้น</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🌟 เวรที่กำลังจะถึงของคุณ -->
        <div class="col-xl-4 col-lg-5">
            <div class="card staff-card h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-alarm text-success me-2"></i> เวรที่กำลังจะถึงของคุณ</h6>
                    <a href="index.php?c=roster" class="text-decoration-none small text-primary fw-bold">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="card-body p-3">
                    <?php if(empty($upcoming_shifts)): ?>
                        <div class="text-center text-muted py-5 opacity-50">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                            ไม่มีเวรที่กำหนดไว้ในเร็วๆ นี้
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach($upcoming_shifts as $shift): 
                                $date_ts = strtotime($shift['shift_date']);
                                $day_num = date('d', $date_ts);
                                $month_name = $thai_months[(int)date('m', $date_ts)];
                                $is_today = ($shift['shift_date'] === date('Y-m-d'));
                                
                                // สีกะ
                                $shift_type = $shift['shift_type'];
                                $badge_bg = 'bg-secondary';
                                if ($shift_type == 'บ' || $shift_type == 'A') $badge_bg = 'bg-warning text-dark';
                                elseif ($shift_type == 'ร' || $shift_type == 'N') $badge_bg = 'bg-success';
                                elseif (strpos($shift_type, '/') !== false) $badge_bg = 'bg-primary';
                            ?>
                            <div class="shift-box p-3 d-flex justify-content-between align-items-center <?= $is_today ? 'border-primary shadow-sm bg-white' : '' ?>">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="text-center" style="min-width: 50px;">
                                        <div class="fw-bold text-dark fs-5 lh-1"><?= $day_num ?></div>
                                        <div class="text-muted" style="font-size: 11px;"><?= $month_name ?></div>
                                    </div>
                                    <div class="vr opacity-25"></div>
                                    <div>
                                        <h6 class="mb-1 fw-bold text-dark d-flex align-items-center">
                                            กะการทำงาน: <span class="badge <?= $badge_bg ?> ms-2"><?= htmlspecialchars($shift_type) ?></span>
                                        </h6>
                                        <div class="text-muted" style="font-size: 12px;">
                                            <?php if($is_today): ?>
                                                <span class="text-primary fw-bold"><i class="bi bi-record-circle-fill"></i> วันนี้</span>
                                            <?php else: ?>
                                                <i class="bi bi-clock"></i> รอเข้าปฏิบัติงาน
                                            <?php endif; ?>
                                        </div>
                                    </div>
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