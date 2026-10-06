<?php
// ที่อยู่ไฟล์: views/swap/index.php
$swaps = $swaps ?? [];
$staff_list = $staff_list ?? [];
$current_user_id = $_SESSION['user']['id'];
$hospital_id = $_SESSION['user']['hospital_id'];
$is_manager = in_array(strtoupper($_SESSION['user']['role']), ['DIRECTOR', 'SCHEDULER', 'ADMIN', 'SUPERADMIN']);

$swap_counts = [
    'total' => count($swaps),
    'target' => 0,
    'director' => 0,
    'approved' => 0,
    'rejected' => 0,
];
foreach ($swaps as $swap_item) {
    $status = $swap_item['status'] ?? '';
    if ($status === 'PENDING_TARGET') $swap_counts['target']++;
    elseif ($status === 'PENDING_DIRECTOR') $swap_counts['director']++;
    elseif ($status === 'APPROVED') $swap_counts['approved']++;
    else $swap_counts['rejected']++;
}

// 🌟 ฟังก์ชันแปลงวันที่เป็นรูปแบบภาษาไทย (เช่น 15 มี.ค. 2567)
if (!function_exists('thai_date_format')) {
    function thai_date_format($date_string, $show_time = false) {
        if (empty($date_string) || $date_string == '0000-00-00' || $date_string == '0000-00-00 00:00:00') {
            return '-';
        }
        $thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $timestamp = strtotime($date_string);
        $d = date('j', $timestamp);
        $m = $thai_months[date('n', $timestamp)];
        $y = date('Y', $timestamp) + 543;
        
        $result = "$d $m $y";
        if ($show_time) {
            $result .= ' ' . date('H:i', $timestamp) . ' น.';
        }
        return $result;
    }
}

// 🌟 ดึงข้อมูลเวรของทุกคนใน รพ.สต. เพื่อเอาไปทำ Smart Calendar ใน JavaScript
$db = (new Database())->getConnection();
$valid_shifts_for_js = [];
try {
    $stmt = $db->prepare("
        SELECT user_id, shift_date as duty_date, shift_type 
        FROM shifts 
        WHERE hospital_id = :hid 
        AND shift_date >= DATE_SUB(CURRENT_DATE, INTERVAL 5 DAY)
        AND shift_date <= DATE_ADD(CURRENT_DATE, INTERVAL 60 DAY)
    ");
    $stmt->execute([':hid' => $hospital_id]);
    $all_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_shifts as $s) {
        $s_type = trim($s['shift_type'] ?? '');
        if (!empty($s_type) && !in_array($s_type, ['O', 'L', 'ย', 'OFF', ''])) {
            $valid_shifts_for_js[$s['user_id']][$s['duty_date']] = $s_type;
        }
    }
} catch (Exception $e) {}

