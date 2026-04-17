<?php
// ที่อยู่ไฟล์: views/logs/index.php

// ดักจับตัวแปรจาก Controller ป้องกัน Error
$log_list = $logs ?? $system_logs ?? $recent_logs ?? [];
$current_page = $current_page ?? 1;
$total_pages = $total_pages ?? 1;
?>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .log-table th { font-weight: 600; color: #475569; font-size: 14px; background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .log-table td { vertical-align: middle; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
    
    .input-group-modern { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; transition: all 0.2s; }
    .input-group-modern:focus-within { background-color: #ffffff; border-color: #3b82f6; box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15); }
    .input-group-modern input { background: transparent; border: none; box-shadow: none; }
    
    .log-row { transition: background-color 0.2s; }
    .log-row:hover { background-color: #f8fafc; }
    
    /* ปรับแต่ง Scrollbar */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100 bg-light">
    <!-- 🌟 ส่วนหัว (Header) -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-journal-text fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">ประวัติการใช้งานระบบ (System Logs)</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">ตรวจสอบความเคลื่อนไหวและกิจกรรมต่างๆ ที่เกิดขึ้นภายในระบบ</p>
            </div>
        </div>
        
        <div class="d-flex gap-2">
            <div class="input-group input-group-modern shadow-sm" style="width: 250px;">
                <span class="input-group-text bg-transparent border-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="logSearchInput" class="form-control border-0" placeholder="ค้นหาประวัติ (ในหน้านี้)...">
            </div>
        </div>
    </div>

    <!-- 🌟 ตารางแสดงประวัติ -->
    <div class="card card-modern overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive custom-scrollbar" style="max-height: 70vh;">
                <table class="table log-table mb-0">
                    <thead class="sticky-top" style="z-index: 10;">
                        <tr>
                            <th class="ps-4 py-3" style="width: 15%;">วัน-เวลา</th>
                            <th class="py-3" style="width: 20%;">ผู้กระทำ (User)</th>
                            <th class="py-3" style="width: 15%;">ประเภท (Action)</th>
                            <th class="py-3" style="width: 50%;">รายละเอียด (Details)</th>
                        </tr>
                    </thead>
                    <tbody id="logTableBody">
                        <?php if(empty($log_list)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="bi bi-inbox fs-1 opacity-50"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark">ยังไม่มีประวัติการใช้งานในระบบ</h6>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($log_list as $log): 
                                $action = strtoupper($log['action'] ?? $log['action_type'] ?? 'SYSTEM');
                                
                                // จัดการสี Badge ตามประเภทการกระทำ
                                $badgeClass = 'bg-secondary text-dark border-secondary';
                                if(in_array($action, ['CREATE', 'ADD', 'INSERT'])) $badgeClass = 'bg-success text-success border-success';
                                elseif(in_array($action, ['UPDATE', 'EDIT', 'APPROVE'])) $badgeClass = 'bg-warning text-dark border-warning';
                                elseif(in_array($action, ['DELETE', 'REMOVE', 'CANCEL'])) $badgeClass = 'bg-danger text-danger border-danger';
                                elseif(in_array($action, ['LOGIN'])) $badgeClass = 'bg-info text-info border-info';
                                elseif(in_array($action, ['LOGOUT'])) $badgeClass = 'bg-dark text-dark border-dark';

                                // หาชื่อคนทำ
                                $user_name = $log['user_name'] ?? $log['action_by_name'] ?? 'ผู้ใช้ ID: '.($log['user_id'] ?? '?');
                                $created_at = !empty($log['created_at']) ? date('d/m/Y H:i:s', strtotime($log['created_at'])) : '-';
                            ?>
                            <tr class="log-row">
                                <td class="ps-4 text-muted font-monospace" style="font-size: 13px;">
                                    <?= $created_at ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person-circle fs-5 text-secondary me-2 opacity-50"></i> 
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($user_name) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?> bg-opacity-10 border border-opacity-25 px-2 py-1 rounded-pill" style="font-size: 11px; letter-spacing: 0.5px;">
                                        <?= htmlspecialchars($action) ?>
                                    </span>
                                </td>
                                <td class="text-muted pe-4">
                                    <?= htmlspecialchars($log['details'] ?? '-') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if(!empty($log_list)): ?>
        <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3" style="font-size: 13px;">
            <div class="text-muted">
                <i class="bi bi-info-circle text-primary me-1"></i> แสดงข้อมูลหน้าละ 20 รายการ
            </div>
            
            <!-- 🌟 ระบบแบ่งหน้า (Pagination) -->
            <?php if (isset($total_pages) && $total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0 shadow-sm rounded-pill overflow-hidden">
                    <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="index.php?c=logs&page=<?= $current_page - 1 ?>" tabindex="-1">ก่อนหน้า</a>
                    </li>
                    
                    <?php 
                        $start_p = max(1, $current_page - 2);
                        $end_p = min($total_pages, $current_page + 2);
                        for ($i = $start_p; $i <= $end_p; $i++): 
                    ?>
                        <li class="page-item <?= ($current_page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?c=logs&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link px-3" href="index.php?c=logs&page=<?= $current_page + 1 ?>">ถัดไป</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 🌟 ระบบค้นหาประวัติแบบ Real-time (ในหน้าปัจจุบัน)
    const searchInput = document.getElementById('logSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#logTableBody .log-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>