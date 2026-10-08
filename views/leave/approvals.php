<?php
// ที่อยู่ไฟล์: views/leave/approvals.php

// 🌟 ฟังก์ชันแปลงวันที่ให้ดูง่ายขึ้น
function getShortThaiDateApprovals($date_str) {
    if (empty($date_str)) return '-';
    $thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($date_str);
    return date('j', $ts) . ' ' . $thai_months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

require_once __DIR__ . '/../components/ui.php';
?>
<style>
    /* ปรับแต่งดีไซน์เพิ่มเติม */
    .cancel-request-row { background-color: #fffbeb !important; border-left: 4px solid #f59e0b !important; }
    .cancelled-row { background-color: #f8fafc !important; opacity: 0.8; border-left: 4px solid #94a3b8 !important; }
    .normal-request-row { border-left: 4px solid transparent; }
    
    .btn-soft-warning { background-color: #fef3c7; color: #d97706; border: none; }
    .btn-soft-warning:hover { background-color: #fde68a; color: #b45309; }
    
    .btn-soft-secondary { background-color: #f1f5f9; color: #475569; border: none; }
    .btn-soft-secondary:hover { background-color: #e2e8f0; color: #334155; }
    
    .badge-soft-warning { background-color: #fffbeb; color: #d97706; border: 1px solid #fcd34d; }
</style>
<link rel="stylesheet" href="public/css/leave-workflow.css?v=2">

<div class="rp-page">
    <?php
    rp_page_header(
        'พิจารณาอนุมัติใบลา',
        'ตรวจสอบใบลาและคำขอยกเลิก พร้อมดำเนินการจากรายการเดียว',
        '<span class="rp-badge rp-badge--info"><i class="bi bi-inbox" aria-hidden="true"></i> ' . count($pending_leaves ?? []) . ' รายการ</span>',
        'Approval Queue'
    );
    ?>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="rp-alert rp-alert--success" role="status" aria-live="polite">
            <span class="rp-alert__icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></span>
            <div class="rp-alert__content"><?= rp_e($_SESSION['success_msg']) ?></div>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="rp-alert rp-alert--danger" role="alert">
            <span class="rp-alert__icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></span>
            <div class="rp-alert__content"><?= rp_e($_SESSION['error_msg']) ?></div>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <section class="rp-section">
        <?php rp_section_header('รายการรอพิจารณา', 'จัดลำดับคำขอที่ต้องดำเนินการก่อน'); ?>
        <div class="d-md-none rp-approval-mobile-list mb-3">
            <?php if (empty($pending_leaves)): ?>
                <div class="rp-card">
                    <?php rp_empty_state('bi-check2-all', 'ไม่มีใบลาค้างพิจารณา', 'ขณะนี้ไม่มีรายการที่ต้องดำเนินการ'); ?>
                </div>
            <?php else: ?>
                <?php foreach($pending_leaves as $leave):
                    $is_cancel_req = (($leave['status'] ?? '') === 'CANCEL_REQUESTED');
                ?>
                    <article class="rp-card rp-card__body rp-approval-card <?= $is_cancel_req ? 'rp-approval-card--cancel' : '' ?>">
                        <div class="rp-approval-card__person">
                            <div>
                                <h3 class="rp-approval-card__name"><?= rp_e($leave['user_name']) ?></h3>
                                <div class="rp-approval-card__meta">
                                    <?= rp_e($leave['employee_type']) ?> · <?= rp_e($leave['hospital_name'] ?? 'ส่วนกลาง') ?>
                                </div>
                            </div>
                            <span class="rp-badge <?= $is_cancel_req ? 'rp-badge--warning' : 'rp-badge--info' ?>">
                                <?= $is_cancel_req ? 'ขอยกเลิก' : 'รอพิจารณา' ?>
                            </span>
                        </div>

                        <div class="rp-approval-card__facts">
                            <div class="rp-approval-card__fact">
                                <span class="rp-approval-card__fact-label">ประเภท</span>
                                <span class="rp-approval-card__fact-value"><?= rp_e($leave['leave_type']) ?></span>
                            </div>
                            <div class="rp-approval-card__fact">
                                <span class="rp-approval-card__fact-label">จำนวนวัน</span>
                                <span class="rp-approval-card__fact-value"><?= rp_e((float)$leave['num_days']) ?> วัน</span>
                            </div>
                            <div class="rp-approval-card__fact">
                                <span class="rp-approval-card__fact-label">ช่วงวันที่</span>
                                <span class="rp-approval-card__fact-value">
                                    <?php
                                    $start_dt = getShortThaiDateApprovals($leave['start_date']);
                                    $end_dt = getShortThaiDateApprovals($leave['end_date']);
                                    echo rp_e($start_dt === $end_dt ? $start_dt : "{$start_dt} - {$end_dt}");
                                    ?>
                                </span>
                            </div>
                            <div class="rp-approval-card__fact">
                                <span class="rp-approval-card__fact-label">ยื่นเมื่อ</span>
                                <span class="rp-approval-card__fact-value"><?= rp_e(date('d/m/Y H:i', strtotime($leave['created_at']))) ?></span>
                            </div>
                        </div>

                        <?php if (!empty($leave['reason'])): ?>
                            <div class="rp-approval-card__reason"><?= nl2br(rp_e($leave['reason'])) ?></div>
                        <?php endif; ?>

                        <div class="d-flex gap-2 mt-3">
                            <a href="index.php?c=leave&a=print&id=<?= (int)$leave['id'] ?>" target="_blank" rel="noopener" class="rp-btn rp-btn--secondary rp-btn--sm flex-fill">
                                <i class="bi bi-file-earmark-text"></i> ดูต้นฉบับ
                            </a>
                            <?php if(!empty($leave['med_cert_path'])): ?>
                                <a href="index.php?c=leave&a=download_med_cert&id=<?= (int)$leave['id'] ?>" target="_blank" rel="noopener" class="rp-btn rp-btn--secondary rp-btn--sm flex-fill">
                                    <i class="bi bi-paperclip"></i> ใบรับรอง
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if(!$is_cancel_req): ?><form method="POST" action="index.php?c=leave&a=official_review" class="border rounded-3 p-3 my-3">
<input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>"><input type="hidden" name="request_id" value="<?= (int)$leave['id'] ?>">
<label class="form-label">ตรวจสอบเอกสารและสิทธิการลา</label><textarea name="hr_review_note" class="form-control" maxlength="500" rows="2" placeholder="ข้อสังเกตจากเจ้าหน้าที่"></textarea>
<button type="submit" class="rp-btn rp-btn--secondary mt-2">บันทึกผลตรวจสอบ</button></form><?php endif; ?>
                        <div class="rp-approval-card__actions">
                            <?php if($is_cancel_req): ?>
                                <form action="index.php?c=leave&a=process_approval" method="POST" onsubmit="return confirm('อนุมัติให้ยกเลิกใบลานี้หรือไม่?');">
                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                    <input type="hidden" name="request_id" value="<?= (int)$leave['id'] ?>">
                                    <input type="hidden" name="action" value="APPROVE_CANCEL">
                                    <button class="rp-btn rp-btn--warning w-100" type="submit"><i class="bi bi-check2-all"></i> ให้ยกเลิก</button>
                                </form>
                                <form action="index.php?c=leave&a=process_approval" method="POST" onsubmit="return confirm('ปฏิเสธคำขอยกเลิกนี้หรือไม่?');">
                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                    <input type="hidden" name="request_id" value="<?= (int)$leave['id'] ?>">
                                    <input type="hidden" name="action" value="REJECT_CANCEL">
                                    <button class="rp-btn rp-btn--secondary w-100" type="submit"><i class="bi bi-x-lg"></i> ปฏิเสธ</button>
                                </form>
                            <?php else: ?>
                                <form action="index.php?c=leave&a=process_approval" method="POST" onsubmit="return confirm('ยืนยันการอนุมัติใบลานี้หรือไม่?');"><label class="form-label small">ความเห็นผู้บังคับบัญชา (ถ้ามี)</label><textarea class="form-control mb-2" name="supervisor_opinion" maxlength="500" rows="2"></textarea>
                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                    <input type="hidden" name="request_id" value="<?= (int)$leave['id'] ?>">
                                    <input type="hidden" name="action" value="APPROVED">
                                    <button class="rp-btn rp-btn--success w-100" type="submit"><i class="bi bi-check-lg"></i> อนุมัติ</button>
                                </form>
                                <form action="index.php?c=leave&a=process_approval" method="POST" onsubmit="return confirm('ยืนยันไม่อนุมัติใบลานี้หรือไม่?');"><label class="form-label small">ความเห็นผู้บังคับบัญชา (ถ้ามี)</label><textarea class="form-control mb-2" name="supervisor_opinion" maxlength="500" rows="2"></textarea>
                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                    <input type="hidden" name="request_id" value="<?= (int)$leave['id'] ?>">
                                    <input type="hidden" name="action" value="REJECTED">
                                    <button class="rp-btn rp-btn--danger w-100" type="submit"><i class="bi bi-x-lg"></i> ไม่อนุมัติ</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="rp-card overflow-hidden d-none d-md-block">
        <div class="rp-card__header">
            <h2 class="rp-card__title"><i class="bi bi-inbox-fill text-primary me-2" aria-hidden="true"></i>คิวใบลา</h2>
            <span class="rp-badge rp-badge--info"><?= count($pending_leaves ?? []) ?> รายการ</span>
        </div>
        <div class="rp-card__body p-0">
            <div class="table-responsive custom-scrollbar rp-data-table-wrap">
                <table class="table rp-table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary sticky-top" style="font-size: 13px; z-index: 10;">
                        <tr>
                            <th class="ps-4 py-3">ผู้ยื่นเรื่อง</th>
                            <th class="py-3">สังกัด</th>
                            <th class="py-3">รายการ</th>
                            <th class="py-3">ช่วงวันที่</th>
                            <th class="text-center py-3">จำนวนวัน</th>
                            <th class="py-3">เหตุผลการลา</th>
                            <th class="text-center pe-4 py-3" width="240">ดำเนินการ/สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php if (empty($pending_leaves)): ?>
                            <tr>
                                <td colspan="7">
                                    <?php rp_empty_state('bi-check2-all', 'ไม่มีใบลาค้างพิจารณา', 'ขณะนี้ไม่มีรายการที่ต้องดำเนินการ'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($pending_leaves as $leave): 
                                // 🌟 เช็คว่าเป็นการขอลาปกติ, ขอยกเลิกใบลา หรือสถานะอื่นๆ
                                $is_cancel_req = ($leave['status'] == 'CANCEL_REQUESTED');
                                $is_cancelled = ($leave['status'] == 'CANCELLED');
                                
                                $row_class = 'normal-request-row';
                                if ($is_cancel_req) $row_class = 'cancel-request-row';
                                if ($is_cancelled) $row_class = 'cancelled-row';
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-dark" style="font-size: 14.5px;"><?= htmlspecialchars($leave['user_name']) ?></div>
                                    <div class="small text-muted" style="font-size: 12px;"><?= htmlspecialchars($leave['employee_type']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark border border-secondary border-opacity-25 rounded-pill fw-medium" style="font-size: 11px;">
                                        <?= htmlspecialchars($leave['hospital_name'] ?? 'ส่วนกลาง') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($is_cancelled): ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary rounded-pill mb-1 d-inline-flex align-items-center" style="font-size: 10px;">
                                            <i class="bi bi-slash-circle me-1"></i> ถูกยกเลิกแล้ว
                                        </span><br>
                                    <?php elseif($is_cancel_req): ?>
                                        <span class="badge badge-soft-warning rounded-pill mb-1 d-inline-flex align-items-center" style="font-size: 10px;">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> ขอยกเลิกใบลา
                                        </span><br>
                                    <?php endif; ?>
                                    
                                    <span class="fw-bold text-dark d-block" style="font-size: 14px;"><?= htmlspecialchars($leave['leave_type']) ?></span>
                                    
                                    <?php if(!empty($leave['med_cert_path'])): ?>
                                        <a href="index.php?c=leave&a=download_med_cert&id=<?= (int)$leave['id'] ?>" target="_blank" rel="noopener" class="rp-badge rp-badge--info text-decoration-none mt-1">
                                            <i class="bi bi-paperclip"></i> ดูใบรับรองแพทย์
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small bg-white px-2 py-1 rounded-2 border text-nowrap fw-medium text-secondary shadow-sm d-inline-block" style="font-size: 12px;">
                                        <?php 
                                            $start_dt = getShortThaiDateApprovals($leave['start_date']);
                                            $end_dt = getShortThaiDateApprovals($leave['end_date']);
                                            echo ($start_dt === $end_dt) ? $start_dt : "{$start_dt} - {$end_dt}";
                                        ?>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size: 10px;"><i class="bi bi-clock me-1"></i> ยื่นเมื่อ: <?= date('d/m/Y H:i', strtotime($leave['created_at'])) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-primary fs-5"><?= floatval($leave['num_days']) ?></span>
                                </td>
                                <td>
                                    <div class="text-muted small" style="max-width: 180px; white-space: pre-wrap; line-height: 1.4; font-size: 12px;">
                                        <?= htmlspecialchars($leave['reason']) ?>
                                    </div>
                                </td>
                                <td class="text-center pe-4">
                                    <a href="index.php?c=leave&a=print&id=<?= $leave['id'] ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill shadow-sm mb-2 w-100 fw-bold text-primary" style="font-size: 11px;">
                                        <i class="bi bi-file-earmark-text me-1"></i> ดูใบลาต้นฉบับ
                                    </a>
                                    <a href="index.php?c=leave&a=generate_document&id=<?= (int)$leave['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill shadow-sm mb-2 w-100 fw-bold" style="font-size: 11px;">
                                        <i class="bi bi-file-earmark-word me-1"></i> สร้าง Word จาก Template
                                    </a>
                                    
                                    <!-- 🌟 ดักจับสถานะที่สิ้นสุดแล้ว (เพื่อเปลี่ยนปุ่มเป็นป้ายบอกสถานะ) -->
                                    <?php if ($leave['status'] == 'CANCELLED'): ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 d-block py-2 rounded-pill w-100"><i class="bi bi-slash-circle me-1"></i> ยกเลิกสำเร็จแล้ว</span>
                                    <?php elseif ($leave['status'] == 'APPROVED'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-block py-2 rounded-pill w-100"><i class="bi bi-check-circle me-1"></i> อนุมัติสำเร็จแล้ว</span>
                                    <?php elseif ($leave['status'] == 'REJECTED'): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 d-block py-2 rounded-pill w-100"><i class="bi bi-x-circle me-1"></i> ไม่อนุมัติ</span>
                                    <?php else: ?>
                                        
                                        <!-- กรณีสถานะรอการดำเนินการ (ปุ่มกดยังโชว์อยู่) -->
                                        <div class="d-flex gap-2">
                                            <?php if($is_cancel_req): ?>
                                                <!-- 🌟 กลุ่มปุ่มสำหรับ "พิจารณาคำขอยกเลิกใบลา" (สถานะ: CANCEL_REQUESTED) -->
                                                <form action="index.php?c=leave&a=process_approval" method="POST" class="m-0 flex-fill">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="request_id" value="<?= (int)$leave['id'] ?>">
                                                    <input type="hidden" name="action" value="APPROVE_CANCEL">
                                                    <button type="submit" class="btn btn-sm btn-warning w-100 rounded-3 fw-bold text-dark shadow-sm px-0" style="font-size: 11px;" onclick="return confirm('ยืนยัน [อนุมัติให้ยกเลิกใบลา] นี้ใช่หรือไม่?\n\nระบบจะทำการคืนโควตาวันลาจำนวน <?= floatval($leave['num_days']) ?> วัน ให้กับพนักงานท่านนี้โดยอัตโนมัติ');">
                                                        <i class="bi bi-check2-all"></i> ให้ยกเลิก
                                                    </button>
                                                </form>
                                                <form action="index.php?c=leave&a=process_approval" method="POST" class="m-0 flex-fill">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="request_id" value="<?= (int)$leave['id'] ?>">
                                                    <input type="hidden" name="action" value="REJECT_CANCEL">
                                                    <button type="submit" class="btn btn-sm btn-soft-secondary w-100 rounded-3 fw-bold shadow-sm px-0" style="font-size: 11px;" onclick="return confirm('ยืนยัน [ไม่อนุมัติให้ยกเลิก] ใช่หรือไม่?\n\nใบลาฉบับนี้จะยังคงสถานะอนุมัติตามเดิม (ไม่คืนโควตา)');">
                                                        <i class="bi bi-x-lg"></i> ปฏิเสธ
                                                    </button>
                                                </form>
                                                
                                            <?php else: ?>
                                                <!-- 🌟 กลุ่มปุ่มสำหรับ "พิจารณาอนุมัติใบลาใหม่" (สถานะ: PENDING) -->
                                                <form action="index.php?c=leave&a=process_approval" method="POST" class="m-0 flex-fill">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="request_id" value="<?= (int)$leave['id'] ?>">
                                                    <input type="hidden" name="action" value="APPROVED">
                                                    <button type="submit" class="btn btn-sm btn-success w-100 rounded-3 fw-bold shadow-sm px-0" style="font-size: 11px;" onclick="return confirm('ยืนยันการ [อนุมัติ] ใบลาใช่หรือไม่?\n\nระบบจะทำการหักโควตาวันลาของพนักงานจำนวน <?= floatval($leave['num_days']) ?> วัน');">
                                                        <i class="bi bi-check-lg"></i> อนุมัติ
                                                    </button>
                                                </form>
                                                <form action="index.php?c=leave&a=process_approval" method="POST" class="m-0 flex-fill">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="request_id" value="<?= (int)$leave['id'] ?>">
                                                    <input type="hidden" name="action" value="REJECTED">
                                                    <button type="submit" class="btn btn-sm btn-danger w-100 rounded-3 fw-bold shadow-sm px-0" style="font-size: 11px;" onclick="return confirm('ยืนยัน [ไม่อนุมัติ] ใบลาใช่หรือไม่?');">
                                                        <i class="bi bi-x-lg"></i> ไม่อนุมัติ
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- คำอธิบายเพิ่มเติมด้านล่างตาราง -->
        <div class="rp-alert rp-alert--info m-3">
            <span class="rp-alert__icon"><i class="bi bi-info-circle" aria-hidden="true"></i></span>
            <div class="rp-alert__content">
                รายการขอยกเลิกใบลาจะแสดงโทนสีเหลือง เมื่ออนุมัติการยกเลิก ระบบจะคืนโควตาวันลาให้อัตโนมัติ
            </div>
        </div>
    </div>
    </section>
</div>