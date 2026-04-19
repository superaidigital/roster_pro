<?php
// views/notification/index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: index.php?c=auth&a=login");
    exit;
}

require_once 'config/database.php';
require_once 'models/NotificationModel.php';

$db = (new Database())->getConnection();
$notifModel = new NotificationModel($db);
$user_id = $_SESSION['user']['id'];

// ดึงการแจ้งเตือนทั้งหมด (จำกัด 100 รายการล่าสุดเพื่อไม่ให้หน้าเว็บหนักเกินไป)
$notifications = $notifModel->getUserNotifications($user_id, 100);
$unread_count = $notifModel->getUnreadCount($user_id);
?>

<style>
    /* 🌟 Custom CSS สำหรับหน้า Notification ให้ดูสมส่วนและสวยงาม */
    .notif-container {
        max-width: 850px;
        margin: 0 auto;
        padding: 1.5rem 1rem;
    }
    .notif-card {
        border-radius: 12px;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        overflow: hidden;
        background: #fff;
    }
    .notif-item {
        transition: all 0.2s ease-in-out;
        border-left: 4px solid transparent;
        cursor: pointer;
        position: relative;
        padding: 1.25rem 1.5rem; /* ปรับ Padding ให้ดูโปร่งขึ้น */
    }
    .notif-item:hover {
        background-color: #f8fafc;
    }
    .notif-item.unread {
        background-color: #f0fdf4; /* สีเขียวอ่อนๆ บ่งบอกว่ามีอะไรใหม่ */
        border-left-color: #10b981;
    }
    .notif-item.unread .notif-title {
        font-weight: 700;
        color: #0f172a;
    }
    .notif-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    
    /* ปุ่ม Action ขวามือ */
    .notif-action-btn {
        opacity: 0;
        transition: all 0.2s;
        border-radius: 8px;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: transparent;
        border: none;
    }
    .notif-item:hover .notif-action-btn {
        opacity: 1;
        background-color: #fee2e2;
        color: #ef4444 !important;
    }
    
    /* สำหรับหน้าจอขนาดเล็ก */
    @media (max-width: 768px) {
        .notif-container { padding: 1rem 0.5rem; }
        .notif-item { padding: 1rem; }
        .notif-action-btn { 
            opacity: 1; 
            width: 32px; height: 32px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .notif-title { font-size: 14px !important; }
        .notif-message { font-size: 13px !important; }
        .notif-icon-box { width: 38px; height: 38px; font-size: 1.1rem; }
    }

    .empty-state-icon {
        font-size: 4.5rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }
    
    /* อนิเมชั่นตอนลบ */
    .fade-out {
        opacity: 0;
        transform: translateX(30px);
        transition: all 0.3s ease-out;
    }
</style>

<!-- 🌟 ใช้ container-fluid ควบคู่กับ max-width เพื่อให้ตอบสนองกับหน้าจอหลัก -->
<div class="container-fluid py-3">
    <div class="notif-container">
        
        <!-- 🌟 Header Section -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end mb-4 gap-3">
            <div>
                <h3 class="fw-bolder text-dark mb-1 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-bell-fill fs-5"></i>
                    </div>
                    การแจ้งเตือน
                </h3>
                <p class="text-muted mb-0 small ms-1" style="padding-left: 54px;">
                    คุณมีข้อความใหม่ <span class="badge bg-danger rounded-pill px-2" id="pageUnreadCount"><?= $unread_count ?></span> รายการ
                </p>
            </div>
            <div class="d-flex gap-2">
                <?php if (!empty($notifications)): ?>
                    <button class="btn btn-outline-primary btn-sm fw-bold rounded-pill px-3 shadow-sm" onclick="markAllAsRead()">
                        <i class="bi bi-check2-all me-1"></i> อ่านทั้งหมด
                    </button>
                    <button class="btn btn-outline-danger btn-sm fw-bold rounded-pill px-3 shadow-sm" onclick="deleteAllNotifs()">
                        <i class="bi bi-trash3 me-1"></i> ล้างข้อมูล
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- 🌟 List Section -->
        <div class="card notif-card">
            <div class="list-group list-group-flush" id="notificationList">
                
                <?php if (empty($notifications)): ?>
                    <!-- 🌟 Empty State -->
                    <div class="text-center py-5 my-3" id="emptyState">
                        <i class="bi bi-inbox empty-state-icon d-block"></i>
                        <h5 class="fw-bold text-secondary mt-3">ไม่มีการแจ้งเตือน</h5>
                        <p class="text-muted small">เมื่อมีการอัปเดตตารางเวร หรือการลางาน ข้อความจะแสดงที่นี่</p>
                    </div>
                <?php else: ?>
                
                    <?php foreach ($notifications as $notif): 
                        $is_read = $notif['is_read'] == 1;
                        $type = strtoupper($notif['type'] ?? 'INFO');
                        
                        // กำหนดสีและไอคอนตามประเภท
                        $icon = 'bi-info-circle'; $bg_color = 'bg-primary bg-opacity-10'; $text_color = 'text-primary';
                        if ($type == 'SUCCESS' || $type == 'APPROVED') { $icon = 'bi-check2-circle'; $bg_color = 'bg-success bg-opacity-10'; $text_color = 'text-success'; }
                        elseif ($type == 'WARNING' || $type == 'PENDING') { $icon = 'bi-exclamation-triangle'; $bg_color = 'bg-warning bg-opacity-10'; $text_color = 'text-warning text-dark'; }
                        elseif ($type == 'DANGER' || $type == 'REJECTED') { $icon = 'bi-x-octagon'; $bg_color = 'bg-danger bg-opacity-10'; $text_color = 'text-danger'; }
                        elseif ($type == 'SWAP') { $icon = 'bi-arrow-left-right'; $bg_color = 'bg-info bg-opacity-10'; $text_color = 'text-info text-dark'; }
                        elseif ($type == 'LEAVE') { $icon = 'bi-calendar2-minus'; $bg_color = 'bg-secondary bg-opacity-10'; $text_color = 'text-secondary'; }

                        // แปลงวันที่แบบไทยสั้นๆ
                        $date_time = date('d/m/Y H:i', strtotime($notif['created_at']));
                    ?>
                    
                    <div class="list-group-item notif-item <?= !$is_read ? 'unread' : '' ?>" id="notif-row-<?= $notif['id'] ?>" onclick="handleNotifClick(event, <?= $notif['id'] ?>, '<?= htmlspecialchars($notif['link'] ?? '') ?>')">
                        <div class="d-flex align-items-start gap-3">
                            
                            <!-- Icon -->
                            <div class="notif-icon-box <?= $bg_color ?> <?= $text_color ?>">
                                <i class="bi <?= $icon ?>"></i>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex flex-wrap justify-content-between align-items-center mb-1 gap-2">
                                    <div class="notif-title text-truncate flex-grow-1 <?= $is_read ? 'text-secondary' : '' ?>" style="font-size: 15px;">
                                        <?= htmlspecialchars($notif['title']) ?>
                                        <?php if (!$is_read): ?>
                                            <span class="badge bg-danger rounded-circle p-1 ms-1 d-inline-block align-middle" style="width: 8px; height: 8px; margin-top:-2px;" id="dot-<?= $notif['id'] ?>"></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-muted small text-nowrap"><i class="bi bi-clock me-1"></i><?= $date_time ?></span>
                                </div>
                                <p class="mb-0 text-muted notif-message" style="font-size: 14px; line-height: 1.5; padding-right: 1rem;">
                                    <?= htmlspecialchars($notif['message']) ?>
                                </p>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="d-flex align-items-center align-self-center ps-2">
                                <button class="notif-action-btn text-muted" onclick="deleteNotif(event, <?= $notif['id'] ?>)" title="ลบการแจ้งเตือนนี้">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </div>

                        </div>
                    </div>
                    
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
// ==========================================
// 🌟 JavaScript Functions สำหรับหน้า Notification
// ==========================================

const BASE_URL = 'index.php?c=ajax';

// 1. จัดการเมื่อคลิกที่ Card (อ่าน + ไปที่ลิงก์)
function handleNotifClick(event, id, link) {
    // ถ้าผู้ใช้กดปุ่มลบ ให้ข้ามฟังก์ชันนี้ไป (ป้องกันการเปลี่ยนหน้า)
    if (event.target.closest('button')) return;

    // ทำเครื่องหมายว่าอ่านแล้วเงียบๆ
    fetch(`${BASE_URL}&a=read_notif&id=${id}`).then(() => {
        // อัปเดต UI 
        const row = document.getElementById(`notif-row-${id}`);
        const dot = document.getElementById(`dot-${id}`);
        if(row) {
            row.classList.remove('unread');
            const title = row.querySelector('.notif-title');
            if(title) title.classList.add('text-secondary');
        }
        if(dot) dot.remove();

        // ไปที่ลิงก์ถ้ามี
        if (link && link !== '') {
            window.location.href = link;
        }
    });
}

// 2. ลบการแจ้งเตือน 1 รายการ
function deleteNotif(event, id) {
    event.stopPropagation(); // ไม่ให้ทำงานทับกับการกดอ่าน
    
    Swal.fire({
        title: 'ลบการแจ้งเตือน?',
        text: "คุณต้องการลบข้อความนี้ใช่หรือไม่",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ลบเลย',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`${BASE_URL}&a=delete_notif&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    const row = document.getElementById(`notif-row-${id}`);
                    // ใส่ Animation ก่อนลบ
                    row.classList.add('fade-out');
                    setTimeout(() => {
                        row.remove();
                        checkEmptyState();
                        updateBadgeCount();
                    }, 300);
                }
            });
        }
    });
}

