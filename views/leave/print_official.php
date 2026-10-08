<?php
$e=static fn($v)=>htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');
$date=static function($v){if(!$v)return '........................';$d=strtotime((string)$v);if(!$d)return '........................';$months=['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];return date('j',$d).' '.$months[(int)date('n',$d)].' '.((int)date('Y',$d)+543);};
$map=(new LeaveDocumentService($db))->replacementMap($docData);
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><title>แบบใบลา - <?= $e($docData['employee_name']) ?></title>
<style>
@page{size:A4;margin:17mm 18mm 15mm 22mm}*{box-sizing:border-box}body{font-family:'TH Sarabun New','Sarabun',Tahoma,sans-serif;font-size:16pt;line-height:1.3;color:#111;background:#eee;margin:0}.page{width:210mm;min-height:297mm;margin:12mm auto;background:white;padding:17mm 18mm 15mm 22mm;box-shadow:0 4px 15px #777}h1{font-size:19pt;text-align:center;margin:0 0 12mm}.right{text-align:right}.indent{text-indent:15mm}.line{border-bottom:1px dotted #777;display:inline;padding:0 2mm;min-width:18mm}.stats{border-collapse:collapse;width:100%;font-size:14pt}.stats th,.stats td{border:1px solid #555;padding:3px 5px;text-align:center}.sign{margin-top:5mm;text-align:center}.approval{display:grid;grid-template-columns:1fr 1fr;gap:10mm;margin-top:10mm}.block{min-height:56mm;page-break-inside:avoid}.actions{text-align:center;background:#29313c;padding:12px}button{padding:7px 18px;cursor:pointer}@media print{body{background:white}.page{margin:0;min-height:0;box-shadow:none;padding:0;width:auto}.actions{display:none}}
</style></head><body>
<div class="actions"><button onclick="window.print()">พิมพ์ / บันทึก PDF</button></div>
<main class="page"><h1>แบบใบลาป่วย ลากิจส่วนตัว ลาพักผ่อน และลาคลอดบุตร</h1>
<div class="right">เขียนที่ <span class="line"><?= $e($docData['hospital_name']) ?></span></div>
<div class="right">วันที่ <?= $e($date($docData['created_at']??null)) ?></div>
<p>เรื่อง ขออนุญาต<?= $e($leave['leave_type_name']) ?></p>
<p>เรียน ผู้มีอำนาจอนุญาตลา</p>
<p class="indent">ข้าพเจ้า <span class="line"><?= $e($docData['employee_name']) ?></span> ตำแหน่ง <span class="line"><?= $e($docData['position']) ?></span><br>สังกัด <span class="line"><?= $e($docData['hospital_name']) ?></span></p>
<p>มีความประสงค์ขออนุญาตลา (ทำเครื่องหมาย ✓)</p>
<p><?php foreach(LeaveOfficialFormService::categories() as $name): ?><?= $name===$leave['leave_type_name']?'☑':'☐' ?> <?= $e($name) ?>　<?php endforeach; ?></p>
<p>เนื่องจาก <span class="line"><?= $e($docData['reason']) ?></span></p>
<p>ตั้งแต่วันที่ <span class="line"><?= $e($date($leave['start_date'])) ?></span> ถึงวันที่ <span class="line"><?= $e($date($leave['end_date'])) ?></span> รวม <?= $e($leave['num_days']) ?> วัน</p>
<p>ในระหว่างลาสามารถติดต่อข้าพเจ้าได้ที่ <span class="line"><?= $e($officialDetails['contact_address']??'....................................................') ?></span> โทรศัพท์ <span class="line"><?= $e($officialDetails['contact_phone']??'........................') ?></span></p>
<?php if(($leave['leave_type_name']??'')==='ลาพักผ่อน' || !empty($officialDetails['delegate_name'])): ?>
<p>ผู้ปฏิบัติงานแทน <span class="line"><?= $e($officialDetails['delegate_name']??'................................') ?></span> ตำแหน่ง <span class="line"><?= $e($officialDetails['delegate_position']??'................................') ?></span><br>งานที่มอบหมาย <span class="line"><?= $e($officialDetails['delegate_duties']??'...............................................................') ?></span></p>
<?php endif; ?>
<div class="right sign">(ลงชื่อ) ................................................ ผู้ขอลา<br>(<?= $e($docData['employee_name']) ?>)<br>ตำแหน่ง <?= $e($docData['position']) ?></div>
<div class="approval">
<section class="block"><strong>สถิติการลา ปีงบประมาณ พ.ศ. <?= $e($officialSummary['fiscal_year_be']) ?></strong><table class="stats"><tr><th>ประเภท</th><th>ลามาแล้ว</th><th>ลาครั้งนี้</th><th>รวมเป็น</th></tr><?php foreach($officialSummary['rows'] as $row): ?><tr><td><?= $e($row['name']) ?></td><td><?= $e($row['previous']) ?></td><td><?= $e($row['current']) ?></td><td><?= $e($row['total']) ?></td></tr><?php endforeach; ?></table><small>จำนวนวันเป็นไปตามวิธีนับของการลาแต่ละประเภท</small><div class="sign">(ลงชื่อ) ........................................ ผู้ตรวจสอบ<br>(<?= $e($officialDetails['reviewer_name']??'................................') ?>)<br><?= $e($officialDetails['reviewer_position']??'') ?></div></section>
<section class="block"><strong>ความเห็นผู้บังคับบัญชา</strong><p><?= nl2br($e($officialDetails['supervisor_opinion']??'................................................................')) ?></p><div class="sign">(ลงชื่อ) ........................................<br>(<?= $e($officialDetails['supervisor_name']??'................................') ?>)<br><?= $e($officialDetails['supervisor_position']??'') ?></div><strong>คำสั่งผู้มีอำนาจอนุญาต</strong><p><?= $leave['status']==='APPROVED'?'☑ อนุญาต　☐ ไม่อนุญาต':($leave['status']==='REJECTED'?'☐ อนุญาต　☑ ไม่อนุญาต':'☐ อนุญาต　☐ ไม่อนุญาต') ?></p><div class="sign">(ลงชื่อ) ........................................<br>(<?= $e(in_array($leave['status'],['APPROVED','REJECTED'],true)?($docData['approver_name']??''):'') ?>)<br><?= $e(in_array($leave['status'],['APPROVED','REJECTED'],true)?($docData['approver_position']??''):'') ?></div></section>
</div><p style="font-size:11pt">หมายเหตุ: แบบรวมสี่ประเภทนี้เป็นแบบประยุกต์จากรูปแบบราชการ โปรดตรวจสอบแบบที่หน่วยงานกำหนดก่อนประกาศใช้</p>
</main></body></html>