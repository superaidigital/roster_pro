<?php
$rows=$rows??[];
$summary=$summary??[];
?>
<div class="rp-page">
  <div class="rp-page-header mb-3">
    <div>
      <div class="rp-page-header__eyebrow">DATA QUALITY</div>
      <h1 class="rp-page-header__title">ตรวจสอบคุณภาพข้อมูล 43 แฟ้ม</h1>
      <p class="rp-page-header__subtitle mb-0">ตรวจ Error / Warning / Legacy compatibility ของแต่ละรอบนำส่ง</p>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <?php foreach([
      ['submissions','ชุดข้อมูล','bi-folder2-open'],
      ['passed','ผ่าน','bi-check-circle'],
      ['needs_attention','ต้องตรวจสอบ','bi-exclamation-triangle'],
      ['errors','Error','bi-x-octagon'],
      ['warnings','Warning','bi-exclamation-circle'],
      ['legacy','Legacy','bi-arrow-repeat']
    ] as [$key,$label,$icon]): ?>
    <div class="col-6 col-lg-2">
      <div class="rp-card h-100"><div class="rp-card__body">
        <div class="small text-muted"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></div>
        <div class="fs-3 fw-bold"><?= number_format((int)($summary[$key]??0)) ?></div>
      </div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <section class="rp-card">
    <div class="rp-card__header"><h2 class="rp-card__title">ผลตรวจล่าสุด</h2></div>
    <div class="rp-card__body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr>
            <th>หน่วยบริการ</th><th>รอบเดือน</th><th>Profile</th><th>สถานะ</th>
            <th class="text-end">Error</th><th class="text-end">Warning</th><th></th>
          </tr></thead>
          <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">ยังไม่มีข้อมูล</td></tr>
          <?php else: foreach($rows as $row):
            $s=$row['submission']; $i=$row['issues'];
          ?>
            <tr>
              <td><?= htmlspecialchars(($s['hospital_code']??'').' '.($s['hospital_name']??''),ENT_QUOTES,'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($s['report_month']??''),ENT_QUOTES,'UTF-8') ?></td>
              <td><span class="rp-badge rp-badge--<?= (($s['profile_code']??'')==='RPHST_V241')?'success':'warning' ?>"><?= htmlspecialchars((string)($s['profile_code']??'-'),ENT_QUOTES,'UTF-8') ?></span></td>
              <td><?= htmlspecialchars((string)($s['status']??''),ENT_QUOTES,'UTF-8') ?></td>
              <td class="text-end fw-bold text-danger"><?= number_format((int)$i['ERROR']) ?></td>
              <td class="text-end fw-bold text-warning"><?= number_format((int)$i['WARNING']) ?></td>
              <td class="text-end"><a class="rp-btn rp-btn--secondary" href="index.php?c=data43&a=detail&id=<?= (int)$s['id'] ?>">รายละเอียด</a></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>