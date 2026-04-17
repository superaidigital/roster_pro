<?php
// ที่อยู่ไฟล์: views/settings/holidays.php
// ชื่อไฟล์: holidays.php

// ฟังก์ชันสำหรับแปลงวันที่เป็นภาษาไทย (ในตาราง)
function getThaiDate($date_str) {
    if (empty($date_str)) return "-";
    $thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $time = strtotime($date_str);
    if (!$time) return "-";
    
    $d = date('j', $time);
    $m = $thai_months[date('n', $time)];
    $y = date('Y', $time) + 543;
    return "$d $m $y";
}
?>

<!-- 🌟 นำเข้า CSS ปฏิทิน (ใช้ลิงก์ jsdelivr ที่เสถียร) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">

<style>
    .cursor-pointer { cursor: pointer !important; }
    .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; }
    .page-link { color: #495057; }
    
    /* 🌟 CSS เทคนิคซ่อนเลข ค.ศ. เดิมในปฏิทิน เพื่อเอาเลข พ.ศ. มาวางทับ */
    .flatpickr-current-month .numInput.cur-year {
        color: transparent !important;
        background: transparent !important;
    }
    .flatpickr-current-month .numInput.cur-year::selection {
        background: transparent;
        color: transparent;
    }
    .thai-year-overlay {
        color: #484848; /* สีข้อความให้เข้ากับธีม airbnb */
        font-family: inherit;
        font-size: 1.15em;
    }
</style>

<div class="w-100 bg-light p-3 p-md-4 min-vh-100">
    <div class="container-fluid max-w-7xl mx-auto">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h4 class="fw-bold text-dark mb-1">จัดการวันหยุดนักขัตฤกษ์</h4>
                <p class="text-muted mb-0" style="font-size: 14px;">ตั้งค่าวันหยุด เพื่อให้ระบบไฮไลต์สีแดงในตารางจัดเวรอัตโนมัติ</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="index.php?c=settings&a=sync_api&year=<?= date('Y') ?>" class="btn btn-outline-success fw-bold shadow-sm" onclick="return confirm('ระบบจะดึงวันหยุดของปี <?= date('Y') ?> จากฐานข้อมูลสากลมาเพิ่มในระบบ\n\n(ระบบจะข้ามวันที่มีอยู่ในฐานข้อมูลแล้วเพื่อป้องกันข้อมูลซ้ำ)\n\nคุณต้องการดำเนินการต่อหรือไม่?')">
                    <i class="bi bi-cloud-download me-1"></i> ดึงวันหยุดปี <?= date('Y') ?> (API)
                </a>
                
                <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
                    <i class="bi bi-plus-lg me-1"></i> เพิ่มวันหยุดเอง
                </button>
            </div>
        </div>

        <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <strong>สำเร็จ!</strong> <?= $_SESSION['success_msg'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>ผิดพลาด!</strong> <?= $_SESSION['error_msg'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-12 col-md-5 col-lg-4">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อวันหยุด หรือ วันที่ (เช่น 13 เม.ย.)...">
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3 px-4" width="25%">วันที่</th>
                                <th class="py-3 px-4">ชื่อวันหยุด / เทศกาล</th>
                                <th class="py-3 px-4 text-center" width="20%">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="holidayTableBody">
                            <?php if(count($holidays) > 0): ?>
                                <?php foreach($holidays as $h): ?>
                                <tr class="holiday-row">
                                    <td class="px-4 fw-bold text-danger">
                                        <?= getThaiDate($h['holiday_date']) ?>
                                    </td>
                                    <td class="px-4 holiday-name">
                                        <?= htmlspecialchars($h['holiday_name']) ?>
                                    </td>
                                    <td class="px-4 text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="openEditModal(<?= $h['id'] ?>, '<?= $h['holiday_date'] ?>', '<?= htmlspecialchars(addslashes($h['holiday_name'])) ?>')">
                                            <i class="bi bi-pencil"></i> แก้ไข
                                        </button>
                                        <a href="index.php?c=settings&a=delete_holiday&id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('คุณต้องการลบวันหยุดนี้ใช่หรือไม่?')">
                                            <i class="bi bi-trash"></i> ลบ
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noDataRow">
                                    <td colspan="3" class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x fs-1 d-block mb-3 text-light"></i>
                                        ยังไม่มีข้อมูลวันหยุดในระบบ<br>
                                        <small>คุณสามารถกดปุ่ม "ดึงวันหยุดปี <?= date('Y') ?> (API)" เพื่อเพิ่มวันหยุดอัตโนมัติได้เลย</small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <tr id="noSearchResult" style="display: none;">
                                <td colspan="3" class="text-center py-5 text-muted">
                                    <i class="bi bi-search fs-2 d-block mb-3 text-light"></i>
                                    <span id="searchFeedback">ไม่พบข้อมูลที่ค้นหา</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-footer bg-white border-top py-3 flex-column flex-md-row justify-content-between align-items-center" id="paginationWrapper" style="display: none;">
                <div class="text-muted small mb-3 mb-md-0 fw-medium" id="paginationInfo">แสดงข้อมูล...</div>
                <div class="d-flex align-items-center justify-content-between w-100 w-md-auto gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <label for="rowsPerPage" class="text-muted small mb-0 d-none d-sm-block">แสดง:</label>
                        <select id="rowsPerPage" class="form-select form-select-sm text-secondary bg-light border-0 shadow-none cursor-pointer font-monospace" style="width: auto;">
                            <option value="10" selected>10 แถว</option>
                            <option value="20">20 แถว</option>
                            <option value="50">50 แถว</option>
                            <option value="all">ทั้งหมด</option>
                        </select>
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0 shadow-sm" id="paginationControls"></ul>
                    </nav>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- ================= Modal เพิ่มวันหยุดเอง ================= -->
<div class="modal fade" id="addHolidayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">เพิ่มวันหยุดใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?c=settings&a=add_holiday" method="POST">
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">วันที่หยุด</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 text-muted"><i class="bi bi-calendar-event"></i></span>
                            <input type="text" name="holiday_date" id="holiday_date" class="form-control bg-light border-0 thai-datepicker" placeholder="คลิกเพื่อเลือกวันที่..." required style="cursor: pointer; background-color: #f8f9fa !important;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">ชื่อวันหยุด</label>
                        <input type="text" name="holiday_name" class="form-control bg-light border-0" placeholder="เช่น วันสงกรานต์, วันปิยมหาราช" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">บันทึกวันหยุด</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= Modal แก้ไขวันหยุด ================= -->
<div class="modal fade" id="editHolidayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">แก้ไขวันหยุด</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?c=settings&a=edit_holiday" method="POST">
                <input type="hidden" name="id" id="edit_holiday_id">
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">วันที่หยุด</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 text-muted"><i class="bi bi-calendar-event"></i></span>
                            <input type="text" name="holiday_date" id="edit_holiday_date" class="form-control bg-light border-0 thai-datepicker" placeholder="คลิกเพื่อเลือกวันที่..." required style="cursor: pointer; background-color: #f8f9fa !important;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">ชื่อวันหยุด</label>
                        <input type="text" name="holiday_name" id="edit_holiday_name" class="form-control bg-light border-0" placeholder="เช่น วันสงกรานต์, วันปิยมหาราช" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- 🌟 นำเข้า JS ปฏิทิน (ใช้ลิงก์ jsdelivr ที่เสถียร) -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

<script>
// ฟังก์ชันโยนข้อมูลลง Modal แก้ไข
function openEditModal(id, date, name) {
    document.getElementById('edit_holiday_id').value = id;
    document.getElementById('edit_holiday_name').value = name;
    
    const dateInput = document.getElementById('edit_holiday_date');
    if (dateInput && dateInput._flatpickr) {
        dateInput._flatpickr.setDate(date);
    } else {
        dateInput.value = date;
    }

    const editModal = new bootstrap.Modal(document.getElementById('editHolidayModal'));
    editModal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    
    // 🌟 ตั้งค่า Flatpickr ภาษาไทย + แสดงผล พ.ศ. แบบแม่นยำ 100%
    if (typeof flatpickr !== 'undefined') {
        flatpickr(".thai-datepicker", {
            locale: "th",                  
            altInput: true,                
            altFormat: "j F Y", // รูปแบบช่องกรอกจะถูก override ด้วย formatDate ด้านล่าง     
            dateFormat: "Y-m-d", // ส่งค่าไป Database         
            disableMobile: true,         
            
            // 🌟 บังคับใช้ชื่อเดือนภาษาไทยและบวก พ.ศ. ทันทีเมื่อแสดงผลบนช่อง Input
            formatDate: function(date, format, locale) {
                if (format === 'j F Y') {
                    const thaiMonthsFull = [
                        "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
                        "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
                    ];
                    const d = date.getDate();
                    const m = thaiMonthsFull[date.getMonth()];
                    const y = date.getFullYear() + 543;
                    return `${d} ${m} ${y}`;
                }
                
                // ถ้ารูปแบบอื่นปล่อยให้ Flatpickr จัดการ (เช่น Y-m-d สำหรับบันทึกลง DB)
                return flatpickr.formatDate(date, format);
            },
            
            // เทคนิคสร้าง Overlay เปลี่ยน ค.ศ. เป็น พ.ศ. ด้านในตัวปฏิทิน
            onReady: function(selectedDates, dateStr, instance) {
                const yearWrapper = instance.currentYearElement.parentNode;
                yearWrapper.style.position = 'relative';
                
                const overlay = document.createElement('span');
                overlay.className = 'thai-year-overlay fw-bold';
                overlay.style.position = 'absolute';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100%';
                overlay.style.height = '100%';
                overlay.style.display = 'flex';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';
                overlay.style.pointerEvents = 'none'; 
                
                yearWrapper.appendChild(overlay);
                
                instance.updateThaiYear = function() {
                    overlay.textContent = instance.currentYear + 543;
                };
                instance.updateThaiYear();
            },
            onYearChange: function(selectedDates, dateStr, instance) {
                if(instance.updateThaiYear) instance.updateThaiYear();
            },
            onMonthChange: function(selectedDates, dateStr, instance) {
                if(instance.updateThaiYear) instance.updateThaiYear();
            }
        });
    } else {
        console.error("Flatpickr Library ไม่สามารถโหลดได้ กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต");
    }

    // ระบบค้นหาและแบ่งหน้า (Pagination)
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('holidayTableBody');
    const rows = Array.from(tableBody.querySelectorAll('.holiday-row'));
    const noSearchResult = document.getElementById('noSearchResult');
    const searchFeedback = document.getElementById('searchFeedback');
    const noDataRow = document.getElementById('noDataRow');
    
    const paginationWrapper = document.getElementById('paginationWrapper');
    const paginationInfo = document.getElementById('paginationInfo');
    const rowsPerPageSelect = document.getElementById('rowsPerPage');
    const paginationControls = document.getElementById('paginationControls');

    let currentPage = 1;
    let rowsPerPage = 10;
    let filteredRows = [...rows];

    function updateTable() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';

        filteredRows = rows.filter(row => {
            const dateText = row.cells[0].textContent.toLowerCase();
            const nameText = row.cells[1].textContent.toLowerCase();
            return dateText.includes(searchTerm) || nameText.includes(searchTerm);
        });

        const totalRows = filteredRows.length;
        const totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(totalRows / rowsPerPage);

        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = rowsPerPage === 'all' ? 0 : (currentPage - 1) * rowsPerPage;
        const endIdx = rowsPerPage === 'all' ? totalRows : startIdx + rowsPerPage;

        rows.forEach(row => row.style.display = 'none');
        filteredRows.slice(startIdx, endIdx).forEach(row => row.style.display = '');

        if (rows.length === 0) {
            if(paginationWrapper) paginationWrapper.style.display = 'none';
        } else if (totalRows === 0 && searchTerm !== '') {
            noSearchResult.style.display = '';
            searchFeedback.innerHTML = `ไม่พบข้อมูลที่ค้นหา <b>"${searchInput.value}"</b>`;
            if(paginationWrapper) paginationWrapper.style.display = 'none';
            if(noDataRow) noDataRow.style.display = 'none';
        } else {
            noSearchResult.style.display = 'none';
            if(noDataRow) noDataRow.style.display = 'none';
            
            if(paginationWrapper) {
                paginationWrapper.style.display = 'flex';
                paginationWrapper.classList.add('d-md-flex');
                renderPagination(totalRows, totalPages, startIdx, endIdx);
            }
        }
    }

    function renderPagination(totalRows, totalPages, startIdx, endIdx) {
        const actualEnd = Math.min(endIdx, totalRows);
        paginationInfo.innerHTML = `แสดง <b>${totalRows > 0 ? startIdx + 1 : 0}</b> ถึง <b>${actualEnd}</b> จากทั้งหมด <b>${totalRows}</b> รายการ`;
        
        paginationControls.innerHTML = '';
        
        if (totalPages <= 1) {
            paginationControls.style.display = 'none';
            return;
        }
        
        paginationControls.style.display = 'flex';

        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link cursor-pointer" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a>`;
        if (currentPage > 1) {
            prevLi.onclick = (e) => { e.preventDefault(); currentPage--; updateTable(); };
        }
        paginationControls.appendChild(prevLi);

        for (let i = 1; i <= totalPages; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${currentPage === i ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link cursor-pointer">${i}</a>`;
            li.onclick = (e) => { e.preventDefault(); currentPage = i; updateTable(); };
            paginationControls.appendChild(li);
        }

        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link cursor-pointer" aria-label="Next"><span aria-hidden="true">&raquo;</span></a>`;
        if (currentPage < totalPages) {
            nextLi.onclick = (e) => { e.preventDefault(); currentPage++; updateTable(); };
        }
        paginationControls.appendChild(nextLi);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() { currentPage = 1; updateTable(); });
    }

    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', function() {
            rowsPerPage = this.value === 'all' ? 'all' : parseInt(this.value);
            currentPage = 1; updateTable();
        });
    }

    updateTable();
});
</script>