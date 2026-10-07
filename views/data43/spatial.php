<?php
$spatial_rows = $spatial_rows ?? [];
$hospital_coverage = $hospital_coverage ?? [];
$metric_options = $metric_options ?? [];
$spatial_summary = $spatial_summary ?? [];
$hospitals = $hospitals ?? [];
$is_admin = $is_admin ?? false;
$selected_hospital_id = $selected_hospital_id ?? null;
$report_month = $report_month ?? date('Y-m');
$area_level = $area_level ?? 'TAMBON';
$metric_code = $metric_code ?? '';

function data43_area_label(array $row, string $level): string {
    $cw = $row['changwat_code'] ?: '--';
    $ap = $row['ampur_code'] ?: '--';
    $tb = $row['tambon_code'] ?: '--';
    $vl = $row['village_code'] ?: '--';

    return match ($level) {
        'AMPUR' => "อำเภอ {$cw}-{$ap}",
        'VILLAGE' => "หมู่ {$cw}-{$ap}-{$tb}-{$vl}",
        default => "ตำบล {$cw}-{$ap}-{$tb}",
    };
}

function data43_metric_label(string $code): string {
    return match ($code) {
        'HOUSEHOLD_RECORDS' => 'ระเบียนครัวเรือน',
        'ADDRESS_RECORDS' => 'ระเบียนที่อยู่',
        'PERSON_RECORDS' => 'ระเบียนบุคคล',
        default => $code === '' ? 'ทุกระเบียนที่มีข้อมูลพื้นที่' : $code,
    };
}

function data43_privacy_count(int $value): string {
    return ($value > 0 && $value < 5) ? '&lt;5' : number_format($value);
}

$levelLabels = [
    'AMPUR' => 'อำเภอ',
    'TAMBON' => 'ตำบล',
    'VILLAGE' => 'หมู่บ้าน',
];

$maxMetric = 1;
foreach ($spatial_rows as $row) {
    $maxMetric = max($maxMetric, (int)($row['metric_value'] ?? 0));
}

