<?php
// ที่อยู่ไฟล์: views/settings/general.php
// ตัวแปร $settings จะถูกส่งมาจาก SettingsController

$settings = $settings ?? [];
?>
<style>
    .custom-switch .form-check-input { width: 3rem; height: 1.5rem; cursor: pointer; }
    .custom-switch .form-check-input:checked { background-color: #ef4444; border-color: #ef4444; }
    .custom-switch .form-check-label { cursor: pointer; padding-top: 2px; }
</style>

<div class="container-fluid px-3 py-4">
    
    <!-- Header พร้อมปุ่มย้อนกลับ -->
    <div class="d-flex align-items-center mb-4">
        <a href="index.php?c=settings&a=system" class="btn btn-light border shadow-sm rounded-circle d-flex align-items-center justify-content-center me-3 hover-scale" style="width: 40px; height: 40px;">
            <i class="bi bi-arrow-left fs-5"></i>
        </a>
        <div>
            <h2 class="h4 text-dark mb-0 fw-bold">การตั้งค่าทั่วไป (General Config)</h2>
            <p class="text-muted mb-0 small">จัดการชื่อระบบและการเปิด/ปิดฟีเจอร์พื้นฐาน</p>
        </div>
    </div>

    <!-- 🌟 แจ้งเตือนสถานะ -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 py-3 px-4 mb-4 d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-3 fs-4"></i> <div class="fw-bold"><?= $_SESSION['success_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 max-w-100" style="max-width: 800px;">
        <div class="card-body p-4 p-md-5">
            
            <form action="index.php?c=settings&a=update_system" method="POST">
                <input type="hidden" name="section" value="general">
                
                <h6 class="fw-bold text-primary mb-4 border-bottom pb-2"><i class="bi bi-info-square me-2"></i> ข้อมูลแอปพลิเคชัน</h6>

                <div class="mb-4">
                    <label class="form-label fw-bold text-muted small">ชื่อระบบ (App Name) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg bg-light rounded-3" name="settings[app_name]" 
                           value="<?= htmlspecialchars($settings['app_name'] ?? 'Roster Pro') ?>" required>
                    <div class="form-text mt-1">ชื่อนี้จะแสดงที่แถบเมนูด้านบนและหน้าล็อกอิน</div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small">เบอร์โทรศัพท์ส่วนกลาง (Helpdesk)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone-fill"></i></span>
                            <input type="text" class="form-control bg-light" name="settings[contact_phone]" 
                                   value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>" placeholder="02-123-4567">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small">อีเมลส่วนกลาง</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope-fill"></i></span>
                            <input type="email" class="form-control bg-light" name="settings[contact_email]" 
                                   value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" placeholder="admin@example.com">
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="form-label fw-bold text-muted small">ประกาศของระบบ (System Announcement)</label>
                    <textarea class="form-control bg-light rounded-3 p-3" name="settings[system_announcement]" rows="3" placeholder="พิมพ์ข้อความที่ต้องการแจ้งให้บุคลากรทุกคนทราบ..."><?= htmlspecialchars($settings['system_announcement'] ?? '') ?></textarea>
                    <div class="form-text">ข้อความนี้จะแสดงในหน้าแรก (Dashboard) ของผู้ใช้งานทุกคน</div>
                </div>

                <h6 class="fw-bold text-danger mb-4 border-bottom pb-2"><i class="bi bi-shield-exclamation me-2"></i> โหมดฉุกเฉิน / บำรุงรักษา</h6>

                <div class="p-3 rounded-4 mb-4" style="background-color: #fff1f2; border: 1px solid #ffe4e6;">
                    <div class="form-check form-switch custom-switch d-flex align-items-center m-0">
                        <input class="form-check-input mt-0 me-3 shadow-sm" type="checkbox" role="switch" id="maintenanceMode" name="settings[maintenance_mode]" value="1" 
                               <?= (isset($settings['maintenance_mode']) && $settings['maintenance_mode'] == '1') ? 'checked' : '' ?>>
                        <label class="form-check-label mb-0" for="maintenanceMode">
                            <strong class="text-danger">เปิดโหมดปรับปรุงระบบ (Maintenance Mode)</strong>
                            <div class="text-muted small lh-1 mt-1">หากเปิดใช้งาน ผู้ใช้ทั่วไปจะไม่สามารถล็อกอินเข้าสู่ระบบได้ชั่วคราว (ยกเว้นระดับผู้ดูแลระบบ)</div>
                        </label>
                    </div>
                </div>

                <div class="text-end pt-3 mt-4 border-top">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-save me-2"></i> บันทึกการตั้งค่า
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>