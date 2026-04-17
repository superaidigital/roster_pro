<?php
// ที่อยู่ไฟล์: views/settings/menus.php
?>
<style>
    .table-permissions th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 13px;
        text-align: center;
        vertical-align: middle;
        padding: 1rem;
        border-bottom: 2px solid #e2e8f0;
    }
    .table-permissions td {
        vertical-align: middle;
        font-size: 14px;
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        background-color: #ffffff;
    }
    .table-permissions tbody tr:hover td {
        background-color: #f8fafc;
    }
    /* ปรับขนาด Checkbox ให้ใหญ่และคลิกง่ายขึ้น */
    .role-checkbox {
        width: 1.2rem;
        height: 1.2rem;
        cursor: pointer;
    }
    .form-switch .form-check-input {
        width: 2.5em;
        height: 1.25em;
        cursor: pointer;
    }
    /* สีของแถวที่ถูกปิดการใช้งาน */
    .row-disabled {
        background-color: #f8fafc;
        opacity: 0.7;
    }
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    <!-- 🌟 Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-ui-checks-grid fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">จัดการเมนูและสิทธิ์การเข้าถึง</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">เปิด/ปิด เมนู และกำหนดระดับผู้ใช้งานที่สามารถเข้าถึงได้ (Permission Matrix)</p>
            </div>
        </div>
    </div>

    <!-- 🌟 Alerts -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert border-0 bg-success bg-opacity-10 text-success rounded-4 d-flex align-items-center mb-4 p-3 shadow-sm">
            <i class="bi bi-check-circle-fill fs-5 me-3"></i> 
            <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['success_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert border-0 bg-danger bg-opacity-10 text-danger rounded-4 d-flex align-items-center mb-4 p-3 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-3"></i> 
            <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['error_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <!-- 🌟 ฟอร์มและตาราง -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <form action="index.php?c=settings&a=save_menus" method="POST">
            <div class="table-responsive">
                <table class="table table-permissions mb-0">
                    <thead>
                        <tr>
                            <th class="text-start ps-4" style="width: 25%;">สถานะ / ชื่อเมนู</th>
                            <?php foreach ($system_roles as $role): ?>
                                <th>
                                    <div class="d-flex flex-column align-items-center gap-1">
                                        <span class="badge bg-light text-dark border w-100 py-2"><?= $role ?></span>
                                        <!-- 🌟 ปุ่มเลือกทั้งหมด (Check All) ประจำคอลัมน์ -->
                                        <div class="form-check m-0 mt-1" title="เลือก/ยกเลิก ทั้งหมด">
                                            <input class="form-check-input border-secondary check-all-col" type="checkbox" data-role="<?= $role ?>" style="cursor: pointer;">
                                        </div>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($menus)): ?>
                            <tr><td colspan="<?= count($system_roles) + 1 ?>" class="text-center py-5 text-muted">ไม่พบข้อมูลเมนูในระบบ</td></tr>
                        <?php else: ?>
                            <?php foreach ($menus as $menu): ?>
                                <?php 
                                    // 🌟 ดักจับ Error: รองรับกรณีคอลัมน์ชื่อ url, link, menu_url หรือค่าเป็น NULL
                                    $menu_link = $menu['menu_link'] ?? $menu['url'] ?? $menu['menu_url'] ?? $menu['link'] ?? '';
                                    
                                    // แปลงสิทธิ์ที่มีในฐานข้อมูล (String) ให้เป็น Array
                                    $current_roles = explode(',', $menu['allowed_roles'] ?? ''); 
                                    $is_settings = (strpos($menu_link, 'c=settings') !== false);
                                ?>
                                <tr class="<?= empty($menu['is_active']) ? 'row-disabled' : '' ?>">
                                    <td class="text-start ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <!-- สวิตช์ เปิด/ปิด เมนู -->
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" role="switch" 
                                                       name="menu_data[<?= $menu['id'] ?>][is_active]" 
                                                       value="1" 
                                                       id="switch_<?= $menu['id'] ?>"
                                                       <?= !empty($menu['is_active']) ? 'checked' : '' ?>
                                                       <?= $is_settings ? 'onclick="return false;" title="ไม่สามารถปิดเมนูตั้งค่าได้"' : '' ?>>
                                            </div>
                                            <!-- ไอคอนและชื่อเมนู -->
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-light rounded p-2 text-secondary d-flex align-items-center justify-content-center shadow-sm border" style="width: 36px; height: 36px;">
                                                    <i class="<?= htmlspecialchars($menu['menu_icon'] ?? 'bi bi-grid') ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($menu['menu_name'] ?? 'ไม่มีชื่อเมนู') ?></div>
                                                    <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($menu_link) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Checkbox สำหรับแต่ละ Role -->
                                    <?php foreach ($system_roles as $role): ?>
                                        <td class="text-center">
                                            <div class="form-check d-flex justify-content-center m-0">
                                                <input class="form-check-input border-secondary role-checkbox role-<?= $role ?>" type="checkbox" 
                                                       name="menu_data[<?= $menu['id'] ?>][roles][]" 
                                                       value="<?= $role ?>" 
                                                       <?= in_array($role, $current_roles) ? 'checked' : '' ?>
                                                       <?= ($is_settings && $role === 'SUPERADMIN') ? 'onclick="return false;" title="SUPERADMIN ต้องเข้าถึงเมนูตั้งค่าได้เสมอ"' : '' ?>>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-light border-top p-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div class="text-muted small">
                    <i class="bi bi-shield-lock-fill text-warning me-1"></i> <strong>ระบบรักษาความปลอดภัย:</strong> เมนูการตั้งค่าระบบและสิทธิ์การเข้าถึงของ SUPERADMIN จะถูกบังคับให้เปิดใช้งานเสมอ ป้องกันการเผลอตัดสิทธิ์ตัวเอง (Lockout)
                </div>
                <button type="submit" class="btn btn-primary rounded-pill fw-bold px-5 shadow-sm text-nowrap">
                    <i class="bi bi-save me-2"></i> บันทึกการกำหนดสิทธิ์
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 🌟 Script สำหรับควบคุม Check All ประจำคอลัมน์ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // เลือก Checkbox 'Check All' ทั้งหมด
    const checkAllCols = document.querySelectorAll('.check-all-col');
    
    checkAllCols.forEach(colCheck => {
        colCheck.addEventListener('change', function() {
            const roleName = this.getAttribute('data-role');
            const isChecked = this.checked;
            
            // หา Checkbox ทั้งหมดที่ตรงกับคลาส role-นั้นๆ
            const roleCheckboxes = document.querySelectorAll('.role-' + roleName);
            
            roleCheckboxes.forEach(checkbox => {
                // เปลี่ยนค่า Checked (ยกเว้นตัวที่โดนล็อคไว้ด้วย onclick="return false;")
                if (!checkbox.hasAttribute('onclick')) {
                    checkbox.checked = isChecked;
                }
            });
        });
    });

    // กำหนดสีพื้นหลังเทาๆ ให้แถวถ้าเมนูนั้นถูกปิดการใช้งาน (Visual cue)
    const switches = document.querySelectorAll('.form-switch .form-check-input');
    switches.forEach(switchEl => {
        switchEl.addEventListener('change', function() {
            const tr = this.closest('tr');
            if(this.checked) {
                tr.classList.remove('row-disabled');
            } else {
                tr.classList.add('row-disabled');
            }
        });
    });
});
</script>