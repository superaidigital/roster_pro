<?php
// ที่อยู่ไฟล์: views/dashboard/admin_index.php

$total_hospitals = $total_hospitals ?? 0;
$total_staff = $total_staff ?? 0;
$on_duty_today = $on_duty_today ?? 0;
$pending_leaves = $pending_leaves ?? 0;
$pending_swaps = $pending_swaps ?? 0;
$estimated_budget = $estimated_budget ?? 0;

$status_counts = $status_counts ?? ['APPROVED' => 0, 'SUBMITTED' => 0, 'DRAFT' => 0, 'WAITING' => 0];
$waiting_hospitals = $waiting_hospitals ?? [];
$workload_data = $workload_data ?? [];
$risk_hospitals = $risk_hospitals ?? [];
$recent_leaves = $recent_leaves ?? [];
$fatigue_staff = $fatigue_staff ?? [];
$leave_trends_labels = $leave_trends_labels ?? [];
$leave_trends_data = $leave_trends_data ?? [];
$recent_logs = $recent_logs ?? [];
$today_usages = $today_usages ?? []; // 🌟 ตัวแปรเก็บข้อมูลการใช้งานวันนี้

$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$current_month_th = $thai_months[(int)date('m')] . ' ' . (date('Y') + 543);

require_once __DIR__ . '/../components/ui.php';

// ==============================================================
// 🌟 กรองเอา "ส่วนกลาง" ออกจากการแจ้งเตือนความเสี่ยง (ให้ตรงกับหน้าวิเคราะห์)
// ==============================================================
$filtered_fatigue = [];
foreach ($fatigue_staff as $f) {
    $h_name = $f['hosp_name'] ?? $f['hospital_name'] ?? '';
    if (mb_strpos($h_name, 'ส่วนกลาง') === false) {
        $filtered_fatigue[] = $f;
    }
}
$fatigue_staff = $filtered_fatigue;

$filtered_risk = [];
foreach ($risk_hospitals as $r) {
    if (mb_strpos($r['name'] ?? '', 'ส่วนกลาง') === false) {
        $filtered_risk[] = $r;
    }
}
$risk_hospitals = $filtered_risk;

