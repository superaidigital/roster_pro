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

<style>
    .bg-gradient-primary { background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: white; }
    .bg-gradient-success { background: linear-gradient(135deg, #10b981 0%, #22c55e 100%); color: white; }
    .bg-gradient-warning { background: linear-gradient(135deg, #f59e0b 0%, #eab308 100%); color: white; }
    .bg-gradient-danger { background: linear-gradient(135deg, #ef4444 0%, #f43f5e 100%); color: white; }
    .bg-gradient-info { background: linear-gradient(135deg, #06b6d4 0%, #0ea5e9 100%); color: white; }
    .bg-gradient-purple { background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); color: white; }

    .timeline { position: relative; padding-left: 30px; margin-bottom: 0; list-style: none; }
    .timeline::before { content: ''; position: absolute; top: 0; bottom: 0; left: 14px; width: 2px; background: #e2e8f0; }
    .timeline-item { position: relative; margin-bottom: 1.5rem; }
    .timeline-item::before { content: ''; position: absolute; left: -20px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #2563eb; border: 2px solid #fff; box-shadow: 0 0 0 2px #2563eb; }
    .timeline-item:last-child { margin-bottom: 0; }

    @keyframes ring {
      0%, 50%, 100% { transform: rotate(0); }
      10% { transform: rotate(12deg); }
      20% { transform: rotate(-8deg); }
      30% { transform: rotate(8deg); }
      40% { transform: rotate(-6deg); }
    }
    .bell-shake { animation: ring 2.4s ease-in-out infinite; display: inline-block; }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }

    .rp-exec-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.25rem;
        padding: 1.35rem 1.5rem;
        margin-bottom: 1rem;
        border: 1px solid #dbe7ee;
        border-radius: 1rem;
        background:
            radial-gradient(circle at 92% 10%, rgba(14,165,233,.10), transparent 12rem),
            linear-gradient(135deg, #ffffff 0%, #f7fbfd 100%);
        box-shadow: 0 .35rem 1.15rem rgba(15, 23, 42, .045);
    }
    .rp-exec-title {
        margin: 0 0 .3rem;
        color: #0f172a;
        font-size: clamp(1.25rem, 2vw, 1.65rem);
        font-weight: 800;
        letter-spacing: -.025em;
    }
    .rp-exec-subtitle {
        margin: 0;
        color: #64748b;
        font-size: .875rem;
    }
    .rp-exec-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: .6rem;
    }
    .rp-exec-chip {
        min-height: 2.65rem;
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        padding: .6rem .85rem;
        border: 1px solid #d7e3ea;
        border-radius: .75rem;
        background: #fff;
        color: #334155;
        font-size: .85rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .rp-exec-chip i { color: #2563eb; font-size: 1rem; }

    .rp-dashboard-section-label {
        margin: 0 0 .65rem;
        color: #64748b;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .rp-action-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: .7rem;
        min-height: 4.55rem;
        height: 100%;
        padding: .8rem .85rem;
        border: 1px solid #dbe5eb;
        border-radius: .9rem;
        background: #fff;
        color: #1e293b;
        text-decoration: none;
        box-shadow: 0 .2rem .65rem rgba(15,23,42,.025);
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease, background .18s ease;
    }
    .rp-action-card:hover {
        color: #0f172a;
        border-color: #b8cfdd;
        background: #fbfdff;
        box-shadow: 0 .45rem 1rem rgba(15,23,42,.055);
        transform: translateY(-1px);
    }
    .rp-action-card:focus-visible {
        outline: .18rem solid rgba(37,99,235,.35);
        outline-offset: .15rem;
    }
    .rp-action-icon {
        display: grid;
        place-items: center;
        width: 2.45rem;
        height: 2.45rem;
        flex: 0 0 2.45rem;
        border-radius: .72rem;
        font-size: 1.08rem;
    }
    .rp-tone-blue { color: #1d4ed8; background: #eff6ff; }
    .rp-tone-cyan { color: #0369a1; background: #ecfeff; }
    .rp-tone-green { color: #15803d; background: #f0fdf4; }
    .rp-tone-amber { color: #b45309; background: #fffbeb; }
    .rp-tone-red { color: #b91c1c; background: #fef2f2; }
    .rp-tone-violet { color: #6d28d9; background: #f5f3ff; }
    .rp-tone-slate { color: #475569; background: #f1f5f9; }

    .rp-action-copy { min-width: 0; display: grid; gap: .12rem; }
    .rp-action-title {
        color: #1e293b;
        font-size: .86rem;
        font-weight: 800;
        line-height: 1.25;
    }
    .rp-action-meta {
        color: #94a3b8;
        font-size: .7rem;
        line-height: 1.25;
    }
    .rp-action-badge {
        position: absolute;
        top: .45rem;
        right: .45rem;
        min-width: 1.35rem;
        height: 1.35rem;
        display: inline-grid;
        place-items: center;
        padding: 0 .35rem;
        border-radius: 999px;
        background: #dc2626;
        color: #fff;
        border: 2px solid #fff;
        font-size: .65rem;
        font-weight: 800;
    }
    .rp-action-badge.warning { background: #d97706; }

    .rp-alert-center {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        width: 100%;
        margin: 0 0 1rem;
        padding: .9rem 1rem;
        border: 1px solid #fecaca;
        border-left: 4px solid #dc2626;
        border-radius: .95rem;
        background: #fff7f7;
        color: #1e293b;
        cursor: pointer;
        text-align: left;
    }
    .rp-alert-center:hover { background: #fff1f2; border-color: #fca5a5; }
    .rp-alert-left { display: flex; align-items: center; gap: .8rem; min-width: 0; }
    .rp-alert-icon {
        display: grid;
        place-items: center;
        width: 2.55rem;
        height: 2.55rem;
        flex: 0 0 2.55rem;
        border-radius: .75rem;
        background: #dc2626;
        color: #fff;
        font-size: 1.05rem;
    }
    .rp-alert-title { margin: 0 0 .1rem; color: #b91c1c; font-size: .87rem; font-weight: 800; }
    .rp-alert-text { margin: 0; color: #64748b; font-size: .78rem; }
    .rp-alert-cta {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        color: #b91c1c;
        font-size: .78rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .rp-kpi-card {
        position: relative;
        height: 100%;
        min-height: 5.7rem;
        overflow: hidden;
        border: 1px solid #dbe5eb;
        border-radius: .95rem;
        background: #fff;
        box-shadow: 0 .2rem .65rem rgba(15,23,42,.025);
    }
    .rp-kpi-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: .2rem;
        background: var(--kpi-accent, #2563eb);
    }
    .rp-kpi-card .card-body {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .7rem;
        min-height: 5.7rem;
        padding: .9rem 1rem !important;
    }
    .rp-kpi-label {
        margin: 0 0 .25rem;
        color: #64748b;
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .035em;
        text-transform: uppercase;
    }
    .rp-kpi-value {
        margin: 0;
        color: #0f172a;
        font-size: clamp(1.35rem, 1.9vw, 1.7rem);
        font-weight: 800;
        letter-spacing: -.025em;
        line-height: 1.05;
    }
    .rp-kpi-icon {
        display: grid;
        place-items: center;
        width: 2.55rem;
        height: 2.55rem;
        flex: 0 0 2.55rem;
        border-radius: .75rem;
        font-size: 1.05rem;
    }

    @media (max-width: 991.98px) {
        .rp-exec-hero { align-items: flex-start; flex-direction: column; }
        .rp-exec-actions { width: 100%; justify-content: flex-start; }
        .rp-alert-center { align-items: flex-start; }
    }
    @media (max-width: 575.98px) {
        .rp-dashboard-page { padding-inline: .1rem !important; }
        .rp-exec-hero { padding: 1rem; }
        .rp-exec-actions { display: grid; grid-template-columns: 1fr; }
        .rp-exec-chip { width: 100%; justify-content: center; }
        .rp-action-card { min-height: 4.25rem; padding: .7rem; }
        .rp-action-meta { display: none; }
        .rp-alert-center { flex-direction: column; }
        .rp-alert-cta { padding-left: 3.35rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .bell-shake { animation: none; }
        .rp-action-card { transition: none; }
    }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100 rp-dashboard-page">
    
    <section class="rp-exec-hero" aria-labelledby="execDashboardTitle">
        <div>
            <h2 class="rp-exec-title" id="execDashboardTitle">ภาพรวมระบบ</h2>
            <p class="rp-exec-subtitle">
                Executive Dashboard · ยินดีต้อนรับ
                <span class="fw-bold text-primary"><?= htmlspecialchars($_SESSION['user']['name'] ?? 'ผู้ดูแลระบบ', ENT_QUOTES, 'UTF-8') ?></span>
            </p>
        </div>
        <div class="rp-exec-actions">
            <a href="index.php?c=dashboard&a=map_view" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                <span>แสดงแผนที่ รพ.สต.</span>
            </a>
            <div class="rp-exec-chip" aria-label="วันที่ปัจจุบัน">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <span>วันนี้ <?= date('d') ?> <?= $thai_months[(int)date('m')] ?> <?= date('Y') + 543 ?></span>
            </div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="mb-3" aria-labelledby="quickActionsTitle">
        <h3 class="rp-dashboard-section-label" id="quickActionsTitle">เมนูลัด</h3>
        <div class="row g-2 g-md-3">
            <div class="col-6 col-lg-4 col-xl-2">
                <a href="index.php?c=roster" class="rp-action-card">
                    <span class="rp-action-icon rp-tone-green"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span>
                    <span class="rp-action-copy">
                        <span class="rp-action-title">จัดการตารางเวร</span>
                        <span class="rp-action-meta">สร้างและจัดเวร</span>
                    </span>
                </a>
            </div>
            <div class="col-6 col-lg-4 col-xl-2">
                <a href="index.php?c=leave&a=approvals" class="rp-action-card">
                    <span class="rp-action-icon rp-tone-red"><i class="bi bi-file-earmark-check" aria-hidden="true"></i></span>
                    <span class="rp-action-copy">
                        <span class="rp-action-title">พิจารณาใบลา</span>
                        <span class="rp-action-meta">ตรวจคำขออนุมัติ</span>
                    </span>
                    <?php if ($pending_leaves > 0): ?>
                        <span class="rp-action-badge" aria-label="<?= (int)$pending_leaves ?> ใบลารออนุมัติ"><?= (int)$pending_leaves ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="col-6 col-lg-4 col-xl-2">
                <a href="index.php?c=swap" class="rp-action-card">
                    <span class="rp-action-icon rp-tone-amber"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></span>
                    <span class="rp-action-copy">
                        <span class="rp-action-title">คำขอแลกเวร</span>
                        <span class="rp-action-meta">ตรวจและอนุมัติคำขอ</span>
                    </span>
                    <?php if ($pending_swaps > 0): ?>
                        <span class="rp-action-badge warning" aria-label="<?= (int)$pending_swaps ?> คำขอแลกเวร"><?= (int)$pending_swaps ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="col-6 col-lg-4 col-xl-2">
                <a href="index.php?c=staff" class="rp-action-card">
                    <span class="rp-action-icon rp-tone-blue"><i class="bi bi-people" aria-hidden="true"></i></span>
                    <span class="rp-action-copy">
                        <span class="rp-action-title">ฐานข้อมูลบุคลากร</span>
                        <span class="rp-action-meta">ข้อมูลและสิทธิ์ผู้ใช้</span>
                    </span>
                </a>
            </div>
            <div class="col-6 col-lg-4 col-xl-2">
                <a href="index.php?c=report" class="rp-action-card">
                    <span class="rp-action-icon rp-tone-cyan"><i class="bi bi-bar-chart" aria-hidden="true"></i></span>
                    <span class="rp-action-copy">
                        <span class="rp-action-title">รายงานสถิติ</span>
                        <span class="rp-action-meta">สรุปและวิเคราะห์ข้อมูล</span>
                    </span>
                </a>
            </div>
            <div class="col-6 col-lg-4 col-xl-2">
                <a href="index.php?c=settings" class="rp-action-card">
                    <span class="rp-action-icon rp-tone-slate"><i class="bi bi-sliders" aria-hidden="true"></i></span>
                    <span class="rp-action-copy">
                        <span class="rp-action-title">ตั้งค่าระบบ</span>
                        <span class="rp-action-meta">กำหนดค่าการใช้งาน</span>
                    </span>
                </a>
            </div>
        </div>
    </section>

    <!-- Alert Center -->
    <?php
    $total_alerts = count($risk_hospitals) + count($fatigue_staff) + count($waiting_hospitals);
    if ($total_alerts > 0):
    ?>
    <button type="button" class="rp-alert-center" onclick="showAlertPopup()" aria-label="เปิดศูนย์แจ้งเตือนความเสี่ยง <?= (int)$total_alerts ?> รายการ">
        <span class="rp-alert-left">
            <span class="rp-alert-icon"><i class="bi bi-bell-fill bell-shake" aria-hidden="true"></i></span>
            <span>
                <span class="rp-alert-title d-block">ศูนย์แจ้งเตือนความเสี่ยง</span>
                <span class="rp-alert-text d-block">มีรายการที่ควรตรวจสอบ <?= number_format($total_alerts) ?> รายการ</span>
            </span>
        </span>
        <span class="rp-alert-cta">
            ดูรายละเอียด
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </span>
    </button>
    <?php endif; ?>

    <!-- KPI Stats -->
    <section class="mb-4" aria-labelledby="kpiTitle">
        <h3 class="rp-dashboard-section-label" id="kpiTitle">ตัวชี้วัดสำคัญ</h3>
        <div class="row g-2 g-md-3">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="rp-kpi-card" style="--kpi-accent:#0891b2;">
                    <div class="card-body">
                        <div>
                            <p class="rp-kpi-label">หน่วยบริการ</p>
                            <p class="rp-kpi-value"><?= number_format($total_hospitals) ?></p>
                        </div>
                        <span class="rp-kpi-icon rp-tone-cyan"><i class="bi bi-hospital" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="rp-kpi-card" style="--kpi-accent:#2563eb;">
                    <div class="card-body">
                        <div>
                            <p class="rp-kpi-label">บุคลากร</p>
                            <p class="rp-kpi-value"><?= number_format($total_staff) ?></p>
                        </div>
                        <span class="rp-kpi-icon rp-tone-blue"><i class="bi bi-people" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="rp-kpi-card" style="--kpi-accent:#16a34a;">
                    <div class="card-body">
                        <div>
                            <p class="rp-kpi-label">ขึ้นเวรวันนี้</p>
                            <p class="rp-kpi-value"><?= number_format($on_duty_today) ?></p>
                        </div>
                        <span class="rp-kpi-icon rp-tone-green"><i class="bi bi-person-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="rp-kpi-card" style="--kpi-accent:#dc2626;">
                    <div class="card-body">
                        <div>
                            <p class="rp-kpi-label">ใบลารออนุมัติ</p>
                            <p class="rp-kpi-value <?= $pending_leaves > 0 ? 'text-danger' : '' ?>"><?= number_format($pending_leaves) ?></p>
                        </div>
                        <span class="rp-kpi-icon rp-tone-red"><i class="bi bi-file-earmark-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="rp-kpi-card" style="--kpi-accent:#d97706;">
                    <div class="card-body">
                        <div>
                            <p class="rp-kpi-label">ขอแลกเวร</p>
                            <p class="rp-kpi-value <?= $pending_swaps > 0 ? 'text-warning' : '' ?>"><?= number_format($pending_swaps) ?></p>
                        </div>
                        <span class="rp-kpi-icon rp-tone-amber"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="rp-kpi-card" style="--kpi-accent:#7c3aed;">
                    <div class="card-body">
                        <div>
                            <p class="rp-kpi-label">งบประมาณ (บาท)</p>
                            <p class="rp-kpi-value" style="font-size:clamp(1.15rem,1.6vw,1.45rem);"><?= number_format($estimated_budget) ?></p>
                        </div>
                        <span class="rp-kpi-icon rp-tone-violet"><i class="bi bi-cash-stack" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Charts -->
    <div class="row g-4 mb-4">
        <div class="col-xl-4 col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-pie-chart-fill text-primary me-2"></i> สถานะตารางเวร</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container"><canvas id="rosterStatusChart"></canvas></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-steps text-info me-2"></i> สถิติการลาหยุดเดือนนี้</h6>
                </div>
                <div class="card-body">
                    <?php if(empty($leave_trends_data)): ?>
                        <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted opacity-50">
                            <i class="bi bi-calendar-x fs-1 mb-2"></i><p>ไม่มีข้อมูลการลาหยุดที่อนุมัติแล้ว</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container"><canvas id="leaveTrendChart"></canvas></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-12">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-line-fill text-warning me-2"></i> Top 5 ภาระงาน (กะ/เดือน)</h6>
                </div>
                <div class="card-body">
                    <?php if(empty($workload_data)): ?>
                        <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted opacity-50">
                            <i class="bi bi-graph-down fs-1 mb-2"></i><p>ยังไม่มีข้อมูลการจัดเวร</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container"><canvas id="workloadChart"></canvas></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 แถวใหม่: สรุปการใช้งานระบบประจำวัน (Daily Usage) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-activity text-primary me-2"></i> รายงานการเข้าใช้งานระบบประจำวัน</h6>
                        <p class="text-muted small mb-0">ข้อมูลความเคลื่อนไหวแยกตามหน่วยบริการ (อัปเดตแบบเรียลไทม์)</p>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-calendar-check me-1"></i> ข้อมูล ณ วันนี้: <?= date('d/m/Y') ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive custom-scrollbar" style="max-height: 400px;">
                        <table class="table table-modern mb-0 table-hover align-middle">
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
            <div class="card dashboard-card h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-envelope-exclamation-fill text-danger me-2"></i> คำขอลาล่าสุด (รออนุมัติ)</h6>
                    <a href="index.php?c=leave&a=approvals" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="font-size:12px;">จัดการใบลารออนุมัติ (<?= $pending_leaves ?>)</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-modern mb-0 table-hover text-center align-middle">
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
            <div class="card dashboard-card h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-secondary me-2"></i> ประวัติการทำรายการล่าสุด</h6>
                    <a href="index.php?c=logs" class="btn btn-sm btn-light rounded-pill px-3" style="font-size:12px;">ดูทั้งหมด</a>
                </div>
                <div class="card-body">
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