<?php
// ที่อยู่ไฟล์: views/notification/index.php

$notifications = $notifications ?? [];
$unread_count = 0;
foreach ($notifications as $n) {
    if ($n['is_read'] == 0) $unread_count++;
}
?>

<style>
    .notif-card { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .notif-item { transition: all 0.2s ease; border-radius: 1rem; border: 1px solid transparent; }
    .notif-item:hover { background-color: #f8fafc; border-color: #e2e8f0; transform: translateX(5px); }
    .notif-unread { background-color: #eff6ff; border-left: 4px solid #3b82f6 !important; }
    .notif-unread:hover { background-color: #e0f2fe; }
    .icon-box { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 56px; height: 56px;">
                <i class="bi bi-bell-fill fs-4 position-relative">
                    <?php if($unread_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 12px; height: 12px;"></span>
                    <?php endif; ?>
                </i>
            </div>
            <div>
                <h3 class="fw-bolder text-dark mb-0">การแจ้งเตือน (Notifications)</h3>
                <p class="text-muted mb-0" style="font-size: 14px;">รายการอัปเดตและข่าวสารล่าสุดในระบบของคุณ</p>
            </div>
        </div>
        <div>
            <?php if($unread_count > 0): ?>
                <a href="index.php?c=notification&a=read_all" class="btn btn-light border shadow-sm rounded-pill fw-bold text-primary px-4 hover-shadow" onclick="return confirm('ยืนยันทำเครื่องหมายอ่านแล้วทั้งหมด?');">
                    <i class="bi bi-check-all me-1 fs-5 align-middle"></i> อ่านแล้วทั้งหมด
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert bg-success bg-opacity-10 text-success rounded-4 d-flex align-items-center mb-4 p-3 border-start border-success border-4 fw-bold shadow-sm">
            <i class="bi bi-check-circle-fill fs-5 me-3"></i> <?= $_SESSION['success_msg'] ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8 col-xl-7">
            <div class="card notif-card overflow-hidden">
                <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bolder text-dark mb-0"><i class="bi bi-card-list text-primary me-2"></i> รายการทั้งหมด</h6>
                    <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm"><?= $unread_count ?> ข้อความใหม่</span>
                </div>
                <div class="card-body p-3">
                    
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5 text-muted">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-bell-slash fs-1 text-secondary opacity-50"></i>
                            </div>
                            <h5 class="fw-bold text-dark">ไม่มีการแจ้งเตือน</h5>
                            <p class="small">คุณติดตามข่าวสารครบถ้วนแล้วในขณะนี้</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($notifications as $n): 
                                // กำหนดสีและไอคอนตามประเภท
                                $type = strtoupper($n['type'] ?? 'INFO');
                                $icon = 'bi-info-circle';
                                $color = 'primary';
                                
                                if ($type == 'SUCCESS' || $type == 'APPROVED') { $icon = 'bi-check-circle-fill'; $color = 'success'; }
                                elseif ($type == 'WARNING' || $type == 'PENDING') { $icon = 'bi-exclamation-triangle-fill'; $color = 'warning text-dark'; }
                                elseif ($type == 'DANGER' || $type == 'REJECTED') { $icon = 'bi-x-circle-fill'; $color = 'danger'; }
                                elseif ($type == 'SWAP') { $icon = 'bi-arrow-left-right'; $color = 'info'; }
                                elseif ($type == 'LEAVE') { $icon = 'bi-person-dash-fill'; $color = 'warning text-dark'; }

                                $is_read = ($n['is_read'] == 1);
                                $link = !empty($n['link']) ? "index.php?c=notification&a=read&id={$n['id']}&url=" . urlencode($n['link']) : "index.php?c=notification&a=read&id={$n['id']}";
                            ?>
                                <a href="<?= $link ?>" class="text-decoration-none text-dark">
                                    <div class="notif-item p-3 d-flex align-items-start <?= !$is_read ? 'notif-unread shadow-sm' : '' ?>">
                                        <div class="icon-box bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> shadow-sm">
                                            <i class="bi <?= $icon ?>"></i>
                                        </div>
                                        <div class="ms-3 flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="fw-bold mb-1 <?= !$is_read ? 'text-dark' : 'text-secondary' ?>"><?= htmlspecialchars($n['title']) ?></h6>
                                                <small class="text-muted" style="font-size: 11px;"><i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></small>
                                            </div>
                                            <p class="mb-0 text-muted" style="font-size: 13.5px;"><?= htmlspecialchars($n['message']) ?></p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>