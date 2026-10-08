(()=>{'use strict';
const mapEl=document.getElementById('data43ThailandMap');if(!mapEl||typeof L==='undefined')return;
const mode=document.getElementById('data43BoundaryMode');
const reset=document.getElementById('data43MapReset');
const status=document.getElementById('data43MapStatus');
const label=document.getElementById('data43MapModeLabel');
const urls={province:'assets/geojson/thailand/provinces.geojson',amphoe:'assets/geojson/thailand/amphoes.geojson',tambon:'assets/geojson/thailand/tambons.geojson'};
const labels={province:'จังหวัด',amphoe:'อำเภอ',tambon:'ตำบล'};
const cache=new Map();let layer=null;let current='province';
const map=L.map(mapEl,{minZoom:5,maxZoom:18,preferCanvas:true}).setView([13.2,101],6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
function setStatus(v,t){status.textContent=t||'กำลังโหลดข้อมูลแผนที่...';status.classList.toggle('show',!!v)}
function prop(p,names){for(const n of names){if(p&&p[n]!=null&&String(p[n]).trim()!=='')return String(p[n])}return '-'}
function province(p){return prop(p,['province_name','pro_name_th','PROV_NAMT','NAME_1','ADM1_TH','จังหวัด'])}
function amphoe(p){return prop(p,['amphoe_name','amp_name_th','AMP_NAMT','NAME_2','ADM2_TH','อำเภอ'])}
function tambon(p){return prop(p,['tambon_name','tam_name_th','TAM_NAMT','NAME_3','ADM3_TH','ตำบล'])}
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function tooltip(feature){const p=feature.properties||{};if(current==='province')return '<strong>จังหวัด '+esc(province(p))+'</strong>';if(current==='amphoe')return '<strong>อำเภอ '+esc(amphoe(p))+'</strong><br>จังหวัด '+esc(province(p));return '<strong>ตำบล '+esc(tambon(p))+'</strong><br>อำเภอ '+esc(amphoe(p))+'<br>จังหวัด '+esc(province(p))}
const normal=()=>({weight:1,color:'#52697f',opacity:.95,fillColor:'#4d8ed8',fillOpacity:.12});
const hover=()=>({weight:2,color:'#1268d9',fillColor:'#2e8df0',fillOpacity:.42});
function onEach(feature,l){l.bindTooltip(tooltip(feature),{sticky:true,direction:'top',className:'data43-map-tooltip'});l.on({mouseover:e=>{e.target.setStyle(hover());e.target.bringToFront()},mouseout:e=>layer&&layer.resetStyle(e.target),click:e=>{const b=e.target.getBounds();if(b&&b.isValid())map.fitBounds(b,{padding:[24,24],maxZoom:current==='tambon'?13:11})}})}
async function load(type){current=type;label.textContent=labels[type]||type;setStatus(true);try{let data=cache.get(type);if(!data){const res=await fetch(urls[type],{cache:'force-cache'});if(!res.ok)throw new Error('ไม่พบไฟล์ '+urls[type]);data=await res.json();if(!data||!Array.isArray(data.features))throw new Error('รูปแบบ GeoJSON ไม่ถูกต้อง');cache.set(type,data)}if(layer)map.removeLayer(layer);layer=L.geoJSON(data,{style:normal,onEachFeature:onEach}).addTo(map);const b=layer.getBounds();if(b.isValid())map.fitBounds(b,{padding:[12,12]})}catch(err){console.error(err);setStatus(true,'ยังไม่มี GeoJSON: '+err.message);setTimeout(()=>setStatus(false),5000);return}setStatus(false)}
mode?.addEventListener('change',e=>load(e.target.value));reset?.addEventListener('click',()=>{if(layer&&layer.getBounds().isValid())map.fitBounds(layer.getBounds(),{padding:[12,12]});else map.setView([13.2,101],6)});
load('province');
})();