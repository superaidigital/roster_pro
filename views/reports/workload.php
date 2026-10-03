<?php
$selected_month = $selected_month ?? date('m');
$selected_year = $selected_year ?? date('Y');
$filter_hospital = $filter_hospital ?? 'all';
$workload_data = $workload_data ?? [];
$hospitals_list = $hospitals_list ?? [];

$thai_months = ['', 'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$month_label = $thai_months[(int)$selected_month] . ' ' . ((int)$selected_year + 543);
?>
<div class="container-fluid px-3 px-md-4 py-4">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
    <div>
      <h3 class="fw-bold mb-1"><i class="bi bi-bar-chart-line text-primary me-2"></i>รายงานภาระงาน</h3>
      <p class="text-muted mb-0">สรุปจำนวนกะปฏิบัติงานรายบุคคล ประจำเดือน <?= htmlspecialchars($month_label) ?></p>
    </div>
    <form method="GET" class="row g-2">
      <input type="hidden" name="c" value="report">
      <input type="hidden" name="a" value="workload">
      <div class="col-auto"><select class="form-select" name="month"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= (int)$selected_month===$m?'selected':'' ?>><?= $thai_months[$m] ?></option><?php endfor; ?></select></div>
      <div class="col-auto"><input class="form-control" type="number" name="year" value="<?= (int)$selected_year ?>" min="2020" max="2100"></div>
      <?php if (!empty($hospitals_list)): ?><div class="col-auto"><select class="form-select" name="hospital_id"><option value="all">ทุกหน่วยบริการ</option><?php foreach($hospitals_list as $h): ?><option value="<?= (int)$h['id'] ?>" <?= (string)$filter_hospital===(string)$h['id']?'selected':'' ?>><?= htmlspecialchars($h['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="col-auto"><button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>กรอง</button></div>
    </form>
  </div>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>หน่วยบริการ</th><th>บุคลากร</th><th>ตำแหน่ง</th><th class="text-center">เช้า</th><th class="text-center">บ่าย</th><th class="text-center">ดึก/อื่น</th><th class="text-center">รวม</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($workload_data)): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">ไม่พบข้อมูลภาระงานในช่วงที่เลือก</td></tr>
          <?php else: foreach($workload_data as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['hospital_name'] ?? '-') ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($row['user_name'] ?? '-') ?></td>
              <td><?= htmlspecialchars(($row['type'] ?? '-') . (!empty($row['position_number']) ? ' #' . $row['position_number'] : '')) ?></td>
              <td class="text-center"><?= (int)($row['shift_m'] ?? 0) ?></td>
              <td class="text-center"><?= (int)($row['shift_a'] ?? 0) ?></td>
              <td class="text-center"><?= (int)($row['shift_n'] ?? 0) ?></td>
              <td class="text-center fw-bold"><?= (int)($row['total'] ?? 0) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
