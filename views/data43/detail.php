<?php
$files = $files ?? [];
$quality_summary = $quality_summary ?? null;
?>
<div class="rp-page">
    <div class="rp-page-header mb-3">
        <div>
            <div class="rp-page-header__eyebrow">SUBMISSION #<?= (int)$submission['id'] ?></div>
            <h1 class="rp-page-header__title">รายละเอียดการนำส่งข้อมูล 43 แฟ้ม</h1>
            <p class="rp-page-header__subtitle mb-0">
                <?= htmlspecialchars($submission['hospital_name'], ENT_QUOTES, 'UTF-8') ?>
                · รอบ <?= htmlspecialchars($submission['report_month'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="rp-page-header__actions">
            <a href="index.php?c=data43&a=index<?= !empty($submission['hospital_id']) ? '&hospital_id='.(int)$submission['hospital_id'] : '' ?>" class="rp-btn rp-btn--secondary">
                <i class="bi bi-arrow-left"></i> กลับ
            </a>
            <?php if (!empty($is_admin)): ?>
                <form action="index.php?c=data43&a=delete_submission"
                      method="POST"
                      class="d-inline"
                      onsubmit="return confirm('ยืนยันลบ Submission #<?= (int)$submission['id'] ?> ?\n\nข้อมูลรายการไฟล์และ Aggregate เชิงพื้นที่ที่เกี่ยวข้องจะถูกลบด้วย และไม่สามารถย้อนกลับได้');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
                    <button type="submit" class="rp-btn rp-btn--danger">
                        <i class="bi bi-trash3"></i> ลบชุดข้อมูล
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="rp-card p-3"><div class="small text-muted">ตรวจพบตาม Profile</div><div class="fs-3 fw-bold"><?= (int)$submission['detected_files'] ?>/<?= (int)$submission['expected_files'] ?></div></div></div>
        <div class="col-md-3"><div class="rp-card p-3"><div class="small text-muted">จำนวนข้อมูล</div><div class="fs-3 fw-bold"><?= number_format((int)$submission['total_rows']) ?></div></div></div>
        <div class="col-md-3"><div class="rp-card p-3"><div class="small text-muted">สถานะ</div><div class="fs-5 fw-bold"><?= htmlspecialchars($submission['status'], ENT_QUOTES, 'UTF-8') ?></div></div></div>
        <div class="col-md-3"><div class="rp-card p-3"><div class="small text-muted">เวลานำส่ง</div><div class="fw-bold"><?= htmlspecialchars($submission['uploaded_at'], ENT_QUOTES, 'UTF-8') ?></div></div></div>
    </div>

    <?php if (!empty($quality_summary)): ?>
        <section class="rp-card mb-3">
            <div class="rp-card__header">
                <div>
                    <h2 class="rp-card__title mb-1"><i class="bi bi-shield-check me-2"></i>คุณภาพข้อมูล</h2>
                    <div class="text-muted small">สรุปการตรวจโครงสร้างและการเชื่อมโยง โดยไม่เก็บ PID/CID/HID ในรายงานนี้</div>
                </div>
            </div>
            <div class="rp-card__body">
                <div class="row g-3">
                    <div class="col-md-3"><div class="small text-muted">โครงสร้างตาม Profile</div><div class="fs-4 fw-bold"><?= (int)$quality_summary['detected_expected_files'] ?>/<?= (int)$quality_summary['expected_files'] ?></div></div>
                    <div class="col-md-3"><div class="small text-muted">เชื่อม PERSON → HOME ได้</div><div class="fs-4 fw-bold"><?= number_format((int)$quality_summary['linked_people']) ?></div></div>
                    <div class="col-md-3"><div class="small text-muted">หา HOME ไม่พบ</div><div class="fs-4 fw-bold text-warning"><?= number_format((int)$quality_summary['unresolved_people']) ?></div></div>
                    <div class="col-md-3"><div class="small text-muted">ปัญหา Header</div><div class="fs-4 fw-bold text-danger"><?= (int)$quality_summary['header_issue_files'] ?></div></div>
                </div>

                <?php $missing = (array)($quality_summary['missing_codes_json'] ?? []); ?>
                <?php if ($missing): ?>
                    <hr>
                    <div class="fw-bold mb-2">โครงสร้างที่ยังไม่พบ</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($missing as $code): ?>
                            <span class="rp-badge rp-badge--warning"><?= htmlspecialchars((string)$code,ENT_QUOTES,'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php $invalidExpected = (array)($quality_summary['invalid_expected_codes_json'] ?? []); ?>
                <?php if ($invalidExpected): ?>
                    <hr>
                    <div class="fw-bold mb-2">แฟ้มที่ตรวจพบแต่ยังประมวลผลไม่ได้</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($invalidExpected as $code): ?>
                            <span class="rp-badge rp-badge--danger"><?= htmlspecialchars((string)$code,ENT_QUOTES,'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php $headerIssues = (array)($quality_summary['header_issues_json'] ?? []); ?>
                <?php if ($headerIssues): ?>
                    <hr>
                    <div class="fw-bold mb-2">คอลัมน์สำคัญที่ขาด</div>
                    <?php foreach ($headerIssues as $code=>$columns): ?>
                        <div class="small mb-1"><code><?= htmlspecialchars((string)$code,ENT_QUOTES,'UTF-8') ?></code> :
                            <?= htmlspecialchars(implode(', ',(array)$columns),ENT_QUOTES,'UTF-8') ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($submission['error_summary'])): ?>
        <div class="rp-alert rp-alert--warning mb-3">
            <span class="rp-alert__icon"><i class="bi bi-exclamation-triangle"></i></span>
            <div class="rp-alert__content"><?= htmlspecialchars($submission['error_summary'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($is_admin)): ?>
        <div class="rp-alert rp-alert--warning mb-3">
            <span class="rp-alert__icon"><i class="bi bi-shield-exclamation"></i></span>
            <div class="rp-alert__content">
                สิทธิ์ ADMIN สามารถลบ Submission นี้ได้ การลบจะลบ metadata ของไฟล์และ Aggregate เชิงพื้นที่ของ Submission นี้ด้วย
                แต่ไม่มีไฟล์ ZIP ต้นฉบับค้างอยู่ในระบบ เนื่องจากไฟล์ชั่วคราวถูกลบหลังประมวลผลแล้ว
            </div>
        </div>
    <?php endif; ?>

    <section class="rp-card">
        <div class="rp-card__header">
            <div>
                <h2 class="rp-card__title mb-1">ไฟล์ที่ตรวจพบ</h2>
                <div class="text-muted small">มาตรฐาน <?= htmlspecialchars((string)($submission['standard_version'] ?? '2.4.1'), ENT_QUOTES, 'UTF-8') ?> · Profile <?= htmlspecialchars((string)($submission['profile_code'] ?? 'RPHST_V241'), ENT_QUOTES, 'UTF-8') ?> · ไม่แสดง PII รายบุคคล</div>
            </div>
        </div>
        <div class="rp-card__body p-0">
            <div class="table-responsive">
                <table class="rp-table mb-0">
                    <thead>
                        <tr>
                            <th>รหัส/ชื่อชุดข้อมูล</th>
                            <th>ชื่อไฟล์</th>
                            <th>ประเภท</th>
                            <th class="text-end">ขนาด</th>
                            <th class="text-end">แถว</th>
                            <th class="text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($file['file_code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><?= htmlspecialchars($file['original_filename'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= strtoupper(htmlspecialchars($file['extension'], ENT_QUOTES, 'UTF-8')) ?></td>
                            <td class="text-end"><?= number_format(((int)$file['file_size']) / 1024, 1) ?> KB</td>
                            <td class="text-end"><?= $file['row_count'] === null ? '-' : number_format((int)$file['row_count']) ?></td>
                            <td class="text-center">
                                <span class="rp-badge rp-badge--<?= $file['status'] === 'VALID' ? 'success' : ($file['status'] === 'SKIPPED' ? 'warning' : 'danger') ?>">
                                    <?= htmlspecialchars($file['status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!empty($file['error_message'])): ?>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($file['error_message'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>