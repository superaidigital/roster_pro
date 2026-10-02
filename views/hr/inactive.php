<?php
// ที่อยู่ไฟล์: views/hr/inactive.php

// สกัดรายชื่อหน่วยบริการและสาเหตุที่ไม่ซ้ำกัน เพื่อนำไปสร้าง Dropdown ตัวกรองอัตโนมัติ
$filter_hospitals = [];
$filter_reasons = [];

if (!empty($inactive_staff)) {
    foreach ($inactive_staff as $u) {
        $hosp_name = $u['hospital_name'] ?? 'ส่วนกลาง';
        $reason = $u['inactive_reason'] ?? 'ระงับบัญชี';
        
        if (!in_array($hosp_name, $filter_hospitals)) {
            $filter_hospitals[] = $hosp_name;
        }
        if (!in_array($reason, $filter_reasons)) {
            $filter_reasons[] = $reason;
        }
    }
    sort($filter_hospitals);
    sort($filter_reasons);
}
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .dataTables_filter { display: none; } /* ซ่อนช่อง Search ของ DataTables แบบเก่า */
    .table-modern th { font-weight: 600; color: #475569; font-size: 13px; background-color: #f8fafc; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding: 1rem; }
    .table-modern td { vertical-align: middle; font-size: 14px; border-bottom: 1px solid #f1f5f9; padding: 1rem; background-color: #ffffff; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-person-x-fill fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">ทำเนียบผู้พ้นสภาพ (Inactive/Alumni)</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">รายชื่อบุคลากรที่เกษียณอายุ ลาออก หรือถูกระงับบัญชี</p>
            </div>
        </div>
    </div>

    <!-- 🌟 ระบบค้นหาและตัวกรอง (Filter & Search) 🌟 -->
    <div class="card card-modern mb-4 border-0 shadow-sm rounded-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group shadow-sm rounded-3">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อ-สกุล, ตำแหน่ง...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filterHospital" class="form-select shadow-sm rounded-3">
                        <option value="">-- ทุกหน่วยบริการ --</option>
                        <?php foreach($filter_hospitals as $h): ?>
                            <option value="<?= htmlspecialchars($h) ?>">🏥 <?= htmlspecialchars($h) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filterReason" class="form-select shadow-sm rounded-3">
                        <option value="">-- ทุกสาเหตุการพ้นสภาพ --</option>
                        <?php foreach($filter_reasons as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-light border w-100 rounded-3 shadow-sm fw-bold text-secondary" onclick="clearFilters()" title="ล้างตัวกรอง">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> ล้างตัวกรอง
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 ตารางข้อมูล -->
    <div class="card card-modern border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="inactiveTable" class="table table-modern table-hover align-middle mb-0" style="width:100%">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th>ชื่อ - นามสกุล</th>
                            <th>ตำแหน่งสุดท้าย</th>
                            <th>สาเหตุการพ้นสภาพ</th>
                            <th>วันที่มีผล</th>
                            <th>หมายเหตุ</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($inactive_staff)): foreach($inactive_staff as $u): 
                            $hosp_name = htmlspecialchars($u['hospital_name'] ?? 'ส่วนกลาง');
                            $reason = htmlspecialchars($u['inactive_reason'] ?? 'ระงับบัญชี');
                        ?>
                            <tr data-hospital="<?= $hosp_name ?>" data-reason="<?= $reason ?>">
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($u['name']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-hospital me-1"></i><?= $hosp_name ?></div>
                                </td>
                                <td><?= htmlspecialchars($u['position']) ?></td>
                                <td>
                                    <?php
                                        $badge = 'bg-secondary';
                                        if(strpos($reason, 'เกษียณ') !== false) $badge = 'bg-info text-dark';
                                        if(strpos($reason, 'ลาออก') !== false) $badge = 'bg-warning text-dark';
                                        if(strpos($reason, 'เสียชีวิต') !== false) $badge = 'bg-dark';
                                    ?>
                                    <span class="badge <?= $badge ?> px-3 py-2 rounded-pill shadow-sm"><?= $reason ?></span>
                                </td>
                                <td class="font-monospace text-primary fw-medium">
                                    <?= !empty($u['inactive_date']) ? date('d/m/', strtotime($u['inactive_date'])) . (date('Y', strtotime($u['inactive_date'])) + 543) : '-' ?>
                                </td>
                                <td class="small text-muted" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($u['inactive_note'] ?? '-') ?>">
                                    <?= htmlspecialchars($u['inactive_note'] ?? '-') ?>
                                </td>
                                <td class="text-center">
                                    <a href="index.php?c=profile&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary rounded-circle shadow-sm" title="ดูแฟ้มประวัติ">
                                        <i class="bi bi-file-earmark-person"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var table = $('#inactiveTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" },
        "order": [[3, "desc"]], // เรียงตามวันที่พ้นสภาพล่าสุด
        "dom": '<"top">rt<"bottom"lip><"clear">' // กำหนดให้ซ่อน Search เดิม และโชว์ Pagination ไว้ด้านล่าง
    });

    // 🌟 สร้างเงื่อนไขการค้นหาเพิ่มเติมของ DataTables ให้กรองตาม Dropdown ได้
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var filterHosp = $('#filterHospital').val();
        var filterReason = $('#filterReason').val();
        
        var rowNode = settings.aoData[dataIndex].nTr; 
        if (!rowNode) return true; 

        var rowHosp = rowNode.getAttribute('data-hospital') || "";
        var rowReason = rowNode.getAttribute('data-reason') || "";

        if (filterHosp && filterHosp !== rowHosp) return false;
        if (filterReason && filterReason !== rowReason) return false;
        
        return true;
    });

    // ตรวจจับเมื่อพิมพ์ค้นหา และอัปเดตตาราง
    $('#searchInput').on('keyup', function() { 
        table.search(this.value).draw(); 
    });

    // ตรวจจับเมื่อเปลี่ยนค่า Dropdown
    $('#filterHospital, #filterReason').on('change', function() { 
        table.draw(); 
    });
});

// ฟังก์ชันล้างตัวกรองทั้งหมดกลับเป็นค่าเริ่มต้น
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterHospital').value = '';
    document.getElementById('filterReason').value = '';
    $('#inactiveTable').DataTable().search('').draw();
}
</script>