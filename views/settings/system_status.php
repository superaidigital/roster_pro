<?php
// ที่อยู่ไฟล์: views/settings/system_status.php
// รับตัวแปร $status_data จาก Controller
?>
<div class="container-fluid px-3 py-4">
    
    <div class="d-flex align-items-center mb-4">
        <a href="index.php?c=settings&a=system" class="btn btn-light border shadow-sm rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
            <i class="bi bi-arrow-left fs-5"></i>
        </a>
        <div>
            <h2 class="h4 text-dark mb-0 fw-bold">รายงานสถานะระบบ (System Status)</h2>
            <p class="text-muted mb-0 small">ตรวจสอบความพร้อมและการใช้งานทรัพยากรเซิร์ฟเวอร์</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- 🌟 ส่วนที่ 1: สถานะการเชื่อมต่อ -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
                <h6 class="fw-bold text-muted small text-uppercase mb-4">สถานะการทำงานปัจจุบัน</h6>
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success rounded-circle me-3" style="width: 12px; height: 12px; box-shadow: 0 0 10px #10b981;"></div>
                    <span class="fw-bold text-dark">ฐานข้อมูล: <?= $status_data['db_status'] ?></span>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success rounded-circle me-3" style="width: 12px; height: 12px; box-shadow: 0 0 10px #10b981;"></div>
                    <span class="fw-bold text-dark">เซิร์ฟเวอร์เว็บ: ปกติ (Online)</span>
                </div>
                <hr>
                <div class="text-muted small">อัปเดตล่าสุด: <?= date('d/m/Y H:i:s') ?></div>
            </div>
        </div>

        <!-- 🌟 ส่วนที่ 2: ความจุพื้นที่ (Disk Usage) -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
                <h6 class="fw-bold text-muted small text-uppercase mb-4">การใช้งานพื้นที่จัดเก็บ (Server Storage)</h6>
                <div class="row align-items-center">
                    <div class="col-md-3 text-center border-end">
                        <div class="display-6 fw-bold text-primary"><?= $status_data['disk_usage_percent'] ?>%</div>
                        <div class="small text-muted">ถูกใช้งานแล้ว</div>
                    </div>
                    <div class="col-md-9 ps-md-4 mt-3 mt-md-0">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small fw-bold">พื้นที่ว่าง: <span class="text-success"><?= $status_data['disk_free'] ?> GB</span></span>
                            <span class="small text-muted">ทั้งหมด: <?= $status_data['disk_total'] ?> GB</span>
                        </div>
                        <div class="progress rounded-pill" style="height: 15px; background-color: #f1f5f9;">
                            <div class="progress-bar bg-primary" style="width: <?= $status_data['disk_usage_percent'] ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🌟 ส่วนที่ 3: ข้อมูลเชิงลึก -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-info-square-fill text-primary me-2"></i> ข้อมูลทางเทคนิค (Technical Info)</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>หัวข้อ</th><th>รายละเอียด</th></tr>
                        </thead>
                        <tbody>
                            <tr><td class="ps-4 fw-bold">ขนาดฐานข้อมูลปัจจุบัน</td><td class="text-primary fw-bold"><?= $status_data['db_size'] ?> MB</td></tr>
                            <tr><td class="ps-4">PHP Version</td><td><?= $status_data['php_version'] ?></td></tr>
                            <tr><td class="ps-4">ระบบปฏิบัติการ (OS)</td><td><?= $status_data['os'] ?></td></tr>
                            <tr><td class="ps-4">ข้อมูลเซิร์ฟเวอร์ DB</td><td><small class="text-muted"><?= $status_data['db_server'] ?></small></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>