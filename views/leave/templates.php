<?php
$templates = $templates ?? [];
$leave_types = $leave_types ?? [];
$hospitals = $hospitals ?? [];
$placeholders = $placeholders ?? [];
$schema_ready = $schema_ready ?? false;
?>
<div class="rp-page">
    <div class="rp-page-header mb-3">
        <div>
            <div class="rp-page-header__eyebrow">Leave Documents</div>
            <h1 class="rp-page-header__title">จัดการแบบฟอร์มวันลา</h1>
            <p class="rp-page-header__subtitle mb-0">
                อัปโหลดแบบฟอร์ม Word/PDF และเชื่อมข้อมูลใบลาเข้ากับเอกสารอัตโนมัติ
            </p>
        </div>
        <div class="rp-page-header__actions">
            <a href="index.php?c=leave&a=index" class="rp-btn rp-btn--secondary">
                <i class="bi bi-arrow-left"></i> กลับระบบวันลา
            </a>
        </div>
    </div>

    <?php if (!$schema_ready): ?>
        <div class="rp-alert rp-alert--warning mb-3">
            <span class="rp-alert__icon"><i class="bi bi-database-exclamation"></i></span>
            <div class="rp-alert__content">
                <strong>ยังไม่ได้ติดตั้งฐานข้อมูล Template Manager</strong><br>
                กรุณารันไฟล์ <code>database/migrations/20261007_leave_form_templates.sql</code> ก่อนใช้งาน
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success_msg'])): ?>
        <div class="rp-alert rp-alert--success mb-3">
            <span class="rp-alert__icon"><i class="bi bi-check-circle"></i></span>
            <div class="rp-alert__content"><?= htmlspecialchars($_SESSION['success_msg'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error_msg'])): ?>
        <div class="rp-alert rp-alert--danger mb-3">
            <span class="rp-alert__icon"><i class="bi bi-exclamation-octagon"></i></span>
            <div class="rp-alert__content"><?= htmlspecialchars($_SESSION['error_msg'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-xl-4">
            <section class="rp-card h-100">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1"><i class="bi bi-cloud-arrow-up me-2"></i>อัปโหลด Template</h2>
                        <div class="text-muted small">รองรับ DOCX และ PDF สูงสุด 10 MB</div>
                    </div>
                </div>
                <div class="rp-card__body">
                    <form action="index.php?c=leave&a=template_upload" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">ชื่อแบบฟอร์ม <span class="text-danger">*</span></label>
                            <input type="text" name="template_name" class="rp-control" maxlength="180"
                                   placeholder="เช่น แบบใบลาป่วย/ลากิจ/พักผ่อน" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภทการลาที่ใช้แบบฟอร์มนี้</label>
                            <div class="border rounded-3 p-2" style="max-height:200px;overflow:auto">
                                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="createAllTypes" checked><label for="createAllTypes" class="form-check-label fw-bold">ใช้ได้ทุกประเภทการลา</label></div>
                                <?php foreach($leave_types as $type): ?>
                                <div class="form-check">
                                    <input class="form-check-input create-leave-type" type="checkbox" name="leave_type_ids[]" value="<?= (int)$type['id'] ?>" id="ct<?= (int)$type['id'] ?>" disabled>
                                    <label class="form-check-label" for="ct<?= (int)$type['id'] ?>"><?= htmlspecialchars($type['leave_type'],ENT_QUOTES,'UTF-8') ?></label>
                                </div><?php endforeach; ?>
                            </div>
                            <small class="text-muted">ยกเลิก “ทุกประเภท” เพื่อเลือกได้หลายประเภทพร้อมกัน</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">หน่วยบริการ</label>
                            <select name="hospital_id" class="rp-control">
                                <option value="">ใช้ได้ทุกหน่วยบริการ</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?= (int)$hospital['id'] ?>">
                                        <?= htmlspecialchars($hospital['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="leaveUploadDrop" class="border border-2 rounded-3 p-3 mb-3 text-center" tabindex="0" role="button" aria-label="ลากไฟล์ DOCX หรือ PDF มาวาง หรือคลิกเพื่อเลือกไฟล์" style="border-style:dashed!important;cursor:pointer">
                            <i class="bi bi-cloud-arrow-up fs-2 d-block"></i><strong>ลากไฟล์มาวางที่นี่</strong><div id="leaveFileName" class="small text-muted">หรือกดเพื่อเลือกไฟล์ด้านล่าง</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ไฟล์ Template <span class="text-danger">*</span></label>
                            <input type="file" id="leaveTemplateFile" name="template_file" class="form-control"
                                   accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                   required>
                            <div class="form-text">
                                DOCX ใช้ Placeholder ได้ทันที · PDF จะเข้าสู่ขั้น Mapping ตำแหน่งใน Phase 12.2
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">หมายเหตุ</label>
                            <textarea name="notes" class="rp-control" rows="3" maxlength="500"
                                      placeholder="เช่น ใช้สำหรับข้าราชการส่วนท้องถิ่น"></textarea>
                        </div>

                        <button type="submit" class="rp-btn rp-btn--primary w-100" <?= !$schema_ready ? 'disabled' : '' ?>>
                            <i class="bi bi-cloud-arrow-up"></i> อัปโหลดแบบฟอร์ม
                        </button>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="rp-card mb-3">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1"><i class="bi bi-file-earmark-richtext me-2"></i>Template ที่ใช้งาน</h2>
                        <div class="text-muted small"><?= count($templates) ?> รายการ</div>
                    </div>
                </div>
                <div class="rp-card__body p-0">
                    <div class="table-responsive">
                        <table class="rp-table mb-0">
                            <thead>
                                <tr>
                                    <th>แบบฟอร์ม</th>
                                    <th>ประเภทลา/หน่วยงาน</th>
                                    <th class="text-center">Version</th>
                                    <th class="text-center">สถานะ</th>
                                    <th class="text-end">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody id="leaveTemplateRows">
                            <?php if (empty($templates)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-file-earmark-plus fs-2 d-block mb-2 opacity-50"></i>
                                        ยังไม่มี Template
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($templates as $tpl): ?>
                                    <tr data-template-id="<?= (int)$tpl['id'] ?>" draggable="true">
                                        <td><span class="text-muted me-2" title="ลากเพื่อจัดลำดับ" style="cursor:grab">⠿</span>
                                            <div class="fw-bold"><?= htmlspecialchars($tpl['template_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars($tpl['original_filename'], ENT_QUOTES, 'UTF-8') ?>
                                                · <?= htmlspecialchars($tpl['file_type'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($tpl['leave_types_label'] ?: ($tpl['leave_type'] ?: 'ทุกประเภทการลา'), ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($tpl['hospital_name'] ?: 'ทุกหน่วยบริการ', ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td class="text-center">v<?= (int)$tpl['version'] ?></td>
                                        <td class="text-center">
                                            <?php if ($tpl['file_type'] === 'PDF' && $tpl['mapping_status'] !== 'READY'): ?>
                                                <span class="rp-badge rp-badge--warning">รอ Mapping</span>
                                            <?php elseif ((int)$tpl['is_active'] === 1): ?>
                                                <span class="rp-badge rp-badge--success">ใช้งาน</span>
                                            <?php else: ?>
                                                <span class="rp-badge rp-badge--secondary">ปิดใช้งาน</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1 flex-wrap">
                                                <button type="button" class="rp-btn rp-btn--secondary rp-btn--sm" title="แก้ไขข้อมูลและประเภทลา" data-bs-toggle="modal" data-bs-target="#templateEdit<?= (int)$tpl['id'] ?>"><i class="bi bi-pencil"></i></button>
                                                <a href="index.php?c=leave&a=template_editor&id=<?= (int)$tpl['id'] ?>" class="rp-btn rp-btn--secondary rp-btn--sm" title="เปิดตัวจัดวางฟิลด์"><i class="bi bi-bounding-box"></i></a>
                                                <a href="index.php?c=leave&a=template_download&id=<?= (int)$tpl['id'] ?>"
                                                   class="rp-btn rp-btn--secondary rp-btn--sm" title="ดาวน์โหลดต้นฉบับ">
                                                    <i class="bi bi-download"></i>
                                                </a>

                                                <button type="button" class="rp-btn rp-btn--secondary rp-btn--sm" title="อัปโหลดไฟล์ใหม่เป็นเวอร์ชันใหม่" data-bs-toggle="modal" data-bs-target="#templateReplace<?= (int)$tpl['id'] ?>"><i class="bi bi-arrow-repeat"></i></button>
                                                <button type="button" class="rp-btn rp-btn--danger rp-btn--sm" title="ลบแบบฟอร์ม (ตรวจสอบเอกสารอ้างอิงก่อน)" data-bs-toggle="modal" data-bs-target="#templateDelete<?= (int)$tpl['id'] ?>"><i class="bi bi-trash3"></i></button>

                                                <form action="index.php?c=leave&a=template_toggle" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                                                    <input type="hidden" name="active" value="<?= (int)$tpl['is_active'] === 1 ? 0 : 1 ?>">
                                                    <button type="submit" class="rp-btn rp-btn--secondary rp-btn--sm"
                                                            <?= $tpl['mapping_status'] !== 'READY' ? 'disabled' : '' ?>>
                                                        <i class="bi <?= (int)$tpl['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                                    </button>
                                                </form>

                                                <form action="index.php?c=leave&a=template_archive" method="POST" class="d-inline"
                                                      onsubmit="return confirm('เก็บ Template นี้เข้าคลังหรือไม่?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                                                    <button type="submit" class="rp-btn rp-btn--danger rp-btn--sm">
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="rp-card">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1"><i class="bi bi-braces me-2"></i>Placeholder สำหรับ Word</h2>
                        <div class="text-muted small">พิมพ์ Placeholder ลงในไฟล์ DOCX ต้นฉบับ แล้วระบบจะแทนค่าจากใบลาให้อัตโนมัติ</div>
                    </div>
                </div>
                <div class="rp-card__body">
                    <div class="row g-2">
                        <?php foreach ($placeholders as $key => $description): ?>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-2 h-100 bg-light">
                                    <code><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></code>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="rp-alert rp-alert--info mt-3 mb-0">
                        <span class="rp-alert__icon"><i class="bi bi-info-circle"></i></span>
                        <div class="rp-alert__content">
                            เพื่อให้แทนค่าได้แน่นอน ควรพิมพ์ Placeholder แต่ละตัวต่อเนื่องใน Word และไม่ใส่ Bold/สีต่างกันกลางคำ เช่น
                            <code>{{employee_name}}</code>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php foreach($templates as $item): ?>
<div class="modal fade" id="templateReplace<?= (int)$item['id'] ?>" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
<form method="post" action="index.php?c=leave&a=template_replace" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>อัปโหลดไฟล์ใหม่</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<p class="mb-2 fw-bold"><?= htmlspecialchars($item['template_name'],ENT_QUOTES,'UTF-8') ?> · v<?= (int)$item['version'] ?></p>
<p class="small text-muted">ระบบจะสร้างเวอร์ชันใหม่และคัดลอกประเภทการลา/หน่วยบริการเดิม ไม่เขียนทับไฟล์เก่า หากไฟล์ใหม่ยังไม่พร้อม ระบบจะคงฉบับเดิมไว้</p>
<label class="form-label" for="replacement<?= (int)$item['id'] ?>">ไฟล์ DOCX หรือ PDF ใหม่</label>
<input class="form-control" id="replacement<?= (int)$item['id'] ?>" type="file" name="template_file" accept=".docx,.pdf" required>
<div class="form-text">สูงสุด 10 MB • ไม่เปลี่ยนเอกสารใบลาที่เคยสร้างแล้ว</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-cloud-upload me-1"></i>อัปโหลดเป็นเวอร์ชันใหม่</button></div>
</form></div></div></div>
<div class="modal fade" id="templateDelete<?= (int)$item['id'] ?>" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
<form method="post" action="index.php?c=leave&a=template_delete">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-trash3 me-2"></i>ลบแบบฟอร์มถาวร</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<p><strong><?= htmlspecialchars($item['template_name'],ENT_QUOTES,'UTF-8') ?></strong> · v<?= (int)$item['version'] ?></p>
<div class="alert alert-warning">ถ้ามีเอกสารใบลาที่เคยสร้างจากแบบฟอร์มนี้ ระบบจะไม่อนุญาตให้ลบถาวร กรุณาใช้ “เก็บเข้าคลัง” แทน</div>
<label class="form-label" for="deleteConfirm<?= (int)$item['id'] ?>">พิมพ์ DELETE เพื่อยืนยันการลบ</label>
<input id="deleteConfirm<?= (int)$item['id'] ?>" class="form-control" type="text" name="confirm_delete" pattern="DELETE" autocomplete="off" required>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-danger">ยืนยันลบถาวร</button></div>
</form></div></div></div>
<?php endforeach; ?>

<?php foreach($templates as $tpl): $selectedTypes=array_values(array_filter(explode(',',(string)($tpl['leave_type_ids']??'')))); ?>
<div class="modal fade" id="templateEdit<?= (int)$tpl['id'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" action="index.php?c=leave&a=template_update">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token,ENT_QUOTES,'UTF-8') ?>">
  <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
  <div class="modal-header"><h5 class="modal-title">แก้ไขแบบฟอร์ม #<?= (int)$tpl['id'] ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
  <label class="form-label">ชื่อแบบฟอร์ม</label><input class="form-control mb-3" name="template_name" maxlength="180" value="<?= htmlspecialchars($tpl['template_name'],ENT_QUOTES,'UTF-8') ?>" required>
  <label class="form-label">หน่วยบริการ</label>
  <select class="form-select mb-3" name="hospital_id"><option value="">ทุกหน่วยบริการ</option><?php foreach($hospitals as $h): ?><option value="<?= (int)$h['id'] ?>" <?= (int)$tpl['hospital_id']===(int)$h['id']?'selected':'' ?>><?= htmlspecialchars($h['name'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select>
  <label class="form-label">ประเภทการลาที่รองรับ (เลือกได้หลายรายการ)</label>
  <div class="border rounded p-2 mb-3" style="max-height:200px;overflow:auto">
  <div class="form-check"><input type="checkbox" class="form-check-input edit-all-types" id="all<?= (int)$tpl['id'] ?>" <?= !$selectedTypes && !$tpl['leave_type_id']?'checked':'' ?>><label class="form-check-label" for="all<?= (int)$tpl['id'] ?>">ทุกประเภท</label></div>
  <?php foreach($leave_types as $t): $checked=in_array((string)$t['id'],$selectedTypes,true)||(!$selectedTypes&&(int)$tpl['leave_type_id']===(int)$t['id']); ?>
  <div class="form-check"><input type="checkbox" name="leave_type_ids[]" class="form-check-input edit-leave-type" value="<?= (int)$t['id'] ?>" <?= $checked?'checked':'' ?> <?= (!$selectedTypes&&!$tpl['leave_type_id'])?'disabled':'' ?> id="type<?= (int)$tpl['id'] ?>_<?= (int)$t['id'] ?>"><label class="form-check-label" for="type<?= (int)$tpl['id'] ?>_<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['leave_type'],ENT_QUOTES,'UTF-8') ?></label></div>
  <?php endforeach; ?></div>
  <label class="form-label">หมายเหตุ</label><textarea class="form-control" name="notes" maxlength="500" rows="2"><?= htmlspecialchars((string)$tpl['notes'],ENT_QUOTES,'UTF-8') ?></textarea>
  </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary">บันทึก</button></div>
  </form></div></div>
</div><?php endforeach; ?>
<script>
(()=>{'use strict';
const all=document.getElementById('createAllTypes');
all?.addEventListener('change',()=>{document.querySelectorAll('.create-leave-type').forEach(c=>{c.disabled=all.checked;if(all.checked)c.checked=false})});
document.querySelectorAll('.edit-all-types').forEach(el=>el.addEventListener('change',()=>{el.closest('form').querySelectorAll('.edit-leave-type').forEach(c=>{c.disabled=el.checked;if(el.checked)c.checked=false})}));
const drop=document.getElementById('leaveUploadDrop'),file=document.getElementById('leaveTemplateFile');
if(drop&&file){drop.onclick=()=>file.click();drop.onkeydown=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();file.click()}};
['dragover','dragenter'].forEach(type=>drop.addEventListener(type,e=>{e.preventDefault();drop.classList.add('bg-info-subtle')}));
['dragleave','drop'].forEach(type=>drop.addEventListener(type,e=>{e.preventDefault();drop.classList.remove('bg-info-subtle')}));
drop.addEventListener('drop',e=>{if(e.dataTransfer.files.length===1){file.files=e.dataTransfer.files;file.dispatchEvent(new Event('change'))}});
file.addEventListener('change',()=>{document.getElementById('leaveFileName').textContent=file.files[0]?.name||'ยังไม่ได้เลือกไฟล์'})}
const tbody=document.getElementById('leaveTemplateRows');let moving=null;
tbody?.querySelectorAll('tr[data-template-id]').forEach(row=>{
row.addEventListener('dragstart',e=>{moving=row;e.dataTransfer.effectAllowed='move'});
row.addEventListener('dragover',e=>e.preventDefault());
row.addEventListener('drop',e=>{e.preventDefault();if(!moving||moving===row)return;
const box=row.getBoundingClientRect();tbody.insertBefore(moving,e.clientY<box.top+box.height/2?row:row.nextSibling);
const ids=[...tbody.querySelectorAll('tr[data-template-id]')].map(r=>Number(r.dataset.templateId));
const data=new URLSearchParams({csrf_token:<?= json_encode((string)$csrf_token) ?>,ids:JSON.stringify(ids)});
fetch('index.php?c=leave&a=template_reorder',{method:'POST',body:data,credentials:'same-origin'}).then(async r=>{const out=await r.json();if(!r.ok||!out.ok)throw Error(out.message||'บันทึกไม่สำเร็จ')}).catch(e=>{alert(e.message);location.reload()});});
row.addEventListener('dragend',()=>moving=null);
});
})();
</script>