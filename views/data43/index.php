<?php
$history = $history ?? [];
$hospitals = $hospitals ?? [];
$summary = $summary ?? ['total'=>0,'complete'=>0,'incomplete'=>0,'failed'=>0];
$is_admin = $is_admin ?? false;
?>
<div class="rp-page">
    <div class="rp-page-header mb-3">
        <div>
            <div class="rp-page-header__eyebrow">PUBLIC HEALTH DATA</div>
            <h1 class="rp-page-header__title">นำส่งข้อมูล 43 แฟ้ม</h1>
            <p class="rp-page-header__subtitle mb-0">
                รับไฟล์ ZIP ราย รพ.สต. ตรวจสอบความครบถ้วน บันทึกประวัติ และลบไฟล์ชั่วคราวหลังประมวลผล
            </p>
        </div>
        <div class="rp-page-header__actions">
            <a href="index.php?c=data43&a=dashboard<?= !empty($selected_hospital_id) ? '&hospital_id='.(int)$selected_hospital_id : '' ?>" class="rp-btn rp-btn--secondary">
                <i class="bi bi-speedometer2"></i> แดชบอร์ด 43 แฟ้ม
            </a>
        </div>
    </div>

    <?php if (!$schema_ready): ?>
        <div class="rp-alert rp-alert--warning mb-3">
            <span class="rp-alert__icon"><i class="bi bi-database-exclamation"></i></span>
            <div class="rp-alert__content">
                กรุณารัน <code>database/migrations/20261007_data43_submissions.sql</code> ก่อนใช้งาน
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success_msg'])): ?>
        <div class="rp-alert rp-alert--success mb-3">
            <span class="rp-alert__icon"><i class="bi bi-check-circle"></i></span>
            <div class="rp-alert__content"><?= htmlspecialchars($_SESSION['success_msg'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error_msg'])): ?>
        <div class="rp-alert rp-alert--danger mb-3">
            <span class="rp-alert__icon"><i class="bi bi-exclamation-octagon"></i></span>
            <div class="rp-alert__content"><?= htmlspecialchars($_SESSION['error_msg'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="rp-card p-3 h-100">
                <div class="text-muted small">รายการนำส่ง</div>
                <div class="fs-3 fw-bold"><?= (int)$summary['total'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="rp-card p-3 h-100">
                <div class="text-muted small">ครบ</div>
                <div class="fs-3 fw-bold text-success"><?= (int)$summary['complete'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="rp-card p-3 h-100">
                <div class="text-muted small">ไม่ครบ</div>
                <div class="fs-3 fw-bold text-warning"><?= (int)$summary['incomplete'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="rp-card p-3 h-100">
                <div class="text-muted small">ผิดพลาด</div>
                <div class="fs-3 fw-bold text-danger"><?= (int)$summary['failed'] ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <section class="rp-card">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1"><i class="bi bi-file-earmark-zip me-2"></i>ส่งข้อมูล</h2>
                        <div class="text-muted small">ระบบจะผูกหน่วยบริการกับบัญชีผู้ใช้โดยอัตโนมัติ</div>
                    </div>
                </div>
                <div class="rp-card__body">
                    <form action="index.php?c=data43&a=upload" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                        <?php if ($is_admin): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">รพ.สต. / หน่วยบริการ</label>
                                <select name="hospital_id" class="rp-control" required>
                                    <option value="">-- เลือกหน่วยบริการ --</option>
                                    <?php foreach ($hospitals as $hospital): ?>
                                        <option value="<?= (int)$hospital['id'] ?>"
                                            <?= (int)($selected_hospital_id ?? 0) === (int)$hospital['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(($hospital['hospital_code'] ? '['.$hospital['hospital_code'].'] ' : '') . $hospital['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div class="rp-alert rp-alert--info mb-3">
                                <span class="rp-alert__icon"><i class="bi bi-building-check"></i></span>
                                <div class="rp-alert__content">
                                    นำส่งในนามหน่วยบริการที่ผูกกับบัญชีนี้
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold">รอบเดือนข้อมูล</label>
                            <input type="month" name="report_month" class="rp-control" value="<?= htmlspecialchars(date('Y-m'), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">ไฟล์ ZIP</label>
                            <input type="file" name="zip_file" class="form-control" accept=".zip,application/zip" required>
                            <div class="form-text">รองรับสูงสุด 200 MB · CSV/TXT/XLSX ภายใน ZIP</div>
                        </div>

                        <button type="submit" class="rp-btn rp-btn--primary w-100" <?= !$schema_ready ? 'disabled' : '' ?>>
                            <i class="bi bi-cloud-arrow-up"></i> นำส่งข้อมูล 43 แฟ้ม
                        </button>
                    </form>

                    <div class="rp-alert rp-alert--info mt-3 mb-0">
                        <span class="rp-alert__icon"><i class="bi bi-shield-lock"></i></span>
                        <div class="rp-alert__content">
                            ไฟล์ต้นฉบับและไฟล์ที่แตกออกจะถูกลบทันทีหลังตรวจเสร็จ ระบบเก็บเฉพาะ metadata, hash และสถิติที่จำเป็น
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="rp-card">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1">ประวัติการนำส่ง</h2>
                        <div class="text-muted small">รายการล่าสุดไม่เกิน 100 รายการ</div>
                    </div>
                    <?php if ($is_admin): ?>
                        <form action="index.php" method="GET" class="d-flex gap-2">
                            <input type="hidden" name="c" value="data43">
                            <input type="hidden" name="a" value="index">
                            <select name="hospital_id" class="rp-control" onchange="this.form.submit()">
                                <option value="">ทุกหน่วยบริการ</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?= (int)$hospital['id'] ?>"
                                        <?= (int)($selected_hospital_id ?? 0) === (int)$hospital['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($hospital['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="rp-card__body p-0">
                    <div class="table-responsive">
                        <table class="rp-table mb-0">
                            <thead>
                                <tr>
                                    <th>หน่วยบริการ</th>
                                    <th>รอบเดือน</th>
                                    <th class="text-center">ชุดข้อมูล</th>
                                    <th class="text-center">สถานะ</th>
                                    <th>ผู้ส่ง / เวลา</th>
                                    <th class="text-end">รายละเอียด</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                                        ยังไม่มีประวัติการนำส่ง
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($row['hospital_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string)$row['hospital_code'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($row['report_month'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-center">
                                            <strong><?= (int)$row['detected_files'] ?></strong>/<?= (int)$row['expected_files'] ?>
                                            <div class="small text-muted"><?= number_format((int)$row['total_rows']) ?> แถว</div>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $statusMap = [
                                                'COMPLETE' => ['success','ครบ'],
                                                'INCOMPLETE' => ['warning','ไม่ครบ'],
                                                'FAILED' => ['danger','ผิดพลาด'],
                                                'PROCESSING' => ['info','กำลังประมวลผล'],
                                            ];
                                            [$tone,$label] = $statusMap[$row['status']] ?? ['secondary',$row['status']];
                                            ?>
                                            <span class="rp-badge rp-badge--<?= $tone ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($row['uploaded_by_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($row['uploaded_at'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td class="text-end">
                                            <a href="index.php?c=data43&a=detail&id=<?= (int)$row['id'] ?>" class="rp-btn rp-btn--secondary rp-btn--sm">
                                                <i class="bi bi-eye"></i> ดู
                                            </a>
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
    </div>
</div>