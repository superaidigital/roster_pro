<?php
$selected_month = $selected_month ?? date('m');
$selected_year = $selected_year ?? date('Y');
$filter_hospital = $filter_hospital ?? 'all';
$leave_records = $leave_records ?? [];
$hospitals_list = $hospitals_list ?? [];
$total_leave_days = $total_leave_days ?? 0;
$pending_requests = $pending_requests ?? 0;
$total_users_on_leave = $total_users_on_leave ?? 0;
$top_leave_type = $top_leave_type ?? '-';
$thai_months = ['', 'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$month_label = $thai_months[(int)$selected_month] . ' ' . ((int)$selected_year + 543);
?>
<div class="container-fluid px-3 px-md-4 py-4">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
    <div>
      <h3 class="fw-bold mb-1"><i class="bi bi-calendar2-x text-danger me-2"></i>รายงานประวัติการลา</h3>
      <p class="text-muted mb-0">ข้อมูลการลาที่คาบเกี่ยวเดือน <?= htmlspecialchars($month_label) ?></p>
    </div>
    <form method="GET" class="row g-2">
      <input type="hidden" name="c" value="report"><input type="hidden" name="a" value="leave">
      <div class="col-auto"><select class="form-select" name="month"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= (int)$selected_month===$m?'selected':'' ?>><?= $thai_months[$m] ?></option><?php endfor; ?></select></div>
      <div class="col-auto"><input class="form-control" type="number" name="year" value="<?= (int)$selected_year ?>" min="2020" max="2100"></div>
      <?php if (!empty($hospitals_list)): ?><div class="col-auto"><select class="form-select" name="hospital_id"><option value="all">ทุกหน่วยบริการ</option><?php foreach($hospitals_list as $h): ?><option value="<?= (int)$h['id'] ?>" <?= (string)$filter_hospital===(string)$h['id']?'selected':'' ?>><?= htmlspecialchars($h['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="col-auto"><button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>กรอง</button></div>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">วันลาอนุมัติรวม</div><div class="fs-3 fw-bold"><?= (int)$total_leave_days ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">คำขอรออนุมัติ</div><div class="fs-3 fw-bold"><?= (int)$pending_requests ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">ผู้ลาที่ได้รับอนุมัติ</div><div class="fs-3 fw-bold"><?= (int)$total_users_on_leave ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="text-muted small">ประเภทลาที่พบมากสุด</div><div class="fs-5 fw-bold text-truncate"><?= htmlspecialchars($top_leave_type) ?></div></div></div></div>
  </div>

  <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>บุคลากร</th><th>หน่วยบริการ</th><th>ประเภทลา</th><th>ช่วงวันที่</th><th class="text-center">วัน</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php if(empty($leave_records)): ?><tr><td colspan="6" class="text-center text-muted py-5">ไม่พบประวัติการลาในช่วงที่เลือก</td></tr>
      <?php else: foreach($leave_records as $row): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($row['user_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($row['hospital_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($row['leave_type'] ?? '-') ?></td>
          <td><?= htmlspecialchars(($row['start_date'] ?? '-') . ' - ' . ($row['end_date'] ?? '-')) ?></td>
          <td class="text-center"><?= (int)($row['leave_days'] ?? 0) ?></td>
          <td><span class="badge text-bg-<?= ($row['status'] ?? '')==='APPROVED'?'success':((($row['status'] ?? '')==='PENDING')?'warning':'secondary') ?>"><?= htmlspecialchars($row['status'] ?? '-') ?></span></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div></div></div>
</div>