$filtered_waiting = [];
foreach ($waiting_hospitals as $w) {
    // รองรับทั้งกรณีที่เป็น Array หรือ String
    $w_name = is_array($w) ? ($w['name'] ?? '') : $w;
    if (mb_strpos($w_name, 'ส่วนกลาง') === false) {
        $filtered_waiting[] = $w;
    }
}
$waiting_hospitals = $filtered_waiting;
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="rp-page">

    <?php
    ob_start();
    ?>
        <?php if (in_array(strtoupper($_SESSION['user']['role'] ?? ''), ['ADMIN', 'SUPERADMIN'], true)): ?>
            <a href="index.php?c=dashboard&a=map_view" class="rp-btn rp-btn--primary">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                <span>แสดงแผนที่ รพ.สต.</span>
            </a>
        <?php endif; ?>
        <span class="rp-badge rp-badge--info">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <?= date('d') ?> <?= rp_e($thai_months[(int)date('m')]) ?> <?= date('Y') + 543 ?>
        </span>
    <?php
    $dashboard_header_actions = ob_get_clean();

    rp_page_header(
        'ภาพรวมระบบ',
        'Executive Dashboard · ยินดีต้อนรับ ' . ($_SESSION['user']['name'] ?? 'ผู้ใช้งาน'),
        $dashboard_header_actions,
        'Roster Pro'
    );
    ?>

    <?php
    $total_alerts = count($risk_hospitals) + count($fatigue_staff) + count($waiting_hospitals);
    ?>

    <?php if ($total_alerts > 0): ?>
        <section class="rp-section" aria-labelledby="attentionTitle">
            <?php rp_section_header('สิ่งที่ต้องให้ความสนใจ', 'รายการที่ควรตรวจสอบก่อนเริ่มงานส่วนอื่น'); ?>
            <button type="button" class="rp-card rp-card--interactive w-100 text-start p-0 border-danger-subtle" onclick="showAlertPopup()" aria-label="เปิดศูนย์แจ้งเตือนความเสี่ยง">
                <div class="rp-card__body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="rp-stat-card__icon" style="--rp-stat-soft:var(--rp-danger-50);--rp-stat-accent:var(--rp-danger-600);" aria-hidden="true">
                            <i class="bi bi-bell-fill"></i>
                        </span>
                        <div>
                            <h2 id="attentionTitle" class="rp-card__title mb-1">ศูนย์แจ้งเตือนความเสี่ยง</h2>
                            <p class="rp-section-description mb-0">
                                พบรายการที่ต้องตรวจสอบ <strong class="text-danger"><?= (int)$total_alerts ?></strong> รายการ
                            </p>
                        </div>
                    </div>
                    <span class="rp-btn rp-btn--danger">
                        <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                        ดูรายละเอียด
                    </span>
                </div>
            </button>
        </section>
    <?php endif; ?>

    <section class="rp-section" aria-labelledby="todayTitle">
        <?php rp_section_header('วันนี้', 'สถานะกำลังคนและงานสำคัญที่เกี่ยวข้องกับการปฏิบัติงานวันนี้'); ?>
        <div class="rp-dashboard-grid" id="todayTitle">
            <div class="rp-card rp-today-card rp-dashboard-span-4">
                <div class="rp-today-card__top">
                    <div>
                        <p class="rp-today-card__label">กำลังปฏิบัติงานวันนี้</p>
                        <h3 class="rp-today-card__title"><?= number_format($on_duty_today) ?> คน</h3>
                        <p class="rp-today-card__description">จำนวนบุคลากรที่มีตารางเวรในวันนี้</p>
                    </div>
                    <span class="rp-today-card__icon"><i class="bi bi-person-workspace" aria-hidden="true"></i></span>
                </div>
                <div class="rp-today-card__footer">
                    <a href="index.php?c=roster" class="rp-btn rp-btn--secondary rp-btn--sm">ดูตารางเวร</a>
                </div>
            </div>

            <div class="rp-card rp-today-card rp-dashboard-span-4">
                <div class="rp-today-card__top">
                    <div>
                        <p class="rp-today-card__label">ใบลารอพิจารณา</p>
                        <h3 class="rp-today-card__title"><?= number_format($pending_leaves) ?> รายการ</h3>
                        <p class="rp-today-card__description">คำขอที่ยังต้องดำเนินการอนุมัติ</p>
                    </div>
                    <span class="rp-today-card__icon" style="background:var(--rp-danger-50);color:var(--rp-danger-700);"><i class="bi bi-envelope-paper" aria-hidden="true"></i></span>
                </div>
                <div class="rp-today-card__footer">
                    <a href="index.php?c=leave&a=approvals" class="rp-btn rp-btn--danger rp-btn--sm">พิจารณาใบลา</a>
                </div>
            </div>

            <div class="rp-card rp-today-card rp-dashboard-span-4">
                <div class="rp-today-card__top">
                    <div>
                        <p class="rp-today-card__label">คำขอแลกเวร</p>
                        <h3 class="rp-today-card__title"><?= number_format($pending_swaps) ?> รายการ</h3>
                        <p class="rp-today-card__description">คำขอสลับเวรที่กำลังรอการดำเนินการ</p>
                    </div>
                    <span class="rp-today-card__icon" style="background:var(--rp-warning-50);color:var(--rp-warning-700);"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></span>
                </div>
                <div class="rp-today-card__footer">
                    <a href="index.php?c=swap" class="rp-btn rp-btn--warning rp-btn--sm">ดูคำขอแลกเวร</a>
                </div>
            </div>
        </div>
    </section>

    <section class="rp-section" aria-labelledby="quickActionsTitle">
        <?php rp_section_header('เมนูลัด', 'เข้าถึงงานที่ใช้บ่อยโดยไม่ต้องค้นหาในเมนูด้านข้าง'); ?>
        <div class="rp-grid rp-grid--6 rp-action-grid-mobile" id="quickActionsTitle">
            <?php rp_action_card('จัดการตารางเวร', 'สร้างและตรวจเวร', 'bi-calendar-check', 'index.php?c=roster', 'success'); ?>
            <?php rp_action_card('พิจารณาใบลา', 'ตรวจคำขออนุมัติ', 'bi-envelope-paper', 'index.php?c=leave&a=approvals', 'danger', (int)$pending_leaves); ?>
            <?php rp_action_card('คำขอแลกเวร', 'ตรวจและอนุมัติคำขอ', 'bi-arrow-left-right', 'index.php?c=swap', 'warning', (int)$pending_swaps); ?>
            <?php rp_action_card('ฐานข้อมูลบุคลากร', 'ข้อมูลและสิทธิ์ผู้ใช้', 'bi-people', 'index.php?c=staff', 'violet'); ?>
            <?php rp_action_card('รายงานสถิติ', 'สรุปและวิเคราะห์ข้อมูล', 'bi-bar-chart', 'index.php?c=report', 'info'); ?>
            <?php rp_action_card('ตั้งค่าระบบ', 'กำหนดค่าการใช้งาน', 'bi-sliders', 'index.php?c=settings', 'primary'); ?>
        </div>
    </section>

    <section class="rp-section" aria-labelledby="kpiTitle">
        <?php rp_section_header('ตัวชี้วัดสำคัญ', 'ข้อมูลภาพรวมล่าสุดสำหรับการตัดสินใจ'); ?>
        <div class="rp-grid rp-grid--6" id="kpiTitle">
            <?php rp_stat_card('หน่วยบริการ', number_format($total_hospitals), 'bi-hospital', 'info'); ?>
            <?php rp_stat_card('บุคลากร', number_format($total_staff), 'bi-people-fill', 'primary'); ?>
            <?php rp_stat_card('ขึ้นเวรวันนี้', number_format($on_duty_today), 'bi-person-workspace', 'success'); ?>
            <?php rp_stat_card('ใบลารออนุมัติ', number_format($pending_leaves), 'bi-envelope-paper-fill', $pending_leaves > 0 ? 'danger' : 'primary', 'index.php?c=leave&a=approvals'); ?>
            <?php rp_stat_card('ขอแลกเวร', number_format($pending_swaps), 'bi-arrow-left-right', $pending_swaps > 0 ? 'warning' : 'primary', 'index.php?c=swap'); ?>
            <?php rp_stat_card('งบประมาณ (บาท)', number_format($estimated_budget), 'bi-cash-coin', 'violet'); ?>
        </div>
    </section>

    <section class="rp-section" aria-labelledby="insightsTitle">
        <?php rp_section_header('Insight เดือนนี้', 'ดูแนวโน้มสำคัญก่อนลงรายละเอียดรายงาน'); ?>
        <div class="rp-dashboard-grid" id="insightsTitle">
            <div class="rp-card rp-dashboard-card rp-dashboard-span-4">
                <div class="rp-card__header">
                    <h3 class="rp-card__title"><i class="bi bi-pie-chart-fill text-primary me-2"></i>สถานะตารางเวร</h3>
                </div>
                <div class="rp-card__body">
                    <div class="rp-chart-frame"><canvas id="rosterStatusChart"></canvas></div>
                </div>
            </div>

            <div class="rp-card rp-dashboard-card rp-dashboard-span-4">
                <div class="rp-card__header">
                    <h3 class="rp-card__title"><i class="bi bi-bar-chart-steps text-info me-2"></i>สถิติการลาหยุดเดือนนี้</h3>
                </div>
                <div class="rp-card__body">
                    <?php if(empty($leave_trends_data)): ?>
                        <?php rp_empty_state('bi-calendar-x', 'ยังไม่มีข้อมูลการลา', 'เมื่อมีรายการลาที่อนุมัติแล้ว สถิติจะปรากฏที่นี่', 'ดูระบบวันลา', 'index.php?c=leave'); ?>
                    <?php else: ?>
                        <div class="rp-chart-frame"><canvas id="leaveTrendChart"></canvas></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="rp-card rp-dashboard-card rp-dashboard-span-4">
                <div class="rp-card__header">
                    <h3 class="rp-card__title"><i class="bi bi-bar-chart-line-fill text-warning me-2"></i>Top 5 ภาระงาน</h3>
                </div>
                <div class="rp-card__body">
                    <?php if(empty($workload_data)): ?>
                        <?php rp_empty_state('bi-graph-down', 'ยังไม่มีข้อมูลการจัดเวร', 'ระบบจะแสดงภาระงานหลังมีการจัดเวรในเดือนนี้', 'ไปยังตารางเวร', 'index.php?c=roster'); ?>
                    <?php else: ?>
                        <div class="rp-chart-frame"><canvas id="workloadChart"></canvas></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- 🌟 แถวใหม่: สรุปการใช้งานระบบประจำวัน (Daily Usage) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="rp-card rp-dashboard-card h-100">
                <div class="rp-card__header flex-wrap">
                    <div>
                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-activity text-primary me-2"></i> รายงานการเข้าใช้งานระบบประจำวัน</h6>
                        <p class="text-muted small mb-0">ข้อมูลความเคลื่อนไหวแยกตามหน่วยบริการ (อัปเดตแบบเรียลไทม์)</p>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-calendar-check me-1"></i> ข้อมูล ณ วันนี้: <?= date('d/m/Y') ?>
                    </span>
                </div>
                <div class="rp-card__body p-0">
                    <div class="table-responsive custom-scrollbar" style="max-height: 400px;">
                        <table class="table rp-table mb-0 table-hover align-middle">
                            <thead class="sticky-top bg-light" style="z-index: 1;">
                                <tr>
                                    <th class="text-center px-3" width="5%">ที่</th>
                                    <th width="30%">หน่วยบริการ (รพ.สต.)</th>
                                    <th class="text-center" width="15%">รวมความเคลื่อนไหว</th>
                                    <th width="35%">รายละเอียดกิจกรรม (ครั้ง)</th>
                                    <th class="text-center" width="15%">ใช้งานล่าสุด</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($today_usages)): ?>
                                    <?php $no = 1; foreach ($today_usages as $usage): ?>
                                        <tr>
                                            <td class="text-center text-muted fw-bold"><?= $no++ ?></td>
                                            <td>
                                                <div class="fw-bold text-dark">
                                                    <i class="bi bi-building text-secondary me-2"></i><?= htmlspecialchars($usage['hospital_name'] ?? 'ส่วนกลาง (สสจ./รพ.)') ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-dark rounded-pill fs-6 px-3 shadow-sm"><?= number_format($usage['total_actions']) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php if($usage['count_login'] > 0): ?>
                                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 py-2 px-2" title="การเข้าสู่ระบบ">
                                                            <i class="bi bi-box-arrow-in-right me-1"></i> ล็อกอิน <?= $usage['count_login'] ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($usage['count_manage'] > 0): ?>
                                                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 py-2 px-2" title="เพิ่ม แก้ไข ลบ นำเข้า หรือล้างข้อมูล">
                                                            <i class="bi bi-pencil-square me-1 text-warning"></i> จัดการข้อมูล <?= $usage['count_manage'] ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($usage['count_export'] > 0): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-2" title="การพิมพ์ หรือ ดาวน์โหลดข้อมูล">
                                                            <i class="bi bi-printer me-1"></i> พิมพ์เอกสาร <?= $usage['count_export'] ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center text-muted small fw-medium">
                                                <i class="bi bi-clock-history me-1"></i><?= date('H:i', strtotime($usage['last_active'])) ?> น.
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted opacity-50">
                                            <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                                                <i class="bi bi-activity fs-1 text-secondary"></i>
                                            </div>
                                            <h6 class="fw-bold mb-1 text-dark">ยังไม่มีการใช้งานระบบในวันนี้</h6>
                                            <p class="small mb-0">ระบบจะสรุปข้อมูลทันทีเมื่อมีหน่วยบริการเข้าสู่ระบบหรือจัดการข้อมูล</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Logs & Recent Leaves -->
    <div class="row g-4">
        <div class="col-xl-7 col-lg-6">
            <div class="rp-card rp-dashboard-card h-100">
                <div class="rp-card__header">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-envelope-exclamation-fill text-danger me-2"></i> คำขอลาล่าสุด (รออนุมัติ)</h6>
                    <a href="index.php?c=leave&a=approvals" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="font-size:12px;">จัดการใบลารออนุมัติ (<?= $pending_leaves ?>)</a>
                </div>
                <div class="rp-card__body p-0">
                    <div class="table-responsive">
                        <table class="table rp-table mb-0 table-hover text-center align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-start px-4">ผู้ขออนุมัติ</th>
                                    <th>ประเภท</th>
                                    <th>ช่วงวันที่</th>
                                    <th>ส่งเมื่อ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recent_leaves)): ?>
                                    <tr><td colspan="5" class="py-5 text-muted opacity-50"><i class="bi bi-check-circle fs-2 d-block mb-2"></i> ไม่มีคำขอที่รออนุมัติ</td></tr>
                                <?php else: ?>
                                    <?php foreach($recent_leaves as $leave): 
                                        $sd = date('d/m', strtotime($leave['start_date']));
                                        $ed = date('d/m', strtotime($leave['end_date']));
                                    ?>
                                    <tr>
                                        <td class="text-start px-4 fw-bold text-dark" style="font-size: 13.5px;">
                                            <?= htmlspecialchars($leave['user_name']) ?>
                                            <div class="text-muted fw-normal" style="font-size: 11px;"><?= date('d/m/Y H:i', strtotime($leave['created_at'])) ?></div>
                                        </td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-dark border px-2 py-1"><?= htmlspecialchars($leave['leave_type']) ?></span></td>
                                        <td class="font-monospace text-primary fw-medium" style="font-size: 12px;"><?= $sd ?> - <?= $ed ?></td>
                                        <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($leave['created_at'])) ?></td>
                                        <td><a href="index.php?c=leave&a=approvals" class="btn btn-sm btn-primary rounded-pill shadow-sm" style="font-size:11px;">พิจารณา</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5 col-lg-6">
            <div class="rp-card rp-dashboard-card h-100">
                <div class="rp-card__header">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-secondary me-2"></i> ประวัติการทำรายการล่าสุด</h6>
                    <a href="index.php?c=logs" class="btn btn-sm btn-light rounded-pill px-3" style="font-size:12px;">ดูทั้งหมด</a>
                </div>
                <div class="rp-card__body">
                    <?php if(empty($recent_logs)): ?>
                        <div class="text-center text-muted py-4 opacity-50"><i class="bi bi-journal-x fs-2 d-block mb-2"></i> ไม่มีประวัติการใช้งาน</div>
                    <?php else: ?>
                        <ul class="timeline">
                            <?php foreach($recent_logs as $log): 
                                // จัดการสีไอคอนตามประเภท Log
                                $action_color = 'primary';
                                if($log['action'] == 'DELETE') $action_color = 'danger';
                                elseif($log['action'] == 'CREATE') $action_color = 'success';
                                elseif($log['action'] == 'UPDATE') $action_color = 'warning';
                            ?>
                            <li class="timeline-item">
                                <div class="fw-bold text-dark" style="font-size: 13.5px;">
                                    <?= htmlspecialchars($log['user_name']) ?>
                                    <span class="badge bg-<?= $action_color ?> ms-1" style="font-size: 9px;"><?= htmlspecialchars($log['action']) ?></span>
                                </div>
                                <div class="text-muted mb-1" style="font-size: 12.5px;"><?= htmlspecialchars($log['details']) ?></div>
                                <div class="text-secondary font-monospace" style="font-size: 10px;"><i class="bi bi-clock me-1"></i> <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// 🌟 ฟังก์ชันสำหรับเปิด Popup การแจ้งเตือนแบบสรุป
