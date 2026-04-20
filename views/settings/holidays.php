<?php
// ที่อยู่ไฟล์: views/settings/holidays.php
$year = $_GET['year'] ?? date('Y');
$holidays = $holidays ?? [];

// ฟังก์ชันแปลงวันที่เป็นรูปแบบ พ.ศ. (วว/ดด/ปปปป)
function formatDateThai($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    $yearThai = $date->format('Y') + 543;
    return $date->format('d/m/') . $yearThai;
}
?>

<!-- เพิ่ม Flatpickr CSS สำหรับ Datepicker ภาษาไทย -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    /* ปรับแต่งฟอนต์ปฏิทินให้กลืนกับระบบ */
    .flatpickr-calendar { font-family: inherit; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-calendar2-event fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">จัดการวันหยุดนักขัตฤกษ์</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">ข้อมูลอ้างอิงเพื่อใช้คำนวณสิทธิการลา และการจ่ายค่าตอบแทน OT วันหยุด</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?c=settings&a=system" class="btn btn-light border fw-bold rounded-pill shadow-sm px-4">
                <i class="bi bi-arrow-left me-1"></i> กลับ
            </a>
            <a href="index.php?c=settings&a=sync_api&year=<?= $year ?>" class="btn btn-primary fw-bold rounded-pill shadow-sm px-4" onclick="return confirm('ระบบจะทำการดึงข้อมูลจาก Server ส่วนกลาง (Data.go.th / Nager Date)\nต้องการดำเนินการต่อหรือไม่?');">
                <i class="bi bi-cloud-arrow-down me-1"></i> ซิงค์ API ปี <?= $year ?>
            </a>
        </div>
    </div>

    <!-- Alerts -->
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

    <div class="row g-4">
        <!-- ฟอร์มเพิ่มวันหยุด -->
        <div class="col-xl-4 col-lg-5">
            <div class="card card-modern h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>เพิ่มวันหยุด (Manual)</h6>
                </div>
                <div class="card-body p-4">
                    <form action="index.php?c=settings&a=save_holiday" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold" style="font-size: 13px;">วันที่ <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <!-- เปลี่ยน Input เป็น Text เพื่อให้ Flatpickr เข้ามาคุมรูปแบบไทยได้ -->
                                <input type="text" name="holiday_date" class="form-control form-control-sm rounded-3 datepicker-th bg-white" placeholder="วว/ดด/ปปปป" style="padding-right: 35px;" required>
                                <i class="bi bi-calendar3 position-absolute text-muted" style="right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;"></i>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" style="font-size: 13px;">ชื่อวันหยุด <span class="text-danger">*</span></label>
                            <input type="text" name="holiday_name" class="form-control form-control-sm rounded-3" placeholder="เช่น วันสงกรานต์" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold" style="font-size: 13px;">ประเภทวันหยุด</label>
                            <select name="holiday_type" class="form-select form-select-sm rounded-3">
                                <option value="REGULAR">🏖️ วันหยุดปกติ (Regular)</option>
                                <option value="COMPENSATION">🔄 วันหยุดชดเชย (Compensation)</option>
                                <option value="SPECIAL">🌟 วันหยุดพิเศษ/มติ ครม. (Special)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">
                            <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                        </button>
                    </form>
                    
                    <hr class="my-4 text-muted">
                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-dark" style="font-size: 12px; line-height: 1.5;">
                        <i class="bi bi-info-circle-fill text-warning me-1"></i>
                        <b>เกร็ดความรู้:</b> การเพิ่ม "วันหยุดพิเศษ" ระหว่างเดือนที่จัดเวรไปแล้ว อาจต้องให้เจ้าหน้าที่ทำการแก้ไขเรทค่าเวรให้ตรงกับเงื่อนไข OT วันหยุดด้วยตนเอง
                    </div>
                </div>
            </div>
        </div>

        <!-- รายการวันหยุด -->
        <div class="col-xl-8 col-lg-7">
            <div class="card card-modern h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul text-secondary me-2"></i>ปฏิทินวันหยุดประจำปี</h6>
                    
                    <!-- ตัวกรองปี -->
                    <form action="index.php" method="GET" class="d-flex align-items-center">
                        <input type="hidden" name="c" value="settings">
                        <input type="hidden" name="a" value="holidays">
                        <select name="year" class="form-select form-select-sm rounded-pill px-3 shadow-sm" onchange="this.form.submit()" style="width: auto;">
                            <?php 
                                $currentYear = date('Y');
                                for($i = $currentYear - 2; $i <= $currentYear + 2; $i++): 
                            ?>
                                <option value="<?= $i ?>" <?= $year == $i ? 'selected' : '' ?>>ปี พ.ศ. <?= $i + 543 ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13.5px;">
                            <thead class="table-light text-muted sticky-top" style="font-size: 12.5px;">
                                <tr>
                                    <th class="py-3 ps-4">วันที่</th>
                                    <th>ชื่อวันหยุด</th>
                                    <th>ประเภท</th>
                                    <th class="text-center">สถานะใช้งาน</th>
                                    <th class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($holidays)): ?>
                                    <tr><td colspan="5" class="py-5 text-center text-muted">ไม่พบข้อมูลวันหยุดในปี <?= $year ?> (กรุณากดซิงค์ API)</td></tr>
                                <?php else: ?>
                                    <?php foreach ($holidays as $h): ?>
                                    <tr class="<?= isset($h['is_active']) && $h['is_active'] == 0 ? 'opacity-50 text-decoration-line-through' : '' ?>">
                                        <td class="py-3 ps-4 fw-bold text-dark">
                                            <?= formatDateThai($h['holiday_date']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($h['holiday_name']) ?></td>
                                        <td>
                                            <?php 
                                                $type = $h['holiday_type'] ?? 'REGULAR';
                                                if($type == 'COMPENSATION') echo '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-arrow-repeat me-1"></i>ชดเชย</span>';
                                                elseif($type == 'SPECIAL') echo '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25"><i class="bi bi-star-fill me-1"></i>กรณีพิเศษ</span>';
                                                else echo '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><i class="bi bi-calendar-check me-1"></i>วันหยุดปกติ</span>';
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if(isset($h['is_active']) && $h['is_active'] == 1): ?>
                                                <a href="index.php?c=settings&a=toggle_holiday&id=<?= $h['id'] ?>&status=0" class="btn btn-sm btn-success rounded-pill" style="font-size: 11px;" title="กดเพื่อปิดใช้งาน">กำลังใช้งาน</a>
                                            <?php else: ?>
                                                <a href="index.php?c=settings&a=toggle_holiday&id=<?= $h['id'] ?>&status=1" class="btn btn-sm btn-secondary rounded-pill" style="font-size: 11px;" title="กดเพื่อเปิดใช้งาน">ปิดใช้งาน</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="index.php?c=settings&a=delete_holiday&id=<?= $h['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger rounded-circle" 
                                               onclick="return confirm('ยืนยันการลบวันหยุดนี้?');" title="ลบ">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- เพิ่ม Flatpickr JS และตั้งค่าภาษาไทย -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    flatpickr(".datepicker-th", {
        altInput: true,         // สร้าง Input หลอกขึ้นมาแสดงผลภาษาไทย
        altFormat: "d/m/Y",     // เราจะดักจับและเปลี่ยนเป็น พ.ศ. ตรง formatDate
        dateFormat: "Y-m-d",    // ค่าจริงที่ส่งไปให้ Backend (ปี ค.ศ.)
        locale: "th",           // ใช้ภาษาไทย
        allowInput: true,
        onChange: function(selectedDates, dateStr, instance) {
             // อัปเดตปี พ.ศ. ทุกครั้งที่มีการเลือกวัน
             updateFlatpickrYearToThai(instance);
        },
        onOpen: function(selectedDates, dateStr, instance) {
             // แปลงปี ค.ศ. เป็น พ.ศ. ตอนเปิด
             updateFlatpickrYearToThai(instance);
        },
        onMonthChange: function(selectedDates, dateStr, instance) {
            // แปลงปี ค.ศ. เป็น พ.ศ. ตอนเปลี่ยนเดือน
             updateFlatpickrYearToThai(instance);
        },
        onYearChange: function(selectedDates, dateStr, instance) {
             // แปลงปี ค.ศ. เป็น พ.ศ. ตอนเปลี่ยนปี
             updateFlatpickrYearToThai(instance);
        },
        formatDate: function(date, format, locale) {
            // ดักจับ Format เพื่อแก้ไขปี ค.ศ. เป็น ปี พ.ศ. ตอนแสดงผลใน Input
            if (format === "d/m/Y") {
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear() + 543; // บวก 543 เข้าไป
                return `${day}/${month}/${year}`;
            }
            return flatpickr.formatDate(date, format);
        }
    });

    // ฟังก์ชันสำหรับแปลงปี ค.ศ. เป็น พ.ศ. ในส่วน UI ของ Flatpickr
    function updateFlatpickrYearToThai(instance) {
        if (!instance.currentYearElement) return;

        // ห้ามให้ Trigger event onYearChange เพื่อป้องกัน Loop ไม่สิ้นสุด
        let currentYear = instance.currentYear;
        // ปรับค่าที่แสดงผลให้เป็น พ.ศ. (ค่าจริงๆ ของ instance.currentYear ยังเป็น ค.ศ. อยู่)
        instance.currentYearElement.value = currentYear + 543;

        // เช็คว่ามี Select box สำหรับเดือนและปีไหม ถ้ามีให้ปรับด้วย
        if(instance.yearElements) {
             instance.yearElements.forEach((el, index) => {
                 el.value = instance.currentYear + 543;
             });
        }
    }
});
</script>