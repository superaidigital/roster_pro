<?php
// ที่อยู่ไฟล์: views/settings/line_notify.php
?>
<style>
    .card-modern {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 0.25rem 1.25rem rgba(0, 0, 0, 0.04);
        background: #ffffff;
    }
    .icon-box-lg {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }
    .form-control-modern {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
        transition: all 0.2s;
    }
    .form-control-modern:focus {
        background-color: #ffffff;
        border-color: #00B900;
        box-shadow: 0 0 0 0.25rem rgba(0, 185, 0, 0.15);
    }
    .btn-line {
        background-color: #00B900;
        color: white;
        border: none;
        transition: all 0.2s;
    }
    .btn-line:hover {
        background-color: #009900;
        color: white;
        transform: translateY(-2px);
    }
    /* สไตล์สำหรับ Toggle Switch */
    .form-switch .form-check-input {
        width: 3rem;
        height: 1.5rem;
        margin-top: 0.1rem;
    }
    .form-switch .form-check-input:checked {
        background-color: #00B900;
        border-color: #00B900;
    }
</style>

<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <h2 class="fw-bold text-dark d-flex align-items-center mb-1">
            <div class="icon-box-lg bg-success bg-opacity-10 me-3 shadow-sm" style="color: #00B900;">
                <i class="bi bi-line"></i>
            </div>
            การเชื่อมต่อ LINE Notify
        </h2>
        <p class="text-muted ms-5 ps-4 mb-0">ตั้งค่าการแจ้งเตือนผ่านแอปพลิเคชัน LINE ไปยังกลุ่มหรือบุคคล</p>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3 d-flex align-items-center mb-4">
            <i class="bi bi-check-circle-fill fs-5 text-success me-3"></i> 
            <div class="fw-bold text-dark"><?= $_SESSION['success_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); endif; ?>
        
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger me-3"></i> 
            <div class="fw-bold text-dark"><?= $_SESSION['error_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); endif; ?>

    <div class="row">
        <!-- ตั้งค่า Token & เงื่อนไข -->
        <div class="col-lg-7 mb-4">
            <div class="card card-modern h-100">
                <div class="card-body p-4 p-md-5">
                    <form action="index.php?c=settings&a=save_line_notify" method="POST">
                        
                        <h5 class="fw-bold mb-3 border-bottom pb-2">1. รหัส Token</h5>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">LINE Notify Token</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-key-fill text-muted"></i></span>
                                <input type="text" name="line_notify_token" class="form-control form-control-modern border-start-0" 
                                       placeholder="วาง Token ของคุณที่นี่ (เว้นว่างหากต้องการปิดการใช้งาน)" 
                                       value="<?= htmlspecialchars($settings['line_notify_token'] ?? '') ?>">
                            </div>
                        </div>

                        <h5 class="fw-bold mb-3 border-bottom pb-2 mt-5">2. เงื่อนไขการแจ้งเตือน</h5>
                        <div class="list-group list-group-flush mb-4">
                            <!-- แจ้งเตือนเมื่อส่งใบลา -->
                            <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center border-0">
                                <div>
                                    <div class="fw-bold text-dark mb-1"><i class="bi bi-send-check text-primary me-2"></i>มีการยื่นใบลาใหม่</div>
                                    <div class="small text-muted">แจ้งเตือนเมื่อบุคลากรยื่นคำขอลาเข้าระบบ เพื่อให้ ผอ. พิจารณา</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="line_notify_on_submit" value="1" 
                                        <?= (isset($settings['line_notify_on_submit']) && $settings['line_notify_on_submit'] == '1') ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <!-- แจ้งเตือนเมื่อพิจารณาใบลา -->
                            <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center border-0">
                                <div>
                                    <div class="fw-bold text-dark mb-1"><i class="bi bi-check2-square text-success me-2"></i>มีการอนุมัติ/ไม่อนุมัติใบลา</div>
                                    <div class="small text-muted">แจ้งเตือนผลการพิจารณา เพื่อให้ผู้ยื่นและทีมจัดเวรรับทราบ</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="line_notify_on_request" value="1"
                                        <?= (isset($settings['line_notify_on_request']) && $settings['line_notify_on_request'] == '1') ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <!-- แจ้งเตือนเรื่องเวร -->
                            <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center border-0">
                                <div>
                                    <div class="fw-bold text-dark mb-1"><i class="bi bi-calendar-check text-warning me-2"></i>การประกาศ/แก้ไขตารางเวร</div>
                                    <div class="small text-muted">แจ้งเตือนเมื่อมีการบันทึกหรือเผยแพร่ตารางเวรประจำเดือน</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="line_notify_on_holiday" value="1"
                                        <?= (isset($settings['line_notify_on_holiday']) && $settings['line_notify_on_holiday'] == '1') ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm rounded-pill py-3 mt-2">
                            <i class="bi bi-save me-1"></i> บันทึกการตั้งค่า LINE
                        </button>
                    </form>

                    <!-- ส่วนทดสอบการส่ง -->
                    <hr class="my-5 opacity-10">
                    <form action="index.php?c=settings&a=test_line" method="POST">
                        <div class="d-flex align-items-center justify-content-between bg-light p-4 rounded-4 border">
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">ทดสอบการเชื่อมต่อ</h6>
                                <span class="small text-muted">ระบบจะส่งข้อความทดสอบไปยังกลุ่ม LINE ที่ผูกไว้</span>
                            </div>
                            <button type="submit" class="btn btn-line fw-bold rounded-pill shadow-sm px-4 py-2" <?= empty($settings['line_notify_token']) ? 'disabled' : '' ?>>
                                <i class="bi bi-send-fill me-2"></i> ทดสอบส่ง
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>

        <!-- คู่มือด้านขวา -->
        <div class="col-lg-5 mb-4">
            <div class="card card-modern h-100 bg-success bg-opacity-10 border border-success border-opacity-25">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4" style="color: #00B900;">
                        <i class="bi bi-question-circle-fill me-2"></i> วิธีการขอ Token และติดตั้ง
                    </h5>
                    
                    <div class="timeline-steps">
                        <div class="d-flex mb-4">
                            <div class="me-3"><span class="badge bg-success rounded-circle p-2">1</span></div>
                            <div>
                                <strong class="d-block text-dark mb-1">เข้าสู่ระบบ LINE Notify</strong>
                                <span class="small text-muted">ไปที่เว็บไซต์ <a href="https://notify-bot.line.me/th/" target="_blank" class="fw-bold text-success text-decoration-none">notify-bot.line.me</a> แล้วล็อกอินด้วยบัญชี LINE ของคุณ</span>
                            </div>
                        </div>
                        <div class="d-flex mb-4">
                            <div class="me-3"><span class="badge bg-success rounded-circle p-2">2</span></div>
                            <div>
                                <strong class="d-block text-dark mb-1">สร้าง Token ใหม่</strong>
                                <span class="small text-muted">คลิกที่ชื่อของคุณมุมขวาบน > เลือก "หน้าของฉัน" > เลื่อนลงล่างสุดคลิก "ออก Token"</span>
                            </div>
                        </div>
                        <div class="d-flex mb-4">
                            <div class="me-3"><span class="badge bg-success rounded-circle p-2">3</span></div>
                            <div>
                                <strong class="d-block text-dark mb-1">ตั้งชื่อและเลือกกลุ่มเป้าหมาย</strong>
                                <span class="small text-muted">ตั้งชื่อ (เช่น RosterPro) และเลือกแชทกลุ่ม รพ.สต. ที่ต้องการให้แจ้งเตือนเด้งไป</span>
                            </div>
                        </div>
                        <div class="d-flex mb-4">
                            <div class="me-3"><span class="badge bg-success rounded-circle p-2">4</span></div>
                            <div>
                                <strong class="d-block text-dark mb-1">คัดลอก Token มาใส่ในระบบ</strong>
                                <span class="small text-muted">กดออก Token แล้ว Copy ข้อความยาวๆ มาวางในช่องด้านซ้าย แล้วกดบันทึก</span>
                            </div>
                        </div>
                    </div>

                    <div class="alert bg-white border-0 shadow-sm mt-4 mb-0 py-3 d-flex align-items-center">
                        <i class="bi bi-exclamation-circle-fill text-danger fs-4 me-3"></i>
                        <div class="small fw-medium text-dark">
                            <strong>สำคัญมาก:</strong> คุณต้องเชิญบัญชี <strong>LINE Notify</strong> เข้าไปในกลุ่ม LINE นั้นด้วย ระบบจึงจะส่งข้อความได้
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>