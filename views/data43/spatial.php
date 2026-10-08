<?php
$is_admin=$is_admin??false;
$csrf_token=$_SESSION['csrf_token']??($_SESSION['csrf_token']=bin2hex(random_bytes(32)));
$hospitals=$hospitals??[];
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
.d43-wrap{display:grid;gap:16px}.d43-toolbar{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
.d43-toolbar label{display:block;font-size:12px;font-weight:700;margin-bottom:4px}.d43-toolbar select,.d43-toolbar input{width:100%;min-height:40px;border:1px solid #cbd5e1;border-radius:10px;padding:8px;background:#fff;color:#0f172a}
.d43-map-layout{display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:14px}.d43-panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px;min-width:0}
#d43-map{height:min(64vh,680px);min-height:440px;background:#e5eef5;border-radius:12px;z-index:0}
.d43-crumbs{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.d43-crumbs button{border:0;border-radius:9px;background:#e2e8f0;color:#0f172a;padding:7px 10px}
.d43-crumbs button[aria-current=page]{background:#155e75;color:white}
.d43-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.d43-kpis div{padding:12px;background:#f1f5f9;border-radius:12px}
.d43-kpis strong{display:block;font-size:22px}.d43-muted{color:#64748b;font-size:13px}
.d43-legend-row{display:flex;align-items:center;gap:9px;margin:7px 0;font-size:13px}
.d43-dot{display:inline-block;height:14px;width:18px;border-radius:4px}
#d43-search-results{max-height:170px;overflow:auto;display:grid;gap:4px}
#d43-search-results button{border:1px solid #e2e8f0;background:white;text-align:left;border-radius:8px;padding:8px}
#d43-list{display:grid;gap:6px;max-height:220px;overflow:auto}
#d43-list button{text-align:left;border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:9px}
.d43-state{padding:12px;border-radius:10px;background:#e0f2fe;color:#0c4a6e;margin:10px 0}
.d43-loading{opacity:.65}
@media(max-width:1100px){.d43-toolbar{grid-template-columns:repeat(3,minmax(0,1fr))}.d43-map-layout{grid-template-columns:1fr}}
@media(max-width:650px){.d43-toolbar{grid-template-columns:1fr 1fr}#d43-map{height:55vh;min-height:350px}.d43-kpis{grid-template-columns:1fr 1fr}}
</style>
<div class="rp-page d43-wrap">
  <div class="rp-page-header">
    <div><div class="rp-page-header__eyebrow">DATA43 • GEOSPATIAL ANALYTICS</div>
      <h1 class="rp-page-header__title">แผนที่วิเคราะห์ข้อมูล 43 แฟ้มเชิงพื้นที่</h1>
      <p class="rp-page-header__subtitle">จังหวัด → อำเภอ → ตำบล • แผนที่สีตามจำนวนระเบียน Aggregate • ไม่มีพิกัดผู้ป่วย</p>
    </div>
  </div>
  <section class="d43-panel">
    <div class="d43-toolbar">
      <div><label for="d43-month">รอบเดือน (ค.ศ.)</label><input id="d43-month" type="month" value="<?= htmlspecialchars($report_month??date('Y-m'),ENT_QUOTES,'UTF-8') ?>"></div>
      <div><label for="d43-hospital">รพ.สต. / หน่วยบริการ</label><select id="d43-hospital" <?= !$is_admin?'disabled':'' ?>>
        <?php if ($is_admin): ?><option value="">ทุกหน่วยบริการ</option><?php endif; ?>
        <?php foreach ($hospitals as $h): ?>
          <option value="<?= (int)$h['id'] ?>" <?= (int)($selected_hospital_id??0)===(int)$h['id']?'selected':'' ?>><?= htmlspecialchars($h['name'],ENT_QUOTES,'UTF-8') ?></option>
        <?php endforeach; ?>
        <?php if (!$is_admin): ?><option value="" selected>หน่วยบริการของฉัน</option><?php endif; ?>
      </select></div>
      <div><label for="d43-metric">ตัวชี้วัด</label><select id="d43-metric"><option value="">กำลังโหลด...</option></select></div>
      <div><label for="d43-search">ค้นหาจังหวัด / อำเภอ / ตำบล</label><input id="d43-search" type="search" placeholder="ชื่อพื้นที่หรือรหัส" autocomplete="off"></div>
      <div><label>ข้อมูล</label><a class="rp-btn rp-btn--secondary w-100" href="index.php?c=data43&a=dashboard">แดชบอร์ดการนำส่ง</a></div>
    </div>
    <div id="d43-search-results" role="listbox" aria-label="ผลค้นหาพื้นที่"></div>
  </section>
  <div class="d43-kpis" aria-live="polite">
    <div><span class="d43-muted">พื้นที่บนแผนที่</span><strong id="d43-count-areas">–</strong></div>
    <div><span class="d43-muted">พื้นที่มีข้อมูล</span><strong id="d43-with-data">–</strong></div>
    <div><span class="d43-muted">พื้นที่ปกปิดตัวเลข</span><strong id="d43-suppressed">–</strong></div>
  </div>
  <div class="d43-map-layout">
    <section class="d43-panel">
      <nav class="d43-crumbs" id="d43-crumbs" aria-label="ลำดับพื้นที่"><button type="button">ประเทศไทย</button></nav>
      <div id="d43-map" aria-label="แผนที่สีแสดงจำนวนระเบียนตามพื้นที่"></div>
      <div id="d43-state" class="d43-state" role="status">กำลังโหลดข้อมูลพื้นที่...</div>
      <p class="d43-muted">การเลือกพื้นที่จะคงสีเลือกไว้ชัดเจน ส่วนการชี้เมาส์เป็นเพียง Hover ชั่วคราว • เลือกสีตามจำนวนระเบียนของแฟ้มที่เลือก ไม่ใช่อัตราป่วยหรือจำนวนผู้ป่วยไม่ซ้ำ</p>
    </section>
    <aside class="d43-panel">
      <h3 class="h6 fw-bold">คำอธิบายสี</h3><div id="d43-legend"></div>
      <hr><h3 class="h6 fw-bold">พื้นที่ที่เลือก</h3><div id="d43-selected" class="d43-muted">คลิกพื้นที่บนแผนที่</div>
      <hr><h3 class="h6 fw-bold">พื้นที่ในระดับปัจจุบัน</h3><div id="d43-list"></div>
    </aside>
  </div>
  <?php if ($is_admin): ?>
  <section class="d43-panel">
    <h2 class="h6 fw-bold">จัดการ Master ขอบเขตพื้นที่ (ผู้ดูแลระบบ)</h2>
    <p class="d43-muted">นำเข้า GeoJSON มาตรฐานที่ตรวจสอบรหัสพื้นที่และแหล่งที่มาแล้ว ระบบรองรับ Polygon/MultiPolygon และรหัส area_code ความยาว 2, 4, 6 หลัก</p>
    <form method="post" action="index.php?c=data43&a=spatial_admin" enctype="multipart/form-data" class="row g-2 align-items-end">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>">
      <input type="hidden" name="operation" value="upload_geojson">
      <div class="col-md-3"><label class="form-label" for="d43-level">ระดับขอบเขต</label><select id="d43-level" name="level" class="rp-control"><option value="PROVINCE">จังหวัด (2 หลัก)</option><option value="AMPUR">อำเภอ (4 หลัก)</option><option value="TAMBON">ตำบล (6 หลัก)</option></select></div>
      <div class="col-md-6"><label class="form-label" for="d43-file">ไฟล์ GeoJSON</label><input id="d43-file" type="file" name="geojson" accept=".json,.geojson,application/geo+json" class="rp-control" required></div>
      <div class="col-md-3"><button class="rp-btn rp-btn--primary w-100">นำเข้าขอบเขตพื้นที่</button></div>
    </form>
    <hr><h3 class="h6">ผูกพื้นที่รับผิดชอบ รพ.สต.</h3>
    <form method="post" action="index.php?c=data43&a=spatial_admin" class="row g-2 align-items-end">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>">
      <input type="hidden" name="operation" value="assign_area">
      <div class="col-md-5"><label class="form-label">หน่วยบริการ</label><select class="rp-control" name="hospital_id" required><option value="">เลือกหน่วยบริการ</option><?php foreach ($hospitals as $h): ?><option value="<?= (int)$h['id'] ?>"><?= htmlspecialchars($h['name'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">รหัสตำบล 6 หลัก</label><input name="tambon_code" class="rp-control" pattern="[0-9]{6}" maxlength="6" required></div>
      <div class="col-md-2"><label class="form-label">ดำเนินการ</label><select class="rp-control" name="assign"><option value="1">เพิ่มพื้นที่</option><option value="0">ยกเลิกพื้นที่</option></select></div>
      <div class="col-md-2"><button class="rp-btn rp-btn--primary w-100">บันทึก</button></div>
    </form>
  </section>
  <?php endif; ?>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(()=>{'use strict';
const $=id=>document.getElementById(id), colors={'none':'#e2e8f0','suppressed':'#94a3b8','5-9':'#ccfbf1','10-24':'#5eead4','25-49':'#14b8a6','50-99':'#0f766e','100+':'#115e59'};
const labels={'none':'ไม่มีข้อมูล','suppressed':'ปกปิด (1–4)','5-9':'5–9','10-24':'10–24','25-49':'25–49','50-99':'50–99','100+':'100 ขึ้นไป'};
Object.keys(colors).forEach(k=>{const d=document.createElement('div');d.className='d43-legend-row';const dot=document.createElement('span');dot.className='d43-dot';dot.style.background=colors[k];const t=document.createElement('span');t.textContent=labels[k];d.append(dot,t);$('d43-legend').append(d)});
const map=L.map('d43-map',{zoomControl:true}).setView([15.2,102.8],6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
let geo=null,selected=null,selectedFeature=null,seq=0,searchTimer=null,level='PROVINCE',parent='',trail=[],featureIndex=new Map();
const endpoint='index.php?c=data43&a=spatial_api';
const status=(s)=>{$('d43-state').textContent=s;$('d43-state').hidden=!s};
const searchParams=(type,extras={})=>{const p=new URLSearchParams({c:'data43',a:'spatial_api',type,...extras});return 'index.php?'+p.toString()};
const scope=()=>{const v=$('d43-hospital').value;return v?{hospital_id:v}:{}};
const textEl=(tag,value)=>{const e=document.createElement(tag);e.textContent=value;return e};
async function api(type,extra={}){const response=await fetch(searchParams(type,{...scope(),...extra}),{credentials:'same-origin',cache:'no-store'});const data=await response.json();if(!response.ok)throw Error(data.error||'โหลดข้อมูลไม่สำเร็จ');return data}
async function options(){const data=await api('options',{month:$('d43-month').value});$('d43-metric').replaceChildren();data.items.forEach((x,i)=>{const o=document.createElement('option');o.value=x.metric_code+'|'+x.source_file_code;o.textContent=x.metric_code+' · '+x.source_file_code;$('d43-metric').append(o)});if(!data.items.length){const o=document.createElement('option');o.value='';o.textContent='ไม่มี Aggregate ในรอบเดือนนี้';$('d43-metric').append(o)}}
function crumbs(){const root=$('d43-crumbs');root.replaceChildren();const make=(name,index)=>{const b=textEl('button',name);b.type='button';b.setAttribute('aria-current',index===trail.length?'page':'false');b.onclick=()=>{const list=trail.slice(0,index);trail=list;level=index===0?'PROVINCE':index===1?'AMPUR':'TAMBON';parent=index===0?'':list[list.length-1].code;selected=null;load()};root.append(b)};make('ประเทศไทย',0);trail.forEach((x,i)=>make(x.name,i+1))}
function updateSelected(f){selectedFeature=f;const el=$('d43-selected');el.replaceChildren();if(!f){el.textContent='คลิกพื้นที่บนแผนที่';return}const p=f.properties;el.append(textEl('strong',p.name+' ('+p.code+')'),textEl('p',p.suppressed?'ปกปิดจำนวน 1–4':p.band==='none'?'ไม่มีข้อมูล':labels[p.band]+' ระเบียน'));const button=textEl('button','แสดง รพ.สต. ในพื้นที่รับผิดชอบ');button.className='rp-btn rp-btn--secondary';button.onclick=async()=>{button.disabled=true;try{const d=await api('facilities',{level,code:p.code});el.append(textEl('p',d.items.length?d.items.map(x=>x.name).join(' • '):'ยังไม่มีการผูกหน่วยบริการกับพื้นที่นี้'))}catch(e){el.append(textEl('p',e.message))}};el.append(button);if(level!=='TAMBON'){const drill=textEl('button','ดู'+(level==='PROVINCE'?'อำเภอ':'ตำบล')+' →');drill.className='rp-btn rp-btn--primary ms-2';drill.onclick=()=>drillDown(p);el.append(drill)}}
function drillDown(p){if(level==='TAMBON')return;trail.push({code:p.code,name:p.name});parent=p.code;level=level==='PROVINCE'?'AMPUR':'TAMBON';selected=null;load()}
function choose(p){selected=p.code;featureIndex.forEach(({layer,feature})=>layer.setStyle(style(feature)));updateSelected({properties:p})}
function style(f){const p=f.properties;return{color:selected===p.code?'#f97316':'#ffffff',weight:selected===p.code?4:1.4,fillColor:colors[p.band]||colors.none,fillOpacity:.78}}
function renderList(features){const holder=$('d43-list');holder.replaceChildren();features.forEach(f=>{const p=f.properties;const b=textEl('button',p.name+' · '+(p.suppressed?'ปกปิด':p.band==='none'?'ไม่มีข้อมูล':labels[p.band]));b.onclick=()=>{choose(p);const item=featureIndex.get(p.code);if(item)map.fitBounds(item.layer.getBounds(),{padding:[25,25],maxZoom:11})};holder.append(b)})}
async function load(){const current=++seq;status('กำลังโหลดขอบเขตและสถิติ...');crumbs();$('d43-selected').textContent='คลิกพื้นที่บนแผนที่';const parts=$('d43-metric').value.split('|');try{
const data=await api('map',{month:$('d43-month').value,level,parent,metric:parts[0]||'',source:parts[1]||''});if(current!==seq)return;
if(geo)map.removeLayer(geo);featureIndex.clear();selected=null;
geo=L.geoJSON(data,{style,onEachFeature:(f,layer)=>{const p=f.properties;featureIndex.set(p.code,{feature:f,layer});layer.bindTooltip(()=>p.name+' · '+(p.suppressed?'ปกปิด':p.band==='none'?'ไม่มีข้อมูล':labels[p.band]+' ระเบียน'),{sticky:true});layer.on('mouseover',()=>{if(selected!==p.code)layer.setStyle({weight:3,color:'#0f172a',fillOpacity:.9});layer.bringToFront()});layer.on('mouseout',()=>layer.setStyle(style(f)));layer.on('click',()=>{choose(p);if(level!=='TAMBON')drillDown(p)})}}).addTo(map);
renderList(data.features);$('d43-count-areas').textContent=data.features.length.toLocaleString('th-TH');$('d43-with-data').textContent=data.features.filter(x=>x.properties.band!=='none').length.toLocaleString('th-TH');$('d43-suppressed').textContent=data.features.filter(x=>x.properties.suppressed).length.toLocaleString('th-TH');
if(geo.getLayers().length)map.fitBounds(geo.getBounds(),{padding:[20,20]});status(data.features.length?'':data.meta.boundary_ready?'ไม่มีขอบเขตระดับนี้ กรุณาตรวจรหัส parent_code และไฟล์ GeoJSON':'ยังไม่มี Master GeoJSON กรุณาให้ Admin นำเข้าขอบเขตพื้นที่');
}catch(e){if(current!==seq)return;status('ไม่สามารถแสดงแผนที่: '+e.message)}}
async function refresh(){level='PROVINCE';parent='';trail=[];selected=null;status('กำลังโหลดตัวชี้วัด');try{await options();await load()}catch(e){status(e.message)}}
$('d43-month').addEventListener('change',refresh);$('d43-hospital').addEventListener('change',refresh);$('d43-metric').addEventListener('change',()=>{selected=null;load()});
$('d43-search').addEventListener('input',()=>{clearTimeout(searchTimer);const q=$('d43-search').value.trim();const out=$('d43-search-results');out.replaceChildren();if(q.length<2)return;searchTimer=setTimeout(async()=>{try{const d=await api('search',{q});if(q!==$('d43-search').value.trim())return;out.replaceChildren();d.items.forEach(p=>{const b=textEl('button',p.name_th+' ('+p.area_code+')');b.type='button';b.onclick=()=>{const code=p.area_code;trail=p.area_level==='PROVINCE'?[]:p.area_level==='AMPUR'?[{code:code.slice(0,2),name:'จังหวัด '+code.slice(0,2)}]:[{code:code.slice(0,2),name:'จังหวัด '+code.slice(0,2)},{code:code.slice(0,4),name:'อำเภอ '+code.slice(0,4)}];level=p.area_level;parent=level==='PROVINCE'?'':code.slice(0,level==='AMPUR'?2:4);selected=code;out.replaceChildren();load().then(()=>{const x=featureIndex.get(code);if(x){choose(x.feature.properties);map.fitBounds(x.layer.getBounds())}})};out.append(b)})}catch(e){status(e.message)}},250)});
refresh();
})();
</script>
