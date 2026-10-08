<?php
$tracking = $tracking ?? [];
$trend = $trend ?? [];
$low_coverage = $low_coverage ?? [];
$dashboard = $dashboard ?? [];
$hospitals = $hospitals ?? [];
$is_admin = $is_admin ?? false;
$selected_hospital_id = $selected_hospital_id ?? null;
$report_month = $report_month ?? date('Y-m');

function data43_status_meta(?string $status): array {
    return match ($status) {
        'COMPLETE' => ['success', 'ครบ', 'bi-check-circle-fill'],
        'INCOMPLETE' => ['warning', 'ไม่ครบ', 'bi-exclamation-triangle-fill'],
        'FAILED' => ['danger', 'ผิดพลาด', 'bi-x-octagon-fill'],
        'PROCESSING' => ['info', 'กำลังประมวลผล', 'bi-hourglass-split'],
        default => ['secondary', 'ยังไม่ส่ง', 'bi-dash-circle'],
    };
}

$trendMax = 1;
foreach ($trend as $t) {
    $trendMax = max($trendMax, (int)($t['submitted'] ?? 0));
}
?>
<style>
.data43-kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.85rem}
.data43-kpi{padding:1rem;border:1px solid var(--rp-border);border-radius:1rem;background:#fff}
.data43-kpi__label{font-size:.72rem;color:var(--rp-text-muted);font-weight:600}
.data43-kpi__value{font-size:1.8rem;line-height:1.1;font-weight:800;margin-top:.3rem}
.data43-progress{height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden}
.data43-progress>span{display:block;height:100%;border-radius:inherit;background:#1677ff}
.data43-progress.is-success>span{background:#16a34a}
.data43-trend{display:flex;align-items:flex-end;gap:.55rem;min-height:210px;padding:.75rem .25rem 0}
.data43-trend__item{flex:1;min-width:36px;text-align:center}
.data43-trend__bars{height:150px;display:flex;gap:3px;align-items:flex-end;justify-content:center}
.data43-trend__bar{width:12px;border-radius:5px 5px 2px 2px;background:#1677ff;min-height:3px}
.data43-trend__bar.complete{background:#16a34a}
.data43-trend__label{font-size:.65rem;color:var(--rp-text-muted);margin-top:.35rem;white-space:nowrap}
.data43-watch{display:flex;justify-content:space-between;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--rp-border)}
.data43-watch:last-child{border-bottom:0}
.data43-table td,.data43-table th{vertical-align:middle}
@media(max-width:1199px){.data43-kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:767px){.data43-kpi-grid{grid-template-columns:repeat(2,1fr)}.data43-trend{overflow-x:auto}.data43-trend__item{min-width:48px}}
</style>

<div class="rp-page">
    <div class="rp-page-header mb-3">
        <div>
            <div class="rp-page-header__eyebrow">MOPH DATA STANDARD 2.4.1</div>
            <h1 class="rp-page-header__title">Command Center 43 แฟ้ม</h1>
            <p class="rp-page-header__subtitle mb-0">
                ติดตามการนำส่งและความครบถ้วนตาม Profile รพ.สต. ของมาตรฐานข้อมูลสุขภาพ Version 2.4.1 โดยใช้เฉพาะข้อมูลสรุปและ metadata
            </p>
        </div>
        <div class="rp-page-header__actions">
            <a href="index.php?c=data43&a=spatial&month=<?= urlencode($report_month) ?><?= $selected_hospital_id ? '&hospital_id='.(int)$selected_hospital_id : '' ?>" class="rp-btn rp-btn--secondary">
                <i class="bi bi-geo-alt-fill"></i> วิเคราะห์เชิงพื้นที่
            </a>
            <a href="index.php?c=data43&a=index<?= $selected_hospital_id ? '&hospital_id='.(int)$selected_hospital_id : '' ?>" class="rp-btn rp-btn--primary">
                <i class="bi bi-cloud-arrow-up"></i> นำส่งข้อมูล
            </a>
        </div>
    </div>

    <?php if (!$schema_ready): ?>
        <div class="rp-alert rp-alert--warning mb-3">
            <span class="rp-alert__icon"><i class="bi bi-database-exclamation"></i></span>
            <div class="rp-alert__content">กรุณารัน migration ระบบ 43 แฟ้มก่อนใช้งาน Dashboard</div>
        </div>
    <?php endif; ?>

    <section class="rp-card mb-3">
        <div class="rp-card__body">
            <form method="GET" action="index.php" class="row g-2 align-items-end">
                <input type="hidden" name="c" value="data43">
                <input type="hidden" name="a" value="dashboard">

                <div class="col-md-3">
                    <label class="form-label fw-bold small">รอบเดือน</label>
                    <input type="month" name="month" class="rp-control" value="<?= htmlspecialchars($report_month, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <?php if ($is_admin): ?>
                <div class="col-md-6">
                    <label class="form-label fw-bold small">รพ.สต. / หน่วยบริการ</label>
                    <select name="hospital_id" class="rp-control">
                        <option value="">ทุกหน่วยบริการ</option>
                        <?php foreach ($hospitals as $h): ?>
                            <option value="<?= (int)$h['id'] ?>" <?= (int)$selected_hospital_id === (int)$h['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($h['hospital_code'] ? '['.$h['hospital_code'].'] ' : '').$h['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-3">
                    <button class="rp-btn rp-btn--secondary w-100" type="submit">
                        <i class="bi bi-funnel"></i> แสดงผล
                    </button>
                </div>
            </form>
        </div>
    </section>

    <div class="data43-kpi-grid mb-3">
        <div class="data43-kpi"><div class="data43-kpi__label">รพ.สต. ทั้งหมด</div><div class="data43-kpi__value"><?= (int)($dashboard['total_hospitals'] ?? 0) ?></div></div>
        <div class="data43-kpi"><div class="data43-kpi__label">ส่งแล้ว</div><div class="data43-kpi__value text-primary"><?= (int)($dashboard['submitted'] ?? 0) ?></div></div>
        <div class="data43-kpi"><div class="data43-kpi__label">ยังไม่ส่ง</div><div class="data43-kpi__value text-danger"><?= (int)($dashboard['not_submitted'] ?? 0) ?></div></div>
        <div class="data43-kpi"><div class="data43-kpi__label">ครบตาม Profile รพ.สต.</div><div class="data43-kpi__value text-success"><?= (int)($dashboard['complete'] ?? 0) ?></div></div>
        <div class="data43-kpi"><div class="data43-kpi__label">ไม่ครบ</div><div class="data43-kpi__value text-warning"><?= (int)($dashboard['incomplete'] ?? 0) ?></div></div>
        <div class="data43-kpi"><div class="data43-kpi__label">ผิดพลาด</div><div class="data43-kpi__value text-danger"><?= (int)($dashboard['failed'] ?? 0) ?></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <section class="rp-card h-100">
                <div class="rp-card__header"><h2 class="rp-card__title mb-0">อัตราการนำส่ง</h2></div>
                <div class="rp-card__body">
                    <div class="display-5 fw-bold mb-2"><?= number_format((float)($dashboard['submission_rate'] ?? 0),1) ?>%</div>
                    <div class="data43-progress mb-2"><span style="width:<?= min(100,max(0,(float)($dashboard['submission_rate'] ?? 0))) ?>%"></span></div>
                    <div class="small text-muted">
                        ส่งแล้ว <?= (int)($dashboard['submitted'] ?? 0) ?> จาก <?= (int)($dashboard['total_hospitals'] ?? 0) ?> หน่วย
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="rp-card h-100">
                <div class="rp-card__header"><h2 class="rp-card__title mb-0">ความครบถ้วนเฉลี่ย</h2></div>
                <div class="rp-card__body">
                    <div class="display-5 fw-bold text-success mb-2"><?= number_format((float)($dashboard['completeness_rate'] ?? 0),1) ?>%</div>
                    <div class="data43-progress is-success mb-2"><span style="width:<?= min(100,max(0,(float)($dashboard['completeness_rate'] ?? 0))) ?>%"></span></div>
                    <div class="small text-muted">คำนวณจากโครงสร้างที่ตรวจพบเทียบกับ expected_files ของ Profile รพ.สต. ใน Submission</div>
                </div>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="rp-card h-100">
                <div class="rp-card__header"><h2 class="rp-card__title mb-0">ภาพรวมเดือนนี้</h2></div>
                <div class="rp-card__body">
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">จำนวนครั้งที่นำส่ง</span><strong><?= number_format((int)($dashboard['attempts'] ?? 0)) ?></strong></div>
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Records ที่ตรวจนับได้</span><strong><?= number_format((int)($dashboard['total_rows'] ?? 0)) ?></strong></div>
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">กำลังประมวลผล</span><strong><?= (int)($dashboard['processing'] ?? 0) ?></strong></div>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <section class="rp-card h-100">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1">แนวโน้มย้อนหลัง 12 เดือน</h2>
                        <div class="small text-muted">สีน้ำเงิน = หน่วยที่ส่ง · สีเขียว = หน่วยที่ครบ</div>
                    </div>
                </div>
                <div class="rp-card__body">
                    <?php if (empty($trend)): ?>
                        <div class="text-center py-5 text-muted">ยังไม่มีข้อมูลแนวโน้ม</div>
                    <?php else: ?>
                        <div class="data43-trend" aria-label="กราฟแนวโน้มการนำส่ง">
                            <?php foreach ($trend as $t):
                                $submittedH = max(3, ((int)$t['submitted'] / $trendMax) * 150);
                                $completeH = max(3, ((int)$t['complete_count'] / $trendMax) * 150);
                            ?>
                            <div class="data43-trend__item" title="<?= htmlspecialchars($t['report_month'],ENT_QUOTES,'UTF-8') ?>: ส่ง <?= (int)$t['submitted'] ?> / ครบ <?= (int)$t['complete_count'] ?>">
                                <div class="data43-trend__bars">
                                    <span class="data43-trend__bar" style="height:<?= $submittedH ?>px"></span>
                                    <span class="data43-trend__bar complete" style="height:<?= $completeH ?>px"></span>
                                </div>
                                <div class="data43-trend__label"><?= htmlspecialchars(substr($t['report_month'],5,2).'/'.substr($t['report_month'],0,4),ENT_QUOTES,'UTF-8') ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="rp-card h-100">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1">ชุดข้อมูลที่พบน้อย</h2>
                        <div class="small text-muted">จาก Submission ล่าสุดของเดือน</div>
                    </div>
                </div>
                <div class="rp-card__body">
                    <?php if (empty($low_coverage)): ?>
                        <div class="text-center py-4 text-muted">ยังไม่มีข้อมูลไฟล์</div>
                    <?php else: ?>
                        <?php foreach ($low_coverage as $row): ?>
                            <div class="data43-watch">
                                <div>
                                    <code><?= htmlspecialchars($row['file_code'],ENT_QUOTES,'UTF-8') ?></code>
                                    <div class="small text-muted">พบในหน่วยบริการ</div>
                                </div>
                                <strong><?= (int)$row['hospital_count'] ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <section class="rp-card">
        <div class="rp-card__header">
            <div>
                <h2 class="rp-card__title mb-1">ติดตามราย รพ.สต.</h2>
                <div class="small text-muted">ใช้รายการนำส่งล่าสุดของแต่ละหน่วยในรอบเดือนที่เลือก</div>
            </div>
            <input type="search" id="data43HospitalSearch" class="rp-control" placeholder="ค้นหา รพ.สต..." style="max-width:260px">
        </div>
        <div class="rp-card__body p-0">
            <div class="table-responsive">
                <table class="rp-table data43-table mb-0" id="data43TrackingTable">
                    <thead>
                        <tr>
                            <th>รพ.สต.</th>
                            <th class="text-center">สถานะ</th>
                            <th class="text-center">แฟ้ม</th>
                            <th class="text-end">Records</th>
                            <th class="text-center">ส่งซ้ำ</th>
                            <th>ส่งล่าสุด</th>
                            <th class="text-end">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tracking as $row):
                        [$tone,$label,$icon] = data43_status_meta($row['status'] ?? null);
                    ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['hospital_name'],ENT_QUOTES,'UTF-8') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars((string)$row['hospital_code'],ENT_QUOTES,'UTF-8') ?></div>
                            </td>
                            <td class="text-center"><span class="rp-badge rp-badge--<?= $tone ?>"><i class="bi <?= $icon ?> me-1"></i><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></span></td>
                            <td class="text-center"><?= empty($row['submission_id']) ? '-' : ((int)$row['detected_files'].'/'.(int)$row['expected_files']) ?></td>
                            <td class="text-end"><?= empty($row['submission_id']) ? '-' : number_format((int)$row['total_rows']) ?></td>
                            <td class="text-center"><?= (int)$row['submission_count'] ?></td>
                            <td><?= empty($row['uploaded_at']) ? '-' : htmlspecialchars($row['uploaded_at'],ENT_QUOTES,'UTF-8') ?></td>
                            <td class="text-end">
                                <?php if (!empty($row['submission_id'])): ?>
                                    <a href="index.php?c=data43&a=detail&id=<?= (int)$row['submission_id'] ?>" class="rp-btn rp-btn--secondary rp-btn--sm"><i class="bi bi-eye"></i> ดู</a>
                                <?php else: ?>
                                    <span class="text-muted small">ยังไม่ส่ง</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tracking)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">ไม่พบหน่วยบริการในขอบเขตนี้</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('data43HospitalSearch');
    const table = document.getElementById('data43TrackingTable');
    if (!input || !table) return;

    input.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        table.querySelectorAll('tbody tr').forEach(function (row) {
            row.style.display = !q || row.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});
</script>
