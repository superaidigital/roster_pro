<?php
// ที่อยู่ไฟล์: views/leave/report.php
// ตรวจสอบว่ามีข้อมูลประเภทการลาหรือไม่ ป้องกัน Error
$leave_types = $leave_types ?? [];
$report_data = $report_data ?? [];
?>
<div class="container-fluid px-4 py-4">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="h3 text-gray-800 mb-0 fw-bold">
                <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> รายงานสรุปวันลาประจำปี
            </h2>
            <p class="text-muted mt-1 mb-0">ข้อมูลโควตาวันลาและการใช้สิทธิ์ ปีงบประมาณ <?= $budget_year + 543 ?></p>
        </div>
        
        <!-- 🌟 ฟอร์มเลือกปีงบประมาณ -->
        <form action="index.php" method="GET" class="d-flex gap-2">
            <input type="hidden" name="c" value="leave">
            <input type="hidden" name="a" value="report">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar"></i></span>
                <select name="year" class="form-select border-start-0 fw-bold text-primary" onchange="this.form.submit()" style="min-width: 150px;">
                    <?php 
                        // สร้างตัวเลือกปีงบประมาณ (ย้อนหลัง 2 ปี และล่วงหน้า 1 ปี)
                        $current_y = (int)date('Y') + ((int)date('m') >= 10 ? 1 : 0);
                        for ($y = $current_y - 2; $y <= $current_y + 1; $y++) {
                            $selected = ($y == $budget_year) ? 'selected' : '';
                            echo "<option value=\"$y\" $selected>ปีงบประมาณ " . ($y + 543) . "</option>";
                        }
                    ?>
                </select>
            </div>
            
            <button type="button" onclick="exportTableToExcel('leaveReportTable', 'รายงานสรุปวันลา_ปี<?= $budget_year + 543 ?>')" class="btn btn-success fw-bold shadow-sm d-flex align-items-center text-nowrap">
                <i class="bi bi-file-earmark-excel-fill me-2"></i> ส่งออก Excel
            </button>
        </form>
    </div>

    <!-- 🌟 ตารางรายงานที่ดึงข้อมูลจาก Database -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive custom-scrollbar" style="max-height: 65vh;">
                <table class="table table-bordered table-hover align-middle mb-0 text-center" id="leaveReportTable" style="min-width: 1000px;">
                    <thead class="align-middle sticky-top" style="z-index: 10;">
                        <tr>
                            <th rowspan="2" class="px-4 text-start shadow-sm text-nowrap" style="position: sticky; left: 0; background-color: #f8f9fa; z-index: 12; min-width: 220px; border-bottom: 1px solid #dee2e6;">รายชื่อบุคลากร</th>
                            <th rowspan="2" class="px-3 text-nowrap bg-light border-bottom" style="min-width: 180px;">ประเภทพนักงาน</th>
                            
                            <?php if(empty($leave_types)): ?>
                                <th class="bg-light text-muted py-3 border-bottom-0">ยังไม่มีการตั้งค่าประเภทการลา</th>
                            <?php else: ?>
                                <!-- สร้างหัวตาราง (ประเภทการลา) แบบไดนามิกจากฐานข้อมูล -->
                                <?php foreach($leave_types as $lt): 
                                    $bg = 'secondary';
                                    if($lt['leave_type'] == 'ลาพักผ่อน') $bg = 'success';
                                    elseif($lt['leave_type'] == 'ลาป่วย') $bg = 'danger';
                                    elseif($lt['leave_type'] == 'ลากิจส่วนตัว') $bg = 'warning';
                                ?>
                                    <th colspan="3" class="bg-<?= $bg ?> bg-opacity-10 text-dark fw-bold py-3 text-nowrap border-bottom-0">
                                        <?= htmlspecialchars($lt['leave_type']) ?>
                                    </th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                        <tr>
                            <?php if(!empty($leave_types)): ?>
                                <!-- สร้างหัวตารางย่อย (สิทธิ์/ใช้/เหลือ) -->
                                <?php foreach($leave_types as $lt): ?>
                                    <th class="text-muted bg-light text-nowrap border-bottom" style="font-size: 13px;">ได้สิทธิ์</th>
                                    <th class="text-danger bg-light text-nowrap border-bottom" style="font-size: 13px;">ใช้ไป</th>
                                    <th class="text-success bg-light text-nowrap border-bottom" style="font-size: 13px;">คงเหลือ</th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="<?= empty($leave_types) ? 3 : (count($leave_types) * 3) + 2 ?>" class="text-center text-muted py-5">
                                    <i class="bi bi-folder2-open fs-1 d-block mb-3 opacity-25"></i> ไม่พบข้อมูลพนักงานในหน่วยบริการนี้
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($report_data as $row): 
                                $staff = $row['staff']; 
                                $bals = $row['balances']; 
                            ?>
                                <tr>
                                    <!-- ชื่อพนักงาน ล็อคไว้ทางซ้าย (Sticky Column) -->
                                    <td class="text-start px-4 fw-bold text-dark shadow-sm text-nowrap" style="position: sticky; left: 0; background-color: #ffffff; z-index: 1;">
                                        <?= htmlspecialchars($staff['name']) ?>
                                    </td>
                                    <td style="font-size: 12px;" class="text-muted text-nowrap bg-white"><?= htmlspecialchars($staff['employee_type'] ?? '-') ?></td>
                                    
                                    <?php if(empty($leave_types)): ?>
                                        <td class="text-muted">-</td>
                                    <?php else: ?>
                                        <!-- ดึงยอดวันลาคงเหลือจากตาราง leave_balances มาแสดง -->
                                        <?php foreach($leave_types as $lt): 
                                            $bal = null;
                                            foreach($bals as $b) {
                                                if ($b['leave_type_id'] == $lt['id']) {
                                                    $bal = $b; break;
                                                }
                                            }
                                        ?>
                                            <?php if($bal): ?>
                                                <td class="bg-light fw-medium text-secondary"><?= floatval($bal['total_allowable']) ?></td>
                                                <td class="text-danger fw-bold <?= floatval($bal['used_days']) > 0 ? 'bg-danger bg-opacity-10' : '' ?>"><?= floatval($bal['used_days']) ?></td>
                                                <td class="text-success fw-bold fs-6"><?= floatval($bal['remaining']) ?></td>
                                            <?php else: ?>
                                                <td class="bg-light text-muted">-</td>
                                                <td class="text-muted">-</td>
                                                <td class="text-muted">-</td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top py-3 text-muted" style="font-size: 13px;">
            <i class="bi bi-info-circle text-primary me-1"></i> <strong>คำแนะนำ:</strong> เมื่อกดส่งออก Excel ระบบจะแปลงตารางนี้ให้อยู่ในรูปแบบไฟล์ .xls ที่พร้อมสำหรับการปริ้นท์และนำไปใช้งานต่อได้ทันที
        </div>
    </div>
</div>

<style>
    /* ปรับแต่ง Scrollbar ของตารางให้ดูสะอาดตา */
    .custom-scrollbar::-webkit-scrollbar { width: 8px; height: 8px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
</style>

<script>
// ฟังก์ชันส่งออกตารางเป็น Excel
function exportTableToExcel(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel;charset=utf-8';
    var tableSelect = document.getElementById(tableID);
    
    // โคลนตารางเพื่อไม่ให้กระทบ UI เดิม
    var tableClone = tableSelect.cloneNode(true);
    
    // ลบ CSS Classes ที่ทำให้ Excel เพี้ยน และตีเส้นขอบตาราง
    var cells = tableClone.querySelectorAll('td, th');
    cells.forEach(function(cell) {
        var text = cell.innerText || cell.textContent;
        cell.innerHTML = text.trim();
        cell.style.border = "1px solid black"; 
        cell.style.textAlign = "center";
        cell.style.verticalAlign = "middle";
    });

    // กำหนดโครงสร้าง HTML สำหรับ Excel (เพิ่ม \ufeff เพื่อรองรับภาษาไทย 100%)
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
</script>