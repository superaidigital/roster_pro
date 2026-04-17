<?php
// ที่อยู่ไฟล์: views/settings/hospital.php
// ชื่อไฟล์: hospital.php

// 🌟 กำหนดสิทธิ์การแก้ไขข้อมูล: เฉพาะ ADMIN และ DIRECTOR เท่านั้นที่แก้ไขข้อมูล รพ.สต. ได้
$can_edit = false;
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'ADMIN' || $_SESSION['user']['role'] === 'DIRECTOR') {
        $can_edit = true;
    }
}

// ตรวจสอบข้อมูล รพ.สต. ถ้าไม่มีให้ใช้ค่าเริ่มต้น
if (empty($hospital)) {
    $hospital = [
        'id' => '', 'hospital_code' => '', 'name' => 'ยังไม่ได้ตั้งชื่อ รพ.สต.', 'address' => '',
        'sub_district' => '', 'district' => '', 'province' => '', 'zipcode' => '',
        'latitude' => '', 'longitude' => '', 'hospital_size' => 'S', 'phone' => '', 'email' => '',
        'morning_shift' => '08:30 - 16:30', 'afternoon_shift' => '16:30 - 00:30', 'night_shift' => '00:30 - 08:30',
        'director_name' => '', 'director_position' => ''
    ];
}

// สร้างที่อยู่แบบเต็มสำหรับแสดงผลในนามบัตร
$full_address = [];
if(!empty($hospital['address'])) $full_address[] = $hospital['address'];
if(!empty($hospital['sub_district'])) $full_address[] = "ต." . $hospital['sub_district'];
if(!empty($hospital['district'])) $full_address[] = "อ." . $hospital['district'];
if(!empty($hospital['province'])) $full_address[] = "จ." . $hospital['province'];
if(!empty($hospital['zipcode'])) $full_address[] = $hospital['zipcode'];
$full_address_str = !empty($full_address) ? implode(' ', $full_address) : 'ยังไม่ได้ระบุที่อยู่';

// 🌟 ดึงโลโก้หรือตัวอักษรย่อ (ผ่าน Model ที่ถูกส่งมาจาก Controller)
$logoSrc = isset($hospitalModel) ? $hospitalModel->getHospitalLogo($hospital) : 'public/images/default-logo.png';
?>

<!-- 🌟 นำเข้า CSS ของ Leaflet (แผนที่) และ jquery.Thailand.js -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dist/jquery.Thailand.min.css">

