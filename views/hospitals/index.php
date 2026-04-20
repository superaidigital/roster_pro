<?php
// ที่อยู่ไฟล์: views/hospitals/index.php

require_once 'config/database.php';
$db = (new Database())->getConnection();

// 🌟 1. ดึงสิทธิ์ผู้ใช้งาน
$user_role = strtoupper($_SESSION['user']['role'] ?? '');
$isAdmin = in_array($user_role, ['ADMIN', 'SUPERADMIN']);
$isDirector = ($user_role === 'DIRECTOR');
$my_hospital_id = $_SESSION['user']['hospital_id'] ?? null;

// 🌟 2. คัดกรองข้อมูล (Filter) ให้แสดงทุกหน่วยงาน ยกเว้น "ส่วนกลาง"
$all_hospitals = $hospitals ?? [];
$filtered_hospitals = [];
$hospital_ids = []; // เก็บ ID ไว้ใช้อ้างอิงหาจำนวนคน

foreach ($all_hospitals as $h) {
    $name = $h['name'] ?? '';
    // เช็คว่าไม่ใช่ "ส่วนกลาง" และ ID ไม่ใช่ 0
    if (mb_strpos($name, 'ส่วนกลาง') === false && $h['id'] != 0) {
        $filtered_hospitals[] = $h;
        $hospital_ids[] = $h['id'];
    }
}
$hospitals = $filtered_hospitals; // ใช้ข้อมูลที่กรองแล้วแทนที่ข้อมูลเดิม

// 🌟 ลอจิกคำนวณหา รหัสอ้างอิงระบบ (ID) ถัดไปอัตโนมัติ
$max_id_num = 0;
if (!empty($hospitals)) {
    foreach ($hospitals as $h) {
        $num = (int)preg_replace('/[^0-9]/', '', $h['id']);
        if ($num > $max_id_num) {
            $max_id_num = $num;
        }
    }
}
$auto_next_id = 'h' . ($max_id_num + 1);

// ==========================================
// 🌟 3. ลอจิกคำนวณสถิติภาพรวม เฉพาะ รพ.สต. (เชื่อม DB Real-time)
// ==========================================
$total_hospitals = count($hospitals);
$total_staff = 0;
$on_duty_today = 0;
$on_leave_today = 0;
$today_date = date('Y-m-d');

