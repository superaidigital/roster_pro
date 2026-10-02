<?php
// ที่อยู่ไฟล์: views/users/index.php

// รับข้อมูลจาก Controller
// ป้องกัน Error กรณี $_SESSION['user']['role'] เป็น Array 
$raw_role = $_SESSION['user']['role'] ?? 'STAFF';
$current_user_role = is_array($raw_role) ? 'STAFF' : trim(strtoupper($raw_role));

$is_superadmin = ($current_user_role === 'SUPERADMIN');
$is_admin = ($current_user_role === 'ADMIN');
$is_hr = ($current_user_role === 'HR');
$is_director = ($current_user_role === 'DIRECTOR');

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
<!-- jQuery (จำเป็นสำหรับ DataTables และ Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables สำหรับแบ่งหน้าตาราง -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Select2 สำหรับพิมพ์ค้นหาหน่วยบริการ -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
    
    /* ปรับแต่ง Select2 ให้เข้ากับ Bootstrap 5 */
    .select2-container .select2-selection--single { height: 38px; border: 1px solid #dee2e6; border-radius: 0.375rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
    
    /* ซ่อน Search bar เดิมของ DataTables เพราะเราใช้กล่องค้นหาด้านบนแทน */
    .dataTables_filter { display: none; }
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
            <!-- ปุ่มลบหลายรายการ -->
            <button class="btn btn-danger fw-bold rounded-pill shadow-sm px-4 d-none" id="btn-bulk-delete" onclick="bulkDelete()">
                <i class="bi bi-trash me-1"></i> ลบที่เลือก (<span id="selected-count">0</span>)
            </button>
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
                    <select id="filterHospital" class="form-select shadow-sm rounded-3 select2-filter">
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
                        <option value="DIRECTOR">DIRECTOR</option>
                        <option value="HR">HR</option>
                        <?php if($is_admin || $is_superadmin): ?>
                        <option value="ADMIN">ADMIN</option>
                        <option value="SUPERADMIN">SUPERADMIN</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filterStatus" class="form-select shadow-sm rounded-3">
                        <option value="">-- ทุกสถานะ --</option>
                        <option value="1">🟢 เปิดใช้งาน</option>
                        <option value="0">🔴 ระงับบัญชี/พ้นสภาพ</option>
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
        <div class="table-responsive p-3">
            <table id="usersTable" class="table table-modern mb-0" style="min-width: 1100px;">
                <thead>
                    <tr>
                        <th width="3%" class="text-center">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                        </th>
                        <th class="text-center" width="5%"><i class="bi bi-arrow-down-up"></i></th>
                        <th width="20%">ชื่อ-นามสกุล / Login</th>
                        <th width="18%">หน่วยบริการ</th>
                        <th width="15%">ตำแหน่ง/วิชาชีพ</th>
                        <th width="12%">เรทค่าตอบแทน</th>
                        <th width="15%">สิทธิ์ & สถานะ</th>
                        <th class="text-center" width="12%">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <?php if(!empty($users_list)): foreach($users_list as $user): 
                        
                        // --- Fix: ป้องกัน Error "Array to string conversion" ด้วยการกรองข้อมูลทุกฟิลด์ ---
                        $u_id = is_array($user['id'] ?? '') ? '' : (string)($user['id'] ?? '');
                        
                        $raw_u_role = is_array($user['role'] ?? 'STAFF') ? 'STAFF' : (string)($user['role'] ?? 'STAFF');
                        $u_role = strtoupper(trim($raw_u_role));

                        // กฎ: ถ้าล็อกอินเป็น Admin จะไม่เห็น Superadmin
                        if($is_admin && $u_role === 'SUPERADMIN') {
                            continue;
                        }

                        // สกัดข้อมูลตัวอื่นๆ ให้อยู่ในรูป String/Int เสมอ
                        $u_hospital_id = is_array($user['hospital_id'] ?? 0) ? 0 : (int)($user['hospital_id'] ?? 0);
                        $u_is_active = is_array($user['is_active'] ?? 1) ? 1 : (int)($user['is_active'] ?? 1);
                        
                        $u_name = is_array($user['name'] ?? '') ? 'ไม่มีชื่อ' : (string)($user['name'] ?? 'ไม่มีชื่อ');
                        $u_username = is_array($user['username'] ?? '') ? '' : (string)($user['username'] ?? '');
                        $u_phone = is_array($user['phone'] ?? '') ? '' : (string)($user['phone'] ?? '');
                        $u_login = $u_username !== '' ? $u_username : $u_phone;
                        
                        $u_position = is_array($user['position'] ?? '-') ? '-' : (string)($user['position'] ?? '-');
                        $u_emp_type = is_array($user['employee_type'] ?? 'ทั่วไป') ? 'ทั่วไป' : (string)($user['employee_type'] ?? 'ทั่วไป');
                        $u_type = is_array($user['type'] ?? '-') ? '-' : (string)($user['type'] ?? '-');
                        
                        $u_pay_rate = is_array($user['pay_rate_name'] ?? 'ไม่ได้ตั้งค่า') ? 'ไม่ได้ตั้งค่า' : (string)($user['pay_rate_name'] ?? 'ไม่ได้ตั้งค่า');
                        $u_pay_rate_id = is_array($user['pay_rate_id'] ?? '') ? '' : (string)($user['pay_rate_id'] ?? '');
                        
                        $u_color = is_array($user['color_theme'] ?? 'primary') ? 'primary' : (string)($user['color_theme'] ?? 'primary');
                        $u_start_date = is_array($user['start_date'] ?? '') ? '' : (string)($user['start_date'] ?? '');
                        $u_id_card = is_array($user['id_card'] ?? '') ? '' : (string)($user['id_card'] ?? '');
                        $u_posnum = is_array($user['position_number'] ?? '') ? '' : (string)($user['position_number'] ?? '');

                        $initial = mb_substr($u_name, 0, 1, 'UTF-8');
                        if(empty($initial)) $initial = '?';
                    ?>
                        <tr class="user-row" 
                            data-id="<?= htmlspecialchars($u_id) ?>" 
                            data-hospital="<?= htmlspecialchars($u_hospital_id) ?>" 
                            data-role="<?= htmlspecialchars($u_role) ?>" 
                            data-status="<?= htmlspecialchars($u_is_active) ?>">
                            
                            <td class="text-center">
                                <?php if($u_id != ($_SESSION['user']['id'] ?? '')): ?>
                                <input class="form-check-input user-checkbox" type="checkbox" value="<?= htmlspecialchars($u_id) ?>">
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><i class="bi bi-grip-vertical drag-handle"></i></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-<?= htmlspecialchars($u_color) ?> me-3"><?= htmlspecialchars($initial) ?></div>
                                    <div>
                                        <div class="fw-bold text-dark user-name"><?= htmlspecialchars($u_name) ?></div>
                                        <div class="small text-muted font-monospace user-username"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($u_login) ?></div>
                                        <span class="d-none user-phone"><?= htmlspecialchars($u_phone) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 180px;">
                                    <span class="badge bg-light text-dark border px-2 py-1"><i class="bi bi-hospital me-1"></i><?= htmlspecialchars($hosp_map[$u_hospital_id] ?? 'ส่วนกลาง (สสจ./รพ.)') ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark" style="font-size: 13px;"><?= htmlspecialchars($u_position) ?></div>
                                <span class="badge bg-info bg-opacity-10 text-dark border border-info border-opacity-25 px-2 py-1 rounded-pill mt-1" style="font-size: 11px;"><?= htmlspecialchars($u_emp_type) ?></span>
                                <div class="small text-muted d-none"><i class="bi bi-briefcase me-1"></i><?= htmlspecialchars($u_type) ?></div>
                            </td>
                            <td>
                                <div class="small text-secondary fw-medium"><?= htmlspecialchars($u_pay_rate) ?></div>
                            </td>
                            <td>
                                <?php 
                                    $role_color = 'bg-secondary';
                                    if($u_role === 'SUPERADMIN') $role_color = 'bg-danger';
                                    elseif($u_role === 'ADMIN' || $u_role === 'HR') $role_color = 'bg-primary';
                                    elseif($u_role === 'DIRECTOR') $role_color = 'bg-info';
                                    elseif($u_role === 'SCHEDULER') $role_color = 'bg-success';
                                ?>
                                <span class="badge <?= $role_color ?> bg-opacity-10 text-dark border px-2 py-1 mb-1" style="font-size: 11px;"><i class="bi bi-shield-lock me-1"></i><?= htmlspecialchars($u_role) ?></span>
                                <div>
                                    <?php if($u_is_active == 1): ?>
                                        <span class="badge bg-success rounded-pill" style="font-size: 10px;">เปิดใช้งาน</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill" style="font-size: 10px;">
                                            <?= htmlspecialchars($user['inactive_reason'] ?? 'ระงับบัญชี') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <!-- Status Toggle -->
                                <?php if($u_id != ($_SESSION['user']['id'] ?? '')): ?>
                                    <?php if($u_is_active == 1): ?>
                                        <!-- กรณีบัญชียังเปิดใช้งาน: ปุ่มระงับ (เรียก Modal) -->
                                        <button type="button" class="btn btn-sm btn-light border text-danger rounded-circle shadow-sm" 
                                                title="ระงับ/ยกเลิกการใช้งาน"
                                                data-bs-toggle="modal" data-bs-target="#deactivateUserModal"
                                                data-id="<?= htmlspecialchars($u_id) ?>"
                                                data-name="<?= htmlspecialchars($u_name) ?>">
                                            <i class="bi bi-power"></i>
                                        </button>
                                    <?php else: ?>
                                        <!-- กรณีบัญชีถูกระงับ: ปุ่มเปิดใช้งาน (Submit กลับเป็น 1 ตรงๆ) -->
                                        <form action="index.php?c=users&a=toggle" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการเปิดใช้งานบัญชีนี้อีกครั้ง?');">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($u_id) ?>">
                                            <input type="hidden" name="status" value="1">
                                            <button type="submit" class="btn btn-sm btn-light border text-success rounded-circle shadow-sm" title="เปิดใช้งาน">
                                                <i class="bi bi-power"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Edit Button -->
                                <button class="btn btn-sm btn-light border text-primary rounded-circle shadow-sm ms-1" 
                                        data-bs-toggle="modal" data-bs-target="#editUserModal"
                                        data-id="<?= htmlspecialchars($u_id) ?>"
                                        data-hospital="<?= htmlspecialchars($u_hospital_id) ?>"
                                        data-name="<?= htmlspecialchars($u_name) ?>"
                                        data-username="<?= htmlspecialchars($u_username) ?>"
                                        data-role="<?= htmlspecialchars($u_role) ?>"
                                        data-position="<?= htmlspecialchars($u_position) ?>"
                                        data-type="<?= htmlspecialchars($u_type) ?>"
                                        data-emptype="<?= htmlspecialchars($u_emp_type) ?>"
                                        data-payrate="<?= htmlspecialchars($u_pay_rate_id) ?>"
                                        data-color="<?= htmlspecialchars($u_color) ?>"
                                        data-phone="<?= htmlspecialchars($u_phone) ?>"
                                        data-startdate="<?= htmlspecialchars($u_start_date) ?>"
                                        data-idcard="<?= htmlspecialchars($u_id_card) ?>"
                                        data-posnum="<?= htmlspecialchars($u_posnum) ?>">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                
                                <!-- Delete Button -->
                                <?php if($u_id != ($_SESSION['user']['id'] ?? '')): ?>
                                    <form action="index.php?c=users&a=delete" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบผู้ใช้งานท่านนี้ออกจากระบบ? ข้อมูลเวรจะถูกลบไปด้วย');">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($u_id) ?>">
                                        <button type="submit" class="btn btn-sm btn-light border text-danger rounded-circle ms-1 shadow-sm" title="ลบผู้ใช้งาน">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
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
            <form action="index.php?c=users&a=add" method="POST" id="addForm" autocomplete="off">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-plus-fill text-primary me-2"></i>เพิ่มผู้ใช้งานระบบ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">สังกัดหน่วยบริการ (Hospital) *</label>
                            <select name="hospital_id" id="add_hospital_id" class="form-select select2-modal rounded-3 border-primary" style="width: 100%;" required>
                                <option value="0">🏢 ส่วนกลาง (สสจ. / โรงพยาบาลเครือข่าย)</option>
                                <?php foreach($hospitals_list as $h): ?>
                                    <option value="<?= $h['id'] ?>">🏥 <?= htmlspecialchars($h['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- ฟอร์มเสริมกรณีเลือก "ส่วนกลาง" -->
                        <div class="col-12" id="central_fields_add" style="display:none;">
                            <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-50 rounded-3">
                                <h6 class="text-warning fw-bold mb-2"><i class="bi bi-star-fill me-1"></i> ตั้งค่าเพิ่มเติม (เฉพาะผู้ใช้ส่วนกลาง)</h6>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mt-2">
                                            <input class="form-check-input" type="checkbox" id="add_view_all" name="central_view_all" value="1">
                                            <label class="form-check-label fw-medium" for="add_view_all">อนุญาตให้เข้าถึงข้อมูลข้ามหน่วยงาน</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control form-control-sm" name="central_department" placeholder="ระบุแผนก/ฝ่ายย่อยในส่วนกลาง (ถ้ามี)">
                                    </div>
                                </div>
                            </div>
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

                        <!-- ช่องกรอกตำแหน่ง -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ตำแหน่ง (Position) <span class="text-danger">*</span></label>
                            <input type="text" name="position" class="form-control rounded-3 border-primary" placeholder="เช่น พยาบาลวิชาชีพชำนาญการ, นักจัดการงานทั่วไป" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">ประเภทพนักงาน</label>
                            <select name="employee_type" class="form-select rounded-3">
                                <option value="ข้าราชการ">ข้าราชการ</option>
                                <option value="พนักงานราชการ">พนักงานราชการ</option>
                                <option value="พนักงานกระทรวงสาธารณสุข (ทั่วไป)">พนักงานกระทรวงสาธารณสุข (ทั่วไป)</option>
                                <option value="พนักงานกระทรวงสาธารณสุข (พิเศษ)">พนักงานกระทรวงสาธารณสุข (พิเศษ)</option>
                                <option value="ลูกจ้างประจำ">ลูกจ้างประจำ</option>
                                <option value="ลูกจ้างชั่วคราว/รายคาบ/เหมาบริการ">ลูกจ้างชั่วคราว/รายคาบ/เหมาบริการ</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">สายวิชาชีพ (ระดับตำแหน่ง)</label>
                            <input type="text" name="type" class="form-control rounded-3" placeholder="เช่น พยาบาลวิชาชีพ, แพทย์">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-control rounded-3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">วันที่เริ่มงาน</label>
                            <input type="text" name="start_date" class="form-control rounded-3 thai-datepicker bg-white" placeholder="เลือกวันที่">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ธีมสีตัวแทน</label>
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
                            <input type="text" name="username" class="form-control rounded-3 border-primary" autocomplete="username" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-primary">Password *</label>
                            <!-- ป้องกัน Auto-fill รหัสผ่าน -->
                            <input type="password" name="password" class="form-control rounded-3 border-primary" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');" placeholder="ตั้งรหัสผ่าน" required minlength="4">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-primary">สิทธิ์เข้าใช้งานระบบ *</label>
                            <select name="role" class="form-select rounded-3 border-primary" required>
                                <option value="STAFF">พนักงานทั่วไป (STAFF)</option>
                                <option value="SCHEDULER">ผู้จัดเวร (SCHEDULER)</option>
                                <option value="DIRECTOR">ผู้อำนวยการ (DIRECTOR)</option>
                                <option value="HR">ฝ่ายบุคคล (HR)</option>
                                <?php if($is_admin || $is_superadmin): ?>
                                    <option value="ADMIN">ผู้ดูแลระบบ (ADMIN)</option>
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
            <form action="index.php?c=users&a=edit" method="POST" id="editForm" autocomplete="off">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>แก้ไขข้อมูลผู้ใช้งาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">สังกัดหน่วยบริการ *</label>
                            <select name="hospital_id" id="edit_hospital" class="form-select select2-modal rounded-3 bg-light border-warning" style="width: 100%;" required>
                                <option value="0">🏢 ส่วนกลาง (สสจ. / โรงพยาบาลเครือข่าย)</option>
                                <?php foreach($hospitals_list as $h): ?>
                                    <option value="<?= $h['id'] ?>">🏥 <?= htmlspecialchars($h['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- ฟอร์มส่วนกลาง Edit -->
                        <div class="col-12" id="central_fields_edit" style="display:none;">
                            <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-50 rounded-3">
                                <h6 class="text-warning fw-bold mb-2"><i class="bi bi-star-fill me-1"></i> ตั้งค่าเพิ่มเติม (เฉพาะผู้ใช้ส่วนกลาง)</h6>
                                <p class="text-muted small mb-0">ระบบเปิดใช้ฟอร์มนี้เพราะบุคลากรอยู่สังกัดส่วนกลาง</p>
                            </div>
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

                        <!-- เพิ่มช่องตำแหน่งในการแก้ไข -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold">ตำแหน่ง (Position) <span class="text-danger">*</span></label>
                            <input type="text" name="position" id="edit_position" class="form-control rounded-3 border-warning" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">ประเภทพนักงาน</label>
                            <select name="employee_type" id="edit_employee_type" class="form-select rounded-3">
                                <option value="ข้าราชการ">ข้าราชการ</option>
                                <option value="พนักงานราชการ">พนักงานราชการ</option>
                                <option value="พนักงานกระทรวงสาธารณสุข (ทั่วไป)">พนักงานกระทรวงสาธารณสุข (ทั่วไป)</option>
                                <option value="พนักงานกระทรวงสาธารณสุข (พิเศษ)">พนักงานกระทรวงสาธารณสุข (พิเศษ)</option>
                                <option value="ลูกจ้างประจำ">ลูกจ้างประจำ</option>
                                <option value="ลูกจ้างชั่วคราว/รายคาบ/เหมาบริการ">ลูกจ้างชั่วคราว/รายคาบ/เหมาบริการ</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">สายวิชาชีพ </label>
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
                        
                        <div class="col-12"><hr class="my-2"></div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-warning">Username (ล็อกอิน)</label>
                            <input type="text" name="username" id="edit_username" class="form-control rounded-3 border-warning" autocomplete="username">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-warning">รหัสผ่านใหม่ <small>(เว้นว่างถ้าไม่เปลี่ยน)</small></label>
                            <!-- ป้องกัน Auto-fill รหัสผ่าน -->
                            <input type="password" name="password" id="edit_password" class="form-control rounded-3" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');" placeholder="เว้นว่างถ้าไม่เปลี่ยนรหัส">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">สิทธิ์การใช้งาน</label>
                            <select name="role" id="edit_role" class="form-select rounded-3 border-warning" required>
                                <option value="STAFF">พนักงานทั่วไป (STAFF)</option>
                                <option value="SCHEDULER">ผู้จัดเวร (SCHEDULER)</option>
                                <option value="DIRECTOR">ผู้อำนวยการ (DIRECTOR)</option>
                                <option value="HR">ฝ่ายบุคคล (HR)</option>
                                <?php if($is_admin || $is_superadmin): ?>
                                    <option value="ADMIN">ผู้ดูแลระบบ (ADMIN)</option>
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

<!-- 🌟 Modal: ระงับ/ยกเลิกการใช้งาน (Deactivate User) -->
<div class="modal fade" id="deactivateUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="index.php?c=users&a=toggle" method="POST">
                <input type="hidden" name="id" id="deactivate_user_id">
                <input type="hidden" name="status" value="0"> <!-- 0 = ระงับการใช้งาน -->
                
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-person-dash-fill me-2"></i>ระงับ/ยกเลิกการใช้งาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert bg-danger bg-opacity-10 text-danger border-0 border-start border-danger border-4 rounded-3 p-3 mb-4">
                        ผู้ใช้งาน: <strong id="deactivate_user_name" class="fs-6"></strong><br>
                        <small>เมื่อระงับการใช้งาน ผู้ใช้รายนี้จะไม่สามารถเข้าสู่ระบบและไม่มีชื่อในกระดานจัดเวรได้อีก</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ระบุสาเหตุการระงับ *</label>
                        <select name="inactive_reason" class="form-select rounded-3 border-danger" required>
                            <option value="">-- เลือกสาเหตุ --</option>
                            <option value="เกษียณอายุ">เกษียณอายุ</option>
                            <option value="ลาออก">ลาออก</option>
                            <option value="ระงับการใช้งานชั่วคราว">ระงับการใช้งานชั่วคราว</option>
                            <option value="เสียชีวิต">เสียชีวิต</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">วันที่มีผล</label>
                        <input type="text" name="inactive_date" class="form-control rounded-3 thai-datepicker bg-white" placeholder="เลือกวันที่">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">หมายเหตุเพิ่มเติม</label>
                        <textarea name="inactive_note" class="form-control rounded-3" rows="2" placeholder="ระบุข้อมูลเพิ่มเติม (ถ้ามี)"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4 px-4 py-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold"><i class="bi bi-power me-1"></i> ยืนยันการระงับ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ฟังก์ชันลบหลายรายการ (Bulk Delete)
function bulkDelete() {
    let selectedIds = [];
    $('.user-checkbox:checked').each(function() {
        selectedIds.push($(this).val());
    });

    if(selectedIds.length === 0) return;

    if(confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้งานที่เลือกจำนวน ' + selectedIds.length + ' รายการ? ข้อมูลจะถูกลบถาวร')) {
        let form = $('<form>', {
            'action': 'index.php?c=users&a=bulk_delete',
            'method': 'POST'
        }).append($('<input>', {
            'name': 'ids',
            'value': JSON.stringify(selectedIds),
            'type': 'hidden'
        }));
        $(document.body).append(form);
        form.submit();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    
    // --- เริ่ม: ตั้งค่า DataTables สำหรับแบ่งหน้าตาราง ---
    var table = $('#usersTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" },
        "pageLength": 15,
        "dom": '<"top">rt<"bottom"lip><"clear">', // ซ่อนช่อง Search ของ DataTables
        "columnDefs": [ { "orderable": false, "targets": [0, 1, 7] } ], // ปิดการเรียงลำดับคอลัมน์ Checkbox, ลากตำแหน่ง, และ จัดการ
        "order": [[2, 'asc']] // ค่าเริ่มต้นเรียงตามชื่อ
    });

    // Custom Filtering ของ DataTables ให้เชื่อมกับกล่องค้นหาของคุณ
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex, rowData, counter) {
        var filterHosp = $('#filterHospital').val();
        var filterRole = $('#filterRole').val();
        var filterStatus = $('#filterStatus').val();
        var rowNode = table.row(dataIndex).node();
        
        var rowHosp = $(rowNode).data('hospital').toString();
        var rowRole = $(rowNode).data('role');
        var rowStatus = $(rowNode).data('status').toString();

        if (filterHosp && filterHosp !== rowHosp) return false;
        if (filterRole && filterRole !== rowRole) return false;
        if (filterStatus && filterStatus !== rowStatus) return false;
        return true;
    });

    // จับ Event เมื่อพิมพ์ค้นหา
    $('#searchInput').on('keyup', function() { table.search(this.value).draw(); });
    // จับ Event เมื่อเปลี่ยน Dropdown
    $('#filterHospital, #filterRole, #filterStatus').on('change', function() { table.draw(); });
    // --- จบ: DataTables ---

    // --- เริ่ม: Select2 สำหรับค้นหาหน่วยบริการ ---
    // ตัวกรองค้นหาด้านบน
    $('.select2-filter').select2({ width: '100%' });
    
    // Dropdown ใน Modal
    $('#add_hospital_id').select2({
        dropdownParent: $('#addUserModal'),
        width: '100%'
    });
    $('#edit_hospital').select2({
        dropdownParent: $('#editUserModal'),
        width: '100%'
    });
    // --- จบ: Select2 ---

    // --- เริ่ม: ตรวจจับ "ส่วนกลาง" (value = 0) ---
    $('#add_hospital_id').on('change', function() {
        if($(this).val() == '0') $('#central_fields_add').slideDown();
        else $('#central_fields_add').slideUp();
    });
    $('#edit_hospital').on('change', function() {
        if($(this).val() == '0') $('#central_fields_edit').slideDown();
        else $('#central_fields_edit').slideUp();
    });
    // --- จบ: ตรวจจับส่วนกลาง ---

    // --- เริ่ม: ระบบ Checkbox สำหรับลบหลายรายการ ---
    $('#selectAll').change(function() {
        $('.user-checkbox').prop('checked', $(this).prop('checked'));
        toggleBulkBtn();
    });
    $(document).on('change', '.user-checkbox', function() { toggleBulkBtn(); });

    function toggleBulkBtn() {
        let count = $('.user-checkbox:checked').length;
        if(count > 0) {
            $('#btn-bulk-delete').removeClass('d-none');
            $('#selected-count').text(count);
        } else {
            $('#btn-bulk-delete').addClass('d-none');
        }
    }
    // --- จบ: Checkbox ---

    // --- 🌟 เริ่ม: Flatpickr (ตั้งค่าแสดง พ.ศ. 2569 สมบูรณ์) ---
    flatpickr(".thai-datepicker", { 
        locale: "th", 
        altInput: true, 
        altFormat: "j F Y", 
        dateFormat: "Y-m-d",
        formatDate: function(date, format, locale) {
            const thaiMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            // เมื่อรูปแบบเป็น altFormat ให้คืนค่า ปี พ.ศ. (+543) ทันที
            if (format === "j F Y") { 
                return date.getDate() + ' ' + thaiMonths[date.getMonth()] + ' ' + (date.getFullYear() + 543);
            }
            // สำหรับบันทึกฟอร์มลงฐานข้อมูล คืนค่า ปี ค.ศ. ปกติ
            const d = String(date.getDate()).padStart(2, '0');
            const m = String(date.getMonth() + 1).padStart(2, '0');
            return date.getFullYear() + '-' + m + '-' + d;
        },
        onReady: function(selectedDates, dateStr, instance) {
            // 1. ซ่อนช่องปี ค.ศ. เดิมของไลบรารี
            const yearInput = instance.currentYearElement;
            yearInput.style.display = 'none';
            
            // 2. สร้างช่องปี พ.ศ. ขึ้นมาใหม่
            const beYearInput = document.createElement('input');
            beYearInput.className = 'numInput cur-year';
            beYearInput.type = 'number';
            beYearInput.min = 2400;
            beYearInput.max = 2999;
            beYearInput.step = 1;
            beYearInput.value = instance.currentYear + 543;
            
            // 3. ใส่ช่อง พ.ศ. ลงไปแทนที่ในปฏิทิน
            yearInput.parentNode.insertBefore(beYearInput, yearInput.nextSibling);
            
            // 4. ตรวจจับเมื่อผู้ใช้พิมพ์หรือกดลูกศรเปลี่ยนปี พ.ศ.
            beYearInput.addEventListener('input', function(e) {
                let beYear = parseInt(this.value);
                if (beYear >= 2400) {
                    // สั่งให้ระบบหลักของ flatpickr เปลี่ยนเป็น ค.ศ. ที่ถูกต้อง
                    instance.changeYear(beYear - 543);
                }
            });
            
            // เก็บตัวแปรอ้างอิงไว้ใช้
            instance.beYearInput = beYearInput;
        },
        onYearChange: function(selectedDates, dateStr, instance) {
            if (instance.beYearInput) instance.beYearInput.value = instance.currentYear + 543;
        }
    });
    // --- จบ: Flatpickr ---

    const tbody = document.getElementById('users-table-body');
    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            handle: '.drag-handle', animation: 150, ghostClass: 'sortable-ghost',
            onEnd: function () {
                const orderData = Array.from(tbody.querySelectorAll('tr.user-row')).map((row, idx) => ({
                    id: row.dataset.id, order: idx + 1
                }));
                fetch('index.php?c=users&a=update_order', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: orderData })
                }).then(response => response.json()).then(data => {
                    if (data.success) showToastAlert('สลับตำแหน่งเรียบร้อย', 'success');
                });
            }
        });
    }

    // Modal Edit Popup Event
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('edit_id').value = btn.dataset.id;
            
            // อัปเดต Select2
            $('#edit_hospital').val(btn.dataset.hospital || '0').trigger('change');
            
            document.getElementById('edit_name').value = btn.dataset.name;
            document.getElementById('edit_username').value = btn.dataset.username;
            document.getElementById('edit_position').value = btn.dataset.position; 
            
            // 🌟 แก้ไข: ประเภทพนักงาน (ตัดช่องว่างเพื่อป้องกันเลือกไม่ติด)
            let empTypeSel = document.getElementById('edit_employee_type');
            let empVal = btn.dataset.emptype ? btn.dataset.emptype.trim() : '';
            if(empVal !== '' && !Array.from(empTypeSel.options).some(opt => opt.value === empVal)) {
                empTypeSel.add(new Option(empVal, empVal));
            }
            empTypeSel.value = empVal !== '' ? empVal : 'ข้าราชการ';
            
            document.getElementById('edit_type').value = btn.dataset.type;
            document.getElementById('edit_pay_rate_id').value = btn.dataset.payrate;
            document.getElementById('edit_color').value = btn.dataset.color;
            document.getElementById('edit_phone').value = btn.dataset.phone;
            document.getElementById('edit_id_card').value = btn.dataset.idcard;
            
            let roleSel = document.getElementById('edit_role');
            if(!Array.from(roleSel.options).some(opt => opt.value === btn.dataset.role)) {
                roleSel.add(new Option(btn.dataset.role, btn.dataset.role));
            }
            roleSel.value = btn.dataset.role;

            // 🌟 แก้ไข: โหลดวันที่ลงใน Flatpickr ได้ถูกต้อง
            const fpInput = document.querySelector('#edit_start_date');
            if (fpInput && fpInput._flatpickr) {
                if (btn.dataset.startdate) fpInput._flatpickr.setDate(btn.dataset.startdate);
                else fpInput._flatpickr.clear();
            }

            // 🌟 ป้องกันรหัสผ่าน Auto-fill ซ้ำสองตอนกดแก้ไข
            const passInput = document.getElementById('edit_password');
            if (passInput) {
                passInput.value = '';
                passInput.setAttribute('readonly', true);
            }
        });
    }

    // 🌟 ดึงข้อมูลไปแสดงใน Modal ระงับบัญชี (Deactivate User Modal)
    const deactivateModal = document.getElementById('deactivateUserModal');
    if (deactivateModal) {
        deactivateModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('deactivate_user_id').value = btn.dataset.id;
            document.getElementById('deactivate_user_name').innerText = btn.dataset.name;
        });
    }
});

function resetForm() {
    document.getElementById('addForm').reset();
    $('#add_hospital_id').val('0').trigger('change'); // คืนค่า Select2 
    const fpInputs = document.querySelectorAll('#addForm .thai-datepicker');
    fpInputs.forEach(input => { if (input._flatpickr) input._flatpickr.clear(); });
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    $('#filterHospital').val('').trigger('change');
    document.getElementById('filterRole').value = '';
    document.getElementById('filterStatus').value = '';
    
    // อัปเดตตาราง DataTables
    var table = $('#usersTable').DataTable();
    table.search('').draw();
}

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
    toast.innerHTML = `<div class="d-flex"><div class="toast-body fw-bold" style="font-size: 14px;"><i class="bi ${icon} me-2"></i> ${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    toastContainer.appendChild(toast);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 3000);
}
</script>