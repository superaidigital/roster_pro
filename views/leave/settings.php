<?php
// ที่อยู่ไฟล์: views/leave/settings.php
?>
<style>
    /* Modern UI สำหรับหน้าตั้งค่า */
    .card-modern {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 0.25rem 1.25rem rgba(0, 0, 0, 0.04);
        background: #ffffff;
    }
    .icon-box-sm {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .form-control-modern {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        transition: all 0.2s;
    }
    .form-control-modern:focus {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15);
    }
    
    /* 🌟 สไตล์เพิ่มเติมสำหรับ Input Group ให้เหมือนรูปภาพต้นแบบ (ไร้รอยต่อ) */
    .input-group-modern {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        transition: all 0.2s;
        overflow: hidden;
    }
    .input-group-modern:focus-within {
        background-color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15);
    }
    .input-group-modern .form-control {
        background-color: transparent;
        border: none;
        box-shadow: none;
    }
    .input-group-modern .input-group-text {
        background-color: transparent;
        border: none;
        font-weight: 500;
        color: #64748b;
    }
    
    /* สไตล์สำหรับตารางระเบียบการลา */
    .table-rule th {
        vertical-align: middle;
        text-align: center;
        font-weight: bold;
    }
    .table-rule td {
        vertical-align: top;
        font-size: 13px;
        color: #475569;
    }
    .bg-official { background-color: #eff6ff !important; border-top: 3px solid #3b82f6; }
    .bg-mission { background-color: #f0fdf4 !important; border-top: 3px solid #10b981; }
    .bg-general { background-color: #fffbeb !important; border-top: 3px solid #f59e0b; }
    
    .table-hover tbody tr:hover td {
        background-color: #f8fafc;
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="mb-4">
        <h2 class="fw-bold text-dark d-flex align-items-center mb-1">
            <div class="icon-box-sm bg-danger bg-opacity-10 text-danger me-3">
                <i class="bi bi-gear-fill"></i>
            </div>
            ตั้งค่าและระเบียบการลา
        </h2>
        <p class="text-muted ms-5 ps-2 mb-0">ตั้งค่าฐานโควตาวันลา และข้อกำหนดตามระเบียบองค์การบริหารส่วนจังหวัด</p>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3 d-flex align-items-center mb-4">
            <i class="bi bi-check-circle-fill fs-5 text-success me-3"></i> 
            <div class="fw-bold text-dark"><?= $_SESSION['success_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); endif; ?>

    <!-- ========================================== -->
    <!-- 🌟 ส่วนที่ 1: ตั้งค่าฐานสิทธิการลา (Database) -->
    <!-- ========================================== -->
    <div class="card card-modern mb-5">
        <div class="card-header bg-white py-4 border-bottom px-4 d-flex align-items-center">
            <div class="icon-box-sm bg-primary bg-opacity-10 text-primary me-3 shadow-sm">
                <i class="bi bi-sliders"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-bold text-dark">กำหนดฐานโควตาวันลา (Base Quota)</h5>
                <div class="small text-muted mt-1">ใช้เป็นฐานในการคำนวณวันลาของระบบ (อิงตามสิทธิ์สูงสุดของข้าราชการ)</div>
            </div>
        </div>
        <div class="card-body p-0">
            <form action="index.php?c=leave&a=settings" method="POST">
                <div class="table-responsive pb-2">
                    <table class="table table-hover align-middle mb-0 border-0">
                        <thead class="table-light text-dark" style="font-size: 14px;">
                            <tr>
                                <th class="ps-4 py-3" width="25%">ประเภทการลา</th>
                                <th class="py-3" width="25%">จำนวนวันสูงสุดต่อปี (ฐาน)</th>
                                <th class="pe-4 py-3">คำอธิบายแสดงในระบบ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($quotas)): ?>
                                <?php foreach ($quotas as $q): ?>
                                <tr>
                                    <td class="ps-4 py-3 align-middle">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="bi bi-check2-square text-success fs-5"></i>
                                            <span class="fw-bold text-dark" style="font-size: 15px;"><?= htmlspecialchars($q['leave_type']) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 align-middle">
                                        <div class="input-group input-group-modern mx-auto" style="max-width: 180px;">
                                            <!-- รองรับทศนิยมเพื่อแสดง 60.0 ตามรูป -->
                                            <input type="number" step="0.5" name="quotas[<?= $q['id'] ?>][max_days]" class="form-control text-center fw-bold text-primary fs-5" value="<?= htmlspecialchars($q['max_days']) ?>" required min="0">
                                            <span class="input-group-text pe-3">วัน</span>
                                        </div>
                                    </td>
                                    <td class="pe-4 py-3 align-middle">
                                        <input type="text" name="quotas[<?= $q['id'] ?>][description]" class="form-control form-control-modern w-100 text-muted" value="<?= htmlspecialchars($q['description']) ?>" required>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">ไม่พบข้อมูลโควตาในระบบ กรุณาติดต่อ Super Admin</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top text-end py-4 px-4 rounded-bottom-4">
                    <button type="submit" class="btn btn-primary fw-bold shadow-sm rounded-pill px-4 py-2" style="font-size: 15px;">
                        <i class="bi bi-box-arrow-down me-2"></i> บันทึกการตั้งค่าฐานข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 🌟 ส่วนที่ 2: สรุประเบียบการลาจากเอกสาร PDF (11 ประเภท) -->
    <!-- ========================================== -->
    <div class="d-flex align-items-center mb-3 px-2">
        <div class="icon-box-sm bg-info bg-opacity-10 text-info me-3">
            <i class="bi bi-book-half"></i>
        </div>
        <h4 class="mb-0 fw-bold text-dark">สรุปสิทธิการลาตามประเภทบุคลากร ๑๑ ประเภท</h4>
    </div>
    
    <div class="card card-modern border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive custom-scrollbar">
                <table class="table table-bordered table-rule mb-0">
                    <thead>
                        <tr>
                            <th class="bg-light text-dark py-3" width="16%">ประเภทการลา</th>
                            <th class="bg-official text-primary py-3" width="28%">ข้าราชการ/พนักงานท้องถิ่น</th>
                            <th class="bg-mission text-success py-3" width="28%">พนักงานจ้างตามภารกิจ</th>
                            <th class="bg-general text-warning text-dark py-3" width="28%">พนักงานจ้างทั่วไป</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 1. ลาป่วย -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-bandaid text-danger me-1"></i> ๑. การลาป่วย</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ปีละไม่เกิน <strong>60 วันทำการ</strong> (ลาเพิ่มได้อีกไม่เกิน 60 วันกรณีจำเป็น)</li>
                                    <li>รอบประเมินลาได้ไม่เกิน 23 วัน</li>
                                    <li class="text-danger fw-bold">ลา 30 วันขึ้นไป ต้องมีใบรับรองแพทย์</li>
                                </ul>
                            </td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาได้ไม่เกิน <strong>60 วันทำการ</strong></li>
                                    <li>รอบประเมินลาได้ไม่เกิน 23 วัน</li>
                                    <li class="text-danger fw-bold">ลาป่วยเกิน 3 วัน ต้องมีใบรับรองแพทย์</li>
                                </ul>
                            </td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาป่วยได้ <strong>ไม่เกิน 15 วันทำการ</strong></li>
                                    <li>(ลดหลั่นตามอายุสัญญาจ้าง เช่น จ้าง 6 เดือน ลาได้ 8 วัน)</li>
                                    <li class="text-danger fw-bold">ลาป่วยเกิน 3 วัน ต้องมีใบรับรองแพทย์</li>
                                </ul>
                            </td>
                        </tr>

                        <!-- 2. ลาคลอดบุตร -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-gender-female text-primary me-1"></i> ๒. การลาคลอดบุตร</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาได้ <strong>ไม่เกิน 90 วัน/ครั้ง</strong></li>
                                    <li>ไม่ต้องมีใบรับรองแพทย์</li>
                                    <li>ลาต่อเนื่องได้อีก 30 วันทำการ (รวมในลากิจ)</li>
                                </ul>
                            </td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาได้ <strong>ไม่เกิน 90 วัน</strong> (นับรวมวันหยุด)</li>
                                    <li>ได้รับค่าตอบแทนระหว่างลาไม่เกิน 45 วัน</li>
                                    <li>มีสิทธิรับเงินสงเคราะห์จากประกันสังคม</li>
                                </ul>
                            </td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาได้ <strong>ไม่เกิน 90 วัน</strong> (นับรวมวันหยุด)</li>
                                    <li>ได้รับค่าตอบแทนระหว่างลาไม่เกิน 45 วัน</li>
                                    <li>มีสิทธิรับเงินสงเคราะห์จากประกันสังคม</li>
                                </ul>
                            </td>
                        </tr>

                        <!-- 3. ลากิจส่วนตัว -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-briefcase text-warning me-1"></i> ๓. การลากิจส่วนตัว</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ยื่นล่วงหน้า 3 วัน หรือยื่นวันแรกที่กลับมา</li>
                                    <li>ลากิจเพื่อเลี้ยงดูบุตรได้ <strong>45 วัน</strong> (ได้เงินเดือน)</li>
                                    <li>นับรวมลาป่วยไม่เกิน 23 วันในครึ่งปี (ถ้าเกินไม่ได้เลื่อนขั้น)</li>
                                </ul>
                            </td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาได้ <strong>ไม่เกิน 45 วันทำการ</strong></li>
                                    <li>(ปีแรกที่เข้าปฏิบัติงาน ลาได้ไม่เกิน 15 วัน)</li>
                                    <li>ยื่นล่วงหน้า และเมื่อได้รับอนุญาตจึงลาได้</li>
                                </ul>
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                        </tr>

                        <!-- 4. ลาพักผ่อน -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-brightness-high text-success me-1"></i> ๔. การลาพักผ่อน</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ได้ <strong>10 วันทำการ/ปี</strong></li>
                                    <li>อายุราชการ < 10 ปี สะสมได้ <strong>20 วัน</strong></li>
                                    <li>อายุราชการ >= 10 ปี สะสมได้ <strong>30 วัน</strong></li>
                                </ul>
                            </td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ได้ <strong>10 วันทำการ/ปี</strong></li>
                                    <li class="text-danger fw-bold">สะสมวันลาไม่ได้ (ใช้ปีต่อปี)</li>
                                    <li>ปีแรกต้องจ้างครบ 6 เดือนก่อนจึงมีสิทธิลา</li>
                                </ul>
                            </td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ได้ <strong>10 วันทำการ/ปี</strong></li>
                                    <li class="text-danger fw-bold">สะสมวันลาไม่ได้ (ใช้ปีต่อปี)</li>
                                    <li>ปีแรกต้องจ้างครบ 6 เดือนก่อนจึงมีสิทธิลา</li>
                                </ul>
                            </td>
                        </tr>

                        <!-- 5. ลาอุปสมบท/ฮัจย์ -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-moon-stars text-info me-1"></i> ๕. ลาอุปสมบท/ฮัจย์</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาได้ <strong>ไม่เกิน 120 วัน</strong></li>
                                    <li>ยื่นล่วงหน้าไม่น้อยกว่า 60 วัน</li>
                                    <li>ให้นายก อบจ. เป็นผู้อนุญาต</li>
                                </ul>
                            </td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาได้ <strong>ไม่เกิน 120 วัน</strong> โดยได้รับค่าจ้าง</li>
                                    <li>(เว้นแต่ปีแรกที่จ้าง จะไม่ได้ค่าจ้างระหว่างลา)</li>
                                    <li>ยื่นล่วงหน้าไม่น้อยกว่า 60 วัน</li>
                                </ul>
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่มีสิทธิลา
                            </td>
                        </tr>

                        <!-- 6. ลาเข้ารับการคัดเลือก/เตรียมพล -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-shield-check text-dark me-1"></i> ๖. ลาเข้ารับการคัดเลือก<br>หรือเตรียมพล</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>รายงานตัวล่วงหน้าไม่น้อยกว่า 48 ชั่วโมง</li>
                                    <li>กลับเข้าปฏิบัติราชการภายใน 7 วันหลังครบกำหนด</li>
                                </ul>
                            </td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาได้ตามระยะเวลาที่เข้าฝึก (ได้รับค่าจ้าง)</li>
                                    <li>(หากได้รับเงินจากกลาโหม จะไม่ได้ค่าจ้างระหว่างลา)</li>
                                </ul>
                            </td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาได้ตามระยะเวลาที่เข้าฝึก <strong>ไม่เกิน 30 วัน</strong></li>
                                    <li>(หากได้รับเงินจากกลาโหม จะไม่ได้ค่าจ้างระหว่างลา)</li>
                                </ul>
                            </td>
                        </tr>

                        <!-- 7. ลาไปศึกษา ฝึกอบรม ดูงาน -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-mortarboard text-primary me-1"></i> ๗. ลาไปศึกษา ฝึกอบรม<br>ดูงาน หรือวิจัย</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ในประเทศ: นายก อบจ. เป็นผู้อนุญาต</li>
                                    <li>ต่างประเทศ: ผู้ว่าราชการจังหวัด เป็นผู้อนุญาต</li>
                                </ul>
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                        </tr>

                        <!-- 8. ลาไปปฏิบัติงานองค์การระหว่างประเทศ -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-globe text-success me-1"></i> ๘. ลาไปปฏิบัติงาน<br>ในองค์การระหว่างประเทศ</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาได้ <strong>ไม่เกิน 1 ปี</strong> (ผู้ว่าฯ อนุญาต)</li>
                                    <li>กลับมารายงานตัวภายใน 15 วัน</li>
                                </ul>
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                        </tr>

                        <!-- 9. ลาติดตามคู่สมรส -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-people text-info me-1"></i> ๙. การลาติดตามคู่สมรส</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>มีสิทธิลาได้ <strong>2 ปี แต่ไม่เกิน 4 ปี</strong></li>
                                    <li>ในประเทศ: นายก อบจ. อนุญาต</li>
                                    <li>ต่างประเทศ: ผู้ว่าราชการจังหวัด อนุญาต</li>
                                </ul>
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                        </tr>

                        <!-- 10. ไปช่วยเหลือภริยาคลอดบุตร -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-balloon-heart text-secondary me-1"></i> ๑๐. ลาไปช่วยเหลือภริยา<br>ที่คลอดบุตร</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>ลาติดต่อกันได้ <strong>ไม่เกิน 15 วันทำการ</strong></li>
                                    <li>เสนอใบลาก่อน/ในวันลา ภายใน 90 วัน</li>
                                </ul>
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                        </tr>

                        <!-- 11. ลาไปฟื้นฟูสมรรถภาพด้านอาชีพ -->
                        <tr>
                            <td class="fw-bold bg-light"><i class="bi bi-heart-pulse text-danger me-1"></i> ๑๑. ลาไปฟื้นฟู<br>สมรรถภาพด้านอาชีพ</td>
                            <td>
                                <ul class="mb-0 ps-3">
                                    <li>กรณีบาดเจ็บ/พิการ จากการปฏิบัติหน้าที่</li>
                                    <li>ลาได้ตามระยะเวลาหลักสูตร แต่ <strong>ไม่เกิน 12 เดือน</strong></li>
                                </ul>
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                            <td class="bg-danger bg-opacity-10 text-center align-middle text-danger fw-bold">
                                <i class="bi bi-x-circle fs-5 d-block mb-1"></i> ไม่สามารถลาได้
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white text-muted small py-3">
                <i class="bi bi-info-circle text-primary me-1"></i> <strong>หมายเหตุ:</strong> ข้อมูลนี้สรุปจากคู่มือการลาของบุคลากรในสังกัดองค์การบริหารส่วนจังหวัด ระบบ Roster Pro ได้ออกแบบการหักโควตาและการแจ้งเตือนอ้างอิงตามระเบียบนี้
            </div>
        </div>
    </div>
</div>