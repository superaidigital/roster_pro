<?php
// ที่อยู่ไฟล์: views/hr/dashboard.php

// 🌟 ป้องกัน Error Undefined Variable กรณีถูกโหลดจากหน้า Dashboard หลัก
$stats = $stats ?? ['total_active' => 0, 'total_civil_servant' => 0, 'total_contractor' => 0, 'total_inactive' => 0];
$birthdays = $birthdays ?? [];
$expiring_licenses = $expiring_licenses ?? [];
$gender_stats = $gender_stats ?? [];
$avg_score_comp = $avg_score_comp ?? 0;
$perfect_staff_comp = $perfect_staff_comp ?? 0;
$total_staff_comp = $total_staff_comp ?? 0;
$pending_leaves = $pending_leaves ?? 0; // เผื่อกรณีขาดหาย
?>
<!-- โหลด Chart.js สำหรับแสดงกราฟ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .card-dashboard { border: none; border-radius: 1.25rem; transition: transform 0.2s; background: #fff; }
    .card-dashboard:hover { transform: translateY(-5px); }
    .quick-link-btn { transition: all 0.2s; border: 1px solid #f1f5f9; }
    .quick-link-btn:hover { background-color: #f8fafc; border-color: #3b82f6; color: #3b82f6 !important; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
    .list-group-item { border-left: none; border-right: none; padding: 0.75rem 1.25rem; }
    .progress-custom { height: 10px; border-radius: 10px; background-color: #e2e8f0; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    
    <!-- ยินดีต้อนรับและ Quick Actions -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="h4 text-dark mb-1 fw-bold">ระบบบริหารทรัพยากรบุคคล (HR Overview)</h2>
            <p class="text-muted mb-0"><i class="bi bi-calendar-check me-1"></i> ข้อมูลประจำวันที่ <?= date('d/m/') . (date('Y')+543) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?c=users" class="btn btn-primary rounded-pill px-3 fw-bold shadow-sm">
                <i class="bi bi-person-plus-fill me-1"></i> เพิ่มบุคลากร
            </a>
            <a href="index.php?c=hr&a=payroll" class="btn btn-success rounded-pill px-3 fw-bold shadow-sm">
                <i class="bi bi-cash-stack me-1"></i> ทำเรื่องเบิกค่าเวร
            </a>
        </div>
    </div>

    <!-- แถวที่ 1: สถิติตัวเลขหลัก -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-dashboard shadow-sm p-3 border-start border-primary border-5">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4 me-3">
                        <i class="bi bi-people-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-bold">บุคลากรทั้งหมด</div>
                        <h3 class="fw-black mb-0"><?= number_format($stats['total_active']) ?> <small class="fs-6 fw-normal">คน</small></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-dashboard shadow-sm p-3 border-start border-success border-5">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-4 me-3">
                        <i class="bi bi-person-badge-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-bold">ข้าราชการ</div>
                        <h3 class="fw-black mb-0"><?= number_format($stats['total_civil_servant']) ?> <small class="fs-6 fw-normal">คน</small></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-dashboard shadow-sm p-3 border-start border-info border-5">
                <div class="d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-4 me-3">
                        <i class="bi bi-briefcase-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-bold">ลูกจ้าง/พนักงาน</div>
                        <h3 class="fw-black mb-0"><?= number_format($stats['total_contractor']) ?> <small class="fs-6 fw-normal">คน</small></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-dashboard shadow-sm p-3 border-start border-danger border-5">
                <div class="d-flex align-items-center">
                    <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-4 me-3">
                        <i class="bi bi-person-x-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-bold">พ้นสภาพปีนี้</div>
                        <h3 class="fw-black mb-0"><?= number_format($stats['total_inactive']) ?> <small class="fs-6 fw-normal">คน</small></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- แถวที่ 2 ซ้าย: กราฟสถิติและวันเกิด -->
        <div class="col-lg-8">
            <div class="row g-4">
                <!-- กราฟสัดส่วนเพศ -->
                <div class="col-md-6">
                    <div class="card card-dashboard shadow-sm h-100 p-4">
                        <h6 class="fw-bold mb-4 border-bottom pb-2">สัดส่วนเพศบุคลากร</h6>
                        <div style="height: 200px;">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>
                </div>
                <!-- รายการวันเกิด -->
                <div class="col-md-6">
                    <div class="card card-dashboard shadow-sm h-100 overflow-hidden">
                        <div class="p-4 border-bottom">
                            <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-cake2-fill me-2"></i>วันเกิดบุคลากรเดือนนี้</h6>
                        </div>
                        <div class="list-group list-group-flush custom-scrollbar" style="max-height: 250px; overflow-y: auto;">
                            <?php if(empty($birthdays)): ?>
                                <div class="p-4 text-center text-muted small">เดือนนี้ไม่มีวันเกิดพนักงาน</div>
                            <?php else: foreach($birthdays as $b): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2 me-3 small">
                                            <?= date('d', strtotime($b['birth_date'])) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark small"><?= htmlspecialchars($b['name']) ?></div>
                                            <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($b['short_name'] ?? 'ส่วนกลาง') ?></div>
                                        </div>
                                    </div>
                                    <?php if(date('d-m', strtotime($b['birth_date'])) == date('d-m')): ?>
                                        <span class="badge bg-warning text-dark rounded-pill shadow-sm">วันนี้! 🎂</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- แจ้งเตือนใบประกอบวิชาชีพ (ย้ายมาไว้ข้างล่างเพื่อให้ดูเด่น) -->
            <div class="card card-dashboard shadow-sm mt-4 overflow-hidden">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-octagon-fill me-2"></i>ใบประกอบวิชาชีพที่ใกล้หมดอายุ (ภายใน 60 วัน)</h6>
                </div>
                <div class="card-body p-0">
                    <?php if(empty($expiring_licenses)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-shield-check fs-1 text-success d-block mb-2 opacity-25"></i>
                            ข้อมูลปกติ ยังไม่มีใบประกอบวิชาชีพหมดอายุ
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th class="ps-4">ชื่อบุคลากร</th>
                                        <th>ชื่อใบประกอบวิชาชีพ</th>
                                        <th>วันหมดอายุ</th>
                                        <th class="text-center">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($expiring_licenses as $lic): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($lic['user_name']) ?></td>
                                            <td class="small text-muted"><?= htmlspecialchars($lic['license_name']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($lic['expire_date'])) ?></td>
                                            <td class="text-center">
                                                <?php if($lic['days_left'] <= 0): ?>
                                                    <span class="badge bg-danger px-3 rounded-pill">หมดอายุแล้ว!</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark px-3 rounded-pill">เหลือ <?= $lic['days_left'] ?> วัน</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- แถวที่ 2 ขวา: สิทธิ์วันลา, ความสมบูรณ์ และลิงก์ด่วน -->
        <div class="col-lg-4">
            
            <!-- 🌟 สรุปความสมบูรณ์ของประวัติ (ส่วนที่เพิ่มใหม่) 🌟 -->
            <div class="card card-dashboard shadow-sm mb-4 p-4 border border-primary border-opacity-10">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0">ความสมบูรณ์ประวัติบุคลากร</h6>
                    <i class="bi bi-clipboard-check text-primary fs-5"></i>
                </div>
                <div class="text-center mt-2 mb-3">
                    <?php 
                        $pg_color = 'text-danger'; $bg_color = 'bg-danger';
                        if($avg_score_comp >= 50) { $pg_color = 'text-warning'; $bg_color = 'bg-warning'; }
                        if($avg_score_comp >= 80) { $pg_color = 'text-primary'; $bg_color = 'bg-primary'; }
                        if($avg_score_comp == 100) { $pg_color = 'text-success'; $bg_color = 'bg-success'; }
                    ?>
                    <div class="display-4 fw-black <?= $pg_color ?> mb-1">
                        <?= $avg_score_comp ?>%
                    </div>
                    <p class="text-muted small mb-0">ค่าเฉลี่ยความสมบูรณ์ทั้งองค์กร</p>
                </div>
                <div class="progress progress-custom mb-3">
                    <div class="progress-bar <?= $bg_color ?>" style="width: <?= $avg_score_comp ?>%"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-4 pb-2 border-bottom">
                    <span>สมบูรณ์ 100% แล้ว</span>
                    <span class="fw-bold text-dark"><?= number_format($perfect_staff_comp) ?> <span class="text-muted fw-normal">/ <?= number_format($total_staff_comp) ?> คน</span></span>
                </div>
                <a href="index.php?c=hr&a=completeness" class="btn btn-outline-primary btn-sm rounded-pill w-100 fw-bold">ตรวจสอบและติดตามแก้ข้อมูล</a>
            </div>

            <!-- สรุปวันลาที่รออนุมัติ -->
            <div class="card card-dashboard shadow-sm mb-4 p-4 text-center bg-warning bg-opacity-10 border border-warning border-opacity-25">
                <h6 class="fw-bold text-dark mb-3">คำร้องขอลาที่รอการตรวจสอบ</h6>
                <div class="display-4 fw-black text-dark mb-2"><?= number_format($pending_leaves) ?></div>
                <p class="text-muted small">รายการทั้งหมดที่รอกระบวนการจาก HR</p>
                <a href="index.php?c=leave&a=approvals" class="btn btn-dark btn-sm rounded-pill w-100 fw-bold mt-2">ไปที่หน้าอนุมัติการลา</a>
            </div>

            <!-- เมนูทางลัดงานบุคคล -->
            <div class="card card-dashboard shadow-sm h-100 p-4">
                <h6 class="fw-bold mb-3">เมนูทางลัด (Quick Tasks)</h6>
                <div class="d-grid gap-2">
                    <a href="index.php?c=hr&a=inactive" class="btn quick-link-btn text-start p-3 rounded-4 d-flex align-items-center">
                        <i class="bi bi-archive-fill text-secondary me-3 fs-5"></i>
                        <div>
                            <div class="fw-bold text-dark small">ทำเนียบผู้พ้นสภาพ</div>
                            <div class="text-muted" style="font-size: 11px;">ค้นข้อมูลคนลาออก/เกษียณ</div>
                        </div>
                    </a>
                    <a href="index.php?c=settings&a=shift_types" class="btn quick-link-btn text-start p-3 rounded-4 d-flex align-items-center">
                        <i class="bi bi-wallet2 text-primary me-3 fs-5"></i>
                        <div>
                            <div class="fw-bold text-dark small">ตั้งค่าเรทค่าตอบแทน</div>
                            <div class="text-muted" style="font-size: 11px;">ปรับเปลี่ยนราคาค่าเวร</div>
                        </div>
                    </a>
                    <a href="index.php?c=staff" class="btn quick-link-btn text-start p-3 rounded-4 d-flex align-items-center">
                        <i class="bi bi-file-earmark-bar-graph-fill text-success me-3 fs-5"></i>
                        <div>
                            <div class="fw-bold text-dark small">รายงานสถิติกำลังคน</div>
                            <div class="text-muted" style="font-size: 11px;">Export ข้อมูลรายเดือน</div>
                        </div>
                    </a>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ข้อมูลเพศจาก PHP
    const genderData = {
        male: <?= $gender_stats['M'] ?? 0 ?>,
        female: <?= $gender_stats['F'] ?? 0 ?>,
        other: <?= $gender_stats[''] ?? 0 ?>
    };

    // สร้างกราฟวงกลม
    const ctx = document.getElementById('genderChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['ชาย', 'หญิง', 'ไม่ระบุ'],
            datasets: [{
                data: [genderData.male, genderData.female, genderData.other],
                backgroundColor: ['#3b82f6', '#ec4899', '#94a3b8'],
                hoverOffset: 10,
                borderWidth: 0
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
            },
            cutout: '70%'
        }
    });
});
</script>