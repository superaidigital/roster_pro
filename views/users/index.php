<?php
// ที่อยู่ไฟล์: views/users/index.php

// รับข้อมูลจาก Controller
$current_user_role = trim(strtoupper($_SESSION['user']['role'] ?? 'STAFF'));
$is_superadmin = ($current_user_role === 'SUPERADMIN');
$is_admin = ($current_user_role === 'ADMIN');
$is_hr = ($current_user_role === 'HR');

$users_list = $users_list ?? [];
$hospitals_list = $hospitals_list ?? [];
$pay_rates = $pay_rates ?? [];

// สร้าง Map สำหรับชื่อหน่วยบริการเพื่อความรวดเร็วในการแสดงผล
$hosp_map = [0 => 'ส่วนกลาง (สสจ./รพ.)'];
foreach($hospitals_list as $h) {
    $hosp_map[$h['id']] = $h['name'];
}
?>

<!-- Include Required Plugins -->
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
    .avatar-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-person-lines-fill fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">จัดการผู้ใช้งานและเครือข่าย</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">รายชื่อบุคลากรทั้งหมดภายใต้การกำกับดูแลของคุณ</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#importCsvModal">
                <i class="bi bi-file-earmark-arrow-up me-1"></i> นำเข้า CSV
            </button>
            <button class="btn btn-primary fw-bold rounded-pill shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addUserModal" onclick="resetForm()">
                <i class="bi bi-person-plus-fill me-1"></i> เพิ่มผู้ใช้งาน
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
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

    <!-- Real-time Filter & Search Section -->
    <div class="card card-modern mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group shadow-sm rounded-3">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อ, Username, เบอร์โทร...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filterHospital" class="form-select shadow-sm rounded-3">
                        <option value="">-- ทุกหน่วยบริการ --</option>
                        <option value="0">ส่วนกลาง (สสจ./รพ.)</option>
                        <?php foreach($hospitals_list as $h): ?>
                            <option value="<?= $h['id'] ?>">🏥 <?= htmlspecialchars($h['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filterRole" class="form-select shadow-sm rounded-3">
                        <option value="">-- ทุกสิทธิ์ --</option>
                        <option value="STAFF">STAFF</option>
                        <option value="SCHEDULER">SCHEDULER</option>
                        <option value="HR">HR</option>
                        <option value="ADMIN">ADMIN</option>
                        <option value="SUPERADMIN">SUPERADMIN</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filterStatus" class="form-select shadow-sm rounded-3">
                        <option value="">-- ทุกสถานะ --</option>
                        <option value="1">🟢 เปิดใช้งาน</option>
                        <option value="0">🔴 ระงับบัญชี</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-light border w-100 rounded-3 shadow-sm" onclick="clearFilters()" title="ล้างตัวกรอง">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Card -->
    <div class="card card-modern overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-stars text-primary me-2"></i>ทำเนียบบุคลากร</h6>
            <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-grip-vertical"></i> ลากที่ไอคอนเพื่อสลับตำแหน่ง</span>
        </div>
        <div class="table-responsive">
            <table class="table table-modern mb-0" style="min-width: 1100px;">
                <thead>
                    <tr>
                        <th class="text-center" width="5%"><i class="bi bi-arrow-down-up"></i></th>
                        <th width="20%">ชื่อ-นามสกุล / Login</th>
                        <th width="18%">หน่วยบริการ</th>
                        <th width="15%">ตำแหน่ง/วิชาชีพ</th>
                        <th width="12%">เรทค่าตอบแทน</th>
                        <th width="15%">สิทธิ์ & สถานะ</th>
                        <th class="text-center" width="15%">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <?php if(empty($users_list)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">ไม่พบข้อมูลผู้ใช้งานในระบบ</td></tr>
                    <?php else: foreach($users_list as $user): 
                        $theme = $user['color_theme'] ?? 'primary';
                        $initial = mb_substr($user['name'], 0, 1, 'UTF-8');
                    ?>
                        <!-- เพิ่ม Data Attributes เพื่อให้ JS กรองข้อมูลง่ายขึ้น -->
                        <tr class="user-row" 
                            data-id="<?= $user['id'] ?>" 
                            data-hospital="<?= $user['hospital_id'] ?? 0 ?>" 
                            data-role="<?= strtoupper($user['role']) ?>" 
                            data-status="<?= $user['is_active'] ?? 1 ?>">
                            
                            <td class="text-center"><i class="bi bi-grip-vertical drag-handle"></i></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-<?= $theme ?> me-3"><?= $initial ?></div>
                                    <div>
                                        <div class="fw-bold text-dark user-name"><?= htmlspecialchars($user['name']) ?></div>
                                        <div class="small text-muted font-monospace user-username"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($user['username'] ?? $user['phone']) ?></div>
                                        <span class="d-none user-phone"><?= htmlspecialchars($user['phone'] ?? '') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 180px;">
                                    <span class="badge bg-light text-dark border px-2 py-1"><i class="bi bi-hospital me-1"></i><?= htmlspecialchars($hosp_map[$user['hospital_id'] ?? 0]) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info bg-opacity-10 text-dark border border-info border-opacity-25 px-2 py-1 rounded-pill mb-1"><?= htmlspecialchars($user['employee_type'] ?? 'ทั่วไป') ?></span>
                                <div class="small text-muted"><i class="bi bi-briefcase me-1"></i><?= htmlspecialchars($user['type'] ?? '-') ?></div>
                            </td>
                            <td>
                                <div class="small text-secondary fw-medium"><?= htmlspecialchars($user['pay_rate_name'] ?? 'ไม่ได้ตั้งค่า') ?></div>
                            </td>
                            <td>
                                <?php 
                                    $role_color = 'bg-secondary';
                                    if($user['role'] == 'SUPERADMIN') $role_color = 'bg-danger';
                                    elseif($user['role'] == 'ADMIN' || $user['role'] == 'HR') $role_color = 'bg-primary';
                                    elseif($user['role'] == 'SCHEDULER') $role_color = 'bg-success';
                                ?>
                                <span class="badge <?= $role_color ?> bg-opacity-10 text-dark border px-2 py-1 mb-1" style="font-size: 11px;"><i class="bi bi-shield-lock me-1"></i><?= htmlspecialchars($user['role']) ?></span>
                                <div>
                                    <?php if(isset($user['is_active']) && $user['is_active'] == 1): ?>
                                        <span class="badge bg-success rounded-pill" style="font-size: 10px;">เปิดใช้งาน</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill" style="font-size: 10px;">ระงับบัญชี</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <!-- Status Toggle -->
                                <?php if($user['id'] != $_SESSION['user']['id']): ?>
                                    <a href="index.php?c=users&a=toggle&id=<?= $user['id'] ?>&status=<?= ($user['is_active'] ?? 1) == 1 ? 0 : 1 ?>" 
                                       class="btn btn-sm btn-light border <?= ($user['is_active'] ?? 1) == 1 ? 'text-success' : 'text-danger' ?> rounded-circle shadow-sm" 
                                       title="<?= ($user['is_active'] ?? 1) == 1 ? 'ระงับการใช้งาน' : 'เปิดใช้งาน' ?>">
                                        <i class="bi bi-power"></i>
                                    </a>
                                <?php endif; ?>

                                <!-- Edit Button -->
                                <button class="btn btn-sm btn-light border text-primary rounded-circle shadow-sm ms-1" 
                                        data-bs-toggle="modal" data-bs-target="#editUserModal"
                                        data-id="<?= $user['id'] ?>"
                                        data-hospital="<?= htmlspecialchars($user['hospital_id'] ?? '0') ?>"
                                        data-name="<?= htmlspecialchars($user['name']) ?>"
                                        data-username="<?= htmlspecialchars($user['username'] ?? '') ?>"
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
                                
                                <!-- Delete Button -->
                                <?php if($user['id'] != $_SESSION['user']['id']): ?>
                                    <a href="index.php?c=users&a=delete&id=<?= $user['id'] ?>" class="btn btn-sm btn-light border text-danger rounded-circle ms-1 shadow-sm" onclick="return confirm('ยืนยันการลบผู้ใช้งานท่านนี้ออกจากระบบ? ข้อมูลเวรจะถูกลบไปด้วย');"><i class="bi bi-trash-fill"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Import CSV -->
<div class="modal fade" id="importCsvModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="index.php?c=users&a=import" method="POST" enctype="multipart/form-data">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-file-earmark-excel text-success me-2"></i>นำเข้าบุคลากร (CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 bg-info bg-opacity-10 text-dark rounded-4 mb-4" style="font-size: 13px;">
                        <i class="bi bi-info-circle-fill me-2"></i>กรุณาใช้ไฟล์เทมเพลตที่ถูกต้องเพื่อป้องกันข้อผิดพลาดในการประมวลผล
                    </div>
                    <div class="text-center mb-4">
                        <a href="index.php?c=users&a=download_template" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                            <i class="bi bi-download me-1"></i> ดาวน์โหลด Template .CSV
                        </a>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">เลือกไฟล์จากคอมพิวเตอร์ของคุณ <span class="text-danger">*</span></label>
                        <input type="file" name="import_file" class="form-control rounded-3" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4 px-4 py-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold"><i class="bi bi-cloud-upload me-1"></i> เริ่มนำเข้าข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="index.php?c=users&a=add" method="POST" id="addForm">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-plus-fill text-primary me-2"></i>เพิ่มผู้ใช้งานระบบ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">สังกัดหน่วยบริการ (Hospital) *</label>
                            <select name="hospital_id" class="form-select rounded-3 border-primary" required>
                                <option value="0">🏢 ส่วนกลาง (สสจ. / โรงพยาบาลเครือข่าย)</option>
                                <?php foreach($hospitals_list as $h): ?>
                                    <option value="<?= $h['id'] ?>">🏥 <?= htmlspecialchars($h['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ชื่อ-สกุล *</label>
                            <input type="text" name="name" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">เลขบัตรประชาชน</label>
                            <input type="text" name="id_card" class="form-control rounded-3" maxlength="13">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">กลุ่มเรทค่าตอบแทน</label>
                            <select name="pay_rate_id" class="form-select rounded-3">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php foreach($pay_rates as $pr) echo "<option value='{$pr['id']}'>{$pr['name']}</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ประเภทพนักงาน</label>
                            <select name="employee_type" class="form-select rounded-3">
                                <option value="ข้าราชการ/พนักงานท้องถิ่น">ข้าราชการ/พนักงานท้องถิ่น</option>
                                <option value="พนักงานจ้างตามภารกิจ">พนักงานจ้างตามภารกิจ</option>
                                <option value="พนักงานจ้างทั่วไป">พนักงานจ้างทั่วไป</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ตำแหน่ง/วิชาชีพ</label>
                            <input type="text" name="type" class="form-control rounded-3" placeholder="เช่น พยาบาลวิชาชีพ">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-control rounded-3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">วันที่เริ่มงาน</label>
                            <input type="text" name="start_date" class="form-control rounded-3 thai-datepicker bg-white" placeholder="เลือกวันที่">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ธีมสีตัวแทน (ในตารางเวร)</label>
                            <select name="color_theme" class="form-select rounded-3">
                                <option value="primary">🔵 น้ำเงิน</option>
                                <option value="success">🟢 เขียว</option>
                                <option value="danger">🔴 แดง</option>
                                <option value="warning">🟠 ส้มเหลือง</option>
                                <option value="info">🩵 ฟ้า</option>
                                <option value="secondary">⚪ เทา</option>
                            </select>
                        </div>
                        <div class="col-12"><hr class="my-2"></div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-primary">Username *</label>
                            <input type="text" name="username" class="form-control rounded-3 border-primary" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-primary">Password *</label>
                            <input type="password" name="password" class="form-control rounded-3 border-primary" required minlength="4">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-primary">สิทธิ์เข้าใช้งานระบบ *</label>
                            <select name="role" class="form-select rounded-3 border-primary" required>
                                <option value="STAFF">พนักงานทั่วไป (STAFF)</option>
                                <option value="SCHEDULER">ผู้จัดเวร (SCHEDULER)</option>
                                <?php if($is_admin || $is_superadmin || $is_hr): ?>
                                    <option value="HR">ฝ่ายบุคคล (HR)</option>
                                    <option value="ADMIN">ผู้ดูแลระบบ (ADMIN)</option>
                                <?php endif; ?>
                                <?php if($is_superadmin): ?>
                                    <option value="SUPERADMIN">ผู้ดูแลสูงสุด (SUPERADMIN)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4 px-4 py-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="bi bi-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="index.php?c=users&a=edit" method="POST" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>แก้ไขข้อมูลผู้ใช้งาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">สังกัดหน่วยบริการ *</label>
                            <select name="hospital_id" id="edit_hospital" class="form-select rounded-3 bg-light border-warning" required>
                                <option value="0">🏢 ส่วนกลาง (สสจ. / โรงพยาบาลเครือข่าย)</option>
                                <?php foreach($hospitals_list as $h): ?>
                                    <option value="<?= $h['id'] ?>">🏥 <?= htmlspecialchars($h['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ชื่อ-สกุล *</label>
                            <input type="text" name="name" id="edit_name" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">เลขบัตรประชาชน</label>
                            <input type="text" name="id_card" id="edit_id_card" class="form-control rounded-3" maxlength="13">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">กลุ่มเรทค่าตอบแทน</label>
                            <select name="pay_rate_id" id="edit_pay_rate_id" class="form-select rounded-3">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php foreach($pay_rates as $pr) echo "<option value='{$pr['id']}'>{$pr['name']}</option>"; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ตำแหน่ง/วิชาชีพ</label>
                            <input type="text" name="type" id="edit_type" class="form-control rounded-3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control rounded-3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ธีมสีตัวแทน</label>
                            <select name="color_theme" id="edit_color" class="form-select rounded-3">
                                <option value="primary">🔵 น้ำเงิน</option>
                                <option value="success">🟢 เขียว</option>
                                <option value="danger">🔴 แดง</option>
                                <option value="warning">🟠 ส้มเหลือง</option>
                                <option value="info">🩵 ฟ้า</option>
                                <option value="secondary">⚪ เทา</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">วันที่เริ่มงาน</label>
                            <input type="text" name="start_date" id="edit_start_date" class="form-control rounded-3 thai-datepicker bg-white">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted">Username</label>
                            <input type="text" id="edit_username" class="form-control rounded-3 bg-light" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-warning">รหัสผ่านใหม่ <small>(เว้นว่างถ้าไม่เปลี่ยน)</small></label>
                            <input type="password" name="password" class="form-control rounded-3" minlength="4">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">สิทธิ์การใช้งาน *</label>
                            <select name="role" id="edit_role" class="form-select rounded-3 border-warning" required>
                                <option value="STAFF">พนักงานทั่วไป (STAFF)</option>
                                <option value="SCHEDULER">ผู้จัดเวร (SCHEDULER)</option>
                                <?php if(in_array($current_user_role, ['ADMIN', 'SUPERADMIN', 'DIRECTOR'])): ?>
                                    <option value="DIRECTOR">ผู้อำนวยการ (DIRECTOR)</option>
                                <?php endif; ?>
                                <?php if(in_array($current_user_role, ['ADMIN', 'SUPERADMIN', 'HR'])): ?>
                                    <option value="HR">ฝ่ายบุคคล (HR)</option>
                                <?php endif; ?>
                                <?php if(in_array($current_user_role, ['ADMIN', 'SUPERADMIN'])): ?>
                                    <option value="ADMIN">ผู้ดูแลระบบ (ADMIN)</option>
                                <?php endif; ?>
                                <?php if($is_superadmin): ?>
                                    <option value="SUPERADMIN">ผู้ดูแลสูงสุด (SUPERADMIN)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4 px-4 py-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark"><i class="bi bi-check-circle me-1"></i> ยืนยันการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ฟังก์ชัน Real-time Filter/Search
function applyFilters() {
    const searchText = document.getElementById('searchInput').value.toLowerCase();
    const filterHospital = document.getElementById('filterHospital').value;
    const filterRole = document.getElementById('filterRole').value;
    const filterStatus = document.getElementById('filterStatus').value;

    const rows = document.querySelectorAll('.user-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const name = row.querySelector('.user-name').textContent.toLowerCase();
        const username = row.querySelector('.user-username').textContent.toLowerCase();
        const phone = row.querySelector('.user-phone').textContent.toLowerCase();
        
        const rowHospital = row.dataset.hospital;
        const rowRole = row.dataset.role;
        const rowStatus = row.dataset.status;

        // เช็คเงื่อนไขต่างๆ
        const matchSearch = name.includes(searchText) || username.includes(searchText) || phone.includes(searchText);
        const matchHospital = filterHospital === '' || rowHospital === filterHospital;
        const matchRole = filterRole === '' || rowRole === filterRole;
        const matchStatus = filterStatus === '' || rowStatus === filterStatus;

        if (matchSearch && matchHospital && matchRole && matchStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // แสดงข้อความเมื่อค้นหาไม่พบ
    let emptyRow = document.getElementById('empty-search-row');
    if (visibleCount === 0) {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.id = 'empty-search-row';
            emptyRow.innerHTML = '<td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-search me-2 fs-3 d-block mb-2"></i>ไม่พบข้อมูลบุคลากรที่ตรงกับเงื่อนไขการค้นหา</td>';
            document.getElementById('users-table-body').appendChild(emptyRow);
        }
        emptyRow.style.display = '';
    } else {
        if (emptyRow) emptyRow.style.display = 'none';
    }
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterHospital').value = '';
    document.getElementById('filterRole').value = '';
    document.getElementById('filterStatus').value = '';
    applyFilters();
}

// ผูก Event Listener กับช่องค้นหา
document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('filterHospital').addEventListener('change', applyFilters);
document.getElementById('filterRole').addEventListener('change', applyFilters);
document.getElementById('filterStatus').addEventListener('change', applyFilters);


// ฟังก์ชันสร้างแจ้งเตือนมุมล่างขวา (Bootstrap Toast)
function showToastAlert(message, type = 'success') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '1055';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
    toast.className = `toast align-items-center text-white bg-${type} border-0 show shadow-lg`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body fw-bold" style="font-size: 14px;">
          <i class="bi ${icon} me-2"></i> ${message}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;

    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
    
    toast.querySelector('.btn-close').addEventListener('click', () => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Thai Year Datepicker Configuration
    const updateThaiYear = function(instance) {
        if (!instance.currentYearElement) return;
        setTimeout(() => {
            instance.currentYearElement.value = instance.currentYear + 543;
        }, 10);
    };

    flatpickr(".thai-datepicker", { 
        locale: "th", altInput: true, altFormat: "j F Y", dateFormat: "Y-m-d",
        onChange: updateThaiYear, onMonthChange: updateThaiYear, onYearChange: updateThaiYear, onOpen: updateThaiYear, onValueUpdate: updateThaiYear,
        onReady: function(selectedDates, dateStr, instance) {
            updateThaiYear(instance);
            instance.currentYearElement.addEventListener('change', function() {
                let thaiYear = parseInt(this.value);
                if (thaiYear > 2400) instance.changeYear(thaiYear - 543);
            });
        },
        formatDate: function(date, format, locale) {
            if (format === "j F Y") {
                return `${date.getDate()} ${locale.months.longhand[date.getMonth()]} ${date.getFullYear() + 543}`;
            }
            return flatpickr.formatDate(date, format);
        }
    });

    // 2. Drag & Drop Implementation (พร้อมแจ้งเตือน)
    const tbody = document.getElementById('users-table-body');
    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            handle: '.drag-handle', animation: 150, ghostClass: 'sortable-ghost',
            onEnd: function () {
                const orderData = Array.from(tbody.querySelectorAll('tr.user-row')).map((row, idx) => ({
                    id: row.dataset.id, order: idx + 1
                }));
                
                fetch('index.php?c=users&a=update_order', {
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: orderData })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToastAlert('สลับตำแหน่งการจัดเรียงเรียบร้อยแล้ว', 'success');
                    } else {
                        showToastAlert('ไม่สามารถบันทึกลำดับได้', 'danger');
                    }
                })
                .catch(error => {
                    showToastAlert('เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว', 'danger');
                });
            }
        });
    }

    // 3. Modal Population Logic
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('edit_id').value = btn.dataset.id;
            document.getElementById('edit_hospital').value = btn.dataset.hospital || '0';
            document.getElementById('edit_name').value = btn.dataset.name;
            document.getElementById('edit_username').value = btn.dataset.username;
            document.getElementById('edit_type').value = btn.dataset.type;
            document.getElementById('edit_pay_rate_id').value = btn.dataset.payrate;
            document.getElementById('edit_color').value = btn.dataset.color;
            document.getElementById('edit_phone').value = btn.dataset.phone;
            document.getElementById('edit_id_card').value = btn.dataset.idcard;
            
            // ดักจับและเพิ่มสิทธิ์ลงใน Dropdown หากไม่มีตัวเลือกนี้อยู่
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

function resetForm() {
    document.getElementById('addForm').reset();
    const fpInputs = document.querySelectorAll('#addForm .thai-datepicker');
    fpInputs.forEach(input => { if (input._flatpickr) input._flatpickr.clear(); });
}
</script>