<?php
// ที่อยู่ไฟล์: views/logs/index.php

// ป้องกันตัวแปรว่าง
$search_keyword = $search_keyword ?? '';
$action_filter = $action_filter ?? '';
$date_filter = $date_filter ?? '';
$logs = $logs ?? [];
$current_page = $current_page ?? 1;
$total_pages = $total_pages ?? 1;

// ฟังก์ชันสร้าง URL สำหรับ Pagination โดยคงค่าตัวกรองเดิมไว้
if (!function_exists('buildPaginationUrl')) {
    function buildPaginationUrl($page, $search, $action, $date) {
        $params = ['c' => 'logs', 'a' => 'index', 'page' => $page];
        if (!empty($search)) $params['search'] = $search;
        if (!empty($action)) $params['action'] = $action;
        if (!empty($date)) $params['date'] = $date;
        return 'index.php?' . http_build_query($params);
    }
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .table-modern th { font-weight: 600; color: #475569; font-size: 13px; background-color: #f8fafc; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
    .table-modern td { vertical-align: middle; font-size: 14px; border-bottom: 1px solid #f1f5f9; padding: 1rem 0.75rem; background-color: #ffffff; }
    .table-modern tbody tr { transition: background-color 0.2s; }
    .table-modern tbody tr:hover td { background-color: #f8fafc; }
    
    .badge-action { min-width: 85px; font-weight: bold; letter-spacing: 0.5px; font-size: 11.5px; }
    .action-create { background-color: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
    .action-update { background-color: #e0e7ff; color: #4f46e5; border: 1px solid #c7d2fe; }
    .action-delete { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .action-login { background-color: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
    .action-download { background-color: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
    .action-default { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
</style>

<div class="container-fluid px-3 px-md-4 py-4 d-flex flex-column" style="min-height: calc(100vh - 70px);">
    
    <!-- 🌟 Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-journal-code fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">บันทึกประวัติการใช้งาน (System Logs)</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">ติดตามและตรวจสอบการเปลี่ยนแปลงข้อมูลในระบบ (Audit Trail)</p>
            </div>
        </div>
    </div>

    <!-- 🌟 Alerts -->
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert border-0 bg-danger bg-opacity-10 text-danger rounded-4 d-flex align-items-center mb-4 p-3 shadow-sm border-start border-danger border-4">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-3"></i> 
            <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['error_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <!-- 🌟 ตัวกรองการค้นหา (Filters) -->
    <div class="card card-modern mb-4">
        <div class="card-body p-4">
            <form action="index.php" method="GET" class="row g-3 align-items-end">
                <!-- ซ่อนค่า c และ a เพื่อให้ Router ทำงานได้ถูกต้อง -->
                <input type="hidden" name="c" value="logs">
                <input type="hidden" name="a" value="index">
                
                <div class="col-md-4 col-lg-4">
                    <label class="form-label fw-bold small text-muted">ค้นหา (ชื่อผู้ใช้, รายละเอียด)</label>
                    <div class="input-group shadow-sm rounded-3 overflow-hidden border">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-0" placeholder="พิมพ์คำค้นหา..." value="<?= htmlspecialchars($search_keyword) ?>">
                    </div>
                </div>
                
                <div class="col-md-3 col-lg-3">
                    <label class="form-label fw-bold small text-muted">ประเภทกิจกรรม (Action)</label>
                    <select name="action" class="form-select shadow-sm rounded-3 border">
                        <option value="">-- ทั้งหมด --</option>
                        <option value="LOGIN" <?= $action_filter === 'LOGIN' ? 'selected' : '' ?>>LOGIN (เข้าสู่ระบบ)</option>
                        <option value="CREATE" <?= $action_filter === 'CREATE' ? 'selected' : '' ?>>CREATE (เพิ่มข้อมูล)</option>
                        <option value="UPDATE" <?= $action_filter === 'UPDATE' ? 'selected' : '' ?>>UPDATE (แก้ไขข้อมูล)</option>
                        <option value="DELETE" <?= $action_filter === 'DELETE' ? 'selected' : '' ?>>DELETE (ลบข้อมูล)</option>
                        <option value="DOWNLOAD" <?= $action_filter === 'DOWNLOAD' ? 'selected' : '' ?>>DOWNLOAD (ดาวน์โหลด)</option>
                    </select>
                </div>
                
                <div class="col-md-3 col-lg-3">
                    <label class="form-label fw-bold small text-muted">วันที่ดำเนินการ</label>
                    <div class="input-group shadow-sm rounded-3 overflow-hidden border">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-calendar3 text-muted"></i></span>
                        <input type="text" name="date" class="form-control border-0 flatpickr-date" placeholder="เลือกวันที่..." value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                </div>
                
                <div class="col-md-2 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1 fw-bold rounded-3 shadow-sm"><i class="bi bi-funnel-fill me-1"></i> กรอง</button>
                    <a href="index.php?c=logs" class="btn btn-light border text-secondary rounded-3 shadow-sm" title="ล้างตัวกรอง"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- 🌟 ตารางแสดงประวัติ -->
    <div class="card card-modern flex-grow-1 overflow-hidden d-flex flex-column mb-3">
        <div class="card-body p-0 d-flex flex-column flex-grow-1">
            <div class="table-responsive custom-scrollbar flex-grow-1">
                <table class="table table-modern mb-0 align-middle">
                    <thead class="sticky-top" style="z-index: 10;">
                        <tr>
                            <th class="ps-4" style="width: 15%;">วัน - เวลา</th>
                            <th style="width: 20%;">ผู้ดำเนินการ</th>
                            <th class="text-center" style="width: 12%;">กิจกรรม</th>
                            <th style="width: 38%;">รายละเอียด (Details)</th>
                            <th style="width: 15%;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="bi bi-journal-x fs-1 text-secondary opacity-50"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">ไม่พบประวัติการใช้งาน</h6>
                                    <p class="text-muted small mb-0">ยังไม่มีข้อมูลในระบบ หรือลองเปลี่ยนเงื่อนไขการค้นหาใหม่</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): 
                                // จัดการรูปแบบวันที่
                                $date_obj = new DateTime($log['created_at']);
                                $formatted_date = $date_obj->format('d/m/Y');
                                $formatted_time = $date_obj->format('H:i:s');
                                
                                // จัดการสีของ Badge ตามประเภทกิจกรรม
                                $action = strtoupper($log['action']);
                                $badge_class = 'action-default';
                                if ($action === 'CREATE') $badge_class = 'action-create';
                                elseif ($action === 'UPDATE') $badge_class = 'action-update';
                                elseif ($action === 'DELETE') $badge_class = 'action-delete';
                                elseif ($action === 'LOGIN') $badge_class = 'action-login';
                                elseif ($action === 'DOWNLOAD') $badge_class = 'action-download';
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark" style="font-size: 13px;"><?= $formatted_date ?></div>
                                    <div class="text-muted small"><i class="bi bi-clock me-1"></i><?= $formatted_time ?> น.</div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-2 fw-bold" style="width: 32px; height: 32px; font-size: 14px;">
                                            <?= mb_substr($log['user_name'] ?? 'S', 0, 1, 'UTF-8') ?>
                                        </div>
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 13.5px; max-width: 150px;" title="<?= htmlspecialchars($log['user_name'] ?? 'ระบบ (System)') ?>">
                                            <?= htmlspecialchars($log['user_name'] ?? 'ระบบ (System)') ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill <?= $badge_class ?> badge-action shadow-sm py-2 px-3"><?= htmlspecialchars($action) ?></span>
                                </td>
                                <td>
                                    <div class="text-dark" style="font-size: 13.5px; line-height: 1.4;">
                                        <?= htmlspecialchars($log['details']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted font-monospace small bg-light px-2 py-1 rounded border"><i class="bi bi-globe me-1 opacity-50"></i> <?= htmlspecialchars($log['ip_address'] ?? '-') ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 🌟 ระบบแบ่งหน้า (Pagination) -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div class="text-muted small fw-medium">
                หน้า <?= $current_page ?> จากทั้งหมด <?= $total_pages ?> หน้า
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0 shadow-sm">
                    <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= buildPaginationUrl($current_page - 1, $search_keyword, $action_filter, $date_filter) ?>" tabindex="-1"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    
                    <?php 
                    // แสดงเลขหน้าแบบจำกัด (Window) เพื่อไม่ให้ยาวเกินไป
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="'.buildPaginationUrl(1, $search_keyword, $action_filter, $date_filter).'">1</a></li>';
                        if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= buildPaginationUrl($i, $search_keyword, $action_filter, $date_filter) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="'.buildPaginationUrl($total_pages, $search_keyword, $action_filter, $date_filter).'">'.$total_pages.'</a></li>';
                    }
                    ?>
                    
                    <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= buildPaginationUrl($current_page + 1, $search_keyword, $action_filter, $date_filter) ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 🌟 Script สำหรับ ปฏิทิน Datepicker (รองรับปี พ.ศ.) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const updateThaiYear = function(instance) {
        if (!instance.currentYearElement) return;
        setTimeout(() => {
            instance.currentYearElement.value = instance.currentYear + 543;
        }, 10);
    };

    flatpickr(".flatpickr-date", { 
        locale: "th", 
        altInput: true, 
        altFormat: "j M Y", // ตัวย่อเดือน (เช่น 15 มี.ค. 2569)
        dateFormat: "Y-m-d", // ส่งค่าเป็น ค.ศ. ให้ระบบค้นหา
        allowInput: true,
        onChange: function(selectedDates, dateStr, instance) { updateThaiYear(instance); },
        onReady: function(selectedDates, dateStr, instance) {
            updateThaiYear(instance);
            instance.currentYearElement.addEventListener('change', function() {
                let thaiYear = parseInt(this.value);
                if (thaiYear > 2400) instance.changeYear(thaiYear - 543);
            });
        },
        onYearChange: function(selectedDates, dateStr, instance) { updateThaiYear(instance); },
        onMonthChange: function(selectedDates, dateStr, instance) { updateThaiYear(instance); },
        onOpen: function(selectedDates, dateStr, instance) { updateThaiYear(instance); },
        onValueUpdate: function(selectedDates, dateStr, instance) { updateThaiYear(instance); },
        formatDate: function(date, format, locale) {
            // ดักจับการ Format ค่าหน้าบ้าน ให้ปี + 543 เสมอ
            if (format === "j M Y") {
                const d = date.getDate();
                const m = locale.months.shorthand[date.getMonth()];
                const y = date.getFullYear() + 543; 
                return `${d} ${m} ${y}`;
            }
            return flatpickr.formatDate(date, format);
        }
    });
});
</script>