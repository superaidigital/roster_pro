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
        'HOUSEHOLD_RECORDS' => 'จำนวนระเบียนครัวเรือน',
        'ADDRESS_RECORDS' => 'จำนวนระเบียนที่อยู่',
        'PERSON_RECORDS' => 'จำนวนระเบียนบุคคล',
        default => $code === '' ? 'ระเบียนทั้งหมดที่มีพื้นที่' : $code,
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

$mapRows = [];
foreach ($spatial_rows as $row) {
    $lat = isset($row['centroid_lat']) ? (float)$row['centroid_lat'] : 0.0;
    $lng = isset($row['centroid_lng']) ? (float)$row['centroid_lng'] : 0.0;

    if ($lat === 0.0 || $lng === 0.0) continue;

    $mapRows[] = [
        'label' => data43_area_label($row, $area_level),
        'lat' => $lat,
        'lng' => $lng,
        'value' => (int)$row['metric_value'],
        'hospitals' => (int)$row['hospital_count'],
    ];
}

$mapJson = json_encode(
    $mapRows,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
);
?>
<style>
.data43-spatial-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem}
.data43-spatial-kpi{padding:1rem;border:1px solid var(--rp-border);border-radius:1rem;background:#fff}
.data43-spatial-kpi__label{font-size:.72rem;color:var(--rp-text-muted);font-weight:600}
.data43-spatial-kpi__value{font-size:1.7rem;line-height:1.15;font-weight:800;margin-top:.3rem}
.data43-map-wrap{position:relative;min-height:420px;border:1px solid var(--rp-border);border-radius:1rem;background:linear-gradient(180deg,#f8fafc,#fff);overflow:hidden}
.data43-map-svg{display:block;width:100%;height:420px}
.data43-map-empty{min-height:420px;display:grid;place-items:center;text-align:center;color:var(--rp-text-muted);padding:2rem}
.data43-map-legend{display:flex;gap:1rem;flex-wrap:wrap;font-size:.7rem;color:var(--rp-text-muted);margin-top:.65rem}
.data43-rank{display:flex;align-items:center;gap:.75rem;padding:.7rem 0;border-bottom:1px solid var(--rp-border)}
.data43-rank:last-child{border-bottom:0}
.data43-rank__num{width:2rem;height:2rem;display:grid;place-items:center;border-radius:.65rem;background:var(--rp-surface-muted,#f1f5f9);font-weight:800}
.data43-rank__copy{min-width:0;flex:1}
.data43-rank__value{font-weight:800;white-space:nowrap}
.data43-area-pill{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .55rem;border-radius:999px;border:1px solid var(--rp-border);background:#fff;font-size:.72rem}
.data43-coverage-bar{height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden}
.data43-coverage-bar>span{display:block;height:100%;background:#1677ff;border-radius:inherit}
@media(max-width:991px){.data43-spatial-kpis{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.data43-spatial-kpis{grid-template-columns:1fr 1fr}.data43-map-svg,.data43-map-empty{height:320px;min-height:320px}}
</style>

<div class="rp-page">
    <div class="rp-page-header mb-3">
        <div>
            <div class="rp-page-header__eyebrow">SPATIAL ANALYTICS</div>
            <h1 class="rp-page-header__title">วิเคราะห์ข้อมูล 43 แฟ้มเชิงพื้นที่</h1>
            <p class="rp-page-header__subtitle mb-0">
                วิเคราะห์ข้อมูลระดับอำเภอ ตำบล และหมู่บ้านจากค่า Aggregate โดยไม่แสดงข้อมูลประชาชนรายบุคคล
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
                ก่อน จากนั้นนำส่ง ZIP ใหม่เพื่อสร้าง Aggregate เชิงพื้นที่
            </div>
        </div>
    <?php endif; ?>

    <section class="rp-card mb-3">
        <div class="rp-card__body">
            <form method="GET" action="index.php" class="row g-2 align-items-end">
                <input type="hidden" name="c" value="data43">
                <input type="hidden" name="a" value="spatial">

                <div class="col-lg-2 col-md-4">
                    <label class="form-label fw-bold small">รอบเดือน</label>
                    <input type="month" name="month" class="rp-control" value="<?= htmlspecialchars($report_month,ENT_QUOTES,'UTF-8') ?>">
                </div>

                <?php if ($is_admin): ?>
                <div class="col-lg-4 col-md-8">
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

                <div class="col-lg-2 col-md-4">
                    <label class="form-label fw-bold small">ระดับพื้นที่</label>
                    <select name="level" class="rp-control">
                        <?php foreach ($levelLabels as $key=>$label): ?>
                            <option value="<?= $key ?>" <?= $area_level === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
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

                <div class="col-lg-2 col-md-4">
                    <button class="rp-btn rp-btn--primary w-100" type="submit">
                        <i class="bi bi-geo-alt"></i> วิเคราะห์
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
            <div class="data43-spatial-kpi__label">พื้นที่มีพิกัด Aggregate</div>
            <div class="data43-spatial-kpi__value"><?= number_format((int)($spatial_summary['geocoded_areas'] ?? 0)) ?></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <section class="rp-card h-100">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1">แผนภาพจุดเชิงพื้นที่</h2>
                        <div class="small text-muted">ตำแหน่งคือ centroid ของกลุ่มพื้นที่ ไม่ใช่พิกัดครัวเรือนรายหลังและไม่ใช่ขอบเขตแผนที่ราชการ</div>
                    </div>
                </div>
                <div class="rp-card__body">
                    <?php if (empty($mapRows)): ?>
                        <div class="data43-map-empty">
                            <div>
                                <i class="bi bi-geo-alt fs-1 d-block mb-2 opacity-50"></i>
                                ยังไม่มีค่า LATITUDE/LONGITUDE ที่สามารถสร้าง centroid ในระดับนี้ได้
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="data43-map-wrap">
                            <svg id="data43SpatialSvg" class="data43-map-svg" viewBox="0 0 900 420" role="img" aria-label="แผนภาพ centroid ของข้อมูลเชิงพื้นที่"></svg>
                        </div>
                        <div class="data43-map-legend">
                            <span><i class="bi bi-circle-fill me-1"></i> ขนาดจุดสัมพันธ์กับจำนวน Records</span>
                            <span><i class="bi bi-shield-check me-1"></i> ไม่แสดงจุดรายบุคคล</span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="rp-card h-100">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1">พื้นที่ที่มีข้อมูลสูงสุด</h2>
                        <div class="small text-muted">Top 10 · มีการซ่อน small cell ต่ำกว่า 5</div>
                    </div>
                </div>
                <div class="rp-card__body">
                    <?php foreach (array_slice($spatial_rows,0,10) as $i=>$row): ?>
                        <div class="data43-rank">
                            <div class="data43-rank__num"><?= $i+1 ?></div>
                            <div class="data43-rank__copy">
                                <div class="fw-bold"><?= htmlspecialchars(data43_area_label($row,$area_level),ENT_QUOTES,'UTF-8') ?></div>
                                <div class="small text-muted">รพ.สต. <?= (int)$row['hospital_count'] ?> แห่ง</div>
                            </div>
                            <div class="data43-rank__value"><?= data43_privacy_count((int)$row['metric_value']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($spatial_rows)): ?>
                        <div class="text-center py-4 text-muted">ยังไม่มีข้อมูลเชิงพื้นที่</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <section class="rp-card h-100">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1">รายละเอียดตามพื้นที่</h2>
                        <div class="small text-muted">ใช้รหัสพื้นที่จากข้อมูลต้นทาง เพื่อหลีกเลี่ยงการตีความชื่อพื้นที่ผิดจากรหัสที่ยังไม่ได้ผูก Master Data</div>
                    </div>
                </div>
                <div class="rp-card__body p-0">
                    <div class="table-responsive">
                        <table class="rp-table mb-0">
                            <thead>
                                <tr>
                                    <th>พื้นที่</th>
                                    <th class="text-end">Records</th>
                                    <th class="text-center">รพ.สต.</th>
                                    <th class="text-center">แหล่งแฟ้ม</th>
                                    <th class="text-center">พิกัด</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($spatial_rows as $row): ?>
                                <tr>
                                    <td><span class="data43-area-pill"><i class="bi bi-geo-alt"></i><?= htmlspecialchars(data43_area_label($row,$area_level),ENT_QUOTES,'UTF-8') ?></span></td>
                                    <td class="text-end fw-bold"><?= data43_privacy_count((int)$row['metric_value']) ?></td>
                                    <td class="text-center"><?= (int)$row['hospital_count'] ?></td>
                                    <td class="text-center"><?= (int)$row['source_file_count'] ?></td>
                                    <td class="text-center"><?= (!empty($row['centroid_lat']) && !empty($row['centroid_lng'])) ? '<span class="text-success">มี</span>' : '<span class="text-muted">-</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($spatial_rows)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">ยังไม่มี Aggregate เชิงพื้นที่สำหรับเงื่อนไขนี้</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            <section class="rp-card h-100">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1">Coverage ราย รพ.สต.</h2>
                        <div class="small text-muted">จำนวนพื้นที่ที่มีข้อมูลในระดับที่เลือก</div>
                    </div>
                </div>
                <div class="rp-card__body">
                    <?php
                    $maxCoverage = 1;
                    foreach ($hospital_coverage as $row) {
                        $maxCoverage = max($maxCoverage,(int)$row['area_count']);
                    }
                    foreach ($hospital_coverage as $row):
                        $pct = min(100,((int)$row['area_count']/$maxCoverage)*100);
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between gap-2 mb-1">
                                <div class="text-truncate fw-bold"><?= htmlspecialchars($row['hospital_name'],ENT_QUOTES,'UTF-8') ?></div>
                                <span class="small text-muted"><?= (int)$row['area_count'] ?> พื้นที่</span>
                            </div>
                            <div class="data43-coverage-bar"><span style="width:<?= $pct ?>%"></span></div>
                            <div class="small text-muted mt-1"><?= data43_privacy_count((int)$row['metric_value']) ?> records</div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($hospital_coverage)): ?>
                        <div class="text-center py-4 text-muted">ยังไม่มีข้อมูล Coverage</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <div class="rp-alert rp-alert--info mt-3 mb-0">
        <span class="rp-alert__icon"><i class="bi bi-shield-lock"></i></span>
        <div class="rp-alert__content">
            Dashboard นี้ใช้ข้อมูล Aggregate เท่านั้น และซ่อนค่าพื้นที่ย่อยที่มีจำนวนน้อยกว่า 5 รายการบนหน้าจอ
            เพื่อช่วยลดความเสี่ยงการระบุตัวบุคคลจากข้อมูลสุขภาพเชิงพื้นที่
        </div>
    </div>
</div>

<?php if (!empty($mapRows)): ?>
<script>
(function () {
    const svg = document.getElementById('data43SpatialSvg');
    if (!svg) return;

    const rows = <?= $mapJson ?: '[]' ?>;
    if (!Array.isArray(rows) || rows.length === 0) return;

    const NS = 'http://www.w3.org/2000/svg';
    const width = 900;
    const height = 420;
    const pad = 40;

    const lats = rows.map(r => Number(r.lat)).filter(Number.isFinite);
    const lngs = rows.map(r => Number(r.lng)).filter(Number.isFinite);
    const values = rows.map(r => Number(r.value)).filter(Number.isFinite);

    const minLat = Math.min(...lats);
    const maxLat = Math.max(...lats);
    const minLng = Math.min(...lngs);
    const maxLng = Math.max(...lngs);
    const maxValue = Math.max(1,...values);

    const spanLat = Math.max(0.0001,maxLat-minLat);
    const spanLng = Math.max(0.0001,maxLng-minLng);

    function x(lng){ return pad + ((lng-minLng)/spanLng)*(width-pad*2); }
    function y(lat){ return height-pad - ((lat-minLat)/spanLat)*(height-pad*2); }

    const grid = document.createElementNS(NS,'g');
    grid.setAttribute('stroke','#e2e8f0');
    grid.setAttribute('stroke-width','1');
    for(let i=1;i<5;i++){
        const gx = pad + ((width-pad*2)/5)*i;
        const gy = pad + ((height-pad*2)/5)*i;
        const vl = document.createElementNS(NS,'line');
        vl.setAttribute('x1',gx);vl.setAttribute('x2',gx);vl.setAttribute('y1',pad);vl.setAttribute('y2',height-pad);
        grid.appendChild(vl);
        const hl = document.createElementNS(NS,'line');
        hl.setAttribute('x1',pad);hl.setAttribute('x2',width-pad);hl.setAttribute('y1',gy);hl.setAttribute('y2',gy);
        grid.appendChild(hl);
    }
    svg.appendChild(grid);

    rows.forEach((row,index)=>{
        const value = Math.max(1,Number(row.value)||1);
        const r = Math.max(5,Math.min(24,5 + Math.sqrt(value/maxValue)*19));

        const circle = document.createElementNS(NS,'circle');
        circle.setAttribute('cx',x(Number(row.lng)));
        circle.setAttribute('cy',y(Number(row.lat)));
        circle.setAttribute('r',r);
        circle.setAttribute('fill','var(--rp-primary,#1677ff)');
        circle.setAttribute('fill-opacity','0.28');
        circle.setAttribute('stroke','var(--rp-primary,#1677ff)');
        circle.setAttribute('stroke-width','2');
        circle.setAttribute('tabindex','0');

        const title = document.createElementNS(NS,'title');
        title.textContent = row.label + ' · ' + (value < 5 ? '<5' : value.toLocaleString('th-TH')) + ' records';
        circle.appendChild(title);
        svg.appendChild(circle);

        if(rows.length <= 20){
            const label = document.createElementNS(NS,'text');
            label.setAttribute('x',x(Number(row.lng))+r+4);
            label.setAttribute('y',y(Number(row.lat))+4);
            label.setAttribute('font-size','10');
            label.setAttribute('fill','#475569');
            label.textContent = row.label;
            svg.appendChild(label);
        }
    });
})();
</script>
<?php endif; ?>
