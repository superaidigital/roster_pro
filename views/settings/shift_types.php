<?php
// ที่อยู่ไฟล์: views/settings/shift_types.php
// ตัวแปร $pay_rates ถูกส่งมาจาก SettingsController
?>
<div class="w-100 bg-light p-3 p-md-4 min-vh-100">
    <div class="container-fluid max-w-7xl mx-auto">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <a href="index.php?c=settings&a=system" class="btn btn-sm btn-light text-secondary fw-bold mb-2 border rounded-pill shadow-sm">
                    <i class="bi bi-arrow-left me-1"></i> กลับหน้าตั้งค่าระบบ
                </a>
                <h4 class="fw-bold text-dark mb-1">
                    <i class="bi bi-tags-fill text-primary me-2"></i> จัดการประเภทเวร & อัตราค่าตอบแทน
                </h4>
                <p class="text-muted mb-0" style="font-size: 14px;">กำหนดรหัสเวร โค้ดสีสำหรับแสดงผลในตาราง และอัตราเบิกจ่ายตามสายงาน</p>
            </div>
            <div>
                <button class="btn btn-primary fw-bold shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#editRatesModal">
                    <i class="bi bi-pencil-square me-1"></i> ปรับปรุงอัตราค่าตอบแทน
                </button>
            </div>
        </div>

        <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-4">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success_msg'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error_msg'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom p-3 px-4">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-palette text-warning me-2"></i> สัญลักษณ์และโค้ดสี (Shift Codes)</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div><div class="fw-bold text-dark mb-1">เวรบ่าย นอกเวลาราชการ</div><div class="text-muted small">เวลา 16.31 - 20.30 น.</div></div>
                                <span class="badge bg-warning bg-opacity-25 text-warning text-dark border border-warning px-3 py-2 fs-6">บ</span>
                            </li>
                            <li class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div><div class="fw-bold text-dark mb-1">รอเรียกบริการ (On Call)</div><div class="text-muted small">เวลา 20.31 - 08.29 น.</div></div>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 fs-6">ร</span>
                            </li>
                            <li class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div><div class="fw-bold text-dark mb-1">วันหยุดราชการ</div><div class="text-muted small">เวลา 08.30 - 16.30 น.</div></div>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-3 py-2 fs-6">ย</span>
                            </li>
                            <li class="list-group-item p-3 d-flex justify-content-between align-items-center bg-light">
                                <div><div class="fw-bold text-dark mb-1">เวรควบ (บ่าย + รอเรียก)</div><div class="text-muted small">นอกเวลาบ่าย ควบดึก</div></div>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-3 py-2 fs-6">บ/ร</span>
                            </li>
                            <li class="list-group-item p-3 d-flex justify-content-between align-items-center bg-light border-bottom-0">
                                <div><div class="fw-bold text-dark mb-1">เวรควบ (วันหยุด + บ่าย)</div><div class="text-muted small">เวรเช้าวันหยุด ควบบ่าย</div></div>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-3 py-2 fs-6">ย/บ</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- อัตราค่าตอบแทน (ดึงจาก Database) -->
            <div class="col-12 col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom p-3 px-4 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-cash-stack text-success me-2"></i> อัตราค่าตอบแทน (ซิงค์ฐานข้อมูลแล้ว)</h6>
                        <span class="badge bg-success text-white shadow-sm"><i class="bi bi-database-check"></i> DB Sync</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 text-center">
                                <thead class="table-light text-secondary" style="font-size: 13px;">
                                    <tr>
                                        <th class="py-3 px-4 text-start" width="40%">กลุ่มสายงาน</th>
                                        <th class="py-3" width="20%">วันหยุด (ย)</th>
                                        <th class="py-3" width="20%">เวรบ่าย (บ)</th>
                                        <th class="py-3" width="20%">เวรดึก (ร)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pay_rates as $rate): ?>
                                    <tr>
                                        <td class="text-start px-4 py-3">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($rate['group_name']) ?></div>
                                            <div class="text-muted" style="font-size: 11px;">คีย์เวิร์ดตรวจจับ: <?= htmlspecialchars($rate['keywords']) ?></div>
                                        </td>
                                        <td class="fw-bold text-danger"><?= $rate['rate_y'] ?> <small class="text-muted fw-normal">บ.</small></td>
                                        <td class="fw-bold text-primary"><?= $rate['rate_b'] ?> <small class="text-muted fw-normal">บ.</small></td>
                                        <td class="fw-bold text-success"><?= $rate['rate_r'] > 0 ? $rate['rate_r'].' <small class="text-muted fw-normal">บ.</small>' : '<span class="text-muted">-</span>' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-top p-3 text-muted" style="font-size: 12px;">
                        <i class="bi bi-info-circle text-primary me-1"></i> 
                        ระบบจะนำคีย์เวิร์ดไปเทียบกับชื่อ "ตำแหน่ง" ในฐานข้อมูลบุคลากรอัตโนมัติ (เช่น เจอคำว่า ป.ตรี จะปัดเข้ากลุ่ม 1 ทันที)
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ================= Modal ฟอร์มแก้ไขเรทเงิน ================= -->
<div class="modal fade" id="editRatesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="index.php?c=settings&a=save_pay_rates" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-cash-coin text-success me-2"></i> ปรับปรุงอัตราค่าตอบแทน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                
                <!-- 🌟 เปลี่ยนกล่องแจ้งเตือนให้อธิบายระบบ Snapshot -->
                <div class="alert alert-info border-info border-opacity-50 d-flex align-items-center mb-4 rounded-3 shadow-sm">
                    <i class="bi bi-info-circle-fill fs-3 text-info me-3"></i>
                    <div style="font-size: 13px;">
                        <strong class="text-dark">ระบบแช่แข็งข้อมูล (Snapshot System):</strong> การแก้ไขอัตราค่าตอบแทนใหม่นี้ <b>จะไม่ส่งผลกระทบ</b> ต่อตารางเวรที่ผู้อำนวยการกดยืนยัน <b>"อนุมัติแล้ว"</b> ในอดีต<br>
                        (เรทเงินใหม่จะถูกนำไปคำนวณเฉพาะกับตารางเดือนปัจจุบันที่กำลังจัดทำ หรือตารางที่ถูกตีกลับให้แก้ไขเท่านั้น)
                    </div>
                </div>

                <?php foreach($pay_rates as $idx => $rate): ?>
                <div class="row g-3 mb-3 pb-3 <?= $idx < 2 ? 'border-bottom' : '' ?>">
                    <div class="col-12 fw-bold text-primary"><?= htmlspecialchars($rate['group_name']) ?></div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small">วันหยุด (ย) - บาท</label>
                        <input type="number" name="rate_y_<?= $rate['id'] ?>" class="form-control fw-bold text-danger" value="<?= $rate['rate_y'] ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small">เวรบ่าย (บ) - บาท</label>
                        <input type="number" name="rate_b_<?= $rate['id'] ?>" class="form-control fw-bold text-primary" value="<?= $rate['rate_b'] ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small">เวรรอ (ร) - บาท</label>
                        <input type="number" name="rate_r_<?= $rate['id'] ?>" class="form-control fw-bold text-success" value="<?= $rate['rate_r'] ?>" <?= $rate['id'] != 1 ? 'readonly' : 'required' ?>>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm" onclick="return confirm('ยืนยันการเปลี่ยนแปลงอัตราค่าตอบแทนระบบใช่หรือไม่?');">
                    <i class="bi bi-save me-1"></i> บันทึกการเปลี่ยนแปลง
                </button>
            </div>
        </form>
    </div>
</div>