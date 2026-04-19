<?php
// ที่อยู่ไฟล์: views/staff/index.php
$staff_list = $staff_list ?? [];
$pay_rates = $pay_rates ?? [];
$hospital_name = $hospital_name ?? 'หน่วยบริการของคุณ';
$current_user_role = trim(strtoupper($_SESSION['user']['role'] ?? 'STAFF'));
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .table-modern th { font-weight: 600; color: #475569; font-size: 13px; background-color: #f8fafc; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding: 1rem; }
    .table-modern td { vertical-align: middle; font-size: 14px; border-bottom: 1px solid #f1f5f9; padding: 1rem; background-color: #ffffff; }
    .drag-handle { cursor: grab; font-size: 1.2rem; color: #94a3b8; }
    .drag-handle:active { cursor: grabbing; color: #3b82f6; }
    .sortable-ghost td { background-color: #eff6ff !important; border-top: 2px dashed #3b82f6; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-people-fill fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">จัดการบุคลากร</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">สังกัด: <?= htmlspecialchars($hospital_name) ?></p>
            </div>
        </div>
        <button class="btn btn-primary fw-bold rounded-pill shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="bi bi-person-plus-fill me-2"></i> เพิ่มบุคลากร
        </button>
    </div>

    <!-- Alerts -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert bg-success bg-opacity-10 text-success rounded-4 p-3 border-start border-success border-4 mb-4"><?= $_SESSION['success_msg'] ?></div>
        <?php unset($_SESSION['success_msg']); endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert bg-danger bg-opacity-10 text-danger rounded-4 p-3 border-start border-danger border-4 mb-4"><?= $_SESSION['error_msg'] ?></div>
        <?php unset($_SESSION['error_msg']); endif; ?>

    <!-- ตาราง -->
    <div class="card card-modern overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">จัดเรียงลำดับการแสดงผลในตารางเวร</h6>
            <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill"><i class="bi bi-grip-vertical"></i> ลากสลับตำแหน่งได้</span>
        </div>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th class="text-center" width="5%"><i class="bi bi-arrow-down-up"></i></th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>ตำแหน่ง/สายงาน</th>
                        <th>เบอร์โทร</th>
                        <th>สิทธิ์</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="staff-table-body">
                    <?php if(empty($staff_list)): ?>
                        <tr><td colspan="6" class="text-center py-5">ไม่พบข้อมูล</td></tr>
                    <?php else: foreach($staff_list as $user): ?>
                        <tr data-id="<?= $user['id'] ?>">
                            <td class="text-center"><i class="bi bi-grip-vertical drag-handle"></i></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle me-3" style="width: 12px; height: 12px; background-color: var(--bs-<?= htmlspecialchars($user['color_theme'] ?? 'primary') ?>);"></div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($user['name']) ?></div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info bg-opacity-10 text-dark border px-2 py-1 rounded-pill"><?= htmlspecialchars($user['employee_type'] ?? 'ทั่วไป') ?></span>
                                <div class="small text-muted mt-1"><?= htmlspecialchars($user['type'] ?? '-') ?></div>
                            </td>
                            <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($user['role']) ?></span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-light border text-primary rounded-circle" title="แก้ไข"
                                        data-bs-toggle="modal" data-bs-target="#editStaffModal"
                                        data-id="<?= $user['id'] ?>"
                                        data-name="<?= htmlspecialchars($user['name']) ?>"
                                        data-username="<?= htmlspecialchars($user['username']) ?>"
                                        data-role="<?= htmlspecialchars($user['role']) ?>"
                                        data-type="<?= htmlspecialchars($user['type'] ?? '') ?>"
                                        data-emptype="<?= htmlspecialchars($user['employee_type'] ?? '') ?>"
                                        data-payrate="<?= htmlspecialchars($user['pay_rate_id'] ?? '') ?>"
                                        data-color="<?= htmlspecialchars($user['color_theme'] ?? 'primary') ?>"
                                        data-phone="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                        data-startdate="<?= htmlspecialchars($user['start_date'] ?? '') ?>"
                                        data-idcard="<?= htmlspecialchars($user['id_card'] ?? '') ?>"
                                        data-posnum="<?= htmlspecialchars($user['position_number'] ?? '') ?>">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <?php if($user['id'] != $_SESSION['user']['id']): ?>
                                    <a href="index.php?c=staff&a=delete&id=<?= $user['id'] ?>" class="btn btn-sm btn-light border text-danger rounded-circle ms-1" onclick="return confirm('ยืนยันลบ?');"><i class="bi bi-trash-fill"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="addStaffModal"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content rounded-4"><form action="index.php?c=staff&a=add" method="POST"><div class="modal-header"><h5 class="modal-title fw-bold">เพิ่มบุคลากร</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
    <!-- ซ่อน Hospital ID -->
    <div class="col-md-6"><label class="form-label">ชื่อ-สกุล *</label><input type="text" name="name" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">เลขบัตร ปชช.</label><input type="text" name="id_card" class="form-control" maxlength="13"></div>
    <div class="col-md-6"><label class="form-label">กลุ่มเรทค่าเวร</label><select name="pay_rate_id" class="form-select"><option value="">-- เลือก --</option><?php foreach($pay_rates as $pr) echo "<option value='{$pr['id']}'>{$pr['name']}</option>"; ?></select></div>
    <div class="col-md-6"><label class="form-label">ประเภทพนักงาน</label><select name="employee_type" class="form-select"><option value="ข้าราชการ/พนักงานท้องถิ่น">ข้าราชการ/พนักงานส่วนท้องถิ่น</option><option value="พนักงานจ้างตามภารกิจ">พนักงานจ้างตามภารกิจ</option><option value="พนักงานจ้างทั่วไป">พนักงานจ้างทั่วไป</option></select></div>
    <div class="col-md-6"><label class="form-label">ตำแหน่ง/วิชาชีพ</label><input type="text" name="type" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">เลขประจำตำแหน่ง</label><input type="text" name="position_number" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">วันที่เริ่มงาน</label><input type="text" name="start_date" class="form-control thai-datepicker"></div>
    <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input type="text" name="phone" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">สีในตาราง</label><select name="color_theme" class="form-select"><option value="primary">น้ำเงิน</option><option value="success">เขียว</option><option value="danger">แดง</option><option value="warning">ส้มเหลือง</option><option value="info">ฟ้า</option><option value="secondary">เทา</option><option value="dark">ดำ</option></select></div>
    <div class="col-12"><hr></div>
    <div class="col-md-4"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required minlength="4"></div>
    <div class="col-md-4"><label class="form-label">Role *</label><select name="role" class="form-select"><option value="STAFF">STAFF</option><option value="SCHEDULER">SCHEDULER</option><?php if(in_array($current_user_role, ['ADMIN', 'SUPERADMIN', 'DIRECTOR'])) echo '<option value="DIRECTOR">DIRECTOR</option>'; ?></select></div>
</div></div><div class="modal-footer"><button type="submit" class="btn btn-primary rounded-pill px-4">บันทึก</button></div></form></div></div></div>

<!-- Modal Edit -->
<div class="modal fade" id="editStaffModal"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content rounded-4"><form action="index.php?c=staff&a=edit" method="POST"><input type="hidden" name="id" id="edit_id"><div class="modal-header"><h5 class="modal-title fw-bold">แก้ไขข้อมูล</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
    <div class="col-md-6"><label class="form-label">ชื่อ-สกุล *</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">เลขบัตร ปชช.</label><input type="text" name="id_card" id="edit_id_card" class="form-control" maxlength="13"></div>
    <div class="col-md-6"><label class="form-label">กลุ่มเรทค่าเวร</label><select name="pay_rate_id" id="edit_pay_rate_id" class="form-select"><option value="">-- เลือก --</option><?php foreach($pay_rates as $pr) echo "<option value='{$pr['id']}'>{$pr['name']}</option>"; ?></select></div>
    <div class="col-md-6"><label class="form-label">ประเภทพนักงาน</label><select name="employee_type" id="edit_employee_type" class="form-select"><option value="ข้าราชการ/พนักงานท้องถิ่น">ข้าราชการ/พนักงานส่วนท้องถิ่น</option><option value="พนักงานจ้างตามภารกิจ">พนักงานจ้างตามภารกิจ</option><option value="พนักงานจ้างทั่วไป">พนักงานจ้างทั่วไป</option></select></div>
    <div class="col-md-6"><label class="form-label">ตำแหน่ง/วิชาชีพ</label><input type="text" name="type" id="edit_type" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">เลขประจำตำแหน่ง</label><input type="text" name="position_number" id="edit_position_number" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">วันที่เริ่มงาน</label><input type="text" name="start_date" id="edit_start_date" class="form-control thai-datepicker"></div>
    <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">สีในตาราง</label><select name="color_theme" id="edit_color" class="form-select"><option value="primary">น้ำเงิน</option><option value="success">เขียว</option><option value="danger">แดง</option><option value="warning">ส้มเหลือง</option><option value="info">ฟ้า</option><option value="secondary">เทา</option><option value="dark">ดำ</option></select></div>
    <div class="col-12"><hr></div>
    <div class="col-md-4"><label class="form-label">Username</label><input type="text" id="edit_username" class="form-control" readonly disabled></div>
    <div class="col-md-4"><label class="form-label">รหัสผ่านใหม่</label><input type="password" name="password" class="form-control" minlength="4"></div>
    <div class="col-md-4"><label class="form-label">Role *</label><select name="role" id="edit_role" class="form-select"><option value="STAFF">STAFF</option><option value="SCHEDULER">SCHEDULER</option><?php if(in_array($current_user_role, ['ADMIN', 'SUPERADMIN', 'DIRECTOR'])) echo '<option value="DIRECTOR">DIRECTOR</option>'; ?></select></div>
</div></div><div class="modal-footer"><button type="submit" class="btn btn-warning rounded-pill px-4 text-dark">อัปเดตข้อมูล</button></div></form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ==========================================
    // 🌟 1. ตั้งค่า Flatpickr ให้รองรับปี พ.ศ. (+543) ใน UI อย่างสมบูรณ์
    // ==========================================
    const updateThaiYear = function(instance) {
        if (!instance.currentYearElement) return;
        // ใช้ setTimeout เพื่อให้แน่ใจว่า UI ของ Flatpickr อัปเดตเสร็จแล้วค่อยเปลี่ยนค่า
        setTimeout(() => {
            instance.currentYearElement.value = instance.currentYear + 543;
        }, 10);
    };

    flatpickr(".thai-datepicker", { 
        locale: "th", 
        altInput: true, 
        altFormat: "j F Y", // รูปแบบที่จะนำไปแปลงเป็น พ.ศ. เพื่อแสดงผล
        dateFormat: "Y-m-d", // บันทึกลงตารางยังคงเป็น ค.ศ. (สากล) เหมือนเดิม
        onChange: function(selectedDates, dateStr, instance) {
            updateThaiYear(instance);
        },
        onReady: function(selectedDates, dateStr, instance) {
            updateThaiYear(instance);
            // เมื่อผู้ใช้พิมพ์ปี พ.ศ. เอง ให้แปลงกลับเป็น ค.ศ. เพื่อให้ปฏิทินทำงานต่อได้ถูกต้อง
            instance.currentYearElement.addEventListener('change', function() {
                let thaiYear = parseInt(this.value);
                if (thaiYear > 2400) {
                    instance.changeYear(thaiYear - 543);
                }
            });
        },
        onYearChange: function(selectedDates, dateStr, instance) {
            updateThaiYear(instance);
        },
        onMonthChange: function(selectedDates, dateStr, instance) {
            updateThaiYear(instance);
        },
        onOpen: function(selectedDates, dateStr, instance) {
            updateThaiYear(instance);
        },
        onValueUpdate: function(selectedDates, dateStr, instance) {
            updateThaiYear(instance);
        },
        formatDate: function(date, format, locale) {
            // ดักจับการ Format ค่า Alt Input (ช่องกรอกข้อมูลเสมือน) ให้ปี + 543
            if (format === "j F Y") {
                const d = date.getDate();
                const m = locale.months.longhand[date.getMonth()];
                const y = date.getFullYear() + 543; 
                return `${d} ${m} ${y}`;
            }
            return flatpickr.formatDate(date, format);
        }
    });

    // ==========================================
    // 🌟 2. Drag & Drop Sorting
    // ==========================================
    const tbody = document.getElementById('staff-table-body');
    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            handle: '.drag-handle', animation: 150,
            onEnd: function () {
                const orderData = Array.from(tbody.querySelectorAll('tr')).map((row, idx) => ({
                    id: row.dataset.id, order: idx + 1
                }));
                fetch('index.php?c=staff&a=update_order', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: orderData })
                });
            }
        });
    }

    // ==========================================
    // 🌟 3. Modal Edit Data Population
    // ==========================================
    const editModal = document.getElementById('editStaffModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('edit_id').value = btn.dataset.id;
            document.getElementById('edit_name').value = btn.dataset.name;
            document.getElementById('edit_type').value = btn.dataset.type;
            document.getElementById('edit_employee_type').value = btn.dataset.emptype;
            document.getElementById('edit_pay_rate_id').value = btn.dataset.payrate;
            document.getElementById('edit_color').value = btn.dataset.color;
            document.getElementById('edit_phone').value = btn.dataset.phone;
            document.getElementById('edit_id_card').value = btn.dataset.idcard;
            document.getElementById('edit_position_number').value = btn.dataset.posnum;

            let roleSel = document.getElementById('edit_role');
            if(!Array.from(roleSel.options).some(opt => opt.value === btn.dataset.role)) {
                roleSel.add(new Option(btn.dataset.role, btn.dataset.role));
            }
            roleSel.value = btn.dataset.role;

            const fpInput = document.querySelector('#edit_start_date');
            if (fpInput && fpInput._flatpickr) {
                if (btn.dataset.startdate) {
                    fpInput._flatpickr.setDate(btn.dataset.startdate);
                } else {
                    fpInput._flatpickr.clear();
                }
            }
        });
    }
});
</script>