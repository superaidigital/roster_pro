<?php
$templates = $templates ?? [];
$leave_types = $leave_types ?? [];
$hospitals = $hospitals ?? [];
$placeholders = $placeholders ?? [];
$schema_ready = $schema_ready ?? false;
?>
<div class="rp-page">
    <div class="rp-page-header mb-3">
        <div>
            <div class="rp-page-header__eyebrow">Leave Documents</div>
            <h1 class="rp-page-header__title">จัดการแบบฟอร์มวันลา</h1>
            <p class="rp-page-header__subtitle mb-0">
                อัปโหลดแบบฟอร์ม Word/PDF และเชื่อมข้อมูลใบลาเข้ากับเอกสารอัตโนมัติ
            </p>
        </div>
        <div class="rp-page-header__actions">
            <a href="index.php?c=leave&a=index" class="rp-btn rp-btn--secondary">
                <i class="bi bi-arrow-left"></i> กลับระบบวันลา
            </a>
        </div>
    </div>

    <?php if (!$schema_ready): ?>
        <div class="rp-alert rp-alert--warning mb-3">
            <span class="rp-alert__icon"><i class="bi bi-database-exclamation"></i></span>
            <div class="rp-alert__content">
                <strong>ยังไม่ได้ติดตั้งฐานข้อมูล Template Manager</strong><br>
                กรุณารันไฟล์ <code>database/migrations/20261007_leave_form_templates.sql</code> ก่อนใช้งาน
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

    <div class="row g-3">
        <div class="col-xl-4">
            <section class="rp-card h-100">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1"><i class="bi bi-cloud-arrow-up me-2"></i>อัปโหลด Template</h2>
                        <div class="text-muted small">รองรับ DOCX และ PDF สูงสุด 10 MB</div>
                    </div>
                </div>
                <div class="rp-card__body">
                    <form action="index.php?c=leave&a=template_upload" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">ชื่อแบบฟอร์ม <span class="text-danger">*</span></label>
                            <input type="text" name="template_name" class="rp-control" maxlength="180"
                                   placeholder="เช่น แบบใบลาป่วย/ลากิจ/พักผ่อน" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภทการลา</label>
                            <select name="leave_type_id" class="rp-control">
                                <option value="">ใช้ได้ทุกประเภทการลา</option>
                                <?php foreach ($leave_types as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>">
                                        <?= htmlspecialchars($type['leave_type'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">หน่วยบริการ</label>
                            <select name="hospital_id" class="rp-control">
                                <option value="">ใช้ได้ทุกหน่วยบริการ</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?= (int)$hospital['id'] ?>">
                                        <?= htmlspecialchars($hospital['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">ไฟล์ Template <span class="text-danger">*</span></label>
                            <input type="file" name="template_file" class="form-control"
                                   accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                   required>
                            <div class="form-text">
                                DOCX ใช้ Placeholder ได้ทันที · PDF จะเข้าสู่ขั้น Mapping ตำแหน่งใน Phase 12.2
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">หมายเหตุ</label>
                            <textarea name="notes" class="rp-control" rows="3" maxlength="500"
                                      placeholder="เช่น ใช้สำหรับข้าราชการส่วนท้องถิ่น"></textarea>
                        </div>

                        <button type="submit" class="rp-btn rp-btn--primary w-100" <?= !$schema_ready ? 'disabled' : '' ?>>
                            <i class="bi bi-cloud-arrow-up"></i> อัปโหลดแบบฟอร์ม
                        </button>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="rp-card mb-3">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1"><i class="bi bi-file-earmark-richtext me-2"></i>Template ที่ใช้งาน</h2>
                        <div class="text-muted small"><?= count($templates) ?> รายการ</div>
                    </div>
                </div>
                <div class="rp-card__body p-0">
                    <div class="table-responsive">
                        <table class="rp-table mb-0">
                            <thead>
                                <tr>
                                    <th>แบบฟอร์ม</th>
                                    <th>ประเภทลา/หน่วยงาน</th>
                                    <th class="text-center">Version</th>
                                    <th class="text-center">สถานะ</th>
                                    <th class="text-end">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($templates)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-file-earmark-plus fs-2 d-block mb-2 opacity-50"></i>
                                        ยังไม่มี Template
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($templates as $tpl): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($tpl['template_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars($tpl['original_filename'], ENT_QUOTES, 'UTF-8') ?>
                                                · <?= htmlspecialchars($tpl['file_type'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($tpl['leave_type'] ?: 'ทุกประเภทการลา', ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($tpl['hospital_name'] ?: 'ทุกหน่วยบริการ', ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td class="text-center">v<?= (int)$tpl['version'] ?></td>
                                        <td class="text-center">
                                            <?php if ($tpl['file_type'] === 'PDF' && $tpl['mapping_status'] !== 'READY'): ?>
                                                <span class="rp-badge rp-badge--warning">รอ Mapping</span>
                                            <?php elseif ((int)$tpl['is_active'] === 1): ?>
                                                <span class="rp-badge rp-badge--success">ใช้งาน</span>
                                            <?php else: ?>
                                                <span class="rp-badge rp-badge--secondary">ปิดใช้งาน</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1 flex-wrap">
                                                <a href="index.php?c=leave&a=template_download&id=<?= (int)$tpl['id'] ?>"
                                                   class="rp-btn rp-btn--secondary rp-btn--sm" title="ดาวน์โหลดต้นฉบับ">
                                                    <i class="bi bi-download"></i>
                                                </a>

                                                <form action="index.php?c=leave&a=template_toggle" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                                                    <input type="hidden" name="active" value="<?= (int)$tpl['is_active'] === 1 ? 0 : 1 ?>">
                                                    <button type="submit" class="rp-btn rp-btn--secondary rp-btn--sm"
                                                            <?= $tpl['file_type'] === 'PDF' && $tpl['mapping_status'] !== 'READY' ? 'disabled' : '' ?>>
                                                        <i class="bi <?= (int)$tpl['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                                    </button>
                                                </form>

                                                <form action="index.php?c=leave&a=template_archive" method="POST" class="d-inline"
                                                      onsubmit="return confirm('เก็บ Template นี้เข้าคลังหรือไม่?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                                                    <button type="submit" class="rp-btn rp-btn--danger rp-btn--sm">
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="rp-card">
                <div class="rp-card__header">
                    <div>
                        <h2 class="rp-card__title mb-1"><i class="bi bi-braces me-2"></i>Placeholder สำหรับ Word</h2>
                        <div class="text-muted small">พิมพ์ Placeholder ลงในไฟล์ DOCX ต้นฉบับ แล้วระบบจะแทนค่าจากใบลาให้อัตโนมัติ</div>
                    </div>
                </div>
                <div class="rp-card__body">
                    <div class="row g-2">
                        <?php foreach ($placeholders as $key => $description): ?>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-2 h-100 bg-light">
                                    <code><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></code>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="rp-alert rp-alert--info mt-3 mb-0">
                        <span class="rp-alert__icon"><i class="bi bi-info-circle"></i></span>
                        <div class="rp-alert__content">
                            เพื่อให้แทนค่าได้แน่นอน ควรพิมพ์ Placeholder แต่ละตัวต่อเนื่องใน Word และไม่ใส่ Bold/สีต่างกันกลางคำ เช่น
                            <code>{{employee_name}}</code>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
