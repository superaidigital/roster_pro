<?php
$spatial_rows = $spatial_rows ?? [];
$hospital_coverage = $hospital_coverage ?? [];
$metric_options = $metric_options ?? [];
$spatial_summary = $spatial_summary ?? [];
$hospitals = $hospitals ?? [];
$is_admin = $is_admin ?? false;
$selected_hospital_id = $selected_hospital_id ?? null;
$report_month = $report_month ?? date('Y-m');
$area_level = $area_level ?? 'CHANGWAT';
$metric_code = $metric_code ?? 'DM';
$display_mode = $display_mode ?? 'rate';
$ampur_code = $ampur_code ?? null;
$tambon_code = $tambon_code ?? null;

$metricDefinitions = Data43MetricRegistry::all();
$metricLabels = [];
foreach ($metricDefinitions as $code => $definition) {
    $metricLabels[$code] = (string)$definition['label'];
}
$metric_definition = $metric_definition ?? (Data43MetricRegistry::get($metric_code) ?? Data43MetricRegistry::get('DM'));
$metric_unit = (string)($metric_definition['unit'] ?? '');
$rate_mode_supported = Data43MetricRegistry::canUseRateMode($metric_code);

$levelLabels = [
    'CHANGWAT' => 'จังหวัด',
    'AMPUR' => 'อำเภอ',
    'TAMBON' => 'ตำบล',
    'VILLAGE' => 'หมู่บ้าน',
];

function data43_privacy_count(int $value, bool $suppressed = false): string {
    if ($suppressed) return '&lt;5 / ปกปิด';
    return number_format($value);
}

function data43_area_code(array $row, string $level): string {
    $cw = (string)($row['changwat_code'] ?? '');
    $ap = (string)($row['ampur_code'] ?? '');
    $tb = (string)($row['tambon_code'] ?? '');
    $vl = (string)($row['village_code'] ?? '');

    return match ($level) {
        'CHANGWAT' => $cw,
        'AMPUR' => $cw . $ap,
        'TAMBON' => $cw . $ap . $tb,
        'VILLAGE' => $cw . $ap . $tb . $vl,
        default => '',
    };
}

function data43_area_label(array $row, string $level): string {
    $code = data43_area_code($row, $level);
    return match ($level) {
        'CHANGWAT' => 'จังหวัด รหัส ' . ($code ?: '--'),
        'AMPUR' => 'อำเภอ รหัส ' . ($code ?: '--'),
        'TAMBON' => 'ตำบล รหัส ' . ($code ?: '--'),
        'VILLAGE' => 'หมู่บ้าน รหัส ' . ($code ?: '--'),
        default => $code,
    };
}

$mapRows = array_map(static function(array $row) use ($area_level): array {
    return [
        'key' => 'TH' . data43_area_code($row, $area_level),
        'changwat' => (string)($row['changwat_code'] ?? ''),
        'ampur' => (string)($row['ampur_code'] ?? ''),
        'tambon' => (string)($row['tambon_code'] ?? ''),
        'village' => (string)($row['village_code'] ?? ''),
        'count' => (int)($row['metric_value'] ?? 0),
        'population' => (int)($row['population_value'] ?? 0),
        'denominator' => (int)($row['denominator_value'] ?? 0),
        'value' => $row['display_value'] !== null ? (float)$row['display_value'] : null,
        'suppressed' => !empty($row['privacy_suppressed']),
        'privacy_reason' => $row['privacy_reason'] ?? null,
        'lat' => $row['centroid_lat'] !== null ? (float)$row['centroid_lat'] : null,
        'lng' => $row['centroid_lng'] !== null ? (float)$row['centroid_lng'] : null,
    ];
}, $spatial_rows);

$queryBase = [
    'c' => 'data43',
    'a' => 'spatial',
    'month' => $report_month,
    'metric' => $metric_code,
    'mode' => $display_mode,
];
if ($selected_hospital_id) $queryBase['hospital_id'] = (int)$selected_hospital_id;

