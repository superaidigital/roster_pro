<?php
$esc=static fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');
$isPdf=($template['file_type']==='PDF');
?>
<style>
.ltd-shell{display:grid;grid-template-columns:minmax(200px,245px) minmax(0,1fr) minmax(205px,245px);gap:12px;align-items:start}
.ltd-pane{background:#fff;border:1px solid #dbe4ee;border-radius:14px;padding:14px;min-width:0}
.ltd-palette{display:flex;flex-direction:column;gap:6px;max-height:65vh;overflow:auto}
.ltd-palette button{border:1px solid #dbe4ee;background:#f8fafc;border-radius:9px;text-align:left;padding:9px;cursor:grab}
.ltd-palette button:hover{background:#ecfeff;border-color:#0e7490}
.ltd-workspace{background:#eaf0f6;border:1px solid #cbd5e1;border-radius:12px;padding:22px;overflow:auto;max-height:75vh}
.ltd-paper{position:relative;width:100%;max-width:720px;margin:auto;box-shadow:0 10px 28px #0f172a22;background:white;overflow:hidden}
.ltd-paper canvas{display:block;width:100%;height:100%}
.ltd-overlay{position:absolute;inset:0}
.ltd-field{position:absolute;box-sizing:border-box;border:1.5px solid #0891b2;background:#06b6d42b;color:#0f172a;display:flex;align-items:center;font-size:11px;white-space:nowrap;overflow:visible;cursor:move;touch-action:none;user-select:none}
.ltd-field.selected{border:2px solid #f97316;background:#fb923c33;z-index:3}
.ltd-field-label{overflow:hidden;text-overflow:ellipsis;pointer-events:none;padding:0 4px}
.ltd-resize{position:absolute;width:11px;height:11px;right:-5px;bottom:-5px;background:#f97316;border:2px solid white;border-radius:3px;cursor:nwse-resize}
.ltd-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:7px;margin-bottom:12px}
.ltd-toolbar .form-control{max-width:82px}
.ltd-hint{font-size:12px;color:#64748b}
.ltd-props label{font-size:12px;font-weight:650;display:block;margin-top:10px;margin-bottom:4px}
.ltd-props .form-control{min-width:0}
.ltd-file{font-size:12px;overflow-wrap:anywhere}
@media(max-width:1150px){.ltd-shell{grid-template-columns:200px minmax(0,1fr)}.ltd-props{grid-column:1/-1}}
@media(max-width:690px){.ltd-shell{grid-template-columns:1fr}.ltd-props{grid-column:auto}.ltd-palette{display:grid;grid-template-columns:1fr 1fr;max-height:250px}.ltd-workspace{padding:8px;max-height:none}}
</style>
<div class="rp-page">
<div class="rp-page-header mb-3"><div>
<div class="rp-page-header__eyebrow">ROSTER PRO • FORM DESIGNER</div>
<h1 class="rp-page-header__title">จัดวางฟิลด์บนแบบฟอร์ม</h1>
<p class="rp-page-header__subtitle ltd-file"><?= $esc($template['template_name']) ?> · <?= $esc($template['original_filename']) ?> · v<?= (int)$template['version'] ?></p>
</div><a class="rp-btn rp-btn--secondary" href="index.php?c=leave&a=templates">กลับรายการแบบฟอร์ม</a></div>
<?php if(!empty($_SESSION['success_msg'])): ?><div class="alert alert-success" role="status"><?= $esc($_SESSION['success_msg']) ?></div><?php unset($_SESSION['success_msg']);endif;?>
<?php if(!empty($_SESSION['error_msg'])): ?><div class="alert alert-danger" role="alert"><?= $esc($_SESSION['error_msg']) ?></div><?php unset($_SESSION['error_msg']);endif;?>
<?php if(!$isPdf): ?>
<section class="ltd-pane">
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3"><div><h2 class="h5 mb-1">ตัวอย่างไฟล์ Word (DOCX)</h2><p class="ltd-hint mb-0">อ่านเนื้อหาข้อความและตารางจากเอกสารจริง โดยไม่ส่งไฟล์ไปยังบริการภายนอก</p></div>
<a class="rp-btn rp-btn--primary" href="index.php?c=leave&a=template_download&id=<?= (int)$template['id'] ?>">ดาวน์โหลด DOCX</a></div>
<div id="ltd-word-status" class="ltd-hint" role="status">กำลังเปิดเอกสาร Word...</div>
<div id="ltd-word-paper" class="ltd-paper mt-3" style="min-height:480px;max-width:800px;padding:36px;line-height:1.7;overflow-wrap:anywhere" aria-label="ตัวอย่างเนื้อหา Word"></div>
<p class="ltd-hint mt-3">การแก้ไข Word ยังคงใช้ Placeholder เช่น <code>{{employee_name}}</code> ใน Microsoft Word และอัปโหลดเป็นเวอร์ชันใหม่ ไม่ใช่การลากตำแหน่งบนกระดาษ</p>
</section>
<script>
(()=>{'use strict';
const target=document.getElementById('ltd-word-paper'),status=document.getElementById('ltd-word-status');
const url='index.php?c=leave&a=template_word_preview&id=<?= (int)$template['id'] ?>';
fetch(url,{credentials:'same-origin',cache:'no-store'}).then(async r=>{const result=await r.json();if(!r.ok)throw Error(result.error||'โหลด Word ไม่สำเร็จ');return result})
.then(data=>{
 target.replaceChildren();
 for(const block of (data.blocks||[])){
  if(block.kind==='table'){
   const table=document.createElement('table');table.className='table table-bordered table-sm';
   for(const row of block.rows){const tr=document.createElement('tr');for(const cell of row){const td=document.createElement('td');td.textContent=cell;td.style.whiteSpace='pre-wrap';tr.append(td)}table.append(tr)}target.append(table);
  }else{const el=document.createElement(block.kind==='heading'?'h3':'p');el.textContent=block.text;el.style.whiteSpace='pre-wrap';target.append(el)}
 }
 if(!target.childNodes.length)target.textContent='เอกสารนี้ไม่มีข้อความที่สามารถอ่านเป็นตัวอย่างได้';
 status.textContent=data.notes+(data.truncated?' • เนื้อหาบางส่วนถูกจำกัดเพื่อความปลอดภัย':'');
}).catch(err=>{status.textContent=err.message;target.textContent='ไม่สามารถแสดงตัวอย่าง Word ได้ กรุณาดาวน์โหลดไฟล์ต้นฉบับเพื่อตรวจสอบ'});
})();
</script>
<?php else: ?>
<div class="alert alert-info">เมื่อจัดวางฟิลด์เสร็จและบันทึกแล้ว ให้เปิดใช้งาน Template เพื่อสร้าง PDF อัตโนมัติจากข้อมูลวันลาจริง (ต้องติดตั้ง FPDI/TCPDF และฟอนต์ภาษาไทยบนเครื่องเซิร์ฟเวอร์)</div>
<form id="ltd-form" method="post" action="index.php?c=leave&a=template_fields_save">
<input type="hidden" name="csrf_token" value="<?= $esc($csrf_token) ?>"><input type="hidden" name="id" value="<?= (int)$template['id'] ?>"><input type="hidden" name="fields" id="ltd-json">
</form>
<div class="ltd-toolbar ltd-pane">
<button id="ltd-prev" class="btn btn-outline-secondary btn-sm" type="button">‹ ก่อนหน้า</button>
<label for="ltd-page" class="small mb-0">หน้า</label><input id="ltd-page" class="form-control form-control-sm" type="number" min="1" value="1">
<span id="ltd-page-total" class="small text-muted">/ –</span>
<button id="ltd-next" class="btn btn-outline-secondary btn-sm" type="button">ถัดไป ›</button>
<label for="ltd-zoom" class="small ms-2 mb-0">ซูม</label><select id="ltd-zoom" class="form-select form-select-sm" style="width:auto"><option value="0.75">75%</option><option value="1" selected>100%</option><option value="1.25">125%</option><option value="1.5">150%</option></select>
<button id="ltd-reset" class="btn btn-outline-secondary btn-sm" type="button">ย้อนค่าล่าสุด</button>
<button id="ltd-save" class="btn btn-primary btn-sm ms-auto" type="button">บันทึกตำแหน่งฟิลด์</button>
</div>
<div class="ltd-shell">
<aside class="ltd-pane">
<h2 class="h6 fw-bold">1. ฟิลด์ข้อมูลใบลา</h2><p class="ltd-hint">ลากลงเอกสาร หรือคลิกเพื่อเพิ่ม</p>
<div class="ltd-palette" id="ltd-palette">
<?php foreach($placeholders as $key=>$label): ?><button class="ltd-source" type="button" draggable="true" data-key="<?= $esc($key) ?>"><strong><?= $esc($label) ?></strong><span class="d-block ltd-hint"><?= $esc($key) ?></span></button><?php endforeach; ?>
</div></aside>
<section class="ltd-pane" style="min-width:0"><h2 class="h6 fw-bold">2. ตัวอย่างเอกสารจริง</h2><div class="ltd-workspace"><div id="ltd-paper" class="ltd-paper"><canvas id="ltd-canvas"></canvas><div id="ltd-overlay" class="ltd-overlay" aria-label="พื้นที่ลากวางฟิลด์"></div></div></div><div id="ltd-pdf-fallback" hidden><p class="ltd-hint">หากโหลด PDF.js ไม่ได้ สามารถตรวจดู PDF ต้นฉบับได้ที่นี่ (โหมดสำรองไม่รองรับการจัดวางฟิลด์)</p><iframe title="PDF ต้นฉบับ" src="index.php?c=leave&a=template_download&id=<?= (int)$template['id'] ?>&amp;preview=1" style="width:100%;height:620px;border:1px solid #cbd5e1;border-radius:12px"></iframe></div><p id="ltd-status" role="status" class="ltd-hint mt-2">กำลังเตรียมตัวอย่าง PDF...</p></section>
<aside class="ltd-pane ltd-props"><h2 class="h6 fw-bold">3. คุณสมบัติฟิลด์</h2><div id="ltd-empty" class="ltd-hint">คลิกเลือกฟิลด์ในเอกสารเพื่อแก้ไข</div><div id="ltd-properties" hidden>
<label>ฟิลด์</label><div id="ltd-key" class="small fw-bold" style="overflow-wrap:anywhere"></div>
<div class="row g-2">
<div class="col-6"><label for="ltd-x">X (%)</label><input id="ltd-x" class="form-control form-control-sm" type="number" min="0" max="100" step=".1"></div>
<div class="col-6"><label for="ltd-y">Y (%)</label><input id="ltd-y" class="form-control form-control-sm" type="number" min="0" max="100" step=".1"></div>
<div class="col-6"><label for="ltd-w">กว้าง (%)</label><input id="ltd-w" class="form-control form-control-sm" type="number" min="2" max="100" step=".1"></div>
<div class="col-6"><label for="ltd-h">สูง (%)</label><input id="ltd-h" class="form-control form-control-sm" type="number" min="1" max="100" step=".1"></div>
</div><button id="ltd-delete" type="button" class="btn btn-outline-danger btn-sm w-100 mt-3">ลบฟิลด์นี้</button></div>
<p class="ltd-hint mt-3">พิกัดเป็นสัดส่วนของหน้ากระดาษจริง จึงคงตำแหน่งเมื่อซูมและเปลี่ยนหน้าจอ ใช้ปุ่มมุมขวาล่างของฟิลด์เพื่อปรับขนาด</p>
</aside>
</div>
<script type="module">
const pdfUrl=<?= json_encode('index.php?c=leave&a=template_download&id='.(int)$template['id'].'&preview=1',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const saved=<?= json_encode($fields,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const byId=id=>document.getElementById(id);
const paper=byId('ltd-paper'),overlay=byId('ltd-overlay'),canvas=byId('ltd-canvas'),status=byId('ltd-status'),pageInput=byId('ltd-page');
const clean=f=>({field_key:String(f.field_key),page_number:Number(f.page_number),x:Number(f.x),y:Number(f.y),width:Number(f.width),height:Number(f.height)});
let fields=saved.map(clean),selected=-1,pageNo=1,pdf=null,renderJob=null,sequence=0;
const clamp=(x,min,max)=>Math.max(min,Math.min(max,x));
const round=x=>Math.round(x*100)/100;
const labels=new Map([...document.querySelectorAll('.ltd-source')].map(e=>[e.dataset.key,e.querySelector('strong').textContent]));
function tell(t){status.textContent=t}
function props(){const f=fields[selected];byId('ltd-empty').hidden=!!f;byId('ltd-properties').hidden=!f;if(!f)return;
byId('ltd-key').textContent=labels.get(f.field_key)||f.field_key;
[['ltd-x','x'],['ltd-y','y'],['ltd-w','width'],['ltd-h','height']].forEach(([id,k])=>{byId(id).value=round(f[k]);});}
function paint(){overlay.replaceChildren();fields.forEach((f,i)=>{if(f.page_number!==pageNo)return;
const node=document.createElement('div');node.className='ltd-field'+(selected===i?' selected':'');
Object.assign(node.style,{left:f.x+'%',top:f.y+'%',width:f.width+'%',height:f.height+'%'});
const label=document.createElement('span');label.className='ltd-field-label';label.textContent=labels.get(f.field_key)||f.field_key;node.append(label);
const grip=document.createElement('span');grip.className='ltd-resize';grip.title='ลากเพื่อปรับขนาด';node.append(grip);
node.addEventListener('pointerdown',e=>{e.preventDefault();const handle=e.target===grip;selected=i;props();node.classList.add('selected');const bounds=overlay.getBoundingClientRect();
const current=node;const start={x:e.clientX,y:e.clientY,left:f.x,top:f.y,w:f.width,h:f.height};current.setPointerCapture(e.pointerId);
const move=ev=>{const dx=(ev.clientX-start.x)/bounds.width*100,dy=(ev.clientY-start.y)/bounds.height*100;
if(handle){f.width=round(clamp(start.w+dx,2,100-f.x));f.height=round(clamp(start.h+dy,1,100-f.y));}
else{f.x=round(clamp(start.left+dx,0,100-f.width));f.y=round(clamp(start.top+dy,0,100-f.height));}
Object.assign(current.style,{left:f.x+'%',top:f.y+'%',width:f.width+'%',height:f.height+'%'});props()};
const end=()=>{current.removeEventListener('pointermove',move);current.removeEventListener('pointerup',end);current.removeEventListener('pointercancel',end);paint()};
current.addEventListener('pointermove',move);current.addEventListener('pointerup',end);current.addEventListener('pointercancel',end);
});
overlay.append(node)});props()}
function add(key,x=35,y=45){if(!labels.has(key))return;if(fields.some(f=>f.field_key===key&&f.page_number===pageNo)){tell('ฟิลด์นี้อยู่บนหน้าปัจจุบันแล้ว');return}
fields.push({field_key:key,page_number:pageNo,x:round(clamp(x,0,75)),y:round(clamp(y,0,94)),width:24,height:5});selected=fields.length-1;paint();}
document.querySelectorAll('.ltd-source').forEach(btn=>{btn.addEventListener('click',()=>add(btn.dataset.key));btn.addEventListener('dragstart',e=>e.dataTransfer.setData('text/plain',btn.dataset.key))});
overlay.addEventListener('dragover',e=>e.preventDefault());
overlay.addEventListener('drop',e=>{e.preventDefault();const r=overlay.getBoundingClientRect();add(e.dataTransfer.getData('text/plain'),(e.clientX-r.left)/r.width*100,(e.clientY-r.top)/r.height*100)});
[['ltd-x','x'],['ltd-y','y'],['ltd-w','width'],['ltd-h','height']].forEach(([id,k])=>byId(id).addEventListener('change',()=>{const f=fields[selected];if(!f)return;const n=Number(byId(id).value);if(!Number.isFinite(n))return;
f[k]=round(clamp(n,k==='width'?2:k==='height'?1:0,k==='x'?100-f.width:k==='y'?100-f.height:k==='width'?100-f.x:100-f.y));paint()}));
byId('ltd-delete').onclick=()=>{if(selected<0)return;fields.splice(selected,1);selected=-1;paint()};
byId('ltd-reset').onclick=()=>{if(!confirm('ย้อนตำแหน่งฟิลด์กลับเป็นข้อมูลที่บันทึกล่าสุด?'))return;fields=saved.map(clean);selected=-1;paint()};
byId('ltd-save').onclick=()=>{byId('ltd-json').value=JSON.stringify(fields);byId('ltd-form').submit()};
async function draw(){if(!pdf)return;const seq=++sequence;pageNo=clamp(parseInt(pageInput.value)||1,1,pdf.numPages);pageInput.value=pageNo;selected=-1;paint();
try{if(renderJob){try{renderJob.cancel();await renderJob.promise}catch(e){if(e?.name!=='RenderingCancelledException')throw e;}renderJob=null;}
const pg=await pdf.getPage(pageNo);if(seq!==sequence)return;
const base=pg.getViewport({scale:1});const targetWidth=Math.min(680,Math.max(250,paper.parentElement.clientWidth-24))*Number(byId('ltd-zoom').value);
const scale=targetWidth/base.width;const v=pg.getViewport({scale});const dpr=Math.min(window.devicePixelRatio||1,2);
paper.style.width=v.width+'px';paper.style.height=v.height+'px';canvas.width=Math.round(v.width*dpr);canvas.height=Math.round(v.height*dpr);canvas.style.width=v.width+'px';canvas.style.height=v.height+'px';
const ctx=canvas.getContext('2d');ctx.setTransform(dpr,0,0,dpr,0,0);renderJob=pg.render({canvasContext:ctx,viewport:v});await renderJob.promise;if(seq!==sequence)return;
tell('กำลังแสดงหน้า '+pageNo+' / '+pdf.numPages);
}catch(e){if(e?.name!=='RenderingCancelledException')tell('แสดง PDF ไม่สำเร็จ: '+e.message)}}
pageInput.onchange=draw;byId('ltd-zoom').onchange=draw;byId('ltd-prev').onclick=()=>{pageInput.value=pageNo-1;draw()};byId('ltd-next').onclick=()=>{pageInput.value=pageNo+1;draw()};
try{
const lib=await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.min.mjs');
lib.GlobalWorkerOptions.workerSrc='https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.worker.min.mjs';
pdf=await lib.getDocument({url:pdfUrl,withCredentials:true}).promise;byId('ltd-page-total').textContent='/ '+pdf.numPages;pageInput.max=pdf.numPages;await draw();
}catch(e){tell('โหลด PDF.js ไม่สำเร็จ: '+e.message+' • แสดงไฟล์ต้นฉบับสำรองด้านล่าง');byId('ltd-pdf-fallback').hidden=false;byId('ltd-paper').hidden=true;byId('ltd-save').disabled=true}
</script>
<?php endif; ?>
</div>