if (!empty($hospital_ids)) {
    // สร้างพารามิเตอร์ IN (?,?,?) สำหรับ Query
    $inQuery = implode(',', array_fill(0, count($hospital_ids), '?'));
    
    // 📊 ก. หาจำนวนบุคลากรทั้งหมด (ไม่รวมแอดมิน) ที่สังกัด รพ.สต.
    try {
        $stmt_staff = $db->prepare("SELECT COUNT(*) FROM users WHERE role NOT IN ('SUPERADMIN', 'ADMIN') AND hospital_id IN ($inQuery)");
        $stmt_staff->execute($hospital_ids);
        $total_staff = $stmt_staff->fetchColumn() ?: 0;
    } catch (Exception $e) {}

    // 📊 ข. หาจำนวนผู้ขึ้นเวรวันนี้ (เชื่อมกับตาราง shifts สังกัด รพ.สต.)
    try {
        $stmt_duty = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM shifts WHERE shift_date = ? AND shift_type NOT IN ('', 'L', 'O', 'OFF', 'ย') AND hospital_id IN ($inQuery)");
        $params_duty = array_merge([$today_date], $hospital_ids);
        $stmt_duty->execute($params_duty);
        $on_duty_today = $stmt_duty->fetchColumn() ?: 0;
    } catch (Exception $e) {}

    // 📊 ค. หาจำนวนผู้ลางานวันนี้ (เชื่อมกับตาราง leave_requests สังกัด รพ.สต.)
    try {
        $stmt_leave = $db->prepare("
            SELECT COUNT(DISTINCT lr.user_id) 
            FROM leave_requests lr 
            JOIN users u ON lr.user_id = u.id 
            WHERE lr.status = 'APPROVED' AND ? BETWEEN lr.start_date AND lr.end_date 
            AND u.hospital_id IN ($inQuery)
        ");
        $params_leave = array_merge([$today_date], $hospital_ids);
        $stmt_leave->execute($params_leave);
        $on_leave_today = $stmt_leave->fetchColumn() ?: 0;
    } catch (Exception $e) {}
}
?>

<style>
    body { background-color: #f4f6f9; }
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: #ffffff; }
    .table-modern th { font-weight: 600; color: #475569; font-size: 13px; background-color: #f8fafc; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; letter-spacing: 0.5px; padding: 1rem 0.75rem; }
    .table-modern td { vertical-align: middle; font-size: 14.5px; border-bottom: 1px solid #f1f5f9; padding: 1rem 0.75rem; background-color: #ffffff; transition: background-color 0.2s; }
    .table-modern tbody tr:hover td { background-color: #f8fafc; }
    .search-box { border: 1px solid #e2e8f0; background: #fff; transition: all 0.2s; border-radius: 1rem; overflow: hidden; }
    .search-box:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .search-box input { border: none; box-shadow: none; background: transparent; font-size: 14px; }
    .search-box input:focus { outline: none; }
    .btn-action { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s; }
    .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .size-badge { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: 800; font-size: 13px; }
    .size-s { background-color: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
    .size-m { background-color: #e0e7ff; color: #4f46e5; border: 1px solid #c7d2fe; }
    .size-l { background-color: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
    .size-xl { background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .size-unknown { background-color: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100 d-flex flex-column">
    
    <!-- 🌟 Header & Controls -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-hospital-fill text-danger me-2"></i> จัดการหน่วยบริการ (รพ.สต.)
            </h4>
            <p class="text-muted mb-0" style="font-size: 14px;">ตั้งค่า เพิ่ม แก้ไข ข้อมูลโรงพยาบาลส่งเสริมสุขภาพตำบลในเครือข่าย</p>
        </div>
        
        <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
            <!-- ช่องค้นหา -->
            <div class="input-group shadow-sm" style="min-width: 250px;">
                <span class="input-group-text bg-white text-muted border-end-0 rounded-start-pill"><i class="bi bi-search"></i></span>
                <input type="text" id="hospitalSearch" class="form-control border-start-0 rounded-end-pill" placeholder="ค้นหารหัส หรือชื่อ รพ.สต.">
            </div>
            
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success fw-bold shadow-sm d-flex align-items-center justify-content-center flex-grow-1 text-nowrap rounded-pill bg-white" onclick="exportTableToExcel('hospitalsTable', 'ข้อมูลหน่วยบริการ_รพสต')">
                    <i class="bi bi-file-earmark-excel-fill me-1"></i> ส่งออก
                </button>

                <!-- 🛡️ แสดงปุ่ม นำเข้า/เพิ่ม เฉพาะ Admin -->
                <?php if($isAdmin): ?>
                <button class="btn btn-success fw-bold shadow-sm d-flex align-items-center justify-content-center flex-grow-1 text-nowrap rounded-pill" data-bs-toggle="modal" data-bs-target="#uploadExcelModal">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> นำเข้า
                </button>
                
                <button class="btn btn-primary fw-bold shadow-sm d-flex align-items-center justify-content-center flex-grow-1 text-nowrap rounded-pill" data-bs-toggle="modal" data-bs-target="#addHospitalModal">
                    <i class="bi bi-plus-lg me-1"></i> เพิ่ม รพ.สต.
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 🌟 Alerts -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert border-0 bg-success bg-opacity-10 text-success rounded-4 d-flex align-items-center mb-4 p-3 shadow-sm border-start border-success border-4">
            <i class="bi bi-check-circle-fill fs-5 me-3"></i> <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['success_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert border-0 bg-danger bg-opacity-10 text-danger rounded-4 d-flex align-items-center mb-4 p-3 shadow-sm border-start border-danger border-4">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-3"></i> <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['error_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <!-- 🌟 กล่องสรุปสถิติ -->
    <div class="row g-3 mb-4">
        <!-- 1. จำนวน รพ.สต. -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white overflow-hidden">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-building fs-4"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($total_hospitals) ?></h4>
                        <div class="text-muted" style="font-size: 12px;">หน่วยบริการ (แห่ง)</div>
                    </div>
                </div>
                <div class="bg-primary" style="height: 3px; width: 100%;"></div>
            </div>
        </div>
        
        <!-- 2. บุคลากรทั้งหมด -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white overflow-hidden">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-people-fill fs-4 text-primary"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($total_staff) ?></h4>
                        <div class="text-muted" style="font-size: 12px;">บุคลากรทั้งหมด (คน)</div>
                    </div>
                </div>
                <div class="bg-info" style="height: 3px; width: 100%;"></div>
            </div>
        </div>

        <!-- 3. ผู้ปฏิบัติงานวันนี้ -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white overflow-hidden">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-person-workspace fs-4"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($on_duty_today) ?></h4>
                        <div class="text-muted" style="font-size: 12px;">ขึ้นเวรวันนี้ (คน)</div>
                    </div>
                </div>
                <div class="bg-success" style="height: 3px; width: 100%;"></div>
            </div>
        </div>

        <!-- 4. ลางาน/ขาดราชการ -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white overflow-hidden">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-person-dash-fill fs-4"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($on_leave_today) ?></h4>
                        <div class="text-muted" style="font-size: 12px;">ลางาน/หยุดพัก วันนี้ (คน)</div>
                    </div>
                </div>
                <div class="bg-danger" style="height: 3px; width: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- 🌟 ตารางข้อมูล รพ.สต. -->
    <div class="card card-modern flex-grow-1 overflow-hidden d-flex flex-column mb-4">
        <div class="card-body p-0 d-flex flex-column flex-grow-1">
            <div class="table-responsive flex-grow-1">
                <table class="table table-modern mb-0" id="hospitalsTable">
                    <thead class="sticky-top" style="z-index: 10;">
                        <tr>
                            <th class="text-center" style="width: 5%;">#</th>
                            <th class="text-center" style="width: 10%;">รหัสหน่วย</th>
                            <th style="width: 30%;">ชื่อหน่วยบริการ (รพ.สต.)</th>
                            <th class="text-center" style="width: 10%;">ขนาด</th>
                            <th style="width: 20%;">ผู้อำนวยการ รพ.สต.</th>
                            <th style="width: 15%;">เบอร์ติดต่อ</th>
                            <th class="text-center pe-4" style="width: 10%;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="hospitalTableBody">
                        <?php if (empty($hospitals)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="bi bi-building-x fs-1 text-secondary opacity-50"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">ไม่พบข้อมูล รพ.สต.</h6>
                                    <p class="text-muted small mb-0">ยังไม่มีข้อมูลในระบบ หรือชื่อไม่ตรงกับเงื่อนไข</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($hospitals as $h): 
                                // จัดการป้ายขนาด
                                $size = strtoupper(trim($h['hospital_size'] ?? 'S'));
                                $size_class = 'size-unknown';
                                if ($size === 'S') $size_class = 'size-s';
                                elseif ($size === 'M') $size_class = 'size-m';
                                elseif ($size === 'L') $size_class = 'size-l';
                                elseif ($size === 'XL') $size_class = 'size-xl';

                                // 🌟 เช็คสิทธิ์: เป็น Admin หรือ ผอ. ของ รพ. นี้เท่านั้น ถึงจะแก้ไขได้
                                $canEdit = $isAdmin || ($isDirector && $h['id'] == $my_hospital_id);
                            ?>
                            <!-- ไฮไลท์สีเหลืองอ่อนสำหรับ รพ. ของตนเอง -->
                            <tr class="hospital-row <?= ($isDirector && $h['id'] == $my_hospital_id) ? 'table-warning' : '' ?>">
                                <td class="text-center text-muted fw-medium"><?= $no++ ?>
                                    <span class="d-none hospital-id"><?= htmlspecialchars($h['id']) ?></span>
                                </td>
                                <td class="text-center font-monospace fw-bold text-primary hospital-code">
                                    <?= htmlspecialchars($h['hospital_code'] ?? '-') ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark hospital-name" style="font-size: 15px;">
                                        <?= htmlspecialchars($h['name']) ?>
                                    </div>
                                    <?php if(!empty($h['district'])): ?>
                                        <div class="text-muted mt-1" style="font-size: 12px;">
                                            <i class="bi bi-geo-alt-fill text-danger opacity-75 me-1"></i> อ.<?= htmlspecialchars($h['district']) ?> จ.<?= htmlspecialchars($h['province'] ?? '') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="size-badge <?= $size_class ?> shadow-sm mx-auto" title="ขนาด <?= $size ?>">
                                        <?= $size ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex justify-content-center align-items-center me-2 fw-bold" style="width: 32px; height: 32px; font-size: 14px;">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                        <div class="text-dark fw-medium" style="font-size: 14px;">
                                            <?= htmlspecialchars($h['director_name'] ?? 'ยังไม่ระบุ') ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($h['phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($h['phone']) ?>" class="text-decoration-none text-dark fw-medium">
                                            <i class="bi bi-telephone-fill text-success me-1 opacity-75"></i> <?= htmlspecialchars($h['phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4 text-nowrap">
                                    <?php if($canEdit): ?>
                                        <!-- ปุ่มแก้ไข (เห็นเฉพาะ Admin หรือ ผอ. รพ. ตัวเอง) -->
                                        <button type="button" class="btn-action bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 me-1" title="เปลี่ยนชื่อ/รหัส"
                                                onclick="openEditModal('<?= htmlspecialchars($h['id']) ?>', '<?= htmlspecialchars($h['hospital_code'] ?? '') ?>', '<?= htmlspecialchars($h['name'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        
                                        <a href="index.php?c=settings&a=hospital&id=<?= urlencode($h['id']) ?>" class="btn-action bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 me-1" title="ตั้งค่าข้อมูลพื้นฐาน/พิกัดแผนที่">
                                            <i class="bi bi-gear-fill"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if($isAdmin): ?>
                                        <!-- ปุ่มลบ (เห็นเฉพาะ Admin) -->
                                        <a href="index.php?c=hospitals&a=delete&id=<?= urlencode($h['id']) ?>" class="btn-action bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" title="ลบ" onclick="return confirm('คำเตือน: ยืนยันการลบ <?= htmlspecialchars($h['name'], ENT_QUOTES) ?> ?\n\n(หากมีพนักงานสังกัดอยู่ จะไม่สามารถลบได้)');">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                    <?php elseif(!$canEdit): ?>
                                        <!-- ไอคอนล็อกกุญแจ หาก ผอ. ดู รพ. อื่น -->
                                        <span class="text-muted small" title="คุณไม่มีสิทธิ์แก้ไขหน่วยบริการนี้"><i class="bi bi-lock-fill"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- ระบบแบ่งหน้า (Pagination) -->
        <?php if(!empty($hospitals)): ?>
        <div class="p-3 border-top bg-white d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
            <div class="text-muted small fw-medium" id="paginationInfo">แสดงข้อมูล...</div>
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0 d-none d-sm-block">แสดง:</label>
                <select id="rowsPerPageSelect" class="form-select form-select-sm text-secondary bg-light border-0 shadow-none" style="width: auto; cursor: pointer;">
                    <option value="10" selected>10 แห่ง</option>
                    <option value="25">25 แห่ง</option>
                    <option value="50">50 แห่ง</option>
                    <option value="all">ทั้งหมด</option>
                </select>
                <nav><ul class="pagination pagination-sm mb-0 shadow-sm" id="paginationControls"></ul></nav>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ================= 🌟 Modal นำเข้า Excel/CSV (เฉพาะ Admin) ================= -->
<?php if($isAdmin): ?>
<div class="modal fade" id="uploadExcelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 bg-success text-white rounded-top" style="padding: 1.5rem;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> นำเข้าข้อมูล (Excel/CSV)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form action="index.php?c=hospitals&a=import_csv" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4 text-center">
                    <div class="mb-4">
                        <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-cloud-arrow-up-fill text-success" style="font-size: 40px;"></i>
                        </div>
                        <h6 class="fw-bold text-dark">อัปโหลดไฟล์ตารางข้อมูล รพ.สต.</h6>
                        <p class="text-muted small mb-0">รองรับไฟล์นามสกุล <b>.csv</b> เท่านั้น <br>(สามารถบันทึกจาก Excel ด้วยเมนู Save as > CSV UTF-8)</p>
                    </div>

                    <div class="mb-4 text-start">
                        <input class="form-control" type="file" id="file_csv" name="file_csv" accept=".csv" required>
                    </div>

                    <div class="p-3 bg-warning bg-opacity-10 rounded border border-warning border-opacity-25 text-start">
                        <div class="fw-bold text-dark mb-1" style="font-size: 13px;"><i class="bi bi-info-circle-fill text-warning me-1"></i> คำแนะนำก่อนอัปโหลด:</div>
                        <ul class="text-muted mb-0 ps-3" style="font-size: 12px; line-height: 1.6;">
                            <li>รูปแบบตารางต้องเรียงคอลัมน์: <b>รหัสอ้างอิง(ID)</b>, <b>รหัส 5 หลัก</b>, <b>ชื่อ รพ.สต.</b></li>
                            <li>แถวแรกสุด (Header) จะถูกข้ามไม่อ่านข้อมูล</li>
                            <li>รหัสอ้างอิงระบบ (ID) ต้องไม่ซ้ำกับของเดิมที่มีอยู่ (เช่น h99, h100)</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 pb-4 px-4 d-flex justify-content-between">
                    <a href="index.php?c=hospitals&a=download_template" class="btn btn-outline-success fw-bold rounded-pill px-3">
                        <i class="bi bi-download me-1"></i> โหลดไฟล์ต้นแบบ
                    </a>
                    <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm rounded-pill">
                        <i class="bi bi-upload me-1"></i> เริ่มนำเข้าข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= 🌟 Modal เพิ่มหน่วยบริการ ================= -->
<div class="modal fade" id="addHospitalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 bg-light rounded-top-4 pb-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-building-add text-primary me-2"></i> เพิ่มหน่วยบริการ (รพ.สต.)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?c=hospitals&a=add" method="POST">
                <div class="modal-body pt-3 pb-4 px-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">รหัสอ้างอิงระบบ (ID - สร้างอัตโนมัติ)</label>
                        <input type="text" name="id" class="form-control bg-primary bg-opacity-10 border-primary border-opacity-25 text-primary fw-bold" value="<?= $auto_next_id ?>" readonly>
                        <small class="text-primary mt-1 d-block" style="font-size: 11px;"><i class="bi bi-info-circle me-1"></i>ระบบรันลำดับรหัสนี้ให้อัตโนมัติ</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">รหัสหน่วยบริการ (5 หลัก)</label>
                        <input type="text" name="hospital_code" class="form-control bg-white shadow-sm" placeholder="เช่น 04875" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">ชื่อหน่วยบริการ</label>
                        <input type="text" name="name" class="form-control bg-white shadow-sm" placeholder="เช่น รพ.สต. บ้านโคก" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary fw-bold rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm"><i class="bi bi-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================= 🌟 Modal แก้ไขชื่อ/รหัส (Admin & Director) ================= -->
<div class="modal fade" id="editHospitalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 bg-light rounded-top-4 pb-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i> เปลี่ยนชื่อ/รหัสหน่วยบริการ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?c=hospitals&a=edit" method="POST">
                <div class="modal-body pt-3 pb-4 px-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">รหัสอ้างอิงระบบ (ID - ห้ามแก้ไข)</label>
                        <input type="text" id="edit_id" name="id" class="form-control bg-secondary bg-opacity-10 border-0 text-muted fw-bold" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">รหัสหน่วยบริการ (5 หลัก)</label>
                        <input type="text" id="edit_code" name="hospital_code" class="form-control bg-white shadow-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">ชื่อหน่วยบริการ</label>
                        <input type="text" id="edit_name" name="name" class="form-control bg-white shadow-sm" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary fw-bold rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning fw-bold px-4 text-dark rounded-pill shadow-sm"><i class="bi bi-save me-1"></i> อัปเดตข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 🌟 ฟังก์ชันแปลงตาราง HTML เป็น Excel
function exportTableToExcel(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel;charset=utf-8';
    var tableSelect = document.getElementById(tableID);
    
    // โคลนตารางออกมาเพื่อไม่ให้กระทบ UI ที่แสดงผลอยู่
    var tableClone = tableSelect.cloneNode(true);
    
    // 1. ตัดคอลัมน์ "จัดการ" ทิ้ง (คือคอลัมน์สุดท้ายของ Thead และ Tbody)
    var ths = tableClone.querySelectorAll('thead tr th');
    if(ths.length > 0) ths[ths.length - 1].remove();

    var trs = tableClone.querySelectorAll('tbody tr');
    trs.forEach(tr => {
        var tds = tr.querySelectorAll('td');
        if(tds.length > 0) tds[tds.length - 1].remove();
    });

    // 2. ทำความสะอาดไอคอน หรือ class ที่ซ่อนอยู่เพื่อความสวยงามใน Excel
    var unwantedElements = tableClone.querySelectorAll('.bi, .d-none');
    unwantedElements.forEach(el => el.remove());

    var tableHTML = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body>' + tableClone.outerHTML + '</body></html>';
    
    // สร้างชื่อไฟล์
    filename = filename ? filename + '.xls' : 'excel_data.xls';
    
    // สร้าง Link ดาวน์โหลด
    downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    
    if (navigator.msSaveOrOpenBlob){
        var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
        navigator.msSaveOrOpenBlob( blob, filename);
    } else {
        downloadLink.href = 'data:' + dataType + ', ' + encodeURIComponent(tableHTML);
        downloadLink.download = filename;
        downloadLink.click();
    }
    // ลบ Link ทิ้งหลังดาวน์โหลดเสร็จ
    document.body.removeChild(downloadLink);
}

// ฟังก์ชันโยนข้อมูลใส่ Modal แก้ไข
function openEditModal(id, code, name) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_name').value = name;
    new bootstrap.Modal(document.getElementById('editHospitalModal')).show();
}

// 🌟 สคริปต์ค้นหาและแบ่งหน้าตาราง (Pagination & Real-time Search)
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('hospitalSearch');
    const tableBody = document.getElementById('hospitalTableBody');
    const paginationControls = document.getElementById('paginationControls');
    const paginationInfo = document.getElementById('paginationInfo');
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');

    if (!tableBody || !document.querySelector('.hospital-row')) return;

    const allRows = Array.from(document.querySelectorAll('.hospital-row'));
    let currentPage = 1;
    let rowsPerPage = 10;
    let filteredRows = [...allRows];

    function updateTable() {
        const term = searchInput ? searchInput.value.toLowerCase().trim() : '';

        // 1. ค้นหา
        filteredRows = allRows.filter(row => {
            const code = row.querySelector('.hospital-code').textContent.toLowerCase();
            const name = row.querySelector('.hospital-name').textContent.toLowerCase();
            const id = row.querySelector('.hospital-id').textContent.toLowerCase();
            return code.includes(term) || name.includes(term) || id.includes(term);
        });

        // 2. แบ่งหน้า
        const totalRows = filteredRows.length;
        const totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(totalRows / rowsPerPage);

        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = rowsPerPage === 'all' ? 0 : (currentPage - 1) * rowsPerPage;
        const endIdx = rowsPerPage === 'all' ? totalRows : startIdx + rowsPerPage;

        allRows.forEach(row => row.style.display = 'none');
        filteredRows.slice(startIdx, endIdx).forEach(row => row.style.display = '');

        // 3. อัปเดตข้อความ "แสดงข้อมูล..."
        if (paginationInfo) {
            if (totalRows === 0) {
                paginationInfo.innerHTML = `ไม่พบข้อมูลที่ค้นหา`;
                // แสดงแถว Not Found
                let noResultRow = document.getElementById('noResultRow');
                if (!noResultRow) {
                    noResultRow = document.createElement('tr');
                    noResultRow.id = 'noResultRow';
                    noResultRow.innerHTML = '<td colspan="7" class="text-center py-5 text-muted fw-bold"><i class="bi bi-search mb-2 fs-2 d-block"></i>ไม่พบ รพ.สต. ที่ตรงกับคำค้นหา</td>';
                    tableBody.appendChild(noResultRow);
                }
                noResultRow.style.display = '';
            } else {
                let noResultRow = document.getElementById('noResultRow');
                if(noResultRow) noResultRow.style.display = 'none';
                paginationInfo.innerHTML = `แสดง <b>${startIdx + 1}</b> ถึง <b>${Math.min(endIdx, totalRows)}</b> จาก <b>${totalRows}</b> แห่ง`;
            }
        }

        // 4. สร้างปุ่มเปลี่ยนหน้า
        if (paginationControls) {
            paginationControls.innerHTML = '';
            if (totalPages > 1) {
                let prevLi = document.createElement('li');
                prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
                prevLi.innerHTML = `<a class="page-link shadow-none" style="cursor:pointer;">&laquo;</a>`;
                if (currentPage > 1) { prevLi.onclick = (e) => { e.preventDefault(); currentPage--; updateTable(); }; }
                paginationControls.appendChild(prevLi);

                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);
                
                if (startPage > 1) {
                    let firstLi = document.createElement('li');
                    firstLi.className = `page-item`;
                    firstLi.innerHTML = `<a class="page-link shadow-none" style="cursor:pointer;">1</a>`;
                    firstLi.onclick = (e) => { e.preventDefault(); currentPage = 1; updateTable(); };
                    paginationControls.appendChild(firstLi);
                    if (startPage > 2) paginationControls.innerHTML += `<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>`;
                }

                for (let i = startPage; i <= endPage; i++) {
                    let li = document.createElement('li');
                    li.className = `page-item ${currentPage === i ? 'active' : ''}`;
                    li.innerHTML = `<a class="page-link shadow-none" style="cursor:pointer;">${i}</a>`;
                    li.onclick = (e) => { e.preventDefault(); currentPage = i; updateTable(); };
                    paginationControls.appendChild(li);
                }

                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) paginationControls.innerHTML += `<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>`;
                    let lastLi = document.createElement('li');
                    lastLi.className = `page-item`;
                    lastLi.innerHTML = `<a class="page-link shadow-none" style="cursor:pointer;">${totalPages}</a>`;
                    lastLi.onclick = (e) => { e.preventDefault(); currentPage = totalPages; updateTable(); };
                    paginationControls.appendChild(lastLi);
                }

                let nextLi = document.createElement('li');
                nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
                nextLi.innerHTML = `<a class="page-link shadow-none" style="cursor:pointer;">&raquo;</a>`;
                if (currentPage < totalPages) { nextLi.onclick = (e) => { e.preventDefault(); currentPage++; updateTable(); }; }
                paginationControls.appendChild(nextLi);
            }
        }
    }

    if (searchInput) searchInput.addEventListener('input', () => { currentPage = 1; updateTable(); });
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', function() {
            rowsPerPage = this.value === 'all' ? 'all' : parseInt(this.value);
            currentPage = 1; updateTable();
        });
    }

    updateTable(); // รันครั้งแรก
});
</script>