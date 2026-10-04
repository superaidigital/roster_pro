<?php
$riskLabels = [
    'ROUTINE' => ['label' => 'ทั่วไป', 'class' => 'secondary'],
    'WATCH' => ['label' => 'เฝ้าระวัง', 'class' => 'warning'],
    'HIGH' => ['label' => 'เสี่ยงสูง', 'class' => 'danger'],
    'URGENT' => ['label' => 'เร่งด่วน', 'class' => 'danger'],
];

$visitTypeLabels = [
    'HOME_VISIT' => 'เยี่ยมบ้านทั่วไป',
    'CHRONIC_FOLLOWUP' => 'ติดตามโรคเรื้อรัง',
    'WOUND_CARE' => 'ดูแลแผล',
    'MATERNAL_CHILD' => 'แม่และเด็ก',
    'ELDERLY' => 'ผู้สูงอายุ',
    'OTHER' => 'อื่น ๆ',
];
?>
<link rel="stylesheet" href="public/css/field.css?v=20261004-field-v3">

<div class="container-fluid rp-field-page px-0">
    <?php if (!empty($_SESSION['success_msg'])): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="status">
            <i class="bi bi-check-circle-fill"></i>
            <span><?= htmlspecialchars($_SESSION['success_msg'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= htmlspecialchars($_SESSION['error_msg'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <div class="rp-field-hero mb-3">
        <div>
            <div class="rp-field-eyebrow">FOLLOW-UP WORK QUEUE</div>
            <h2 class="mb-1">คิวติดตามงานเยี่ยมบ้าน</h2>
            <p class="mb-0">เรียงงานเสี่ยงสูงและงานเกินกำหนดขึ้นก่อน เพื่อช่วยลดรายการตกหล่นหลังกลับจากพื้นที่</p>
        </div>
        <a href="index.php?c=field" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i>กลับหน้าบันทึก
        </a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="rp-field-stat rp-field-stat-danger">
                <span>เกินกำหนด</span>
                <strong><?= number_format((int)$followUpSummary['overdue']) ?></strong>
                <i class="bi bi-exclamation-octagon-fill"></i>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="rp-field-stat rp-field-stat-warning">
                <span>ครบกำหนดวันนี้</span>
                <strong><?= number_format((int)$followUpSummary['today']) ?></strong>
                <i class="bi bi-calendar2-check-fill"></i>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="rp-field-stat">
                <span>ภายใน 7 วัน</span>
                <strong><?= number_format((int)$followUpSummary['next_7_days']) ?></strong>
                <i class="bi bi-calendar2-week"></i>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="rp-field-stat rp-field-stat-danger">
                <span>เสี่ยงสูง/เร่งด่วน</span>
                <strong><?= number_format((int)$followUpSummary['high_risk']) ?></strong>
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
        </div>
    </div>

    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div>
                <h5 class="mb-1"><i class="bi bi-list-check text-primary me-2"></i>รายการรอติดตาม</h5>
                <small class="text-muted">แสดงเฉพาะรายการที่บันทึกเสร็จแล้วและยังมีสถานะรอติดตาม</small>
            </div>
            <span class="badge bg-primary-subtle text-primary-emphasis border">
                <?= number_format(count($queue)) ?> รายการ
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>กำหนดติดตาม</th>
                            <th>ผู้รับบริการ</th>
                            <th>ประเภท</th>
                            <th>ความเสี่ยง</th>
                            <th>ประเด็นติดตาม</th>
                            <th>ผู้บันทึก/หน่วยบริการ</th>
                            <th>ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$queue): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success"></i>
                                ไม่มีงานติดตามค้างในขณะนี้
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($queue as $item):
                            $days = (int)($item['days_until_followup'] ?? 0);
                            if ($days < 0) {
                                $dueLabel = 'เกิน ' . abs($days) . ' วัน';
                                $dueClass = 'danger';
                            } elseif ($days === 0) {
                                $dueLabel = 'วันนี้';
                                $dueClass = 'warning';
                            } elseif ($days === 1) {
                                $dueLabel = 'พรุ่งนี้';
                                $dueClass = 'info';
                            } else {
                                $dueLabel = 'อีก ' . $days . ' วัน';
                                $dueClass = $days <= 7 ? 'info' : 'secondary';
                            }

                            $riskMeta = $riskLabels[$item['risk_level'] ?? 'ROUTINE'] ?? $riskLabels['ROUTINE'];
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars((string)$item['follow_up_date'], ENT_QUOTES, 'UTF-8') ?></div>
                                <span class="badge bg-<?= htmlspecialchars($dueClass, ENT_QUOTES, 'UTF-8') ?> mt-1">
                                    <?= htmlspecialchars($dueLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars((string)($item['patient_name'] ?: $item['patient_ref']), ENT_QUOTES, 'UTF-8') ?></div>
                                <small class="text-muted"><?= htmlspecialchars((string)$item['patient_ref'], ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td><?= htmlspecialchars($visitTypeLabels[$item['visit_type']] ?? (string)$item['visit_type'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge bg-<?= htmlspecialchars($riskMeta['class'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($riskMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!empty($item['referral_required'])): ?>
                                    <span class="badge bg-info text-dark d-block mt-1">มีแผนส่งต่อ</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars((string)($item['chief_concern'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($item['care_plan'])): ?>
                                    <small class="text-muted d-block mt-1"><?= htmlspecialchars(mb_strimwidth((string)$item['care_plan'], 0, 120, '…', 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars((string)($item['created_by_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                <small class="text-muted"><?= htmlspecialchars((string)($item['hospital_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td>
                                <form action="index.php?c=field&a=complete_followup"
                                      method="POST"
                                      onsubmit="return confirm('ยืนยันว่าดำเนินการติดตามรายการนี้แล้ว?');">
                                    <?= security_csrf_input() ?>
                                    <input type="hidden" name="visit_id" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="return_to" value="followups">
                                    <button type="submit" class="btn btn-success btn-sm" data-loading-text="กำลังปิดงาน...">
                                        <i class="bi bi-check2-circle me-1"></i>ปิดงานติดตาม
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
