<?php $geojson_status=$geojson_status??[]; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="assets/css/data43-thailand-map.css">
<div class="rp-page">
  <div class="rp-page-header mb-3">
    <div>
      <div class="rp-page-header__eyebrow">THAILAND MAP</div>
      <h1 class="rp-page-header__title">แผนที่ประเทศไทย</h1>
      <p class="rp-page-header__subtitle mb-0">แสดงขอบเขตจังหวัด อำเภอ ตำบล และเตรียมเชื่อม Choropleth ข้อมูล 43 แฟ้ม</p>
    </div>
  </div>

  <?php if(in_array(false,$geojson_status,true)): ?>
    <div class="rp-alert rp-alert--warning mb-3">
      <span class="rp-alert__icon"><i class="bi bi-map"></i></span>
      <div class="rp-alert__content">
        ยังไม่มี GeoJSON ครบทุกระดับ กรุณาวางไฟล์ที่
        <code>assets/geojson/thailand/provinces.geojson</code>,
        <code>amphoes.geojson</code> และ <code>tambons.geojson</code>
        โดยใช้ CRS EPSG:4326
      </div>
    </div>
  <?php endif; ?>

  <div class="data43-map-page">
    <div class="data43-map-toolbar">
      <div class="data43-map-toolbar__title"><strong>ขอบเขตการปกครองประเทศไทย</strong><small>OpenStreetMap + Leaflet.js</small></div>
      <div class="data43-map-field"><label>ระดับพื้นที่</label><select id="data43BoundaryMode"><option value="province">จังหวัด</option><option value="amphoe">อำเภอ</option><option value="tambon">ตำบล</option></select></div>
      <button type="button" id="data43MapReset" class="rp-btn rp-btn--secondary"><i class="bi bi-arrows-fullscreen"></i> แสดงทั้งหมด</button>
    </div>
    <div id="data43ThailandMap"></div>
    <div id="data43MapStatus" class="data43-map-status"></div>
    <div class="data43-map-info"><div class="small text-muted">ระดับที่แสดง</div><strong id="data43MapModeLabel">จังหวัด</strong></div>
  </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/data43-thailand-map.js" defer></script>