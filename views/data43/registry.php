<?php
$schemas=$schemas??[];
$records=$records??[];
$overview=$overview??[];
$is_admin=$is_admin??false;
$selected_hospital_id=$selected_hospital_id??null;
$file_code=$file_code??'PERSON';
$csrf_token=$csrf_token??'';
$prefill_pid=trim((string)($_GET['pid']??''));
?>
<link rel="stylesheet" href="assets/css/data43-registry.css">

<div class="rp-page data43-registry"
     data-d43-registry
     data-schemas='<?= htmlspecialchars(json_encode($schemas,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),ENT_QUOTES,'UTF-8') ?>'
     data-csrf="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>"
     data-hospital="<?= (int)($selected_hospital_id??0) ?>"
     data-file="<?= htmlspecialchars($file_code,ENT_QUOTES,'UTF-8') ?>"
     data-prefill-pid="<?= htmlspecialchars($prefill_pid,ENT_QUOTES,'UTF-8') ?>">

  <div class="rp-page-header mb-3">
    <div>
      <div class="rp-page-header__eyebrow">DATA43 REGISTRY</div>
      <h1 class="rp-page-header__title">ทะเบียนข้อมูล 43 แฟ้ม</h1>
      <p class="rp-page-header__subtitle mb-0">บันทึก/แก้ไขข้อมูลตามโครงสร้างมาตรฐาน พร้อม validation, audit และการส่งออก</p>
    </div>
    <div class="rp-page-header__actions">
      <a href="index.php?c=data43&a=dashboard<?= $selected_hospital_id?'&hospital_id='.(int)$selected_hospital_id:'' ?>" class="rp-btn rp-btn--secondary">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <?php if($selected_hospital_id): ?>
      <button type="button" class="rp-btn rp-btn--primary" data-d43-add="<?= htmlspecialchars($file_code,ENT_QUOTES,'UTF-8') ?>">
        <i class="bi bi-plus-circle"></i> เพิ่มข้อมูล
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if(!$schema_ready): ?>
    <div class="rp-alert rp-alert--warning mb-3">
      <span class="rp-alert__icon"><i class="bi bi-database-exclamation"></i></span>
      <div class="rp-alert__content">กรุณารัน <code>database/migrations/20261007_data43_registry.sql</code> และกำหนด <code>DATA43_RECORD_KEY</code> ก่อนใช้งาน</div>
    </div>
  <?php endif; ?>

  <?php if($is_admin): ?>
  <section class="rp-card mb-3">
    <div class="rp-card__body">
      <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
        <input type="hidden" name="c" value="data43">
        <input type="hidden" name="a" value="registry">
        <input type="hidden" name="file" value="<?= htmlspecialchars($file_code,ENT_QUOTES,'UTF-8') ?>">
        <div style="min-width:280px;flex:1">
          <label class="form-label fw-bold small">รพ.สต. / หน่วยบริการ</label>
          <select name="hospital_id" class="rp-control" onchange="this.form.submit()">
            <option value="">-- เลือกหน่วยบริการ --</option>
            <?php foreach($hospitals as $h): ?>
              <option value="<?= (int)$h['id'] ?>" <?= (int)$selected_hospital_id===(int)$h['id']?'selected':'' ?>>
                <?= htmlspecialchars(($h['hospital_code']?'['.$h['hospital_code'].'] ':'').$h['name'],ENT_QUOTES,'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
  </section>
  <?php endif; ?>

  <?php if($selected_hospital_id): ?>
  <div class="d43-kpis mb-3">
    <div class="d43-kpi"><small>ประชากร PERSON</small><strong><?= number_format((int)($overview['people']??0)) ?></strong></div>
    <div class="d43-kpi"><small>TYPEAREA 1/3/5</small><strong><?= number_format((int)(($overview['typearea']['1']??0)+($overview['typearea']['3']??0)+($overview['typearea']['5']??0))) ?></strong></div>
    <div class="d43-kpi"><small>อายุ 60+</small><strong><?= number_format((int)($overview['age']['60+']??0)) ?></strong></div>
    <div class="d43-kpi"><small>โรคเรื้อรัง</small><strong><?= number_format((int)($overview['chronic_people']??0)) ?></strong></div>
    <div class="d43-kpi"><small>DM / HT</small><strong><?= number_format((int)($overview['dm']??0)) ?> / <?= number_format((int)($overview['ht']??0)) ?></strong></div>
    <div class="d43-kpi"><small>ยังไม่ผูก HOME</small><strong><?= number_format((int)($overview['missing_home']??0)) ?></strong></div>
  </div>

  <section class="rp-card mb-3">
    <div class="rp-card__body">
      <div class="d43-tabs">
        <?php foreach($schemas as $code=>$schema): ?>
          <a class="d43-tab <?= $file_code===$code?'active':'' ?>"
             href="index.php?c=data43&a=registry&file=<?= urlencode($code) ?><?= $selected_hospital_id?'&hospital_id='.(int)$selected_hospital_id:'' ?>">
            <i class="bi <?= htmlspecialchars($schema['icon']??'bi-file-earmark',ENT_QUOTES,'UTF-8') ?>"></i>
            <?= htmlspecialchars($schema['label']??$code,ENT_QUOTES,'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="rp-card">
    <div class="rp-card__header">
      <div>
        <h2 class="rp-card__title mb-1"><?= htmlspecialchars(($schemas[$file_code]['label']??$file_code),ENT_QUOTES,'UTF-8') ?></h2>
        <div class="small text-muted">แสดง 100 รายการล่าสุด · ข้อมูลสำคัญถูกเข้ารหัสในฐานข้อมูล</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="rp-btn rp-btn--secondary" href="index.php?c=data43&a=registry_export&file=<?= urlencode($file_code) ?>&format=txt<?= $selected_hospital_id?'&hospital_id='.(int)$selected_hospital_id:'' ?>">
          <i class="bi bi-filetype-txt"></i> TXT
        </a>
        <a class="rp-btn rp-btn--secondary" href="index.php?c=data43&a=registry_export&file=<?= urlencode($file_code) ?>&format=csv<?= $selected_hospital_id?'&hospital_id='.(int)$selected_hospital_id:'' ?>">
          <i class="bi bi-filetype-csv"></i> CSV
        </a>
        <a class="rp-btn rp-btn--secondary" href="index.php?c=data43&a=registry_export&format=zip&inner=txt<?= $selected_hospital_id?'&hospital_id='.(int)$selected_hospital_id:'' ?>">
          <i class="bi bi-file-earmark-zip"></i> ZIP รวม
        </a>
        <button type="button" class="rp-btn rp-btn--primary" data-d43-add="<?= htmlspecialchars($file_code,ENT_QUOTES,'UTF-8') ?>">
          <i class="bi bi-plus-circle"></i> เพิ่ม
        </button>
      </div>
    </div>
    <div class="rp-card__body">
      <?php if(!$records): ?>
        <div class="d43-empty"><i class="bi bi-inbox fs-2 d-block mb-2"></i>ยังไม่มีข้อมูลในแฟ้มนี้</div>
      <?php else: ?>
        <div class="d43-list">
          <?php foreach($records as $row): ?>
          <div class="d43-row">
            <div>
              <div class="d43-row-title"><?= htmlspecialchars((string)($row['display']??('#'.$row['id'])),ENT_QUOTES,'UTF-8') ?></div>
              <div class="d43-row-meta">แก้ไขล่าสุด <?= htmlspecialchars((string)$row['updated_at'],ENT_QUOTES,'UTF-8') ?></div>
            </div>
            <div class="d43-row-actions">
              <?php if($file_code==='PERSON' && !empty($row['pid_ref'])): ?>
                <a class="rp-btn rp-btn--secondary" href="index.php?c=data43&a=registry_profile&pid=<?= urlencode($row['pid_ref']) ?><?= $selected_hospital_id?'&hospital_id='.(int)$selected_hospital_id:'' ?>">
                  <i class="bi bi-person-lines-fill"></i> โปรไฟล์
                </a>
              <?php endif; ?>
              <button type="button" class="rp-btn rp-btn--secondary" data-d43-edit="<?= (int)$row['id'] ?>"><i class="bi bi-pencil"></i> แก้ไข</button>
              <?php if(($data43_role??'SURVEYOR')==='ADMIN'): ?>
              <form method="POST" action="index.php?c=data43&a=registry_delete" onsubmit="return confirm('ยืนยันลบรายการนี้?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>">
                <input type="hidden" name="record_id" value="<?= (int)$row['id'] ?>">
                <?php if($selected_hospital_id): ?><input type="hidden" name="hospital_id" value="<?= (int)$selected_hospital_id ?>"><?php endif; ?>
                <button class="rp-btn rp-btn--danger" type="submit"><i class="bi bi-trash3"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<div id="d43Modal" class="d43-modal" aria-hidden="true">
  <div class="d43-backdrop" data-d43-close></div>
  <section class="d43-sheet" role="dialog" aria-modal="true" aria-labelledby="d43ModalTitle">
    <div class="d43-sheet-head">
      <div>
        <div id="d43ModalTitle" class="d43-sheet-title">เพิ่มข้อมูล</div>
        <div class="d43-note">วันที่แสดงเป็น พ.ศ. และบันทึกเป็น ค.ศ. YYYYMMDD · D_UPDATE เติมอัตโนมัติ</div>
      </div>
      <button type="button" class="rp-btn rp-btn--secondary" data-d43-close aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="d43-sheet-tabs">
      <?php foreach($schemas as $code=>$schema): ?>
        <button type="button" class="d43-sheet-tab <?= $file_code===$code?'active':'' ?>" data-d43-add="<?= htmlspecialchars($code,ENT_QUOTES,'UTF-8') ?>">
          <?= htmlspecialchars($schema['label']??$code,ENT_QUOTES,'UTF-8') ?>
        </button>
      <?php endforeach; ?>
    </div>
    <form id="d43Form">
      <input type="hidden" id="d43RecordId">
      <input type="hidden" id="d43FileCode" value="<?= htmlspecialchars($file_code,ENT_QUOTES,'UTF-8') ?>">
      <div class="d43-sheet-body">
        <div id="d43Fields" class="d43-form-grid"></div>
      </div>
      <div class="d43-sheet-foot">
        <button type="button" class="rp-btn rp-btn--secondary" data-d43-close>ยกเลิก</button>
        <button type="submit" class="rp-btn rp-btn--primary"><i class="bi bi-save"></i> บันทึกข้อมูล</button>
      </div>
    </form>
  </section>
</div>

<script src="assets/js/data43-registry.js" defer></script>
