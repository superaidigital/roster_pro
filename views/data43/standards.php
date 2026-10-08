<?php
$catalog=$catalog??[];
$rphst_expected=$rphst_expected??[];
?>
<div class="rp-page">
  <div class="rp-page-header mb-3">
    <div>
      <div class="rp-page-header__eyebrow">MOPH STANDARD</div>
      <h1 class="rp-page-header__title">มาตรฐานข้อมูล 43 แฟ้ม</h1>
      <p class="rp-page-header__subtitle mb-0">Version <?= htmlspecialchars($standard_version??'',ENT_QUOTES,'UTF-8') ?> · <?= (int)($total_structures??0) ?> โครงสร้าง · Profile รพ.สต. <?= (int)($expected_count??0) ?> โครงสร้าง</p>
    </div>
  </div>
  <section class="rp-card">
    <div class="rp-card__body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>#</th><th>Code</th><th>ชื่อแฟ้ม</th><th>กลุ่ม</th><th>รพ.สต.</th></tr></thead>
          <tbody>
          <?php foreach($catalog as $row): $code=(string)$row['code']; ?>
            <tr>
              <td><?= (int)$row['no'] ?></td>
              <td><code><?= htmlspecialchars($code,ENT_QUOTES,'UTF-8') ?></code></td>
              <td><?= htmlspecialchars((string)$row['name_th'],ENT_QUOTES,'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$row['category'],ENT_QUOTES,'UTF-8') ?></td>
              <td><?= isset($rphst_expected[$code])?'<span class="rp-badge rp-badge--success">Applicable</span>':'<span class="rp-badge">N/A</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>