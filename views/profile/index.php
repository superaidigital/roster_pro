<?php
// ที่อยู่ไฟล์: views/profile/index.php

// ฟังก์ชันแปลงวันที่ ค.ศ. เป็น พ.ศ. แบบย่อ
function formatDateThai($date) {
    if (empty($date) || $date == '0000-00-00') return '-';
    $thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $timestamp = strtotime($date);
    $d = date('j', $timestamp);
    $m = $thai_months[(int)date('m', $timestamp)];
    $y = date('Y', $timestamp) + 543;
    return "$d $m $y";
}

$u_color = $target_user['color_theme'] ?? 'primary';
$u_name = $target_user['name'] ?? 'ไม่มีชื่อ';
$initial = mb_substr($u_name, 0, 1, 'UTF-8');

// ==========================================
// 🌟 ระบบ Smart Auto-fill (ปรับปรุงให้ดึงชื่อหลักมาใช้เสมอ)
// ==========================================
$title_name = $profile['title_name'] ?? '';
$first_name_th = $profile['first_name_th'] ?? '';
$last_name_th = $profile['last_name_th'] ?? '';

if (empty($first_name_th) && empty($last_name_th) && !empty($target_user['name'])) {
    $fullName = trim($target_user['name']);
    $parts = explode(' ', $fullName);
    
    // หาคำนำหน้า
    $titles = ['นางสาว', 'น.ส.', 'นาง', 'นาย', 'ว่าที่ร้อยตรี', 'ว่าที่ร.ต.', 'นพ.', 'พญ.', 'ทพ.', 'ทพญ.', 'ดร.'];
    foreach ($titles as $t) {
        if (mb_strpos($fullName, $t) === 0) {
            $title_name = $t;
            $fullName = trim(mb_substr($fullName, mb_strlen($t)));
            $parts = explode(' ', $fullName);
            break;
        }
    }
    // แยกชื่อ-สกุล
    if (count($parts) >= 2) {
        $last_name_th = array_pop($parts);
        $first_name_th = implode(' ', $parts);
    } else {
        $first_name_th = $fullName;
    }
}
?>

