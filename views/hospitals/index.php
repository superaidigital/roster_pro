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
$all_hospitals = $hospitals_list ?? []; // ใช้ $hospitals_list จาก Controller
$filtered_hospitals = [];
$hospital_ids = []; 

foreach ($all_hospitals as $h) {
    $name = $h['name'] ?? '';
    if (mb_strpos($name, 'ส่วนกลาง') === false && $h['id'] != 0) {
        $filtered_hospitals[] = $h;
        $hospital_ids[] = $h['id'];
    }
}
$hospitals = $filtered_hospitals; 

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
    $inQuery = implode(',', array_fill(0, count($hospital_ids), '?'));
    
    // 📊 ก. หาจำนวนบุคลากรทั้งหมด (ไม่รวมแอดมิน)
    try {
        $stmt_staff = $db->prepare("SELECT COUNT(*) FROM users WHERE role NOT IN ('SUPERADMIN', 'ADMIN') AND deleted_at IS NULL AND hospital_id IN ($inQuery)");
        $stmt_staff->execute($hospital_ids);
        $total_staff = $stmt_staff->fetchColumn() ?: 0;
    } catch (Exception $e) {}

    // 📊 ข. หาจำนวนผู้ขึ้นเวรวันนี้
    try {
        $stmt_duty = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM shifts WHERE shift_date = ? AND shift_type NOT IN ('', 'L', 'O', 'OFF', 'ย') AND hospital_id IN ($inQuery)");
        $params_duty = array_merge([$today_date], $hospital_ids);
        $stmt_duty->execute($params_duty);
        $on_duty_today = $stmt_duty->fetchColumn() ?: 0;
    } catch (Exception $e) {}

    // 📊 ค. หาจำนวนผู้ลางานวันนี้
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .rp-hosp-page {
        max-width: 100rem;
        margin-inline: auto;
    }

    .rp-hosp-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .rp-hosp-heading {
        display: flex;
        align-items: flex-start;
        gap: .8rem;
        min-width: 0;
    }

    .rp-hosp-heading-icon {
        display: grid;
        place-items: center;
        width: 2.55rem;
        height: 2.55rem;
        flex: 0 0 2.55rem;
        border: 1px solid #d9e7ef;
        border-radius: .78rem;
        color: #0f6cbd;
        background: linear-gradient(145deg, #edf7ff, #effcf8);
        font-size: 1.05rem;
    }

    .rp-hosp-title {
        margin: 0 0 .2rem;
        color: #0f172a;
        font-size: clamp(1.2rem, 1.8vw, 1.5rem);
        font-weight: 800;
        letter-spacing: -.02em;
        line-height: 1.25;
    }

    .rp-hosp-subtitle {
        margin: 0;
        color: #64748b;
        font-size: .82rem;
        line-height: 1.45;
    }

    .rp-hosp-toolbar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: .5rem;
        min-width: 0;
    }

    .rp-hosp-search {
        display: flex;
        align-items: center;
        min-width: min(22rem, 100%);
        min-height: 2.65rem;
        overflow: hidden;
        border: 1px solid #d7e3ea;
        border-radius: .75rem;
        background: #fff;
        transition: border-color .18s ease, box-shadow .18s ease;
    }

    .rp-hosp-search:focus-within {
        border-color: #7fb1d2;
        box-shadow: 0 0 0 .2rem rgba(15,108,189,.10);
    }

    .rp-hosp-search-icon {
        display: grid;
        place-items: center;
        width: 2.6rem;
        flex: 0 0 2.6rem;
        color: #64748b;
        font-size: .95rem;
    }

    .rp-hosp-search input {
        width: 100%;
        min-width: 0;
        border: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
        padding: .55rem .75rem .55rem 0;
        color: #0f172a;
        font-size: .84rem;
    }

    .rp-hosp-search input::placeholder { color: #94a3b8; }

    .rp-toolbar-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        min-height: 2.65rem;
        padding: .55rem .85rem;
        border-radius: .72rem !important;
        font-size: .8rem;
        font-weight: 800;
        white-space: nowrap;
        box-shadow: none !important;
    }

    .rp-toolbar-btn i {
        margin: 0 !important;
        font-size: .95rem;
        line-height: 1;
    }

    .rp-toolbar-btn.is-export {
        color: #166534 !important;
        border: 1px solid #bbdfc6 !important;
        background: #f6fcf8 !important;
    }
    .rp-toolbar-btn.is-export:hover {
        color: #14532d !important;
        border-color: #8fc79f !important;
        background: #ecf8ef !important;
    }

    .rp-toolbar-btn.is-import {
        color: #0f766e !important;
        border: 1px solid #b9e2dc !important;
        background: #f0fdfa !important;
    }
    .rp-toolbar-btn.is-import:hover {
        color: #115e59 !important;
        border-color: #83ccc2 !important;
        background: #e7f9f5 !important;
    }

    .rp-toolbar-btn.is-primary {
        color: #fff !important;
        border: 1px solid #0f6cbd !important;
        background: #0f6cbd !important;
    }
    .rp-toolbar-btn.is-primary:hover {
        border-color: #0b5a9d !important;
        background: #0b5a9d !important;
    }

    .rp-toolbar-btn.is-danger {
        color: #b91c1c !important;
        border: 1px solid #fecaca !important;
        background: #fff7f7 !important;
    }

    .rp-hosp-kpi {
        position: relative;
        height: 100%;
        min-height: 5.5rem;
        overflow: hidden;
        border: 1px solid #dce6ed;
        border-radius: .95rem;
        background: #fff;
        box-shadow: 0 .2rem .65rem rgba(15,23,42,.025);
    }

    .rp-hosp-kpi::before {
        content: '';
        position: absolute;
        inset: 0 auto 0 0;
        width: .2rem;
        background: var(--kpi-accent, #2563eb);
    }

    .rp-hosp-kpi-body {
        display: flex;
        align-items: center;
        gap: .8rem;
        min-height: 5.5rem;
        padding: .85rem 1rem;
    }

    .rp-hosp-kpi-icon {
        display: grid;
        place-items: center;
        width: 2.6rem;
        height: 2.6rem;
        flex: 0 0 2.6rem;
        border-radius: .75rem;
        font-size: 1.05rem;
    }

    .rp-tone-blue { color: #1d4ed8; background: #eff6ff; }
    .rp-tone-cyan { color: #0369a1; background: #ecfeff; }
    .rp-tone-green { color: #15803d; background: #f0fdf4; }
    .rp-tone-red { color: #b91c1c; background: #fef2f2; }

    .rp-hosp-kpi-value {
        margin: 0 0 .12rem;
        color: #0f172a;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -.025em;
        line-height: 1;
    }

    .rp-hosp-kpi-label {
        color: #64748b;
        font-size: .72rem;
        font-weight: 700;
        line-height: 1.25;
    }

    .rp-hosp-panel {
        border: 1px solid #dce6ed !important;
        border-radius: 1rem !important;
        background: #fff !important;
        box-shadow: 0 .35rem 1rem rgba(15,23,42,.035) !important;
    }

    .rp-hosp-panel .card-header {
        min-height: 3.35rem;
        padding: .75rem 1rem !important;
        background: #fff !important;
        border-bottom: 1px solid #e5edf2 !important;
    }

    .rp-panel-title {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        margin: 0;
        color: #1e293b;
        font-size: .86rem;
        font-weight: 800;
    }

    .rp-panel-title-icon {
        display: grid;
        place-items: center;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: .5rem;
        color: #0f6cbd;
        background: #eff6ff;
        font-size: .85rem;
    }

    .rp-sort-hint {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .4rem .65rem;
        border: 1px solid #d7e3ea;
        border-radius: .65rem;
        background: #f8fafc;
        color: #64748b;
        font-size: .7rem;
        font-weight: 700;
    }

    .table-modern th {
        padding: .75rem .7rem !important;
        color: #64748b !important;
        background: #f8fafc !important;
        border-bottom: 1px solid #dce6ed !important;
        font-size: .7rem !important;
        font-weight: 800 !important;
        letter-spacing: .035em;
        text-transform: uppercase;
    }

    .table-modern td {
        padding: .78rem .7rem !important;
        color: #334155;
        background: #fff;
        border-bottom: 1px solid #edf2f5 !important;
        font-size: .82rem;
        vertical-align: middle;
    }

    .table-modern tbody tr:hover td { background: #fbfdff; }

    .hospital-name {
        color: #0f172a !important;
        font-size: .84rem !important;
        font-weight: 800 !important;
        line-height: 1.35;
    }

    .hospital-code {
        display: flex;
        align-items: center;
        gap: .3rem;
        color: #94a3b8 !important;
        font-size: .69rem !important;
    }

    .size-badge {
        display: inline-grid;
        place-items: center;
        width: 1.9rem;
        height: 1.9rem;
        border-radius: .55rem;
        font-size: .68rem;
        font-weight: 800;
    }
    .size-s { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
    .size-m { background:#eef2ff; color:#4338ca; border:1px solid #c7d2fe; }
    .size-l { background:#fffbeb; color:#b45309; border:1px solid #fde68a; }
    .size-xl { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
    .size-unknown { background:#f8fafc; color:#64748b; border:1px solid #e2e8f0; }

    .drag-handle {
        color: #94a3b8;
        cursor: grab;
        font-size: 1rem;
        transition: color .18s ease;
    }
    .drag-handle:hover { color: #0f6cbd; }
    .drag-handle:active { cursor: grabbing; }
    .sortable-ghost td {
        background: #eff6ff !important;
        border-top: 1px dashed #60a5fa !important;
        border-bottom: 1px dashed #60a5fa !important;
    }

    .rp-row-actions {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
    }

    .btn-action {
        display: inline-grid;
        place-items: center;
        width: 2rem;
        height: 2rem;
        padding: 0 !important;
        border-radius: .6rem !important;
        box-shadow: none !important;
        transition: border-color .18s ease, background .18s ease, color .18s ease;
    }

    .btn-action i {
        margin: 0 !important;
        font-size: .82rem;
        line-height: 1;
    }

    .btn-action.is-edit {
        color: #a16207 !important;
        border: 1px solid #fde68a !important;
        background: #fffbeb !important;
    }
    .btn-action.is-edit:hover { background:#fef3c7 !important; border-color:#facc15 !important; }

    .btn-action.is-config {
        color: #1d4ed8 !important;
        border: 1px solid #bfdbfe !important;
        background: #eff6ff !important;
    }
    .btn-action.is-config:hover { background:#dbeafe !important; border-color:#93c5fd !important; }

    .btn-action.is-delete {
        color: #b91c1c !important;
        border: 1px solid #fecaca !important;
        background: #fef2f2 !important;
    }
    .btn-action.is-delete:hover { background:#fee2e2 !important; border-color:#fca5a5 !important; }

    .dataTables_filter { display: none; }

    @media (max-width: 1199.98px) {
        .rp-hosp-hero { flex-direction: column; }
        .rp-hosp-toolbar { width: 100%; justify-content: flex-start; }
    }

    @media (max-width: 767.98px) {
        .rp-hosp-toolbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
        }

        .rp-hosp-search { width: 100%; min-width: 0; }

        .rp-hosp-toolbar-actions {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0,1fr));
            width: 100%;
        }

        .rp-toolbar-btn { width: 100%; }

        .rp-hosp-kpi-body { padding: .78rem .85rem; }

        .rp-sort-hint { display: none; }
    }

    @media (max-width: 479.98px) {
        .rp-hosp-heading-icon { display: none; }
        .rp-hosp-toolbar-actions { grid-template-columns: 1fr; }
    }

    @media (prefers-reduced-motion: reduce) {
        .btn-action,
        .rp-hosp-search { transition: none; }
    }
</style>

<div class="container-fluid px-2 px-md-3 py-3 min-vh-100 d-flex flex-column rp-hosp-page">
    
    <section class="rp-hosp-hero" aria-labelledby="hospitalPageTitle">
        <div class="rp-hosp-heading">
            <span class="rp-hosp-heading-icon" aria-hidden="true"><i class="bi bi-hospital"></i></span>
            <div>
                <h2 class="rp-hosp-title" id="hospitalPageTitle">จัดการหน่วยบริการ (รพ.สต.)</h2>
                <p class="rp-hosp-subtitle">ตั้งค่า เพิ่ม แก้ไข และดูแลข้อมูลโรงพยาบาลส่งเสริมสุขภาพตำบลในเครือข่าย</p>
            </div>
        </div>

        <div class="rp-hosp-toolbar" role="group" aria-label="เครื่องมือจัดการหน่วยบริการ">
            <label class="rp-hosp-search" for="hospitalSearch">
                <span class="rp-hosp-search-icon"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input type="search" id="hospitalSearch" placeholder="ค้นหารหัส หรือชื่อ รพ.สต." autocomplete="off">
            </label>

            <div class="d-flex gap-2 rp-hosp-toolbar-actions">
                <button class="rp-toolbar-btn is-danger d-none" id="btn-bulk-delete" type="button" onclick="bulkDelete()">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                    <span>ลบ (<span id="selected-count">0</span>)</span>
                </button>

                <button class="rp-toolbar-btn is-export" type="button" onclick="exportTableToExcel('hospitalsTable', 'ข้อมูลหน่วยบริการ_รพสต')">
                    <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i>
                    <span>ส่งออก</span>
                </button>

                <?php if ($isAdmin): ?>
                <button class="rp-toolbar-btn is-import" type="button" data-bs-toggle="modal" data-bs-target="#uploadExcelModal">
                    <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i>
                    <span>นำเข้า</span>
                </button>

                <button class="rp-toolbar-btn is-primary" type="button" data-bs-toggle="modal" data-bs-target="#addHospitalModal">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    <span>เพิ่ม รพ.สต.</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </section>

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

    <section class="row g-2 g-md-3 mb-3" aria-label="สรุปข้อมูลหน่วยบริการ">
        <div class="col-6 col-xl-3">
            <div class="rp-hosp-kpi" style="--kpi-accent:#2563eb;">
                <div class="rp-hosp-kpi-body">
                    <span class="rp-hosp-kpi-icon rp-tone-blue"><i class="bi bi-buildings" aria-hidden="true"></i></span>
                    <div>
                        <p class="rp-hosp-kpi-value"><?= number_format($total_hospitals) ?></p>
                        <div class="rp-hosp-kpi-label">หน่วยบริการ (แห่ง)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="rp-hosp-kpi" style="--kpi-accent:#0891b2;">
                <div class="rp-hosp-kpi-body">
                    <span class="rp-hosp-kpi-icon rp-tone-cyan"><i class="bi bi-people" aria-hidden="true"></i></span>
                    <div>
                        <p class="rp-hosp-kpi-value"><?= number_format($total_staff) ?></p>
                        <div class="rp-hosp-kpi-label">บุคลากรทั้งหมด (คน)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="rp-hosp-kpi" style="--kpi-accent:#16a34a;">
                <div class="rp-hosp-kpi-body">
                    <span class="rp-hosp-kpi-icon rp-tone-green"><i class="bi bi-person-check" aria-hidden="true"></i></span>
                    <div>
                        <p class="rp-hosp-kpi-value"><?= number_format($on_duty_today) ?></p>
                        <div class="rp-hosp-kpi-label">ขึ้นเวรวันนี้ (คน)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="rp-hosp-kpi" style="--kpi-accent:#dc2626;">
                <div class="rp-hosp-kpi-body">
                    <span class="rp-hosp-kpi-icon rp-tone-red"><i class="bi bi-person-dash" aria-hidden="true"></i></span>
                    <div>
                        <p class="rp-hosp-kpi-value"><?= number_format($on_leave_today) ?></p>
                        <div class="rp-hosp-kpi-label">ลางาน / หยุดพักวันนี้ (คน)</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="card rp-hosp-panel flex-grow-1 overflow-hidden d-flex flex-column mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h3 class="rp-panel-title"><span class="rp-panel-title-icon"><i class="bi bi-list-ul" aria-hidden="true"></i></span>รายชื่อหน่วยบริการ</h3>
            <?php if($isAdmin): ?>
            <span class="rp-sort-hint"><i class="bi bi-grip-vertical" aria-hidden="true"></i>ลากเพื่อเรียงลำดับ</span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0 d-flex flex-column flex-grow-1">
            <div class="table-responsive flex-grow-1 p-3">
                <table class="table table-modern mb-0" id="hospitalsTable" style="min-width: 900px;">
                    <thead class="sticky-top" style="z-index: 10;">
                        <tr>
                            <th width="3%" class="text-center">
                                <?php if($isAdmin): ?><input class="form-check-input" type="checkbox" id="selectAll"><?php endif; ?>
                            </th>
                            <th class="text-center" width="5%"><i class="bi bi-arrow-down-up"></i></th>
                            <th width="30%">ชื่อหน่วยบริการ / รพ.สต.</th>
                            <th width="15%" class="text-center">ขนาด</th>
                            <th width="20%">ผู้อำนวยการ รพ.สต.</th>
                            <th width="15%">เบอร์ติดต่อ</th>
                            <th class="text-center pe-4" width="12%">จัดการ</th>
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
                            <?php foreach ($hospitals as $h): 
                                $size = strtoupper(trim($h['hospital_size'] ?? 'S'));
                                $size_class = 'size-unknown';
                                if ($size === 'S') $size_class = 'size-s';
                                elseif ($size === 'M') $size_class = 'size-m';
                                elseif ($size === 'L') $size_class = 'size-l';
                                elseif ($size === 'XL') $size_class = 'size-xl';

                                // สิทธิ์การจัดการ
                                $canEdit = $isAdmin || ($isDirector && $h['id'] == $my_hospital_id);
                            ?>
                            <tr class="hosp-row <?= ($isDirector && $h['id'] == $my_hospital_id) ? 'table-warning' : '' ?>" data-id="<?= htmlspecialchars($h['id']) ?>">
                                <td class="text-center">
                                    <?php if($isAdmin): ?>
                                        <input class="form-check-input hosp-checkbox" type="checkbox" value="<?= htmlspecialchars($h['id']) ?>">
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if($isAdmin): ?>
                                        <i class="bi bi-grip-vertical drag-handle"></i>
                                    <?php else: ?>
                                        <i class="bi bi-grip-vertical text-muted opacity-25"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="hospital-name">
                                        <?= htmlspecialchars($h['name']) ?>
                                    </div>
                                    <div class="hospital-code font-monospace mt-1">
                                        <i class="bi bi-upc-scan text-primary opacity-75 me-1"></i> <?= htmlspecialchars($h['hospital_code'] ?? 'ไม่มีรหัส') ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="size-badge <?= $size_class ?> shadow-sm mx-auto" title="ขนาด <?= $size ?>"><?= $size ?></div>
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
                                <td class="text-center pe-4 text-nowrap"><div class="rp-row-actions">
                                    <?php if($canEdit): ?>
                                        <button type="button" class="btn-action is-edit" title="เปลี่ยนชื่อ/รหัส"
                                                onclick="openEditModal('<?= htmlspecialchars($h['id']) ?>', '<?= htmlspecialchars($h['hospital_code'] ?? '') ?>', '<?= htmlspecialchars($h['name'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        
                                        <a href="index.php?c=settings&a=hospital&id=<?= urlencode($h['id']) ?>" class="btn-action is-config" title="ตั้งค่าข้อมูลพื้นฐาน/พิกัด">
                                            <i class="bi bi-gear-fill"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if($isAdmin): ?>
                                        <form action="index.php?c=hospitals&a=delete" method="POST" class="d-inline" onsubmit="return confirm('คำเตือน: ยืนยันการลบ <?= htmlspecialchars($h['name'], ENT_QUOTES) ?> ?');">
                                            <?= security_csrf_input() ?>
                                            <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                            <button type="submit" class="btn-action is-delete" title="ลบ">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    <?php elseif(!$canEdit): ?>
                                        <span class="text-muted small" title="คุณไม่มีสิทธิ์แก้ไขหน่วยบริการนี้"><i class="bi bi-lock-fill"></i> ไม่มีสิทธิ์</span>
                                    <?php endif; ?>
                                </div></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

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

<div class="modal fade" id="addHospitalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 bg-light rounded-top-4 pb-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-building-add text-primary me-2"></i> เพิ่มหน่วยบริการ (รพ.สต.)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?c=hospitals&a=add" method="POST" id="addForm">
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

<?php if($isAdmin || $isDirector): ?>
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
<?php endif; ?>

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
    var unwantedElements = tableClone.querySelectorAll('.bi, .d-none, .form-check-input, .drag-handle');
    unwantedElements.forEach(el => el.remove());

    var tableHTML = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body>' + tableClone.outerHTML + '</body></html>';
    
    filename = filename ? filename + '.xls' : 'excel_data.xls';
    
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
    document.body.removeChild(downloadLink);
}

// ฟังก์ชันโยนข้อมูลใส่ Modal แก้ไข
function openEditModal(id, code, name) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_name').value = name;
    new bootstrap.Modal(document.getElementById('editHospitalModal')).show();
}

// ฟังก์ชันลบหลายรายการ (Bulk Delete) สำหรับ Admin
function bulkDelete() {
    let selectedIds = [];
    $('.hosp-checkbox:checked').each(function() {
        selectedIds.push($(this).val());
    });

    if(selectedIds.length === 0) return;

    Swal.fire({
        title: 'ยืนยันลบหลายรายการ?',
        text: `คุณต้องการลบหน่วยบริการที่เลือกจำนวน ${selectedIds.length} รายการใช่หรือไม่? (ข้อมูลจะไม่หายไปจากฐานข้อมูล แต่จะถูกซ่อนไว้)`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ลบข้อมูล',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            let form = $('<form>', {
                'action': 'index.php?c=hospitals&a=bulk_delete',
                'method': 'POST'
            }).append($('<input>', {
                'name': 'ids',
                'value': JSON.stringify(selectedIds),
                'type': 'hidden'
            }));
            $(document.body).append(form);
            form.submit();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    
    // 🌟 1. ตั้งค่า DataTables สำหรับการค้นหา (ปิดการแบ่งหน้า Paging เพื่อให้ Sortable ลากได้อิสระ)
    var table = $('#hospitalsTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" },
        "paging": false,   // สำคัญมาก: ปิดแบ่งหน้าเพื่อให้ลากข้อมูลได้ทั้งหมด
        "ordering": false, // สำคัญมาก: ปิดเรียงลำดับอัตโนมัติ ไม่ให้ลากแล้วดีดกลับ
        "info": true,
        "dom": '<"top">rt<"bottom"i><"clear">'
    });

    // 🌟 2. เชื่อมช่องค้นหาด้านบนเข้ากับ DataTables
    $('#hospitalSearch').on('keyup', function() { table.search(this.value).draw(); });

    // 🌟 3. ระบบ Checkbox สำหรับลบหลายรายการ (แสดงปุ่มเมื่อมีการเลือก)
    $('#selectAll').change(function() {
        $('.hosp-checkbox').prop('checked', $(this).prop('checked'));
        toggleBulkBtn();
    });
    $(document).on('change', '.hosp-checkbox', function() { toggleBulkBtn(); });

    function toggleBulkBtn() {
        let count = $('.hosp-checkbox:checked').length;
        if(count > 0) {
            $('#btn-bulk-delete').removeClass('d-none').addClass('d-inline-flex');
            $('#selected-count').text(count);
        } else {
            $('#btn-bulk-delete').addClass('d-none').removeClass('d-inline-flex');
        }
    }

    // 🌟 4. ระบบ SortableJS (ลากเพื่อสลับตำแหน่งเฉพาะ Admin)
    <?php if($isAdmin): ?>
    const tbody = document.getElementById('hospitalTableBody');
    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            handle: '.drag-handle', // ลากได้เฉพาะตรงไอคอน Grip
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                // ดึงลำดับใหม่หลังจากลากเสร็จ
                const orderedRows = Array.from(tbody.querySelectorAll('.hosp-row'));
                const orderData = orderedRows.map((row, index) => {
                    return { id: row.getAttribute('data-id'), order: index + 1 };
                });

                // ส่งข้อมูลอัปเดตผ่าน AJAX
                fetch('index.php?c=hospitals&a=update_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order: orderData })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // ป็อปอัปแจ้งเตือนมุมขวาล่าง
                        const Toast = Swal.mixin({
                            toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
                        });
                        Toast.fire({ icon: 'success', title: 'อัปเดตลำดับเรียบร้อยแล้ว' });
                    } else {
                        Swal.fire('ผิดพลาด', 'ไม่สามารถบันทึกลำดับได้', 'error');
                    }
                }).catch(err => console.error(err));
            }
        });
    }
    <?php endif; ?>
});
</script>