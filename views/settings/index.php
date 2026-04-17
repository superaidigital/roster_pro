<?php
// ที่อยู่ไฟล์: views/settings/index.php
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); background: #ffffff; }
    .nav-pills-custom .nav-link {
        color: #475569; border-radius: 0.75rem; padding: 0.8rem 1.2rem; font-weight: 600; margin-bottom: 0.5rem; transition: all 0.2s; text-align: left;
    }
    .nav-pills-custom .nav-link:hover { background-color: #f1f5f9; color: #2563eb; }
    .nav-pills-custom .nav-link.active { background-color: #eff6ff; color: #2563eb; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.1); border-left: 4px solid #3b82f6; }
    
    .input-group-modern { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; overflow: hidden; transition: all 0.2s; }
    .input-group-modern:focus-within { background-color: #ffffff; border-color: #3b82f6; box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15); }
    .input-group-modern .form-control { background: transparent; border: none; box-shadow: none; }
    
    .table-modern th { background-color: #f8fafc; color: #475569; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
    .table-modern td { vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex align-items-center mb-4">
        <div class="bg-dark bg-opacity-10 text-dark rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
            <i class="bi bi-sliders fs-4"></i>
        </div>
        <div>
            <h2 class="h4 text-dark mb-0 fw-bold">ตั้งค่าระบบส่วนกลาง (Control Panel)</h2>
            <p class="text-muted mb-0" style="font-size: 13px;">จัดการสิทธิ์ โควตา และโครงสร้างหลักของระบบ Roster Pro</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-4 d-flex align-items-center mb-4 p-3 shadow-sm border-start border-success border-4">
            <i class="bi bi-check-circle-fill fs-5 me-3"></i> <div class="fw-bold"><?= $_SESSION['success_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); endif; ?>

    <div class="row g-4">
        <!-- 🌟 เมนูแท็บด้านซ้าย -->
        <div class="col-lg-3">
            <div class="card card-modern p-3">
                <div class="nav flex-column nav-pills nav-pills-custom" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    <button class="nav-link <?= $active_tab == 'system' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-system" type="button" role="tab">
                        <i class="bi bi-chat-square-dots me-2"></i> ตั้งค่าการแจ้งเตือน (LINE)
                    </button>
                    <button class="nav-link <?= $active_tab == 'hospitals' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-hospitals" type="button" role="tab">
                        <i class="bi bi-hospital me-2"></i> จัดการหน่วยบริการ
                    </button>
                    <button class="nav-link <?= $active_tab == 'holidays' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-holidays" type="button" role="tab">
                        <i class="bi bi-calendar-event me-2"></i> วันหยุดนักขัตฤกษ์
                    </button>
                    <button class="nav-link <?= $active_tab == 'payrates' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-payrates" type="button" role="tab">
                        <i class="bi bi-cash-stack me-2"></i> เรทค่าตอบแทน (เงินเวร)
                    </button>
                    <a href="index.php?c=leave&a=settings" class="nav-link text-decoration-none">
                        <i class="bi bi-envelope-paper-heart me-2"></i> กฎระเบียบวันลา <i class="bi bi-box-arrow-up-right ms-auto float-end" style="font-size: 10px; margin-top: 4px;"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- 🌟 เนื้อหาแต่ละแท็บด้านขวา -->
        <div class="col-lg-9">
            <div class="tab-content" id="v-pills-tabContent">
                
                <!-- 1. ตั้งค่าระบบ (LINE Notify) -->
                <div class="tab-pane fade <?= $active_tab == 'system' ? 'show active' : '' ?>" id="tab-system" role="tabpanel">
                    <div class="card card-modern">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-line text-success me-2"></i> เชื่อมต่อ LINE Notify</h5>
                        </div>
                        <div class="card-body p-4">
                            <form action="index.php?c=settings&a=save_system" method="POST">
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark">LINE Notify Access Token</label>
                                    <div class="input-group-modern d-flex align-items-center shadow-sm">
                                        <span class="ps-3 text-success"><i class="bi bi-key-fill"></i></span>
                                        <input type="text" name="line_notify_token" class="form-control px-2" value="<?= htmlspecialchars($settings['line_notify_token'] ?? '') ?>" placeholder="วาง Token ที่ได้จากไลน์ที่นี่...">
                                    </div>
                                    <div class="form-text mt-2"><i class="bi bi-info-circle"></i> ออก Token ได้ที่ <a href="https://notify-bot.line.me" target="_blank">notify-bot.line.me</a> (เลือกส่งเข้ากลุ่ม หรือส่วนตัว)</div>
                                </div>
                                
                                <h6 class="fw-bold mb-3">เปิด/ปิด เหตุการณ์ที่ต้องการให้แจ้งเตือน</h6>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="line_notify_on_submit" value="1" <?= ($settings['line_notify_on_submit'] ?? '1') == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-medium">แจ้งเตือนเมื่อ: <span class="text-primary">ส่งตารางเวรขอพิจารณา / อนุมัติเวร</span></label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="line_notify_on_request" value="1" <?= ($settings['line_notify_on_request'] ?? '1') == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-medium">แจ้งเตือนเมื่อ: <span class="text-warning">มีการยื่นขอปลดล็อคแก้ไขตารางเวร</span></label>
                                </div>
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" name="line_notify_on_holiday" value="1" <?= ($settings['line_notify_on_holiday'] ?? '1') == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-medium">แจ้งเตือนเมื่อ: <span class="text-danger">มีการเสนอเพิ่มวันหยุดใหม่ / ยื่นใบลา</span></label>
                                </div>

                                <hr>
                                <button type="submit" class="btn btn-primary fw-bold shadow-sm rounded-pill px-4"><i class="bi bi-save me-1"></i> บันทึกการตั้งค่า</button>
                                <button type="button" class="btn btn-outline-success fw-bold rounded-pill px-4 ms-2" onclick="testLineNotify()"><i class="bi bi-send-check me-1"></i> ทดสอบส่งข้อความ</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 2. จัดการหน่วยบริการ -->
                <div class="tab-pane fade <?= $active_tab == 'hospitals' ? 'show active' : '' ?>" id="tab-hospitals" role="tabpanel">
                    <div class="card card-modern">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-hospital text-danger me-2"></i> รายชื่อหน่วยบริการ</h5>
                            <button class="btn btn-sm btn-primary fw-bold rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#hospModal" onclick="clearHospModal()">
                                <i class="bi bi-plus-lg"></i> เพิ่ม รพ.สต.
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-modern mb-0">
                                    <thead><tr><th class="ps-4">ID</th><th>ชื่อหน่วยบริการ</th><th>จำนวนบุคลากร</th><th class="text-center pe-4">จัดการ</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($hospitals as $h): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-muted">#<?= $h['id'] ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($h['name']) ?></td>
                                            <td><span class="badge bg-secondary">กำลังใช้งาน</span></td>
                                            <td class="text-center pe-4">
                                                <button class="btn btn-sm btn-light border text-primary rounded-circle" onclick="editHosp(<?= $h['id'] ?>, '<?= htmlspecialchars($h['name'], ENT_QUOTES) ?>')"><i class="bi bi-pencil"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. วันหยุดนักขัตฤกษ์ -->
                <div class="tab-pane fade <?= $active_tab == 'holidays' ? 'show active' : '' ?>" id="tab-holidays" role="tabpanel">
                    <div class="card card-modern">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-event text-warning me-2"></i> ปฏิทินวันหยุดราชการ/นักขัตฤกษ์</h5>
                        </div>
                        <div class="card-body p-4 bg-light border-bottom">
                            <form action="index.php?c=settings&a=save_holiday" method="POST" class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">เลือกวันที่</label>
                                    <input type="text" name="holiday_date" id="holiday_picker" class="form-control" placeholder="คลิกเลือกวันหยุด..." required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">ชื่อวันหยุด</label>
                                    <input type="text" name="holiday_name" class="form-control" placeholder="เช่น วันสงกรานต์, วันแรงงาน" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-plus-circle me-1"></i> เพิ่มวันหยุด</button>
                                </div>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table table-modern mb-0">
                                    <thead class="sticky-top"><tr><th class="ps-4">วันที่ (ปี-เดือน-วัน)</th><th>ชื่อวันหยุด</th><th class="text-center pe-4">ลบ</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($holidays as $hol): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold font-monospace text-danger"><?= $hol['holiday_date'] ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($hol['holiday_name']) ?></td>
                                            <td class="text-center pe-4">
                                                <a href="index.php?c=settings&a=delete_holiday&id=<?= $hol['id'] ?>" class="btn btn-sm btn-light border text-danger rounded-circle" onclick="return confirm('ยืนยันลบวันหยุดนี้?');"><i class="bi bi-trash"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. เรทค่าตอบแทน -->
                <div class="tab-pane fade <?= $active_tab == 'payrates' ? 'show active' : '' ?>" id="tab-payrates" role="tabpanel">
                    <div class="card card-modern">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-cash-stack text-success me-2"></i> เรทค่าตอบแทนการขึ้นเวร</h5>
                            <button class="btn btn-sm btn-primary fw-bold rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#payRateModal" onclick="clearPayRateModal()">
                                <i class="bi bi-plus-lg"></i> เพิ่มสูตรคำนวณ
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-modern mb-0 text-center">
                                    <thead>
                                        <tr>
                                            <th class="ps-4 text-start">กลุ่มตำแหน่ง (Keywords)</th>
                                            <th class="text-success bg-success bg-opacity-10">เรท ดึก (ร)</th>
                                            <th class="text-danger bg-danger bg-opacity-10">เรท วันหยุด (ย)</th>
                                            <th class="text-warning text-dark bg-warning bg-opacity-10">เรท บ่าย (บ)</th>
                                            <th class="pe-4">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pay_rates as $pr): ?>
                                        <tr>
                                            <td class="ps-4 text-start">
                                                <div class="text-wrap text-muted small" style="max-width: 250px; line-height: 1.4;"><?= htmlspecialchars($pr['keywords']) ?></div>
                                            </td>
                                            <td class="fw-bold fs-6"><?= number_format($pr['rate_r']) ?> ฿</td>
                                            <td class="fw-bold fs-6"><?= number_format($pr['rate_y']) ?> ฿</td>
                                            <td class="fw-bold fs-6"><?= number_format($pr['rate_b']) ?> ฿</td>
                                            <td class="pe-4">
                                                <button class="btn btn-sm btn-light border text-primary rounded-circle me-1" onclick="editPayRate(<?= $pr['id'] ?>, '<?= htmlspecialchars($pr['keywords'], ENT_QUOTES) ?>', <?= $pr['rate_r'] ?>, <?= $pr['rate_y'] ?>, <?= $pr['rate_b'] ?>)"><i class="bi bi-pencil"></i></button>
                                                <a href="index.php?c=settings&a=delete_payrate&id=<?= $pr['id'] ?>" class="btn btn-sm btn-light border text-danger rounded-circle" onclick="return confirm('ยืนยันลบเรทราคานี้?');"><i class="bi bi-trash"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-white text-muted small py-3"><i class="bi bi-info-circle text-primary"></i> <strong>คำอธิบาย:</strong> ระบบจะนำชื่อตำแหน่งของพนักงานมาตรวจสอบกับช่อง Keywords (คั่นด้วยลูกน้ำ) หากตรงกันจะใช้เรทราคานั้นทันที</div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal แก้ไข รพ.สต. -->
<div class="modal fade" id="hospModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="index.php?c=settings&a=save_hospital" method="POST">
                <input type="hidden" name="id" id="hosp_id">
                <div class="modal-header border-0 pb-0"><h6 class="modal-title fw-bold">ข้อมูลหน่วยบริการ</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label small fw-bold">ชื่อหน่วยบริการ</label>
                    <input type="text" name="name" id="hosp_name" class="form-control" required placeholder="เช่น รพ.สต. บ้านหนอง...">
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal แก้ไข PayRate -->
<div class="modal fade" id="payRateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <form action="index.php?c=settings&a=save_payrate" method="POST">
                <input type="hidden" name="id" id="pr_id">
                <div class="modal-header bg-light border-0"><h6 class="modal-title fw-bold text-dark"><i class="bi bi-cash-stack me-2 text-success"></i> สูตรคำนวณค่าตอบแทน</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-primary">คำค้นหาตำแหน่ง (คั่นด้วยลูกน้ำ ,)</label>
                        <textarea name="keywords" id="pr_keys" class="form-control" rows="3" required placeholder="เช่น พยาบาลวิชาชีพ,แพทย์แผนไทย,นักวิชาการ..."></textarea>
                    </div>
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <label class="form-label small fw-bold text-success">เรท ดึก (ร)</label>
                            <input type="number" name="rate_r" id="pr_r" class="form-control text-center fw-bold text-success" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold text-danger">เรท วันหยุด (ย)</label>
                            <input type="number" name="rate_y" id="pr_y" class="form-control text-center fw-bold text-danger" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold text-warning text-dark">เรท บ่าย (บ)</label>
                            <input type="number" name="rate_b" id="pr_b" class="form-control text-center fw-bold text-warning text-dark" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">บันทึกเรทราคา</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof flatpickr !== 'undefined') {
            flatpickr("#holiday_picker", { dateFormat: "Y-m-d", locale: "th", disableMobile: "true" });
        }
        
        // จดจำ Tab ล่าสุดเมื่อรีเฟรชหน้า
        var triggerTabList = [].slice.call(document.querySelectorAll('#v-pills-tab button'))
        triggerTabList.forEach(function (triggerEl) {
            triggerEl.addEventListener('click', function (event) {
                var tabId = event.target.getAttribute('data-bs-target').replace('#tab-', '');
                history.pushState(null, null, '?c=settings&tab=' + tabId);
            })
        });
    });

    function editHosp(id, name) {
        document.getElementById('hosp_id').value = id; document.getElementById('hosp_name').value = name;
        new bootstrap.Modal(document.getElementById('hospModal')).show();
    }
    function clearHospModal() { document.getElementById('hosp_id').value = ''; document.getElementById('hosp_name').value = ''; }

    function editPayRate(id, keys, r, y, b) {
        document.getElementById('pr_id').value = id; document.getElementById('pr_keys').value = keys;
        document.getElementById('pr_r').value = r; document.getElementById('pr_y').value = y; document.getElementById('pr_b').value = b;
        new bootstrap.Modal(document.getElementById('payRateModal')).show();
    }
    function clearPayRateModal() {
        document.getElementById('pr_id').value = ''; document.getElementById('pr_keys').value = '';
        document.getElementById('pr_r').value = ''; document.getElementById('pr_y').value = ''; document.getElementById('pr_b').value = '';
    }

    function testLineNotify() {
        const token = document.querySelector('input[name="line_notify_token"]').value;
        if(!token) return alert('กรุณาระบุ Token ก่อนทดสอบ');
        fetch('index.php?c=ajax&a=test_line_notify', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({token: token})
        }).then(res => res.json()).then(data => {
            if(data.status === 'success') alert('✅ ส่งข้อความทดสอบสำเร็จ! กรุณาตรวจสอบในแอปพลิเคชัน LINE');
            else alert('❌ ไม่สามารถส่งข้อความได้: ' + data.message);
        });
    }
</script>