<?php
// ที่อยู่ไฟล์: views/settings/system.php

$role = strtoupper($_SESSION['user']['role'] ?? '');
$is_superadmin = ($role === 'SUPERADMIN');
$settings = $settings ?? []; // รับค่าจาก Controller
require_once __DIR__ . '/../components/ui.php';
?>

<style>
    /* ==========================================================================
       🌟 Premium Modern UI Styles สำหรับหน้าตั้งค่าระบบ
       ========================================================================== */
    body { background-color: #f4f7f6; }
    
    /* Animation โหลดหน้า */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in {
        animation: fadeInUp 0.5s ease-out forwards;
    }

    /* สไตล์การ์ดเมนู */
    .setting-card { 
        border: none; 
        border-radius: 1.25rem; 
        background: #ffffff; 
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
        box-shadow: 0 4px 15px rgba(0,0,0,0.02); 
        height: 100%;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.8);
    }
    
    /* เส้นขีดด้านล่างเวลา Hover */
    .setting-card::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: var(--card-color, #3b82f6);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.3s ease;
    }
    
    .setting-card:hover { 
        transform: translateY(-8px); 
        box-shadow: 0 15px 30px rgba(0,0,0,0.08); 
        border-color: #cbd5e1;
    }
    
    .setting-card:hover::after {
        transform: scaleX(1);
    }
    
    /* กล่องไอคอนแบบ Gradient */
    .icon-box { 
        width: 64px; 
        height: 64px; 
        border-radius: 18px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 28px; 
        margin-bottom: 1.25rem;
        transition: transform 0.3s ease;
        color: #ffffff;
        box-shadow: 0 8px 16px var(--icon-shadow);
    }
    
    .setting-card:hover .icon-box {
        transform: scale(1.1) rotate(5deg);
    }
    
    .card-title-modern { font-size: 1.1rem; font-weight: 800; color: #1e293b; letter-spacing: -0.3px; margin-bottom: 8px; }
    .card-text-modern { font-size: 0.85rem; color: #64748b; line-height: 1.6; margin-bottom: 0; }
    
    /* ชุดสี Gradient แบบพรีเมียม */
    .grad-blue   { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); --icon-shadow: rgba(59, 130, 246, 0.3); --card-color: #3b82f6; }
    .grad-green  { background: linear-gradient(135deg, #10b981 0%, #059669 100%); --icon-shadow: rgba(16, 185, 129, 0.3); --card-color: #10b981; }
    .grad-orange { background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); --icon-shadow: rgba(245, 158, 11, 0.3); --card-color: #f59e0b; }
    .grad-red    { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); --icon-shadow: rgba(239, 68, 68, 0.3); --card-color: #ef4444; }
    .grad-purple { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); --icon-shadow: rgba(139, 92, 246, 0.3); --card-color: #8b5cf6; }
    .grad-cyan   { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); --icon-shadow: rgba(14, 165, 233, 0.3); --card-color: #0ea5e9; }
    .grad-dark   { background: linear-gradient(135deg, #475569 0%, #1e293b 100%); --icon-shadow: rgba(71, 85, 105, 0.3); --card-color: #475569; }

    /* สไตล์ฟอร์มใน Modal */
    .modern-input-group .form-control { border-radius: 0.75rem; border: 1px solid #cbd5e1; padding: 0.75rem 1rem; transition: all 0.2s; background-color: #f8fafc; }
    .modern-input-group .form-control:focus { background-color: #ffffff; border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
    .modern-switch .form-check-input { width: 44px; height: 24px; cursor: pointer; }
    .modern-switch .form-check-input:checked { background-color: #10b981; border-color: #10b981; }
</style>
<link rel="stylesheet" href="public/css/admin-hub.css?v=2">

<div class="rp-page">
    
    <?php
    ob_start();
    if ($is_superadmin):
    ?>
        <span class="rp-badge rp-badge--danger"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> SUPERADMIN</span>
    <?php
    endif;
    $settings_header_actions = ob_get_clean();

    rp_page_header(
        'ตั้งค่าระบบส่วนกลาง',
        'จัดการค่าพื้นฐาน การแจ้งเตือน วันหยุด สิทธิ์ และการดูแลระบบจากศูนย์กลางเดียว',
        $settings_header_actions,
        'System Settings'
    );
    ?>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="rp-alert rp-alert--success" role="status" aria-live="polite">
            <span class="rp-alert__icon"><i class="bi bi-check-circle-fill"></i></span>
            <div class="rp-alert__content"><?= rp_e($_SESSION['success_msg']) ?></div>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="rp-alert rp-alert--danger" role="alert">
            <span class="rp-alert__icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
            <div class="rp-alert__content"><?= rp_e($_SESSION['error_msg']) ?></div>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <section class="rp-section" aria-labelledby="settingsToolsTitle">
        <?php rp_section_header('เครื่องมือระบบ', 'เลือกหมวดที่ต้องการจัดการ ระบบจะแสดงเฉพาะรายการที่สิทธิ์ของคุณเข้าถึงได้'); ?>
        <div class="rp-settings-grid" id="settingsToolsTitle">
            <a href="#" class="rp-setting-card" data-bs-toggle="modal" data-bs-target="#generalSettingsModal">
                <span class="rp-setting-card__icon"><i class="bi bi-sliders"></i></span>
                <h2 class="rp-setting-card__title">ข้อมูลทั่วไปของระบบ</h2>
                <p class="rp-setting-card__description">ชื่อระบบ โหมดซ่อมบำรุง และค่าพื้นฐานสำหรับการทำงาน</p>
                <div class="rp-setting-card__footer">เปิดการตั้งค่า →</div>
            </a>

            <a href="#" class="rp-setting-card rp-setting-card--success" data-bs-toggle="modal" data-bs-target="#lineNotifyModal">
                <span class="rp-setting-card__icon"><i class="bi bi-bell"></i></span>
                <h2 class="rp-setting-card__title">การแจ้งเตือน</h2>
                <p class="rp-setting-card__description">กำหนดช่องทางและเหตุการณ์ที่ต้องส่งการแจ้งเตือนจากระบบ</p>
                <div class="rp-setting-card__footer">ตั้งค่าการแจ้งเตือน →</div>
            </a>

            <a href="index.php?c=settings&a=holidays" class="rp-setting-card rp-setting-card--danger">
                <span class="rp-setting-card__icon"><i class="bi bi-calendar2-heart"></i></span>
                <h2 class="rp-setting-card__title">วันหยุดราชการ</h2>
                <p class="rp-setting-card__description">กำหนดวันหยุดเพื่อใช้คำนวณวันลาและตารางปฏิบัติงาน</p>
                <div class="rp-setting-card__footer">จัดการวันหยุด →</div>
            </a>

            <a href="index.php?c=settings&a=shift_types" class="rp-setting-card rp-setting-card--warning">
                <span class="rp-setting-card__icon"><i class="bi bi-cash-coin"></i></span>
                <h2 class="rp-setting-card__title">กลุ่มสายงาน / ค่าเวร</h2>
                <p class="rp-setting-card__description">กำหนดกลุ่มวิชาชีพและอัตราค่าตอบแทนของเวรแต่ละประเภท</p>
                <div class="rp-setting-card__footer">จัดการอัตรา →</div>
            </a>

            <a href="index.php?c=settings&a=system_status" class="rp-setting-card rp-setting-card--info">
                <span class="rp-setting-card__icon"><i class="bi bi-hdd-network"></i></span>
                <h2 class="rp-setting-card__title">สถานะเซิร์ฟเวอร์</h2>
                <p class="rp-setting-card__description">ตรวจฐานข้อมูล พื้นที่จัดเก็บ และข้อมูลสภาพแวดล้อมของเซิร์ฟเวอร์</p>
                <div class="rp-setting-card__footer">ตรวจสอบสถานะ →</div>
            </a>

            <a href="index.php?c=logs" class="rp-setting-card">
                <span class="rp-setting-card__icon"><i class="bi bi-journal-code"></i></span>
                <h2 class="rp-setting-card__title">ประวัติการใช้งาน</h2>
                <p class="rp-setting-card__description">ตรวจสอบการเพิ่ม แก้ไข ลบ และกิจกรรมสำคัญที่เกิดขึ้นในระบบ</p>
                <div class="rp-setting-card__footer">เปิด Audit Log →</div>
            </a>

            <?php if ($is_superadmin): ?>
                <a href="index.php?c=settings&a=menus" class="rp-setting-card rp-setting-card--violet">
                    <span class="rp-setting-card__icon"><i class="bi bi-ui-checks-grid"></i></span>
                    <h2 class="rp-setting-card__title">สิทธิ์เมนู</h2>
                    <p class="rp-setting-card__description">กำหนดเมนูที่แต่ละบทบาทสามารถเห็นและเข้าใช้งานได้</p>
                    <div class="rp-setting-card__footer">จัดการสิทธิ์ →</div>
                </a>

                <a href="index.php?c=settings&a=backup" class="rp-setting-card rp-setting-card--success">
                    <span class="rp-setting-card__icon"><i class="bi bi-database-down"></i></span>
                    <h2 class="rp-setting-card__title">สำรองข้อมูล</h2>
                    <p class="rp-setting-card__description">สร้าง ดาวน์โหลด และตรวจสอบไฟล์สำรองฐานข้อมูลของระบบ</p>
                    <div class="rp-setting-card__footer">จัดการ Backup →</div>
                </a>

                <a href="#" data-bs-toggle="modal" data-bs-target="#resetSystemModal" class="rp-setting-card rp-setting-card--critical">
                    <span class="rp-setting-card__icon"><i class="bi bi-exclamation-octagon-fill"></i></span>
                    <h2 class="rp-setting-card__title">Factory Reset</h2>
                    <p class="rp-setting-card__description">ล้างข้อมูลตารางเวร วันลา และข้อมูลรอบปีเพื่อเริ่มต้นระบบใหม่</p>
                    <div class="rp-setting-card__footer">การดำเนินการความเสี่ยงสูง →</div>
                </a>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- ================= 🌟 Modal 1: ตั้งค่าข้อมูลทั่วไป ================= -->
<div class="modal fade" id="generalSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom-0 bg-primary bg-opacity-10 pb-3 p-4">
                <h5 class="modal-title fw-bolder text-primary"><i class="bi bi-sliders me-2"></i> ข้อมูลทั่วไปของระบบ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <form action="index.php?c=settings&a=update_system" method="POST">
                <input type="hidden" name="section" value="general">
                <div class="modal-body p-4 modern-input-group">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-2">ชื่อระบบ (System Name)</label>
                        <input type="text" name="settings[system_name]" class="form-control" value="<?= htmlspecialchars($settings['system_name'] ?? 'ระบบจัดการตารางปฏิบัติงานและวันลา') ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark mb-2">ชื่อย่อระบบ (Short Name)</label>
                        <input type="text" name="settings[system_short_name]" class="form-control" value="<?= htmlspecialchars($settings['system_short_name'] ?? 'Roster Pro') ?>">
                    </div>
                    <div class="card bg-warning bg-opacity-10 border-warning border-opacity-25 shadow-none rounded-4">
                        <div class="card-body p-4">
                            <div class="form-check form-switch modern-switch mb-0 d-flex align-items-center">
                                <input class="form-check-input me-3 flex-shrink-0" type="checkbox" id="maintenanceMode" name="settings[maintenance_mode]" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <div>
                                    <label class="form-check-label fw-bolder text-dark mb-1" for="maintenanceMode">เปิดโหมดซ่อมบำรุง (Maintenance Mode)</label>
                                    <div class="text-muted" style="font-size: 0.85rem; line-height: 1.4;">ระบบจะปิดการใช้งานชั่วคราวสำหรับผู้ใช้ทั่วไป จะสามารถล็อกอินเข้าได้เฉพาะ Admin เท่านั้น</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light p-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-light border fw-bold rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm"><i class="bi bi-save me-1"></i> บันทึกการตั้งค่า</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= 🌟 Modal 2: ตั้งค่า LINE Notify ================= -->
<div class="modal fade" id="lineNotifyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom-0 bg-success bg-opacity-10 pb-3 p-4">
                <h5 class="modal-title fw-bolder text-success"><i class="bi bi-line me-2"></i> ตั้งค่าการแจ้งเตือน LINE Notify</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body p-4 bg-white modern-input-group">
                <form action="index.php?c=settings&a=update_system" method="POST" id="lineNotifyForm">
                    <input type="hidden" name="section" value="line_notify">
                    
                    <div class="mb-4 pb-4 border-bottom">
                        <label class="form-label fw-bold text-dark mb-2">LINE Notify Token (สำหรับกลุ่มส่วนกลาง)</label>
                        <div class="input-group shadow-sm border border-success border-opacity-25 rounded-3 overflow-hidden focus-ring-success">
                            <span class="input-group-text bg-success bg-opacity-10 border-0 text-success"><i class="bi bi-key-fill"></i></span>
                            <input type="text" name="settings[line_notify_token]" class="form-control border-0 bg-white" value="<?= htmlspecialchars($settings['line_notify_token'] ?? '') ?>" placeholder="กรอก Token สตริงที่ได้จากเว็บ LINE Notify...">
                        </div>
                    </div>

                    <h6 class="fw-bolder text-dark mb-3"><i class="bi bi-toggle-on text-success me-2"></i> เลือกเหตุการณ์ที่ต้องการให้แจ้งเตือน</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 border rounded-4 bg-light bg-opacity-50 h-100 transition-all hover-bg-white">
                                <div class="form-check form-switch modern-switch mb-0">
                                    <input class="form-check-input float-end ms-2" type="checkbox" id="notifySubmit" name="settings[line_notify_on_submit]" value="1" <?= ($settings['line_notify_on_submit'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark d-block mb-1" for="notifySubmit">ส่งตารางเวรขออนุมัติ</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded-4 bg-light bg-opacity-50 h-100 transition-all hover-bg-white">
                                <div class="form-check form-switch modern-switch mb-0">
                                    <input class="form-check-input float-end ms-2" type="checkbox" id="notifyRequest" name="settings[line_notify_on_request]" value="1" <?= ($settings['line_notify_on_request'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark d-block mb-1" for="notifyRequest">ขอปลดล็อคแก้ไขตาราง</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="p-3 border rounded-4 bg-light bg-opacity-50 transition-all hover-bg-white">
                                <div class="form-check form-switch modern-switch mb-0">
                                    <input class="form-check-input float-end ms-2" type="checkbox" id="notifyHoliday" name="settings[line_notify_on_holiday]" value="1" <?= ($settings['line_notify_on_holiday'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-dark d-block mb-1" for="notifyHoliday">เสนอเพิ่มวันหยุดนักขัตฤกษ์</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0 bg-light p-3 d-flex justify-content-between align-items-center">
                <form action="index.php?c=settings&a=test_line" method="POST" class="m-0" onsubmit="return confirm('ระบบจะทำการส่งข้อความทดสอบไปยังกลุ่ม LINE ของคุณ ยืนยันหรือไม่?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-outline-success fw-bold rounded-pill px-4">
                        <i class="bi bi-send-check-fill me-1"></i> ทดสอบส่งข้อความ
                    </button>
                </form>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border fw-bold rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" form="lineNotifyForm" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm"><i class="bi bi-save me-1"></i> บันทึกตั้งค่า</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= 🌟 Modal 3: รีเซ็ตระบบใหม่ (Factory Reset) ================= -->
<div class="modal fade" id="resetSystemModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom-0 bg-danger bg-opacity-10 pb-3 p-4">
                <h5 class="modal-title fw-bolder text-danger"><i class="bi bi-exclamation-octagon-fill me-2"></i> ยืนยันการรีเซ็ตระบบ (Factory Reset)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            
            <form action="index.php?c=settings&a=factory_reset" method="POST" id="resetForm">
                <div class="modal-body p-4 bg-white">
                    <div class="text-center mb-4">
                        <i class="bi bi-trash3-fill text-danger" style="font-size: 3rem;"></i>
                        <h6 class="fw-bold mt-2 text-dark">ระบบจะทำการลบข้อมูลต่อไปนี้ทั้งหมด:</h6>
                    </div>
                    
                    <ul class="list-group mb-4 shadow-sm rounded-3">
                        <li class="list-group-item text-danger border-danger border-opacity-25 bg-danger bg-opacity-10"><i class="bi bi-check2-square me-2"></i> ข้อมูลตารางเวรปฏิบัติงานทั้งหมด</li>
                        <li class="list-group-item text-danger border-danger border-opacity-25 bg-danger bg-opacity-10"><i class="bi bi-check2-square me-2"></i> ข้อมูลการขออนุมัติวันลาทั้งหมด</li>
                        <li class="list-group-item text-danger border-danger border-opacity-25 bg-danger bg-opacity-10"><i class="bi bi-check2-square me-2"></i> ประวัติการแลกเปลี่ยนเวรทั้งหมด</li>
                        <li class="list-group-item text-danger border-danger border-opacity-25 bg-danger bg-opacity-10"><i class="bi bi-check2-square me-2"></i> ประวัติการใช้งานระบบ (Logs)</li>
                    </ul>

                    <p class="text-muted small text-center mb-3">
                        <i class="bi bi-info-circle-fill text-primary"></i> ข้อมูลผู้ใช้งาน และข้อมูลหน่วยบริการ (รพ.สต.) จะยังคงอยู่ เพื่อให้คุณเริ่มต้นจัดตารางเวรในรอบปีใหม่ได้ทันที
                    </p>

                    <hr>

                    <div class="mb-3 mt-3">
                        <label class="form-label fw-bold text-dark">พิมพ์คำว่า <span class="text-danger">RESET-CONFIRM</span> เพื่อยืนยัน</label>
                        <input type="text" name="confirm_code" id="confirmCodeInput" class="form-control form-control-lg border-danger text-center fw-bold" placeholder="พิมพ์รหัสยืนยันที่นี่" autocomplete="off" required>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 bg-light p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-light border fw-bold rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm" id="btnSubmitReset" disabled>
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> ยืนยันการล้างข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 🌟 ระบบล็อกปุ่ม Reset จนกว่าจะพิมพ์รหัสถูกต้อง
    const confirmInput = document.getElementById('confirmCodeInput');
    const btnSubmitReset = document.getElementById('btnSubmitReset');
    
    if (confirmInput && btnSubmitReset) {
        confirmInput.addEventListener('input', function() {
            if (this.value.trim() === 'RESET-CONFIRM') {
                btnSubmitReset.removeAttribute('disabled');
                btnSubmitReset.classList.remove('btn-danger');
                btnSubmitReset.classList.add('btn-dark'); // เปลี่ยนสีให้รู้ว่าพร้อมกด
            } else {
                btnSubmitReset.setAttribute('disabled', 'true');
                btnSubmitReset.classList.remove('btn-dark');
                btnSubmitReset.classList.add('btn-danger');
            }
        });
    }

    // ป้องกันการกด Enter โดยไม่ได้ตั้งใจ
    document.getElementById('resetForm').addEventListener('submit', function(e) {
        if (confirmInput.value.trim() !== 'RESET-CONFIRM') {
            e.preventDefault();
            alert('กรุณาพิมพ์รหัสยืนยันให้ถูกต้อง!');
        } else {
            // ถ้ายืนยันถูกต้อง ให้ถามครั้งสุดท้าย
            if (!confirm('คุณแน่ใจแล้วใช่ไหม? ข้อมูลที่ถูกลบจะไม่สามารถกู้คืนได้ (เว้นแต่จะใช้ไฟล์ Backup)')) {
                e.preventDefault();
            }
        }
    });
});
</script>