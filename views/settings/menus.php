<?php
// ที่อยู่ไฟล์: views/settings/menus.php
$menus = $menus ?? [];
// กำหนดระดับสิทธิ์ทั้งหมดในระบบเรียงจากน้อยไปมาก
$system_roles = [
    'STAFF' => ['label' => 'พนักงานทั่วไป', 'color' => 'secondary'],
    'SCHEDULER' => ['label' => 'ผู้จัดเวร', 'color' => 'success'],
    'DIRECTOR' => ['label' => 'ผู้อำนวยการ', 'color' => 'info'],
    'ADMIN' => ['label' => 'ผู้ดูแลระบบ', 'color' => 'primary'],
    'SUPERADMIN' => ['label' => 'ผู้ดูแลสูงสุด', 'color' => 'danger']
];
?>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .table-matrix th, .table-matrix td { vertical-align: middle; }
    .table-matrix th.role-col { width: 11%; text-align: center; border-left: 1px dashed #e9ecef; }
    .table-matrix td.role-col { text-align: center; border-left: 1px dashed #e9ecef; }
    
    /* Custom Checkbox ให้อ่านง่าย */
    .matrix-checkbox { width: 1.25rem; height: 1.25rem; cursor: pointer; }
    .form-switch .form-check-input { width: 2.5em; height: 1.25em; cursor: pointer; }
    
    /* แถวที่ถูกปิดใช้งานให้สีจางลง */
    .row-inactive { opacity: 0.6; background-color: #f8f9fa; }
    .row-inactive td { text-decoration: line-through; color: #6c757d; }
    .row-inactive td.col-switch, .row-inactive td.col-switch * { text-decoration: none !important; opacity: 1 !important; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-shield-lock fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">จัดการเมนูและสิทธิ์ (Permission Matrix)</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">กำหนดสิทธิ์การเข้าถึงเมนูต่างๆ ของผู้ใช้งานแต่ละระดับ</p>
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

    <div class="alert border-0 bg-warning bg-opacity-10 text-dark rounded-4 p-3 mb-4 shadow-sm" style="font-size: 13.5px; border-left: 4px solid #ffc107 !important;">
        <i class="bi bi-info-circle-fill text-warning me-2"></i>
        <b>คำแนะนำ:</b> การปิดสถานะ "เปิดใช้งานเมนู" จะทำให้เมนูนั้นหายไปจากระบบสำหรับ <u>ผู้ใช้งานทุกคน</u> (รวมถึง Super Admin), ส่วนการติ๊กช่องสิทธิ์ จะเป็นการอนุญาตให้ตำแหน่งนั้นมองเห็นเมนูได้
    </div>

    <form action="index.php?c=settings&a=save_menus" method="POST" id="menuForm">
        <div class="card card-modern overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>ตารางกำหนดสิทธิ์เมนูระบบ</h6>
                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm" onclick="return confirm('ยืนยันการบันทึกการเปลี่ยนแปลงสิทธิ์การเข้าถึงเมนูหรือไม่?');">
                    <i class="bi bi-save me-1"></i> บันทึกการตั้งค่า
                </button>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-matrix mb-0" style="font-size: 13.5px; min-width: 900px;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 ps-4" style="width: 25%;">ชื่อเมนู / URL</th>
                                <th class="text-center" style="width: 10%;">เปิดใช้งานเมนู</th>
                                <!-- สร้างคอลัมน์ตาม Role -->
                                <?php foreach ($system_roles as $role_key => $role_info): ?>
                                    <th class="role-col pb-2">
                                        <div class="text-<?= $role_info['color'] ?> fw-bold mb-1" style="font-size: 12px;"><?= $role_key ?></div>
                                        <small class="text-muted" style="font-size: 11px;"><?= $role_info['label'] ?></small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($menus)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">ไม่พบข้อมูลเมนูในระบบ</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($menus as $m): 
                                    $roles_array = explode(',', $m['allowed_roles']);
                                    $is_active = $m['is_active'] == 1;
                                    
                                    // ตรวจสอบว่าเป็นเมนู "ตั้งค่าระบบ" หรือไม่ (ป้องกันแอดมินปิดสิทธิ์ตัวเอง)
                                    $menu_link = $m['menu_link'] ?? $m['url'] ?? '';
                                    $is_settings_menu = (strpos($menu_link, 'c=settings') !== false);
                                ?>
                                <tr class="<?= !$is_active ? 'row-inactive' : '' ?>">
                                    
                                    <!-- ชื่อเมนูและไอคอน -->
                                    <td class="py-3 ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded p-2 me-3 text-secondary">
                                                <i class="<?= htmlspecialchars($m['icon'] ?? 'bi bi-dash') ?> fs-5"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark" style="font-size: 14.5px;">
                                                    <?= htmlspecialchars($m['menu_name'] ?? $m['name']) ?>
                                                </div>
                                                <div class="text-muted mt-1 font-monospace" style="font-size: 11px;">
                                                    <i class="bi bi-link-45deg"></i> <?= htmlspecialchars($menu_link) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- สวิตช์ เปิด/ปิด เมนู -->
                                    <td class="text-center col-switch">
                                        <div class="form-check form-switch d-flex justify-content-center m-0">
                                            <?php if ($is_settings_menu): ?>
                                                <!-- ล็อกไม่ให้ปิดเมนูการตั้งค่า เพื่อป้องกันระบบพัง -->
                                                <input class="form-check-input bg-primary border-primary" type="checkbox" checked disabled title="เมนูระบบบังคับเปิด">
                                                <input type="hidden" name="menu_data[<?= $m['id'] ?>][is_active]" value="1">
                                            <?php else: ?>
                                                <input class="form-check-input" type="checkbox" name="menu_data[<?= $m['id'] ?>][is_active]" value="1" <?= $is_active ? 'checked' : '' ?> onchange="toggleRow(this)">
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <!-- เช็คบ็อกซ์สิทธิ์แต่ละ Role -->
                                    <?php foreach ($system_roles as $role_key => $role_info): 
                                        $has_access = in_array($role_key, $roles_array);
                                        // บังคับให้ SUPERADMIN เข้าถึงเมนูตั้งค่าได้เสมอ
                                        $is_locked_admin = ($is_settings_menu && $role_key == 'SUPERADMIN');
                                    ?>
                                    <td class="role-col">
                                        <?php if ($is_locked_admin): ?>
                                            <input class="form-check-input matrix-checkbox border-<?= $role_info['color'] ?>" type="checkbox" checked disabled title="จำเป็นต้องมีสิทธิ์นี้">
                                            <input type="hidden" name="menu_data[<?= $m['id'] ?>][roles][]" value="<?= $role_key ?>">
                                            <i class="bi bi-lock-fill text-danger ms-1" style="font-size: 10px;" title="ระบบล็อกสิทธิ์นี้ไว้"></i>
                                        <?php else: ?>
                                            <input class="form-check-input matrix-checkbox border-<?= $role_info['color'] ?>" 
                                                   type="checkbox" 
                                                   name="menu_data[<?= $m['id'] ?>][roles][]" 
                                                   value="<?= $role_key ?>" 
                                                   <?= $has_access ? 'checked' : '' ?>>
                                        <?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                    
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3 d-flex justify-content-end">
                 <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="return confirm('ยืนยันการบันทึกการเปลี่ยนแปลงสิทธิ์การเข้าถึงเมนูหรือไม่?');">
                    <i class="bi bi-save me-1"></i> บันทึกการตั้งค่า
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// สคริปต์สำหรับจัดการ UI เมื่อสลับสวิตช์เปิด/ปิดเมนู ให้แถวเปลี่ยนสี
function toggleRow(checkbox) {
    const tr = checkbox.closest('tr');
    if (checkbox.checked) {
        tr.classList.remove('row-inactive');
    } else {
        tr.classList.add('row-inactive');
    }
}
</script>