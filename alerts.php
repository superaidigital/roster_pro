<?php
// ที่อยู่ไฟล์: views/dashboard/alerts.php

$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$current_month_th = $thai_months[(int)date('m')] . ' ' . (date('Y') + 543);
$today_th = date('d') . ' ' . $thai_months[(int)date('m')] . ' ' . (date('Y') + 543);

// 🌟 กรองเอา "ส่วนกลาง" ออกจากการแจ้งเตือนความเสี่ยง
$filtered_fatigue = [];
foreach ($fatigue_staff ?? [] as $f) {
    if (mb_strpos($f['hosp_name'] ?? '', 'ส่วนกลาง') === false) {
        $filtered_fatigue[] = $f;
    }
}
$fatigue_staff = $filtered_fatigue;

$filtered_risk = [];
foreach ($risk_hospitals ?? [] as $r) {
    if (mb_strpos($r['name'] ?? '', 'ส่วนกลาง') === false) {
        $filtered_risk[] = $r;
    }
}
$risk_hospitals = $filtered_risk;

$filtered_waiting = [];
foreach ($waiting_hospitals ?? [] as $w) {
    if (mb_strpos($w['name'] ?? '', 'ส่วนกลาง') === false) {
        $filtered_waiting[] = $w;
    }
}
$waiting_hospitals = $filtered_waiting;

// 🌟 นับจำนวนที่ผ่านการกรองแล้ว
$total_fatigue = count($fatigue_staff);
$total_risk = count($risk_hospitals);
$total_waiting = count($waiting_hospitals);
$grand_total = $total_fatigue + $total_risk + $total_waiting;
?>

