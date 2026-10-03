<?php
$exportParams = array_filter([
    'status' => $filters['status'] ?? '',
    'risk_level' => $filters['risk_level'] ?? '',
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
    'q' => $filters['q'] ?? '',
], static fn($value) => $value !== '');
$exportUrl = 'index.php?c=field&a=export_csv' . ($exportParams ? '&' . http_build_query($exportParams) : '');

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
<link rel="stylesheet" href="public/css/field.css?v=20261004-field-v1">

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
            <div class="rp-field-eyebrow">COMMUNITY FIELD WORK</div>
            <h2 class="mb-1">เยี่ยมบ้านและงานชุมชน</h2>
            <p class="mb-0">บันทึกข้อมูลภาคสนามแบบ Mobile-first พร้อม GPS, ร่างออฟไลน์ และรูปประกอบที่ควบคุมสิทธิ์</p>
        </div>
        <div class="rp-network-chip" id="fieldNetworkChip" aria-live="polite">
            <span class="rp-network-dot"></span>
            <span id="fieldNetworkText">กำลังตรวจสอบเครือข่าย</span>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="rp-field-stat">
                <span>เยี่ยมวันนี้</span>
                <strong><?= number_format($summary['today_count']) ?></strong>
                <i class="bi bi-calendar2-check"></i>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="rp-field-stat">
                <span>ร่างค้าง</span>
                <strong><?= number_format($summary['draft_count']) ?></strong>
                <i class="bi bi-pencil-square"></i>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="rp-field-stat">
                <span>เสร็จแล้ว</span>
                <strong><?= number_format($summary['completed_count']) ?></strong>
                <i class="bi bi-check2-circle"></i>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="rp-field-stat">
                <span>ทั้งหมด</span>
                <strong><?= number_format($summary['total']) ?></strong>
                <i class="bi bi-clipboard2-pulse"></i>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="rp-field-stat rp-field-stat-danger">
                <span>งานเสี่ยงสูง</span>
                <strong><?= number_format($summary['high_risk_count'] ?? 0) ?></strong>
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="rp-field-stat rp-field-stat-warning">
                <span>ติดตามถึงกำหนด</span>
                <strong><?= number_format($summary['followup_due_count'] ?? 0) ?></strong>
                <i class="bi bi-calendar2-event-fill"></i>
            </div>
        </div>
    </div>

    <div class="rp-field-layout">
        <section class="card rp-field-form-card">
            <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <div>
                    <h5 class="mb-1"><i class="bi bi-house-heart-fill text-primary me-2"></i><?= $editVisit ? 'แก้ไขร่างเยี่ยมบ้าน' : 'บันทึกเยี่ยมบ้าน' ?></h5>
                    <small class="text-muted">ระบบจะเก็บร่างบนอุปกรณ์นี้ชั่วคราวสูงสุด 8 ชั่วโมง และไม่เก็บรูปในร่างออฟไลน์</small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($editVisit): ?>
                        <a class="btn btn-outline-secondary btn-sm" id="fieldCancelEdit" href="index.php?c=field">
                            <i class="bi bi-x-circle me-1"></i>ยกเลิกแก้ไข
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-light btn-sm" id="fieldDiscardDraft">
                        <i class="bi bi-trash3 me-1"></i>ล้างร่างบนเครื่อง
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div id="fieldDraftNotice" class="alert alert-info d-none" role="status"></div>

                <form action="index.php?c=field&a=save"
                      method="POST"
                      enctype="multipart/form-data"
                      id="fieldVisitForm"
                      data-rp-wizard="field"
                      data-field-user="<?= (int)$user['id'] ?>"
                      data-field-record="<?= (int)($editVisit['id'] ?? 0) ?>"
                      data-field-draft-key="<?= htmlspecialchars((string)($_SESSION['field_draft_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-field-saved="<?= isset($_GET['saved']) ? '1' : '0' ?>">
                    <?= security_csrf_input() ?>
                    <input type="hidden" name="visit_id" value="<?= (int)($editVisit['id'] ?? 0) ?>">
                    <input type="hidden" name="status" id="fieldStatus" value="DRAFT">

                    <section data-rp-step data-rp-step-title="ข้อมูลทั่วไป">
                        <div class="rp-step-heading">
                            <span class="rp-step-icon"><i class="bi bi-person-vcard-fill"></i></span>
                            <div>
                                <h5>ข้อมูลทั่วไป</h5>
                                <p>ระบุผู้รับบริการ วันที่ และวัตถุประสงค์การลงพื้นที่</p>
                            </div>
                        </div>

                        <?php if ($canSelectHospital): ?>
                        <div class="mb-3">
                            <label class="form-label" for="hospital_id">หน่วยบริการ <span class="text-danger">*</span></label>
                            <select class="form-select" id="hospital_id" name="hospital_id" required>
                                <option value="">เลือก รพ.สต.</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?= (int)$hospital['id'] ?>">
                                        <?= htmlspecialchars($hospital['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label" for="patient_ref">HN / รหัสผู้รับบริการ / รหัสครัวเรือน <span class="text-danger">*</span></label>
                                <input class="form-control" id="patient_ref" name="patient_ref" maxlength="50" required autocomplete="off">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="patient_name">ชื่อผู้รับบริการ</label>
                                <input class="form-control" id="patient_name" name="patient_name" maxlength="150" autocomplete="off">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="patient_age">อายุ</label>
                                <input class="form-control" id="patient_age" name="patient_age" type="number" min="0" max="130" inputmode="numeric">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="visit_date">วันที่เยี่ยม <span class="text-danger">*</span></label>
                                <input class="form-control" id="visit_date" name="visit_date" type="date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="visit_type">ประเภทการเยี่ยม</label>
                                <select class="form-select" id="visit_type" name="visit_type">
                                    <?php foreach ($visitTypeLabels as $value => $label): ?>
                                        <option value="<?= $value ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="chief_concern">เหตุผลหลัก/ประเด็นติดตาม</label>
                                <input class="form-control" id="chief_concern" name="chief_concern" maxlength="255">
                            </div>
                        </div>
                    </section>

                    <section data-rp-step data-rp-step-title="สัญญาณชีพ">
                        <div class="rp-step-heading">
                            <span class="rp-step-icon"><i class="bi bi-activity"></i></span>
                            <div>
                                <h5>สัญญาณชีพและอาการ</h5>
                                <p>กรอกเฉพาะค่าที่ได้วัดจริง ระบบไม่บังคับทุกช่อง</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-6 col-md-4 col-xl-2">
                                <label class="form-label" for="systolic">SBP (mmHg)</label>
                                <input class="form-control" id="systolic" name="systolic" type="number" min="40" max="320" inputmode="numeric">
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <label class="form-label" for="diastolic">DBP (mmHg)</label>
                                <input class="form-control" id="diastolic" name="diastolic" type="number" min="20" max="220" inputmode="numeric">
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <label class="form-label" for="pulse">ชีพจร (ครั้ง/นาที)</label>
                                <input class="form-control" id="pulse" name="pulse" type="number" min="20" max="260" inputmode="numeric">
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <label class="form-label" for="temperature">อุณหภูมิ (°C)</label>
                                <input class="form-control" id="temperature" name="temperature" type="number" step="0.1" min="25" max="50" inputmode="decimal">
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <label class="form-label" for="spo2">SpO₂ (%)</label>
                                <input class="form-control" id="spo2" name="spo2" type="number" min="1" max="100" inputmode="numeric">
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <label class="form-label" for="weight">น้ำหนัก (kg)</label>
                                <input class="form-control" id="weight" name="weight" type="number" step="0.1" min="0.1" max="600" inputmode="decimal">
                            </div>
                            <div class="col-6 col-md-4 col-xl-2">
                                <label class="form-label" for="height">ส่วนสูง (cm)</label>
                                <input class="form-control" id="height" name="height" type="number" step="0.1" min="20" max="280" inputmode="decimal">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="symptoms">อาการ/ข้อมูลจากการซักถาม</label>
                                <textarea class="form-control" id="symptoms" name="symptoms" rows="4" maxlength="3000"></textarea>
                            </div>
                        </div>
                    </section>

                    <section data-rp-step data-rp-step-title="ประเมินและยืนยัน">
                        <div class="rp-step-heading">
                            <span class="rp-step-icon"><i class="bi bi-clipboard2-check-fill"></i></span>
                            <div>
                                <h5>ประเมินและยืนยัน</h5>
                                <p>บันทึกผลประเมิน แผนดูแล พิกัด และรูปประกอบเมื่อจำเป็น</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="assessment">ผลการประเมิน</label>
                                <textarea class="form-control" id="assessment" name="assessment" rows="4" maxlength="3000"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="care_plan">แผนดูแล/ติดตามต่อ</label>
                                <textarea class="form-control" id="care_plan" name="care_plan" rows="4" maxlength="3000"></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="risk_level">ระดับความเสี่ยง</label>
                                <select class="form-select" id="risk_level" name="risk_level">
                                    <option value="ROUTINE">ทั่วไป</option>
                                    <option value="WATCH">เฝ้าระวัง</option>
                                    <option value="HIGH">เสี่ยงสูง</option>
                                    <option value="URGENT">เร่งด่วน</option>
                                </select>
                                <div class="form-text">เจ้าหน้าที่เป็นผู้ประเมินระดับความเสี่ยงตามแนวทางของหน่วยงาน</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="follow_up_date">วันนัดติดตามครั้งถัดไป</label>
                                <input class="form-control" id="follow_up_date" name="follow_up_date" type="date">
                            </div>
                            <div class="col-md-4">
                                <div class="form-check rp-referral-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="referral_required" name="referral_required">
                                    <label class="form-check-label fw-semibold" for="referral_required">ต้องส่งต่อ/ประสานหน่วยบริการอื่น</label>
                                </div>
                            </div>
                            <div class="col-12 d-none" id="fieldReferralNoteWrap">
                                <label class="form-label" for="referral_note">รายละเอียดการส่งต่อ/ประสานงาน</label>
                                <textarea class="form-control" id="referral_note" name="referral_note" rows="3" maxlength="500"></textarea>
                            </div>

                            <div class="col-12">
                                <div class="rp-location-box">
                                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                        <div>
                                            <strong><i class="bi bi-geo-alt-fill me-1 text-danger"></i>พิกัดบ้าน/จุดเยี่ยม</strong>
                                            <div class="small text-muted" id="fieldLocationStatus">ยังไม่ได้จับพิกัด</div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary" id="fieldGetLocation">
                                            <i class="bi bi-crosshair me-1"></i>จับพิกัดปัจจุบัน
                                        </button>
                                    </div>
                                    <input type="hidden" name="latitude" id="fieldLatitude">
                                    <input type="hidden" name="longitude" id="fieldLongitude">
                                    <input type="hidden" name="accuracy_m" id="fieldAccuracy">
                                    <div class="mt-3">
                                        <label class="form-label" for="address_note">รายละเอียดสถานที่/จุดสังเกต</label>
                                        <input class="form-control" id="address_note" name="address_note" maxlength="255">
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="fieldPhotos">รูปประกอบ (ไม่เกิน 3 รูป, รูปละ 5 MB)</label>
                                <input class="form-control" id="fieldPhotos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" multiple>
                                <div id="fieldPhotoPreview" class="rp-photo-preview mt-2"></div>
                                <div class="form-text">รูปไม่ถูกเก็บใน Offline Draft และจะจัดเก็บในพื้นที่ที่ต้องผ่านสิทธิ์ระบบเท่านั้น</div>
                            </div>

                            <div class="col-12">
                                <div class="form-check rp-consent-box">
                                    <input class="form-check-input" type="checkbox" value="1" id="photo_consent" name="photo_consent">
                                    <label class="form-check-label" for="photo_consent">
                                        ยืนยันว่าการบันทึก/แนบรูปเป็นไปตามสิทธิ์และแนวปฏิบัติของหน่วยงาน
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="rp-field-submit mt-4">
                            <button type="submit" class="btn btn-light" data-field-status="DRAFT" data-loading-text="กำลังบันทึกร่าง...">
                                <i class="bi bi-save2 me-1"></i>บันทึกร่าง
                            </button>
                            <button type="submit" class="btn btn-success" data-field-status="COMPLETED" data-loading-text="กำลังบันทึกผล...">
                                <i class="bi bi-check2-circle me-1"></i>บันทึกเสร็จสิ้น
                            </button>
                        </div>
                    </section>
                </form>
            </div>
        </section>

        <aside class="card rp-field-list-card">
            <div class="card-header">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <div>
                        <h5 class="mb-1"><i class="bi bi-clock-history text-primary me-2"></i>รายการล่าสุด</h5>
                        <small class="text-muted">ข้อมูลที่คุณมีสิทธิ์เข้าถึงตามบทบาทและหน่วยบริการ</small>
                    </div>
                    <a class="btn btn-outline-success btn-sm"
                       href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                    </a>
                </div>

                <form class="row g-2 mt-2" method="GET" action="index.php">
                    <input type="hidden" name="c" value="field">
                    <div class="col-12 col-md-5">
                        <input class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>" placeholder="ค้นหารหัส/ชื่อ/เหตุผล">
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <select class="form-select form-select-sm" name="status">
                            <option value="">ทุกสถานะ</option>
                            <option value="DRAFT" <?= $filters['status'] === 'DRAFT' ? 'selected' : '' ?>>ร่าง</option>
                            <option value="COMPLETED" <?= $filters['status'] === 'COMPLETED' ? 'selected' : '' ?>>เสร็จแล้ว</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 d-grid">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>ค้นหา</button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>วันที่</th>
                                <th>ผู้รับบริการ</th>
                                <th>ประเภท</th>
                                <th>ความเสี่ยง</th>
                                <th>ติดตาม</th>
                                <th>ผู้บันทึก</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$visits): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        ยังไม่มีข้อมูลเยี่ยมบ้าน
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($visits as $visit): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($visit['visit_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($visit['patient_name'] ?: $visit['patient_ref'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($visit['patient_ref'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($visitTypeLabels[$visit['visit_type']] ?? $visit['visit_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <?php $riskMeta = $riskLabels[$visit['risk_level'] ?? 'ROUTINE'] ?? $riskLabels['ROUTINE']; ?>
                                        <td>
                                            <span class="badge bg-<?= htmlspecialchars($riskMeta['class'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($riskMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <?php if (!empty($visit['referral_required'])): ?>
                                                <span class="badge bg-info text-dark d-block mt-1">ส่งต่อ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($visit['follow_up_date'])): ?>
                                                <div class="fw-semibold"><?= htmlspecialchars($visit['follow_up_date'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <small class="text-muted"><?= ($visit['follow_up_status'] ?? 'NONE') === 'DONE' ? 'ติดตามแล้ว' : 'รอติดตาม' ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span><?= htmlspecialchars($visit['created_by_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($visit['hospital_name'])): ?>
                                                <small class="d-block text-muted"><?= htmlspecialchars($visit['hospital_name'], ENT_QUOTES, 'UTF-8') ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($visit['status'] === 'COMPLETED'): ?>
                                                <span class="badge bg-success">เสร็จแล้ว</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">ร่าง</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php if ((int)$visit['photo_count'] > 0 && !empty($visit['first_photo_id'])): ?>
                                                    <a href="index.php?c=field&a=photo&id=<?= (int)$visit['first_photo_id'] ?>" target="_blank" class="btn btn-light btn-sm" aria-label="ดูรูปประกอบ">
                                                        <i class="bi bi-image"></i> <?= (int)$visit['photo_count'] ?>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (($visit['status'] ?? '') === 'DRAFT'): ?>
                                                    <a href="index.php?c=field&edit=<?= (int)$visit['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="bi bi-pencil-square"></i> แก้ไข
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (($visit['follow_up_status'] ?? '') === 'PENDING'): ?>
                                                    <form action="index.php?c=field&a=complete_followup" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันว่าดำเนินการติดตามรายการนี้แล้ว?');">
                                                        <?= security_csrf_input() ?>
                                                        <input type="hidden" name="visit_id" value="<?= (int)$visit['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-success btn-sm">
                                                            <i class="bi bi-check2-circle"></i> ปิดติดตาม
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ((int)$visit['photo_count'] === 0 && ($visit['status'] ?? '') !== 'DRAFT' && ($visit['follow_up_status'] ?? '') !== 'PENDING'): ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php if ($editVisit): ?>
<script type="application/json" id="fieldEditPayload"><?= json_encode(
    $editVisit,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
<?php endif; ?>
<script src="public/js/field.js?v=20261004-field-v2" defer></script>
