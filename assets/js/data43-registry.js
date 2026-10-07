(() => {
'use strict';
const root=document.querySelector('[data-d43-registry]');
if(!root)return;
const schemas=JSON.parse(root.dataset.schemas||'{}');
const csrf=root.dataset.csrf||'';
const hospitalId=root.dataset.hospital||'';
const currentFile=root.dataset.file||'PERSON';
const prefillPid=root.dataset.prefillPid||'';
const modal=document.getElementById('d43Modal');
const form=document.getElementById('d43Form');
const fieldsEl=document.getElementById('d43Fields');
const titleEl=document.getElementById('d43ModalTitle');
const recordIdEl=document.getElementById('d43RecordId');
const fileCodeEl=document.getElementById('d43FileCode');

function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];});}
function toThaiDisplay(v){
  const s=String(v||'').replace(/\D/g,''); if(s.length!==8)return '';
  const y=Number(s.slice(0,4))+543; return s.slice(6,8)+'/'+s.slice(4,6)+'/'+y;
}
function fromThaiDisplay(v){
  const m=String(v||'').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
  if(!m)return String(v||'').replace(/\D/g,'');
  let y=Number(m[3]); if(y>2400)y-=543;
  return String(y).padStart(4,'0')+String(m[2]).padStart(2,'0')+String(m[1]).padStart(2,'0');
}
function fieldHtml(f){
 const req=f.required?' required':'';
 const ro=f.readonly?' readonly':'';
 const ph=f.placeholder?' placeholder="'+esc(f.placeholder)+'"':'';
 let control='';
 if(f.type==='select'){
   control='<select data-name="'+esc(f.name)+'"'+req+'><option value="">-- เลือก --</option>'+
     Object.entries(f.options||{}).map(function(pair){return '<option value="'+esc(pair[0])+'">'+esc(pair[1])+'</option>';}).join('')+'</select>';
 }else if(f.type==='thai-date'){
   control='<input type="text" inputmode="numeric" data-thai-date data-name="'+esc(f.name)+'" placeholder="วว/ดด/พ.ศ."'+req+'>';
 }else if(f.type==='auto'||f.type==='auto-person'){
   control='<input type="text" data-name="'+esc(f.name)+'" readonly aria-readonly="true" placeholder="ระบบกำหนดอัตโนมัติ">';
 }else if(f.type==='person-search'||f.type==='home-search'){
   const type=f.type==='home-search'?'HOME':'PERSON';
   control='<input type="text" autocomplete="off" data-search="'+type+'" data-search-display data-name="'+esc(f.name)+'"'+req+' placeholder="พิมพ์อย่างน้อย 2 ตัวอักษร">'+
     '<input type="hidden" data-search-value data-name="'+esc(f.name)+'"><div class="d43-typeahead"></div>';
 }else{
   const type=f.type==='decimal'?'number':'text';
   control='<input type="'+type+'" data-name="'+esc(f.name)+'"'+req+ro+ph+(f.maxlength?' maxlength="'+Number(f.maxlength)+'"':'')+'>';
 }
 return '<div class="d43-field"><label>'+esc(f.label)+(f.required?' *':'')+'</label>'+control+'<div class="d43-error" data-error="'+esc(f.name)+'"></div></div>';
}
function render(fileCode,data){
 data=data||{};
 const schema=schemas[fileCode]; if(!schema)return;
 titleEl.textContent=(recordIdEl.value?'แก้ไข ':'เพิ่มข้อมูล ')+schema.label;
 fileCodeEl.value=fileCode;
 fieldsEl.innerHTML=(schema.fields||[]).map(fieldHtml).join('');
 (schema.fields||[]).forEach(function(f){
   const value=data[f.name]||'';
   if(f.type==='thai-date'){
     const input=fieldsEl.querySelector('[data-name="'+CSS.escape(f.name)+'"][data-thai-date]');
     if(input)input.value=toThaiDisplay(value);
   }else if(f.type==='person-search'||f.type==='home-search'){
     const hidden=fieldsEl.querySelector('[data-search-value][data-name="'+CSS.escape(f.name)+'"]');
     const display=fieldsEl.querySelector('[data-search-display][data-name="'+CSS.escape(f.name)+'"]');
     if(hidden)hidden.value=value;if(display)display.value=value;
   }else{
     const input=fieldsEl.querySelector('[data-name="'+CSS.escape(f.name)+'"]');
     if(input)input.value=value;
   }
 });
 bindTypeahead();
 if(fileCode==='HOME') addGpsButton();
 if(prefillPid && ['ADDRESS','CHRONIC','DEATH'].includes(fileCode) && !data.PID){
   const h=fieldsEl.querySelector('[data-search-value][data-name="PID"]');
   const d=fieldsEl.querySelector('[data-search-display][data-name="PID"]');
   if(h)h.value=prefillPid;if(d)d.value=prefillPid;
 }
}
function openModal(fileCode,data,id){
 recordIdEl.value=id||'';render(fileCode||currentFile,data||{});modal.classList.add('show');document.body.style.overflow='hidden';
 setTimeout(function(){const x=fieldsEl.querySelector('input:not([readonly]),select');if(x)x.focus();},30);
}
function closeModal(){modal.classList.remove('show');document.body.style.overflow='';form.reset();recordIdEl.value='';}
document.querySelectorAll('[data-d43-add]').forEach(function(b){b.addEventListener('click',function(){openModal(b.dataset.d43Add||currentFile,{},'');});});
document.querySelectorAll('[data-d43-edit]').forEach(function(b){b.addEventListener('click',async function(){
 const id=b.dataset.d43Edit; const u=new URL('index.php',location.href);u.searchParams.set('c','data43');u.searchParams.set('a','registry_record');u.searchParams.set('id',id);if(hospitalId)u.searchParams.set('hospital_id',hospitalId);
 const res=await fetch(u);const j=await res.json();if(j.ok)openModal(j.record.file_code,j.record.data,j.record.id);else alert(j.message||'ไม่พบข้อมูล');
});});
document.querySelectorAll('[data-d43-close]').forEach(function(b){b.addEventListener('click',closeModal);});
if(modal)modal.addEventListener('click',function(e){if(e.target.classList.contains('d43-backdrop'))closeModal();});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal.classList.contains('show'))closeModal();});