// 3. อ่านทั้งหมด
function markAllAsRead() {
    fetch(`${BASE_URL}&a=read_all_notif`)
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            document.querySelectorAll('.notif-item').forEach(row => {
                row.classList.remove('unread');
                const title = row.querySelector('.notif-title');
                if(title) title.classList.add('text-secondary');
                
                const dot = row.querySelector('.badge.bg-danger.rounded-circle');
                if(dot) dot.remove();
            });
            document.getElementById('pageUnreadCount').innerText = '0';
            
            // อัปเดตกระดิ่งข้างบน Header ด้วย (ถ้ามี)
            const topBadge = document.getElementById('notifBadge');
            if(topBadge) topBadge.style.display = 'none';
            
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'ทำเครื่องหมายอ่านแล้วทั้งหมด', showConfirmButton: false, timer: 1500 });
        }
    });
}

// 4. ลบทั้งหมด
function deleteAllNotifs() {
    Swal.fire({
        title: 'ลบทั้งหมด?',
        text: "ล้างประวัติการแจ้งเตือนทั้งหมดของคุณ (ไม่สามารถกู้คืนได้)",
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ยืนยันการล้างข้อมูล',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`${BASE_URL}&a=delete_all_notif`)
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    document.getElementById('notificationList').innerHTML = '';
                    checkEmptyState();
                    updateBadgeCount();
                    
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'ล้างข้อมูลสำเร็จ', showConfirmButton: false, timer: 1500 });
                }
            });
        }
    });
}