$provinceUrl = 'index.php?' . http_build_query(array_merge($queryBase, ['level' => 'CHANGWAT']));
$districtUrl = 'index.php?' . http_build_query(array_merge($queryBase, ['level' => 'AMPUR']));
$tambonUrl = 'index.php?' . http_build_query(array_merge($queryBase, [
    'level' => 'TAMBON',
    'ampur' => $ampur_code,
]));
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIINfQ3ynw/TJfMQe7XxTxgY2uJ+4Z6K7qE=" crossorigin="">
<style>
.data43-spatial-page{min-width:0}
.data43-filter-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.75rem;align-items:end}
.data43-map-layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(300px,.75fr);gap:1rem}
.data43-map-card{overflow:hidden}
#data43SpatialMap{height:610px;width:100%;background:#eef2f7}
.data43-legend{display:grid;gap:.4rem;font-size:.78rem}
.data43-legend-row{display:flex;align-items:center;gap:.55rem}
.data43-swatch{width:18px;height:12px;border-radius:3px;border:1px solid rgba(15,23,42,.12)}
.data43-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}
.data43-kpi{padding:1rem;border:1px solid var(--rp-border);border-radius:1rem;background:#fff}
.data43-kpi__label{font-size:.72rem;color:var(--rp-text-muted);font-weight:700}
.data43-kpi__value{font-size:1.65rem;font-weight:800;line-height:1.15;margin-top:.25rem}
.data43-breadcrumb{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;font-size:.84rem}
.data43-breadcrumb a{text-decoration:none;font-weight:700}
.data43-mode{display:flex;border:1px solid var(--rp-border);border-radius:.75rem;overflow:hidden}
.data43-mode a{flex:1;padding:.55rem .65rem;text-align:center;text-decoration:none;font-weight:700;color:var(--rp-text)}
.data43-mode a.active{background:var(--rp-primary,#1677ff);color:#fff}
.data43-table-wrap{overflow:auto;max-height:520px}
.data43-table{min-width:850px}
.data43-table thead th{position:sticky;top:0;background:#fff;z-index:2}
.data43-rank{display:grid;gap:.72rem}
.data43-rank-row{display:grid;grid-template-columns:minmax(110px,1fr) 2fr auto;gap:.6rem;align-items:center}
.data43-rank-bar{height:9px;border-radius:999px;background:#e2e8f0;overflow:hidden}
.data43-rank-bar span{display:block;height:100%;background:var(--rp-primary,#1677ff);border-radius:inherit}
.data43-map-note{font-size:.78rem;color:var(--rp-text-muted);line-height:1.55}
.leaflet-container{font-family:inherit}
.leaflet-popup-content{line-height:1.55}
@media(max-width:1199px){
  .data43-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
  .data43-map-layout{grid-template-columns:1fr}
}
@media(max-width:767px){
  .data43-filter-grid{grid-template-columns:1fr 1fr}
  .data43-kpis{grid-template-columns:1fr 1fr}
  #data43SpatialMap{height:480px}
  .data43-rank-row{grid-template-columns:1fr auto}
  .data43-rank-bar{grid-column:1/-1}
}
@media(max-width:480px){
  .data43-filter-grid{grid-template-columns:1fr}
  .data43-kpis{grid-template-columns:1fr}
}
</style>

<div class="rp-page data43-spatial-page">
    <div class="rp-page-header mb-3">
        <div>
            <div class="rp-page-header__eyebrow">SPATIAL HEALTH ANALYTICS</div>
            <h1 class="rp-page-header__title">วิเคราะห์ข้อมูล 43 แฟ้มเชิงพื้นที่</h1>
            <p class="rp-page-header__subtitle mb-0">
                แผนที่เชิงพื้นที่แบบ Aggregate จังหวัด → อำเภอ → ตำบล → หมู่บ้าน โดยไม่แสดงหมุดรายบุคคล
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
                กรุณารัน <code>database/migrations/20261007_data43_spatial_analytics.sql</code>
                และ <code>20261007_data43_spatial_choropleth.sql</code> ก่อนใช้งาน
            </div>
        </div>
    <?php endif; ?>

    <section class="rp-card mb-3">
        <div class="rp-card__body">
            <form method="GET" action="index.php" class="data43-filter-grid">
                <input type="hidden" name="c" value="data43">
                <input type="hidden" name="a" value="spatial">
                <?php if ($ampur_code): ?><input type="hidden" name="ampur" value="<?= htmlspecialchars($ampur_code,ENT_QUOTES,'UTF-8') ?>"><?php endif; ?>
                <?php if ($tambon_code): ?><input type="hidden" name="tambon" value="<?= htmlspecialchars($tambon_code,ENT_QUOTES,'UTF-8') ?>"><?php endif; ?>

                <div>
                    <label class="form-label fw-bold small">รอบเดือน</label>
                    <input type="month" name="month" class="rp-control" value="<?= htmlspecialchars($report_month,ENT_QUOTES,'UTF-8') ?>">
                </div>

                <?php if ($is_admin): ?>
                <div>
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

                <div>
                    <label class="form-label fw-bold small">ตัวชี้วัด</label>
                    <select name="metric" class="rp-control">
                        <?php foreach ($metricLabels as $code=>$label): ?>
                            <option value="<?= $code ?>" <?= $metric_code === $code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label fw-bold small">ระดับพื้นที่</label>
                    <select name="level" class="rp-control">
                        <?php foreach ($levelLabels as $code=>$label): ?>
                            <option value="<?= $code ?>" <?= $area_level === $code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label fw-bold small">การไล่สี</label>
                    <select name="mode" class="rp-control">
                        <?php if ($rate_mode_supported): ?>
                            <option value="rate" <?= $display_mode === 'rate' ? 'selected' : '' ?>><?= htmlspecialchars($metric_unit,ENT_QUOTES,'UTF-8') ?></option>
                        <?php endif; ?>
                        <option value="count" <?= $display_mode === 'count' ? 'selected' : '' ?>>จำนวน</option>
                    </select>
                </div>

                <div>
                    <button class="rp-btn rp-btn--primary w-100" type="submit"><i class="bi bi-funnel"></i> วิเคราะห์</button>
                </div>
            </form>
        </div>
    </section>

    <div class="data43-breadcrumb mb-3">
        <i class="bi bi-diagram-3"></i>
        <a href="<?= htmlspecialchars($provinceUrl,ENT_QUOTES,'UTF-8') ?>">จังหวัดศรีสะเกษ</a>
        <?php if (in_array($area_level,['TAMBON','VILLAGE'],true) && $ampur_code): ?>
            <span>›</span>
            <a href="<?= htmlspecialchars($districtUrl,ENT_QUOTES,'UTF-8') ?>">อำเภอทั้งหมด</a>
            <span>›</span>
            <a href="<?= htmlspecialchars($tambonUrl,ENT_QUOTES,'UTF-8') ?>">อำเภอ <?= htmlspecialchars($ampur_code,ENT_QUOTES,'UTF-8') ?></a>
        <?php elseif ($area_level === 'AMPUR'): ?>
            <span>› อำเภอ</span>
        <?php endif; ?>
        <?php if ($area_level === 'VILLAGE' && $tambon_code): ?>
            <span>› ตำบล <?= htmlspecialchars($tambon_code,ENT_QUOTES,'UTF-8') ?> › หมู่บ้าน</span>
        <?php elseif ($area_level === 'TAMBON'): ?>
            <span>› ตำบล</span>
        <?php endif; ?>
    </div>

    <div class="data43-kpis mb-3">
        <div class="data43-kpi">
            <div class="data43-kpi__label"><?= htmlspecialchars($metricLabels[$metric_code] ?? $metric_code,ENT_QUOTES,'UTF-8') ?></div>
            <div class="data43-kpi__value"><?= number_format((int)($spatial_summary['records'] ?? 0)) ?></div>
            <div class="small text-muted">จำนวนรวม</div>
        </div>
        <div class="data43-kpi">
            <div class="data43-kpi__label">ประชากรฐาน</div>
            <div class="data43-kpi__value"><?= number_format((int)($spatial_summary['population'] ?? 0)) ?></div>
            <div class="small text-muted">จากแฟ้ม PERSON</div>
        </div>
        <div class="data43-kpi">
            <div class="data43-kpi__label"><?= htmlspecialchars($metric_unit ?: 'ค่าตัวชี้วัด',ENT_QUOTES,'UTF-8') ?></div>
            <div class="data43-kpi__value"><?= $spatial_summary['display_value'] !== null ? number_format((float)$spatial_summary['display_value'],2) : '–' ?></div>
            <div class="small text-muted"><?= htmlspecialchars((string)($metric_definition['calculation'] ?? 'COUNT'),ENT_QUOTES,'UTF-8') ?></div>
        </div>
        <div class="data43-kpi">
            <div class="data43-kpi__label">พื้นที่ที่มีข้อมูล</div>
            <div class="data43-kpi__value"><?= number_format((int)($spatial_summary['areas'] ?? 0)) ?></div>
            <div class="small text-muted"><?= htmlspecialchars($levelLabels[$area_level] ?? $area_level,ENT_QUOTES,'UTF-8') ?></div>
        </div>
    </div>

    <div class="data43-map-layout mb-3">
        <section class="rp-card data43-map-card">
            <div class="rp-card__header">
                <div>
                    <h2 class="rp-card__title mb-1">
                        แผนที่ <?= htmlspecialchars($metricLabels[$metric_code] ?? $metric_code,ENT_QUOTES,'UTF-8') ?>
                    </h2>
                    <div class="small text-muted">
                        สีเข้ม = <?= $display_mode === 'rate' ? 'ค่าตัวชี้วัดสูงกว่า' : 'จำนวนสูงกว่า' ?>
                        · คลิกพื้นที่เพื่อเจาะลึกระดับถัดไป
                    </div>
                </div>
            </div>
            <div id="data43SpatialMap" role="img" aria-label="แผนที่วิเคราะห์ข้อมูลสุขภาพเชิงพื้นที่"></div>
        </section>

        <section class="rp-card">
            <div class="rp-card__header">
                <div>
                    <h2 class="rp-card__title mb-1">คำอธิบายสี</h2>
                    <div class="small text-muted"><?= $display_mode === 'rate' ? $metric_unit : 'จำนวน' ?></div>
                </div>
            </div>
            <div class="rp-card__body">
                <div id="data43Legend" class="data43-legend mb-3"></div>
                <hr>
                <div class="data43-map-note">
                    <strong>PDPA:</strong> ระบบไม่แสดงตำแหน่งบุคคล บ้าน หรือพิกัดรายคน
                    และค่าพื้นที่ที่มีจำนวน 1–4 รายจะแสดงเป็น <strong>&lt;5</strong> พร้อมไม่ใช้ค่าจริงในการไล่สี
                </div>
                <?php if ($area_level === 'VILLAGE'): ?>
                    <div class="rp-alert rp-alert--info mt-3 mb-0">
                        ระดับหมู่บ้านใช้วงพื้นที่ Aggregate จาก centroid ของข้อมูลในหมู่บ้าน
                        เนื่องจากชุด GeoJSON ที่มากับระบบครอบคลุมถึงระดับตำบล
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="data43-map-layout mb-3">
        <section class="rp-card">
            <div class="rp-card__header">
                <div>
                    <h2 class="rp-card__title mb-1">อันดับพื้นที่</h2>
                    <div class="small text-muted">เรียงตาม <?= $display_mode === 'rate' ? 'อัตรา' : 'จำนวน' ?> สูงสุด</div>
                </div>
            </div>
            <div class="rp-card__body">
                <?php
                $rankRows = $spatial_rows;
                usort($rankRows, static function(array $a,array $b) use ($display_mode): int {
                    $av = $display_mode === 'rate' ? (float)($a['display_value'] ?? -1) : (int)($a['metric_value'] ?? 0);
                    $bv = $display_mode === 'rate' ? (float)($b['display_value'] ?? -1) : (int)($b['metric_value'] ?? 0);
                    return $bv <=> $av;
                });
                $maxRank = 1.0;
                foreach ($rankRows as $r) {
                    $v = $display_mode === 'rate' ? (float)($r['display_value'] ?? 0) : (float)($r['metric_value'] ?? 0);
                    if ((int)($r['metric_value'] ?? 0) >= 5) $maxRank = max($maxRank,$v);
                }
                ?>
                <div class="data43-rank">
                    <?php foreach (array_slice($rankRows,0,15) as $row):
                        $count = (int)($row['metric_value'] ?? 0);
                        $value = $display_mode === 'rate' ? (float)($row['display_value'] ?? 0) : (float)$count;
                        $pct = $count >= 5 ? min(100,($value/$maxRank)*100) : 0;
                    ?>
                    <div class="data43-rank-row">
                        <div class="fw-bold small"><?= htmlspecialchars(data43_area_label($row,$area_level),ENT_QUOTES,'UTF-8') ?></div>
                        <div class="data43-rank-bar"><span style="width:<?= $pct ?>%"></span></div>
                        <div class="fw-bold text-end">
                            <?php if (!empty($row['privacy_suppressed'])): ?>
                                &lt;5 / ปกปิด
                            <?php elseif ($display_mode === 'rate'): ?>
                                <?= $row['display_value'] !== null ? number_format((float)$row['display_value'],2) : '–' ?>
                            <?php else: ?>
                                <?= number_format($count) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($rankRows)): ?><div class="text-center text-muted py-4">ยังไม่มีข้อมูลในเงื่อนไขนี้</div><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="rp-card">
            <div class="rp-card__header">
                <div>
                    <h2 class="rp-card__title mb-1">Coverage ราย รพ.สต.</h2>
                    <div class="small text-muted"><?= htmlspecialchars($metricLabels[$metric_code] ?? $metric_code,ENT_QUOTES,'UTF-8') ?></div>
                </div>
            </div>
            <div class="rp-card__body">
                <?php if (empty($hospital_coverage)): ?>
                    <div class="text-center text-muted py-4">ยังไม่มี Coverage</div>
                <?php else: ?>
                    <?php foreach (array_slice($hospital_coverage,0,15) as $row): ?>
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <span class="text-truncate"><?= htmlspecialchars($row['hospital_name'],ENT_QUOTES,'UTF-8') ?></span>
                            <strong><?= data43_privacy_count((int)$row['metric_value']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="rp-card mb-3">
        <div class="rp-card__header">
            <div>
                <h2 class="rp-card__title mb-1">รายละเอียดตามพื้นที่</h2>
                <div class="small text-muted">จำนวน ประชากรฐาน และอัตราต่อ 1,000 คน</div>
            </div>
        </div>
        <div class="rp-card__body p-0">
            <div class="data43-table-wrap">
                <table class="rp-table data43-table mb-0">
                    <thead>
                        <tr>
                            <th>รหัสพื้นที่</th>
                            <th class="text-end">จำนวน</th>
                            <th class="text-end">ประชากรฐาน</th>
                            <th class="text-end">อัตรา / 1,000</th>
                            <th class="text-center">รพ.สต.</th>
                            <th class="text-center">แหล่งแฟ้ม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($spatial_rows as $row):
                            $count = (int)($row['metric_value'] ?? 0);
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars(data43_area_code($row,$area_level),ENT_QUOTES,'UTF-8') ?></strong></td>
                            <td class="text-end fw-bold"><?= data43_privacy_count($count, !empty($row['privacy_suppressed'])) ?></td>
                            <td class="text-end"><?= number_format((int)($row['population_value'] ?? 0)) ?></td>
                            <td class="text-end">
                                <?= !empty($row['privacy_suppressed']) ? '&lt;5 / ปกปิด' : ($row['display_value'] !== null ? number_format((float)$row['display_value'],2) : '–') ?>
                            </td>
                            <td class="text-center"><?= (int)($row['hospital_count'] ?? 0) ?></td>
                            <td class="text-center"><?= (int)($row['source_file_count'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($spatial_rows)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5">ยังไม่มีข้อมูลพื้นที่สำหรับเงื่อนไขนี้</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(() => {
    const rows = <?= json_encode($mapRows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const level = <?= json_encode($area_level) ?>;
    const metric = <?= json_encode($metric_code) ?>;
    const mode = <?= json_encode($display_mode) ?>;
    const month = <?= json_encode($report_month) ?>;
    const hospitalId = <?= json_encode($selected_hospital_id ? (int)$selected_hospital_id : null) ?>;
    const selectedAmpur = <?= json_encode($ampur_code) ?>;
    const selectedTambon = <?= json_encode($tambon_code) ?>;

    if (typeof L === 'undefined') return;

    const map = L.map('data43SpatialMap', {
        zoomControl: true,
        attributionControl: true
    }).setView([15.12, 104.33], 9);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const byKey = new Map(rows.map(r => [r.key, r]));
    const visibleValues = rows
        .filter(r => !r.suppressed)
        .map(r => mode === 'rate' ? Number(r.value ?? 0) : Number(r.count ?? 0))
        .filter(Number.isFinite);

    const maxValue = visibleValues.length ? Math.max(...visibleValues, 0) : 0;
    const breaks = [0, .2, .4, .6, .8, 1].map(x => maxValue * x);
    const colors = ['#eff6ff','#bfdbfe','#60a5fa','#2563eb','#1d4ed8'];
    const noData = '#e5e7eb';

    function colorFor(row) {
        if (!row || row.suppressed || maxValue <= 0) return noData;
        const value = mode === 'rate' ? Number(row.rate ?? 0) : Number(row.count ?? 0);
        if (value <= breaks[1]) return colors[0];
        if (value <= breaks[2]) return colors[1];
        if (value <= breaks[3]) return colors[2];
        if (value <= breaks[4]) return colors[3];
        return colors[4];
    }

    function privacyText(row) {
        if (!row) return 'ไม่มีข้อมูล';
        if (row.suppressed) return '&lt;5';
        if (mode === 'rate') return row.value == null ? '–' : Number(row.value).toLocaleString('th-TH',{maximumFractionDigits:2});
        return Number(row.count).toLocaleString('th-TH');
    }

    function nextUrl(props) {
        const q = new URLSearchParams({c:'data43', a:'spatial', month, metric, mode});
        if (hospitalId) q.set('hospital_id', String(hospitalId));

        if (level === 'CHANGWAT') {
            q.set('level','AMPUR');
        } else if (level === 'AMPUR') {
            const pcode = String(props.ADM2_PCODE || '');
            q.set('level','TAMBON');
            q.set('ampur', pcode.slice(-2));
        } else if (level === 'TAMBON') {
            const pcode = String(props.ADM3_PCODE || '');
            q.set('level','VILLAGE');
            q.set('ampur', pcode.slice(4,6));
            q.set('tambon', pcode.slice(-2));
        } else {
            return null;
        }
        return 'index.php?' + q.toString();
    }

    function nameFor(props) {
        if (level === 'CHANGWAT') return props.ADM1_TH || props.ADM1_EN || 'จังหวัด';
        if (level === 'AMPUR') return props.ADM2_TH || props.ADM2_EN || 'อำเภอ';
        return props.ADM3_TH || props.ADM3_EN || 'ตำบล';
    }

    function featureKey(props) {
        if (level === 'CHANGWAT') return String(props.ADM1_PCODE || '');
        if (level === 'AMPUR') return String(props.ADM2_PCODE || '');
        return String(props.ADM3_PCODE || '');
    }

    function featureAllowed(props) {
        if (level === 'TAMBON' && selectedAmpur) {
            const p = String(props.ADM2_PCODE || '');
            return p.endsWith(selectedAmpur);
        }
        return true;
    }

    function renderLegend() {
        const el = document.getElementById('data43Legend');
        if (!el) return;
        const rowsHtml = [];
        if (maxValue <= 0) {
            rowsHtml.push('<div class="data43-legend-row"><span class="data43-swatch" style="background:'+noData+'"></span>ยังไม่มีข้อมูลที่แสดงสีได้</div>');
        } else {
            for (let i=0;i<5;i++) {
                const lo = breaks[i];
                const hi = breaks[i+1];
                const fmt = v => mode === 'rate' ? Number(v).toFixed(2) : Math.round(v).toLocaleString('th-TH');
                rowsHtml.push('<div class="data43-legend-row"><span class="data43-swatch" style="background:'+colors[i]+'"></span>'+fmt(lo)+' – '+fmt(hi)+'</div>');
            }
        }
        rowsHtml.push('<div class="data43-legend-row"><span class="data43-swatch" style="background:'+noData+'"></span>ไม่มีข้อมูล / จำนวน &lt;5</div>');
        el.innerHTML = rowsHtml.join('');
    }

    renderLegend();

    if (level === 'VILLAGE') {
        const bounds = [];
        rows.forEach(row => {
            if (selectedAmpur && row.ampur !== selectedAmpur) return;
            if (selectedTambon && row.tambon !== selectedTambon) return;
            if (!Number.isFinite(row.lat) || !Number.isFinite(row.lng)) return;

            const circle = L.circle([row.lat,row.lng], {
                radius: 900,
                color: '#64748b',
                weight: 1,
                fillColor: colorFor(row),
                fillOpacity: .78
            }).addTo(map);

            circle.bindPopup(
                '<strong>หมู่ '+row.village+'</strong><br>'+
                'จำนวน: '+(row.suppressed ? '&lt;5' : Number(row.count).toLocaleString('th-TH'))+'<br>'+
                'ประชากรฐาน: '+Number(row.population).toLocaleString('th-TH')+'<br>'+
                'อัตรา/1,000: '+(row.suppressed ? '&lt;5' : (row.value == null ? '–' : Number(row.value).toFixed(2)))
            );
            bounds.push([row.lat,row.lng]);
        });
        if (bounds.length) map.fitBounds(bounds,{padding:[30,30],maxZoom:13});
        return;
    }

    const geoUrl = level === 'CHANGWAT'
        ? 'public/geo/sisaket/adm1.geojson'
        : (level === 'AMPUR' ? 'public/geo/sisaket/adm2.geojson' : 'public/geo/sisaket/adm3.geojson');

    fetch(geoUrl,{credentials:'same-origin'})
        .then(r => {
            if (!r.ok) throw new Error('โหลดขอบเขตแผนที่ไม่สำเร็จ');
            return r.json();
        })
        .then(geo => {
            const filtered = {
                type:'FeatureCollection',
                features:(geo.features || []).filter(f => featureAllowed(f.properties || {}))
            };

            const layer = L.geoJSON(filtered,{
                style: feature => {
                    const row = byKey.get(featureKey(feature.properties || {}));
                    return {
                        color:'#ffffff',
                        weight:1.3,
                        fillColor:colorFor(row),
                        fillOpacity:.8
                    };
                },
                onEachFeature: (feature, shape) => {
                    const props = feature.properties || {};
                    const key = featureKey(props);
                    const row = byKey.get(key);
                    const countText = !row ? '0' : (row.suppressed ? '&lt;5' : Number(row.count).toLocaleString('th-TH'));
                    const rateText = !row || row.value == null ? '–' : (row.suppressed ? '&lt;5' : Number(row.value).toFixed(2));
                    const url = nextUrl(props);

                    shape.bindTooltip(nameFor(props),{sticky:true});
                    shape.bindPopup(
                        '<strong>'+nameFor(props)+'</strong><br>'+
                        'จำนวน: '+countText+'<br>'+
                        'ประชากรฐาน: '+(!row ? '0' : Number(row.population).toLocaleString('th-TH'))+'<br>'+
                        'อัตรา/1,000: '+rateText+
                        (url ? '<br><a href="'+url+'" class="fw-bold">เจาะลึกระดับถัดไป →</a>' : '')
                    );

                    shape.on({
                        mouseover: e => e.target.setStyle({weight:2.4,color:'#0f172a'}),
                        mouseout: e => layer.resetStyle(e.target),
                        click: () => {
                            if (url) window.location.href = url;
                        }
                    });
                }
            }).addTo(map);

            const b = layer.getBounds();
            if (b.isValid()) map.fitBounds(b,{padding:[18,18]});
        })
        .catch(err => {
            console.error(err);
            const el = document.getElementById('data43SpatialMap');
            if (el) el.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted">ไม่สามารถโหลดขอบเขตแผนที่ได้</div>';
        });
})();
</script>