<style>
    body { background-color: #f4f6f9; font-family: 'Sarabun', sans-serif; }
    .alert-card { border: none; border-radius: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: #ffffff; overflow: hidden; }
    .table-modern th { font-weight: 600; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 13px; text-transform: uppercase; padding: 1rem; }
    .table-modern td { vertical-align: middle; font-size: 14.5px; border-bottom: 1px solid #f1f5f9; padding: 1rem; }
    .table-modern tbody tr:hover td { background-color: #f8fafc; }
    
    .icon-header { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    
    /* 🌟 สไตล์เพิ่มเติมสำหรับปุ่มย่อขยาย */
    .card-header-toggle { cursor: pointer; transition: background-color 0.2s ease; user-select: none; }
    .card-header-toggle:hover { filter: brightness(0.97); }
    .toggle-icon { transition: transform 0.3s ease; }
    /* หมุนลูกศรเมื่อถูกย่อ (collapse) */
    .collapsed .toggle-icon { transform: rotate(180deg); }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100">
    
    <!-- 🌟 ส่วนหัวแบบกลับหน้าหลักได้ -->
    <div class="d-flex flex-column flex-md-row align-items-md-center mb-4 gap-3">
        <a href="index.php?c=dashboard" class="btn btn-white rounded-circle shadow-sm border" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; transition: 0.2s;">
            <i class="bi bi-arrow-left fs-5 text-dark"></i>
        </a>
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size: 13px;">
                    <li class="breadcrumb-item"><a href="index.php?c=dashboard" class="text-decoration-none">หน้าหลัก (Dashboard)</a></li>
                    <li class="breadcrumb-item active text-danger fw-bold">ศูนย์แจ้งเตือนความเสี่ยง</li>
                </ol>
            </nav>
            <h3 class="fw-black text-dark mb-0">วิเคราะห์ข้อมูลเชิงลึก (Risk Analysis)</h3>
        </div>
    </div>

    <!-- 🌟 สรุปภาพรวม -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="alert-card card h-100 border-start border-danger border-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-bold mb-1 small text-uppercase">วิกฤต: คนไม่พอขึ้นเวร</p>
                        <h2 class="fw-black text-danger mb-0"><?= $total_risk ?> <span class="fs-6 text-muted fw-normal">แห่ง</span></h2>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex justify-content-center align-items-center fs-2" style="width: 65px; height: 65px;"><i class="bi bi-hospital"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="alert-card card h-100 border-start border-warning border-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-bold mb-1 small text-uppercase">เฝ้าระวัง: ความเหนื่อยล้า</p>
                        <h2 class="fw-black text-warning text-dark mb-0"><?= $total_fatigue ?> <span class="fs-6 text-muted fw-normal">คน</span></h2>
                    </div>
                    <div class="bg-warning bg-opacity-25 text-dark rounded-circle d-flex justify-content-center align-items-center fs-2" style="width: 65px; height: 65px;"><i class="bi bi-heart-pulse"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="alert-card card h-100 border-start border-secondary border-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-bold mb-1 small text-uppercase">ติดตาม: ตารางเวรล่าช้า</p>
                        <h2 class="fw-black text-secondary mb-0"><?= $total_waiting ?> <span class="fs-6 text-muted fw-normal">แห่ง</span></h2>
                    </div>
                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex justify-content-center align-items-center fs-2" style="width: 65px; height: 65px;"><i class="bi bi-clock-history"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🚨 วิเคราะห์ 1: กำลังคนไม่เพียงพอ -->
    <div class="card alert-card mb-4 border border-danger border-opacity-25">
        <div class="card-header card-header-toggle bg-danger bg-opacity-10 py-3 d-flex align-items-center justify-content-between border-bottom border-danger border-opacity-25" data-bs-toggle="collapse" data-bs-target="#collapseRisk1" aria-expanded="true">
            <div class="d-flex align-items-center">
                <div class="icon-header bg-danger text-white me-3 shadow-sm"><i class="bi bi-hospital"></i></div>
                <div>
                    <h5 class="fw-bold text-danger mb-0">1. ความเสี่ยงด้านกำลังคน (Manpower Shortage)</h5>
                    <p class="text-danger opacity-75 small mb-0">แสดงรายชื่อหน่วยบริการที่มีผู้ปฏิบัติงานในวันนี้ (<?= $today_th ?>) น้อยกว่าหรือเท่ากับ 1 คน</p>
                </div>
            </div>
            <i class="bi bi-chevron-up toggle-icon fs-4 text-danger opacity-75"></i>
        </div>
        <div class="collapse show" id="collapseRisk1">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-modern mb-0 text-center">
                        <thead>
                            <tr>
                                <th width="10%">ลำดับ</th>
                                <th class="text-start" width="40%">หน่วยบริการ (รพ.สต.)</th>
                                <th width="25%">จำนวนคนปฏิบัติงานวันนี้</th>
                                <th width="25%">สถานะความเสี่ยง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($total_risk == 0): ?>
                                <tr><td colspan="4" class="py-5 text-success"><i class="bi bi-check-circle-fill fs-2 d-block mb-2"></i> ไม่พบความเสี่ยงด้านกำลังคนในวันนี้</td></tr>
                            <?php else: $i=1; foreach($risk_hospitals as $risk): ?>
                                <tr class="bg-danger bg-opacity-10">
                                    <td class="text-muted"><?= $i++ ?></td>
                                    <td class="text-start fw-bold text-dark"><i class="bi bi-building text-danger me-2"></i><?= htmlspecialchars($risk['name']) ?></td>
                                    <td><span class="badge bg-danger rounded-pill fs-6 px-3"><?= $risk['on_duty'] ?> คน</span></td>
                                    <td><span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> วิกฤต (บุคลากรไม่พอ)</span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 🚨 วิเคราะห์ 2: ความเหนื่อยล้า -->
    <div class="card alert-card mb-4 border border-warning border-opacity-50">
        <div class="card-header card-header-toggle bg-warning bg-opacity-10 py-3 d-flex align-items-center justify-content-between border-bottom border-warning border-opacity-25" data-bs-toggle="collapse" data-bs-target="#collapseRisk2" aria-expanded="true">
            <div class="d-flex align-items-center">
                <div class="icon-header bg-warning text-dark me-3 shadow-sm"><i class="bi bi-heart-pulse"></i></div>
                <div>
                    <h5 class="fw-bold text-dark mb-0">2. แจ้งเตือนบุคลากรที่มีความเหนื่อยล้า (Staff Fatigue)</h5>
                    <p class="text-muted small mb-0">พนักงานที่ขึ้นเวรสะสมมากกว่า 24 กะ ในเดือน <?= $current_month_th ?> (อาจส่งผลต่อประสิทธิภาพการทำงาน)</p>
                </div>
            </div>
            <i class="bi bi-chevron-up toggle-icon fs-4 text-dark opacity-50"></i>
        </div>
        <div class="collapse show" id="collapseRisk2">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-modern mb-0 text-center">
                        <thead>
                            <tr>
                                <th width="10%">ลำดับ</th>
                                <th class="text-start" width="30%">ชื่อ-นามสกุล</th>
                                <th class="text-start" width="20%">ตำแหน่ง</th>
                                <th class="text-start" width="20%">หน่วยบริการ</th>
                                <th width="20%">จำนวนกะสะสม (เดือนนี้)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($total_fatigue == 0): ?>
                                <tr><td colspan="5" class="py-5 text-success"><i class="bi bi-emoji-smile fs-2 d-block mb-2"></i> ไม่มีพนักงานที่ขึ้นเวรเกินกำหนด</td></tr>
                            <?php else: $i=1; foreach($fatigue_staff as $fatigue): ?>
                                <tr class="bg-warning bg-opacity-10">
                                    <td class="text-muted"><?= $i++ ?></td>
                                    <td class="text-start fw-bold text-dark"><i class="bi bi-person-circle text-warning text-dark me-2"></i><?= htmlspecialchars($fatigue['name']) ?></td>
                                    <td class="text-start text-muted small"><?= htmlspecialchars($fatigue['position'] ?: '-') ?></td>
                                    <td class="text-start"><span class="badge bg-white text-dark border"><?= htmlspecialchars($fatigue['hosp_name']) ?></span></td>
                                    <td>
                                        <span class="badge bg-warning text-dark border border-warning px-3 py-2 rounded-pill shadow-sm" style="font-size:14px;">
                                            <i class="bi bi-arrow-up-circle me-1"></i> <?= $fatigue['shift_count'] ?> กะ
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 🚨 วิเคราะห์ 3: การจัดการล่าช้า -->
    <div class="card alert-card border border-secondary border-opacity-25">
        <div class="card-header card-header-toggle bg-secondary bg-opacity-10 py-3 d-flex align-items-center justify-content-between border-bottom border-secondary border-opacity-25" data-bs-toggle="collapse" data-bs-target="#collapseRisk3" aria-expanded="true">
            <div class="d-flex align-items-center">
                <div class="icon-header bg-secondary text-white me-3 shadow-sm"><i class="bi bi-clock-history"></i></div>
                <div>
                    <h5 class="fw-bold text-secondary mb-0">3. การจัดทำตารางเวรล่าช้า (Operation Delay)</h5>
                    <p class="text-muted small mb-0">หน่วยบริการที่ยังไม่เริ่มต้นจัดทำตารางเวร ประจำเดือน <?= $current_month_th ?></p>
                </div>
            </div>
            <i class="bi bi-chevron-up toggle-icon fs-4 text-secondary opacity-75"></i>
        </div>
        <div class="collapse show" id="collapseRisk3">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-modern mb-0 text-center">
                        <thead>
                            <tr>
                                <th width="10%">ลำดับ</th>
                                <th class="text-start" width="40%">หน่วยบริการ (รพ.สต.)</th>
                                <th width="25%">สถานะปัจจุบัน</th>
                                <th width="25%">ข้อเสนอแนะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($total_waiting == 0): ?>
                                <tr><td colspan="4" class="py-5 text-success"><i class="bi bi-calendar-check-fill fs-2 d-block mb-2"></i> ทุกหน่วยบริการเริ่มจัดทำตารางเวรแล้ว</td></tr>
                            <?php else: $i=1; foreach($waiting_hospitals as $wait): ?>
                                <tr>
                                    <td class="text-muted"><?= $i++ ?></td>
                                    <td class="text-start fw-bold text-dark"><i class="bi bi-building text-secondary me-2 opacity-50"></i><?= htmlspecialchars($wait['name']) ?></td>
                                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary border px-3 py-2 rounded-pill"><i class="bi bi-dash-circle-dotted me-1"></i> ยังไม่ดำเนินการ</span></td>
                                    <td><a href="index.php?c=roster&hospital_id=<?= $wait['id'] ?>&month=<?= date('Y-m') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">แจ้งเตือน / จัดตารางให้</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>