// Helper: ตรวจสอบว่าตารางว่างไหมเพื่อโชว์ Empty State
function checkEmptyState() {
    const list = document.getElementById('notificationList');
    if(list.children.length === 0) {
        list.innerHTML = `
            <div class="text-center py-5 my-4 fade-in">
                <i class="bi bi-check2-circle empty-state-icon text-success"></i>
                <h5 class="fw-bold text-secondary mt-3">เคลียร์กล่องข้อความเรียบร้อย</h5>
                <p class="text-muted small">คุณไม่มีการแจ้งเตือนตกค้างแล้ว</p>
            </div>
        `;
        // ซ่อนปุ่ม Action บนขวา
        const btnGroup = document.querySelector('.d-flex.gap-2');
        if(btnGroup) btnGroup.style.display = 'none';
    }
}

// Helper: อัปเดตตัวเลขแจ้งเตือนหลังจากลบ
function updateBadgeCount() {
    fetch(`${BASE_URL}&a=check_new_notif`).then(res => res.json()).then(data => {
        if(data.status === 'success') {
            document.getElementById('pageUnreadCount').innerText = data.unread_count;
            
            const topBadge = document.getElementById('notifBadge');
            if(topBadge) {
                if(data.unread_count > 0) {
                    topBadge.innerText = data.unread_count > 99 ? '99+' : data.unread_count;
                    topBadge.style.display = 'block';
                } else {
                    topBadge.style.display = 'none';
                }
            }
        }
    });
}
</script>