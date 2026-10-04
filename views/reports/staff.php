<?php
// ที่อยู่ไฟล์: views/reports/staff.php
$filter_hospital = $filter_hospital ?? 'all';

// ดึง Top 3 ตำแหน่งยอดฮิต
$top_positions = array_slice($type_counts ?? [], 0, 3, true);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    body { background-color: #f4f6f9; font-family: 'Sarabun', sans-serif; }
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .table-modern th { font-weight: 700; color: #475569; font-size: 13px; background-color: #f8fafc; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding: 1.2rem 1rem; }
    .table-modern td { vertical-align: middle; font-size: 14.5px; border-bottom: 1px solid #f1f5f9; padding: 1rem; }
    .table-modern tbody tr:hover td { background-color: #f8fafc; }
    
    .sys-role-badge { font-size: 11px; padding: 3px 8px; border-radius: 6px; font-weight: bold; }
    
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100 d-flex flex-column">

    <!-- 🌟 Header & Filters -->
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-warning bg-opacity-10 text-warning rounded-4 d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px;">
                <i class="bi bi-people fs-3"></i>
            </div>
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0" style="font-size:12px;">
                        <li class="breadcrumb-item"><a href="index.php?c=report" class="text-decoration-none">ศูนย์รวมรายงาน</a></li>
                        <li class="breadcrumb-item active">ทะเบียนบุคลากร</li>
                    </ol>
                </nav>
                <h3 class="fw-bolder text-dark mb-0">ทะเบียนข้อมูลบุคลากร</h3>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center bg-white p-2 rounded-pill shadow-sm border">
            <form action="index.php" method="GET" class="d-flex gap-2 mb-0 align-items-center">
                <input type="hidden" name="c" value="report">
                <input type="hidden" name="a" value="staff">
                
                <?php if ($is_admin): ?>
                    <select name="hospital_id" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark pe-4" onchange="this.form.submit()" style="max-width: 250px;">
                        <option value="all">🏥 ทุกหน่วยบริการ (ทั้งเครือข่าย)</option>
                        <?php foreach($hospitals_list as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= $filter_hospital == $h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <span class="text-primary fw-bold px-3"><i class="bi bi-hospital me-1"></i> เฉพาะหน่วยงานของท่าน</span>
                <?php endif; ?>
            </form>
            
            <div class="vr mx-1 opacity-25"></div>
            <button class="btn btn-success rounded-pill fw-bold shadow-sm px-4" onclick="exportTableToExcel('staffTable', 'ทะเบียนข้อมูลบุคลากร')">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Excel
            </button>
        </div>
    </div>

    <!-- 🌟 KPI Cards & Charts -->
    <div class="row g-4 mb-4">
        <!-- ยอดรวมบุคลากร -->
        <div class="col-xl-3 col-lg-4">
            <div class="card card-modern h-100 border-primary border-start border-4">
                <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center text-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-person-lines-fill fs-1"></i>
                    </div>
                    <p class="text-muted fw-bold mb-1 small text-uppercase">จำนวนบุคลากรรวมทั้งหมด</p>
                    <h1 class="fw-black text-primary mb-0 display-4"><?= number_format($total_staff ?? 0) ?> <span class="fs-5 text-muted fw-normal">คน</span></h1>
                </div>
            </div>
        </div>

        <!-- กราฟประเภทการจ้างงาน -->
        <div class="col-xl-4 col-lg-8">
            <div class="card card-modern h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bolder text-dark"><i class="bi bi-pie-chart-fill text-success me-2"></i> สัดส่วนตามประเภทการจ้างงาน</h6>
                </div>
                <div class="card-body">
                    <?php if(empty($emp_type_counts)): ?>
                        <div class="text-center text-muted py-4"><i class="bi bi-pie-chart fs-1 opacity-25 mb-2 d-block"></i> ไม่มีข้อมูล</div>
                    <?php else: ?>
                        <div style="position: relative; height: 200px; width: 100%;">
                            <canvas id="empTypeChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top ตำแหน่ง/สายงาน -->
        <div class="col-xl-5 col-lg-12">
            <div class="card card-modern h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bolder text-dark"><i class="bi bi-star-fill text-warning me-2"></i> สถิติจำนวนคนแยกตามตำแหน่ง (Top 3)</h6>
                </div>
                <div class="card-body">
                    <?php if(empty($top_positions)): ?>
                        <div class="text-center text-muted py-4"><i class="bi bi-bar-chart fs-1 opacity-25 mb-2 d-block"></i> ไม่มีข้อมูล</div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3 mt-2">
                            <?php 
                            $colors = ['bg-primary', 'bg-success', 'bg-info'];
                            $i = 0;
                            foreach($top_positions as $pos => $count): 
                                $percent = ($total_staff ?? 0) > 0 ? round(($count / ($total_staff ?? 1)) * 100) : 0;
                                $color = $colors[$i % 3];
                            ?>
                                <div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-bold text-dark" style="font-size: 14px;"><?= htmlspecialchars($pos) ?></span>
                                        <span class="text-muted small fw-bold"><?= $count ?> คน (<?= $percent ?>%)</span>
                                    </div>
                                    <div class="progress" style="height: 8px; border-radius: 10px;">
                                        <div class="progress-bar <?= $color ?> progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $percent ?>%"></div>
                                    </div>
                                </div>
                            <?php $i++; endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 🌟 Staff Table -->
    <div class="card card-modern flex-grow-1 d-flex flex-column">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bolder text-dark"><i class="bi bi-list-columns-reverse text-warning me-2"></i> ข้อมูลรายชื่อบุคลากรทั้งหมด</h6>
        </div>
        <div class="card-body p-0 flex-grow-1">
            <div class="table-responsive custom-scrollbar p-3">
                <table class="table table-modern w-100 text-center" id="staffTable">
                    <thead class="sticky-top">
                        <tr>
                            <th class="text-start ps-3" style="width: 25%;">ชื่อ-นามสกุล</th>
                            <th class="text-start" style="width: 20%;">ตำแหน่ง / วิชาชีพ</th>
                            <th class="text-start" style="width: 15%;">ประเภทบุคลากร</th>
                            <th style="width: 15%;">เลขที่ตำแหน่ง</th>
                            <th class="text-start" style="width: 15%;">หน่วยบริการ (สังกัด)</th>
                            <th class="pe-3" style="width: 10%;">สิทธิ์ระบบ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff_records)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 opacity-50 d-block mb-3"></i> ไม่พบรายชื่อบุคลากร</td></tr>
                        <?php else: 
                            foreach ($staff_records as $row): 
                                // จัดการป้ายสิทธิ์ผู้ใช้งาน
                                $s_role = $row['system_role'];
                                $role_bg = 'bg-secondary bg-opacity-10 text-secondary border-secondary';
                                if($s_role == 'ADMIN') $role_bg = 'bg-danger bg-opacity-10 text-danger border-danger';
                                elseif($s_role == 'DIRECTOR') $role_bg = 'bg-success bg-opacity-10 text-success border-success';
                                elseif($s_role == 'SCHEDULER') $role_bg = 'bg-primary bg-opacity-10 text-primary border-primary';
                                elseif($s_role == 'HR') $role_bg = 'bg-info bg-opacity-10 text-info border-info';
                        ?>
                            <tr>
                                <td class="text-start ps-3 fw-bold text-dark">
                                    <i class="bi bi-person-circle text-muted me-2 opacity-50"></i><?= htmlspecialchars($row['name']) ?>
                                </td>
                                <td class="text-start"><?= htmlspecialchars($row['type'] ?: '-') ?></td>
                                <td class="text-start"><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($row['employee_type'] ?: '-') ?></span></td>
                                <td class="font-monospace text-secondary"><?= htmlspecialchars($row['position_number'] ?: '-') ?></td>
                                <td class="text-start">
                                    <span class="text-truncate d-inline-block" style="max-width:200px;" title="<?= htmlspecialchars($row['hospital_name']) ?>">
                                        <i class="bi bi-building text-primary me-1 opacity-75"></i> <?= htmlspecialchars($row['hospital_name']) ?>
                                    </span>
                                </td>
                                <td class="pe-3">
                                    <span class="sys-role-badge border <?= $role_bg ?>"><?= $s_role ?></span>
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
document.addEventListener('DOMContentLoaded', function() {
    // Initial DataTables
    $('#staffTable').DataTable({
        "language": { 
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json",
            "emptyTable": "<div class='text-center py-5 text-muted'><i class='bi bi-folder-x fs-1 opacity-50 d-block mb-3'></i> ไม่พบรายชื่อบุคลากร</div>"
        },
        "pageLength": 25,
        "ordering": true,
        "order": [[4, 'asc'], [0, 'asc']] // เรียงตาม รพ.สต. แล้วตามด้วยชื่อ
    });

    <?php if(!empty($emp_type_counts)): ?>
    // เรนเดอร์กราฟวงกลม (ประเภทการจ้างงาน)
    const ctx = document.getElementById('empTypeChart').getContext('2d');
    const chartLabels = <?= json_encode(array_keys($emp_type_counts)) ?>;
    const chartData = <?= json_encode(array_values($emp_type_counts)) ?>;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartLabels,
            datasets: [{
                data: chartData,
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4'],
                borderWidth: 2,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: { position: 'right', labels: { font: { family: "'Sarabun', sans-serif", size: 12 }, usePointStyle: true, padding: 15 } },
                tooltip: { callbacks: { label: function(context) { return ' ' + context.label + ': ' + context.raw + ' คน'; } } }
            }
        }
    });
    <?php endif; ?>
});

// ฟังก์ชันส่งออกตารางเป็น Excel แบบ Clean (ลบ Icon ทิ้ง)
function exportTableToExcel(tableID, filename = ''){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel;charset=utf-8';
    var tableSelect = document.getElementById(tableID);
    
    var tableClone = tableSelect.cloneNode(true);
    // ลบส่วนประกอบที่ไม่ต้องการใน Excel (ช่องค้นหา Pagination และไอคอน)
    var unwantedElements = tableClone.querySelectorAll('.dataTables_wrapper .row, .bi');
    unwantedElements.forEach(el => el.remove());
    
    var tableHTML = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head><body>';
    tableHTML += "<h3 style='text-align:center;'>รายงานทะเบียนข้อมูลบุคลากร</h3>";
    tableHTML += tableClone.outerHTML + '</body></html>';
    
    filename = filename ? filename + '.xls' : 'staff_registry.xls';
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