function collect(){
 const out={};
 fieldsEl.querySelectorAll('[data-name]').forEach(function(el){
   const name=el.dataset.name;
   if(el.matches('[data-search-display]'))return;
   let value=el.value||'';
   if(el.hasAttribute('data-thai-date'))value=fromThaiDisplay(value);
   out[name]=value;
 });
 return out;
}
if(form)form.addEventListener('submit',async function(e){
 e.preventDefault();
 fieldsEl.querySelectorAll('.d43-error').forEach(function(x){x.textContent='';});
 const fd=new FormData();
 fd.set('csrf_token',csrf);fd.set('file_code',fileCodeEl.value);fd.set('record_id',recordIdEl.value);fd.set('payload',JSON.stringify(collect()));
 if(hospitalId)fd.set('hospital_id',hospitalId);
 const btn=form.querySelector('[type=submit]');btn.disabled=true;
 try{
   const res=await fetch('index.php?c=data43&a=registry_save',{method:'POST',body:fd});const j=await res.json();
   if(j.ok){location.reload();return;}
   if(j.errors)Object.entries(j.errors).forEach(function(pair){const x=fieldsEl.querySelector('[data-error="'+CSS.escape(pair[0])+'"]');if(x)x.textContent=pair[1];});
   if(j.message&&!j.errors)alert(j.message);
 }catch(err){alert('บันทึกไม่สำเร็จ');}finally{btn.disabled=false;}
});

function bindTypeahead(){
 fieldsEl.querySelectorAll('[data-search]').forEach(function(input){
  let timer;
  input.addEventListener('input',function(){
   const hidden=input.parentElement.querySelector('[data-search-value]'); if(hidden)hidden.value='';
   clearTimeout(timer);const q=input.value.trim();const box=input.parentElement.querySelector('.d43-typeahead');
   if(q.length<2){box.classList.remove('show');return;}
   timer=setTimeout(async function(){
    const u=new URL('index.php',location.href);u.searchParams.set('c','data43');u.searchParams.set('a','registry_search');u.searchParams.set('type',input.dataset.search);u.searchParams.set('q',q);if(hospitalId)u.searchParams.set('hospital_id',hospitalId);
    const res=await fetch(u);const j=await res.json();box.innerHTML='';
    (j.items||[]).forEach(function(item){const b=document.createElement('button');b.type='button';b.textContent=item.label;b.addEventListener('click',function(){
      input.value=item.label;hidden.value=input.dataset.search==='HOME'?item.hid:item.pid;box.classList.remove('show');
    });box.appendChild(b);});
    box.classList.toggle('show',box.childElementCount>0);
   },250);
  });
 });
}
function addGpsButton(){
 const lat=fieldsEl.querySelector('[data-name="LATITUDE"]');const lng=fieldsEl.querySelector('[data-name="LONGITUDE"]');if(!lat||!lng)return;
 const wrap=document.createElement('div');wrap.className='d43-gps full';wrap.innerHTML='<button type="button" class="rp-btn rp-btn--secondary"><i class="bi bi-crosshair"></i> ใช้พิกัดปัจจุบัน</button><span class="d43-note">ต้องใช้ HTTPS หรือ localhost</span>';
 fieldsEl.appendChild(wrap);wrap.querySelector('button').addEventListener('click',function(){
  if(!navigator.geolocation){alert('อุปกรณ์ไม่รองรับ GPS');return;}
  navigator.geolocation.getCurrentPosition(function(pos){lat.value=pos.coords.latitude.toFixed(7);lng.value=pos.coords.longitude.toFixed(7);},function(err){alert('ไม่สามารถอ่านพิกัด: '+err.message);},{enableHighAccuracy:true,timeout:12000});
 });
}
if(prefillPid && ['ADDRESS','CHRONIC','DEATH'].includes(currentFile))openModal(currentFile,{},'');
})();