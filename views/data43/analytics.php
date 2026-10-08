<?php $analytics_cards=$analytics_cards??[]; ?>
<div class="rp-page">
  <div class="rp-page-header mb-3">
    <div>
      <div class="rp-page-header__eyebrow">HEALTH ANALYTICS</div>
      <h1 class="rp-page-header__title">วิเคราะห์ข้อมูลสุขภาพ</h1>
      <p class="rp-page-header__subtitle mb-0">สรุปตัวชี้วัดจากข้อมูลที่ผ่านการประมวลผล โดยไม่แสดงข้อมูลรายบุคคล</p>
    </div>
    <form method="GET" class="d-flex gap-2">
      <input type="hidden" name="c" value="data43"><input type="hidden" name="a" value="analytics">
      <input class="rp-control" type="month" name="month" value="<?= htmlspecialchars($report_month??'',ENT_QUOTES,'UTF-8') ?>">
      <button class="rp-btn rp-btn--primary">แสดงผล</button>
    </form>
  </div>
  <div class="row g-3">
    <?php if(!$analytics_cards): ?><div class="col-12"><div class="rp-card"><div class="rp-card__body text-muted text-center py-5">ยังไม่มีข้อมูลวิเคราะห์สำหรับรอบเดือนนี้</div></div></div>
    <?php else: foreach($analytics_cards as $m): ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="rp-card h-100"><div class="rp-card__body">
          <div class="small text-muted"><?= htmlspecialchars($m['code'],ENT_QUOTES,'UTF-8') ?></div>
          <div class="fs-5 fw-bold mb-2"><?= htmlspecialchars($m['label'],ENT_QUOTES,'UTF-8') ?></div>
          <div class="fs-3 fw-bold"><?= number_format((int)$m['count']) ?></div>
          <div class="small text-muted"><?= htmlspecialchars($m['unit'],ENT_QUOTES,'UTF-8') ?> · พื้นที่ถูกปกปิด <?= number_format((int)$m['suppressed_areas']) ?></div>
        </div></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>