$maxCoverage = 1;
foreach ($hospital_coverage as $row) {
    $maxCoverage = max($maxCoverage, (int)($row['area_count'] ?? 0));
}
?>
<style>
.data43-spatial-page{min-width:0}
.data43-spatial-filter .rp-control{min-height:42px}
.data43-spatial-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem}
.data43-spatial-kpi{min-width:0;padding:1rem;border:1px solid var(--rp-border);border-radius:1rem;background:#fff}
.data43-spatial-kpi__label{font-size:.72rem;color:var(--rp-text-muted);font-weight:700}
.data43-spatial-kpi__value{font-size:1.75rem;line-height:1.1;font-weight:800;margin-top:.3rem;overflow-wrap:anywhere}
.data43-analysis-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(310px,.85fr);gap:1rem}
.data43-area-bars{display:grid;gap:.8rem}
.data43-area-row{display:grid;grid-template-columns:minmax(150px,230px) minmax(120px,1fr) auto;gap:.75rem;align-items:center}
.data43-area-name{min-width:0;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.data43-bar{height:12px;background:#e2e8f0;border-radius:999px;overflow:hidden}
.data43-bar>span{display:block;height:100%;background:var(--rp-primary,#1677ff);border-radius:inherit;min-width:3px}
.data43-area-value{min-width:76px;text-align:right;font-weight:800}
.data43-coverage-list{display:grid;gap:.9rem}
.data43-coverage-item{min-width:0}
.data43-coverage-head{display:flex;justify-content:space-between;gap:.75rem;align-items:center;margin-bottom:.35rem}
.data43-coverage-name{min-width:0;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.data43-coverage-meta{white-space:nowrap;font-size:.72rem;color:var(--rp-text-muted)}
.data43-coverage-bar{height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden}
.data43-coverage-bar>span{display:block;height:100%;background:#14b8a6;border-radius:inherit;min-width:3px}
.data43-spatial-table-wrap{max-height:520px;overflow:auto}
.data43-spatial-table{min-width:760px}
.data43-spatial-table thead th{position:sticky;top:0;background:#fff;z-index:2}
.data43-code{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .55rem;border:1px solid var(--rp-border);border-radius:999px;background:#f8fafc;font-size:.72rem;white-space:nowrap}
.data43-coord-badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.72rem}
.data43-empty{padding:3rem 1rem;text-align:center;color:var(--rp-text-muted)}
.data43-method-card{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}
.data43-method-step{padding:.85rem;border:1px solid var(--rp-border);border-radius:.9rem;background:#f8fafc}
.data43-method-step strong{display:block;margin-bottom:.2rem}
@media(max-width:1199px){
  .data43-analysis-grid{grid-template-columns:1fr}
}
@media(max-width:991px){
  .data43-spatial-kpis{grid-template-columns:repeat(2,1fr)}
  .data43-method-card{grid-template-columns:1fr}
}
@media(max-width:767px){
  .data43-area-row{grid-template-columns:1fr auto}
  .data43-area-row .data43-bar{grid-column:1/-1}
}
@media(max-width:575px){
  .data43-spatial-kpis{grid-template-columns:1fr 1fr}
  .data43-spatial-kpi{padding:.8rem}
  .data43-spatial-kpi__value{font-size:1.4rem}
}
</style>

<div class="rp-page data43-spatial-page">
    <div class="rp-page-header mb-3">
        <div>
            <div class="rp-page-header__eyebrow">SPATIAL ANALYTICS</div>
            <h1 class="rp-page-header__title">วิเคราะห์ข้อมูล 43 แฟ้มเชิงพื้นที่</h1>
            <p class="rp-page-header__subtitle mb-0">
                วิเคราะห์ข้อมูล Aggregate ระดับอำเภอ ตำบล และหมู่บ้าน โดยไม่แสดงข้อมูลประชาชนรายบุคคล
            </p>
        </div>
        <div class="rp-page-header__actions">
            <a href="index.php?c=data43&a=dashboard<?= $selected_hospital_id ? '&hospital_id='.(int)$selected_hospital_id : '' ?>" class="rp-btn rp-btn--secondary">
                <i class="bi bi-speedometer2"></i> Dashboard การนำส่ง
            </a>
        </div>
    </div>

    <?php if (!$spatial_schema_ready): ?>
        <div class="rp-alert rp-alert--warning mb-3">
            <span class="rp-alert__icon"><i class="bi bi-database-exclamation"></i></span>
            <div class="rp-alert__content">
                ยังไม่ได้ติดตั้ง Spatial Analytics กรุณารัน
                <code>database/migrations/20261007_data43_spatial_analytics.sql</code>
                และนำส่ง ZIP ใหม่เพื่อสร้าง Aggregate เชิงพื้นที่
            </div>
        </div>
    <?php endif; ?>

    <div class="rp-alert rp-alert--info mb-3">
        <span class="rp-alert__icon"><i class="bi bi-map"></i></span>
        <div class="rp-alert__content">
            หน้านี้ยังไม่แสดงแผนที่ขอบเขตจริง เนื่องจากยังไม่ได้ผูก GeoJSON/Master พื้นที่ราชการ
            จึงแสดงเป็น Ranking, Coverage และตารางวิเคราะห์แทน เพื่อไม่ให้ตำแหน่งเชิงพื้นที่ดูคลาดเคลื่อน
        </div>
    </div>

    <section class="rp-card mb-3 data43-spatial-filter">
        <div class="rp-card__body">
            <form method="GET" action="index.php" class="row g-2 align-items-end">
                <input type="hidden" name="c" value="data43">
                <input type="hidden" name="a" value="spatial">

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-bold small">รอบเดือน</label>
                    <input type="month" name="month" class="rp-control" value="<?= htmlspecialchars($report_month,ENT_QUOTES,'UTF-8') ?>">
                </div>

                <?php if ($is_admin): ?>
                <div class="col-xl-4 col-md-8">
                    <label class="form-label fw-bold small">รพ.สต. / หน่วยบริการ</label>
                    <select name="hospital_id" class="rp-control">
                        <option value="">ทุกหน่วยบริการ</option>
                        <?php foreach ($hospitals as $h): ?>
                            <option value="<?= (int)$h['id'] ?>" <?= (int)$selected_hospital_id === (int)$h['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($h['hospital_code'] ? '['.$h['hospital_code'].'] ' : '').$h['name'],ENT_QUOTES,'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-bold small">ระดับพื้นที่</label>
                    <select name="level" class="rp-control">
                        <?php foreach ($levelLabels as $key=>$label): ?>
                            <option value="<?= $key ?>" <?= $area_level === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-bold small">ตัวชี้วัด</label>
                    <select name="metric" class="rp-control">
                        <option value="">ทุกระเบียนที่มีพื้นที่</option>
                        <?php
                        $seenMetrics = [];
                        foreach ($metric_options as $opt):
                            $m = (string)$opt['metric_code'];
                            if (isset($seenMetrics[$m])) continue;
                            $seenMetrics[$m] = true;
                        ?>
                            <option value="<?= htmlspecialchars($m,ENT_QUOTES,'UTF-8') ?>" <?= $metric_code === $m ? 'selected' : '' ?>>
                                <?= htmlspecialchars(data43_metric_label($m),ENT_QUOTES,'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <button class="rp-btn rp-btn--primary w-100" type="submit">
                        <i class="bi bi-funnel"></i> วิเคราะห์
                    </button>
                </div>
            </form>
        </div>
    </section>

    <div class="data43-spatial-kpis mb-3">
        <div class="data43-spatial-kpi">
            <div class="data43-spatial-kpi__label">พื้นที่ที่พบ</div>
            <div class="data43-spatial-kpi__value"><?= number_format((int)($spatial_summary['areas'] ?? 0)) ?></div>
            <div class="small text-muted"><?= htmlspecialchars($levelLabels[$area_level] ?? $area_level,ENT_QUOTES,'UTF-8') ?></div>
        </div>
        <div class="data43-spatial-kpi">
            <div class="data43-spatial-kpi__label">Records เชิงพื้นที่</div>
            <div class="data43-spatial-kpi__value"><?= number_format((int)($spatial_summary['records'] ?? 0)) ?></div>
            <div class="small text-muted"><?= htmlspecialchars(data43_metric_label($metric_code),ENT_QUOTES,'UTF-8') ?></div>
        </div>
        <div class="data43-spatial-kpi">
            <div class="data43-spatial-kpi__label">รพ.สต. ที่มีข้อมูล</div>
            <div class="data43-spatial-kpi__value"><?= number_format((int)($spatial_summary['hospitals'] ?? 0)) ?></div>
        </div>
        <div class="data43-spatial-kpi">
            <div class="data43-spatial-kpi__label">พื้นที่มี centroid</div>
            <div class="data43-spatial-kpi__value"><?= number_format((int)($spatial_summary['geocoded_areas'] ?? 0)) ?></div>
            <div class="small text-muted">เก็บแบบ Aggregate</div>
        </div>
    </div>

    <div class="data43-analysis-grid mb-3">
        <section class="rp-card">
            <div class="rp-card__header">
                <div>
                    <h2 class="rp-card__title mb-1">อันดับพื้นที่ตามปริมาณข้อมูล</h2>
                    <div class="small text-muted">Top 15 ของระดับ <?= htmlspecialchars($levelLabels[$area_level] ?? $area_level,ENT_QUOTES,'UTF-8') ?> · แสดง small cell เป็น &lt;5</div>
                </div>
            </div>
            <div class="rp-card__body">
                <?php if (empty($spatial_rows)): ?>
                    <div class="data43-empty">
                        <i class="bi bi-bar-chart fs-2 d-block mb-2 opacity-50"></i>
                        ยังไม่มี Aggregate เชิงพื้นที่สำหรับเงื่อนไขนี้
                    </div>
                <?php else: ?>
                    <div class="data43-area-bars">
                        <?php foreach (array_slice($spatial_rows,0,15) as $row):
                            $value = (int)$row['metric_value'];
                            $pct = min(100, ($value / $maxMetric) * 100);
                        ?>
                            <div class="data43-area-row">
                                <div class="data43-area-name" title="<?= htmlspecialchars(data43_area_label($row,$area_level),ENT_QUOTES,'UTF-8') ?>">
                                    <?= htmlspecialchars(data43_area_label($row,$area_level),ENT_QUOTES,'UTF-8') ?>
                                </div>
                                <div class="data43-bar" aria-label="<?= htmlspecialchars(data43_area_label($row,$area_level),ENT_QUOTES,'UTF-8') ?>">
                                    <span style="width:<?= $pct ?>%"></span>
                                </div>
                                <div class="data43-area-value"><?= data43_privacy_count($value) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="rp-card">
            <div class="rp-card__header">
                <div>
                    <h2 class="rp-card__title mb-1">Coverage ราย รพ.สต.</h2>
                    <div class="small text-muted">จำนวนพื้นที่ที่ตรวจพบในระดับที่เลือก</div>
                </div>
            </div>
            <div class="rp-card__body">
                <?php if (empty($hospital_coverage)): ?>
                    <div class="data43-empty">ยังไม่มีข้อมูล Coverage</div>
                <?php else: ?>
                    <div class="data43-coverage-list">
                        <?php foreach (array_slice($hospital_coverage,0,20) as $row):
                            $pct = min(100, ((int)$row['area_count'] / $maxCoverage) * 100);
                        ?>
                            <div class="data43-coverage-item">
                                <div class="data43-coverage-head">
                                    <div class="data43-coverage-name" title="<?= htmlspecialchars($row['hospital_name'],ENT_QUOTES,'UTF-8') ?>">
                                        <?= htmlspecialchars($row['hospital_name'],ENT_QUOTES,'UTF-8') ?>
                                    </div>
                                    <div class="data43-coverage-meta"><?= (int)$row['area_count'] ?> พื้นที่</div>
                                </div>
                                <div class="data43-coverage-bar"><span style="width:<?= $pct ?>%"></span></div>
                                <div class="small text-muted mt-1"><?= data43_privacy_count((int)$row['metric_value']) ?> records</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="rp-card mb-3">
        <div class="rp-card__header">
            <div>
                <h2 class="rp-card__title mb-1">รายละเอียดตามพื้นที่</h2>
                <div class="small text-muted">แสดงรหัสพื้นที่จากข้อมูลต้นทาง จนกว่าจะเชื่อม Master จังหวัด–อำเภอ–ตำบล–หมู่บ้านอย่างเป็นทางการ</div>
            </div>
        </div>
        <div class="rp-card__body p-0">
            <div class="data43-spatial-table-wrap">
                <table class="rp-table data43-spatial-table mb-0">
                    <thead>
                        <tr>
                            <th>พื้นที่</th>
                            <th class="text-end">Records</th>
                            <th class="text-center">รพ.สต.</th>
                            <th class="text-center">แหล่งแฟ้ม</th>
                            <th class="text-center">Centroid</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($spatial_rows as $row): ?>
                        <tr>
                            <td>
                                <span class="data43-code">
                                    <i class="bi bi-geo-alt"></i>
                                    <?= htmlspecialchars(data43_area_label($row,$area_level),ENT_QUOTES,'UTF-8') ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold"><?= data43_privacy_count((int)$row['metric_value']) ?></td>
                            <td class="text-center"><?= (int)$row['hospital_count'] ?></td>
                            <td class="text-center"><?= (int)$row['source_file_count'] ?></td>
                            <td class="text-center">
                                <?php if (!empty($row['centroid_lat']) && !empty($row['centroid_lng'])): ?>
                                    <span class="data43-coord-badge text-success"><i class="bi bi-check-circle-fill"></i> มี</span>
                                <?php else: ?>
                                    <span class="data43-coord-badge text-muted"><i class="bi bi-dash-circle"></i> ไม่มี</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($spatial_rows)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">ยังไม่มีข้อมูลพื้นที่</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="rp-card mb-3">
        <div class="rp-card__header">
            <div>
                <h2 class="rp-card__title mb-1">วิธีอ่าน Dashboard</h2>
                <div class="small text-muted">เพื่อให้การวิเคราะห์เชิงพื้นที่ไม่ตีความข้อมูลเกินกว่าที่ระบบมีจริง</div>
            </div>
        </div>
        <div class="rp-card__body">
            <div class="data43-method-card">
                <div class="data43-method-step">
                    <strong>1. ปริมาณข้อมูล</strong>
                    <div class="small text-muted">เปรียบเทียบจำนวน Records ในแต่ละพื้นที่ ไม่ได้หมายถึงจำนวนประชากรจริงโดยอัตโนมัติ</div>
                </div>
                <div class="data43-method-step">
                    <strong>2. Coverage</strong>
                    <div class="small text-muted">แสดงจำนวนพื้นที่ที่แต่ละ รพ.สต. มีข้อมูลใน ZIP ล่าสุดของเดือน</div>
                </div>
                <div class="data43-method-step">
                    <strong>3. Centroid</strong>
                    <div class="small text-muted">เก็บไว้เพื่อรองรับ GeoJSON Map ภายหลัง แต่ยังไม่ใช้วาดเป็นแผนที่จริงในรุ่นนี้</div>
                </div>
            </div>
        </div>
    </section>

    <div class="rp-alert rp-alert--info mb-0">
        <span class="rp-alert__icon"><i class="bi bi-shield-lock"></i></span>
        <div class="rp-alert__content">
            Dashboard ใช้ข้อมูล Aggregate เท่านั้น และแสดงค่าพื้นที่ย่อยที่มีจำนวนต่ำกว่า 5 เป็น &lt;5
            เพื่อลดความเสี่ยงการระบุตัวบุคคลจากข้อมูลสุขภาพเชิงพื้นที่
        </div>
    </div>
</div>
