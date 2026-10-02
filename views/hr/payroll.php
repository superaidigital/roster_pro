<?php
// ที่อยู่ไฟล์: views/hr/payroll.php

$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$exp = explode('-', $selected_month);
$display_month = $thai_months[(int)$exp[1]] . ' ' . ($exp[0] + 543);

// ดึงค่า ปี และ เดือน ปัจจุบันแยกกัน เพื่อนำไปสร้าง Dropdown
$current_y = (int)$exp[0];
$current_m = $exp[1];
?>
<div class="container-fluid px-3 px-md-4 py-4">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-cash-stack fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">รายงานค่าตอบแทนการปฏิบัติงาน</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">สรุปยอดเงินค่าเวรสำหรับส่งเบิกจ่ายให้ฝ่ายการเงิน</p>
            </div>
        </div>
        
        <form method="GET" action="index.php" class="d-flex flex-wrap gap-2">
            <input type="hidden" name="c" value="hr">
            <input type="hidden" name="a" value="payroll">
            
            <?php if($is_superadmin): ?>
            <select name="hospital_id" class="form-select border-primary shadow-sm rounded-3" onchange="this.form.submit()" style="width: auto;">
                <option value="0">-- ทุกหน่วยบริการ --</option>
                <?php foreach($hospitals_list as $h): ?>
                    <option value="<?= $h['id'] ?>" <?= $h['id'] == $filter_hosp ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <!-- 🌟 UI เลือกเดือน/ปี พ.ศ. แบบใหม่ (แทนที่ input type="month") 🌟 -->
            <div class="d-flex align-items-center bg-white border border-primary shadow-sm rounded-3 overflow-hidden">
                <div class="px-3 text-primary bg-primary bg-opacity-10 border-end d-flex align-items-center h-100"><i class="bi bi-calendar-month"></i></div>
                
                <!-- เลือกเดือน -->
                <select id="selMonth" class="form-select border-0 text-primary fw-bold shadow-none ps-3" style="cursor: pointer; width: 135px; border-radius: 0;" onchange="submitMonthChange()">
                    <?php for($i=1; $i<=12; $i++): $m_val = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?= $m_val ?>" <?= $m_val === $current_m ? 'selected' : '' ?>><?= $thai_months[$i] ?></option>
                    <?php endfor; ?>
                </select>
                
                <!-- เลือกปี (บวก 543 อัตโนมัติ) -->
                <select id="selYear" class="form-select border-0 border-start text-primary fw-bold shadow-none ps-3" style="cursor: pointer; width: 100px; border-radius: 0;" onchange="submitMonthChange()">
                    <?php 
                    // แสดงปีล่วงหน้าและย้อนหลัง 5 ปี
                    $base_year = date('Y');
                    for($i = $base_year - 5; $i <= $base_year + 5; $i++): 
                    ?>
                        <option value="<?= $i ?>" <?= $i === $current_y ? 'selected' : '' ?>><?= $i + 543 ?></option>
                    <?php endfor; ?>
                </select>
                
                <!-- ตัวแปรซ่อนสำหรับส่งค่ากลับไปประมวลผลรูปแบบ YYYY-MM -->
                <input type="hidden" name="month" id="hiddenMonth" value="<?= $selected_month ?>">
            </div>

            <button type="button" onclick="exportTableToExcel('payrollTable', 'รายงานค่าตอบแทน_<?= $selected_month ?>')" class="btn btn-success fw-bold rounded-3 shadow-sm px-3 text-nowrap">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
            </button>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">ข้อมูลประจำเดือน: <span class="text-primary"><?= $display_month ?></span></h6>
            <h6 class="mb-0 fw-bold text-dark">รวมเงินงบประมาณทั้งสิ้น: <span class="text-success fs-5"><?= number_format($total_budget, 2) ?></span> บาท</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="payrollTable" class="table table-hover table-bordered align-middle text-center mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start px-3">ชื่อ-สกุล / ตำแหน่ง</th>
                            <th>ประเภท</th>
                            <th>กลุ่มเรทค่าตอบแทน</th>
                            <th class="text-success bg-success bg-opacity-10">เวรดึก (ร)</th>
                            <th class="text-primary bg-primary bg-opacity-10">เวรบ่าย (บ)</th>
                            <th class="text-danger bg-danger bg-opacity-10">เวรวันหยุด (ย)</th>
                            <th class="bg-warning bg-opacity-10 text-dark fw-bold">รวมเงินที่ได้รับ (บาท)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($payroll_data)): ?>
                            <tr><td colspan="7" class="py-5 text-muted">ไม่พบข้อมูลการขึ้นเวรในเดือนนี้</td></tr>
                        <?php else: foreach($payroll_data as $row): ?>
                            <tr>
                                <td class="text-start px-3">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($row['position']) ?></div>
                                </td>
                                <td><span class="badge bg-secondary bg-opacity-10 text-dark"><?= htmlspecialchars($row['employee_type']) ?></span></td>
                                <td class="small"><?= htmlspecialchars($row['group_name']) ?></td>
                                
                                <td class="text-success"><?= $row['sum_r'] ?> <div class="small text-muted opacity-50">(x<?= $row['rate_r'] ?>)</div></td>
                                <td class="text-primary"><?= $row['sum_b'] ?> <div class="small text-muted opacity-50">(x<?= $row['rate_b'] ?>)</div></td>
                                <td class="text-danger"><?= $row['sum_y'] ?> <div class="small text-muted opacity-50">(x<?= $row['rate_y'] ?>)</div></td>
                                
                                <td class="bg-warning bg-opacity-10 text-dark fw-bold fs-5 text-end pe-4">
                                    <?= number_format($row['total_pay'], 2) ?>
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
// สคริปต์รวมค่าเดือนและปี แล้วสั่ง Submit Form อัตโนมัติเมื่อมีการเปลี่ยนค่า
function submitMonthChange() {
    const y = document.getElementById('selYear').value;
    const m = document.getElementById('selMonth').value;
    document.getElementById('hiddenMonth').value = y + '-' + m;
    document.getElementById('hiddenMonth').form.submit();
}

function exportTableToExcel(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel;charset=utf-8';
    var tableSelect = document.getElementById(tableID);
    
    // แปลงให้เป็น HTML ที่ Excel อ่านภาษาไทยได้
    var tableHTML = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body>' + tableSelect.outerHTML.replace(/ /g, '%20') + '</body></html>';
    
    filename = filename?filename+'.xls':'excel_data.xls';
    downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    
    if(navigator.msSaveOrOpenBlob){
        var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
        navigator.msSaveOrOpenBlob( blob, filename);
    }else{
        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
        downloadLink.download = filename;
        downloadLink.click();
    }
}
</script>