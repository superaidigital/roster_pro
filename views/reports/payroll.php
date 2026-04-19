<?php
// ที่อยู่ไฟล์: views/reports/payroll.php
$thai_months = ["01"=>"มกราคม", "02"=>"กุมภาพันธ์", "03"=>"มีนาคม", "04"=>"เมษายน", "05"=>"พฤษภาคม", "06"=>"มิถุนายน", "07"=>"กรกฎาคม", "08"=>"สิงหาคม", "09"=>"กันยายน", "10"=>"ตุลาคม", "11"=>"พฤศจิกายน", "12"=>"ธันวาคม"];
$month_text = $thai_months[str_pad($selected_month, 2, '0', STR_PAD_LEFT)] . " " . ($selected_year + 543);
?>
<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .table-modern th { font-weight: 700; color: #475569; font-size: 13px; background-color: #f8fafc; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding: 1.2rem 1rem; }
    .table-modern td { vertical-align: middle; font-size: 14.5px; border-bottom: 1px solid #f1f5f9; padding: 1rem; }
    .table-modern tbody tr:hover td { background-color: #f0fdf4; }
    .money-text { font-family: 'Courier New', Courier, monospace; font-size: 16px; font-weight: bold; color: #15803d; }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100 d-flex flex-column">

    <!-- 🌟 Header & Filters -->
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-success bg-opacity-10 text-success rounded-4 d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px;">
                <i class="bi bi-cash-stack fs-3"></i>
            </div>
            <div>
                <h3 class="fw-bolder text-dark mb-1">สรุปการเบิกจ่ายค่าตอบแทน (Payroll)</h3>
                <p class="text-muted mb-0" style="font-size: 14px;">รายงานเฉพาะหน่วยบริการที่ <span class="badge bg-success">อนุมัติตารางเวรแล้ว</span> เท่านั้น</p>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center bg-white p-2 rounded-pill shadow-sm border">
            <form action="index.php" method="GET" class="d-flex gap-2 mb-0 align-items-center">
                <input type="hidden" name="c" value="report">
                <input type="hidden" name="a" value="payroll">
                
                <?php if ($is_admin): ?>
                    <select name="hospital_id" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark pe-4" onchange="this.form.submit()" style="max-width: 200px;">
                        <option value="all">ทุกหน่วยบริการ</option>
                        <?php foreach($hospitals_list as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= $filter_hospital == $h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="vr mx-1 opacity-25"></div>
                <?php endif; ?>

                <i class="bi bi-calendar-event text-success ms-2"></i>
                <select name="month" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark px-1 cursor-pointer" onchange="this.form.submit()" style="width: 100px;">
                    <?php foreach($thai_months as $m_num => $m_name): ?>
                        <option value="<?= $m_num ?>" <?= $selected_month == $m_num ? 'selected' : '' ?>><?= $m_name ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="year" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark px-1 cursor-pointer" style="width: 80px;" onchange="this.form.submit()">
                    <?php for($i = date('Y')-2; $i <= date('Y')+1; $i++): ?>
                        <option value="<?= $i ?>" <?= $selected_year == $i ? 'selected' : '' ?>><?= $i + 543 ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            
            <div class="vr mx-1 opacity-25"></div>
            <button class="btn btn-success rounded-pill fw-bold shadow-sm px-4" onclick="exportTableToExcel('payrollTable', 'รายงานเบิกจ่าย_<?= $month_text ?>')">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> ส่งออก Excel
            </button>
        </div>
    </div>

    <!-- 🌟 KPI Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card card-modern h-100 border-success border-start border-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-bold mb-1 small text-uppercase">ยอดเบิกจ่ายรวมทั้งหมด (บาท)</p>
                        <h2 class="fw-bolder text-dark mb-0 text-success">฿ <?= number_format($total_network_budget) ?></h2>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center fs-2" style="width: 70px; height: 70px;"><i class="bi bi-wallet2"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-modern h-100 border-primary border-start border-4">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted fw-bold mb-1 small text-uppercase">บุคลากรที่ได้รับค่าตอบแทน</p>
                        <h2 class="fw-bolder text-dark mb-0"><?= number_format($total_staff_paid) ?> <span class="fs-5 fw-normal text-muted">คน</span></h2>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fs-2" style="width: 70px; height: 70px;"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 Payroll Table -->
    <div class="card card-modern flex-grow-1 d-flex flex-column">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <h6 class="mb-0 fw-bolder text-dark"><i class="bi bi-list-columns-reverse text-success me-2"></i> บัญชีเบิกจ่ายประจำเดือน <?= $month_text ?></h6>
        </div>
        <div class="table-responsive custom-scrollbar flex-grow-1" style="max-height: 60vh;">
            <table class="table table-modern mb-0" id="payrollTable">
                <thead class="sticky-top" style="z-index: 10;">
                    <tr>
                        <th class="ps-4" style="width: 5%;">ลำดับ</th>
                        <th style="width: 25%;">ชื่อ-นามสกุล</th>
                        <th style="width: 25%;">ตำแหน่ง / วิชาชีพ</th>
                        <th style="width: 25%;">หน่วยบริการ (สังกัด)</th>
                        <th class="text-end pe-4" style="width: 20%;">จำนวนเงิน (บาท)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payroll_data)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 opacity-50 d-block mb-3"></i> ไม่มีข้อมูลการเบิกจ่ายในเดือนนี้ (อาจจะยังไม่มี รพ.สต. ไหนได้รับการอนุมัติตารางเวร)</td></tr>
                    <?php else: 
                        $no = 1;
                        foreach ($payroll_data as $row): 
                    ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['user_name']) ?></div>
                                <div class="text-muted small">เลขตำแหน่ง: <?= htmlspecialchars($row['position_number']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($row['type']) ?></td>
                            <td><span class="badge bg-light text-dark border"><i class="bi bi-building text-primary me-1"></i> <?= htmlspecialchars($row['hospital_name']) ?></span></td>
                            <td class="text-end pe-4 money-text"><?= number_format($row['pay']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <?php if (!empty($payroll_data)): ?>
                <tfoot>
                    <tr class="bg-light">
                        <td colspan="4" class="text-end fw-bold text-dark py-3">รวมเงินเบิกจ่ายทั้งสิ้น</td>
                        <td class="text-end pe-4 py-3 fw-bolder text-success fs-5">฿ <?= number_format($total_network_budget) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
function exportTableToExcel(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel;charset=utf-8';
    var tableSelect = document.getElementById(tableID);
    
    // สร้างสำเนาตารางเพื่อปรับแต่งก่อน Export
    var tableClone = tableSelect.cloneNode(true);
    
    // แปลง HTML เป็น Excel Format พร้อมรองรับภาษาไทย (BOM)
    var tableHTML = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body>';
    tableHTML += "<h3 style='text-align:center;'>รายงานสรุปการเบิกจ่ายค่าตอบแทน เดือน <?= $month_text ?></h3>";
    tableHTML += tableClone.outerHTML + '</body></html>';
    
    filename = filename ? filename + '.xls' : 'payroll.xls';
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