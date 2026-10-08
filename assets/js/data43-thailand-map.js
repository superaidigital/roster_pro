(()=>{'use strict';
const root=document.getElementById('data43MapRoot');
const mapEl=document.getElementById('data43ThailandMap');
if(!root||!mapEl||typeof L==='undefined')return;

const modeEl=document.getElementById('data43BoundaryMode');
const monthEl=document.getElementById('data43MapMonth');
const metricEl=document.getElementById('data43MapMetric');
const resetBtn=document.getElementById('data43MapReset');
const statusEl=document.getElementById('data43MapStatus');
const modeLabel=document.getElementById('data43MapModeLabel');
const metricLabel=document.getElementById('data43MapMetricLabel');
const selectionEl=document.getElementById('data43MapSelection');
const breadcrumbEl=document.getElementById('data43MapBreadcrumb');
const searchEl=document.getElementById('data43MapSearch');
const searchResults=document.getElementById('data43MapSearchResults');
const legendEl=document.getElementById('data43MapLegendItems');

const hospitalId=root.dataset.hospital||'';
const patientOpenBtn=document.getElementById('data43PatientOpenBtn');
const patientAreaLevel=document.getElementById('data43PatientLevel');
const patientAreaCode=document.getElementById('data43PatientCode');
function setPatientArea(feature){
 if(!patientOpenBtn||!patientAreaLevel||!patientAreaCode)return;
 const p=feature?.properties||{};
 const code=feature?featureKey(feature,currentMode):'';
 patientAreaLevel.value=({province:'CHANGWAT',amphoe:'AMPUR',tambon:'TAMBON'})[currentMode]||'';
 patientAreaCode.value=code;
 patientOpenBtn.disabled=!code||!/^\d{2}(?:\d{2}){0,2}$/.test(code);
}
const urls={
  province:'assets/geojson/thailand/provinces.geojson',
  amphoe:'assets/geojson/thailand/amphoes.geojson',
  tambon:'assets/geojson/thailand/tambons.geojson'
};
const levelApi={province:'CHANGWAT',amphoe:'AMPUR',tambon:'TAMBON'};
const labels={province:'จังหวัด',amphoe:'อำเภอ',tambon:'ตำบล'};
const cache=new Map();
const metricCache=new Map();
let layer=null;
let currentMode='province';
let selected={province:null,amphoe:null,tambon:null};
let selectedLayer=null;
let metricRows=[];
let breaks=[];

const map=L.map(mapEl,{minZoom:5,maxZoom:18,preferCanvas:true}).setView([13.2,101],6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
  maxZoom:19,
  attribution:'&copy; OpenStreetMap contributors'
}).addTo(map);

function setStatus(show,text){
  statusEl.textContent=text||'กำลังโหลดข้อมูลแผนที่...';
  statusEl.classList.toggle('show',!!show);
}
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function prop(p,names){for(const n of names){if(p&&p[n]!=null&&String(p[n]).trim()!=='')return String(p[n]).trim()}return ''}
function digits(v){return String(v??'').replace(/\D/g,'')}
function provinceName(p){return prop(p,['province_name','pro_name_th','PROV_NAMT','NAME_1','ADM1_TH','จังหวัด'])||'-'}
function amphoeName(p){return prop(p,['amphoe_name','amp_name_th','AMP_NAMT','NAME_2','ADM2_TH','อำเภอ'])||'-'}
function tambonName(p){return prop(p,['tambon_name','tam_name_th','TAM_NAMT','NAME_3','ADM3_TH','ตำบล'])||'-'}

function provinceCode(p){
  let v=digits(prop(p,['province_code','pro_code','PROV_CODE','ADM1_PCODE','CHANGWAT','changwat_code']));
  if(v.length>2)v=v.slice(-2);
  return v.padStart(2,'0');
}
function amphoeCode(p){
  let v=digits(prop(p,['amphoe_code','amp_code','AMP_CODE','ADM2_PCODE','AMPUR','ampur_code']));
  if(v.length>=4)v=v.slice(-2);
  return v.padStart(2,'0');
}
function tambonCode(p){
  let v=digits(prop(p,['tambon_code','tam_code','TAM_CODE','ADM3_PCODE','TAMBON','tambon_code']));
  if(v.length>=6)v=v.slice(-2);
  return v.padStart(2,'0');
}
function featureKey(feature,mode){
  const p=feature.properties||{};
  if(mode==='province')return provinceCode(p);
  if(mode==='amphoe')return provinceCode(p)+amphoeCode(p);
  return provinceCode(p)+amphoeCode(p)+tambonCode(p);
}
function rowKey(row,mode){
  if(mode==='province')return String(row.changwat_code||'').padStart(2,'0');
  if(mode==='amphoe')return String(row.changwat_code||'').padStart(2,'0')+String(row.ampur_code||'').padStart(2,'0');
  return String(row.changwat_code||'').padStart(2,'0')+String(row.ampur_code||'').padStart(2,'0')+String(row.tambon_code||'').padStart(2,'0');
}
function currentSelectionText(feature){
  const p=feature?.properties||{};
  if(currentMode==='province')return 'จังหวัด '+provinceName(p);
  if(currentMode==='amphoe')return 'อำเภอ '+amphoeName(p)+' · จังหวัด '+provinceName(p);
  return 'ตำบล '+tambonName(p)+' · อำเภอ '+amphoeName(p)+' · จังหวัด '+provinceName(p);
}
function metricRow(feature){
  const key=featureKey(feature,currentMode);
  return metricRows.find(r=>rowKey(r,currentMode)===key)||null;
}

function colorForValue(value){
  if(value==null||!breaks.length)return '#dbe4ee';
  const colors=['#dcecff','#a8cff7','#6eadeb','#347fd4','#1759a8'];
  for(let i=0;i<breaks.length;i++)if(value<=breaks[i])return colors[i];
  return colors[colors.length-1];
}
function baseStyle(feature){
  const r=metricRow(feature);
  if(r?.suppressed){
    return {weight:1,color:'#8d99a8',opacity:.95,fillColor:'#d7dce2',fillOpacity:.55,dashArray:'4 3'};
  }
  return {
    weight:1,
    color:'#52697f',
    opacity:.95,
    fillColor:r&&r.value!=null?colorForValue(Number(r.value)):'#dbe4ee',
    fillOpacity:r&&r.value!=null?.68:.18
  };
}
function selectedStyle(){
  return {weight:3,color:'#0a4f9f',fillOpacity:.82};
}
function hoverStyle(){
  return {weight:2.5,color:'#1268d9',fillOpacity:.82};
}

function computeBreaks(){
  const vals=metricRows.filter(r=>!r.suppressed&&r.value!=null).map(r=>Number(r.value)).filter(Number.isFinite).sort((a,b)=>a-b);
  if(!vals.length){breaks=[];renderLegend();return}
  const q=p=>vals[Math.min(vals.length-1,Math.floor((vals.length-1)*p))];
  breaks=[q(.2),q(.4),q(.6),q(.8)].map(v=>Number(v.toFixed(2)));
  renderLegend();
}
function renderLegend(){
  legendEl.innerHTML='';
  if(!breaks.length){
    legendEl.innerHTML='<div class="data43-map-legend__item"><span class="data43-map-swatch" style="background:#dbe4ee"></span><span>ยังไม่มีข้อมูล</span></div>';
    return;
  }
  const colors=['#dcecff','#a8cff7','#6eadeb','#347fd4','#1759a8'];
  const ranges=[
    '≤ '+breaks[0],
    breaks[0]+' – '+breaks[1],
    breaks[1]+' – '+breaks[2],
    breaks[2]+' – '+breaks[3],
    '> '+breaks[3]
  ];
  ranges.forEach((txt,i)=>{
    const d=document.createElement('div');d.className='data43-map-legend__item';
    d.innerHTML='<span class="data43-map-swatch" style="background:'+colors[i]+'"></span><span>'+esc(txt)+'</span>';
    legendEl.appendChild(d);
  });
}
function tooltip(feature){
  const p=feature.properties||{};
  const r=metricRow(feature);
  let title=currentSelectionText(feature);
  let html='<strong>'+esc(title)+'</strong>';
  if(r?.suppressed){
    html+='<div class="metric">ข้อมูลถูกปกปิด</div><div class="muted">เพื่อคุ้มครองข้อมูลส่วนบุคคล</div>';
  }else if(r){
    html+='<div class="metric">'+esc(metricEl.options[metricEl.selectedIndex]?.text||metricEl.value)+'</div>';
    if(r.count!=null)html+='<div>จำนวน '+Number(r.count).toLocaleString('th-TH')+'</div>';
    if(r.denominator!=null&&Number(r.denominator)>0)html+='<div>ประชากรฐาน '+Number(r.denominator).toLocaleString('th-TH')+'</div>';
    html+='<div>ค่า '+(r.value==null?'–':Number(r.value).toLocaleString('th-TH',{maximumFractionDigits:2}))+' '+esc(r.unit||'')+'</div>';
  }else{
    html+='<div class="muted">ยังไม่มีข้อมูลตัวชี้วัด</div>';
  }
  return html;
}

function updateBreadcrumb(){
  breadcrumbEl.innerHTML='';
  const add=(text,level,active=false)=>{
    const b=document.createElement('button');b.type='button';b.dataset.level=level;b.textContent=text;b.classList.toggle('active',active);
    b.addEventListener('click',()=>breadcrumbNavigate(level));
    breadcrumbEl.appendChild(b);
  };
  add('ประเทศไทย','province',currentMode==='province'&&!selected.province);
  if(selected.province)add(selected.province.name,'amphoe',currentMode==='amphoe'&&!selected.amphoe);
  if(selected.amphoe)add(selected.amphoe.name,'tambon',currentMode==='tambon');
}
function breadcrumbNavigate(level){
  if(level==='province'){
    selected={province:null,amphoe:null,tambon:null};
    modeEl.value='province';load('province',true);return;
  }
  if(level==='amphoe'){
    selected.amphoe=null;selected.tambon=null;
    modeEl.value='amphoe';load('amphoe',true);return;
  }
  if(level==='tambon'){
    modeEl.value='tambon';load('tambon',true);
  }
}

function selectFeature(feature,l){
  if(selectedLayer&&layer)layer.resetStyle(selectedLayer);
  selectedLayer=l;
  l.setStyle(selectedStyle());
  selectionEl.textContent=currentSelectionText(feature);
  setPatientArea(feature);
}
function drillDown(feature,l){
  selectFeature(feature,l);
  const p=feature.properties||{};
  const b=l.getBounds();if(b?.isValid())map.fitBounds(b,{padding:[24,24],maxZoom:currentMode==='tambon'?13:11});
  if(currentMode==='province'){
    selected.province={code:provinceCode(p),name:provinceName(p)};
    selected.amphoe=null;selected.tambon=null;
    modeEl.value='amphoe';load('amphoe',true);
  }else if(currentMode==='amphoe'){
    selected.amphoe={code:amphoeCode(p),name:amphoeName(p)};
    selected.tambon=null;
    modeEl.value='tambon';load('tambon',true);
  }
}
function onEach(feature,l){
  l.bindTooltip(()=>tooltip(feature),{sticky:true,direction:'top',className:'data43-map-tooltip'});
  l.on({
    mouseover:e=>{if(e.target!==selectedLayer)e.target.setStyle(hoverStyle());e.target.bringToFront()},
    mouseout:e=>{if(e.target!==selectedLayer&&layer)layer.resetStyle(e.target)},
    click:e=>drillDown(feature,e.target)
  });
}

function filterGeoJSON(data,type){
  if(type==='province')return data;
  const features=(data.features||[]).filter(f=>{
    const p=f.properties||{};
    if(selected.province&&provinceCode(p)!==selected.province.code)return false;
    if(type==='tambon'&&selected.amphoe&&amphoeCode(p)!==selected.amphoe.code)return false;
    return true;
  });
  return {...data,features};
}
async function fetchGeo(type){
  if(cache.has(type))return cache.get(type);
  const res=await fetch(urls[type],{cache:'force-cache'});
  if(!res.ok)throw new Error('ไม่พบไฟล์ '+urls[type]);
  const data=await res.json();
  if(!data||!Array.isArray(data.features))throw new Error('รูปแบบ GeoJSON ไม่ถูกต้อง');
  cache.set(type,data);return data;
}
async function fetchMetrics(type){
  const params=new URLSearchParams({c:'data43',a:'map_data',month:monthEl.value,metric:metricEl.value,level:levelApi[type]});
  if(hospitalId&&hospitalId!=='0')params.set('hospital_id',hospitalId);
  if(selected.province?.code)params.set('changwat',selected.province.code);
  if(selected.amphoe?.code)params.set('ampur',selected.amphoe.code);
  const key=params.toString();
  if(metricCache.has(key))return metricCache.get(key);
  const res=await fetch('index.php?'+key,{cache:'no-store'});
  const data=await res.json();
  if(!res.ok||!data.ok)throw new Error(data.message||'โหลดข้อมูลตัวชี้วัดไม่สำเร็จ');
  metricCache.set(key,data);return data;
}
async function load(type,fit=true){
  currentMode=type;selectedLayer=null;setPatientArea(null);modeLabel.textContent=labels[type]||type;
  metricLabel.textContent=metricEl.options[metricEl.selectedIndex]?.text||metricEl.value;
  updateBreadcrumb();setStatus(true,'กำลังโหลดแผนที่และข้อมูล...');
  try{
    const [geo,metricData]=await Promise.all([fetchGeo(type),fetchMetrics(type)]);
    metricRows=metricData.items||[];computeBreaks();
    const filtered=filterGeoJSON(geo,type);
    if(layer)map.removeLayer(layer);
    layer=L.geoJSON(filtered,{style:baseStyle,onEachFeature:onEach}).addTo(map);
    if(fit){
      const b=layer.getBounds();if(b.isValid())map.fitBounds(b,{padding:[16,16]});
    }
    buildSearchIndex(filtered);
    setStatus(false);
  }catch(err){
    console.error(err);setStatus(true,err.message||'โหลดข้อมูลไม่สำเร็จ');
    setTimeout(()=>setStatus(false),5000);
  }
}
function buildSearchIndex(data){
  const items=(data.features||[]).map(f=>{
    const p=f.properties||{};
    return {feature:f,label:currentSelectionText(f),text:(provinceName(p)+' '+amphoeName(p)+' '+tambonName(p)).toLowerCase()};
  });
  searchEl._items=items;
}
function showSearch(q){
  const items=(searchEl._items||[]).filter(x=>x.text.includes(q.toLowerCase())).slice(0,12);
  searchResults.innerHTML='';
  items.forEach(item=>{
    const b=document.createElement('button');b.type='button';b.textContent=item.label;
    b.addEventListener('click',()=>{
      const key=featureKey(item.feature,currentMode);
      let target=null;
      layer?.eachLayer(l=>{if(featureKey(l.feature,currentMode)===key)target=l});
      if(target){selectFeature(item.feature,target);const bounds=target.getBounds();if(bounds.isValid())map.fitBounds(bounds,{padding:[24,24],maxZoom:12});target.openTooltip()}
      searchResults.classList.remove('show');searchEl.value='';
    });
    searchResults.appendChild(b);
  });
  searchResults.classList.toggle('show',items.length>0);
}
searchEl.addEventListener('input',()=>{const q=searchEl.value.trim();if(q.length<2){searchResults.classList.remove('show');return}showSearch(q)});
document.addEventListener('click',e=>{if(!e.target.closest('.data43-map-search'))searchResults.classList.remove('show')});

modeEl.addEventListener('change',()=>{
  const type=modeEl.value;
  if(type==='province')selected={province:null,amphoe:null,tambon:null};
  if(type==='amphoe')selected.amphoe=null;
  load(type,true);
});
monthEl.addEventListener('change',()=>{metricCache.clear();load(currentMode,false)});
metricEl.addEventListener('change',()=>{metricCache.clear();load(currentMode,false)});
resetBtn.addEventListener('click',()=>{
  selected={province:null,amphoe:null,tambon:null};selectionEl.textContent='ยังไม่ได้เลือกพื้นที่';
  modeEl.value='province';load('province',true);
});

renderLegend();
load('province',true);
})();