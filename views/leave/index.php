<?php
// ที่อยู่ไฟล์: views/leave/index.php

// รับค่าประเภทการลาจาก URL ที่ส่งมาจาก Sidebar (ถ้ามี)
$selected_leave_type_req = isset($_GET['type']) ? trim($_GET['type']) : '';
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m'); // รับค่าเดือนเพื่อฟิลเตอร์ประวัติการลา

// 🌟 ฟังก์ชันแปลงวันที่เป็นรูปแบบไทยย่อ (สำหรับแสดงในตารางประวัติการลา)
function getShortThaiDateLeave($date_str) {
    if (empty($date_str)) return '-';
    $thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($date_str);
    $d = date('j', $ts);
    $m = $thai_months[(int)date('n', $ts)];
    $y = date('Y', $ts) + 543;
    return "{$d} {$m} {$y}";
}

// 🌟 กำหนดรูปแบบหน้าจอให้สอดคล้องกับเมนูที่เลือก
$page_title = 'ระบบจัดการวันลา';
$page_icon = 'bi-envelope-paper-heart';
$page_theme = 'primary';

if ($selected_leave_type_req == 'ลาพักผ่อน') {
    $page_title = 'ยื่นลาพักผ่อน';
    $page_icon = 'bi-brightness-high';
    $page_theme = 'success';
} elseif ($selected_leave_type_req == 'ลากิจส่วนตัว') {
    $page_title = 'ยื่นลากิจส่วนตัว';
    $page_icon = 'bi-briefcase';
    $page_theme = 'warning';
} elseif ($selected_leave_type_req == 'ลาป่วย') {
    $page_title = 'ยื่นลาป่วย';
    $page_icon = 'bi-bandaid';
    $page_theme = 'danger';
} elseif ($selected_leave_type_req != '') {
    $page_title = 'ยื่น' . htmlspecialchars($selected_leave_type_req);
    $page_icon = 'bi-file-earmark-text';
    $page_theme = 'primary';
} else {
    // กรณีไม่ได้เลือกประเภท (ยื่นลาอื่นๆ / ประวัติ)
    $page_title = 'ยื่นลาอื่นๆ / ประวัติการลา';
    $page_icon = 'bi-clock-history';
    $page_theme = 'secondary';
}