function showAlertPopup() {
    let htmlContent = `<div class="text-start mt-3 bg-light p-3 rounded-4">`;
    
    <?php if(!empty($risk_hospitals)): ?>
        htmlContent += `
        <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-white border border-danger border-opacity-25 rounded-4 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-danger text-white rounded-circle d-flex justify-content-center align-items-center shadow-sm" style="width: 45px; height: 45px;">
                    <i class="bi bi-hospital fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-danger mb-0">กำลังคนไม่เพียงพอวันนี้</h6>
                    <div class="text-muted small">มีผู้ปฏิบัติงาน &le; 1 คน</div>
                </div>
            </div>
            <h3 class="fw-black text-danger mb-0"><?= count($risk_hospitals) ?> <span class="fs-6 fw-normal text-muted">แห่ง</span></h3>
        </div>`;
    <?php endif; ?>

    <?php if(!empty($fatigue_staff)): ?>
        htmlContent += `
        <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-white border border-warning border-opacity-50 rounded-4 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-warning text-dark rounded-circle d-flex justify-content-center align-items-center shadow-sm" style="width: 45px; height: 45px;">
                    <i class="bi bi-heart-pulse fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">แจ้งเตือนความเหนื่อยล้า</h6>
                    <div class="text-muted small">บุคลากรทำงาน &gt; 24 กะ/เดือน</div>
                </div>
            </div>
            <h3 class="fw-black text-warning text-dark mb-0"><?= count($fatigue_staff) ?> <span class="fs-6 fw-normal text-muted">คน</span></h3>
        </div>`;
    <?php endif; ?>

    <?php if(!empty($waiting_hospitals)): ?>
        htmlContent += `
        <div class="d-flex align-items-center justify-content-between mb-2 p-3 bg-white border border-secondary border-opacity-25 rounded-4 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center shadow-sm" style="width: 45px; height: 45px;">
                    <i class="bi bi-clock-history fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-secondary mb-0">ยังไม่เริ่มจัดทำตารางเวร</h6>
                    <div class="text-muted small">รพ.สต. ที่ยังไม่ดำเนินการ</div>
                </div>
            </div>
            <h3 class="fw-black text-secondary mb-0"><?= count($waiting_hospitals) ?> <span class="fs-6 fw-normal text-muted">แห่ง</span></h3>
        </div>`;
    <?php endif; ?>

    htmlContent += `</div>`;

    Swal.fire({
        title: '<div class="border-bottom pb-3"><h5 class="fw-black text-danger mb-0"><i class="bi bi-bell-fill me-2 fs-4"></i>สรุปการแจ้งเตือนความเสี่ยง</h5><p class="text-muted small mb-0 mt-1 fw-normal">ภาพรวมรายการที่ต้องให้ความสนใจในระบบเดือนนี้</p></div>',
        html: htmlContent,
        width: '520px',
        showCloseButton: true,
        showCancelButton: true,
        confirmButtonText: 'ไปหน้าวิเคราะห์เชิงลึก <i class="bi bi-graph-up-arrow ms-1"></i>',
        cancelButtonText: 'ปิดหน้าต่าง',
        buttonsStyling: true,
        customClass: {
            popup: 'rounded-4 shadow-lg border-0',
            confirmButton: 'btn btn-danger rounded-pill px-4 fw-bold shadow-sm',
            cancelButton: 'btn btn-outline-secondary rounded-pill px-4 fw-bold bg-white border',
            closeButton: 'btn-close shadow-none'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'index.php?c=dashboard&a=alerts';
        }
    });
}

