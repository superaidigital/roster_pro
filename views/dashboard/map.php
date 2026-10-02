<?php
// ที่อยู่ไฟล์: views/dashboard/map.php

// ป้องกันกรณีไม่มีตัวแปรส่งมา
$map_data_json = $map_data_json ?? '[]';
?>

<!-- 🌟 นำเข้าไลบรารี Leaflet.js สำหรับแสดงแผนที่ -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<!-- 🌟 นำเข้า MarkerCluster สำหรับจัดกลุ่มจุดพิกัด -->
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

<!-- 🌟 นำเข้า osmtogeojson สำหรับแปลงข้อมูลพิกัดเขตอำเภอจาก Overpass API -->
<script src="https://unpkg.com/osmtogeojson@3.2.2/osmtogeojson.js"></script>

<style>
    body { background-color: #f4f6f9; font-family: 'Sarabun', sans-serif; }
    
    .dashboard-card {
        border: none; border-radius: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        background: #fff; overflow: hidden; height: calc(100vh - 160px);
        display: flex; flex-direction: column;
    }

    /* 🗺️ สไตล์ของกรอบแผนที่ */
    #hospitalMap { flex-grow: 1; width: 100%; border-bottom-left-radius: 1.25rem; border-bottom-right-radius: 1.25rem; z-index: 1;}
    
    /* 🏷️ สไตล์ของปุ่ม Filter เหนือแผนที่ */
    .map-filters {
        padding: 15px 25px; background: #fff; border-bottom: 1px solid #f1f5f9;
        display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: space-between;
    }
    
    .filter-group { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

    .filter-btn {
        border-radius: 50rem; padding: 6px 15px; font-size: 13px; font-weight: 600;
        border: 1px solid #e2e8f0; background: #fff; color: #64748b;
        cursor: pointer; transition: all 0.2s;
    }
    .filter-btn.active { box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .filter-btn:hover { background: #f8fafc; }
    
    /* สีของปุ่ม Filter ต่างๆ */
    .filter-all.active { background: #334155; color: #fff; border-color: #334155; }
    .filter-success.active { background: #10b981; color: #fff; border-color: #10b981; }
    .filter-warning.active { background: #f59e0b; color: #fff; border-color: #f59e0b; }
    .filter-danger.active { background: #ef4444; color: #fff; border-color: #ef4444; }
    .filter-secondary.active { background: #64748b; color: #fff; border-color: #64748b; }

    /* 📌 สไตล์ของ Popup (Tooltip) หมุดโรงพยาบาล */
    .leaflet-popup-content-wrapper { border-radius: 1rem; padding: 0; box-shadow: 0 10px 25px rgba(0,0,0,0.15); overflow: hidden; }
    .leaflet-popup-content { margin: 0; line-height: 1.5; font-family: 'Sarabun', sans-serif; min-width: 250px;}
    .leaflet-popup-tip-container { display: none; }
    .popup-header { padding: 15px; color: white; }
    .popup-body { padding: 15px; background: #fff; }
    
    .bg-status-success { background: linear-gradient(135deg, #10b981 0%, #22c55e 100%); }
    .bg-status-warning { background: linear-gradient(135deg, #f59e0b 0%, #eab308 100%); }
    .bg-status-danger { background: linear-gradient(135deg, #ef4444 0%, #f43f5e 100%); }
    .bg-status-secondary { background: linear-gradient(135deg, #64748b 0%, #94a3b8 100%); }
    
    /* 🏷️ สไตล์ของ Tooltip สำหรับแสดงชื่ออำเภอเมื่อเอาเมาส์ชี้ */
    .district-tooltip {
        background-color: rgba(255, 255, 255, 0.95) !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 0.5rem !important;
        color: #1e293b !important;
        font-family: 'Sarabun', sans-serif !important;
        font-weight: bold !important;
        font-size: 13px !important;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1) !important;
        padding: 5px 12px !important;
    }
    
    /* 🔴 สไตล์ของหมุดแผนที่จำลอง (CSS Marker) */
    .custom-map-marker {
        width: 32px; height: 32px; border-radius: 50% 50% 50% 0;
        background: #3b82f6; position: absolute; transform: rotate(-45deg);
        left: 50%; top: 50%; margin: -16px 0 0 -16px;
        box-shadow: -2px 2px 5px rgba(0,0,0,0.3); border: 2px solid #fff;
        transition: transform 0.2s;
    }
    .custom-map-marker::after {
        content: ''; width: 14px; height: 14px; margin: 7px 0 0 7px;
        background: #fff; position: absolute; border-radius: 50%;
    }
    .leaflet-marker-icon:hover .custom-map-marker { transform: rotate(-45deg) scale(1.1); box-shadow: -3px 3px 8px rgba(0,0,0,0.4); }
    
    .marker-success { background: #10b981; }
    .marker-warning { background: #f59e0b; }
    .marker-danger { background: #ef4444; }
    .marker-secondary { background: #64748b; }

    /* 📊 สไตล์ของ Summary Card บนแผนที่ */
    .map-summary-card {
        position: absolute; bottom: 25px; right: 25px; z-index: 1000;
        background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(5px);
        border-radius: 1rem; padding: 15px 20px; box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        min-width: 220px; border: 1px solid rgba(255,255,255,0.5);
    }
    .summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 13.5px; }
    .summary-row:last-child { margin-bottom: 0; }
    .summary-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; }

    /* Custom Map Control */
    .leaflet-control-custom-btn {
        background-color: #fff; border: 2px solid rgba(0,0,0,0.2); background-clip: padding-box;
        border-radius: 4px; width: 34px; height: 34px; cursor: pointer;
        display: flex; justify-content: center; align-items: center; font-size: 16px; color: #475569;
        transition: background-color 0.2s, color 0.2s;
    }
    .leaflet-control-custom-btn:hover { background-color: #f4f4f4; color: #0d6efd; }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100">
    
    <!-- 🌟 ส่วนหัว -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-black text-dark mb-1 d-flex align-items-center">
                <a href="index.php?c=dashboard" class="btn btn-light rounded-circle shadow-sm border me-3" title="กลับหน้าหลัก">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <i class="bi bi-geo-alt-fill text-danger me-2"></i> แผนที่เครือข่าย รพ.สต.
            </h3>
            <p class="text-muted mb-0 ms-5" style="font-size: 14px;">ภาพรวมและสถานะการจัดตารางเวรแบบเรียลไทม์จำแนกรายอำเภอในเขตพื้นที่จังหวัดศรีสะเกษ</p>
        </div>
    </div>

    <!-- 🌟 พื้นที่แผนที่และตัวกรอง -->
    <div class="dashboard-card position-relative">
        
        <!-- ตัวกรอง (Filter & Search) -->
        <div class="map-filters shadow-sm">
            <div class="filter-group">
                <span class="text-muted small fw-bold me-2 d-none d-md-inline"><i class="bi bi-funnel-fill"></i> ตัวกรอง:</span>
                <button class="filter-btn filter-all active" onclick="setFilterStatus('ALL')">📍 ทั้งหมด</button>
                <button class="filter-btn filter-success" onclick="setFilterStatus('success')">🟢 ปกติ</button>
                <button class="filter-btn filter-warning" onclick="setFilterStatus('warning')">🟡 รอตรวจสอบ</button>
                <button class="filter-btn filter-secondary" onclick="setFilterStatus('secondary')">⚪ ยังไม่ดำเนินการ</button>
                <button class="filter-btn filter-danger" onclick="setFilterStatus('danger')">🔴 ขาดแคลนบุคลากร</button>
            </div>

            <!-- กล่องค้นหา -->
            <div class="input-group input-group-sm mt-2 mt-lg-0" style="width: 250px; max-width: 100%;">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="mapSearch" class="form-control border-start-0" placeholder="ค้นหาชื่อ รพ.สต. ..." onkeyup="setSearchQuery(this.value)">
            </div>
        </div>

        <!-- 🗺️ DIV สำหรับแสดงแผนที่ Leaflet -->
        <div id="hospitalMap"></div>
        
        <!-- 📊 แผงสรุปข้อมูลลอยบนแผนที่ -->
        <div class="map-summary-card d-none d-md-block">
            <h6 class="fw-bold mb-3 border-bottom pb-2 text-dark"><i class="bi bi-bar-chart-fill text-primary me-2"></i>สรุปผลบนแผนที่</h6>
            <div class="summary-row">
                <span class="text-muted"><span class="summary-dot bg-dark"></span> ทั้งหมด</span>
                <span class="fw-bold text-dark" id="sum-all">0</span>
            </div>
            <div class="summary-row">
                <span class="text-muted"><span class="summary-dot bg-success"></span> ปกติ (อนุมัติแล้ว)</span>
                <span class="fw-bold text-success" id="sum-success">0</span>
            </div>
            <div class="summary-row">
                <span class="text-muted"><span class="summary-dot bg-warning"></span> กำลังจัดทำ/รอตรวจสอบ</span>
                <span class="fw-bold text-warning text-dark" id="sum-warning">0</span>
            </div>
            <div class="summary-row">
                <span class="text-muted"><span class="summary-dot bg-danger"></span> ขาดแคลนบุคลากร</span>
                <span class="fw-bold text-danger" id="sum-danger">0</span>
            </div>
            <div class="summary-row">
                <span class="text-muted"><span class="summary-dot bg-secondary"></span> ยังไม่ดำเนินการ</span>
                <span class="fw-bold text-secondary" id="sum-secondary">0</span>
            </div>
        </div>
        
    </div>

</div>

<script>
    // 1. ข้อมูล JSON และสถานะการกรองปัจจุบัน
    const rawData = <?= $map_data_json ?>;
    const hospitalData = rawData.length > 0 ? rawData : [
        { id: 1, name: "รพ.สต. หนองไผ่", lat: 15.1118, lng: 104.3211, staff_count: 5, status_text: "ปกติ (อนุมัติแล้ว)", status_color: "success" },
        { id: 2, name: "รพ.สต. โพนเขวา", lat: 15.1325, lng: 104.3542, staff_count: 2, status_text: "ขาดแคลนบุคลากร", status_color: "danger" },
        { id: 3, name: "รพ.สต. โนนเค็ง", lat: 15.0894, lng: 104.2875, staff_count: 4, status_text: "กำลังจัดทำ", status_color: "warning" },
        { id: 4, name: "รพ.สต. หญ้าปล้อง", lat: 15.0652, lng: 104.3418, staff_count: 3, status_text: "ยังไม่ดำเนินการ", status_color: "secondary" },
        { id: 5, name: "รพ.สต. ทุ่ม", lat: 15.1501, lng: 104.3105, staff_count: 6, status_text: "ปกติ (อนุมัติแล้ว)", status_color: "success" },
        { id: 6, name: "รพ.สต. น้ำคำ", lat: 15.1022, lng: 104.3801, staff_count: 1, status_text: "ขาดแคลนบุคลากร", status_color: "danger" }
    ];

    let map;
    let markersCluster;
    let allMarkers = [];
    let provinceLayer; 
    let districtsLayer; // ตัวแปรเก็บเลเยอร์ขอบเขตอำเภอ
    
    // สถานะฟิลเตอร์ปัจจุบัน
    let currentStatusFilter = 'ALL';
    let currentSearchQuery = '';
    
    // เก็บ Bounds เริ่มต้นเพื่อใช้ปุ่ม Reset View
    let initialBounds;

    // 2. ฟังก์ชันเริ่มต้นสร้างแผนที่
    function initMap() {
        const defaultLat = 15.1186;
        const defaultLng = 104.3220;

        // เลเยอร์แผนที่ต่างๆ (Map Layers)
        const lightMap = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO', maxZoom: 20
        });
        
        const streetMap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
        });
        
        const satelliteMap = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri',
            maxZoom: 18
        });

        // สร้างแผนที่พร้อมเลเยอร์เริ่มต้น
        map = L.map('hospitalMap', { layers: [lightMap] }).setView([defaultLat, defaultLng], 11);

        // ตัวควบคุมเลเยอร์สลับแผนที่
        const baseMaps = {
            "🗺️ แผนที่แบบสว่าง (Light)": lightMap,
            "🛣️ แผนที่ถนน (Street)": streetMap,
            "🛰️ ดาวเทียม (Satellite)": satelliteMap
        };
        L.control.layers(baseMaps, null, { position: 'topright' }).addTo(map);

        // เพิ่มปุ่ม Reset View ลงบนแผนที่ (Custom Control)
        L.Control.ResetView = L.Control.extend({
            onAdd: function(map) {
                var btn = L.DomUtil.create('div', 'leaflet-control-custom-btn leaflet-bar');
                btn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
                btn.title = "แสดงภาพรวมทั้งหมด";
                L.DomEvent.on(btn, 'click', function() {
                    if (initialBounds) map.fitBounds(initialBounds, { padding: [50, 50], maxZoom: 12 });
                });
                return btn;
            }
        });
        new L.Control.ResetView({ position: 'topleft' }).addTo(map);

        // 🌟 2.1 ดึงข้อมูลเส้นขอบเขตจังหวัดศรีสะเกษ (Province Border - เปลี่ยนเป็นเส้นปะสีน้ำเงิน)
        fetch('https://nominatim.openstreetmap.org/search?q=Si+Sa+Ket+Province+Thailand&polygon_geojson=1&format=json&limit=1')
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0 && data[0].geojson) {
                    provinceLayer = L.geoJSON(data[0].geojson, {
                        style: {
                            color: "#3b82f6",       // สีเส้นขอบ (น้ำเงิน)
                            weight: 3,              // ความหนาเส้น
                            opacity: 0.9,           // ความทึบ
                            dashArray: '8, 8',      // เส้นปะ (Dashed Line)
                            fillOpacity: 0          // ไม่เทสีพื้นหลัง ปล่อยให้อำเภอเทสีแทน
                        }
                    }).addTo(map);
                    
                    if (hospitalData.length === 0) {
                        initialBounds = provinceLayer.getBounds();
                        map.fitBounds(initialBounds, { padding: [20, 20] });
                    }
                }
            }).catch(e => console.log(e));

        // 🌟 2.2 ดึงข้อมูลเส้นเขตแยกตามอำเภอ (District Boundaries) ด้วย Overpass API
        const overpassQuery = `
            [out:json][timeout:25];
            area["name:en"="Si Sa Ket"]["admin_level"="4"]->.searchArea;
            (
              relation["admin_level"="6"](area.searchArea);
            );
            out body;
            >;
            out skel qt;
        `;

        fetch('https://overpass-api.de/api/interpreter', {
            method: 'POST',
            body: overpassQuery
        })
        .then(response => response.json())
        .then(data => {
            if(typeof osmtogeojson !== 'undefined') {
                const geojson = osmtogeojson(data);
                
                // จานสีพาสเทลสำหรับแยกแต่ละอำเภอให้สวยงาม
                const districtColors = [
                    '#ef4444', '#f97316', '#f59e0b', '#84cc16', '#10b981', 
                    '#14b8a6', '#06b6d4', '#0ea5e9', '#3b82f6', '#6366f1', 
                    '#8b5cf6', '#a855f7', '#d946ef', '#ec4899', '#f43f5e'
                ];
                let colorIndex = 0;
                
                districtsLayer = L.geoJSON(geojson, {
                    style: function(feature) {
                        const color = districtColors[colorIndex % districtColors.length];
                        colorIndex++;
                        return {
                            color: color,           // สีเส้นขอบอำเภอ
                            weight: 1.5,            // ความหนาเส้นบางกว่าจังหวัด
                            opacity: 0.8,
                            dashArray: '5, 5',      // ทำเป็นเส้นประ
                            fillColor: color,       // สีพื้นหลังอำเภอ
                            fillOpacity: 0.12       // ความโปร่งใส (จางๆ ไม่บังพิน)
                        };
                    },
                    onEachFeature: function(feature, layer) {
                        // ดึงชื่ออำเภอมาทำ Tooltip
                        const districtName = feature.properties?.name || feature.properties?.['name:th'] || 'อำเภอ';
                        layer.bindTooltip(districtName, { 
                            sticky: true,           // ให้ Tooltip ตามลูกศรเมาส์
                            direction: 'auto', 
                            className: 'district-tooltip' 
                        });
                    }
                }).addTo(map);
            }
        })
        .catch(error => console.log("ไม่สามารถโหลดเส้นขอบเขตอำเภอได้:", error));

        // สร้าง Group สำหรับรวมหมุดที่อยู่ใกล้กัน (Marker Cluster)
        markersCluster = L.markerClusterGroup({
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true,
            maxClusterRadius: 50
        });

        map.addLayer(markersCluster);

        // ทำการประมวลผลข้อมูลครั้งแรก
        applyFilters();
        
        // บันทึกมุมมองเริ่มต้นไว้หลังจากจุดโหลดเสร็จ
        setTimeout(() => {
            if (allMarkers.length > 0) {
                initialBounds = L.featureGroup(allMarkers).getBounds();
                map.fitBounds(initialBounds, { padding: [50, 50], maxZoom: 12 });
            }
        }, 500);
    }

    // 3. ฟังก์ชันอัปเดตแผงสรุป (Summary Panel)
    function updateSummaryPanel(filteredData) {
        document.getElementById('sum-all').innerText = filteredData.length;
        document.getElementById('sum-success').innerText = filteredData.filter(h => h.status_color === 'success').length;
        document.getElementById('sum-warning').innerText = filteredData.filter(h => h.status_color === 'warning').length;
        document.getElementById('sum-danger').innerText = filteredData.filter(h => h.status_color === 'danger').length;
        document.getElementById('sum-secondary').innerText = filteredData.filter(h => h.status_color === 'secondary').length;
    }

    // 4. ฟังก์ชันจัดการการกรอง (รวม Status + Search)
    function applyFilters() {
        let filtered = hospitalData;

        if (currentStatusFilter !== 'ALL') {
            filtered = filtered.filter(h => h.status_color === currentStatusFilter);
        }

        if (currentSearchQuery.trim() !== '') {
            const keyword = currentSearchQuery.toLowerCase();
            filtered = filtered.filter(h => h.name.toLowerCase().includes(keyword));
        }

        renderMarkers(filtered);
        updateSummaryPanel(filtered);
    }

    window.setFilterStatus = function(statusColor) {
        currentStatusFilter = statusColor;
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        if(statusColor === 'ALL') {
            document.querySelector('.filter-all').classList.add('active');
        } else {
            document.querySelector('.filter-' + statusColor).classList.add('active');
        }
        applyFilters();
    }

    window.setSearchQuery = function(query) {
        currentSearchQuery = query;
        applyFilters();
    }

    // 5. ฟังก์ชันวาดหมุดลงแผนที่ (Render Markers)
    function renderMarkers(dataToRender) {
        markersCluster.clearLayers();
        allMarkers = [];

        dataToRender.forEach(hosp => {
            const customIcon = L.divIcon({
                className: 'custom-icon',
                html: `<div class="custom-map-marker marker-${hosp.status_color}"></div>`,
                iconSize: [32, 44],
                iconAnchor: [16, 44],
                popupAnchor: [0, -35]
            });

            const popupHtml = `
                <div class="bg-status-${hosp.status_color} popup-header">
                    <h6 class="fw-bold mb-0 text-white"><i class="bi bi-hospital me-1"></i> ${hosp.name}</h6>
                </div>
                <div class="popup-body">
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span class="text-muted small">บุคลากร/พนักงาน:</span>
                        <span class="fw-bold text-dark">${hosp.staff_count} คน</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">สถานะตารางเวร:</span>
                        <span class="badge bg-${hosp.status_color} bg-opacity-10 text-${hosp.status_color} border border-${hosp.status_color} px-2 py-1">${hosp.status_text}</span>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="index.php?c=roster&hospital_id=${hosp.id}" class="btn btn-sm btn-outline-primary rounded-pill px-3 w-100">
                            จัดการตารางเวร <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            `;

            const marker = L.marker([hosp.lat, hosp.lng], { icon: customIcon }).bindPopup(popupHtml);
            allMarkers.push(marker);
            markersCluster.addLayer(marker);
        });
        
        if(currentSearchQuery.trim() !== '' && allMarkers.length > 0) {
            const bounds = L.featureGroup(allMarkers).getBounds();
            map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
        }
    }

    document.addEventListener("DOMContentLoaded", initMap);
</script>