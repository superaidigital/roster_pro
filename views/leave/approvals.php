<?php
// ที่อยู่ไฟล์: views/leave/approvals.php

// 🌟 ฟังก์ชันแปลงวันที่ให้ดูง่ายขึ้น
function getShortThaiDateApprovals($date_str) {
    if (empty($date_str)) return '-';
    $thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($date_str);
    return date('j', $ts) . ' ' . $thai_months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}
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

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex align-items-center mb-4">
        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 48px; height: 48px;">
            <i class="bi bi-ui-checks fs-4"></i>
        </div>
        <div>
            <h2 class="h4 text-dark fw-bold mb-0">พิจารณาอนุมัติใบลา</h2>
            <p class="text-muted small mb-0">ตรวจสอบและอนุมัติแบบฟอร์มการขอลา หรือการขอยกเลิกใบลาของบุคลากร</p>
        </div>
    </div>

    <!-- Alert ข้อความแจ้งเตือน -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-4 d-flex align-items-center mb-4 p-3 shadow-sm border-start border-success border-4">
            <i class="bi bi-check-circle-fill fs-5 me-3"></i> 
            <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['success_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-4 d-flex align-items-center mb-4 p-3 shadow-sm border-start border-danger border-4">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-3"></i> 
            <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['error_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-inbox-fill text-primary me-2"></i> รายการใบลาที่รอการพิจารณาทั้งหมด</h6>
            <span class="badge bg-primary rounded-pill"><?= count($pending_leaves ?? []) ?> รายการ</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive custom-scrollbar" style="max-height: 65vh;">
                <table class="table table-hover align-middle mb-0">
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
                                <td colspan="7" class="text-center py-5">
                                    <div class="d-inline-flex justify-content-center align-items-center rounded-circle bg-light mb-3" style="width: 80px; height: 80px;">
                                        <i class="bi bi-check2-all text-success opacity-50" style="font-size: 2.5rem;"></i>
                                    </div>
                                    <h6 class="fw-bold text-success mb-1">ไม่มีใบลาค้างพิจารณา</h6>
                                    <p class="text-muted small mb-0">เคลียร์งานเสร็จสิ้น ระบบเรียบร้อยดีในขณะนี้</p>
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
                                        <a href="<?= htmlspecialchars($leave['med_cert_path']) ?>" target="_blank" class="badge bg-info bg-opacity-10 text-info text-decoration-none mt-1 border border-info border-opacity-25" style="font-size: 10px;">
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
                                                    <input type="hidden" name="request_id" value="<?= $leave['id'] ?>">
                                                    <input type="hidden" name="action" value="APPROVE_CANCEL">
                                                    <button type="submit" class="btn btn-sm btn-warning w-100 rounded-3 fw-bold text-dark shadow-sm px-0" style="font-size: 11px;" onclick="return confirm('ยืนยัน [อนุมัติให้ยกเลิกใบลา] นี้ใช่หรือไม่?\n\nระบบจะทำการคืนโควตาวันลาจำนวน <?= floatval($leave['num_days']) ?> วัน ให้กับพนักงานท่านนี้โดยอัตโนมัติ');">
                                                        <i class="bi bi-check2-all"></i> ให้ยกเลิก
                                                    </button>
                                                </form>
                                                <form action="index.php?c=leave&a=process_approval" method="POST" class="m-0 flex-fill">
                                                    <input type="hidden" name="request_id" value="<?= $leave['id'] ?>">
                                                    <input type="hidden" name="action" value="REJECT_CANCEL">
                                                    <button type="submit" class="btn btn-sm btn-soft-secondary w-100 rounded-3 fw-bold shadow-sm px-0" style="font-size: 11px;" onclick="return confirm('ยืนยัน [ไม่อนุมัติให้ยกเลิก] ใช่หรือไม่?\n\nใบลาฉบับนี้จะยังคงสถานะอนุมัติตามเดิม (ไม่คืนโควตา)');">
                                                        <i class="bi bi-x-lg"></i> ปฏิเสธ
                                                    </button>
                                                </form>
                                                
                                            <?php else: ?>
                                                <!-- 🌟 กลุ่มปุ่มสำหรับ "พิจารณาอนุมัติใบลาใหม่" (สถานะ: PENDING) -->
                                                <form action="index.php?c=leave&a=process_approval" method="POST" class="m-0 flex-fill">
                                                    <input type="hidden" name="request_id" value="<?= $leave['id'] ?>">
                                                    <input type="hidden" name="action" value="APPROVED">
                                                    <button type="submit" class="btn btn-sm btn-success w-100 rounded-3 fw-bold shadow-sm px-0" style="font-size: 11px;" onclick="return confirm('ยืนยันการ [อนุมัติ] ใบลาใช่หรือไม่?\n\nระบบจะทำการหักโควตาวันลาของพนักงานจำนวน <?= floatval($leave['num_days']) ?> วัน');">
                                                        <i class="bi bi-check-lg"></i> อนุมัติ
                                                    </button>
                                                </form>
                                                <form action="index.php?c=leave&a=process_approval" method="POST" class="m-0 flex-fill">
                                                    <input type="hidden" name="request_id" value="<?= $leave['id'] ?>">
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
        <div class="card-footer bg-light border-top py-3 text-muted" style="font-size: 12px;">
            <i class="bi bi-info-circle text-primary me-1"></i> <strong>คำแนะนำ:</strong> รายการที่มีแถบสีเหลือง หมายถึงบุคลากรขอยกเลิกใบลาที่เคยได้รับการอนุมัติไปแล้ว หากคุณกด "ให้ยกเลิก" ระบบจะทำการเปลี่ยนสถานะเป็น <span class="badge bg-secondary">ยกเลิกสำเร็จแล้ว</span> และคืนโควตาวันลาให้กับบุคลากรท่านนั้นโดยอัตโนมัติ
        </div>
    </div>
</div>