<div class="w-100 bg-light p-3 p-md-4 min-vh-100">
    <div class="container-fluid max-w-7xl mx-auto">
        
        <div class="mb-4">
            <h4 class="fw-bold text-dark mb-1">ข้อมูลพื้นฐานหน่วยบริการ</h4>
            <p class="text-muted mb-0" style="font-size: 14px;">จัดการข้อมูล รพ.สต., พิกัดที่ตั้ง และตั้งค่าเวลาเข้า-ออกเวร</p>
        </div>

        <!-- 🌟 แสดงแจ้งเตือน (Alerts) -->
        <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <strong>สำเร็จ!</strong> <?= $_SESSION['success_msg'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>เกิดข้อผิดพลาด!</strong> <?= $_SESSION['error_msg'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <?php if(!$can_edit): ?>
            <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center" role="alert">
                <i class="bi bi-shield-lock-fill fs-4 me-3 text-warning"></i>
                <div>
                    <strong>อ่านได้อย่างเดียว:</strong> คุณกำลังดูข้อมูลในโหมดอ่านเท่านั้น สิทธิ์ในการแก้ไขข้อมูลสงวนไว้สำหรับระดับ "ผู้อำนวยการ" และ "ส่วนกลาง"
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- ============================================
                 ด้านซ้าย: นามบัตร/โปรไฟล์ รพ.สต. 
            ============================================= -->
            <div class="col-xl-4 col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden sticky-top" style="top: 20px;">
                    <div class="bg-primary bg-gradient p-4 text-center text-white position-relative">
                        <i class="bi bi-building" style="font-size: 4rem; opacity: 0.8;"></i>
                    </div>
                    <div class="card-body text-center pt-4 pb-4">
                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($hospital['name']) ?></h5>
                        <p class="text-muted small mb-2">รหัสหน่วยบริการ: <span class="badge bg-light text-dark border"><?= !empty($hospital['hospital_code']) ? htmlspecialchars($hospital['hospital_code']) : 'ไม่มีข้อมูล' ?></span></p>
                        
                        <div class="mb-3">
                            <span class="badge bg-info text-white">ขนาด รพ.สต.: Size <?= !empty($hospital['hospital_size']) ? htmlspecialchars($hospital['hospital_size']) : 'S' ?></span>
                        </div>
                        
                        <!-- 🌟 ดึงข้อมูลผู้อำนวยการ รพ.สต. มาแสดง -->
                        <div class="p-3 bg-light rounded-3 text-start mb-3 border">
                            <div class="d-flex align-items-center mb-1">
                                <i class="bi bi-person-badge text-primary me-2 fs-5"></i>
                                <span class="fw-bold text-dark" style="font-size: 14px;">ผู้บริหารหน่วยงาน</span>
                            </div>
                            <div class="ps-4">
                                <div class="fw-bold text-dark" style="font-size: 14px;">
                                    <?= !empty($hospital['director_name']) ? htmlspecialchars($hospital['director_name']) : 'ยังไม่ได้ระบุ (รอดึงข้อมูลจากระบบ)' ?>
                                </div>
                                <div class="text-muted" style="font-size: 12px;">
                                    <?= !empty($hospital['director_position']) ? htmlspecialchars($hospital['director_position']) : 'ผู้อำนวยการ รพ.สต.' ?>
                                </div>
                            </div>
                        </div>

                        <hr class="text-muted opacity-25">
                        
                        <div class="d-flex flex-column gap-2 text-start mt-3">
                            <div class="d-flex text-muted" style="font-size: 14px;">
                                <i class="bi bi-telephone-fill me-3 text-success mt-1"></i>
                                <span><?= !empty($hospital['phone']) ? htmlspecialchars($hospital['phone']) : '-' ?></span>
                            </div>
                            <div class="d-flex text-muted" style="font-size: 14px;">
                                <i class="bi bi-envelope-fill me-3 text-warning mt-1"></i>
                                <span><?= !empty($hospital['email']) ? htmlspecialchars($hospital['email']) : '-' ?></span>
                            </div>
                            <div class="d-flex text-muted" style="font-size: 14px;">
                                <i class="bi bi-geo-alt-fill me-3 text-danger mt-1"></i>
                                <span><?= htmlspecialchars($full_address_str) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================
                 ด้านขวา: ฟอร์มแก้ไขข้อมูล 
            ============================================= -->
            <div class="col-xl-8 col-lg-7">
                <form action="index.php?c=settings&a=save_hospital" method="POST" enctype="multipart/form-data">
                    
                    <!-- ส่วนที่ 1: ข้อมูลทั่วไป -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-white border-bottom p-3 px-4">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-square text-primary me-2"></i> ข้อมูลทั่วไป & ขนาดหน่วยบริการ</h6>
                        </div>
                        <div class="card-body p-4">
                            
                            <!-- 🌟 Logo Section (แสดงโลโก้หรืออักษรย่อออโต้) -->
                            <div class="text-center mb-4 pb-4 border-bottom">
                                <div class="position-relative d-inline-block mb-3">
                                    <img id="logoPreview" src="<?= $logoSrc ?>" 
                                         alt="Logo" class="rounded-circle object-fit-cover shadow-sm bg-white" 
                                         style="width: 120px; height: 120px; border: 4px solid #f8fafc;">
                                    
                                    <?php if($can_edit): ?>
                                    <label for="logoUpload" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 35px; height: 35px; cursor: pointer; transition: 0.2s;">
                                        <i class="bi bi-camera-fill"></i>
                                    </label>
                                    <input type="file" name="logo" id="logoUpload" class="d-none" accept="image/png, image/jpeg, image/jpg">
                                    <?php endif; ?>
                                </div>
                                <?php if($can_edit): ?>
                                <p class="text-muted small mb-0">คลิกที่ไอคอนกล้องเพื่ออัปโหลดโลโก้ใหม่ (แนะนำ 300x300px)</p>
                                <?php endif; ?>
                            </div>

                            <div class="row g-3">
                                <!-- ฟิลด์ซ่อน ID รพ.สต. -->
                                <input type="hidden" name="id" value="<?= htmlspecialchars($hospital['id'] ?? '') ?>">
                                
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-secondary small">รหัสหน่วยบริการ (5 หลัก)</label>
                                    <input type="text" name="hospital_code" class="form-control <?= $_SESSION['user']['role'] !== 'ADMIN' ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['hospital_code'] ?? '') ?>" placeholder="เช่น 04123" required <?= $_SESSION['user']['role'] !== 'ADMIN' ? 'readonly' : '' ?>>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold text-secondary small">ชื่อ รพ.สต.</label>
                                    <input type="text" name="name" class="form-control <?= $_SESSION['user']['role'] !== 'ADMIN' ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['name'] ?? '') ?>" required <?= $_SESSION['user']['role'] !== 'ADMIN' ? 'readonly' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">เบอร์โทรศัพท์ติดต่อ</label>
                                    <input type="text" name="phone" class="form-control <?= !$can_edit ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['phone'] ?? '') ?>" placeholder="045-XXX-XXX" <?= !$can_edit ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">อีเมล</label>
                                    <input type="email" name="email" class="form-control <?= !$can_edit ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['email'] ?? '') ?>" placeholder="example@email.com" <?= !$can_edit ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">ขนาด รพ.สต.</label>
                                    <select name="hospital_size" class="form-select <?= !$can_edit ? 'bg-light border-0' : '' ?>" <?= !$can_edit ? 'disabled' : '' ?>>
                                        <option value="S" <?= (($hospital['hospital_size'] ?? '') == 'S') ? 'selected' : '' ?>>ขนาดเล็ก (S)</option>
                                        <option value="M" <?= (($hospital['hospital_size'] ?? '') == 'M') ? 'selected' : '' ?>>ขนาดกลาง (M)</option>
                                        <option value="L" <?= (($hospital['hospital_size'] ?? '') == 'L') ? 'selected' : '' ?>>ขนาดใหญ่ (L)</option>
                                        <option value="XL" <?= (($hospital['hospital_size'] ?? '') == 'XL') ? 'selected' : '' ?>>ขนาดใหญ่พิเศษ (XL)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 🌟 ส่วนที่ 1.5: ข้อมูลผู้บริหาร (แสดงผลเท่านั้น) -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-white border-bottom p-3 px-4 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-person-badge text-primary me-2"></i> ข้อมูลผู้บริหาร (สำหรับออกรายงาน)</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="alert alert-light border shadow-sm mb-3">
                                <i class="bi bi-info-circle-fill text-info me-2"></i> ข้อมูลส่วนนี้จะถูก <strong>อัปเดตอัตโนมัติ</strong> จากรายชื่อเจ้าหน้าที่ในระบบที่มีระดับสิทธิ์เป็น <code>DIRECTOR</code>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">ชื่อ-นามสกุล ผอ.รพ.สต.</label>
                                    <!-- ไม่ใช้ name="" เพื่อไม่ให้ส่งไปกับฟอร์ม เพราะเราดึงจากตาราง users อัตโนมัติ -->
                                    <input type="text" class="form-control bg-light border-0" value="<?= htmlspecialchars($hospital['director_name'] ?? 'ยังไม่ได้ระบุ') ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">ตำแหน่ง ผอ.รพ.สต.</label>
                                    <!-- ไม่ใช้ name="" เพื่อไม่ให้ส่งไปกับฟอร์ม -->
                                    <input type="text" class="form-control bg-light border-0" value="<?= htmlspecialchars($hospital['director_position'] ?? 'ผู้อำนวยการ รพ.สต.') ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 2: ที่อยู่ (Auto-complete) -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-white border-bottom p-3 px-4">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-geo-alt-fill text-danger me-2"></i> ข้อมูลที่ตั้ง (มีระบบค้นหาอัตโนมัติ)</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold text-secondary small">บ้านเลขที่ / หมู่ / ถนน</label>
                                    <input type="text" name="address" class="form-control <?= !$can_edit ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['address'] ?? '') ?>" placeholder="เช่น 123 ม.4 ถ.สุขุมวิท" <?= !$can_edit ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">ตำบล / แขวง</label>
                                    <input type="text" id="sub_district" name="sub_district" class="form-control <?= !$can_edit ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['sub_district'] ?? '') ?>" <?= !$can_edit ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">อำเภอ / เขต</label>
                                    <input type="text" id="district" name="district" class="form-control <?= !$can_edit ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['district'] ?? '') ?>" <?= !$can_edit ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">จังหวัด</label>
                                    <input type="text" id="province" name="province" class="form-control <?= !$can_edit ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['province'] ?? '') ?>" <?= !$can_edit ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">รหัสไปรษณีย์</label>
                                    <input type="text" id="zipcode" name="zipcode" class="form-control <?= !$can_edit ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['zipcode'] ?? '') ?>" <?= !$can_edit ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 3: พิกัดแผนที่ (Map) -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-white border-bottom p-3 px-4 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-map-fill text-success me-2"></i> พิกัดแผนที่ (Latitude / Longitude)</h6>
                            <?php if($can_edit): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-get-location"><i class="bi bi-crosshair"></i> ดึงพิกัดปัจจุบัน</button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">ละติจูด (Latitude)</label>
                                    <input type="text" id="latitude" name="latitude" class="form-control <?= !$can_edit ? 'bg-light border-0' : 'bg-white' ?>" value="<?= htmlspecialchars($hospital['latitude'] ?? '') ?>" <?= !$can_edit ? 'readonly' : '' ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-secondary small">ลองจิจูด (Longitude)</label>
                                    <input type="text" id="longitude" name="longitude" class="form-control <?= !$can_edit ? 'bg-light border-0' : 'bg-white' ?>" value="<?= htmlspecialchars($hospital['longitude'] ?? '') ?>" <?= !$can_edit ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <!-- กล่องแสดงแผนที่ -->
                            <div id="map" class="rounded-3 border shadow-sm" style="height: 300px; width: 100%; z-index: 1;"></div>
                            <small class="text-muted mt-2 d-block">
                                <?= $can_edit ? '<i class="bi bi-info-circle"></i> คุณสามารถคลิกบนแผนที่ หรือลากหมุดสีแดงเพื่อเปลี่ยนพิกัดได้' : '' ?>
                            </small>
                        </div>
                    </div>

                    <!-- ส่วนที่ 4: ตั้งค่าเวลาปฏิบัติงาน -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-white border-bottom p-3 px-4">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i> ตั้งค่าเวลาปฏิบัติงาน (ตารางเวร)</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="color: #ea580c; font-size: 13px;"><i class="bi bi-brightness-high me-1"></i> เวรเช้า</label>
                                    <input type="text" name="morning_shift" class="form-control <?= !$can_edit ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['morning_shift'] ?? '') ?>" placeholder="เช่น 08:30 - 16:30" <?= !$can_edit ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" style="color: #4f46e5; font-size: 13px;"><i class="bi bi-moon me-1"></i> เวรบ่าย</label>
                                    <input type="text" name="afternoon_shift" class="form-control <?= !$can_edit ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['afternoon_shift'] ?? '') ?>" placeholder="เช่น 16:30 - 00:30" <?= !$can_edit ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark" style="font-size: 13px;"><i class="bi bi-stars me-1"></i> เวรดึก</label>
                                    <input type="text" name="night_shift" class="form-control <?= !$can_edit ? 'bg-light border-0' : '' ?>" value="<?= htmlspecialchars($hospital['night_shift'] ?? '') ?>" placeholder="เช่น 00:30 - 08:30" <?= !$can_edit ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ปุ่มบันทึก -->
                    <?php if($can_edit): ?>
                    <div class="d-flex justify-content-end mb-5">
                        <button type="submit" class="btn btn-primary fw-bold px-5 shadow-sm rounded-pill">
                            <i class="bi bi-save me-2"></i> บันทึกข้อมูล
                        </button>
                    </div>
                    <?php endif; ?>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- 🌟 นำเข้าสคริปต์ jQuery และ Plugins -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dependencies/JQL.min.js"></script>
<script src="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dependencies/typeahead.bundle.js"></script>
<script src="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dist/jquery.Thailand.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const canEdit = <?= $can_edit ? 'true' : 'false' ?>;

    // ==========================================
    // 1. ระบบ Auto-complete ที่อยู่ (jquery.Thailand.js)
    // ==========================================
    if (canEdit) {
        $.Thailand({
            database: 'https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/database/db.json',
            $district: $('#sub_district'), // ตำบล
            $amphoe:   $('#district'),     // อำเภอ
            $province: $('#province'),     // จังหวัด
            $zipcode:  $('#zipcode'),      // รหัสไปรษณีย์
        });
    }

    // ==========================================
    // 2. ระบบแผนที่ (Leaflet.js)
    // ==========================================
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    
    // ตั้งค่าพิกัดเริ่มต้น (ถ้าไม่มีข้อมูลให้ไปที่ใจกลางประเทศไทย)
    let initialLat = parseFloat(latInput.value) || 15.2287; // Default: อุบลราชธานี
    let initialLng = parseFloat(lngInput.value) || 104.8564;

    // สร้างแผนที่
    const map = L.map('map').setView([initialLat, initialLng], 13);
    
    // ดึงภาพแผนที่จาก OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // สร้างหมุด (Marker)
    const marker = L.marker([initialLat, initialLng], {
        draggable: canEdit // ให้ลากได้เฉพาะตอนมีสิทธิ์แก้
    }).addTo(map);

    if (canEdit) {
        // เมื่อลากหมุดเสร็จ ให้อัปเดตค่าในช่อง Input
        marker.on('dragend', function(e) {
            const coords = e.target.getLatLng();
            latInput.value = coords.lat.toFixed(6);
            lngInput.value = coords.lng.toFixed(6);
        });

        // เมื่อคลิกบนแผนที่ ให้ย้ายหมุดมาที่จุดนั้นและอัปเดต Input
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            latInput.value = e.latlng.lat.toFixed(6);
            lngInput.value = e.latlng.lng.toFixed(6);
        });

        // ฟังก์ชันดึงพิกัดจาก GPS ของอุปกรณ์
        document.getElementById('btn-get-location').addEventListener('click', function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    map.setView([lat, lng], 15);
                    marker.setLatLng([lat, lng]);
                    
                    latInput.value = lat.toFixed(6);
                    lngInput.value = lng.toFixed(6);
                }, function(error) {
                    alert('ไม่สามารถดึงตำแหน่งปัจจุบันได้ กรุณาอนุญาตการเข้าถึง Location ในเบราว์เซอร์ของคุณ');
                });
            } else {
                alert('เบราว์เซอร์ของคุณไม่รองรับการดึงตำแหน่ง (Geolocation)');
            }
        });
        
        // ==========================================
        // 3. Script สำหรับแสดงรูปภาพตัวอย่างทันทีเมื่อเลือกไฟล์ (Logo Preview)
        // ==========================================
        const logoUpload = document.getElementById('logoUpload');
        const logoPreview = document.getElementById('logoPreview');

        if (logoUpload && logoPreview) {
            logoUpload.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    // ตรวจสอบว่าเป็นไฟล์รูปภาพ
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            logoPreview.src = e.target.result;
                        }
                        
                        reader.readAsDataURL(file);
                    } else {
                        alert('กรุณาอัปโหลดไฟล์รูปภาพเท่านั้น (PNG, JPG, JPEG)');
                        logoUpload.value = ''; // ล้างค่าที่เลือกผิด
                    }
                }
            });
        }
    }
});
</script>

<style>
    /* แก้ไขปัญหากล่อง Auto-complete ของ Typeahead ทับกับ Bootstrap */
    .tt-menu {
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        width: 100%;
        margin-top: 5px;
        padding: 5px 0;
        z-index: 1000 !important;
    }
    .tt-suggestion {
        padding: 8px 15px;
        cursor: pointer;
        font-size: 14px;
    }
    .tt-suggestion:hover, .tt-cursor {
        background-color: #f0f4f8;
        color: #0d6efd;
    }
</style>