require_once __DIR__ . '/../components/ui.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    .card-modern { border: none; border-radius: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #fff; }
    .table-modern th { font-weight: 600; color: #475569; font-size: 13px; background-color: #f8fafc; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
    .table-modern td { vertical-align: middle; font-size: 14px; border-bottom: 1px solid #f1f5f9; padding: 1rem 0.75rem; }
    .badge-shift { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: bold; }
    .shift-m { background-color: #e0f2fe; color: #0284c7; } /* เช้า */
    .shift-a { background-color: #fef08a; color: #b45309; } /* บ่าย */
    .shift-n { background-color: #1e293b; color: #f8fafc; } /* ดึก */
    .input-group-modern { border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden; }
    .input-group-modern .form-control { border: none; box-shadow: none; }
</style>
<link rel="stylesheet" href="public/css/swap-workflow.css?v=2">

<div class="rp-page">

    <?php
    ob_start();
    ?>
        <a href="index.php?c=roster" class="rp-btn rp-btn--secondary">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            ดูตารางเวร
        </a>
        <button class="rp-btn rp-btn--primary" data-bs-toggle="modal" data-bs-target="#createSwapModal">
            <i class="bi bi-plus-circle" aria-hidden="true"></i>
            ยื่นขอแลกเวร
        </button>
    <?php
    $swap_header_actions = ob_get_clean();
    rp_page_header(
        'คำขอแลกเวร',
        'สร้าง ติดตาม และพิจารณาคำขอสลับตารางเวรจากจุดเดียว',
        $swap_header_actions,
        'Shift Swap'
    );
    ?>

    <!-- Feedback -->
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

    <section class="rp-section" aria-labelledby="swapOverviewTitle">
        <?php rp_section_header('ภาพรวมคำขอ', 'เห็นสถานะสำคัญก่อนลงรายละเอียดแต่ละรายการ'); ?>
        <div class="rp-swap-overview" id="swapOverviewTitle">
            <div class="rp-swap-kpi">
                <div class="rp-swap-kpi__label">คำขอทั้งหมด</div>
                <div class="rp-swap-kpi__value"><?= number_format($swap_counts['total']) ?></div>
            </div>
            <div class="rp-swap-kpi">
                <div class="rp-swap-kpi__label">รอเพื่อนยืนยัน</div>
                <div class="rp-swap-kpi__value"><?= number_format($swap_counts['target']) ?></div>
            </div>
            <div class="rp-swap-kpi">
                <div class="rp-swap-kpi__label">รอผู้จัดเวรอนุมัติ</div>
                <div class="rp-swap-kpi__value"><?= number_format($swap_counts['director']) ?></div>
            </div>
            <div class="rp-swap-kpi">
                <div class="rp-swap-kpi__label">อนุมัติแล้ว</div>
                <div class="rp-swap-kpi__value"><?= number_format($swap_counts['approved']) ?></div>
            </div>
        </div>
    </section>

    <!-- รายการคำขอแลกเวร -->
    <section class="rp-section">
        <?php rp_section_header('รายการแลกเวร', 'ติดตามคำขอของคุณและรายการที่ต้องดำเนินการ'); ?>

        <div class="d-md-none rp-swap-mobile-list mb-3">
            <?php if (empty($swaps)): ?>
                <div class="rp-card">
                    <?php rp_empty_state('bi-arrow-left-right', 'ยังไม่มีคำขอแลกเวร', 'เมื่อสร้างคำขอใหม่ รายการจะปรากฏที่นี่', 'ยื่นขอแลกเวร', '#createSwapModal'); ?>
                </div>
            <?php else: ?>
                <?php foreach ($swaps as $swap):
                    $req_date_th = thai_date_format($swap['requestor_date']);
                    $tar_date_th = thai_date_format($swap['target_date']);
                    $created_at_th = thai_date_format($swap['created_at'], true);

                    $status_class = 'rp-swap-status--rejected';
                    $status_text = 'ปฏิเสธ/ยกเลิก';
                    $status_icon = 'bi-x-circle';
                    if ($swap['status'] === 'PENDING_TARGET') {
                        $status_class = 'rp-swap-status--target';
                        $status_text = 'รอเพื่อนยืนยัน';
                        $status_icon = 'bi-hourglass-split';
                    } elseif ($swap['status'] === 'PENDING_DIRECTOR') {
                        $status_class = 'rp-swap-status--director';
                        $status_text = 'รอผู้จัดเวรอนุมัติ';
                        $status_icon = 'bi-person-workspace';
                    } elseif ($swap['status'] === 'APPROVED') {
                        $status_class = 'rp-swap-status--approved';
                        $status_text = 'อนุมัติแล้ว';
                        $status_icon = 'bi-check-circle';
                    }

                    $req_shift_class = (strtoupper($swap['requestor_shift']) === 'M' || $swap['requestor_shift'] === 'เช้า') ? 'rp-swap-shift--m' : ((strtoupper($swap['requestor_shift']) === 'A' || strpos($swap['requestor_shift'], 'บ') !== false) ? 'rp-swap-shift--a' : 'rp-swap-shift--n');
                    $tar_shift_class = (strtoupper($swap['target_shift']) === 'M' || $swap['target_shift'] === 'เช้า') ? 'rp-swap-shift--m' : ((strtoupper($swap['target_shift']) === 'A' || strpos($swap['target_shift'], 'บ') !== false) ? 'rp-swap-shift--a' : 'rp-swap-shift--n');
                ?>
                    <article class="rp-card rp-card__body rp-swap-card">
                        <div class="rp-swap-card__head">
                            <div>
                                <h3 class="rp-swap-card__title"><?= rp_e($swap['requestor_name']) ?> ↔ <?= rp_e($swap['target_name']) ?></h3>
                                <div class="rp-swap-card__meta">ส่งคำขอ <?= rp_e($created_at_th) ?></div>
                            </div>
                            <span class="rp-swap-status <?= rp_e($status_class) ?>">
                                <i class="bi <?= rp_e($status_icon) ?>" aria-hidden="true"></i><?= rp_e($status_text) ?>
                            </span>
                        </div>

                        <div class="rp-swap-card__route">
                            <div class="rp-swap-card__side">
                                <div class="rp-swap-card__side-label">เวรของผู้ขอ</div>
                                <div class="rp-swap-card__side-name"><?= rp_e($swap['requestor_name']) ?></div>
                                <div class="rp-swap-card__side-date"><?= rp_e($req_date_th) ?> <span class="rp-swap-shift <?= rp_e($req_shift_class) ?>"><?= rp_e($swap['requestor_shift']) ?></span></div>
                            </div>
                            <i class="bi bi-arrow-left-right rp-swap-card__arrow" aria-hidden="true"></i>
                            <div class="rp-swap-card__side">
                                <div class="rp-swap-card__side-label">เวรของคู่แลก</div>
                                <div class="rp-swap-card__side-name"><?= rp_e($swap['target_name']) ?></div>
                                <div class="rp-swap-card__side-date"><?= rp_e($tar_date_th) ?> <span class="rp-swap-shift <?= rp_e($tar_shift_class) ?>"><?= rp_e($swap['target_shift']) ?></span></div>
                            </div>
                        </div>

                        <?php if (!empty($swap['reason'])): ?>
                            <div class="rp-swap-card__reason"><strong>เหตุผล:</strong> <?= nl2br(rp_e($swap['reason'])) ?></div>
                        <?php endif; ?>

                        <div class="rp-swap-card__actions">
                            <?php if ($swap['status'] === 'PENDING_TARGET' && (int)$swap['target_user_id'] === (int)$current_user_id): ?>
                                <form method="POST" action="index.php?c=swap&a=action" onsubmit="return confirm('ยืนยันรับข้อเสนอแลกเวรนี้?');">
                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$swap['id'] ?>">
                                    <input type="hidden" name="act" value="accept">
                                    <button class="rp-btn rp-btn--success w-100" type="submit"><i class="bi bi-check-lg"></i> ยอมรับ</button>
                                </form>
                                <form method="POST" action="index.php?c=swap&a=action" onsubmit="return confirm('ปฏิเสธข้อเสนอนี้?');">
                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$swap['id'] ?>">
                                    <input type="hidden" name="act" value="reject">
                                    <button class="rp-btn rp-btn--danger w-100" type="submit"><i class="bi bi-x-lg"></i> ปฏิเสธ</button>
                                </form>
                            <?php elseif ($swap['status'] === 'PENDING_DIRECTOR' && $is_manager): ?>
                                <form method="POST" action="index.php?c=swap&a=action" onsubmit="return confirm('ยืนยันอนุมัติและสลับตารางเวรทันที?');">
                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$swap['id'] ?>">
                                    <input type="hidden" name="act" value="approve">
                                    <button class="rp-btn rp-btn--success w-100" type="submit"><i class="bi bi-check-circle"></i> อนุมัติ</button>
                                </form>
                                <form method="POST" action="index.php?c=swap&a=action" onsubmit="return confirm('ไม่อนุมัติคำขอนี้?');">
                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$swap['id'] ?>">
                                    <input type="hidden" name="act" value="decline">
                                    <button class="rp-btn rp-btn--danger w-100" type="submit"><i class="bi bi-x-circle"></i> ไม่อนุมัติ</button>
                                </form>
                            <?php elseif (in_array($swap['status'], ['PENDING_TARGET', 'PENDING_DIRECTOR'], true) && (int)$swap['requestor_id'] === (int)$current_user_id): ?>
                                <form method="POST" action="index.php?c=swap&a=action" onsubmit="return confirm('ยืนยันยกเลิกคำขอแลกเวรนี้?');">
                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$swap['id'] ?>">
                                    <input type="hidden" name="act" value="cancel">
                                    <button class="rp-btn rp-btn--secondary w-100" type="submit"><i class="bi bi-x-circle"></i> ยกเลิกคำขอ</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="rp-card overflow-hidden mb-4 d-none d-md-block">
        <div class="rp-card__body p-0">
            <div class="table-responsive rp-data-table-wrap">
                <table class="table rp-table mb-0 align-middle text-center">
                    <thead>
                        <tr>
                            <th class="text-start ps-4">ผู้ขอแลกเวร</th>
                            <th>เวรที่ต้องการให้ (ของฉัน)</th>
                            <th><i class="bi bi-arrow-left-right text-muted fs-5"></i></th>
                            <th>เวรที่ต้องการรับ (เพื่อน)</th>
                            <th class="text-start">ผู้ถูกขอแลก (เพื่อน)</th>
                            <th>สถานะ</th>
                            <th class="pe-4">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($swaps)): ?>
                            <tr>
                                <td colspan="7">
                                    <?php rp_empty_state('bi-inbox', 'ยังไม่มีคำขอแลกเวร', 'เมื่อมีคำขอใหม่ รายการจะปรากฏที่นี่'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($swaps as $swap): 
                                // 🌟 จัดการวันที่เป็นรูปแบบไทย
                                $req_date_th = thai_date_format($swap['requestor_date']);
                                $tar_date_th = thai_date_format($swap['target_date']);
                                $created_at_th = thai_date_format($swap['created_at'], true);
                                
                                // จัดการป้ายสถานะ
                                $status_badge = '';
                                if ($swap['status'] == 'PENDING_TARGET') $status_badge = '<span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-hourglass-split"></i> รอเพื่อนยืนยัน</span>';
                                else if ($swap['status'] == 'PENDING_DIRECTOR') $status_badge = '<span class="badge bg-info text-dark px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-person-workspace"></i> รอ ผอ. อนุมัติ</span>';
                                else if ($swap['status'] == 'APPROVED') $status_badge = '<span class="badge bg-success px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-check-circle"></i> อนุมัติแล้ว</span>';
                                else $status_badge = '<span class="badge bg-danger px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-x-circle"></i> ปฏิเสธ/ยกเลิก</span>';
                                
                                // สีกะ
                                $req_class = strtolower($swap['requestor_shift']) == 'm' || $swap['requestor_shift'] == 'เช้า' ? 'shift-m' : (strtolower($swap['requestor_shift']) == 'a' || strpos($swap['requestor_shift'], 'บ') !== false ? 'shift-a' : 'shift-n');
                                $tar_class = strtolower($swap['target_shift']) == 'm' || $swap['target_shift'] == 'เช้า' ? 'shift-m' : (strtolower($swap['target_shift']) == 'a' || strpos($swap['target_shift'], 'บ') !== false ? 'shift-a' : 'shift-n');
                            ?>
                                <tr>
                                    <td class="text-start ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($swap['requestor_name']) ?></div>
                                        <div class="text-muted" style="font-size: 11px;">ส่งคำขอ: <?= $created_at_th ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary"><?= $req_date_th ?></div>
                                        <span class="badge-shift <?= $req_class ?> mt-1 shadow-sm"><?= htmlspecialchars($swap['requestor_shift']) ?></span>
                                    </td>
                                    <td><i class="bi bi-arrow-right-circle-fill text-success fs-5"></i></td>
                                    <td>
                                        <div class="fw-bold text-danger"><?= $tar_date_th ?></div>
                                        <span class="badge-shift <?= $tar_class ?> mt-1 shadow-sm"><?= htmlspecialchars($swap['target_shift']) ?></span>
                                    </td>
                                    <td class="text-start">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($swap['target_name']) ?></div>
                                    </td>
                                    <td><?= $status_badge ?></td>
                                    <td class="pe-4 text-nowrap">
                                        <?php 
                                            // กรณีคนถูกขอแลก (เพื่อน) ต้องกดยอมรับ/ปฏิเสธ
                                            if ($swap['status'] == 'PENDING_TARGET' && $swap['target_user_id'] == $current_user_id): 
                                        ?>
                                            <div class="d-flex gap-1 justify-content-center">
                                                <form method="POST" action="index.php?c=swap&a=action" onsubmit="return confirm('ยืนยันรับข้อเสนอแลกเวรนี้?');">
                                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$swap['id'] ?>">
                                                    <input type="hidden" name="act" value="accept">
                                                    <button class="rp-btn rp-btn--success rp-btn--sm" type="submit">ยอมรับ</button>
                                                </form>
                                                <form method="POST" action="index.php?c=swap&a=action" onsubmit="return confirm('ปฏิเสธข้อเสนอนี้?');">
                                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$swap['id'] ?>">
                                                    <input type="hidden" name="act" value="reject">
                                                    <button class="rp-btn rp-btn--danger rp-btn--sm" type="submit">ปฏิเสธ</button>
                                                </form>
                                            </div>
                                        
                                        <?php 
                                            // กรณีผู้จัดเวร/ผอ. ต้องกดอนุมัติ
                                            elseif ($swap['status'] == 'PENDING_DIRECTOR' && $is_manager): 
                                        ?>
                                            <div class="d-flex gap-1 justify-content-center">
                                                <form method="POST" action="index.php?c=swap&a=action" onsubmit="return confirm('ยืนยันอนุมัติและสลับตารางเวรทันที?');">
                                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$swap['id'] ?>">
                                                    <input type="hidden" name="act" value="approve">
                                                    <button class="rp-btn rp-btn--success rp-btn--sm" type="submit">อนุมัติ</button>
                                                </form>
                                                <form method="POST" action="index.php?c=swap&a=action" onsubmit="return confirm('ไม่อนุมัติคำขอนี้?');">
                                                    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$swap['id'] ?>">
                                                    <input type="hidden" name="act" value="decline">
                                                    <button class="rp-btn rp-btn--danger rp-btn--sm" type="submit">ไม่อนุมัติ</button>
                                                </form>
                                            </div>
                                        
                                        <?php 
                                            // กรณีผู้ขอแลกเวรเอง ต้องการ "ลบ/ยกเลิกคำขอ" ของตัวเอง
                                            elseif (in_array($swap['status'], ['PENDING_TARGET', 'PENDING_DIRECTOR']) && $swap['requestor_id'] == $current_user_id): 
                                        ?>
                                            <form method="POST" action="index.php?c=swap&a=action" onsubmit="return confirm('คุณต้องการยกเลิกคำขอแลกเวรนี้ใช่หรือไม่?');">
                                                <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                                                <input type="hidden" name="id" value="<?= (int)$swap['id'] ?>">
                                                <input type="hidden" name="act" value="cancel">
                                                <button class="rp-btn rp-btn--secondary rp-btn--sm" type="submit"><i class="bi bi-x-circle"></i> ยกเลิกคำขอ</button>
                                            </form>

                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

    </section>

<!-- 🌟 Modal สร้างคำขอแลกเวร -->
<div class="modal fade rp-swap-modal" id="createSwapModal" tabindex="-1" aria-labelledby="createSwapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="index.php?c=swap&a=create" method="POST" id="swapRequestForm">
                <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">
                <div class="modal-header border-bottom-0 bg-light rounded-top-4 pb-3">
                    <h5 class="modal-title fw-bold text-dark" id="createSwapModalLabel"><i class="bi bi-arrow-left-right text-primary me-2"></i> สร้างคำขอแลกเวรใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="rp-swap-flow" aria-label="ขั้นตอนสร้างคำขอแลกเวร">
                        <div class="rp-swap-flow__step rp-swap-flow__step--active" data-swap-step="1"><span class="rp-swap-flow__number">1</span><span>เวรของฉัน</span></div>
                        <div class="rp-swap-flow__step" data-swap-step="2"><span class="rp-swap-flow__number">2</span><span>เลือกคู่แลก</span></div>
                        <div class="rp-swap-flow__step" data-swap-step="3"><span class="rp-swap-flow__number">3</span><span>เวรเพื่อน</span></div>
                        <div class="rp-swap-flow__step" data-swap-step="4"><span class="rp-swap-flow__number">4</span><span>ตรวจสอบ</span></div>
                    </div>
                    <div class="row g-4">
                        
                        <!-- ฝั่งผู้ขอแลก (ตัวเอง) -->
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-person-fill"></i> เวรของคุณ (ต้องการให้)</h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">วันที่คุณมีเวรอยู่ <span class="text-danger">*</span></label>
                                <div class="input-group-modern d-flex align-items-center bg-white shadow-sm">
                                    <span class="ps-3 text-primary"><i class="bi bi-calendar-event"></i></span>
                                    <input type="text" name="requestor_date" id="my_date_swap" class="rp-control" placeholder="เลือกวันที่..." required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">ผลัด (กะ) ที่จะให้เพื่อน <span class="text-danger">*</span></label>
                                <select name="requestor_shift" id="my_shift_swap" class="rp-control" required>
                                    <option value="">-- เลือกวันที่ก่อน --</option>
                                    <option value="M">เช้า (M)</option>
                                    <option value="A">บ่าย (บ) / A</option>
                                    <option value="N">ดึก (ร) / N</option>
                                </select>
                            </div>
                        </div>

                        <!-- ฝั่งผู้ถูกแลก (เพื่อน) -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-danger mb-3"><i class="bi bi-people-fill"></i> เวรเพื่อน (ต้องการรับแทน)</h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">เลือกเพื่อนร่วมงาน <span class="text-danger">*</span></label>
                                <select name="target_user_id" id="target_user_id_select" class="rp-control" required style="width: 100%;">
                                    <option value="">-- เลือกเจ้าหน้าที่ --</option>
                                    <?php foreach($staff_list as $staff): ?>
                                        <?php if($staff['id'] != $current_user_id): ?>
                                            <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['name']) ?> (<?= htmlspecialchars($staff['type']) ?>)</option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">วันที่เพื่อนมีเวร <span class="text-danger">*</span></label>
                                <div class="input-group-modern d-flex align-items-center bg-white shadow-sm">
                                    <span class="ps-3 text-danger"><i class="bi bi-calendar-event"></i></span>
                                    <input type="text" name="target_date" id="target_date_swap" class="rp-control" placeholder="เลือกเจ้าหน้าที่ก่อน..." required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">ผลัด (กะ) ที่จะรับแทน <span class="text-danger">*</span></label>
                                <select name="target_shift" id="target_shift_swap" class="rp-control" required readonly style="pointer-events: none;">
                                    <option value="">-- เลือกวันที่ก่อน --</option>
                                    <option value="M">เช้า (M)</option>
                                    <option value="A">บ่าย (บ) / A</option>
                                    <option value="N">ดึก (ร) / N</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="rp-swap-preview" id="swapPreview" hidden aria-live="polite">
                                <div class="rp-swap-preview__side">
                                    <div class="rp-swap-preview__label">เวรของคุณที่จะให้</div>
                                    <div class="rp-swap-preview__name"><?= rp_e($_SESSION['user']['name'] ?? 'คุณ') ?></div>
                                    <div class="rp-swap-preview__shift" id="swapPreviewMine">-</div>
                                </div>
                                <div class="rp-swap-preview__arrow"><i class="bi bi-arrow-left-right"></i></div>
                                <div class="rp-swap-preview__side">
                                    <div class="rp-swap-preview__label">เวรที่จะรับจากเพื่อน</div>
                                    <div class="rp-swap-preview__name" id="swapPreviewTargetName">-</div>
                                    <div class="rp-swap-preview__shift" id="swapPreviewTarget">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-2">
                            <label class="form-label small fw-bold text-muted">เหตุผลที่ขอแลกเวร (ระบุหรือไม่ก็ได้)</label>
                            <textarea name="reason" class="rp-control" rows="2" maxlength="1000" placeholder="เช่น ติดธุระส่วนตัว, ไปราชการ..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="rp-btn rp-btn--secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="rp-btn rp-btn--primary" id="swapSubmitBtn"><i class="bi bi-send me-1"></i><span id="swapSubmitText">ส่งคำขอแลกเวร</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ข้อมูลการขึ้นเวรของทุกคน (แปลงจาก PHP เพื่อใช้ใน JS)
const staffShiftData = <?= json_encode($valid_shifts_for_js) ?>;
const myUserId = <?= json_encode($current_user_id) ?>;

let myDatePickerInstance = null;
let targetDatePickerInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    const swapForm = document.getElementById('swapRequestForm');
    const preview = document.getElementById('swapPreview');

    function selectedText(id) {
        const el = document.getElementById(id);
        if (!el || !el.options || el.selectedIndex < 0) return '';
        return el.options[el.selectedIndex].textContent.trim();
    }

    function updateSwapPreview() {
        const myDate = document.getElementById('my_date_swap')?.value || '';
        const myShift = document.getElementById('my_shift_swap')?.value || '';
        const targetId = document.getElementById('target_user_id_select')?.value || '';
        const targetDate = document.getElementById('target_date_swap')?.value || '';
        const targetShift = document.getElementById('target_shift_swap')?.value || '';
        const steps = document.querySelectorAll('[data-swap-step]');

        if (preview) {
            preview.hidden = !(myDate || targetId || targetDate);
            const mine = document.getElementById('swapPreviewMine');
            const target = document.getElementById('swapPreviewTarget');
            const targetName = document.getElementById('swapPreviewTargetName');
            if (mine) mine.textContent = (myDate && myShift) ? myDate + ' · ' + myShift : '-';
            if (target) target.textContent = (targetDate && targetShift) ? targetDate + ' · ' + targetShift : '-';
            if (targetName) targetName.textContent = targetId ? selectedText('target_user_id_select') : '-';
        }

        const complete = [!!(myDate && myShift), !!targetId, !!(targetDate && targetShift), !!(myDate && myShift && targetId && targetDate && targetShift)];
        steps.forEach((step, index) => step.classList.toggle('rp-swap-flow__step--active', complete[index]));
    }

    
    // ตั้งค่า Select2 ให้กับ Dropdown รายชื่อ
    if ($.fn.select2) {
        $('#target_user_id_select').select2({ 
            theme: 'bootstrap-5', 
            dropdownParent: $('#createSwapModal'),
            placeholder: "-- เลือกเจ้าหน้าที่ --"
        });
    }

    // 🌟 1. ตั้งค่าปฏิทินของ "ตัวเราเอง" (ผู้ขอแลก)
    if (typeof flatpickr !== 'undefined') {
        myDatePickerInstance = flatpickr("#my_date_swap", {
            altInput: true, 
            altFormat: "j F Y", 
            dateFormat: "Y-m-d", 
            locale: "th",
            onReady: (d, str, ins) => { if(ins.currentYearElement) ins.currentYearElement.value = ins.currentYear + 543; },
            disable: [
                function(date) {
                    // อนุญาตให้เลือกได้เฉพาะวันที่มีเวร (ถ้ามีข้อมูล)
                    if (staffShiftData[myUserId]) {
                        const dateStr = [
                            date.getFullYear(),
                            String(date.getMonth() + 1).padStart(2, '0'),
                            String(date.getDate()).padStart(2, '0')
                        ].join('-');
                        return !(dateStr in staffShiftData[myUserId]);
                    }
                    return false; 
                }
            ],
            onChange: function(selectedDates, dateStr, instance) {
                // Auto-fill กะของตัวเอง
                if (staffShiftData[myUserId] && staffShiftData[myUserId][dateStr]) {
                    const shiftType = staffShiftData[myUserId][dateStr];
                    const shiftSelect = document.getElementById('my_shift_swap');
                    autoSelectShift(shiftType, shiftSelect);
                }
                updateSwapPreview();
            }
        });

        // 🌟 2. ตั้งค่าปฏิทินของ "เพื่อน" (ผู้ถูกขอแลก)
        targetDatePickerInstance = flatpickr(document.getElementById("target_date_swap"), {
            altInput: true, 
            altFormat: "j F Y", 
            dateFormat: "Y-m-d", 
            locale: "th",
            disable: [ () => true ], // ล็อกไว้ก่อนจนกว่าจะเลือกเพื่อน
            onReady: (d, str, ins) => { if(ins.currentYearElement) ins.currentYearElement.value = ins.currentYear + 543; },
            onChange: function(selectedDates, dateStr, instance) {
                // Auto-fill กะของเพื่อน
                const userId = document.getElementById('target_user_id_select').value;
                if (userId && staffShiftData[userId] && staffShiftData[userId][dateStr]) {
                    const shiftType = staffShiftData[userId][dateStr];
                    const shiftSelect = document.getElementById('target_shift_swap');
                    autoSelectShift(shiftType, shiftSelect);
                }
                updateSwapPreview();
            }
        });
    }

    // 🌟 ฟังก์ชันแปลงตัวอักษรย่อเป็น Value ใน Dropdown
    function autoSelectShift(shiftType, selectElement) {
        let val = '';
        if (shiftType === 'M' || shiftType === 'เช้า') val = 'M';
        else if (shiftType === 'A' || shiftType === 'บ' || shiftType === 'บ่าย' || shiftType.includes('บ')) val = 'A';
        else if (shiftType === 'N' || shiftType === 'ร' || shiftType === 'ดึก' || shiftType.includes('ร')) val = 'N';
        
        if(val) {
            selectElement.value = val;
            selectElement.style.pointerEvents = 'none'; // ล็อกไม่ให้แก้
            selectElement.classList.remove('bg-light');
            selectElement.classList.add('bg-white');
        } else {
            selectElement.value = shiftType; // เผื่อเป็นค่าอื่น
        }
        updateSwapPreview();
    }

    // 🌟 เมื่อเปลี่ยนชื่อเพื่อน ให้ปลดล็อกวันในปฏิทินเฉพาะวันที่เพื่อนมีเวร
    $('#target_user_id_select').on('change', function() {
        const userId = this.value;
        const shiftSelect = document.getElementById('target_shift_swap');
        
        if (targetDatePickerInstance) {
            targetDatePickerInstance.clear();
        }
        
        shiftSelect.value = '';
        shiftSelect.style.pointerEvents = 'none';
        shiftSelect.classList.add('bg-light');

        if (!userId) {
            if (targetDatePickerInstance) targetDatePickerInstance.set('disable', [() => true]);
            updateSwapPreview();
            return;
        }
        
        // ตรวจสอบว่าเพื่อนคนนี้มีเวรไหม
        if (!staffShiftData[userId] || Object.keys(staffShiftData[userId]).length === 0) {
            if (targetDatePickerInstance) targetDatePickerInstance.set('disable', [() => true]);
            alert('เจ้าหน้าที่ท่านนี้ไม่มีเวรในระบบที่สามารถแลกได้ (หรือยังไม่ได้จัดเวร)');
        } else {
            // อนุญาตให้กดได้เฉพาะวันที่มีเวรเท่านั้น
            const availableDates = Object.keys(staffShiftData[userId]);
            if (targetDatePickerInstance) {
                targetDatePickerInstance.set('disable', []); // ยกเลิก Disable ก่อน
                targetDatePickerInstance.set('enable', availableDates); // อนุญาตเฉพาะวันที่ระบุ
            }
        }
        updateSwapPreview();
    });

    ['my_shift_swap', 'target_shift_swap'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', updateSwapPreview);
    });

    if (swapForm) {
        swapForm.addEventListener('submit', function(e) {
            const btn = document.getElementById('swapSubmitBtn');
            const text = document.getElementById('swapSubmitText');
            if (btn && btn.disabled) {
                e.preventDefault();
                return;
            }
            if (btn) btn.disabled = true;
            if (text) text.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>กำลังส่ง...';
        });
    }

    // รีเซ็ตฟอร์มเมื่อปิด Modal
    var myModalEl = document.getElementById('createSwapModal');
    if (myModalEl) {
        myModalEl.addEventListener('hidden.bs.modal', function (event) {
            $(this).find('form').trigger('reset');
            $('#target_user_id_select').val(null).trigger('change');
            if(myDatePickerInstance) myDatePickerInstance.clear();
            if(targetDatePickerInstance) targetDatePickerInstance.clear();
            updateSwapPreview();
        });
    }
});
</script>