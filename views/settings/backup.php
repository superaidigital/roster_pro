<?php
// ที่อยู่ไฟล์: views/settings/backup.php
$db_stats = $db_stats ?? ['total_tables' => 0];
$backup_history = $backup_history ?? [];
$server_backups = $server_backups ?? []; // นำเข้าตัวแปรเก็บรายชื่อไฟล์
?>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .bg-gradient-info { background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%); }
    .bg-gradient-success { background: linear-gradient(135deg, #10b981 0%, #047857 100%); }
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
                <p class="text-muted mb-0" style="font-size: 13px;">ดาวน์โหลด และ จัดเก็บข้อมูลอัตโนมัติบนเซิร์ฟเวอร์</p>
            </div>
        </div>
        <a href="index.php?c=settings&a=system" class="btn btn-light border fw-bold rounded-pill shadow-sm px-4">
            <i class="bi bi-arrow-left me-1"></i> กลับไปตั้งค่าระบบ
        </a>
    </div>

    <!-- Alerts -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert border-0 bg-success bg-opacity-10 text-success rounded-4 p-3 shadow-sm border-start border-success border-4 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success_msg'] ?>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert border-0 bg-danger bg-opacity-10 text-danger rounded-4 p-3 shadow-sm border-start border-danger border-4 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error_msg'] ?>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- กล่องที่ 1: ดาวน์โหลดลงเครื่อง (Manual Download) -->
        <div class="col-xl-6 col-lg-6">
            <div class="card card-modern h-100 overflow-hidden">
                <div class="card-body p-4 text-center d-flex flex-column justify-content-center align-items-center">
                    <div class="bg-gradient-info text-white rounded-circle d-flex align-items-center justify-content-center mb-4 pulse-icon shadow-sm" style="width: 80px; height: 80px;">
                        <i class="bi bi-cloud-arrow-down-fill" style="font-size: 35px;"></i>
                    </div>
                    <h5 class="fw-bolder text-dark mb-2">ดาวน์โหลดไฟล์ Backup ทันที</h5>
                    <p class="text-muted mb-4" style="font-size: 13.5px; line-height: 1.6;">
                        ดึงข้อมูลระบบรวม <b class="text-primary"><?= number_format($db_stats['total_tables']) ?> ตาราง</b> ออกมาเป็นไฟล์รูปแบบ .sql บันทึกลงในคอมพิวเตอร์ของคุณ
                    </p>

                    <form action="index.php?c=settings&a=do_backup" method="POST" class="w-100" id="backupForm">
                        <button type="submit" class="btn btn-info text-white w-100 py-2 rounded-pill fw-bolder shadow-sm" id="btnBackup">
                            <i class="bi bi-laptop me-2"></i> ดาวน์โหลดลงเครื่อง
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- กล่องที่ 2: บันทึกลงเซิร์ฟเวอร์ (Server Backup) -->
        <div class="col-xl-6 col-lg-6">
            <div class="card card-modern h-100 overflow-hidden">
                <div class="card-body p-4 text-center d-flex flex-column justify-content-center align-items-center">
                    <div class="bg-gradient-success text-white rounded-circle d-flex align-items-center justify-content-center mb-4 shadow-sm" style="width: 80px; height: 80px;">
                        <i class="bi bi-server" style="font-size: 35px;"></i>
                    </div>
                    <h5 class="fw-bolder text-dark mb-2">สำรองข้อมูลเก็บไว้ในเซิร์ฟเวอร์</h5>
                    <p class="text-muted mb-4" style="font-size: 13.5px; line-height: 1.6;">
                        บันทึกไฟล์ .sql เก็บไว้ในโฟลเดอร์ <code class="bg-light px-2 py-1 rounded">public/uploads/Backup</code> อย่างปลอดภัย
                    </p>

                    <form action="index.php?c=settings&a=do_server_backup" method="POST" class="w-100" onsubmit="return confirm('ต้องการสร้างไฟล์สำรองข้อมูลเก็บบนเซิร์ฟเวอร์ใช่หรือไม่?');">
                        <button type="submit" class="btn btn-success text-white w-100 py-2 rounded-pill fw-bolder shadow-sm">
                            <i class="bi bi-hdd-fill me-2"></i> บันทึกลงเซิร์ฟเวอร์
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- กล่องฝั่งซ้าย: ไฟล์ที่อยู่บน Server -->
        <div class="col-xl-7 col-lg-12">
            <div class="card card-modern h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-folder2-open text-success me-2"></i> 
                        <h6 class="mb-0 fw-bold text-dark">ไฟล์ Backup บนเซิร์ฟเวอร์ (อัตโนมัติ/แมนนวล)</h6>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 text-center" style="font-size: 13.5px;">
                            <thead class="table-light text-muted sticky-top" style="font-size: 12.5px;">
                                <tr>
                                    <th class="py-3 text-start ps-4">ชื่อไฟล์</th>
                                    <th>ขนาด</th>
                                    <th>วันที่สร้าง</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($server_backups)): ?>
                                    <tr><td colspan="4" class="py-5 text-muted">ยังไม่มีไฟล์สำรองข้อมูลบนเซิร์ฟเวอร์</td></tr>
                                <?php else: ?>
                                    <?php foreach ($server_backups as $file): ?>
                                    <tr>
                                        <td class="py-3 fw-medium text-dark text-start ps-4">
                                            <i class="bi bi-filetype-sql text-primary me-2"></i>
                                            <?= htmlspecialchars($file['filename']) ?>
                                        </td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-secondary border font-monospace"><?= $file['size'] ?> KB</span></td>
                                        <td class="text-muted"><?= $file['date'] ?></td>
                                        <td>
                                            <a href="<?= htmlspecialchars($file['path']) ?>" download class="btn btn-sm btn-outline-primary rounded-circle" title="ดาวน์โหลด">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <a href="index.php?c=settings&a=delete_server_backup&file=<?= urlencode($file['filename']) ?>" 
                                               class="btn btn-sm btn-outline-danger rounded-circle ms-1" 
                                               onclick="return confirm('ยืนยันการลบไฟล์ <?= htmlspecialchars($file['filename']) ?> ?');" title="ลบไฟล์">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- วิธีตั้งค่า Cron Job -->
                    <div class="card-footer bg-light p-3">
                        <h6 class="fw-bold" style="font-size: 13px;"><i class="bi bi-robot text-primary me-1"></i> การตั้งค่าสำรองข้อมูลอัตโนมัติ (Cron Job)</h6>
                        <p class="text-muted mb-1" style="font-size: 12px;">หากต้องการให้ระบบสำรองข้อมูลอัตโนมัติทุกเดือน ให้นำ URL ด้านล่างไปตั้งค่าใน Cron Job ของโฮสติ้ง (เช่น ตั้งค่าให้ทำงานทุกวันที่ 1 ของเดือน)</p>
                        <code class="d-block bg-dark text-white p-2 rounded mt-2" style="font-size: 11px; word-break: break-all;">
                            curl -s "http://<?= $_SERVER['HTTP_HOST'] ?>/index.php?c=settings&a=cron_monthly_backup&key=ROSTER_PRO_CRON_2026"
                        </code>
                    </div>
                </div>
            </div>
        </div>

        <!-- กล่องฝั่งขวา: ประวัติการดาวน์โหลด (Logs) -->
        <div class="col-xl-5 col-lg-12">
            <div class="card card-modern h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-clock-history text-secondary me-2"></i> 
                        <h6 class="mb-0 fw-bold text-dark">ประวัติการทำรายการ (Logs)</h6>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center" style="font-size: 13px;">
                            <thead class="table-light text-muted" style="font-size: 12px;">
                                <tr>
                                    <th class="py-3 text-start ps-3">วัน/เวลา</th>
                                    <th>ผู้ทำรายการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($backup_history)): ?>
                                    <tr><td colspan="2" class="py-5 text-muted">ยังไม่มีประวัติการสำรองข้อมูล</td></tr>
                                <?php else: ?>
                                    <?php foreach ($backup_history as $log): ?>
                                    <tr>
                                        <td class="py-3 text-start ps-3">
                                            <i class="bi bi-check2-circle text-success me-1"></i>
                                            <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                        </td>
                                        <td>
                                            <?php if(empty($log['name'])): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary">อัตโนมัติ (Cron)</span>
                                            <?php else: ?>
                                                <?= htmlspecialchars($log['name']) ?>
                                            <?php endif; ?>
                                        </td>
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
            btnBackup.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> กำลังสร้างไฟล์...';
            btnBackup.classList.add('disabled');
            
            setTimeout(() => {
                btnBackup.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i> กำลังอัปเดตประวัติ...';
                window.location.reload();
            }, 3000); 
        });
    }
});
</script>