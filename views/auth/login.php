<?php
// ที่อยู่ไฟล์: views/auth/login.php
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>เข้าสู่ระบบ | Roster Pro</title>
    <meta name="theme-color" content="#f8fbfd">
    <script id="rp-theme-prepaint">
    (() => {
      try {
        const saved = localStorage.getItem('rp-theme');
        const theme = saved === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
      } catch (_) {
        document.documentElement.setAttribute('data-theme', 'light');
        document.documentElement.setAttribute('data-bs-theme', 'light');
      }
    })();
    </script>
    
    <!-- นำเข้า Bootstrap และ Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="public/css/style.css?v=20261004-health-v15">
    <link rel="stylesheet" href="public/css/ui-proportions.css?v=20261004-health-v15">
    <script src="public/js/progress.js?v=20261004-health-v15" defer></script>
    
    <!-- นำเข้าฟอนต์ Noto Sans Thai -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background:
                radial-gradient(circle at 10% 10%, rgba(15,108,189,.12), transparent 28rem),
                linear-gradient(160deg, #f8fbfd 0%, #edf6f8 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px; /* ป้องกันชิดขอบจอเกินไปในมือถือ */
        }
        
        .login-container {
            max-width: 62rem;
            width: 100%;
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
            display: flex;
        }

        /* 🌟 ฝั่งซ้าย (รูปภาพ) */
        .login-left {
            background: linear-gradient(145deg, #0f6cbd 0%, #0f766e 100%);
            padding: clamp(2rem, 4vw, 3rem);
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
            padding: clamp(2rem, 5vw, 4rem) clamp(1.5rem, 4vw, 3rem);
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
            background: linear-gradient(135deg, #0f6cbd 0%, #0f766e 100%);
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
            box-shadow: 0 8px 20px rgba(15, 108, 189, 0.24);
            color: white;
        }

        /* 📱 ปรับแต่งสำหรับมือถือและแท็บเล็ต */
        @media (max-width: 767.98px) {
            .login-left { display: none !important; } /* ซ่อนฝั่งซ้ายในมือถือ */
            .login-right { padding: 3rem 1.5rem; }
        }
    </style>
<style>
/* Modern Clinical Login v10 */
.login-container {
    max-width: 66rem !important;
    min-height: min(42rem, calc(100dvh - 3rem));
    border: 1px solid #d7e3ea;
    border-radius: 1.4rem !important;
    background: #fff;
    box-shadow: 0 2rem 5rem rgba(11,31,47,.16) !important;
}
.login-left {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 20% 20%, rgba(45,212,191,.24), transparent 18rem),
        linear-gradient(145deg, #12384d 0%, #0b1f2f 72%) !important;
}
.login-left::before {
    content: '';
    position: absolute;
    width: 22rem;
    height: 22rem;
    right: -10rem;
    bottom: -8rem;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 50%;
    box-shadow:
        0 0 0 3rem rgba(255,255,255,.025),
        0 0 0 7rem rgba(255,255,255,.02);
}
.login-left .brand-logo-large {
    color: #5eead4 !important;
    filter: drop-shadow(0 .6rem 1.2rem rgba(45,212,191,.16));
}
.login-left h2 {
    font-size: clamp(2rem, 3vw, 2.7rem);
    letter-spacing: -.04em;
}
.login-right {
    background: #fff;
}
.login-right h3 {
    color: #10212e !important;
    letter-spacing: -.025em;
}
.input-group-modern {
    min-height: 3.15rem;
    border: 1px solid #cbd9e4 !important;
    border-radius: .82rem !important;
    background: #fbfdfe !important;
    box-shadow: none !important;
}
.input-group-modern:focus-within {
    border-color: #0f6cbd !important;
    background: #fff !important;
    box-shadow: 0 0 0 .2rem rgba(15,108,189,.12) !important;
}
.btn-login {
    min-height: 3.2rem;
    border: 0 !important;
    border-radius: .82rem !important;
    color: #fff !important;
    background: linear-gradient(135deg, #0f6cbd, #0f766e) !important;
    box-shadow: 0 .7rem 1.5rem rgba(15,108,189,.18) !important;
}
.btn-login:hover {
    transform: translateY(-1px);
    box-shadow: 0 .85rem 1.8rem rgba(15,108,189,.22) !important;
}
@media (max-width: 767.98px) {
    .login-container {
        min-height: 100dvh;
        border: 0;
        border-radius: 0 !important;
        box-shadow: none !important;
    }
}
</style>
    <link rel="stylesheet" href="public/css/themes.css?v=20261004-theme-v4">
    <script src="public/js/theme.js?v=20261004-theme-v4" defer></script>
</head>
<body>
<button type="button"
        class="nav-icon-btn rp-theme-toggle rp-login-theme-toggle"
        data-rp-theme-toggle
        aria-pressed="false"
        aria-label="เปลี่ยนเป็นโหมดมืด"
        title="โหมดมืด">
    <i class="bi bi-moon-stars-fill" data-rp-theme-icon></i>
</button>

<div id="rpGlobalProgress" class="rp-global-progress" role="progressbar" aria-label="สถานะการประมวลผล" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
    <div class="rp-global-progress-track">
        <div id="rpGlobalProgressBar" class="rp-global-progress-bar"></div>
    </div>
    <div id="rpGlobalProgressLabel" class="rp-global-progress-label" aria-hidden="true"></div>
</div>
<div id="rpProgressLive" class="visually-hidden" aria-live="polite" aria-atomic="true"></div>

<div class="login-container">
    <div class="row g-0 w-100">
        
        <!-- 🎨 ฝั่งซ้าย: รูปภาพและโลโก้ (ซ่อนในมือถือ) -->
        <div class="col-md-5 login-left d-none d-md-flex">
            <div style="z-index: 2;">
                <i class="bi bi-heart-pulse-fill brand-logo-large text-white"></i>
                <h2 class="fw-bold mb-3">Roster<span class="fw-light">Pro</span></h2>
                <p class="opacity-75 fw-light mb-0" style="font-size: 1.1rem; line-height: 1.6;">
                    พื้นที่ทำงานดิจิทัลสำหรับการจัดเวร วันลา และบุคลากร<br>ออกแบบสำหรับหน่วยบริการปฐมภูมิ
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
                    <i class="bi bi-heart-pulse-fill text-primary" style="font-size: 3.5rem;"></i>
                    <h2 class="fw-bold text-dark mt-2 mb-0">Roster<span class="text-primary">Pro</span></h2>
                </div>

                <div class="mb-4 text-center text-md-start">
                    <h3 class="fw-bold text-dark mb-1">เข้าสู่ระบบ</h3>
                    <p class="text-muted small mb-0">กรุณากรอกชื่อผู้ใช้งานและรหัสผ่านของคุณ</p>
                </div>

                <!-- 🔔 กล่องแจ้งเตือนข้อผิดพลาด/สำเร็จ -->
                <?php if (isset($_SESSION['login_error'])): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 d-flex align-items-center mb-4 p-3 shadow-sm" role="alert" aria-live="assertive">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-3"></i> 
                        <div class="fw-bold" style="font-size: 14px;"><?= htmlspecialchars($_SESSION['login_error'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <?php unset($_SESSION['login_error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 d-flex align-items-center mb-4 p-3 shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-3"></i> 
                        <div class="fw-bold" style="font-size: 14px;"><?= htmlspecialchars($_SESSION['error_msg'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <?php unset($_SESSION['error_msg']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center mb-4 p-3 shadow-sm" role="status" aria-live="polite">
                        <i class="bi bi-check-circle-fill fs-5 me-3"></i> 
                        <div class="fw-bold" style="font-size: 14px;"><?= htmlspecialchars($_SESSION['success_msg'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <?php unset($_SESSION['success_msg']); ?>
                <?php endif; ?>

                <!-- 📋 ฟอร์มเข้าสู่ระบบ -->
                <form action="index.php?c=auth&a=login" method="POST">
                    <!-- ซ่อน Input บอกทิศทาง Controller ป้องกันบัคหน้าขาว -->
                    <input type="hidden" name="c" value="auth">
                    <input type="hidden" name="a" value="login">
                    <?= security_csrf_input() ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1" for="usernameInput">ชื่อผู้ใช้งาน (Username)</label>
                        <div class="input-group-modern d-flex align-items-center shadow-sm">
                            <span class="input-group-text ps-3 pe-2"><i class="bi bi-person-fill fs-5"></i></span>
                            <input type="text" name="username" id="usernameInput" class="form-control" placeholder="กรอกชื่อผู้ใช้งาน" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small mb-1" for="passwordInput">รหัสผ่าน (Password)</label>
                        <div class="input-group-modern d-flex align-items-center shadow-sm">
                            <span class="input-group-text ps-3 pe-2"><i class="bi bi-lock-fill fs-5"></i></span>
                            <input type="password" name="password" id="passwordInput" class="form-control" placeholder="กรอกรหัสผ่าน" required autocomplete="current-password">
                            <button type="button"
                                    class="input-group-text pe-3 btn-toggle-password border-0 bg-transparent"
                                    id="togglePasswordBtn"
                                    aria-controls="passwordInput"
                                    aria-pressed="false"
                                    aria-label="แสดงรหัสผ่าน">
                                <i class="bi bi-eye-slash-fill fs-5" id="toggleIcon" aria-hidden="true"></i>
                            </button>
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
                
                const showing = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', showing ? 'text' : 'password');
                toggleBtn.setAttribute('aria-pressed', showing ? 'true' : 'false');
                toggleBtn.setAttribute('aria-label', showing ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
                toggleIcon.classList.toggle('bi-eye-fill', showing);
                toggleIcon.classList.toggle('bi-eye-slash-fill', !showing);
                toggleIcon.classList.toggle('text-primary', showing);
            });
        }
    });
</script>

</body>
</html>