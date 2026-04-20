<?php
// ที่อยู่ไฟล์: views/settings/hospital.php
$hospital = $hospital ?? [];
$user_role = strtoupper($_SESSION['user']['role'] ?? '');
$isAdmin = in_array($user_role, ['SUPERADMIN', 'ADMIN']);
$isDirector = ($user_role === 'DIRECTOR');
$my_hospital_id = $_SESSION['user']['hospital_id'] ?? null;

// 🛡️ ป้องกันความปลอดภัยขั้นสูงสุด: ถ้า ผอ. พยายามเข้าหน้าตั้งค่าของ รพ. อื่นตรงๆ ให้เตะกลับ
if ($isDirector && isset($hospital['id']) && $hospital['id'] != $my_hospital_id) {
    $_SESSION['error_msg'] = "⛔ ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์ตั้งค่าข้อมูลของหน่วยบริการอื่น";
    echo "<script>window.location.replace('index.php?c=hospitals');</script>";
    exit;
}

// ดึงโลโก้จาก HospitalModel (ถ้ามี) หรือใช้รูป Default
$logo_url = isset($hospitalModel) && !empty($hospital) ? $hospitalModel->getHospitalLogo($hospital) : 'assets/images/default_hospital.png';

// Helper Function: แยกเวลาเข้าและออก
function getShiftTimes($shiftStr, $defaultStart, $defaultEnd) {
    if (empty($shiftStr)) return [$defaultStart, $defaultEnd];
    $parts = explode('-', str_replace(' ', '', $shiftStr));
    if (count($parts) == 2) return [$parts[0], $parts[1]];
    return [$defaultStart, $defaultEnd];
}

list($m_start, $m_end) = getShiftTimes($hospital['morning_shift'] ?? '', '08:00', '16:00');
list($a_start, $a_end) = getShiftTimes($hospital['afternoon_shift'] ?? '', '16:00', '00:00');
list($n_start, $n_end) = getShiftTimes($hospital['night_shift'] ?? '', '00:00', '08:00');
?>

