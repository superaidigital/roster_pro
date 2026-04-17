<?php
// ที่อยู่ไฟล์: views/auth/login.php
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | Roster Pro</title>
    
    <!-- นำเข้า Bootstrap และ Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- นำเข้าฟอนต์ Noto Sans Thai -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Noto Sans Thai', sans-serif;
            background-color: #f4f6f9;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px; /* ป้องกันชิดขอบจอเกินไปในมือถือ */
        }
        
        .login-container {
            max-width: 1000px;
            width: 100%;
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
            display: flex;
        }

        /* 🌟 ฝั่งซ้าย (รูปภาพ) */
        .login-left {
            background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
            padding: 3rem;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        /* ลายน้ำปฏิทินที่พื้นหลัง */
        .login-left::after {
            content: '\F22D'; /* รูปปฏิทินจาก bootstrap-icons */
            font-family: 'bootstrap-icons';
            position: absolute;
            right: -30px;
            bottom: -50px;
            font-size: 18rem;
            opacity: 0.08;
            transform: rotate(-15deg);
            pointer-events: none; /* ไม่ให้คลิกโดน */
        }

        .brand-logo-large {
            font-size: 4rem;
            margin-bottom: 1rem;
            text-shadow: 0 4px 10px rgba(0,0,0,0.2);
            position: relative;
            z-index: 2;
        }

        /* 🌟 ฝั่งขวา (ฟอร์ม) */
        .login-right {
            padding: 4rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: #ffffff;
        }

        /* สไตล์กล่อง Input สมัยใหม่ (ไร้รอยต่อ) */
        .input-group-modern {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background-color: #f8fafc;
            transition: all 0.2s;
            overflow: hidden;
        }
        .input-group-modern:focus-within {
            background-color: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15);
        }
        .input-group-modern .input-group-text {
            background-color: transparent;
            border: none;
            color: #64748b;
        }
        .input-group-modern .form-control {
            background-color: transparent;
            border: none;
            box-shadow: none;
            padding: 0.8rem 1rem 0.8rem 0;
            font-size: 15px;
            color: #1e293b;
        }
        
        .btn-toggle-password {
            cursor: pointer;
            transition: color 0.2s;
        }
        .btn-toggle-password:hover {
            color: #3b82f6 !important;
        }

        .btn-login {
            background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
            border: none;
            border-radius: 0.75rem;
            padding: 0.85rem;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            color: white;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
            color: white;
        }

        /* 📱 ปรับแต่งสำหรับมือถือและแท็บเล็ต */
        @media (max-width: 767.98px) {
            .login-left { display: none !important; } /* ซ่อนฝั่งซ้ายในมือถือ */
            .login-right { padding: 3rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="row g-0 w-100">
        
        <!-- 🎨 ฝั่งซ้าย: รูปภาพและโลโก้ (ซ่อนในมือถือ) -->
        <div class="col-md-5 login-left d-none d-md-flex">
            <div style="z-index: 2;">
                <i class="bi bi-calendar2-check-fill brand-logo-large text-white"></i>
                <h2 class="fw-bold mb-3">Roster<span class="fw-light">Pro</span></h2>
                <p class="opacity-75 fw-light mb-0" style="font-size: 1.1rem; line-height: 1.6;">
                    ระบบจัดตารางปฏิบัติงานและลางานออนไลน์<br>สำหรับหน่วยบริการสุขภาพ
                </p>
            </div>
            <div class="position-absolute bottom-0 mb-4 opacity-50 small" style="z-index: 2;">
                &copy; <?= date('Y') ?> Roster Pro System
            </div>
        </div>

        <!-- 📝 ฝั่งขวา: ฟอร์มเข้าสู่ระบบ -->
        <div class="col-md-7 login-right">
            <div class="w-100 mx-auto" style="max-width: 420px;">
                
                <!-- โลโก้สำหรับหน้าจอมือถือ -->
                <div class="text-center d-md-none mb-4 pb-2">
                    <i class="bi bi-calendar2-check-fill text-primary" style="font-size: 3.5rem;"></i>
                    <h2 class="fw-bold text-dark mt-2 mb-0">Roster<span class="text-primary">Pro</span></h2>
                </div>

                <div class="mb-4 text-center text-md-start">
                    <h3 class="fw-bold text-dark mb-1">เข้าสู่ระบบ</h3>
                    <p class="text-muted small mb-0">กรุณากรอกชื่อผู้ใช้งานและรหัสผ่านของคุณ</p>
                </div>

                <!-- 🔔 กล่องแจ้งเตือนข้อผิดพลาด/สำเร็จ -->
                <?php if (isset($_SESSION['login_error'])): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 d-flex align-items-center mb-4 p-3 shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-3"></i> 
                        <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['login_error'] ?></div>
                    </div>
                    <?php unset($_SESSION['login_error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 d-flex align-items-center mb-4 p-3 shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-3"></i> 
                        <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['error_msg'] ?></div>
                    </div>
                    <?php unset($_SESSION['error_msg']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center mb-4 p-3 shadow-sm">
                        <i class="bi bi-check-circle-fill fs-5 me-3"></i> 
                        <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['success_msg'] ?></div>
                    </div>
                    <?php unset($_SESSION['success_msg']); ?>
                <?php endif; ?>

                <!-- 📋 ฟอร์มเข้าสู่ระบบ -->
                <form action="index.php?c=auth&a=login" method="POST">
                    <!-- ซ่อน Input บอกทิศทาง Controller ป้องกันบัคหน้าขาว -->
                    <input type="hidden" name="c" value="auth">
                    <input type="hidden" name="a" value="login">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1">ชื่อผู้ใช้งาน (Username)</label>
                        <div class="input-group-modern d-flex align-items-center shadow-sm">
                            <span class="input-group-text ps-3 pe-2"><i class="bi bi-person-fill fs-5"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="กรอกชื่อผู้ใช้งาน" required autofocus autocomplete="username">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small mb-1">รหัสผ่าน (Password)</label>
                        <div class="input-group-modern d-flex align-items-center shadow-sm">
                            <span class="input-group-text ps-3 pe-2"><i class="bi bi-lock-fill fs-5"></i></span>
                            <input type="password" name="password" id="passwordInput" class="form-control" placeholder="กรอกรหัสผ่าน" required autocomplete="current-password">
                            <span class="input-group-text pe-3 btn-toggle-password" id="togglePasswordBtn">
                                <i class="bi bi-eye-slash-fill fs-5" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>

                    <button type="submit" name="login_btn" value="1" class="btn w-100 btn-login shadow-sm mt-2">
                        เข้าสู่ระบบ <i class="bi bi-arrow-right-circle ms-1"></i>
                    </button>
                </form>
                
                <!-- 💡 ข้อมูลแนะแนวทาง (สำหรับทดสอบ) -->
                <!-- <div class="text-center mt-5 border-top pt-4">
                    <p class="text-muted fw-medium mb-0" style="font-size: 13px;">
                        <i class="bi bi-info-circle me-1"></i> ทดสอบระบบ: <span class="text-dark fw-bold">admin</span> / 1234 หรือ <span class="text-dark fw-bold">director</span> / 1234
                    </p>
                </div> -->
                
            </div>
        </div>
    </div>
</div>

<script>
    // สคริปต์สลับการแสดงผลรหัสผ่าน (Show/Hide Password)
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('passwordInput');
        const toggleIcon = document.getElementById('toggleIcon');

        if(toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault(); 
                
                if (passwordInput.getAttribute('type') === 'password') {
                    passwordInput.setAttribute('type', 'text');
                    toggleIcon.classList.replace('bi-eye-slash-fill', 'bi-eye-fill');
                    toggleIcon.classList.add('text-primary');
                } else {
                    passwordInput.setAttribute('type', 'password');
                    toggleIcon.classList.replace('bi-eye-fill', 'bi-eye-slash-fill');
                    toggleIcon.classList.remove('text-primary');
                }
            });
        }
    });
</script>

</body>
</html>