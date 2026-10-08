<?php
$csrf_token = $csrf_token ?? ($_SESSION['csrf_token'] ?? '');
$patient_hospitals = $patient_hospitals ?? [];
$patient_view_enabled = !empty($patient_hospitals);
$geojson_status=$geojson_status??[];
$metric_definitions=$metric_definitions??[];
$report_month=$report_month??date('Y-m');
$selected_hospital_id=$selected_hospital_id??null;
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="assets/css/data43-thailand-map.css">

<div class="rp-page">
  <div class="rp-page-header mb-3">
    <div>
      <div class="rp-page-header__eyebrow">GIS · DATA43</div>
      <h1 class="rp-page-header__title">แผนที่ข้อมูลสุขภาพ 43 แฟ้ม</h1>
      <p class="rp-page-header__subtitle mb-0">Drill-down ประเทศไทย → จังหวัด → อำเภอ → ตำบล พร้อม Choropleth และการปกปิดข้อมูลพื้นที่เสี่ยงระบุตัวบุคคล</p>
    </div>
  </div>

  <?php if(in_array(false,$geojson_status,true)): ?>
    <div class="rp-alert rp-alert--warning mb-3">
      <span class="rp-alert__icon"><i class="bi bi-map"></i></span>
      <div class="rp-alert__content">
        GeoJSON ยังไม่ครบ กรุณาวาง
        <code>provinces.geojson</code>, <code>amphoes.geojson</code> และ <code>tambons.geojson</code>
        ใน <code>assets/geojson/thailand/</code> โดยใช้ CRS EPSG:4326
      </div>
    </div>
  <?php endif; ?>

  <div id="data43MapRoot"
       class="data43-map-page"
       data-patient-enabled="<?= $patient_view_enabled ? '1' : '0' ?>"
       data-csrf="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>"
       data-month="<?= htmlspecialchars($report_month,ENT_QUOTES,'UTF-8') ?>"
       data-hospital="<?= (int)($selected_hospital_id??0) ?>">

    <div class="data43-map-toolbar">
      <div class="data43-map-toolbar__title">
        <strong>แผนที่เชิงพื้นที่</strong>
        <small>OpenStreetMap + Leaflet.js</small>
      </div>

      <div class="data43-map-field">
        <label for="data43MapMonth">รอบข้อมูล</label>
        <input id="data43MapMonth" type="month" value="<?= htmlspecialchars($report_month,ENT_QUOTES,'UTF-8') ?>">
      </div>

      <div class="data43-map-field">
        <label for="data43MapMetric">ตัวชี้วัด</label>
        <select id="data43MapMetric">
          <?php foreach($metric_definitions as $code=>$def): ?>
            <option value="<?= htmlspecialchars($code,ENT_QUOTES,'UTF-8') ?>" <?= $code==='DM'?'selected':'' ?>>
              <?= htmlspecialchars((string)$def['label'],ENT_QUOTES,'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="data43-map-field">
        <label for="data43BoundaryMode">ระดับพื้นที่</label>
        <select id="data43BoundaryMode">
          <option value="province">จังหวัด</option>
          <option value="amphoe">อำเภอ</option>
          <option value="tambon">ตำบล</option>
        </select>
      </div>

      <div class="data43-map-search">
        <label for="data43MapSearch">ค้นหาพื้นที่</label>
        <div class="data43-map-search__box">
          <i class="bi bi-search"></i>
          <input id="data43MapSearch" type="search" placeholder="จังหวัด / อำเภอ / ตำบล" autocomplete="off">
        </div>
        <div id="data43MapSearchResults" class="data43-map-search__results"></div>
      </div>

      <button type="button" id="data43MapReset" class="rp-btn rp-btn--secondary">
        <i class="bi bi-house-door"></i> ประเทศไทย
      </button>
    </div>

    <div id="data43MapBreadcrumb" class="data43-map-breadcrumb" aria-label="เส้นทางพื้นที่">
      <button type="button" data-level="province" class="active">ประเทศไทย</button>
    </div>

    <div id="data43ThailandMap"></div>

    <div id="data43MapStatus" class="data43-map-status"></div>

    <div class="data43-map-panel">
      <div class="data43-map-panel__row">
        <span>ระดับ</span>
        <strong id="data43MapModeLabel">จังหวัด</strong>
      </div>
      <div class="data43-map-panel__row">
        <span>ตัวชี้วัด</span>
        <strong id="data43MapMetricLabel">เบาหวาน (DM)</strong>
      </div>
      <div id="data43MapSelection" class="data43-map-selection">ยังไม่ได้เลือกพื้นที่</div>
      <?php if($patient_view_enabled): ?>
      <form id="data43PatientDrillForm" method="POST" action="index.php?c=data43&a=map_patients" class="mt-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>">
        <input type="hidden" name="level" id="data43PatientLevel">
        <input type="hidden" name="code" id="data43PatientCode">
        <div class="mb-2"><label class="form-label">หน่วยบริการที่อนุญาต</label>
          <select name="hospital_id" class="rp-control" required>
            <?php foreach($patient_hospitals as $h): ?><option value="<?= (int)$h['id'] ?>"><?= htmlspecialchars($h['name'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?>
          </select></div>
        <div class="mb-2"><label class="form-label">วัตถุประสงค์เข้าถึง</label>
        <select name="purpose" class="rp-control"><option value="CARE">การดูแลรักษา</option><option value="PUBLIC_HEALTH">การปฏิบัติงานสาธารณสุข</option></select></div>
        <button id="data43PatientOpenBtn" type="submit" class="rp-btn rp-btn--primary w-100" disabled><i class="bi bi-shield-lock"></i> ดูข้อมูลผู้ป่วยในพื้นที่</button>
        <div class="small text-muted mt-2">เฉพาะบุคลากรที่ได้รับสิทธิ์รายหน่วยบริการ และมีบันทึกการเปิดดู</div>
      </form><?php endif; ?>
      <div class="data43-map-legend">
        <div class="data43-map-legend__title">ระดับค่าตัวชี้วัด</div>
        <div id="data43MapLegendItems"></div>
        <div class="data43-map-legend__item">
          <span class="data43-map-swatch is-suppressed"></span>
          <span>ข้อมูลถูกปกปิด</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/data43-thailand-map.js" defer></script>