<style>
    .card-modern { border: none; border-radius: 1.25rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: #ffffff; }
    .form-control, .form-select { border-radius: 0.75rem; padding: 0.6rem 1rem; border: 1px solid #e2e8f0; transition: border-color 0.2s, box-shadow 0.2s; }
    .form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
    .form-label { font-size: 13.5px; font-weight: 600; color: #475569; margin-bottom: 0.4rem; }
    
    .logo-preview { width: 130px; height: 130px; border-radius: 1.5rem; object-fit: cover; border: 4px solid #f8fafc; box-shadow: 0 8px 20px rgba(0,0,0,0.08); background-color: #f1f5f9; }
    .cursor-pointer { cursor: pointer; }
    
    .shift-card { border-radius: 1rem; padding: 1.5rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .shift-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.06); }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100 d-flex flex-column" style="background-color: #f4f6f9;">

    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 50px; height: 50px;">
                <i class="bi bi-building-gear fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold text-dark mb-0">ข้อมูลและตั้งค่าหน่วยบริการ</h4>
                <p class="text-muted mb-0" style="font-size: 13px;">จัดการชื่อ รพ.สต., พิกัดตำแหน่งบนแผนที่ และเวลาทำงาน</p>
            </div>
        </div>
        <div>
            <?php 
            // 🌟 ปลดล็อก: ให้ ผอ. เห็นปุ่มย้อนกลับไปหน้ารวมได้
            $canGoBack = $isAdmin || $isDirector;
            if(isset($_GET['id']) && $canGoBack): 
            ?>
            <a href="index.php?c=hospitals" class="btn btn-light border shadow-sm rounded-pill fw-bold px-4">
                <i class="bi bi-arrow-left me-1"></i> กลับหน้ารวม รพ.สต.
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert border-0 bg-success bg-opacity-10 text-success rounded-4 p-3 shadow-sm border-start border-success border-4 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success_msg'] ?>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert border-0 bg-danger bg-opacity-10 text-danger rounded-4 p-3 shadow-sm border-start border-danger border-4 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error_msg'] ?>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <form action="index.php?c=settings&a=save_hospital" method="POST" enctype="multipart/form-data" id="hospitalSettingsForm">
        <!-- Hidden Inputs -->
        <input type="hidden" name="id" value="<?= htmlspecialchars($hospital['id'] ?? '') ?>">
        <?php if(isset($_GET['id'])): ?>
            <input type="hidden" name="redirect_to" value="hospitals">
        <?php endif; ?>

        <!-- Hidden Inputs for Merged Shift Times -->
        <input type="hidden" name="morning_shift" id="morning_shift">
        <input type="hidden" name="afternoon_shift" id="afternoon_shift">
        <input type="hidden" name="night_shift" id="night_shift">

        <div class="row g-4">
            <!-- 🌟 คอลัมน์ซ้าย: ข้อมูลพื้นฐาน -->
            <div class="col-xl-4 col-lg-5">
                <div class="card card-modern h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                        <i class="bi bi-info-circle-fill text-primary fs-5 me-2"></i>
                        <h6 class="fw-bold mb-0 text-dark">ข้อมูลพื้นฐาน</h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- ส่วนอัปโหลดโลโก้ -->
                        <div class="text-center mb-4 pb-3 border-bottom">
                            <img src="<?= $logo_url ?>" alt="Hospital Logo" class="logo-preview mb-3" id="logoPreview">
                            <div>
                                <label for="logoInput" class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold shadow-sm cursor-pointer">
                                    <i class="bi bi-camera-fill me-1"></i> เปลี่ยนรูปตราสัญลักษณ์
                                </label>
                                <input type="file" name="logo" id="logoInput" class="d-none" accept="image/jpeg, image/png">
                                <div class="text-muted mt-2" style="font-size: 11.5px;">รองรับ JPG, PNG ขนาดไม่เกิน 2MB</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">รหัสหน่วยบริการ (5 หลัก) <span class="text-danger">*</span></label>
                            <input type="text" name="hospital_code" class="form-control font-monospace" value="<?= htmlspecialchars($hospital['hospital_code'] ?? '') ?>" placeholder="เช่น 04875" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ชื่อหน่วยบริการ (รพ.สต. / โรงพยาบาล) <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control fw-bold text-dark" value="<?= htmlspecialchars($hospital['name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ขนาดหน่วยบริการ</label>
                            <select name="hospital_size" class="form-select bg-light">
                                <?php $size = $hospital['hospital_size'] ?? 'S'; ?>
                                <option value="S" <?= $size == 'S' ? 'selected' : '' ?>>ขนาด S (รพ.สต. ขนาดเล็ก)</option>
                                <option value="M" <?= $size == 'M' ? 'selected' : '' ?>>ขนาด M (รพ.สต. ขนาดกลาง)</option>
                                <option value="L" <?= $size == 'L' ? 'selected' : '' ?>>ขนาด L (รพ.สต. ขนาดใหญ่)</option>
                                <option value="XL" <?= $size == 'XL' ? 'selected' : '' ?>>ขนาด XL (โรงพยาบาลศูนย์/ทั่วไป)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🌟 คอลัมน์ขวา: ที่อยู่ และ พิกัด GPS -->
            <div class="col-xl-8 col-lg-7">
                <div class="card card-modern h-100">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                        <i class="bi bi-geo-alt-fill text-success fs-5 me-2"></i>
                        <h6 class="fw-bold mb-0 text-dark">ช่องทางการติดต่อ และที่ตั้ง</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label"><i class="bi bi-telephone text-muted me-1"></i> เบอร์โทรศัพท์ติดต่อ</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($hospital['phone'] ?? '') ?>" placeholder="เช่น 045-xxx-xxx">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="bi bi-envelope text-muted me-1"></i> อีเมล (Email)</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($hospital['email'] ?? '') ?>" placeholder="example@email.com">
                            </div>
                        </div>
                        
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3 mt-2" style="font-size: 14px;">ที่อยู่สำหรับจัดส่งเอกสาร</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">บ้านเลขที่ / หมู่ / ถนน</label>
                                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($hospital['address'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ตำบล</label>
                                <input type="text" name="sub_district" class="form-control" value="<?= htmlspecialchars($hospital['sub_district'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">อำเภอ</label>
                                <input type="text" name="district" class="form-control" value="<?= htmlspecialchars($hospital['district'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">จังหวัด</label>
                                <input type="text" name="province" class="form-control" value="<?= htmlspecialchars($hospital['province'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">รหัสไปรษณีย์</label>
                                <input type="text" name="zipcode" class="form-control font-monospace" value="<?= htmlspecialchars($hospital['zipcode'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- 📍 พิกัดแผนที่ -->
                        <div class="mt-4 bg-light p-4 rounded-4 border border-1 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div class="fw-bold text-dark">
                                    <i class="bi bi-geo text-danger fs-5 me-1"></i> พิกัด GPS แผนที่ (Latitude, Longitude)
                                </div>
                                <button type="button" class="btn btn-sm btn-danger fw-bold rounded-pill shadow-sm px-3" onclick="getCurrentLocation()">
                                    <i class="bi bi-crosshair me-1"></i> ดึงพิกัดอัตโนมัติ
                                </button>
                            </div>
                            <p class="text-muted mb-3" style="font-size: 12px;">จำเป็นสำหรับการเช็คอิน-เช็คเอาต์ผ่านมือถือ หรือการคำนวณระยะทาง</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" name="latitude" id="latInput" class="form-control font-monospace" placeholder="Latitude" value="<?= htmlspecialchars($hospital['latitude'] ?? '') ?>">
                                        <label>ละติจูด (Latitude)</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" name="longitude" id="lngInput" class="form-control font-monospace" placeholder="Longitude" value="<?= htmlspecialchars($hospital['longitude'] ?? '') ?>">
                                        <label>ลองจิจูด (Longitude)</label>
                                    </div>
                                </div>
                            </div>
                            <div id="geoMessage" class="small mt-2 fw-medium"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🌟 แถวล่างเต็ม: ตั้งค่าเวลาเข้า-ออกเวร (Shift Timing) -->
            <div class="col-12">
                <div class="card card-modern">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                        <i class="bi bi-clock-fill text-warning fs-5 me-2"></i>
                        <h6 class="fw-bold mb-0 text-dark">ตั้งค่าเวลาเข้า-ออกเวร (Shift Timing)</h6>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted small mb-4">เวลาปฏิบัติงานเหล่านี้จะถูกนำไปใช้แสดงในตารางเวร และอ้างอิงในการบันทึกเวลาเข้า-ออกของบุคลากร</p>

                        <div class="row g-4">
                            <!-- เวรเช้า -->
                            <div class="col-lg-4 col-md-6">
                                <div class="shift-card bg-primary bg-opacity-10 border border-primary border-opacity-25">
                                    <div class="d-flex align-items-center mb-3 pb-2 border-bottom border-primary border-opacity-25">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 45px; height: 45px;">
                                            <i class="bi bi-sun-fill fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bolder text-primary mb-0 fs-5">เวรเช้า (M)</h6>
                                            <small class="text-primary opacity-75">Morning Shift</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="flex-grow-1">
                                            <label class="form-label small text-muted mb-1 fw-bold">เข้างาน</label>
                                            <input type="time" id="m_s" class="form-control text-center fw-bold fs-6" value="<?= $m_start ?>" required>
                                        </div>
                                        <div class="pt-3 fw-bold text-muted opacity-50"><i class="bi bi-arrow-right"></i></div>
                                        <div class="flex-grow-1">
                                            <label class="form-label small text-muted mb-1 fw-bold">ออกงาน</label>
                                            <input type="time" id="m_e" class="form-control text-center fw-bold fs-6" value="<?= $m_end ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- เวรบ่าย -->
                            <div class="col-lg-4 col-md-6">
                                <div class="shift-card bg-warning bg-opacity-10 border border-warning border-opacity-25">
                                    <div class="d-flex align-items-center mb-3 pb-2 border-bottom border-warning border-opacity-25">
                                        <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 45px; height: 45px;">
                                            <i class="bi bi-sunset-fill fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bolder text-dark mb-0 fs-5">เวรบ่าย (A)</h6>
                                            <small class="text-dark opacity-75">Afternoon Shift</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="flex-grow-1">
                                            <label class="form-label small text-muted mb-1 fw-bold">เข้างาน</label>
                                            <input type="time" id="a_s" class="form-control text-center fw-bold fs-6" value="<?= $a_start ?>" required>
                                        </div>
                                        <div class="pt-3 fw-bold text-muted opacity-50"><i class="bi bi-arrow-right"></i></div>
                                        <div class="flex-grow-1">
                                            <label class="form-label small text-muted mb-1 fw-bold">ออกงาน</label>
                                            <input type="time" id="a_e" class="form-control text-center fw-bold fs-6" value="<?= $a_end ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- เวรดึก -->
                            <div class="col-lg-4 col-md-6">
                                <div class="shift-card bg-dark bg-opacity-10 border border-dark border-opacity-25">
                                    <div class="d-flex align-items-center mb-3 pb-2 border-bottom border-dark border-opacity-25">
                                        <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 45px; height: 45px;">
                                            <i class="bi bi-moon-stars-fill fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bolder text-dark mb-0 fs-5">เวรดึก (N)</h6>
                                            <small class="text-dark opacity-75">Night Shift</small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="flex-grow-1">
                                            <label class="form-label small text-muted mb-1 fw-bold">เข้างาน</label>
                                            <input type="time" id="n_s" class="form-control text-center fw-bold fs-6" value="<?= $n_start ?>" required>
                                        </div>
                                        <div class="pt-3 fw-bold text-muted opacity-50"><i class="bi bi-arrow-right"></i></div>
                                        <div class="flex-grow-1">
                                            <label class="form-label small text-muted mb-1 fw-bold">ออกงาน</label>
                                            <input type="time" id="n_e" class="form-control text-center fw-bold fs-6" value="<?= $n_end ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submit Button (Sticky Bottom for Mobile) -->
            <div class="col-12 mb-5">
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill shadow" onclick="prepareSubmit()" style="letter-spacing: 1px;">
                    <i class="bi bi-save-fill me-2"></i> บันทึกข้อมูลและตั้งค่า
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// 1. ระบบพรีวิวรูปภาพก่อนอัปโหลด
document.getElementById('logoInput').addEventListener('change', function(e) {
    if(e.target.files && e.target.files[0]) {
        let reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logoPreview').src = e.target.result;
        }
        reader.readAsDataURL(e.target.files[0]);
    }
});

// 2. ระบบค้นหาพิกัดอัตโนมัติ (HTML5 Geolocation)
function getCurrentLocation() {
    const msgEl = document.getElementById('geoMessage');
    msgEl.innerHTML = '<span class="text-primary fw-bold"><i class="spinner-border spinner-border-sm me-2"></i> กำลังร้องขอและดึงพิกัดจากอุปกรณ์ของคุณ...</span>';
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('latInput').value = position.coords.latitude;
                document.getElementById('lngInput').value = position.coords.longitude;
                msgEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> ดึงพิกัด GPS สำเร็จและลงฟอร์มแล้ว</span>';
                
                // ซ่อนข้อความหลังจาก 4 วินาที
                setTimeout(() => { msgEl.innerHTML = ''; }, 4000);
            },
            function(error) {
                let errorMsg = "ไม่สามารถดึงพิกัดได้ ขาดการเชื่อมต่อ GPS";
                if(error.code === 1) errorMsg = "กรุณากด 'อนุญาต' (Allow) ให้เบราว์เซอร์เข้าถึง Location เพื่อดึงพิกัดอัตโนมัติ";
                msgEl.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> ${errorMsg}</span>`;
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    } else {
        msgEl.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i> อุปกรณ์หรือเบราว์เซอร์ของคุณไม่รองรับ GPS</span>';
    }
}

// 3. จัดเตรียมค่าเวลาก่อนกด Save (รวม Start-End เป็น String เดี่ยวเพื่อส่งเข้า Controller)
function prepareSubmit() {
    const ms = document.getElementById('m_s').value;
    const me = document.getElementById('m_e').value;
    document.getElementById('morning_shift').value = ms + '-' + me;

    const as = document.getElementById('a_s').value;
    const ae = document.getElementById('a_e').value;
    document.getElementById('afternoon_shift').value = as + '-' + ae;

    const ns = document.getElementById('n_s').value;
    const ne = document.getElementById('n_e').value;
    document.getElementById('night_shift').value = ns + '-' + ne;
}
</script>