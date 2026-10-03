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
.shift-box { border-left: 4px solid #3b82f6; background-color: #f8fafc; border-radius: 0.5rem; transition: background-color 0.2s; }
    .shift-box:hover { background-color: #eff6ff; border-left-color: #2563eb; }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100 rp-dashboard-page">
    
    <!-- 🌟 ส่วนหัว (Welcome) -->
    <div class="rp-dashboard-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 60px; height: 60px;">
                <i class="bi bi-person-heart fs-3"></i>
            </div>
            <div>
                <h3 class="fw-black text-dark mb-1">สวัสดี, <span class="text-primary"><?= htmlspecialchars($_SESSION['user']['name']) ?></span></h3>
                <p class="text-muted mb-0" style="font-size: 14px;"><i class="bi bi-building me-1"></i>สังกัด: <?= htmlspecialchars($hospital_name) ?></p>
            </div>
        </div>
        <div class="text-md-end text-muted font-monospace bg-white px-4 py-2 rounded-pill shadow-sm border border-secondary border-opacity-25 fw-bold">
            <i class="bi bi-calendar3 me-2 text-primary"></i> วันนี้: <?= $today_th ?>
        </div>
    </div>

    <!-- 🌟 สถานะย่อย (Mini KPIs) -->
    <div class="row g-3 mb-4">
        <!-- เวรที่กำลังจะถึง -->
        <div class="col-md-3 col-sm-6">
            <div class="staff-card card h-100 border-start border-primary border-4">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-bold mb-1 small text-uppercase">เวรของคุณเดือนนี้</p>
                        <h3 class="fw-black text-dark mb-0"><?= count($upcoming_shifts) ?> <span class="fs-6 text-muted fw-normal">กะ</span></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center" style="width: 45px; height: 45px;"><i class="bi bi-calendar-check fs-5"></i></div>
                </div>
            </div>
        </div>

        <!-- คำขอลา -->
        <div class="col-md-3 col-sm-6">
            <div class="staff-card card h-100 border-start border-danger border-4">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-bold mb-1 small text-uppercase">ใบลารอพิจารณา</p>
                        <h3 class="fw-black <?= $my_pending_leaves > 0 ? 'text-danger' : 'text-dark' ?> mb-0"><?= $my_pending_leaves ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex justify-content-center align-items-center" style="width: 45px; height: 45px;"><i class="bi bi-envelope-paper fs-5"></i></div>
                </div>
            </div>
        </div>

        <!-- ขอแลกเวร -->
        <div class="col-md-3 col-sm-6">
            <div class="staff-card card h-100 border-start border-warning border-4">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-bold mb-1 small text-uppercase">แลกเวรรอพิจารณา</p>
                        <h3 class="fw-black <?= $my_pending_swaps > 0 ? 'text-warning text-dark' : 'text-dark' ?> mb-0"><?= $my_pending_swaps ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h3>
                    </div>
                    <div class="bg-warning bg-opacity-25 text-warning text-dark rounded-circle d-flex justify-content-center align-items-center" style="width: 45px; height: 45px;"><i class="bi bi-arrow-left-right fs-5"></i></div>
                </div>
            </div>
        </div>

        <!-- สถานะตารางรวม -->
        <div class="col-md-3 col-sm-6">
            <div class="staff-card card h-100 border-start border-<?= str_replace(' text-dark', '', $status_color) ?> border-4">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted fw-bold mb-1 small text-uppercase">ตารางเวร รพ.สต.</p>
                        <span class="badge bg-<?= $status_color ?> rounded-pill px-3 py-2 mt-1"><i class="bi <?= $status_icon ?> me-1"></i> <?= $status_text ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

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