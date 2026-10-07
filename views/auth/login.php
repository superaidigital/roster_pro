<?php
// views/auth/login.php
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>เข้าสู่ระบบ | Roster Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="login-page">
<div class="login-network" aria-hidden="true"></div>

<main class="login-shell">
    <section class="login-card" aria-labelledby="loginTitle">
        <div class="login-scene" aria-hidden="true">
            <div class="login-scene-copy">
                <div class="login-kicker">ROSTER PRO · PRIMARY CARE DATA</div>
                <h1>ข้อมูลสุขภาพ<br>เชื่อมโยงถึงพื้นที่</h1>
                <p>บริหารงานหน่วยบริการและข้อมูลมาตรฐานสุขภาพในหน้าจอเดียว พร้อมติดตามคุณภาพข้อมูลและวิเคราะห์เชิงพื้นที่อย่างปลอดภัย</p>
            </div>
            <div class="health-scene">
                <div class="scene-dot d1"></div><div class="scene-dot d2"></div><div class="scene-dot d3"></div><div class="scene-dot d4"></div>
                <div class="map-pin"></div>
                <div class="pulse-line">
                    <svg viewBox="0 0 560 120" preserveAspectRatio="none" focusable="false">
                        <polyline points="0,70 90,70 120,55 145,82 178,20 216,94 245,70 560,70"></polyline>
                    </svg>
                </div>
                <div class="village-ground"></div>
                <div class="house h1"></div><div class="house h2"></div><div class="house h3"></div>
            </div>
            <div class="small opacity-75">&copy; <?= date('Y') ?> Roster Pro System</div>
        </div>

        <div class="login-form-pane">
            <div class="login-form-wrap">
                <div class="login-mobile-brand"><i class="bi bi-heart-pulse-fill"></i><span>Roster Pro</span></div>
                <h2 id="loginTitle" class="login-title">เข้าสู่ระบบ</h2>
                <p class="login-subtitle">ใช้บัญชีของหน่วยบริการเพื่อเข้าสู่ระบบ</p>

                <?php if (isset($_SESSION['login_error'])): ?>
                    <div class="login-alert login-alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div><?= htmlspecialchars((string)$_SESSION['login_error'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <?php unset($_SESSION['login_error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="login-alert login-alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div><?= htmlspecialchars((string)$_SESSION['error_msg'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <?php unset($_SESSION['error_msg']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="login-alert login-alert-success" role="status">
                        <i class="bi bi-check-circle-fill"></i>
                        <div><?= htmlspecialchars((string)$_SESSION['success_msg'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <?php unset($_SESSION['success_msg']); ?>
                <?php endif; ?>

                <form id="loginForm" action="index.php?c=auth&a=login" method="POST" novalidate>
                    <div class="float-field">
                        <i class="bi bi-person-fill field-icon" aria-hidden="true"></i>
                        <input id="usernameInput" type="text" name="username" placeholder=" " required autofocus autocomplete="username" maxlength="100">
                        <label for="usernameInput">ชื่อผู้ใช้งาน</label>
                    </div>

                    <div class="float-field">
                        <i class="bi bi-lock-fill field-icon" aria-hidden="true"></i>
                        <input id="passwordInput" type="password" name="password" placeholder=" " required autocomplete="current-password">
                        <label for="passwordInput">รหัสผ่าน</label>
                        <button type="button" id="togglePasswordBtn" class="password-toggle" aria-label="แสดงรหัสผ่าน" aria-pressed="false">
                            <i class="bi bi-eye-slash-fill"></i>
                        </button>
                    </div>
                    <div id="capsWarning" class="caps-warning" role="status" aria-live="polite">
                        <i class="bi bi-exclamation-circle-fill"></i> Caps Lock เปิดอยู่
                    </div>

                    <button id="loginSubmit" type="submit" class="login-submit">
                        เข้าสู่ระบบ <i class="bi bi-arrow-right-circle ms-1"></i>
                    </button>
                </form>
                <div class="login-footnote">รองรับโหมดมืด และเคารพการตั้งค่า “ลดการเคลื่อนไหว” ของอุปกรณ์</div>
            </div>
        </div>
    </section>
</main>
<script src="assets/js/login.js" defer></script>
</body>
</html>
