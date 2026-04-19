<?php
// ที่อยู่ไฟล์: views/users/index.php

$current_user_role = trim(strtoupper($_SESSION['user']['role'] ?? 'STAFF'));
$is_superadmin = ($current_user_role === 'SUPERADMIN');
$is_admin = ($current_user_role === 'ADMIN');
$is_hr = ($current_user_role === 'HR'); // เพิ่มเช็คสิทธิ์ HR

// 🌟 FORCE FETCH ALL DATA
if (class_exists('Database')) {
    try {
        $db_tmp = (new Database())->getConnection();

        // 1. ดึงผู้ใช้งานทั้งหมด (ADMIN, SUPERADMIN และ HR เห็นทุกคน)
        if ($is_superadmin || $is_admin || $is_hr) {
            $sql_users = "SELECT u.*, h.name as hospital_name, pr.name as pay_rate_name
                          FROM users u
                          LEFT JOIN hospitals h ON u.hospital_id = h.id
                          LEFT JOIN pay_rates pr ON u.pay_rate_id = pr.id
                          ORDER BY h.id ASC, u.display_order ASC, u.id ASC";
            $users_list = $db_tmp->query($sql_users)->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $users_list = $users_list ?? [];
        }

        // 2. ดึง รพ. และ เรทเงินทั้งหมด
        $hospitals_list = $db_tmp->query("SELECT * FROM hospitals ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $pay_rates = $db_tmp->query("SELECT * FROM pay_rates ORDER BY display_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {}
} else {
    $users_list = $users_list ?? [];
    $hospitals_list = $hospitals_list ?? [];
    $pay_rates = $pay_rates ?? [];
}
?>

<!-- นำเข้า Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<!-- นำเข้า Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .table-modern th { font-weight: 600; color: #475569; font-size: 13px; background-color: #f8fafc; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding: 1rem; }
    .table-modern td { vertical-align: middle; font-size: 14px; border-bottom: 1px solid #f1f5f9; padding: 1rem; background-color: #ffffff; }
    .table-modern tbody tr:hover td { background-color: #f8fafc; }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }

    .filter-select2-container .select2-selection {
        border-radius: 50rem !important;
        border: 1px solid #f8f9fa !important;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
        min-height: 38px;
        display: flex;
        align-items: center;
        background-color: #ffffff;
    }
    .filter-select2-container .select2-selection:focus,
    .filter-select2-container.select2-container--focus .select2-selection {
        border-color: #86b7fe !important;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }
    .filter-select2-container .select2-selection__rendered {
        padding-left: 1.25rem;
        color: #475569;
        font-size: 14px;
    }
    .select2-dropdown { border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
    .select2-search__field { border-radius: 0.5rem !important; }
    
    /* 🌟 สีประจำตำแหน่ง HR */
    .bg-hr { background-color: #8b5cf6; color: white; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <!-- Header & Filters -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-people-fill fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">
                    ฐานข้อมูลบุคลากรเครือข่าย
                </h2>
                <p class="text-muted mb-0" style="font-size: 13px;">รายการบุคลากรทั้งหมดที่กรองพบ (<span id="visible-users-count"><?= count($users_list) ?></span> คน)</p>
            </div>
        </div>
        
        <div class="d-flex flex-wrap gap-2 align-items-center">
            
            <select id="filterHospital" class="form-select">
                <option value="all">แสดงทุกหน่วยบริการ</option>
                <option value="center">เฉพาะส่วนกลาง (ศูนย์ควบคุม)</option>
                <?php foreach($hospitals_list as $h): 
                    if (mb_strpos($h['name'], 'ส่วนกลาง') !== false || $h['id'] == 0) continue;
                ?>
                    <option value="<?= htmlspecialchars($h['name']) ?>"><?= htmlspecialchars($h['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="input-group shadow-sm rounded-pill overflow-hidden" style="width: 250px; border: 1px solid #e2e8f0;">
                <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-search"></i></span>
                <input type="text" id="userSearchInput" class="form-control border-0 bg-white" placeholder="ค้นหาชื่อ, ตำแหน่ง...">
            </div>

            <button class="btn btn-outline-success fw-bold rounded-pill shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#importUserModal">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> นำเข้าข้อมูล
            </button>

            <button class="btn btn-primary fw-bold rounded-pill shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill me-2"></i> เพิ่มบุคลากร
            </button>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert border-0 bg-success bg-opacity-10 text-success rounded-4 p-3 shadow-sm border-start border-success border-4 mb-4 alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> <?= nl2br($_SESSION['success_msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert border-0 bg-danger bg-opacity-10 text-danger rounded-4 p-3 shadow-sm border-start border-danger border-4 mb-4 alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= nl2br($_SESSION['error_msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <!-- ตาราง -->
    <div class="card card-modern overflow-hidden">
        <div class="table-responsive custom-scrollbar">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">ชื่อ-นามสกุล</th>
                        <th>หน่วยบริการ (สังกัด)</th>
                        <th>ประเภท/ตำแหน่ง</th>
                        <th>สิทธิ์ (Role)</th>
                        <th class="pe-4 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <?php if (empty($users_list)): ?>
                        <tr id="empty-state-row"><td colspan="5" class="text-center py-5 text-muted">ไม่พบข้อมูลบุคลากร</td></tr>
                    <?php else: foreach ($users_list as $user): 
                        $is_central_admin = (strtoupper($user['role'] ?? '') === 'SUPERADMIN');
                        $can_edit = true;
                        
                        // ป้องกัน ADMIN และ HR แก้ไขข้อมูล SUPERADMIN
                        if (($is_admin || $is_hr) && $is_central_admin) {
                            $can_edit = false; 
                        }

                        $is_center = empty($user['hospital_id']) || $user['hospital_id'] == 0 || mb_strpos($user['hospital_name'] ?? '', 'ส่วนกลาง') !== false;
                        $hosp_attr = $is_center ? 'center' : htmlspecialchars($user['hospital_name'] ?? '');
                    ?>
                        <tr class="user-row" data-hosp="<?= $hosp_attr ?>">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle me-3" style="width: 10px; height: 10px; background-color: var(--bs-<?= htmlspecialchars($user['color_theme'] ?? 'primary') ?>);"></div>
                                    <div>
                                        <div class="fw-bold text-dark user-name"><?= htmlspecialchars($user['name']) ?></div>
                                        <div class="text-muted small">@<?= htmlspecialchars($user['username']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($is_center): ?>
                                    <span class="badge bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25 px-2 py-1 rounded-pill user-hospital"><?= htmlspecialchars($user['hospital_name'] ?? 'ส่วนกลาง (ศูนย์ควบคุม)') ?></span>
                                <?php else: ?>
                                    <span class="text-dark fw-semibold user-hospital"><?= htmlspecialchars($user['hospital_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="user-position">
                                    <span class="badge bg-info bg-opacity-10 text-dark border px-2 py-1 rounded-pill"><?= htmlspecialchars($user['employee_type'] ?? 'ข้าราชการ') ?></span>
                                    <span class="badge bg-success bg-opacity-10 text-success border px-2 py-1 rounded-pill ms-1"><i class="bi bi-cash-coin"></i> <?= htmlspecialchars($user['pay_rate_name'] ?? 'ยังไม่จัดกลุ่ม') ?></span>
                                    <div class="text-muted small mt-1"><?= htmlspecialchars($user['type'] ?? '-') ?></div>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $role = trim(strtoupper($user['role'] ?? 'STAFF'));
                                    // 🌟 เพิ่มสไตล์สีให้กับตำแหน่ง HR (ใช้คลาส hr ที่กำหนดไว้ด้านบน)
                                    $bg = ['DIRECTOR'=>'danger','SCHEDULER'=>'warning text-dark','HR'=>'hr','ADMIN'=>'primary','SUPERADMIN'=>'dark'][$role] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $bg ?> shadow-sm"><?= $role ?></span>
                            </td>
                            <td class="pe-4 text-center text-nowrap">
                                <?php if ($can_edit): ?>
                                    <button class="btn btn-sm btn-light border text-primary rounded-circle shadow-sm" title="แก้ไข"
                                            data-bs-toggle="modal" data-bs-target="#editUserModal"
                                            data-id="<?= $user['id'] ?>"
                                            data-hospital="<?= empty($user['hospital_id']) ? '0' : $user['hospital_id'] ?>"
                                            data-name="<?= htmlspecialchars($user['name']) ?>"
                                            data-username="<?= htmlspecialchars($user['username']) ?>"
                                            data-role="<?= $role ?>"
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
                                    <a href="index.php?c=users&a=delete&id=<?= $user['id'] ?>" class="btn btn-sm btn-light border text-danger rounded-circle shadow-sm ms-1" onclick="return confirm('ยืนยันลบ?');"><i class="bi bi-trash-fill"></i></a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small" title="สงวนสิทธิ์เฉพาะส่วนกลาง">
                                        <i class="bi bi-lock-fill text-danger me-1"></i> ห้ามแก้ไข
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= 🌟 Modal 1: นำเข้าข้อมูล (Bulk Import) ================= -->
<div class="modal fade" id="importUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="index.php?c=users&a=import" method="POST" enctype="multipart/form-data">
                <div class="modal-header border-bottom-0 bg-success bg-opacity-10 rounded-top-4 pb-3">
                    <h5 class="modal-title fw-bold text-success"><i class="bi bi-file-earmark-excel-fill me-2"></i> นำเข้าบุคลากร (CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-dark mb-3">1. เตรียมไฟล์ข้อมูล</h6>
                            <p class="text-muted small mb-2">ดาวน์โหลดไฟล์ต้นแบบ (Template) และกรอกข้อมูลให้ครบถ้วน</p>
                            <a href="index.php?c=users&a=download_template" class="btn btn-sm btn-outline-success rounded-pill fw-bold mb-4">
                                <i class="bi bi-download me-1"></i> ดาวน์โหลด Template .csv
                            </a>
                            <h6 class="fw-bold text-dark mb-3">2. อัปโหลดไฟล์</h6>
                            <input type="file" name="import_file" class="form-control mb-2" accept=".csv" required>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-light rounded-4 p-3 h-100 border">
                                <h6 class="fw-bold text-dark mb-2" style="font-size:13px;"><i class="bi bi-info-circle text-primary"></i> รหัสอ้างอิงหน่วยบริการ</h6>
                                <div class="overflow-auto custom-scrollbar" style="max-height: 150px;">
                                    <table class="table table-sm table-bordered bg-white mb-0" style="font-size: 12px;">
                                        <thead class="table-light sticky-top"><tr><th>รหัส (ID)</th><th>ชื่อหน่วยบริการ</th></tr></thead>
                                        <tbody>
                                            <tr><td class="text-center fw-bold text-danger">0</td><td>ส่วนกลาง (ศูนย์ควบคุม)</td></tr>
                                            <?php foreach($hospitals_list as $h): ?>
                                                <tr><td class="text-center fw-bold"><?= $h['id'] ?></td><td><?= htmlspecialchars($h['name']) ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill fw-bold px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success rounded-pill fw-bold shadow-sm px-4"><i class="bi bi-upload me-1"></i> นำเข้าข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= 🌟 Modal Add ================= -->
<div class="modal fade" id="addUserModal"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content rounded-4"><form action="index.php?c=users&a=add" method="POST"><div class="modal-header"><h5 class="modal-title fw-bold">เพิ่มบุคลากร</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
    
    <div class="col-md-12">
        <label class="form-label">หน่วยบริการ</label>
        <select name="hospital_id" id="addHospitalSelect" class="form-select" required>
            <option value="0">ส่วนกลาง (ศูนย์ควบคุม)</option>
            <?php foreach($hospitals_list as $h) echo "<option value='{$h['id']}'>{$h['name']}</option>"; ?>
        </select>
    </div>
    
    <div class="col-md-6"><label class="form-label">ชื่อ-สกุล *</label><input type="text" name="name" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">เลขบัตร ปชช.</label><input type="text" name="id_card" class="form-control" maxlength="13"></div>
    <div class="col-md-6"><label class="form-label">กลุ่มเรทค่าเวร</label><select name="pay_rate_id" class="form-select"><option value="">-- เลือก --</option><?php foreach($pay_rates as $pr) echo "<option value='{$pr['id']}'>{$pr['name']}</option>"; ?></select></div>
    <div class="col-md-6"><label class="form-label">ประเภทพนักงาน</label><select name="employee_type" class="form-select"><option value="ข้าราชการ/พนักงานท้องถิ่น">ข้าราชการ/พนักงานส่วนท้องถิ่น</option><option value="พนักงานจ้างตามภารกิจ">พนักงานจ้างตามภารกิจ</option><option value="พนักงานจ้างทั่วไป">พนักงานจ้างทั่วไป</option></select></div>
    <div class="col-md-6"><label class="form-label">ตำแหน่ง/วิชาชีพ</label><input type="text" name="type" class="form-control" placeholder="เช่น นักทรัพยากรบุคคลปฏิบัติการ"></div>
    <div class="col-md-6"><label class="form-label">เลขประจำตำแหน่ง</label><input type="text" name="position_number" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">วันที่เริ่มงาน</label><input type="text" name="start_date" class="form-control thai-datepicker"></div>
    <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input type="text" name="phone" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">สีในตาราง</label><select name="color_theme" class="form-select"><option value="primary">น้ำเงิน</option><option value="success">เขียว</option><option value="danger">แดง</option><option value="warning">ส้มเหลือง</option><option value="info">ฟ้า</option><option value="secondary">เทา</option><option value="dark">ดำ</option></select></div>
    <div class="col-12"><hr></div>
    <div class="col-md-4"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required minlength="4"></div>
    <div class="col-md-4">
        <label class="form-label">Role (สิทธิ์การใช้งาน) *</label>
        <select name="role" class="form-select border-primary bg-primary bg-opacity-10 fw-bold text-primary">
            <option value="STAFF">STAFF (ผู้ปฏิบัติงาน)</option>
            <option value="SCHEDULER">SCHEDULER (ผู้จัดเวร รพ.สต.)</option>
            <option value="DIRECTOR">DIRECTOR (ผอ. รพ.สต.)</option>
            <!-- 🌟 เพิ่มตัวเลือก HR สำหรับผู้ดูแลงานทรัพยากรบุคคลโดยเฉพาะ -->
            <option value="HR">HR (นักทรัพยากรบุคคล)</option>
            <option value="ADMIN">ADMIN (แอดมินส่วนกลาง)</option>
            <?php if($is_superadmin) echo '<option value="SUPERADMIN">SUPERADMIN (สูงสุด)</option>'; ?>
        </select>
    </div>
</div></div><div class="modal-footer"><button type="submit" class="btn btn-primary rounded-pill px-4">บันทึก</button></div></form></div></div></div>

<!-- ================= 🌟 Modal Edit ================= -->
<div class="modal fade" id="editUserModal"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content rounded-4"><form action="index.php?c=users&a=edit" method="POST"><input type="hidden" name="id" id="edit_id"><div class="modal-header"><h5 class="modal-title fw-bold">แก้ไขข้อมูล</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
    
    <div class="col-md-12">
        <label class="form-label">หน่วยบริการ</label>
        <select name="hospital_id" id="edit_hospital" class="form-select" required>
            <option value="0">ส่วนกลาง (ศูนย์ควบคุม)</option>
            <?php foreach($hospitals_list as $h) echo "<option value='{$h['id']}'>{$h['name']}</option>"; ?>
        </select>
    </div>

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
    <div class="col-md-4"><label class="form-label">รหัสผ่านใหม่ (ปล่อยว่างถ้าไม่เปลี่ยน)</label><input type="password" name="password" class="form-control" minlength="4"></div>
    <div class="col-md-4">
        <label class="form-label">Role (สิทธิ์การใช้งาน) *</label>
        <select name="role" id="edit_role" class="form-select border-warning bg-warning bg-opacity-10 fw-bold">
            <option value="STAFF">STAFF (ผู้ปฏิบัติงาน)</option>
            <option value="SCHEDULER">SCHEDULER (ผู้จัดเวร รพ.สต.)</option>
            <option value="DIRECTOR">DIRECTOR (ผอ. รพ.สต.)</option>
            <!-- 🌟 เพิ่มตัวเลือก HR สำหรับแก้ไขสิทธิ์ผู้ดูแลงานทรัพยากรบุคคลโดยเฉพาะ -->
            <option value="HR">HR (นักทรัพยากรบุคคล)</option>
            <option value="ADMIN">ADMIN (แอดมินส่วนกลาง)</option>
            <?php if($is_superadmin) echo '<option value="SUPERADMIN">SUPERADMIN (สูงสุด)</option>'; ?>
        </select>
    </div>
</div></div><div class="modal-footer"><button type="submit" class="btn btn-warning rounded-pill px-4 text-dark">อัปเดตข้อมูล</button></div></form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    if ($.fn.select2) {
        $('#filterHospital').select2({ theme: 'bootstrap-5', width: '260px', containerCssClass: 'filter-select2-container' }).on('change', filterTable);
        $('#addHospitalSelect').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#addUserModal') });
        $('#edit_hospital').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#editUserModal') });
    }

    const updateThaiYear = function(instance) {
        if (!instance.currentYearElement) return;
        setTimeout(() => { instance.currentYearElement.value = instance.currentYear + 543; }, 10);
    };

    flatpickr(".thai-datepicker", { 
        locale: "th", altInput: true, altFormat: "j F Y", dateFormat: "Y-m-d", 
        onChange: function(s, d, i) { updateThaiYear(i); },
        onReady: function(s, d, i) {
            updateThaiYear(i);
            i.currentYearElement.addEventListener('change', function() {
                let ty = parseInt(this.value);
                if (ty > 2400) i.changeYear(ty - 543);
            });
        },
        onYearChange: function(s, d, i) { updateThaiYear(i); },
        onMonthChange: function(s, d, i) { updateThaiYear(i); },
        onOpen: function(s, d, i) { updateThaiYear(i); },
        onValueUpdate: function(s, d, i) { updateThaiYear(i); },
        formatDate: function(date, format, locale) {
            if (format === "j F Y") return `${date.getDate()} ${locale.months.longhand[date.getMonth()]} ${date.getFullYear() + 543}`;
            return flatpickr.formatDate(date, format);
        }
    });

    const searchInput = document.getElementById('userSearchInput');
    const hospitalFilter = document.getElementById('filterHospital');
    const countDisplay = document.getElementById('visible-users-count');
    
    function filterTable() {
        const term = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const hosp = hospitalFilter ? hospitalFilter.value : 'all';
        const rows = document.querySelectorAll('#users-table-body .user-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.querySelector('.user-name')?.textContent.toLowerCase() || '';
            const pos = row.querySelector('.user-position')?.textContent.toLowerCase() || '';
            const rowHosp = row.getAttribute('data-hosp');
            
            const matchSearch = name.includes(term) || pos.includes(term);
            const matchHosp = (hosp === 'all') || (hosp === 'center' && rowHosp === 'center') || (rowHosp === hosp);
            
            if (matchSearch && matchHosp) { row.style.display = ''; visibleCount++; } 
            else { row.style.display = 'none'; }
        });

        if (countDisplay) countDisplay.textContent = visibleCount;
        const emptyRow = document.getElementById('empty-state-row');
        if (emptyRow) emptyRow.style.display = (visibleCount === 0) ? '' : 'none';
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    filterTable();

    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('edit_id').value = btn.dataset.id;
            
            if ($.fn.select2) $('#edit_hospital').val(btn.dataset.hospital || '0').trigger('change');
            else document.getElementById('edit_hospital').value = btn.dataset.hospital || '0';

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
                if (btn.dataset.startdate) fpInput._flatpickr.setDate(btn.dataset.startdate);
                else fpInput._flatpickr.clear();
            }
        });
    }
});
</script>