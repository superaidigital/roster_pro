<?php $history=$history??[]; ?>
<div class="rp-page">
  <div class="rp-page-header mb-3">
    <div>
      <div class="rp-page-header__eyebrow">SUBMISSION HISTORY</div>
      <h1 class="rp-page-header__title">สถานะ / ประวัติการนำส่ง</h1>
      <p class="rp-page-header__subtitle mb-0">ติดตามรอบเดือน ไฟล์ที่ส่ง วันที่อัปโหลด และผลตรวจย้อนหลัง</p>
    </div>
  </div>
  <section class="rp-card">
    <div class="rp-card__body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>#</th><th>หน่วยบริการ</th><th>รอบเดือน</th><th>ไฟล์</th><th>Profile</th><th>สถานะ</th><th>อัปโหลดเมื่อ</th><th></th></tr></thead>
          <tbody>
          <?php if(!$history): ?><tr><td colspan="8" class="text-center text-muted py-5">ยังไม่มีประวัติการนำส่ง</td></tr>
          <?php else: foreach($history as $s): ?>
            <tr>
              <td><?= (int)$s['id'] ?></td>
              <td><?= htmlspecialchars(($s['hospital_code']??'').' '.($s['hospital_name']??''),ENT_QUOTES,'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$s['report_month'],ENT_QUOTES,'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$s['original_filename'],ENT_QUOTES,'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($s['profile_code']??'-'),ENT_QUOTES,'UTF-8') ?></td>
              <td><span class="rp-badge rp-badge--<?= ($s['status']==='COMPLETE')?'success':(($s['status']==='FAILED')?'danger':'warning') ?>"><?= htmlspecialchars((string)$s['status'],ENT_QUOTES,'UTF-8') ?></span></td>
              <td><?= htmlspecialchars((string)$s['uploaded_at'],ENT_QUOTES,'UTF-8') ?></td>
              <td><a class="rp-btn rp-btn--secondary" href="index.php?c=data43&a=detail&id=<?= (int)$s['id'] ?>">ดู</a></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>