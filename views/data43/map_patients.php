<?php
$patients=$patients??[]; $code=$code??''; $level=$level??'TAMBON';$purpose=$purpose??'CARE';
$csrf_token=$csrf_token??'';$selected_hospital_id=(int)($selected_hospital_id??0);
?>
<div class="rp-page">
 <div class="rp-page-header mb-3"><div><div class="rp-page-header__eyebrow">RESTRICTED • PATIENT REGISTRY</div>
 <h1 class="rp-page-header__title">ทะเบียนผู้ป่วยตามพื้นที่ (ข้อมูลจำกัดสิทธิ์)</h1>
 <p class="rp-page-header__subtitle">รหัสพื้นที่ <?= htmlspecialchars($code,ENT_QUOTES,'UTF-8') ?> • เฉพาะทะเบียน PERSON/ADDRESS ที่บันทึกในหน่วยบริการนี้ ไม่ใช่รายชื่อจาก ZIP Aggregate</p></div></div>
 <div class="rp-alert rp-alert--warning mb-3"><div class="rp-alert__content">ข้อมูลสุขภาพเป็นข้อมูลส่วนบุคคลอ่อนไหว ห้ามเผยแพร่หรือถ่ายภาพหน้าจอโดยไม่มีอำนาจหน้าที่ • ระบบบันทึกประวัติการเข้าถึงทุกครั้ง • จำกัดการแสดง 50 รายการ</div></div>
 <section class="rp-card"><div class="rp-card__header"><h2 class="rp-card__title">ผู้ป่วยที่ค้นพบในทะเบียนพื้นที่</h2></div>
 <div class="rp-card__body">
 <?php if(!$patients): ?><div class="text-muted">ไม่พบรายการในทะเบียนพื้นที่นี้ หรือยังไม่ได้สร้างดัชนี ADDRESS</div><?php endif; ?>
 <div class="table-responsive"><table class="rp-table"><thead><tr><th>ชื่อ–นามสกุล</th><th>เพศ</th><th>การดำเนินการ</th></tr></thead><tbody>
 <?php foreach($patients as $person): ?><tr>
 <td><?= htmlspecialchars($person['name'],ENT_QUOTES,'UTF-8') ?></td>
 <td><?= htmlspecialchars($person['sex']==='1'?'ชาย':($person['sex']==='2'?'หญิง':'ไม่ระบุ'),ENT_QUOTES,'UTF-8') ?></td>
 <td><form method="POST" action="index.php?c=data43&a=map_patients">
 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token,ENT_QUOTES,'UTF-8') ?>">
 <input type="hidden" name="purpose" value="<?= htmlspecialchars($purpose,ENT_QUOTES,'UTF-8') ?>">
 <input type="hidden" name="hospital_id" value="<?= $selected_hospital_id ?>">
 <input type="hidden" name="level" value="<?= htmlspecialchars($level,ENT_QUOTES,'UTF-8') ?>">
 <input type="hidden" name="code" value="<?= htmlspecialchars($code,ENT_QUOTES,'UTF-8') ?>">
 <input type="hidden" name="person_record_id" value="<?= (int)$person['id'] ?>">
 <button type="submit" class="rp-btn rp-btn--secondary">เปิดประวัติผู้ป่วย</button></form></td>
 </tr><?php endforeach; ?></tbody></table></div>
 <p class="small text-muted mt-2">เมื่อมีหลายหน้า ควรเพิ่ม Pagination ก่อนนำขึ้นใช้งานจริง ข้อมูลระเบียน ADDRESS ที่ไม่มีรหัสพื้นที่ครบจะไม่ถูกนำมาแสดง</p>
 <a class="rp-btn rp-btn--secondary mt-3" href="index.php?c=data43&a=map">กลับแผนที่</a>
 </div></section>
</div>