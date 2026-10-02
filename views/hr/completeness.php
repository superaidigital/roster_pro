<?php
// ที่อยู่ไฟล์: views/hr/completeness.php

// 🌟 ดึงข้อมูลรายชื่อ รพ.สต. ทั้งหมดจากฐานข้อมูล เพื่อให้ Dropdown แสดงครบถ้วนเสมอ
require_once 'config/database.php';
$db_conn = (new Database())->getConnection();
$stmt_hosp = $db_conn->query("SELECT name FROM hospitals WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");
$all_hospitals = $stmt_hosp->fetchAll(PDO::FETCH_COLUMN);

$filter_hospitals = [];
foreach ($all_hospitals as $h_name) {
    // กรองเอาเฉพาะ รพ.สต. เก็บไว้ใน Array (แยกส่วนกลางออกไปต่างหาก)
    if (!empty($h_name) && mb_strpos($h_name, 'ส่วนกลาง') === false) {
        $filter_hospitals[] = $h_name;
    }
}
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .progress-custom { height: 12px; border-radius: 10px; background-color: #e2e8f0; }
    .badge-missing { font-weight: normal; font-size: 11px; padding: 4px 8px; margin-bottom: 2px; display: inline-block; background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; }
    
    /* ซ่อนช่อง Search ของ DataTables แบบเก่า */
    .dataTables_filter { display: none; } 
    
    /* 🌟 CSS สำหรับการ์ดที่กดได้ */
    .card-filter { 
        cursor: pointer; 
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
        border-width: 2px !important;
        opacity: 0.6; /* ค่าเริ่มต้นให้สีดรอปลงนิดนึง */
    }
    .card-filter:hover { 
        transform: translateY(-4px); 
        opacity: 0.9;
        box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important;
    }
    .card-filter.active { 
        opacity: 1; 
        background-color: #ffffff !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important;
    }
    .card-filter.active::after {
        content: '\F26A'; /* ไอคอน Check (Bootstrap Icons) */
        font-family: 'bootstrap-icons';
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 1.2rem;
        opacity: 0.5;
    }
    #card-all.active::after { color: #0d6efd; }
    #card-perfect.active::after { color: #198754; }
    #card-incomplete.active::after { color: #dc3545; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-clipboard2-data-fill fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">ตรวจสอบความสมบูรณ์ของประวัติ (Profile Completeness)</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">ตรวจสอบและติดตามบุคลากรที่กรอกข้อมูลสำคัญยังไม่ครบถ้วน (คลิกที่การ์ดเพื่อกรองข้อมูล)</p>
            </div>
        </div>
    </div>

    <!-- 🌟 สรุปภาพรวม (กดเพื่อกรองข้อมูลได้) 🌟 -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div id="card-all" class="card card-filter border-primary shadow-sm rounded-4 p-3 active" onclick="filterTable('all')">
                <div class="text-muted small fw-bold mb-1">ความสมบูรณ์เฉลี่ย (แสดงทั้งหมด)</div>
                <div class="d-flex align-items-end gap-2">
                    <h2 class="fw-black mb-0 text-primary"><?= $avg_score ?>%</h2>
                </div>
                <div class="progress progress-custom mt-2">
                    <div class="progress-bar bg-primary" style="width: <?= $avg_score ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div id="card-perfect" class="card card-filter border-success shadow-sm rounded-4 p-3 bg-light" onclick="filterTable('perfect')">
                <div class="text-muted small fw-bold mb-1">สมบูรณ์ 100% แล้ว</div>
                <div class="d-flex align-items-end gap-2">
                    <h2 class="fw-black mb-0 text-success"><?= number_format($perfect_staff) ?></h2>
                    <span class="text-muted mb-1">จาก <?= number_format($total_staff) ?> คน</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div id="card-incomplete" class="card card-filter border-danger shadow-sm rounded-4 p-3 bg-light" onclick="filterTable('incomplete')">
                <div class="text-muted small fw-bold mb-1">ต้องติดตามแก้ไข (< 100%)</div>
                <div class="d-flex align-items-end gap-2">
                    <h2 class="fw-black mb-0 text-danger"><?= number_format($total_staff - $perfect_staff) ?></h2>
                    <span class="text-muted mb-1">คน</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 ระบบค้นหาและตัวกรองเพิ่มเติม (Filter & Search) 🌟 -->
    <div class="card card-modern mb-4 border-0 shadow-sm rounded-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group shadow-sm rounded-3">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อ-สกุล, เบอร์โทร, ตำแหน่ง, หน่วยบริการ...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select id="filterHospital" class="form-select shadow-sm rounded-3">
                        <option value="">-- ทุกหน่วยบริการ --</option>
                        <option value="ส่วนกลาง">🏢 ส่วนกลาง</option>
                        <?php foreach($filter_hospitals as $h): ?>
                            <option value="<?= htmlspecialchars($h) ?>">🏥 <?= htmlspecialchars($h) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-light border w-100 rounded-3 shadow-sm fw-bold text-secondary" onclick="clearFilters()" title="ล้างตัวกรอง">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> ล้างตัวกรองทั้งหมด
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ตารางข้อมูล -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="completenessTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th width="20%">ชื่อบุคลากร</th>
                            <th width="20%">ตำแหน่ง/หน่วยงาน/หน่วยบริการ</th>
                            <th width="20%">ความสมบูรณ์ (%)</th>
                            <th width="30%">ข้อมูลที่ยังขาดหาย (ต้องแก้ไข)</th>
                            <th width="10%" class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($staff_data as $s): 
                            $score = $s['completeness_score'];
                            $pg_color = 'bg-danger';
                            if($score >= 50) $pg_color = 'bg-warning';
                            if($score >= 80) $pg_color = 'bg-primary';
                            if($score == 100) $pg_color = 'bg-success';
                            
                            $msg_text = "สวัสดีครับคุณ {$s['name']} รบกวนเข้าระบบ Roster Pro เพื่ออัปเดตข้อมูลประวัติส่วนตัวให้สมบูรณ์ด้วยครับ (ข้อมูลที่ขาด: " . implode(', ', $s['missing_items']) . ")";
                            
                            // 🌟 ตรวจสอบค่าว่าง (empty) แทนที่จะใช้แค่ ?? เพื่อความชัวร์
                            $hosp_name = !empty($s['hosp_name']) ? htmlspecialchars($s['hosp_name']) : 'ส่วนกลาง';
                        ?>
                            <!-- ฝัง data-hospital ไว้เพื่อให้ DataTables นำไปกรองได้ -->
                            <tr data-hospital="<?= $hosp_name ?>">
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($s['name']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($s['phone'] ?: 'ไม่มีเบอร์') ?></div>
                                </td>
                                
                                <!-- 🌟 แสดง ตำแหน่ง/หน่วยงาน/หน่วยบริการ ให้ชัดเจน 🌟 -->
                                <td class="small">
                                    <div class="fw-bold text-secondary" style="font-size: 13px;" title="ตำแหน่ง">
                                        <?= htmlspecialchars($s['position'] ?: 'ไม่ระบุตำแหน่ง') ?>
                                    </div>
                                    <!-- ปรับสไตล์หน่วยบริการให้อยู่ในกรอบ Badge ตามที่ต้องการ -->
                                    <div class="mt-2" title="หน่วยบริการ/สังกัด">
                                        <span class="badge bg-light text-dark border px-2 py-1 fw-normal" style="font-size: 11px;">
                                            <i class="bi bi-hospital me-1 text-secondary"></i><?= $hosp_name ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <!-- ตัวเลขเปอร์เซ็นต์นี้จะถูกอ่านโดย DataTables สำหรับกรองด้วย Clickable Card -->
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span class="fw-bold text-dark score-value"><?= $score ?>%</span>
                                    </div>
                                    <div class="progress progress-custom">
                                        <div class="progress-bar <?= $pg_color ?>" style="width: <?= $score ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($score == 100): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>ข้อมูลครบถ้วน</span>
                                    <?php else: ?>
                                        <?php foreach($s['missing_items'] as $item): ?>
                                            <span class="badge-missing rounded-pill"><i class="bi bi-x me-1"></i><?= $item ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="index.php?c=profile&id=<?= $s['user_id'] ?>" class="btn btn-sm btn-outline-primary rounded-circle" title="ดู/แก้ไขประวัติ">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <?php if($score < 100): ?>
                                        <button class="btn btn-sm btn-outline-success rounded-circle" onclick="copyToClipboard('<?= htmlspecialchars($msg_text, ENT_QUOTES) ?>')" title="คัดลอกข้อความทวงถาม">
                                            <i class="bi bi-line"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// ตัวแปรเก็บสถานะการกรองปัจจุบันของการ์ด (Card Filter)
let currentCardFilter = 'all';
let dataTable;

$(document).ready(function() {
    dataTable = $('#completenessTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json" },
        "order": [[2, "asc"]], // เรียงคนที่คะแนนน้อยสุดขึ้นก่อนเป็นค่าเริ่มต้น
        "dom": '<"top">rt<"bottom"lip><"clear">' // กำหนดให้ซ่อน Search เดิม และโชว์ Pagination ไว้ด้านล่าง
    });

    // 🌟 สร้างฟังก์ชันกรองข้อมูลตารางแบบ Custom ผสมกันระหว่าง Card, Search, และ Dropdown
    $.fn.dataTable.ext.search.push(
        function( settings, data, dataIndex ) {
            var rowNode = settings.aoData[dataIndex].nTr; 
            if (!rowNode) return true; 

            // 1. กรองด้วยหน่วยบริการ (Dropdown)
            var rowHosp = rowNode.getAttribute('data-hospital') || "";
            var filterHosp = $('#filterHospital').val();
            
            // 🌟 แก้ไข: ใช้ includes เพื่อให้ครอบคลุมคำว่า "ส่วนกลาง" หรือกรณีมีข้อความอื่นปน
            if (filterHosp && !rowHosp.includes(filterHosp)) return false;

            // 2. กรองด้วยสถานะความสมบูรณ์ (Clickable Cards)
            let scoreText = data[2] || "0"; // ดึงข้อความจากคอลัมน์ความสมบูรณ์
            let score = parseInt(scoreText.replace(/[^0-9]/g, ''), 10); // แปลง "100%" เป็นเลข 100

            if (currentCardFilter === 'perfect' && score !== 100) return false;
            if (currentCardFilter === 'incomplete' && score === 100) return false;
            
            return true; // ผ่านเงื่อนไขทั้งหมดให้แสดงผล
        }
    );

    // ตรวจจับเมื่อพิมพ์ค้นหาช่อง Search Input
    $('#searchInput').on('keyup', function() { 
        dataTable.search(this.value).draw(); 
    });

    // ตรวจจับเมื่อเปลี่ยนค่าใน Dropdown หน่วยบริการ
    $('#filterHospital').on('change', function() { 
        dataTable.draw(); 
    });
});

// 🌟 ฟังก์ชันทำงานเมื่อคลิกที่การ์ดสถิติ (สถานะความสมบูรณ์)
function filterTable(filterType) {
    currentCardFilter = filterType;
    
    // สั่งตารางให้วาดใหม่ (จะไปเรียกฟังก์ชัน custom search ด้านบนอัตโนมัติ)
    dataTable.draw();
    
    // เปลี่ยนสไตล์ UI ของการ์ดที่ถูกเลือก
    $('.card-filter').removeClass('active bg-white').addClass('bg-light');
    $('#card-' + filterType).addClass('active bg-white').removeClass('bg-light');
}

// 🌟 ฟังก์ชันล้างตัวกรองทั้งหมด (ให้กลับเป็นค่าตั้งต้น)
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterHospital').value = '';
    
    // รีเซ็ตสถานะการ์ดให้กลับไปที่ 'ทั้งหมด'
    filterTable('all'); 
    
    // รีเซ็ตข้อความค้นหาหลักของ DataTables
    dataTable.search('').draw();
}

// ฟังก์ชันสำหรับคัดลอกข้อความ
function copyToClipboard(text) {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    
    Swal.fire({
        icon: 'success',
        title: 'คัดลอกสำเร็จ',
        text: 'คัดลอกข้อความแจ้งเตือนแล้ว สามารถนำไปวางในแชท LINE ได้ทันที',
        timer: 2000,
        showConfirmButton: false
    });
}
</script>