<?php
// ที่อยู่ไฟล์: views/leave/manage.php
?>
<div class="container-fluid px-4">
    <h2 class="mt-4 mb-4">จัดการวันลารายบุคคล</h2>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success_msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); endif; ?>
        
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error_msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); endif; ?>

    <div class="row">
        <!-- Sidebar สำหรับค้นหาและเลือกพนักงาน -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-search text-primary me-2"></i> เลือกบุคลากร</h6>
                </div>
                <div class="card-body bg-light rounded-bottom-4 p-2">
                    <form action="index.php" method="GET" class="mb-3 p-2">
                        <input type="hidden" name="c" value="leave">
                        <input type="hidden" name="a" value="manage">
                        
                        <label class="form-label fw-bold" style="font-size: 14px;">ปีงบประมาณ</label>
                        <select name="year" class="form-select mb-3 shadow-sm border-0" onchange="this.form.submit()">
                            <?php 
                                $current_y = (int)date('Y') + ((int)date('m') >= 10 ? 1 : 0);
                                for ($y = $current_y - 2; $y <= $current_y + 1; $y++) {
                                    $selected = ($y == $budget_year) ? 'selected' : '';
                                    echo "<option value=\"$y\" $selected>" . ($y + 543) . "</option>";
                                }
                            ?>
                        </select>

                        <label class="form-label fw-bold" style="font-size: 14px;">รายชื่อพนักงาน</label>
                        
                        <!-- 🌟 เพิ่มช่องค้นหาชื่อพนักงาน -->
                        <div class="input-group input-group-sm mb-2 shadow-sm border-0 rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="staffSearchManage" class="form-control border-0 ps-0" placeholder="ค้นหาชื่อ หรือตำแหน่ง...">
                        </div>

                        <div class="list-group list-group-flush shadow-sm rounded-3" id="staffListManage" style="max-height: 500px; overflow-y: auto;">
                            <?php foreach($staffs as $st): ?>
                                <button type="submit" name="user_id" value="<?= $st['id'] ?>" class="list-group-item list-group-item-action staff-item-manage <?= ($target_user_id == $st['id']) ? 'active fw-bold' : '' ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div class="text-truncate staff-name-manage" style="max-width: 80%;">
                                            <?= htmlspecialchars($st['name']) ?>
                                            <div class="small staff-type-manage <?= ($target_user_id == $st['id']) ? 'text-white-50' : 'text-muted' ?>"><?= htmlspecialchars($st['type']) ?></div>
                                        </div>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ตารางแก้ไขวันลาของพนักงานที่เลือก -->
        <div class="col-lg-9 mb-4">
            <?php if ($target_user): ?>
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill text-success me-2"></i> ข้อมูลวันลาของ: <span class="text-primary"><?= htmlspecialchars($target_user['name']) ?></span></h5>
                    <span class="badge bg-secondary fs-6">ปีงบประมาณ <?= $budget_year + 543 ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="bg-light p-3 border-bottom d-flex gap-4" style="font-size: 14px;">
                        <div><strong class="text-muted">ประเภทบุคลากร:</strong> <span class="fw-bold text-dark"><?= htmlspecialchars($target_user['employee_type'] ?? '-') ?></span></div>
                        <div><strong class="text-muted">วันที่บรรจุ:</strong> <span class="fw-bold text-dark"><?= !empty($target_user['start_date']) ? date('d/m/', strtotime($target_user['start_date'])).(date('Y', strtotime($target_user['start_date']))+543) : '-' ?></span></div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead class="table-light text-muted">
                                <tr>
                                    <th class="text-start ps-4">ประเภทการลา</th>
                                    <th>โควตาปีนี้</th>
                                    <th>วันลายกมา (สะสม)</th>
                                    <th>รวมสิทธิ์ทั้งหมด</th>
                                    <th class="text-danger">ใช้ไปแล้ว</th>
                                    <th class="text-success">คงเหลือ</th>
                                    <th class="pe-4">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($balances)): ?>
                                    <tr>
                                        <td colspan="7" class="py-5 text-muted">ไม่พบข้อมูลบัญชีวันลาในปีนี้</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($balances as $b): 
                                        $total = $b['quota_days'] + $b['carried_over_days'];
                                        $remain = $total - $b['used_days'];
                                    ?>
                                    <tr>
                                        <td class="text-start ps-4 fw-bold text-dark"><?= htmlspecialchars($b['leave_type_name']) ?></td>
                                        <td><?= floatval($b['quota_days']) ?></td>
                                        <td><?= floatval($b['carried_over_days']) ?></td>
                                        <td class="fw-bold bg-light"><?= floatval($total) ?></td>
                                        <td class="fw-bold text-danger"><?= floatval($b['used_days']) ?></td>
                                        <td class="fw-bold text-success fs-5"><?= floatval($remain) ?></td>
                                        <td class="pe-4">
                                            <button class="btn btn-sm btn-outline-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#editBalanceModal" 
                                                data-id="<?= $b['id'] ?>"
                                                data-name="<?= htmlspecialchars($b['leave_type_name']) ?>"
                                                data-quota="<?= $b['quota_days'] ?>"
                                                data-carried="<?= $b['carried_over_days'] ?>"
                                                data-used="<?= $b['used_days'] ?>">
                                                <i class="bi bi-pencil-square"></i> แก้ไข
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="alert alert-info border-0 shadow-sm text-center py-5 rounded-4">
                    <i class="bi bi-arrow-left-circle fs-1 text-muted d-block mb-3"></i>
                    <h5>กรุณาเลือกรายชื่อพนักงานจากแถบด้านซ้าย</h5>
                    <p class="text-muted mb-0">เพื่อดูและแก้ไขยอดวันลารายบุคคล</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal แก้ไขข้อมูลวันลา -->
