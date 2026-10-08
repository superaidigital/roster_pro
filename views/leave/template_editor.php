<?php
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$isPdf=$template['file_type']==='PDF';
?>
<style>
.lt-editor{display:grid;grid-template-columns:250px minmax(0,1fr);gap:16px}
.lt-palette,.lt-stagebox{background:#fff;border:1px solid #dbe4ef;border-radius:16px;padding:16px}
.lt-palette button{width:100%;border:1px solid #dbe4ef;background:#f8fafc;border-radius:8px;text-align:left;padding:9px;margin-bottom:7px;cursor:grab}
.lt-stage{position:relative;width:100%;aspect-ratio:210/297;background:#fff;border:1px solid #cbd5e1;box-shadow:0 6px 20px #0f172a15;overflow:hidden;touch-action:none}
.lt-field{position:absolute;border:1px solid #0891b2;background:#0891b225;border-radius:4px;cursor:move;overflow:hidden;font-size:12px;padding:2px;touch-action:none}
.lt-field.selected{outline:2px solid #f97316;z-index:3}
.lt-field .lt-del{float:right;border:0;background:white;color:#b91c1c;cursor:pointer}
.lt-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 12px}
@media(max-width:850px){.lt-editor{grid-template-columns:1fr}.lt-palette{display:grid;grid-template-columns:1fr 1fr;gap:8px}}
</style>
<div class="rp-page">
<div class="rp-page-header mb-3"><div><div class="rp-page-header__eyebrow">LEAVE TEMPLATE DESIGNER</div>
<h1 class="rp-page-header__title">จัดวางฟิลด์: <?= $esc($template['template_name']) ?></h1>
<p class="rp-page-header__subtitle"><?= $esc($template['original_filename']) ?> · <?= $esc($template['file_type']) ?> · v<?= (int)$template['version'] ?></p></div>
<a href="index.php?c=leave&a=templates" class="rp-btn rp-btn--secondary">กลับแบบฟอร์ม</a></div>
<?php if(!$isPdf): ?>
<section class="rp-card"><div class="rp-card__body p-3">
<h2 class="h5">DOCX ใช้ Placeholder ในไฟล์ Word</h2><p>เปิดต้นฉบับเพื่อแก้ {{...}} และอัปโหลดเป็นเวอร์ชันใหม่ได้ การลากพิกัดบนหน้ากระดาษรองรับเฉพาะ PDF</p>
<a class="rp-btn rp-btn--primary" href="index.php?c=leave&a=template_download&id=<?= (int)$template['id'] ?>">ดาวน์โหลด DOCX</a>
<div class="mt-3 small text-muted">ตัวอย่าง: <code>{{employee_name}}</code>, <code>{{leave_type}}</code>, <code>{{start_date_th}}</code></div>
</div></section>
<?php else: ?>
<div class="rp-alert rp-alert--warning mb-3"><div class="rp-alert__content">
ตำแหน่งที่วางจะถูกบันทึกเพื่อเตรียม Mapping เท่านั้น ยังไม่รองรับการเขียนข้อความลง PDF เพื่อสร้างใบลาอัตโนมัติ การสร้างเอกสารปัจจุบันยังใช้ DOCX
</div></div>
<form id="lt-save" action="index.php?c=leave&a=template_fields_save" method="post">
<input type="hidden" name="csrf_token" value="<?= $esc($csrf_token) ?>">
<input type="hidden" name="id" value="<?= (int)$template['id'] ?>">
<input type="hidden" name="fields" id="lt-json">
</form>
<div class="lt-editor">
<aside class="lt-palette"><h2 class="h6 fw-bold">ฟิลด์ข้อมูล</h2><p class="small text-muted">ลากไปวางบนกระดาษ หรือคลิกเพื่อเพิ่มกลางหน้า</p>
<?php foreach($placeholders as $key=>$description): ?>
<button type="button" class="lt-source" draggable="true" data-field="<?= $esc($key) ?>"><?= $esc($description) ?><span class="d-block small text-muted"><?= $esc($key) ?></span></button>
<?php endforeach; ?>
</aside>
<section class="lt-stagebox">
<div class="lt-toolbar">
<label for="lt-page">หน้าที่</label><input id="lt-page" type="number" min="1" max="100" value="1" class="form-control" style="width:85px">
<button type="button" id="lt-prev" class="btn btn-outline-secondary">ก่อนหน้า</button><button type="button" id="lt-next" class="btn btn-outline-secondary">ถัดไป</button>
<button type="button" id="lt-save-btn" class="btn btn-primary">บันทึกตำแหน่ง</button>
</div>
<p class="small text-muted">พื้นหลังกระดาษแสดง PDF หน้าที่เลือก โดยวางฟิลด์เป็นเปอร์เซ็นต์จากขนาดหน้ากระดาษ</p>
<div id="lt-stage" class="lt-stage" aria-label="พื้นที่จัดวางฟิลด์" tabindex="0"><canvas id="lt-canvas" style="width:100%;height:100%;display:block"></canvas></div>
<p id="lt-status" role="status" class="small text-muted mt-2">กำลังโหลดหน้ากระดาษ...</p>
</section></div>
<script type="module">
const pdfUrl=<?= json_encode('index.php?c=leave&a=template_download&id='.(int)$template['id'].'&preview=1') ?>;
const initial=<?= json_encode($fields,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const stage=document.getElementById('lt-stage'),canvas=document.getElementById('lt-canvas'),pageInput=document.getElementById('lt-page'),status=document.getElementById('lt-status');
const entries=initial.map(f=>({field_key:f.field_key,page_number:Number(f.page_number),x:Number(f.x),y:Number(f.y),width:Number(f.width||22),height:Number(f.height||5)}));
let currentPage=1,pdf=null,moving=null;
const clamp=(v,lo,hi)=>Math.max(lo,Math.min(hi,v));
function paint(){stage.querySelectorAll('.lt-field').forEach(n=>n.remove());
entries.forEach((f,i)=>{if(f.page_number!==currentPage)return;
const el=document.createElement('div');el.className='lt-field';el.style.left=f.x+'%';el.style.top=f.y+'%';el.style.width=f.width+'%';el.style.height=f.height+'%';
el.textContent=f.field_key;el.title='ลากเพื่อย้าย · คลิก × เพื่อลบ';
const del=document.createElement('button');del.type='button';del.className='lt-del';del.textContent='×';del.onclick=e=>{e.stopPropagation();entries.splice(i,1);paint()};el.append(del);
el.addEventListener('pointerdown',e=>{if(e.target===del)return;e.preventDefault();moving={index:i,x:e.clientX,y:e.clientY,startX:f.x,startY:f.y};el.setPointerCapture(e.pointerId);el.classList.add('selected')});
el.addEventListener('pointermove',e=>{if(!moving||moving.index!==i)return;const b=stage.getBoundingClientRect();f.x=clamp(moving.startX+(e.clientX-moving.x)/b.width*100,0,100-f.width);f.y=clamp(moving.startY+(e.clientY-moving.y)/b.height*100,0,100-f.height);el.style.left=f.x+'%';el.style.top=f.y+'%'});
el.addEventListener('pointerup',()=>{moving=null;el.classList.remove('selected')});stage.append(el)
})}
function add(key,x,y){if(entries.some(f=>f.field_key===key&&f.page_number===currentPage)){status.textContent='ฟิลด์นี้มีในหน้าปัจจุบันแล้ว';return}
entries.push({field_key:key,page_number:currentPage,x:clamp(x,0,75),y:clamp(y,0,94),width:24,height:5});paint()}
document.querySelectorAll('.lt-source').forEach(b=>{b.onclick=()=>add(b.dataset.field,38,45);b.ondragstart=e=>e.dataTransfer.setData('text/plain',b.dataset.field)});
stage.ondragover=e=>e.preventDefault();
stage.ondrop=e=>{e.preventDefault();const key=e.dataTransfer.getData('text/plain');if(!document.querySelector('.lt-source[data-field="'+CSS.escape(key)+'"]'))return;const rect=stage.getBoundingClientRect();add(key,(e.clientX-rect.left)/rect.width*100,(e.clientY-rect.top)/rect.height*100)};
async function showPage(){currentPage=clamp(Number(pageInput.value)||1,1,pdf?.numPages||100);pageInput.value=currentPage;paint();if(!pdf)return;
try{const page=await pdf.getPage(currentPage);const viewport=page.getViewport({scale:1.45}),ctx=canvas.getContext('2d');canvas.width=viewport.width;canvas.height=viewport.height;
await page.render({canvasContext:ctx,viewport}).promise;status.textContent='หน้า '+currentPage+' / '+pdf.numPages}catch(err){status.textContent='โหลดหน้ากระดาษไม่ได้: '+err.message}}
pageInput.onchange=showPage;document.getElementById('lt-prev').onclick=()=>{pageInput.value=Math.max(1,currentPage-1);showPage()};
document.getElementById('lt-next').onclick=()=>{pageInput.value=Math.min(pdf?.numPages||100,currentPage+1);showPage()};
document.getElementById('lt-save-btn').onclick=()=>{document.getElementById('lt-json').value=JSON.stringify(entries);document.getElementById('lt-save').submit()};
try{
const lib=await import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs');
lib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';
pdf=await lib.getDocument({url:pdfUrl,withCredentials:true}).promise;pageInput.max=pdf.numPages;await showPage();
}catch(error){status.textContent='ไม่สามารถแสดง PDF ได้ (ตรวจเครือข่ายและการตั้งค่า CSP): '+error.message;paint()}
</script>
<?php endif; ?>
</div>