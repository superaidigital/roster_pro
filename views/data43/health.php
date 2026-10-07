<?php
$health=$health??['status'=>'ERROR','error_count'=>0,'warning_count'=>0,'checks'=>[]];
$statusClass=match($health['status']??'ERROR'){'OK'=>'success','WARNING'=>'warning',default=>'danger'};
?>
<style>
.d43-health-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
.d43-health-card{border:1px solid var(--rp-border,#dbe3ee);border-radius:16px;background:var(--rp-card-bg,#fff);padding:1rem}
.d43-health-row{display:grid;grid-template-columns:minmax(180px,.8fr) minmax(120px,.35fr) 1.4fr;gap:.75rem;align-items:start;padding:.8rem 0;border-bottom:1px solid var(--rp-border,#e5e7eb)}
.d43-health-row:last-child{border-bottom:0}
.d43-health-status{font-weight:800}
.d43-health-status.OK{color:#12805c}.d43-health-status.ERROR{color:#c62828}.d43-health-status.WARNING{color:#a15c00}
@media(max-width:760px){.d43-health-grid{grid-template-columns:1fr}.d43-health-row{grid-template-columns:1fr}}
</style>

<div class="rp-page">
  <div class="rp-page-header mb-3">
    <div>
      <div class="rp-page-header__eyebrow">DATA43 SYSTEM HEALTH</div>
      <h1 class="rp-page-header__title">ตรวจสุขภาพระบบข้อมูล 43 แฟ้ม</h1>
      <p class="rp-page-header__subtitle mb-0">ตรวจ PHP Extension, Database Schema, Encryption และ Storage ก่อนใช้งานจริง</p>
    </div>
    <div class="rp-page-header__actions">
      <a class="rp-btn rp-btn--secondary" href="index.php?c=data43&a=index"><i class="bi bi-arrow-left"></i> กลับ</a>
      <a class="rp-btn rp-btn--primary" href="index.php?c=data43&a=health"><i class="bi bi-arrow-clockwise"></i> ตรวจใหม่</a>
    </div>
  </div>

  <div class="d43-health-grid mb-3">
    <div class="d43-health-card"><div class="small text-muted">สถานะรวม</div><div class="fs-3 fw-bold"><?= htmlspecialchars((string)$health['status'],ENT_QUOTES,'UTF-8') ?></div></div>
    <div class="d43-health-card"><div class="small text-muted">ข้อผิดพลาด</div><div class="fs-3 fw-bold text-danger"><?= (int)$health['error_count'] ?></div></div>
    <div class="d43-health-card"><div class="small text-muted">คำเตือน</div><div class="fs-3 fw-bold text-warning"><?= (int)$health['warning_count'] ?></div></div>
  </div>

  <section class="rp-card">
    <div class="rp-card__header"><h2 class="rp-card__title mb-0">รายการตรวจสอบ</h2></div>
    <div class="rp-card__body">
      <?php foreach($health['checks'] as $check): ?>
      <div class="d43-health-row">
        <div>
          <div class="fw-bold"><?= htmlspecialchars((string)$check['name'],ENT_QUOTES,'UTF-8') ?></div>
          <div class="small text-muted"><?= htmlspecialchars((string)$check['group'],ENT_QUOTES,'UTF-8') ?></div>
        </div>
        <div class="d43-health-status <?= htmlspecialchars((string)$check['status'],ENT_QUOTES,'UTF-8') ?>">
          <?= htmlspecialchars((string)$check['status'],ENT_QUOTES,'UTF-8') ?>
        </div>
        <div>
          <div><?= htmlspecialchars((string)$check['detail'],ENT_QUOTES,'UTF-8') ?></div>
          <?php if(!empty($check['fix'])): ?><div class="small text-muted mt-1">แนวทางแก้: <?= htmlspecialchars((string)$check['fix'],ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>