<div class="modal fade" id="editBalanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <!-- 🌟 แก้ไข action ให้ชี้ไปที่ Controller ของวันลาอย่างถูกต้อง -->
            <form action="index.php?c=leave&a=manage" method="POST">
                <input type="hidden" name="action" value="update_balance">
                <input type="hidden" name="balance_id" id="edit_balance_id">
                
                <div class="modal-header border-bottom-0 bg-light pb-3 rounded-top-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-sliders text-primary me-2"></i> ปรับปรุงยอด: <span id="edit_leave_name" class="text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning border-0 shadow-sm" style="font-size: 13px;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>ข้อควรระวัง:</strong> การแก้ไขนี้จะกระทบยอดคงเหลือทันที ควรทำเฉพาะกรณีที่ระบบคำนวณวันลายกยอดผิดพลาด หรือมีการเปลี่ยนแปลงสิทธิพิเศษ
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">โควตาปีนี้ (ได้สิทธิ์ตามระเบียบ)</label>
                            <div class="input-group shadow-sm">
                                <input type="number" step="0.5" name="quota_days" id="edit_quota" class="form-control border-0 px-3" required min="0">
                                <span class="input-group-text bg-white border-0 text-muted">วัน</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">วันลายกมาจากปีที่แล้ว (สะสม)</label>
                            <div class="input-group shadow-sm">
                                <input type="number" step="0.5" name="carried_over_days" id="edit_carried" class="form-control border-0 px-3" required min="0">
                                <span class="input-group-text bg-white border-0 text-muted">วัน</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-danger">วันที่ใช้ไปแล้วในปีนี้</label>
                            <div class="input-group shadow-sm">
                                <input type="number" step="0.5" name="used_days" id="edit_used" class="form-control border-0 px-3 text-danger fw-bold" required min="0">
                                <span class="input-group-text bg-white border-0 text-muted">วัน</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary fw-bold rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-bold shadow-sm rounded-pill px-4"><i class="bi bi-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var editModal = document.getElementById('editBalanceModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('edit_balance_id').value = button.getAttribute('data-id');
            document.getElementById('edit_leave_name').innerText = button.getAttribute('data-name');
            document.getElementById('edit_quota').value = button.getAttribute('data-quota');
            document.getElementById('edit_carried').value = button.getAttribute('data-carried');
            document.getElementById('edit_used').value = button.getAttribute('data-used');
        });
    }

    // 🌟 Script สำหรับค้นหาพนักงานในหน้า Manage Leave
    var staffSearchInput = document.getElementById('staffSearchManage');
    if (staffSearchInput) {
        staffSearchInput.addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase().trim();
            var staffItems = document.querySelectorAll('.staff-item-manage');

            staffItems.forEach(function(item) {
                var name = item.querySelector('.staff-name-manage').textContent.toLowerCase();
                var type = item.querySelector('.staff-type-manage').textContent.toLowerCase();

                if (name.includes(searchTerm) || type.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
</script>