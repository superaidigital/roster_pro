<?php
// ที่อยู่ไฟล์: views/leave/balances.php

// ฟังก์ชันช่วยคำนวณอายุราชการ (ปี และ เดือน)
function calculateServiceAge($start_date) {
    if (empty($start_date) || $start_date == '0000-00-00') return ['years' => 0, 'months' => 0, 'text' => 'ไม่ได้ระบุ'];
    
    $start = new DateTime($start_date);
    $today = new DateTime();
    $diff = $today->diff($start);
    
    return [
        'years' => $diff->y,
        'months' => $diff->m,
        'text' => $diff->y . ' ปี ' . $diff->m . ' เดือน'
    ];
}

// ป้องกันตัวแปร undefined กรณีเปิดหน้าทดสอบ
$users_balances = $users_balances ?? [];
?>
<style>
    /* ปรับแต่ง Scrollbar */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    
    <!-- 🌟 ส่วนหัว (Header) -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-wallet2 fs-4"></i>
            </div>
            <div>
                <h2 class="h4 text-dark mb-0 fw-bold">จัดการวันลาสะสมรายบุคคล</h2>
                <p class="text-muted mb-0" style="font-size: 13px;">คำนวณสิทธิ์และเพดานวันลาพักผ่อนสะสมอัตโนมัติ ตามอายุราชการ</p>
            </div>
        </div>
        
        <div class="d-flex gap-2">
            <!-- ปุ่มประมวลผลยอดปีใหม่ -->
            <form action="index.php?c=leave&a=process_new_year" method="POST" class="m-0" onsubmit="return confirm('ยืนยันการประมวลผลตัดยอดปีใหม่?\n\nระบบจะทำการคำนวณวันลายกมาของทุกคนโดยอัตโนมัติ โดยอ้างอิงจากอายุราชการและระเบียบวันลาพักผ่อน (พนักงานจ้างจะไม่ถูกยกยอดมา)');">
                <button type="submit" class="btn btn-primary rounded-pill fw-bold shadow-sm px-4">
                    <i class="bi bi-arrow-repeat me-1"></i> ประมวลผลตัดยอดปีใหม่
                </button>
            </form>
        </div>
    </div>

    <!-- 🌟 Alert แจ้งเตือน -->
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

    <!-- 🌟 ส่วนช่องค้นหาและตารางข้อมูล -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-table text-primary me-2"></i> ตารางสิทธิ์ลาพักผ่อนประจำปี <?= date('Y') + 543 ?></h6>
            
            <div class="input-group input-group-sm shadow-sm rounded-pill overflow-hidden" style="max-width: 250px; border: 1px solid #e2e8f0;">
                <span class="input-group-text bg-white border-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="searchBalanceInput" class="form-control border-0 bg-white" placeholder="ค้นหาชื่อบุคลากร...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive custom-scrollbar" style="max-height: 65vh;">
                <table class="table table-hover align-middle mb-0" id="balancesTable" style="min-width: 1000px;">
                    <thead class="table-light text-secondary sticky-top" style="font-size: 13px; z-index: 10;">
                        <tr>
                            <th class="ps-4 py-3">บุคลากร / ตำแหน่ง</th>
                            <th class="py-3">วันบรรจุ</th>
                            <th class="py-3">อายุราชการ</th>
                            <th class="py-3 text-center">เพดานสะสมสูงสุด</th>
                            <th class="py-3 text-center text-primary bg-primary bg-opacity-10 rounded-start">ยกมาจากปีก่อน</th>
                            <th class="py-3 text-center text-success bg-success bg-opacity-10">ได้ปีนี้</th>
                            <th class="py-3 text-center text-dark bg-light fw-bold">สิทธิ์รวม</th>
                            <th class="py-3 text-center text-danger bg-danger bg-opacity-10">ใช้ไปแล้ว</th>
                            <th class="py-3 text-center text-dark bg-warning bg-opacity-10 fw-bold">คงเหลือจริง</th>
                            <th class="pe-4 py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0" id="balancesTableBody">
                        <?php if (empty($users_balances)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="bi bi-folder2-open fs-1 d-block mb-2 opacity-50"></i> ไม่พบข้อมูลบุคลากรในหน่วยบริการนี้
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($users_balances as $user): 
                                $age = calculateServiceAge($user['start_date']);
                                
                                // 🌟 ลอจิกการคำนวณตามระเบียบ (ลาพักผ่อน)
                                // ถ้าไม่มี start_date หรืออายุงานน้อยกว่า 10 ปี = 20 วัน, 10 ปีขึ้นไป = 30 วัน
                                $max_accumulate = ($age['years'] >= 10) ? 30 : 20; 
                                $new_quota = 10; // ได้สิทธิ์ใหม่ปีละ 10 วันเสมอสำหรับลาพักผ่อน
                                
                                // ยอดรวม (ยกมา + ของใหม่) ต้องไม่เกินเพดาน
                                $total_entitlement = min($user['brought_forward'] + $new_quota, $max_accumulate);
                                
                                // คงเหลือ = ยอดรวม - ใช้ไป
                                $remaining = $total_entitlement - $user['used_this_year'];
                                
                                // จัดฟอร์แมตวันที่
                                $start_date_text = '-';
                                if (!empty($user['start_date']) && $user['start_date'] != '0000-00-00') {
                                    $date = new DateTime($user['start_date']);
                                    $start_date_text = $date->format('d/m/') . ($date->format('Y') + 543);
                                }
                            ?>
                            <tr class="balance-row">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark balance-name" style="font-size: 14.5px;"><?= htmlspecialchars($user['name']) ?></div>
                                    <div class="small text-muted text-truncate" style="font-size: 12px; max-width: 200px;"><?= htmlspecialchars($user['type']) ?></div>
                                </td>
                                <td class="text-muted" style="font-size: 13px;"><?= $start_date_text ?></td>
                                <td class="fw-medium text-dark" style="font-size: 13px;">
                                    <?= $age['text'] ?>
                                    <?php if($age['years'] >= 10): ?>
                                        <i class="bi bi-star-fill text-warning ms-1" title="อายุราชการ 10 ปีขึ้นไป (เพดาน 30 วัน)"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $age['years'] >= 10 ? 'bg-warning text-dark' : 'bg-secondary' ?> rounded-pill shadow-sm px-2">
                                        สูงสุด <?= $max_accumulate ?> วัน
                                    </span>
                                </td>
                                
                                <td class="text-center fw-bold text-primary bg-primary bg-opacity-10 fs-5">
                                    <?= floatval($user['brought_forward']) ?>
                                </td>
                                <td class="text-center fw-bold text-success bg-success bg-opacity-10 fs-5">
                                    +<?= $new_quota ?>
                                </td>
                                <td class="text-center fw-bold text-dark bg-light fs-5">
                                    <?= floatval($total_entitlement) ?>
                                </td>
                                <td class="text-center fw-bold text-danger bg-danger bg-opacity-10 fs-5">
                                    -<?= floatval($user['used_this_year']) ?>
                                </td>
                                <td class="text-center fw-bold text-dark bg-warning bg-opacity-10 fs-4">
                                    <?= floatval($remaining) ?>
                                </td>
                                
                                <td class="pe-4 text-center">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm fw-bold" 
                                            data-bs-toggle="modal" data-bs-target="#editBalanceModal"
                                            data-id="<?= $user['id'] ?>"
                                            data-name="<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>"
                                            data-max="<?= $max_accumulate ?>"
                                            data-brought="<?= $user['brought_forward'] ?>"
                                            data-used="<?= $user['used_this_year'] ?>">
                                        <i class="bi bi-pencil-square me-1"></i> ปรับยอด
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light border-top py-3 text-muted" style="font-size: 13px;">
            <i class="bi bi-info-circle text-primary me-1"></i> <strong>คำแนะนำ:</strong> ในช่วงต้นปีงบประมาณ สามารถกด "ประมวลผลตัดยอดปีใหม่" เพื่อให้ระบบคำนวณวันลายกยอดอัตโนมัติ หรือจะกด "ปรับยอด" เป็นรายบุคคลก็ได้ครับ
        </div>
    </div>
</div>

<!-- 🌟 Modal ปรับยอดวันลายกมา -->
<div class="modal fade" id="editBalanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="index.php?c=leave&a=save_balance" method="POST">
                <input type="hidden" name="user_id" id="modal_user_id">
                
                <div class="modal-header border-bottom-0 bg-light rounded-top-4 pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-calculator text-primary me-2"></i> ปรับปรุงยอดวันลายกยอด</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="fw-bold fs-5 text-dark" id="modal_user_name">ชื่อบุคลากร</div>
                        <div class="badge bg-warning text-dark border border-warning rounded-pill mt-2 px-3 py-2 shadow-sm" id="modal_max_badge">
                            เพดานสะสมรวมสูงสุด: XX วัน
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-primary">จำนวนวันลายกมาจากปีก่อน (วัน) <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden" style="border: 2px solid #cbd5e1;">
                                <input type="number" step="0.5" class="form-control form-control-lg border-0 fw-bold text-center text-primary" 
                                       name="brought_forward" id="modal_brought" required min="0" max="30">
                                <span class="input-group-text bg-white border-0 text-muted">วัน</span>
                            </div>
                            <div class="form-text mt-2 text-muted" style="font-size: 12px; line-height: 1.5;">
                                <i class="bi bi-info-circle-fill text-primary me-1"></i> กรอกจำนวนวันลาพักผ่อนที่เหลือจากปีงบประมาณที่แล้ว<br>ระบบจะนำไป <b>บวกกับสิทธิ์ปีนี้ (10 วัน)</b> ให้อัตโนมัติ (รวมกันต้องไม่เกินเพดาน)
                            </div>
                        </div>
                    </div>

                    <div class="card bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 p-3 mt-4 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-danger"><i class="bi bi-dash-circle me-1"></i> วันลาที่ใช้ไปแล้วในปีนี้:</span>
                            <span class="fw-bold text-danger fs-5" id="modal_used">X วัน</span>
                        </div>
                        <hr class="my-2 opacity-25 border-danger">
                        <div class="small text-danger fw-medium">
                            * เมื่อบันทึกแล้ว ระบบจะคำนวณวันลา "คงเหลือจริง" ให้ใหม่ทันที
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill fw-bold px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill fw-bold shadow-sm px-4"><i class="bi bi-save me-1"></i> บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script สำหรับหน้าจัดการวันลา -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. ส่งข้อมูลเข้า Modal เมื่อกดปุ่ม "ปรับยอด"
    var editModal = document.getElementById('editBalanceModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            
            document.getElementById('modal_user_id').value = button.getAttribute('data-id');
            document.getElementById('modal_user_name').innerText = button.getAttribute('data-name');
            document.getElementById('modal_brought').value = button.getAttribute('data-brought');
            document.getElementById('modal_used').innerText = button.getAttribute('data-used') + ' วัน';
            
            // จำกัด Max Input ตามเพดานสะสม
            var maxAccumulate = button.getAttribute('data-max');
            document.getElementById('modal_max_badge').innerText = 'เพดานสะสมรวมสูงสุดตามระเบียบ: ' + maxAccumulate + ' วัน';
            // ยอดยกมา กรอกได้ไม่เกิน เพดาน - 10 (เพราะต้องเผื่อให้โควตาปีใหม่)
            document.getElementById('modal_brought').setAttribute('max', maxAccumulate - 10);
        });
    }

    // 2. ระบบค้นหาชื่อพนักงานในตารางแบบ Real-time
    const searchInput = document.getElementById('searchBalanceInput');
    const tableBody = document.getElementById('balancesTableBody');
    
    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase().trim();
            const rows = tableBody.querySelectorAll('.balance-row');
            
            rows.forEach(row => {
                const nameText = row.querySelector('.balance-name')?.textContent.toLowerCase() || '';
                if (nameText.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>