require_once __DIR__ . '/../components/ui.php';
?>
<!-- นำเข้า CSS ของ Flatpickr สำหรับปฏิทิน -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    /* ========================================== */
    /* 🌟 Modern UI Styles สำหรับระบบจัดการวันลา */
    /* ========================================== */
    .card-modern {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 0.25rem 1.25rem rgba(0, 0, 0, 0.04);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        background: #ffffff;
    }
    .card-modern:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.08);
    }
    
    .card-stat {
        border: none;
        border-radius: 1rem;
        background: #ffffff;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        border-left: 5px solid;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card-stat:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    .card-stat.border-primary { border-left-color: #3b82f6; }
    .card-stat.border-danger { border-left-color: #ef4444; }
    .card-stat.border-warning { border-left-color: #f59e0b; }
    .card-stat.border-success { border-left-color: #10b981; }

    .icon-box { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
    .icon-box-sm { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .progress-thin { height: 8px; border-radius: 4px; background-color: #f1f5f9; overflow: hidden; }

    .input-group-modern { border: 1px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.2s; background-color: #f8fafc; overflow: hidden; }
    .input-group-modern:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15); background-color: #ffffff; }
    .input-group-modern .input-group-text, .input-group-modern .form-control, .input-group-modern .form-select { border: none; background: transparent; }
    .input-group-modern .form-control:focus, .input-group-modern .form-select:focus { box-shadow: none; }

    .form-select-modern, .form-control-modern { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.6rem 1rem; transition: all 0.2s; }
    .form-select-modern:focus, .form-control-modern:focus { background-color: #ffffff; border-color: #3b82f6; box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15); }

    .alert-modern { border: none; border-left: 4px solid; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
    .alert-modern.alert-success { border-left-color: #10b981; background-color: #ecfdf5; color: #065f46; }
    .alert-modern.alert-danger { border-left-color: #ef4444; background-color: #fef2f2; color: #991b1b; }

    .flatpickr-input[readonly] { cursor: pointer; }
    
    .btn-soft-primary { background-color: #eff6ff; color: #2563eb; border: none; }
    .btn-soft-primary:hover { background-color: #dbeafe; color: #1d4ed8; }
    .btn-soft-danger { background-color: #fef2f2; color: #dc2626; border: none; }
    .btn-soft-danger:hover { background-color: #fee2e2; color: #b91c1c; }
    
    .btn-gradient-primary { background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%); color: white; border: none; transition: opacity 0.2s; }
    .btn-gradient-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; transition: opacity 0.2s; }
    .btn-gradient-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none; transition: opacity 0.2s; }
    .btn-gradient-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; transition: opacity 0.2s; }
    .btn-gradient-secondary { background: linear-gradient(135deg, #64748b 0%, #475569 100%); color: white; border: none; transition: opacity 0.2s; }
    
    .btn-gradient-primary:hover, .btn-gradient-success:hover, .btn-gradient-warning:hover, .btn-gradient-danger:hover, .btn-gradient-secondary:hover { 
        opacity: 0.9; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    /* =========================================================
       LEAVE PAGE V2 - CENTERED MODERN LAYOUT
       ========================================================= */
    .leave-page-container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
    }

    .leave-main-grid {
        max-width: 1120px;
        margin-left: auto;
        margin-right: auto;
    }

    .leave-hero-card {
        position: relative;
        max-width: 820px;
        margin: 0 auto 1.5rem;
        padding: 1.3rem 1.5rem;
        overflow: hidden;
        text-align: center;
        background:
            radial-gradient(circle at top right, rgba(59,130,246,.10), transparent 34%),
            linear-gradient(145deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid rgba(148,163,184,.18);
        border-radius: 1.35rem;
        box-shadow: 0 14px 34px rgba(15,23,42,.07);
    }

    .leave-hero-card::after {
        content: '';
        position: absolute;
        width: 120px;
        height: 120px;
        left: -52px;
        bottom: -70px;
        border-radius: 50%;
        background: rgba(14,165,233,.06);
        pointer-events: none;
    }

    .leave-hero-icon {
        width: 48px;
        height: 48px;
        margin: 0 auto .7rem;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        box-shadow: 0 8px 18px rgba(15,23,42,.06);
    }

    .leave-hero-title {
        margin: 0;
        color: #0f172a;
        font-size: 1.45rem;
        font-weight: 700;
        line-height: 1.25;
    }

    .leave-hero-subtitle {
        margin: .3rem 0 0;
        color: #64748b;
        font-size: .9rem;
    }

    /* Compact leave wallet */
    .leave-wallet {
        max-width: 1120px;
        margin: 0 auto 1.5rem;
        padding: .85rem;
        background: linear-gradient(145deg, #fbfdff 0%, #f8fafc 100%);
        border: 1px solid #e5edf5;
        border-radius: 1.15rem;
        box-shadow: 0 8px 22px rgba(15,23,42,.04);
    }

    .leave-wallet-heading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .7rem;
        margin-bottom: .85rem;
        text-align: center;
    }

    .leave-wallet-heading .icon-box-sm {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        font-size: 1rem;
        flex: 0 0 auto;
    }

    .leave-wallet-heading h4 {
        margin: 0;
        color: #0f172a;
        font-size: 1rem;
        font-weight: 700;
    }

    .leave-wallet-heading small {
        display: block;
        margin-top: 1px;
        color: #94a3b8;
        font-size: .72rem;
        font-weight: 500;
    }

    .leave-wallet-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .8rem;
        min-height: 54px;
        padding: .45rem .55rem .45rem .7rem;
        background: rgba(255,255,255,.72);
        border: 1px solid #e7eef5;
        border-radius: .95rem;
    }

    .leave-wallet-summary-main {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: .7rem;
    }

    .leave-wallet-summary-copy {
        min-width: 0;
    }

    .leave-wallet-key-balances {
        margin-left: auto;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .45rem;
        flex-wrap: wrap;
    }

    .leave-wallet-key-chip {
        min-width: 92px;
        padding: .38rem .55rem;
        border: 1px solid #e2e8f0;
        border-radius: .75rem;
        background: #ffffff;
        text-align: center;
        line-height: 1.1;
        box-shadow: 0 2px 8px rgba(15,23,42,.035);
    }

    .leave-wallet-key-chip .key-label {
        display: block;
        max-width: 105px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #64748b;
        font-size: .64rem;
        font-weight: 700;
    }

    .leave-wallet-key-chip .key-value {
        display: block;
        margin-top: 3px;
        color: #0f172a;
        font-size: .9rem;
        font-weight: 800;
    }

    .leave-wallet-key-chip .key-unit {
        color: #94a3b8;
        font-size: .62rem;
        font-weight: 600;
    }

    .leave-wallet-summary-title {
        margin: 0;
        color: #0f172a;
        font-size: .94rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .leave-wallet-summary-subtitle {
        margin-top: 2px;
        color: #94a3b8;
        font-size: .7rem;
        line-height: 1.25;
    }

    .leave-wallet-toggle {
        min-width: 118px;
        min-height: 36px;
        padding: .4rem .7rem;
        border: 1px solid #dbe7f0;
        border-radius: .75rem;
        background: #ffffff;
        color: #0f766e;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        font-size: .73rem;
        font-weight: 700;
        white-space: nowrap;
        box-shadow: 0 3px 10px rgba(15,23,42,.04);
        transition: all .18s ease;
    }

    .leave-wallet-toggle:hover {
        background: #f0fdfa;
        border-color: #99f6e4;
        color: #115e59;
        transform: translateY(-1px);
    }

    .leave-wallet-toggle i {
        transition: transform .2s ease;
    }

    .leave-wallet-toggle[aria-expanded="true"] i {
        transform: rotate(180deg);
    }

    .leave-wallet-toggle .label-expanded {
        display: none;
    }

    .leave-wallet-toggle[aria-expanded="true"] .label-collapsed {
        display: none;
    }

    .leave-wallet-toggle[aria-expanded="true"] .label-expanded {
        display: inline;
    }

    .leave-wallet-collapse {
        padding-top: .8rem;
    }

    .leave-wallet-collapse.collapsing {
        transition: height .22s ease;
    }

    .leave-wallet .row {
        justify-content: center;
    }

    .leave-wallet-card {
        height: 100%;
        border-left-width: 4px;
        border-radius: .9rem;
        box-shadow: 0 3px 12px rgba(15,23,42,.045);
    }

    .leave-wallet-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(15,23,42,.07);
    }

    .leave-wallet-card .card-body {
        padding: .85rem .95rem !important;
    }

    .leave-wallet-card .wallet-type {
        margin: 0;
        color: #64748b;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .leave-wallet-card .wallet-icon {
        width: 34px;
        min-width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .leave-wallet-card .wallet-balance {
        margin: .45rem 0 .55rem;
        display: flex;
        align-items: baseline;
        gap: .3rem;
    }

    .leave-wallet-card .wallet-balance strong {
        color: #0f172a;
        font-size: 1.55rem;
        line-height: 1;
    }

    .leave-wallet-card .wallet-balance span {
        color: #94a3b8;
        font-size: .75rem;
        font-weight: 500;
    }

    .leave-wallet-card .progress-thin {
        height: 5px;
        margin-bottom: .35rem !important;
    }

    .leave-wallet-card .wallet-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        color: #94a3b8;
        font-size: .68rem;
        line-height: 1.2;
    }

    .leave-wallet-card .wallet-meta strong {
        color: #334155;
        font-weight: 700;
    }

    @media (max-width: 767.98px) {
        .leave-page-container {
            padding-left: .85rem !important;
            padding-right: .85rem !important;
        }

        .leave-hero-card {
            padding: 1.05rem 1rem;
            border-radius: 1.1rem;
        }

        .leave-hero-title {
            font-size: 1.2rem;
        }

        .leave-hero-subtitle {
            font-size: .8rem;
        }

        .leave-wallet {
            padding: .7rem;
        }

        .leave-wallet-summary {
            align-items: center;
            flex-wrap: wrap;
            padding: .55rem;
        }

        .leave-wallet-summary-main {
            flex: 1 1 calc(100% - 52px);
        }

        .leave-wallet-key-balances {
            order: 3;
            width: 100%;
            margin-left: 0;
            justify-content: flex-start;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-top: .45rem;
            scrollbar-width: none;
        }

        .leave-wallet-key-balances::-webkit-scrollbar {
            display: none;
        }

        .leave-wallet-key-chip {
            min-width: 96px;
            flex: 0 0 auto;
        }

        .leave-wallet-summary-subtitle {
            display: none;
        }

        .leave-wallet-toggle {
            min-width: 42px;
            width: 42px;
            height: 36px;
            padding: 0;
        }

        .leave-wallet-toggle .label-collapsed,
        .leave-wallet-toggle .label-expanded {
            display: none !important;
        }
    }
</style>
<link rel="stylesheet" href="public/css/leave-workflow.css?v=2">

<div class="rp-page leave-page-container">
    <?php
    ob_start();
    ?>
        <a href="index.php?c=leave&a=index" class="rp-btn rp-btn--secondary">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            ประวัติการลา
        </a>
    <?php
    $leave_header_actions = ob_get_clean();
    rp_page_header(
        $page_title,
        'ตรวจสอบสิทธิ์ ยื่นคำขอ และติดตามสถานะการลาได้ในหน้าเดียว',
        $leave_header_actions,
        'Leave'
    );
    ?>

    <!-- Feedback -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="rp-alert rp-alert--success" role="status" aria-live="polite">
            <span class="rp-alert__icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></span>
            <div class="rp-alert__content"><?= rp_e($_SESSION['success_msg']) ?></div>
        </div>
        <?php unset($_SESSION['success_msg']); endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="rp-alert rp-alert--danger" role="alert">
            <span class="rp-alert__icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></span>
            <div class="rp-alert__content"><?= rp_e($_SESSION['error_msg']) ?></div>
        </div>
        <?php unset($_SESSION['error_msg']); endif; ?>

    <section class="rp-leave-wallet" aria-labelledby="leaveWalletTitle">
        <div class="rp-leave-wallet__summary">
            <div class="d-flex align-items-center gap-2 flex-grow-1">
                <div class="icon-box-sm bg-info bg-opacity-10 text-info">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="rp-leave-wallet__copy">
                    <h4 id="leaveWalletTitle" class="rp-leave-wallet__title">กระเป๋าสิทธิ์วันลาคงเหลือของคุณ</h4>
                    <div class="rp-leave-wallet__subtitle">ย่อไว้เพื่อประหยัดพื้นที่หน้าจอ • กดเพื่อดูรายละเอียดสิทธิ์วันลา</div>
                </div>
            </div>

            <?php
                // แสดงเฉพาะยอดคงเหลือที่สำคัญในสถานะย่อ เพื่อไม่ให้กินพื้นที่หน้าจอ
                $wallet_summary_items = [];
                if (!empty($leave_balances)) {
                    if (!empty($selected_leave_type_req)) {
                        foreach ($leave_balances as $summary_balance) {
                            if (($summary_balance['leave_type_name'] ?? '') === $selected_leave_type_req) {
                                $wallet_summary_items[] = $summary_balance;
                                break;
                            }
                        }
                    } else {
                        $wallet_priority_types = ['ลาป่วย', 'ลากิจส่วนตัว', 'ลาพักผ่อน'];

                        foreach ($wallet_priority_types as $priority_type) {
                            foreach ($leave_balances as $summary_balance) {
                                if (($summary_balance['leave_type_name'] ?? '') === $priority_type) {
                                    $wallet_summary_items[] = $summary_balance;
                                    break;
                                }
                            }
                        }

                        if (count($wallet_summary_items) < 3) {
                            foreach ($leave_balances as $summary_balance) {
                                $already_added = false;
                                foreach ($wallet_summary_items as $existing_summary) {
                                    if (($existing_summary['leave_type_name'] ?? '') === ($summary_balance['leave_type_name'] ?? '')) {
                                        $already_added = true;
                                        break;
                                    }
                                }

                                if (!$already_added) {
                                    $wallet_summary_items[] = $summary_balance;
                                }

                                if (count($wallet_summary_items) >= 3) break;
                            }
                        }
                    }
                }
            ?>

            <?php if (!empty($wallet_summary_items)): ?>
                <div class="rp-leave-wallet__balances" aria-label="สรุปวันลาคงเหลือ">
                    <?php foreach ($wallet_summary_items as $summary_balance): ?>
                        <div class="rp-leave-balance-chip" title="<?= htmlspecialchars($summary_balance['leave_type_name']) ?>">
                            <span class="rp-leave-balance-chip__label"><?= htmlspecialchars($summary_balance['leave_type_name']) ?></span>
                            <span class="rp-leave-balance-chip__value">
                                <?= floatval($summary_balance['remaining']) ?>
                                <span class="key-unit">วัน</span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <button
                class="leave-wallet-toggle"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#leaveWalletDetails"
                aria-expanded="false"
                aria-controls="leaveWalletDetails">
                <span class="label-collapsed">แสดงรายละเอียด</span>
                <span class="label-expanded">ซ่อนรายละเอียด</span>
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>

        <div class="collapse leave-wallet-collapse" id="leaveWalletDetails">
            <div class="row g-2">
            <?php if (!empty($leave_balances)): ?>
                <?php 
                    $has_shown_card = false;
                    foreach ($leave_balances as $balance): 
                        if (!empty($selected_leave_type_req) && $balance['leave_type_name'] != $selected_leave_type_req) continue;
                        $has_shown_card = true;

                        $color_theme = 'primary'; $icon = 'bi-calendar2-check';
                        if ($balance['leave_type_name'] == 'ลาป่วย') { $color_theme = 'danger'; $icon = 'bi-bandaid'; }
                        if ($balance['leave_type_name'] == 'ลากิจส่วนตัว') { $color_theme = 'warning'; $icon = 'bi-briefcase'; }
                        if ($balance['leave_type_name'] == 'ลาพักผ่อน') { $color_theme = 'success'; $icon = 'bi-brightness-high'; }

                        $total_allowable = floatval($balance['total_allowable']);
                        $used_days = floatval($balance['used_days']);
                        $remaining = floatval($balance['remaining']);
                        $percent_used = $total_allowable > 0 ? ($used_days / $total_allowable) * 100 : 0;
                ?>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card card-stat leave-wallet-card border-<?= $color_theme ?>">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <h6 class="wallet-type"><?= htmlspecialchars($balance['leave_type_name']) ?></h6>
                                    <div class="wallet-icon bg-<?= $color_theme ?> bg-opacity-10 text-<?= $color_theme ?>">
                                        <i class="bi <?= $icon ?>"></i>
                                    </div>
                                </div>

                                <div class="wallet-balance">
                                    <strong><?= $remaining ?></strong>
                                    <span>/ <?= $total_allowable ?> วัน</span>
                                </div>

                                <div class="progress progress-thin">
                                    <div class="progress-bar bg-<?= $color_theme ?>"
                                         role="progressbar"
                                         style="width: <?= min(100, max(0, $percent_used)) ?>%"
                                         aria-valuenow="<?= round($percent_used, 1) ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>

                                <div class="wallet-meta">
                                    <span>ใช้ไป <strong><?= $used_days ?></strong> วัน</span>
                                    <?php if($balance['carried_over_days'] > 0): ?>
                                        <span class="text-info fw-bold text-nowrap">
                                            <i class="bi bi-arrow-up-right-circle"></i>
                                            ยกมา <?= floatval($balance['carried_over_days']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span><?= round($percent_used) ?>%</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!$has_shown_card && !empty($selected_leave_type_req)): ?>
                    <div class="col-12">
                        <div class="alert alert-modern alert-warning px-4 py-3 d-flex align-items-center shadow-sm mb-0">
                            <i class="bi bi-exclamation-circle-fill fs-4 text-warning me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">ไม่พบข้อมูลโควตา "<?= htmlspecialchars($selected_leave_type_req) ?>"</h6>
                                <p class="mb-0 small text-muted">คุณอาจไม่มีสิทธิ์ในประเภทการลานี้ หรือระบบยังไม่ได้กำหนดโควตาให้ กรุณาติดต่อผู้ดูแลระบบ</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-modern alert-info px-4 py-3 d-flex align-items-center shadow-sm mb-0">
                        <i class="bi bi-info-circle-fill fs-4 text-info me-3"></i>
                        <div>
                            <h6 class="fw-bold mb-1">ยังไม่มีข้อมูลบัญชีวันลา</h6>
                            <p class="mb-0 small text-muted">ระบบกำลังประมวลผลกระเป๋าวันลาของคุณ กรุณาติดต่อผู้ดูแลระบบหากไม่พบข้อมูลเกิน 24 ชั่วโมง</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="row g-4 mb-5 leave-main-grid">
        <!-- ========================================== -->
        <!-- 🌟 ส่วนที่ 1: ฟอร์มยื่นใบลา -->
        <!-- ========================================== -->
        <div class="col-lg-4">
            <div class="rp-card h-100">
                <div class="rp-card__header">
                    <div class="icon-box-sm bg-<?= $page_theme ?> bg-opacity-10 text-<?= $page_theme ?> me-3">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <h5 class="mb-0 fw-bold text-dark">
                        <?= $selected_leave_type_req ? 'แบบฟอร์ม' . htmlspecialchars($selected_leave_type_req) : 'ยื่นแบบฟอร์มขอลา' ?>
                    </h5>
                </div>
                <div class="rp-card__body">
                    <form action="index.php?c=leave&a=request" method="POST" id="leaveForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="rp-leave-form-steps" aria-label="ขั้นตอนการยื่นลา">
                            <div class="rp-leave-form-step rp-leave-form-step--active" data-step="1">
                                <span class="rp-leave-form-step__number">1</span>
                                <span>ประเภทลา</span>
                            </div>
                            <div class="rp-leave-form-step" data-step="2">
                                <span class="rp-leave-form-step__number">2</span>
                                <span>ช่วงวัน</span>
                            </div>
                            <div class="rp-leave-form-step" data-step="3">
                                <span class="rp-leave-form-step__number">3</span>
                                <span>รายละเอียด</span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">ประเภทการลา <span class="text-danger">*</span></label>
                            <select name="leave_type_id" id="leave_type" class="form-select rp-control" required>
                                <option value="">-- กรุณาเลือกประเภทการลา --</option>
                                <?php foreach($leave_types as $type): 
                                    $is_selected = ($selected_leave_type_req === $type['leave_type']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $type['id'] ?>" data-name="<?= htmlspecialchars($type['leave_type']) ?>" <?= $is_selected ?>>
                                        <?= htmlspecialchars($type['leave_type']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label fw-bold text-secondary small text-uppercase">ตั้งแต่วันที่ <span class="text-danger">*</span></label>
                                <div class="input-group-modern d-flex align-items-center bg-white">
                                    <span class="ps-3 text-primary"><i class="bi bi-calendar-event"></i></span>
                                    <input type="text" name="start_date" id="start_date" class="form-control rp-control border-0 px-2 fw-medium" required placeholder="คลิกเลือก" readonly style="background-color: transparent;">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-bold text-secondary small text-uppercase">ถึงวันที่ <span class="text-danger">*</span></label>
                                <div class="input-group-modern d-flex align-items-center bg-white">
                                    <span class="ps-3 text-danger"><i class="bi bi-calendar-check"></i></span>
                                    <input type="text" name="end_date" id="end_date" class="form-control rp-control border-0 px-2 fw-medium" required placeholder="คลิกเลือก" readonly style="background-color: transparent;">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary small text-uppercase">เหตุผลการลา <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control rp-control" rows="3" maxlength="1000" aria-describedby="reasonHelp" placeholder="ระบุเหตุผลที่ชัดเจน เช่น พักผ่อนประจำปี, ป่วยเป็นไข้..." required></textarea>
                            <div id="reasonHelp" class="form-text text-muted">สูงสุด 1,000 ตัวอักษร</div>
                        </div>

                        <div class="rp-leave-preview" id="leaveRequestPreview" hidden aria-live="polite">
                            <p class="rp-leave-preview__title">สรุปคำขอก่อนส่ง</p>
                            <div class="rp-leave-preview__grid">
                                <div class="rp-leave-preview__item">
                                    <span class="rp-leave-preview__label">ประเภท</span>
                                    <span class="rp-leave-preview__value" id="previewLeaveType">-</span>
                                </div>
                                <div class="rp-leave-preview__item">
                                    <span class="rp-leave-preview__label">จำนวนวันทำการ</span>
                                    <span class="rp-leave-preview__value" id="previewLeaveDays">-</span>
                                </div>
                                <div class="rp-leave-preview__item">
                                    <span class="rp-leave-preview__label">คงเหลือก่อนยื่น</span>
                                    <span class="rp-leave-preview__value" id="previewLeaveBalance">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- แจ้งเตือนอัปโหลดใบรับรองแพทย์ -->
                        <div class="mb-4" id="med_cert_section" style="display: none;">
                            <div class="alert alert-modern alert-danger px-4 py-3 mb-0 border-0 bg-danger bg-opacity-10 text-danger rounded-4 shadow-sm">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i> 
                                    <strong>ลาป่วยตั้งแต่ 3 วันทำการขึ้นไป</strong>
                                </div>
                                <label class="form-label small text-dark fw-bold mb-2">โปรดแนบไฟล์ใบรับรองแพทย์ (JPG, PNG, PDF)</label>
                                <input class="form-control form-control-sm border-danger border-opacity-25 shadow-sm rounded-3 bg-white" type="file" name="med_cert_file" id="med_cert_file" accept=".jpg,.jpeg,.png,.pdf,application/pdf,image/jpeg,image/png" aria-describedby="medCertHelp">
                                <div id="medCertHelp" class="form-text">รองรับ JPG, PNG, PDF ขนาดไม่เกิน 5 MB</div>
                            </div>
                        </div>

                        <button type="submit" id="btnSubmitLeave" class="rp-btn rp-btn--primary w-100 mt-2">
                            <i class="bi bi-send-fill"></i> <span id="btnSubmitText">ยืนยันการส่งใบลา</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 🌟 ส่วนที่ 2: ประวัติการลา และ การจัดการคำขอ -->
        <!-- ========================================== -->
        <div class="col-lg-8">
            <div class="rp-card h-100">
                <div class="rp-card__header flex-wrap">
                    <div class="d-flex align-items-center mt-1">
                        <div class="icon-box-sm bg-secondary bg-opacity-10 text-secondary me-3">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h5 class="mb-0 fw-bold text-dark">ประวัติการลาของฉัน</h5>
                    </div>
                    
                    <!-- ส่วนกรองเดือน และช่องค้นหา -->
                    <div class="rp-leave-history-toolbar">
                        <form action="index.php" method="GET" class="m-0">
                            <input type="hidden" name="c" value="leave">
                            <input type="hidden" name="a" value="index">
                            <?php if(!empty($selected_leave_type_req)): ?>
                                <input type="hidden" name="type" value="<?= htmlspecialchars($selected_leave_type_req) ?>">
                            <?php endif; ?>
                            
                            <div class="input-group input-group-sm shadow-sm rounded-pill overflow-hidden">
                                <span class="input-group-text bg-white border-0 text-muted ps-3"><i class="bi bi-calendar-range"></i></span>
                                <select name="month" class="rp-control" onchange="this.form.submit()" aria-label="เลือกเดือนประวัติการลา">
                                    <?php 
                                        $current_y = (int)date('Y');
                                        $sel_y = (int)substr($selected_month, 0, 4);
                                        $start_y = min($current_y - 1, $sel_y - 1);
                                        $end_y = max($current_y + 1, $sel_y + 1);
                                        $thai_m_list = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
                                        
                                        for ($y = $end_y; $y >= $start_y; $y--) {
                                            for ($m = 12; $m >= 1; $m--) {
                                                $val = sprintf("%04d-%02d", $y, $m);
                                                $label = $thai_m_list[$m] . ' ' . ($y + 543);
                                                $selected = ($val === $selected_month) ? 'selected' : '';
                                                echo "<option value=\"{$val}\" {$selected}>{$label}</option>";
                                            }
                                        }
                                    ?>
                                </select>
                            </div>
                        </form>

                        <input type="search" id="leaveHistorySearch" class="rp-control" placeholder="ค้นหาประวัติ..." aria-label="ค้นหาประวัติการลา">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive h-100 custom-scrollbar">
                        <table class="table table-hover align-middle mb-0 border-0" id="leaveHistoryTable">
                            <thead class="table-light text-secondary sticky-top" style="z-index: 5;">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase" style="font-size: 12px; font-weight: 700;">ประเภทการลา</th>
                                    <th class="py-3 text-uppercase" style="font-size: 12px; font-weight: 700;">ช่วงวันที่</th>
                                    <th class="text-center py-3 text-uppercase" style="font-size: 12px; font-weight: 700;">จำนวนวัน</th>
                                    <th class="py-3 text-uppercase" style="font-size: 12px; font-weight: 700;">เหตุผล</th>
                                    <th class="text-center pe-4 py-3 text-uppercase" style="width: 150px; font-size: 12px; font-weight: 700;">สถานะ/ดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0" id="leaveHistoryBody">
                                <?php 
                                // 🌟 กรองประวัติ: แสดงเดือนที่เลือก + (ใบลาที่ PENDING หรือ CANCEL_REQUESTED)
                                $my_history = array_filter($my_leaves ?? [], function($l) use ($user_id, $selected_month) {
                                    $leave_month = substr($l['start_date'], 0, 7);
                                    // หากถูกยกเลิกแล้วและไม่ได้อยู่ในเดือนที่เลือก ให้ซ่อนไว้
                                    return $l['user_id'] == $user_id && ($leave_month == $selected_month || in_array($l['status'], ['PENDING', 'CANCEL_REQUESTED']));
                                });
                                ?>
                                <?php if (empty($my_history)): ?>
                                    <tr id="emptyHistoryRow">
                                        <td colspan="5">
                                            <?php rp_empty_state('bi-folder-x', 'ไม่มีประวัติการลาในเดือนนี้', 'ลองเลือกเดือนอื่นจากตัวกรองด้านบนเพื่อดูประวัติย้อนหลัง'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($my_history as $leave): ?>
                                    <tr class="leave-row">
                                        <td class="ps-4 py-3 leave-type-cell">
                                            <div class="d-flex flex-wrap align-items-center mb-1">
                                                <span class="fw-bold text-dark d-block" style="font-size: 14.5px;"><?= htmlspecialchars($leave['leave_type']) ?></span>
                                                <?php if(substr($leave['start_date'], 0, 7) != $selected_month && in_array($leave['status'], ['PENDING', 'CANCEL_REQUESTED'])): ?>
                                                    <span class="badge bg-warning text-dark border border-warning ms-2 rounded-pill shadow-sm" style="font-size: 10px; padding: 2px 6px;">ข้ามเดือน</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if(!empty($leave['med_cert_path'])): ?>
                                                <a href="index.php?c=leave&a=download_med_cert&id=<?= (int)$leave['id'] ?>" target="_blank" rel="noopener" class="badge bg-info bg-opacity-10 text-info text-decoration-none mt-1 border border-info border-opacity-25" style="font-size: 10px;"><i class="bi bi-paperclip"></i> ดูใบรับรอง</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3">
                                            <span class="small bg-light px-2 py-1 rounded-2 border text-nowrap fw-medium text-secondary" style="font-size: 12px;">
                                                <?php 
                                                    $start_dt = getShortThaiDateLeave($leave['start_date']); $end_dt = getShortThaiDateLeave($leave['end_date']);
                                                    echo ($start_dt === $end_dt) ? $start_dt : "{$start_dt} - {$end_dt}";
                                                ?>
                                            </span>
                                        </td>
                                        <td class="text-center py-3"><span class="fw-bold text-primary" style="font-size: 1.1rem;"><?= floatval($leave['num_days']) ?></span></td>
                                        <td class="py-3 leave-reason-cell"><div class="text-truncate text-muted small" style="max-width: 180px;" title="<?= htmlspecialchars($leave['reason']) ?>"><?= htmlspecialchars($leave['reason']) ?></div></td>
                                        <td class="text-center pe-4 py-3 leave-status-cell">
                                            <!-- 🌟 ป้ายสถานะ -->
                                            <?php 
                                                if ($leave['status'] == 'APPROVED') { echo '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 d-block mb-2 py-1 px-2 rounded-pill w-100"><i class="bi bi-check-circle me-1"></i> อนุมัติแล้ว</span>'; } 
                                                elseif ($leave['status'] == 'REJECTED') { echo '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 d-block mb-2 py-1 px-2 rounded-pill w-100"><i class="bi bi-x-circle me-1"></i> ไม่อนุมัติ</span>'; } 
                                                elseif ($leave['status'] == 'CANCELLED') { echo '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 d-block mb-2 py-1 px-2 rounded-pill w-100"><i class="bi bi-slash-circle me-1"></i> ยกเลิกแล้ว</span>'; } 
                                                elseif ($leave['status'] == 'PENDING') { echo '<span class="badge bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50 d-block mb-2 py-1 px-2 rounded-pill w-100"><i class="bi bi-hourglass-split me-1"></i> รอพิจารณา</span>'; } 
                                                elseif ($leave['status'] == 'CANCEL_REQUESTED') { echo '<span class="badge bg-warning text-dark border border-warning d-block mb-2 py-1 px-2 rounded-pill w-100 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i> รออนุมัติยกเลิก</span>'; }
                                                else { echo '<span class="badge bg-light text-dark border d-block mb-2 py-1 px-2 rounded-pill w-100">สถานะไม่ทราบ</span>'; }
                                            ?>
                                            
                                            <!-- ปุ่มเครื่องมือ -->
                                            <div class="d-flex gap-2 justify-content-center">
                                                <a href="index.php?c=leave&a=print&id=<?= $leave['id'] ?>" target="_blank" class="btn btn-soft-primary btn-sm flex-fill rounded-3 fw-bold" style="font-size: 11px;" title="พิมพ์ฟอร์มใบลา"><i class="bi bi-printer"></i> พิมพ์</a>
                                                <a href="index.php?c=leave&a=generate_document&id=<?= (int)$leave['id'] ?>" class="btn btn-outline-primary btn-sm flex-fill rounded-3 fw-bold" style="font-size: 11px;" title="สร้าง Word จาก Template"><i class="bi bi-file-earmark-word"></i> Word</a>
                                                <a href="index.php?c=leave&a=generate_document&amp;format=PDF&amp;id=<?= (int)$leave['id'] ?>" class="btn btn-outline-danger btn-sm flex-fill rounded-3 fw-bold" style="font-size: 11px;" title="สร้าง PDF จาก Template ที่จัดวางฟิลด์แล้ว"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                                                
                                                <?php if (in_array($leave['status'], ['PENDING', 'APPROVED'])): ?>
                                                    <form action="index.php?c=leave&a=cancel" method="POST" class="flex-fill m-0" onsubmit="return confirm('<?= ($leave['status']=='APPROVED') ? 'ใบลาฉบับนี้ถูกอนุมัติไปแล้ว\n\nการยกเลิกจะต้องรอให้หัวหน้าอนุมัติการยกเลิกก่อน ระบบจึงจะคืนโควตาวันลาให้ ยืนยันการส่งคำขอยกเลิกใช่หรือไม่?' : 'คุณแน่ใจหรือไม่ที่จะยกเลิกคำขอใบลาฉบับนี้?' ?>');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="request_id" value="<?= (int)$leave['id'] ?>">
                                                        <button type="submit" class="btn btn-soft-danger btn-sm w-100 rounded-3 fw-bold" style="font-size: 11px;" title="ยกเลิกคำขอนี้"><i class="bi bi-x-circle"></i> ยกเลิก</button>
                                                    </form>
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
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 🌟 ส่วนที่ 3: กระเป๋าวันลาคงเหลือ (Compact) -->
    <!-- ========================================== -->
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchLeaveInput = document.getElementById('leaveHistorySearch'); const leaveTableBody = document.getElementById('leaveHistoryBody');
    if (searchLeaveInput && leaveTableBody) {
        searchLeaveInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase().trim(); const rows = leaveTableBody.querySelectorAll('.leave-row');
            rows.forEach(row => {
                const typeText = row.querySelector('.leave-type-cell')?.textContent.toLowerCase() || '';
                const reasonText = row.querySelector('.leave-reason-cell')?.textContent.toLowerCase() || '';
                const statusText = row.querySelector('.leave-status-cell')?.textContent.toLowerCase() || '';
                if (typeText.includes(filter) || reasonText.includes(filter) || statusText.includes(filter)) { row.style.display = ''; } else { row.style.display = 'none'; }
            });
        });
    }

    const leaveBalanceMap = <?= json_encode(array_reduce($leave_balances ?? [], function($carry, $item) {
        $carry[(string)($item['leave_type_name'] ?? '')] = (float)($item['remaining'] ?? 0);
        return $carry;
    }, []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const leaveTypeSelect = document.getElementById('leave_type'); const medCertSection = document.getElementById('med_cert_section');
    const medCertInput = document.getElementById('med_cert_file'); const leaveForm = document.getElementById('leaveForm');
    const employeeType = "<?= $employee_type ?? '' ?>"; const holidaysList = <?= isset($json_holidays) ? $json_holidays : '[]' ?>;

    function calculateWorkingDays(startDateStr, endDateStr) {
        if (!startDateStr || !endDateStr) return 0;
        const start = new Date(startDateStr); const end = new Date(endDateStr); let count = 0; let curDate = new Date(start.getTime());
        while (curDate <= end) {
            const dayOfWeek = curDate.getDay(); const dateString = curDate.getFullYear() + "-" + String(curDate.getMonth() + 1).padStart(2, '0') + "-" + String(curDate.getDate()).padStart(2, '0');
            if (dayOfWeek !== 0 && dayOfWeek !== 6 && !holidaysList.includes(dateString)) { count++; }
            curDate.setDate(curDate.getDate() + 1);
        }
        return count;
    }

    Array.from(leaveTypeSelect.options).forEach(opt => {
        const name = opt.getAttribute('data-name') || ''; if (!name) return;
        if (employeeType.includes('ทั่วไป')) { if (name === 'ลากิจส่วนตัว' || name === 'ลาพักผ่อน' || name.includes('อุปสมบท') || name.includes('ภริยาคลอด') || name.includes('ศึกษา') || name.includes('ระหว่างประเทศ') || name.includes('ติดตามคู่สมรส') || name.includes('ฟื้นฟู')) { opt.disabled = true; opt.text += ' (ไม่มีสิทธิ)'; } } 
        else if (employeeType.includes('ภารกิจ')) { if (name.includes('ภริยาคลอด') || name.includes('ศึกษา') || name.includes('ระหว่างประเทศ') || name.includes('ติดตามคู่สมรส') || name.includes('ฟื้นฟู')) { opt.disabled = true; opt.text += ' (ไม่มีสิทธิ)'; } }
    });

    function updateLeavePreview() {
        const preview = document.getElementById('leaveRequestPreview');
        const typeEl = document.getElementById('previewLeaveType');
        const daysEl = document.getElementById('previewLeaveDays');
        const balanceEl = document.getElementById('previewLeaveBalance');
        const steps = document.querySelectorAll('.rp-leave-form-step');

        if (!preview || !leaveTypeSelect) return;

        const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
        const leaveName = selectedOption ? (selectedOption.getAttribute('data-name') || '') : '';
        const start = document.getElementById('start_date')?.value || '';
        const end = document.getElementById('end_date')?.value || '';
        const days = (start && end) ? calculateWorkingDays(start, end) : 0;
        const remaining = Object.prototype.hasOwnProperty.call(leaveBalanceMap, leaveName) ? leaveBalanceMap[leaveName] : null;

        preview.hidden = !(leaveName || start || end);
        if (typeEl) typeEl.textContent = leaveName || '-';
        if (daysEl) daysEl.textContent = days > 0 ? days + ' วัน' : '-';
        if (balanceEl) balanceEl.textContent = remaining !== null ? remaining + ' วัน' : 'ไม่จำกัด/ไม่พบโควตา';

        steps.forEach((step, index) => {
            const number = index + 1;
            const active = number === 1 ? !!leaveName : (number === 2 ? !!(start && end) : !!document.querySelector('textarea[name="reason"]')?.value.trim());
            step.classList.toggle('rp-leave-form-step--active', active);
        });
    }

    function checkMedicalCertRequired() {
        const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex]; const leaveName = selectedOption ? selectedOption.getAttribute('data-name') : '';
        const start = document.querySelector('input[name="start_date"]').value; const end = document.querySelector('input[name="end_date"]').value;
        if (leaveName === 'ลาป่วย' && start && end) {
            if (calculateWorkingDays(start, end) >= 3) { medCertSection.style.display = 'block'; medCertInput.required = true; } else { medCertSection.style.display = 'none'; medCertInput.required = false; }
        } else { medCertSection.style.display = 'none'; medCertInput.required = false; }
        updateLeavePreview();
    }

    function changeYearToBuddhist(selectedDates, dateStr, instance) {
        const fp = instance || this; if (!fp || !fp.currentYearElement) return;
        let beYear = fp.currentYear + 543; fp.currentYearElement.value = beYear;
        if (fp.calendarContainer) {
            let yearInputs = fp.calendarContainer.querySelectorAll('.cur-year');
            yearInputs.forEach(function(input) {
                input.value = beYear;
                if (!input.dataset.hooked) {
                    input.dataset.hooked = "true";
                    input.addEventListener('input', function(e) { if (this.value.length === 4) { fp.changeYear(parseInt(this.value) - 543); } });
                }
            });
        }
    }

    const ThaiLocale = { weekdays: { shorthand: ["อา", "จ", "อ", "พ", "พฤ", "ศ", "ส"], longhand: ["อาทิตย์", "จันทร์", "อังคาร", "พุธ", "พฤหัสบดี", "ศุกร์", "เสาร์"], }, months: { shorthand: ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."], longhand: ["มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"], }, firstDayOfWeek: 1, rangeSeparator: " ถึง ", scrollTitle: "เลื่อนเพื่อเพิ่มลด", toggleTitle: "คลิกเพื่อเปลี่ยน", yearAriaLabel: "ปี", monthAriaLabel: "เดือน", };
    const flatpickrConfig = {
        altInput: true, altFormat: "j F Y", dateFormat: "Y-m-d", locale: ThaiLocale, disableMobile: "true",
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            if (dayElem.dateObj.getDay() === 0 || dayElem.dateObj.getDay() === 6) { dayElem.style.color = '#dc2626'; dayElem.style.fontWeight = 'bold'; }
            let dateString = dayElem.dateObj.getFullYear() + "-" + String(dayElem.dateObj.getMonth() + 1).padStart(2, '0') + "-" + String(dayElem.dateObj.getDate()).padStart(2, '0');
            if (holidaysList.includes(dateString)) { dayElem.style.backgroundColor = '#fef2f2'; dayElem.style.color = '#dc2626'; dayElem.style.fontWeight = 'bold'; dayElem.style.border = '1px solid #dc2626'; dayElem.style.borderRadius = '50%'; dayElem.title = "วันหยุดนักขัตฤกษ์"; }
        },
        formatDate: function(date, format, locale) {
            if (format === "j F Y" || format === "j M Y" || format === "d/m/Y") {
                const thaiMonthsFull = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
                const thaiMonthsShort = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                const mArray = format.includes('F') ? thaiMonthsFull : thaiMonthsShort; return `${date.getDate()} ${mArray[date.getMonth()]} ${date.getFullYear() + 543}`;
            }
            return flatpickr.formatDate(date, format, locale);
        },
        onReady: changeYearToBuddhist, onOpen: changeYearToBuddhist, onValueUpdate: changeYearToBuddhist, onYearChange: changeYearToBuddhist, onMonthChange: changeYearToBuddhist, onDraw: changeYearToBuddhist,
        onChange: function(selectedDates, dateStr, instance) { changeYearToBuddhist(selectedDates, dateStr, instance); setTimeout(checkMedicalCertRequired, 50); if (instance.element.id === 'start_date') { endPicker.set('minDate', dateStr); const currentEnd = document.querySelector('input[name="end_date"]').value; if (!currentEnd || new Date(currentEnd) < new Date(dateStr)) { endPicker.setDate(dateStr); } } }
    };

    const startPicker = flatpickr("#start_date", flatpickrConfig); const endPicker = flatpickr("#end_date", flatpickrConfig);
    leaveTypeSelect.addEventListener('change', checkMedicalCertRequired);
    document.querySelector('textarea[name="reason"]')?.addEventListener('input', updateLeavePreview);
    if (leaveTypeSelect.value !== '') checkMedicalCertRequired();
    updateLeavePreview();
    if (leaveForm) { leaveForm.addEventListener('submit', function(e) { const btnSubmit = document.getElementById('btnSubmitLeave'); const btnText = document.getElementById('btnSubmitText'); if (btnSubmit.classList.contains('disabled')) { e.preventDefault(); return; } btnSubmit.classList.add('disabled'); btnSubmit.style.opacity = '0.7'; btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังส่งข้อมูล...'; }); }
});
</script>