document.addEventListener("DOMContentLoaded", function() {
    
    // ==========================================
    // 1. กราฟโดนัท: สถานะตารางเวร
    // ==========================================
    const statusCtx = document.getElementById('rosterStatusChart')?.getContext('2d');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['อนุมัติแล้ว', 'รอตรวจสอบ', 'กำลังจัดทำ', 'ยังไม่ดำเนินการ'],
                datasets: [{
                    data: [<?= $status_counts['APPROVED'] ?>, <?= $status_counts['SUBMITTED'] ?>, <?= $status_counts['DRAFT'] ?>, <?= $status_counts['WAITING'] ?>],
                    backgroundColor: ['#22c55e', '#3b82f6', '#f59e0b', '#cbd5e1'],
                    borderWidth: 2, hoverOffset: 5
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { position: 'right', labels: { font: { family: "'Sarabun', sans-serif", size: 11 }, usePointStyle: true } }
                }
            }
        });
    }

    // ==========================================
    // 2. กราฟพาย: สถิติการลาหยุด
    // ==========================================
    const leaveCtx = document.getElementById('leaveTrendChart')?.getContext('2d');
    if (leaveCtx) {
        const lLabels = <?= json_encode($leave_trends_labels) ?>;
        const lData = <?= json_encode($leave_trends_data) ?>;
        new Chart(leaveCtx, {
            type: 'pie',
            data: {
                labels: lLabels,
                datasets: [{
                    data: lData,
                    backgroundColor: ['#0ea5e9', '#f43f5e', '#8b5cf6', '#10b981', '#f59e0b'],
                    borderWidth: 2, hoverOffset: 5
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { font: { family: "'Sarabun', sans-serif", size: 11 }, usePointStyle: true } }
                }
            }
        });
    }

    // ==========================================
    // 3. กราฟแท่ง: วิเคราะห์ภาระงาน Top 5
    // ==========================================
    const workloadCtx = document.getElementById('workloadChart')?.getContext('2d');
    if (workloadCtx) {
        const hNames = <?= json_encode(array_column($workload_data, 'short_name')) ?>;
        const hShifts = <?= json_encode(array_column($workload_data, 'total_shifts')) ?>;
        
        new Chart(workloadCtx, {
            type: 'bar',
            data: {
                labels: hNames,
                datasets: [{
                    label: 'จำนวนกะที่ปฏิบัติงานรวม',
                    data: hShifts,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1, borderRadius: 6, barPercentage: 0.5
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { font: { family: "'Sarabun', sans-serif" } } },
                    x: { grid: { display: false }, ticks: { font: { family: "'Sarabun', sans-serif", size: 11 } } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }
});
</script>