<?php
// ที่อยู่ไฟล์: views/settings/backup.php
$db_stats = $db_stats ?? ['total_tables' => 0];
$backup_history = $backup_history ?? [];
?>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .bg-gradient-info { background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%); }
    .pulse-icon { animation: pulse 2s infinite; }
    @keyframes pulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.4); }
        70% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(14, 165, 233, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(14, 165, 233, 0); }
    }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-database-down fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">สำรองฐานข้อมูล (Database Backup)</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">ดาวน์โหลดข้อมูลทั้งหมดในระบบเพื่อป้องกันการสูญหาย</p>
            </div>
        </div>
        <a href="index.php?c=settings&a=system" class="btn btn-light border fw-bold rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> กลับไปตั้งค่าระบบ
        </a>
    </div>

    <!-- Alerts -->
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert border-0 bg-danger bg-opacity-10 text-danger rounded-4 p-3 shadow-sm border-start border-danger border-4 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error_msg'] ?>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <div class="row g-4">
        <!-- กล่องฝั่งซ้าย: ปุ่มดาวน์โหลด -->
        <div class="col-xl-5 col-lg-6">
            <div class="card card-modern h-100 overflow-hidden">
                <div class="card-body p-4 p-xl-5 text-center d-flex flex-column justify-content-center align-items-center">
                    <div class="bg-gradient-info text-white rounded-circle d-flex align-items-center justify-content-center mb-4 pulse-icon shadow-lg" style="width: 100px; height: 100px;">
                        <i class="bi bi-cloud-arrow-down-fill" style="font-size: 45px;"></i>
                    </div>
                    <h4 class="fw-bolder text-dark mb-2">สร้างไฟล์ Backup (.sql)</h4>
                    <p class="text-muted mb-4" style="font-size: 14px; line-height: 1.6;">
                        ระบบจะทำการดึงข้อมูลผู้ใช้งาน รพ.สต. ตารางเวร และประวัติการลางานทั้งหมดในระบบ 
                        รวมจำนวน <b class="text-primary"><?= number_format($db_stats['total_tables']) ?> ตาราง</b> ออกมาเป็นไฟล์รูปแบบ MySQL
                    </p>

                    <form action="index.php?c=settings&a=do_backup" method="POST" class="w-100" id="backupForm">
                        <button type="submit" class="btn btn-info text-white w-100 py-3 rounded-pill fw-bolder shadow-sm fs-6" id="btnBackup">
                            <i class="bi bi-download me-2"></i> เริ่มดาวน์โหลดข้อมูลทันที
                        </button>
                    </form>

                    <div class="mt-4 p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-4 text-start w-100">
                        <h6 class="fw-bold text-dark" style="font-size: 13px;"><i class="bi bi-shield-exclamation text-warning me-1"></i> คำเตือนความปลอดภัย:</h6>
                        <ul class="text-muted mb-0 ps-3" style="font-size: 12px; line-height: 1.6;">
                            <li>ไฟล์ .sql มีข้อมูลส่วนบุคคลของบุคลากร ห้ามเผยแพร่สู่สาธารณะเด็ดขาด</li>
                            <li>หากระบบค้างขณะดาวน์โหลด โปรดรอสักครู่เนื่องจากข้อมูลมีขนาดใหญ่</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- กล่องฝั่งขวา: ประวัติการดาวน์โหลด -->
        <div class="col-xl-7 col-lg-6">
            <div class="card card-modern h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-clock-history text-secondary me-2"></i> 
                        <h6 class="mb-0 fw-bold text-dark">ประวัติการสำรองข้อมูลล่าสุด (10 รายการ)</h6>
                    </div>
                    <button class="btn btn-sm btn-light border rounded-pill" onclick="window.location.reload();" title="รีเฟรชข้อมูล"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center" style="font-size: 13.5px;">
                            <thead class="table-light text-muted" style="font-size: 12.5px;">
                                <tr>
                                    <th class="py-3">วัน/เวลาที่ดึงข้อมูล</th>
                                    <th>ผู้ทำรายการ</th>
                                    <th>IP Address</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($backup_history)): ?>
                                    <tr><td colspan="4" class="py-5 text-muted">ยังไม่มีประวัติการสำรองข้อมูล</td></tr>
                                <?php else: ?>
                                    <?php foreach ($backup_history as $log): ?>
                                    <tr>
                                        <td class="py-3 fw-medium text-dark">
                                            <i class="bi bi-calendar-check text-primary me-1"></i>
                                            <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                        </td>
                                        <td><?= htmlspecialchars($log['name'] ?? 'ผู้ดูแลระบบ') ?></td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-secondary border font-monospace"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></span></td>
                                        <td><span class="badge bg-success rounded-pill"><i class="bi bi-check-circle me-1"></i>สำเร็จ</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const backupForm = document.getElementById('backupForm');
    const btnBackup = document.getElementById('btnBackup');
    
    if (backupForm) {
        backupForm.addEventListener('submit', function() {
            // เปลี่ยนปุ่มเป็นสถานะกำลังโหลด
            btnBackup.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> กำลังสร้างไฟล์...';
            btnBackup.classList.add('disabled');
            
            // 🌟 แก้ไข: รอให้ไฟล์สร้างเสร็จประมาณ 3 วินาที แล้วทำการรีเฟรชหน้าเว็บอัตโนมัติ เพื่ออัปเดตตารางประวัติ!
            setTimeout(() => {
                btnBackup.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i> กำลังอัปเดตประวัติ...';
                window.location.reload();
            }, 3000); 
        });
    }
});
</script>