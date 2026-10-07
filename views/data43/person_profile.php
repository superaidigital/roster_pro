<?php
$profile=$profile??[];
$pid=$pid??'';
$person=$profile['PERSON'][0]['data']??[];
function d43_profile_date($value){
    $digits=preg_replace('/\D/','',(string)$value);
    if(strlen($digits)!==8)return (string)$value;
    return substr($digits,6,2).'/'.substr($digits,4,2).'/'.((int)substr($digits,0,4)+543);
}
function d43_profile_value($key,$value){
    if(in_array($key,['BIRTH','DDISCHARGE','DDEATH','DATE_DIAG','DATE_SERV','DATE_DETECT'],true))return d43_profile_date($value);
    if($key==='CID' && strlen((string)$value)===13)return substr($value,0,3).'******'.substr($value,-4);
    return (string)$value;
}
?>
<link rel="stylesheet" href="assets/css/data43-registry.css">
<div class="rp-page">
  <div class="rp-page-header mb-3">
    <div>
      <div class="rp-page-header__eyebrow">PERSON PROFILE</div>
      <h1 class="rp-page-header__title"><?= htmlspecialchars(trim(($person['PRENAME']??'').' '.($person['NAME']??'').' '.($person['LNAME']??'')) ?: 'โปรไฟล์บุคคล',ENT_QUOTES,'UTF-8') ?></h1>
      <p class="rp-page-header__subtitle mb-0">PID <?= htmlspecialchars($pid,ENT_QUOTES,'UTF-8') ?> · รวมข้อมูล HOME / ADDRESS / CHRONIC / DEATH</p>
    </div>
    <div class="rp-page-header__actions">
      <a class="rp-btn rp-btn--secondary" href="index.php?c=data43&a=registry&file=PERSON<?= $selected_hospital_id?'&hospital_id='.(int)$selected_hospital_id:'' ?>"><i class="bi bi-arrow-left"></i> กลับทะเบียน</a>
    </div>
  </div>

  <section class="rp-card mb-3">
    <div class="rp-card__body d-flex gap-2 flex-wrap">
      <?php foreach(['ADDRESS'=>'เพิ่มที่อยู่','CHRONIC'=>'เพิ่มโรคเรื้อรัง','DEATH'=>'บันทึกเสียชีวิต'] as $code=>$label): ?>
        <a class="rp-btn <?= $code==='DEATH'?'rp-btn--danger':'rp-btn--primary' ?>"
           href="index.php?c=data43&a=registry&file=<?= urlencode($code) ?>&pid=<?= urlencode($pid) ?><?= $selected_hospital_id?'&hospital_id='.(int)$selected_hospital_id:'' ?>">
          <i class="bi bi-plus-circle"></i> <?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="d43-profile-grid">
    <?php foreach($profile as $fileCode=>$records): ?>
      <?php foreach($records as $record): ?>
      <article class="d43-profile-card">
        <header>
          <i class="bi bi-file-earmark-medical me-2"></i><?= htmlspecialchars($fileCode,ENT_QUOTES,'UTF-8') ?>
          <span class="float-end small text-muted">#<?= (int)$record['id'] ?></span>
        </header>
        <dl>
          <?php foreach(($record['data']??[]) as $key=>$value): ?>
            <?php if($value===''||$value===null)continue; ?>
            <dt><?= htmlspecialchars((string)$key,ENT_QUOTES,'UTF-8') ?></dt>
            <dd><?= htmlspecialchars(d43_profile_value((string)$key,$value),ENT_QUOTES,'UTF-8') ?></dd>
          <?php endforeach; ?>
        </dl>
      </article>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if(!$profile): ?>
      <div class="d43-empty">ไม่พบข้อมูลบุคคล</div>
    <?php endif; ?>
  </div>
</div>