<!-- Include Required Plugins -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .card-modern { border: none; border-radius: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; margin-bottom: 1.5rem; }
    .avatar-profile { width: 100px; height: 100px; border-radius: 50%; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; color: white; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    
    /* Custom Tabs */
    .nav-tabs-custom { border-bottom: 2px solid #e2e8f0; }
    .nav-tabs-custom .nav-item { margin-bottom: -2px; }
    .nav-tabs-custom .nav-link { border: none; border-bottom: 3px solid transparent; color: #64748b; font-weight: 600; padding: 1rem 1.5rem; transition: all 0.3s; }
    .nav-tabs-custom .nav-link:hover { color: #3b82f6; }
    .nav-tabs-custom .nav-link.active { color: #0d6efd; border-bottom-color: #0d6efd; background: transparent; }
    
    .section-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; border-left: 4px solid #0d6efd; padding-left: 10px; }
    .table-modern th { background-color: #f8fafc; font-weight: 600; color: #475569; font-size: 13px; }
    .table-modern td { vertical-align: middle; font-size: 14px; }
    .empty-state { padding: 3rem 1rem; text-align: center; color: #94a3b8; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <!-- Back Button & Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="javascript:history.back()" class="btn btn-light border shadow-sm rounded-pill px-4 fw-bold text-secondary hover-primary">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success py-2 px-4 rounded-pill mb-0 shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i><?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger py-2 px-4 rounded-pill mb-0 shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
        <?php endif; ?>
    </div>

    <!-- Top Profile Card -->
    <div class="card card-modern overflow-hidden">
        <div class="card-body p-0">
            <div class="bg-<?= $u_color ?> bg-opacity-10" style="height: 100px;"></div>
            <div class="px-4 pb-4 d-flex flex-column flex-md-row gap-4" style="margin-top: -50px;">
                <div class="avatar-profile bg-<?= $u_color ?> flex-shrink-0"><?= $initial ?></div>
                <div class="flex-grow-1 mt-md-4 pt-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3 class="fw-bold text-dark mb-1"><?= htmlspecialchars($target_user['name']) ?></h3>
                            <p class="text-muted fw-medium mb-2" style="font-size: 15px;">
                                <i class="bi bi-briefcase-fill me-1"></i> <?= htmlspecialchars($target_user['position']) ?>
                                <span class="mx-2 text-secondary">|</span>
                                <i class="bi bi-diagram-3-fill me-1"></i> <?= htmlspecialchars($target_user['employee_type']) ?>
                            </p>
                        </div>
                        <div>
                            <?php if($target_user['is_active'] == 1): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> ปฏิบัติงานปกติ</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-x-circle-fill me-1"></i> ระงับการใช้งาน/พ้นสภาพ</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mt-3 g-3">
                        <div class="col-md-3 col-sm-6">
                            <div class="small text-muted mb-1">รหัสพนักงาน/Login</div>
                            <div class="fw-bold text-dark"><i class="bi bi-person-badge me-1 text-primary"></i> <?= htmlspecialchars($target_user['username']) ?: '-' ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="small text-muted mb-1">เบอร์โทรศัพท์</div>
                            <div class="fw-bold text-dark"><i class="bi bi-telephone-fill me-1 text-success"></i> <?= htmlspecialchars($target_user['phone']) ?: '-' ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="small text-muted mb-1">เลขบัตรประชาชน</div>
                            <div class="fw-bold text-dark font-monospace"><i class="bi bi-credit-card-2-front-fill me-1 text-warning"></i> <?= htmlspecialchars($target_user['id_card']) ?: '-' ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="small text-muted mb-1">วันที่เริ่มงาน</div>
                            <div class="fw-bold text-dark"><i class="bi bi-calendar-event-fill me-1 text-info"></i> <?= formatDateThai($target_user['start_date']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-tabs-custom mb-4" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#nav-personal" type="button"><i class="bi bi-person-vcard me-2"></i>ข้อมูลส่วนตัว</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#nav-education" type="button"><i class="bi bi-mortarboard me-2"></i>การศึกษาและใบประกอบวิชาชีพ</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#nav-work" type="button"><i class="bi bi-building me-2"></i>ประวัติการทำงาน</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#nav-training" type="button"><i class="bi bi-award me-2"></i>ประวัติการฝึกอบรม (CPE)</button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="profileTabsContent">

        <!-- ============================================== -->
        <!-- TAB 1: ข้อมูลส่วนตัว (Personal Info) -->
        <!-- ============================================== -->
        <div class="tab-pane fade show active" id="nav-personal" role="tabpanel">
            <div class="card card-modern">
                <div class="card-body p-4">
                    <form action="index.php?c=profile&a=save_profile" method="POST">
                            <?= security_csrf_input() ?>
                        <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                        
                        <h5 class="section-title">ข้อมูลพื้นฐาน (Basic Information)</h5>
                        <div class="row g-3 mb-4">
                            <!-- ใช้ข้อมูลจากระบบ Smart Auto-fill -->
                            <div class="col-md-2">
                                <label class="form-label text-muted small">คำนำหน้า</label>
                                <input type="text" name="title_name" class="form-control rounded-3" value="<?= htmlspecialchars($title_name) ?>" placeholder="นาย/นาง/น.ส.">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label text-muted small">ชื่อ (ภาษาไทย)</label>
                                <input type="text" name="first_name_th" class="form-control rounded-3" value="<?= htmlspecialchars($first_name_th) ?>">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label text-muted small">นามสกุล (ภาษาไทย)</label>
                                <input type="text" name="last_name_th" class="form-control rounded-3" value="<?= htmlspecialchars($last_name_th) ?>">
                            </div>
                            
                            <div class="col-md-2 d-none d-md-block"></div> <!-- Spacer -->
                            <div class="col-md-5">
                                <label class="form-label text-muted small">First Name (English)</label>
                                <input type="text" name="first_name_en" class="form-control rounded-3" value="<?= htmlspecialchars($profile['first_name_en'] ?? '') ?>">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label text-muted small">Last Name (English)</label>
                                <input type="text" name="last_name_en" class="form-control rounded-3" value="<?= htmlspecialchars($profile['last_name_en'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label text-muted small">วัน/เดือน/ปีเกิด <span class="badge bg-info text-dark ms-1">อายุ: <?= $age ?> ปี</span></label>
                                <input type="text" name="birth_date" class="form-control rounded-3 thai-datepicker" value="<?= htmlspecialchars($profile['birth_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small">เพศ</label>
                                <select name="gender" class="form-select rounded-3">
                                    <option value="">-ไม่ระบุ-</option>
                                    <option value="M" <?= ($profile['gender']??'') == 'M' ? 'selected' : '' ?>>ชาย (Male)</option>
                                    <option value="F" <?= ($profile['gender']??'') == 'F' ? 'selected' : '' ?>>หญิง (Female)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-muted small">กรุ๊ปเลือด</label>
                                <input type="text" name="blood_group" class="form-control rounded-3" value="<?= htmlspecialchars($profile['blood_group'] ?? '') ?>" placeholder="A, B, AB, O">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-muted small">ศาสนา</label>
                                <input type="text" name="religion" class="form-control rounded-3" value="<?= htmlspecialchars($profile['religion'] ?? 'พุทธ') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-muted small">สถานภาพ</label>
                                <select name="marital_status" class="form-select rounded-3">
                                    <option value="">-ไม่ระบุ-</option>
                                    <option value="โสด" <?= ($profile['marital_status']??'') == 'โสด' ? 'selected' : '' ?>>โสด</option>
                                    <option value="สมรส" <?= ($profile['marital_status']??'') == 'สมรส' ? 'selected' : '' ?>>สมรส</option>
                                    <option value="หย่าร้าง" <?= ($profile['marital_status']??'') == 'หย่าร้าง' ? 'selected' : '' ?>>หย่าร้าง</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4 border-light">
                        <h5 class="section-title">ข้อมูลที่อยู่และการติดต่อฉุกเฉิน (Contact Info)</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted small">ที่อยู่ตามทะเบียนบ้าน</label>
                                <textarea name="address_permanent" class="form-control rounded-3" rows="3"><?= htmlspecialchars($profile['address_permanent'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small">ที่อยู่ปัจจุบัน (สำหรับติดต่อ)</label>
                                <textarea name="address_current" class="form-control rounded-3" rows="3"><?= htmlspecialchars($profile['address_current'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small text-danger"><i class="bi bi-heart-pulse-fill me-1"></i> บุคคลติดต่อฉุกเฉิน</label>
                                <input type="text" name="emergency_contact_name" class="form-control rounded-3 border-danger border-opacity-25" value="<?= htmlspecialchars($profile['emergency_contact_name'] ?? '') ?>" placeholder="ชื่อ-สกุล">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small text-danger">ความเกี่ยวข้อง</label>
                                <input type="text" name="emergency_contact_relation" class="form-control rounded-3 border-danger border-opacity-25" value="<?= htmlspecialchars($profile['emergency_contact_relation'] ?? '') ?>" placeholder="เช่น บิดา, มารดา, สามี">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small text-danger">เบอร์โทรศัพท์ฉุกเฉิน</label>
                                <input type="text" name="emergency_contact_phone" class="form-control rounded-3 border-danger border-opacity-25" value="<?= htmlspecialchars($profile['emergency_contact_phone'] ?? '') ?>">
                            </div>
                        </div>

                        <hr class="my-4 border-light">
                        <h5 class="section-title">ข้อมูลบัญชีเงินเดือน (Banking)</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label text-muted small">ชื่อธนาคาร</label>
                                <input type="text" name="bank_name" class="form-control rounded-3" value="<?= htmlspecialchars($profile['bank_name'] ?? '') ?>" placeholder="เช่น กรุงไทย, ออมสิน">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">สาขา</label>
                                <input type="text" name="bank_branch" class="form-control rounded-3" value="<?= htmlspecialchars($profile['bank_branch'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">เลขที่บัญชี</label>
                                <input type="text" name="bank_account_no" class="form-control rounded-3 font-monospace" value="<?= htmlspecialchars($profile['bank_account_no'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="bi bi-save2 me-2"></i>บันทึกข้อมูลส่วนตัว</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- TAB 2: การศึกษา และ ใบประกอบวิชาชีพ -->
        <!-- ============================================== -->
        <div class="tab-pane fade" id="nav-education" role="tabpanel">
            
            <!-- ตารางใบประกอบวิชาชีพ -->
            <div class="card card-modern mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="section-title mb-0 border-0 text-success"><i class="bi bi-card-heading me-2"></i>ใบประกอบวิชาชีพ (Licenses)</h5>
                    <button class="btn btn-sm btn-outline-success rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddLicense">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มใบประกอบวิชาชีพ
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>ชื่อใบประกอบวิชาชีพ</th>
                                    <th>สภาวิชาชีพ</th>
                                    <th>เลขที่อนุญาต</th>
                                    <th>วันหมดอายุ</th>
                                    <th>สถานะ</th>
                                    <th width="10%">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($licenses)): ?>
                                    <tr><td colspan="6" class="empty-state"><i class="bi bi-inbox fs-1 d-block mb-2"></i>ยังไม่มีประวัติใบประกอบวิชาชีพ</td></tr>
                                <?php else: foreach($licenses as $lic): 
                                    $status_badge = 'bg-success';
                                    if($lic['status'] == 'EXPIRED') $status_badge = 'bg-danger';
                                    if($lic['status'] == 'REVOKED') $status_badge = 'bg-dark';
                                    
                                    // แจ้งเตือนหมดอายุ
                                    $exp_txt = formatDateThai($lic['expire_date']);
                                    if ($lic['expire_date'] && strtotime($lic['expire_date']) < time()) {
                                        $exp_txt = "<span class='text-danger fw-bold'>$exp_txt (หมดอายุ)</span>";
                                    } elseif ($lic['expire_date'] && strtotime($lic['expire_date']) < strtotime('+60 days')) {
                                        $exp_txt = "<span class='text-warning fw-bold text-dark'>$exp_txt (ใกล้หมดอายุ)</span>";
                                    }
                                ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($lic['license_name']) ?></td>
                                        <td><?= htmlspecialchars($lic['council_name']) ?></td>
                                        <td class="font-monospace text-primary"><?= htmlspecialchars($lic['license_no']) ?></td>
                                        <td><?= $exp_txt ?></td>
                                        <td><span class="badge <?= $status_badge ?>"><?= htmlspecialchars($lic['status']) ?></span></td>
                                        <td>
                                            <form action="index.php?c=profile&a=delete_license" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ?');">
                                                <?= security_csrf_input() ?>
                                                <input type="hidden" name="id" value="<?= $lic['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                                                <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle" aria-label="ลบ"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ตารางประวัติการศึกษา -->
            <div class="card card-modern">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="section-title mb-0 border-0"><i class="bi bi-mortarboard-fill me-2"></i>ประวัติการศึกษา (Education)</h5>
                    <button class="btn btn-sm btn-outline-primary rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddEducation">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มประวัติการศึกษา
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>ระดับการศึกษา</th>
                                    <th>วุฒิการศึกษา</th>
                                    <th>สาขาวิชา/วิชาเอก</th>
                                    <th>สถาบันการศึกษา</th>
                                    <th>ปีที่จบ (พ.ศ.)</th>
                                    <th width="10%">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($educations)): ?>
                                    <tr><td colspan="6" class="empty-state"><i class="bi bi-inbox fs-1 d-block mb-2"></i>ยังไม่มีประวัติการศึกษา</td></tr>
                                <?php else: foreach($educations as $edu): ?>
                                    <tr>
                                        <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?= htmlspecialchars($edu['degree_level']) ?></span></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($edu['degree_name']) ?></td>
                                        <td><?= htmlspecialchars($edu['major']) ?></td>
                                        <td><?= htmlspecialchars($edu['institution']) ?></td>
                                        <td><?= htmlspecialchars($edu['graduation_year']) ?></td>
                                        <td>
                                            <form action="index.php?c=profile&a=delete_education" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ?');">
                                                <?= security_csrf_input() ?>
                                                <input type="hidden" name="id" value="<?= $edu['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                                                <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle" aria-label="ลบ"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- ============================================== -->
        <!-- TAB 3: ประวัติการทำงาน (Work History) -->
        <!-- ============================================== -->
        <div class="tab-pane fade" id="nav-work" role="tabpanel">
            <div class="card card-modern">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="section-title mb-0 border-0"><i class="bi bi-building-fill me-2"></i>ประสบการณ์ทำงาน (Work Experience)</h5>
                    <button class="btn btn-sm btn-outline-primary rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddWork">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มประวัติการทำงาน
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>สถานที่ทำงาน / องค์กร</th>
                                    <th>ตำแหน่ง</th>
                                    <th>ระยะเวลา</th>
                                    <th>สาเหตุที่ออก</th>
                                    <th width="10%">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($work_histories)): ?>
                                    <tr><td colspan="5" class="empty-state"><i class="bi bi-inbox fs-1 d-block mb-2"></i>ยังไม่มีประวัติการทำงานภายนอก</td></tr>
                                <?php else: foreach($work_histories as $work): 
                                    $end_txt = empty($work['end_date']) || $work['end_date'] == '0000-00-00' ? 'ปัจจุบัน' : formatDateThai($work['end_date']);
                                ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($work['company_name']) ?></td>
                                        <td><?= htmlspecialchars($work['position']) ?></td>
                                        <td class="small text-muted"><i class="bi bi-calendar me-1"></i> <?= formatDateThai($work['start_date']) ?> - <?= $end_txt ?></td>
                                        <td class="small"><?= htmlspecialchars($work['reason_for_leave']) ?: '-' ?></td>
                                        <td>
                                            <form action="index.php?c=profile&a=delete_work" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ?');">
                                                <?= security_csrf_input() ?>
                                                <input type="hidden" name="id" value="<?= $work['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                                                <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle" aria-label="ลบ"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- TAB 4: ประวัติการฝึกอบรม (Training & CPE) -->
        <!-- ============================================== -->
        <div class="tab-pane fade" id="nav-training" role="tabpanel">
            <div class="card card-modern">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="section-title mb-0 border-0 text-warning text-dark"><i class="bi bi-award-fill me-2"></i>ประวัติการฝึกอบรม / ดูงาน (Trainings)</h5>
                    <button class="btn btn-sm btn-outline-warning text-dark rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddTraining">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มประวัติการอบรม
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>หลักสูตร / หัวข้อการอบรม</th>
                                    <th>หน่วยงานที่จัด</th>
                                    <th>ระยะเวลาอบรม</th>
                                    <th class="text-center">หน่วยกิต (CPE/CME)</th>
                                    <th width="10%">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($trainings)): ?>
                                    <tr><td colspan="5" class="empty-state"><i class="bi bi-inbox fs-1 d-block mb-2"></i>ยังไม่มีประวัติการฝึกอบรม</td></tr>
                                <?php else: foreach($trainings as $tr): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($tr['course_name']) ?></td>
                                        <td><?= htmlspecialchars($tr['organizer']) ?></td>
                                        <td class="small text-muted"><?= formatDateThai($tr['start_date']) ?> - <?= formatDateThai($tr['end_date']) ?></td>
                                        <td class="text-center"><span class="badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill"><?= (float)$tr['cpe_credits'] ?></span></td>
                                        <td>
                                            <form action="index.php?c=profile&a=delete_training" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบ?');">
                                                <?= security_csrf_input() ?>
                                                <input type="hidden" name="id" value="<?= $tr['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                                                <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle" aria-label="ลบ"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ============================================== -->
<!-- MODALS FOR ADDING DATA -->
<!-- ============================================== -->

<!-- 1. Modal: Add License -->
<div class="modal fade" id="modalAddLicense" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="index.php?c=profile&a=add_license" method="POST">
                            <?= security_csrf_input() ?>
                <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-success"><i class="bi bi-card-heading me-2"></i>เพิ่มใบประกอบวิชาชีพ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small text-muted">ชื่อใบประกอบวิชาชีพ *</label>
                        <input type="text" name="license_name" class="form-control rounded-3" placeholder="เช่น ใบอนุญาตประกอบวิชาชีพเวชกรรม" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">สภาวิชาชีพที่ออกให้</label>
                        <input type="text" name="council_name" class="form-control rounded-3" placeholder="เช่น สภาการพยาบาล">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">เลขที่ใบอนุญาต *</label>
                        <input type="text" name="license_no" class="form-control rounded-3" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted">วันที่ออก</label>
                            <input type="text" name="issue_date" class="form-control rounded-3 thai-datepicker">
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">วันหมดอายุ (ถ้ามี)</label>
                            <input type="text" name="expire_date" class="form-control rounded-3 thai-datepicker border-danger border-opacity-25">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4 px-4 py-3">
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Modal: Add Education -->
<div class="modal fade" id="modalAddEducation" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="index.php?c=profile&a=add_education" method="POST">
                            <?= security_csrf_input() ?>
                <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-primary"><i class="bi bi-mortarboard-fill me-2"></i>เพิ่มประวัติการศึกษา</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-5">
                            <label class="form-label small text-muted">ระดับการศึกษา *</label>
                            <select name="degree_level" class="form-select rounded-3" required>
                                <option value="ปริญญาตรี">ปริญญาตรี</option>
                                <option value="ปริญญาโท">ปริญญาโท</option>
                                <option value="ปริญญาเอก">ปริญญาเอก</option>
                                <option value="อนุปริญญา/ปวส.">อนุปริญญา/ปวส.</option>
                                <option value="วุฒิบัตรเฉพาะทาง">วุฒิบัตรเฉพาะทาง</option>
                            </select>
                        </div>
                        <div class="col-7">
                            <label class="form-label small text-muted">ชื่อวุฒิ (เช่น พย.บ., ส.บ.) *</label>
                            <input type="text" name="degree_name" class="form-control rounded-3" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">สาขาวิชาเอก</label>
                        <input type="text" name="major" class="form-control rounded-3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">สถาบันการศึกษา *</label>
                        <input type="text" name="institution" class="form-control rounded-3" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small text-muted">ปี พ.ศ. ที่จบ</label>
                            <input type="number" name="graduation_year" class="form-control rounded-3" placeholder="เช่น 2560">
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">เกรดเฉลี่ย (GPA)</label>
                            <input type="number" step="0.01" max="4.00" name="gpa" class="form-control rounded-3" placeholder="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4 px-4 py-3">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Modal: Add Work History -->
<div class="modal fade" id="modalAddWork" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="index.php?c=profile&a=add_work" method="POST">
                            <?= security_csrf_input() ?>
                <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-building-fill me-2"></i>เพิ่มประวัติการทำงาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small text-muted">ชื่อสถานที่ทำงาน/องค์กร *</label>
                        <input type="text" name="company_name" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">ตำแหน่ง *</label>
                        <input type="text" name="position" class="form-control rounded-3" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted">วันที่เริ่มงาน *</label>
                            <input type="text" name="start_date" class="form-control rounded-3 thai-datepicker" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">วันที่ออก (เว้นว่างถ้าปัจจุบัน)</label>
                            <input type="text" name="end_date" class="form-control rounded-3 thai-datepicker">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">สาเหตุที่ออก</label>
                        <input type="text" name="reason_for_leave" class="form-control rounded-3">
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4 px-4 py-3">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. Modal: Add Training -->
<div class="modal fade" id="modalAddTraining" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="index.php?c=profile&a=add_training" method="POST">
                            <?= security_csrf_input() ?>
                <input type="hidden" name="user_id" value="<?= $target_user_id ?>">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-warning text-dark"><i class="bi bi-award-fill me-2"></i>เพิ่มประวัติการฝึกอบรม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small text-muted">หลักสูตร / หัวข้อการอบรม *</label>
                        <input type="text" name="course_name" class="form-control rounded-3 border-warning" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">หน่วยงานที่จัดอบรม</label>
                        <input type="text" name="organizer" class="form-control rounded-3">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted">ตั้งแต่วันที่ *</label>
                            <input type="text" name="start_date" class="form-control rounded-3 thai-datepicker" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">ถึงวันที่ *</label>
                            <input type="text" name="end_date" class="form-control rounded-3 thai-datepicker" required>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted">หน่วยกิตการศึกษาต่อเนื่อง (CPE/CME)</label>
                        <input type="number" step="0.5" name="cpe_credits" class="form-control rounded-3 bg-warning bg-opacity-10 text-dark fw-bold w-50" placeholder="0.0">
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4 px-4 py-3">
                    <button type="submit" class="btn btn-warning text-dark rounded-pill px-4 fw-bold">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 🌟 ตั้งค่า Flatpickr ให้แสดง พ.ศ. แบบสมบูรณ์
    flatpickr(".thai-datepicker", { 
        locale: "th", 
        altInput: true, 
        altFormat: "j F Y", 
        dateFormat: "Y-m-d",
        formatDate: function(date, formatStr, locale) {
            const thaiMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            if (formatStr === "j F Y") { 
                return date.getDate() + ' ' + thaiMonths[date.getMonth()] + ' ' + (date.getFullYear() + 543);
            }
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },
        onReady: function(selectedDates, dateStr, instance) {
            const yearInput = instance.currentYearElement;
            yearInput.style.display = 'none';
            
            const beYearInput = document.createElement('input');
            beYearInput.className = 'numInput cur-year';
            beYearInput.type = 'number';
            beYearInput.min = 2400;
            beYearInput.max = 2999;
            beYearInput.step = 1;
            beYearInput.value = instance.currentYear + 543;
            
            yearInput.parentNode.insertBefore(beYearInput, yearInput.nextSibling);
            
            beYearInput.addEventListener('input', function(e) {
                let beYear = parseInt(this.value);
                if (beYear >= 2400) {
                    instance.changeYear(beYear - 543);
                }
            });
            instance.beYearInput = beYearInput;
        },
        onYearChange: function(selectedDates, dateStr, instance) {
            if(instance.beYearInput) instance.beYearInput.value = instance.currentYear + 543;
        }
    });

    // รักษา State ของ Tab เมื่อรีเฟรชหน้า
    let triggerTabList = [].slice.call(document.querySelectorAll('#profileTabs button'))
    triggerTabList.forEach(function (triggerEl) {
        let tabTrigger = new bootstrap.Tab(triggerEl)
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
            localStorage.setItem('activeProfileTab', this.getAttribute('data-bs-target'));
        });
    });

    let activeTab = localStorage.getItem('activeProfileTab');
    if (activeTab) {
        let tab = document.querySelector('button[data-bs-target="' + activeTab + '"]');
        if(tab) { new bootstrap.Tab(tab).show(); }
    }
});
</script>