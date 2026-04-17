<?php
// ที่อยู่ไฟล์: views/profile/index.php
?>
<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <h2 class="fw-bold text-dark d-flex align-items-center mb-1">
            <div class="bg-primary bg-opacity-10 text-primary me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; border-radius: 12px;">
                <i class="bi bi-person-badge fs-4"></i>
            </div>
            โปรไฟล์ส่วนตัว
        </h2>
        <p class="text-muted ms-5 ps-3 mb-0">จัดการข้อมูลส่วนตัวและรหัสผ่านของคุณ</p>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3 d-flex align-items-center mb-4"><i class="bi bi-check-circle-fill fs-5 text-success me-3"></i> <div class="fw-bold text-dark"><?= $_SESSION['success_msg'] ?></div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['success_msg']); endif; ?>
        
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 d-flex align-items-center mb-4"><i class="bi bi-exclamation-triangle-fill fs-5 text-danger me-3"></i> <div class="fw-bold text-dark"><?= $_SESSION['error_msg'] ?></div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['error_msg']); endif; ?>

    <div class="row g-4">
        <!-- ข้อมูลส่วนตัว -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom-0"><h5 class="mb-0 fw-bold"><i class="bi bi-info-circle text-primary me-2"></i> ข้อมูลผู้ใช้งาน</h5></div>
                <div class="card-body">
                    <form action="index.php?c=profile&a=update_info" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">ชื่อ-นามสกุล (แก้ไขไม่ได้)</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user_info['name'] ?? '') ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">ประเภทบุคลากร</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user_info['employee_type'] ?? '') ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">ชื่อผู้ใช้งาน (Username ล็อกอิน)</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user_info['username'] ?? '') ?>" readonly>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small">เบอร์โทรศัพท์ (สามารถแก้ไขได้)</label>
                            <input type="text" name="phone" class="form-control border-primary border-opacity-50" value="<?= htmlspecialchars($user_info['phone'] ?? '') ?>" placeholder="กรอกเบอร์โทรศัพท์">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold"><i class="bi bi-save me-2"></i> บันทึกข้อมูล</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- เปลี่ยนรหัสผ่าน -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom-0"><h5 class="mb-0 fw-bold"><i class="bi bi-shield-lock text-danger me-2"></i> เปลี่ยนรหัสผ่าน</h5></div>
                <div class="card-body">
                    <form action="index.php?c=profile&a=change_password" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">รหัสผ่านปัจจุบัน <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">รหัสผ่านใหม่ <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small">ยืนยันรหัสผ่านใหม่ <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                        <div class="alert alert-warning py-2 small border-0 bg-warning bg-opacity-10 text-dark"><i class="bi bi-exclamation-circle-fill text-warning me-1"></i> รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร</div>
                        <button type="submit" class="btn btn-danger w-100 rounded-pill fw-bold mt-2"><i class="bi bi-key me-2"></i> เปลี่ยนรหัสผ่าน</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>