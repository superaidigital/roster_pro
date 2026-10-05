<?php
$verification = is_array($verification ?? null) ? $verification : null;
$requestedCode = strtoupper(trim((string)($requestedCode ?? '')));
$searched = (bool)($searched ?? false);

$status = 'idle';
$title = 'ตรวจสอบเอกสารตารางเวร';
$message = 'กรอกรหัส Verification Code จากเอกสาร หรือสแกน QR Code';
$icon = '✓';

if ($searched) {
    if (!$verification) {
        $status = 'invalid';
        $title = 'ไม่พบเอกสารที่ตรวจสอบได้';
        $message = 'รหัสตรวจสอบไม่ถูกต้อง หรือเอกสารนี้ไม่ได้ออกจากระบบ Roster Pro';
        $icon = '!';
    } elseif (empty($verification['integrity_valid'])) {
        $status = 'invalid';
        $title = 'การตรวจสอบความถูกต้องไม่ผ่าน';
        $message = 'ข้อมูล Revision หรือ Snapshot ไม่ตรงกับ SHA-256 ที่บันทึกไว้ กรุณาติดต่อหน่วยงานผู้ออกเอกสาร';
        $icon = '!';
    } elseif (empty($verification['is_latest'])) {
        $status = 'superseded';
        $title = 'เอกสารถูกต้อง แต่มีฉบับใหม่กว่า';
        $message = 'Revision นี้ยังตรวจสอบความถูกต้องได้ แต่ไม่ใช่ฉบับล่าสุดของเดือนดังกล่าว';
        $icon = '↻';
    } else {
        $status = 'valid';
        $title = 'เอกสารถูกต้องและเป็นฉบับล่าสุด';
        $message = 'SHA-256 ของ Revision ตรงกับข้อมูลที่ระบบเก็บไว้';
        $icon = '✓';
    }
}

$statusClass = [
    'idle' => 'neutral',
    'valid' => 'success',
    'superseded' => 'warning',
    'invalid' => 'danger',
][$status] ?? 'neutral';

$monthText = '';
if ($verification) {
    $monthYear = (string)$verification['month_year'];
    [$year, $month] = array_map('intval', explode('-', $monthYear));
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $monthText = ($thaiMonths[$month] ?? $monthYear) . ' ' . ($year + 543);
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>ตรวจสอบเอกสารตารางเวร | Roster Pro</title>
<style>
:root{
    color-scheme:light;
    --bg:#f3f7fb;--card:#fff;--text:#142033;--muted:#64748b;--line:#dbe4ee;
    --success:#087f5b;--success-bg:#e8f8f1;
    --warning:#a15c00;--warning-bg:#fff6dd;
    --danger:#b42318;--danger-bg:#fff0ee;
    --neutral:#315a7d;--neutral-bg:#edf5fb;
}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,"Segoe UI","Noto Sans Thai",sans-serif;background:linear-gradient(160deg,#edf6ff 0%,var(--bg) 48%,#f8fafc 100%);color:var(--text)}
main{min-height:100vh;display:grid;place-items:center;padding:24px}
.shell{width:min(760px,100%)}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.logo{width:48px;height:48px;border-radius:15px;background:#0f6cbd;color:#fff;display:grid;place-items:center;font-weight:900;box-shadow:0 12px 28px rgba(15,108,189,.22)}
.brand strong{font-size:1.05rem}.brand span{display:block;color:var(--muted);font-size:.84rem;margin-top:2px}
.card{background:var(--card);border:1px solid rgba(219,228,238,.9);border-radius:24px;box-shadow:0 24px 70px rgba(15,23,42,.10);overflow:hidden}
.hero{padding:28px;display:flex;gap:18px;align-items:flex-start}
.state-icon{width:58px;height:58px;border-radius:18px;display:grid;place-items:center;font-size:1.7rem;font-weight:900;flex:0 0 auto}
.success .state-icon{background:var(--success-bg);color:var(--success)}
.warning .state-icon{background:var(--warning-bg);color:var(--warning)}
.danger .state-icon{background:var(--danger-bg);color:var(--danger)}
.neutral .state-icon{background:var(--neutral-bg);color:var(--neutral)}
h1{font-size:clamp(1.35rem,4vw,2rem);margin:0 0 8px;line-height:1.25}
.lead{margin:0;color:var(--muted);line-height:1.65}
.form-box{padding:0 28px 28px}
form{display:flex;gap:10px}
input{min-width:0;flex:1;border:1px solid var(--line);border-radius:14px;padding:13px 15px;font:inherit;text-transform:uppercase;letter-spacing:.04em}
input:focus{outline:3px solid rgba(15,108,189,.14);border-color:#0f6cbd}
button,.button{border:0;border-radius:14px;padding:13px 18px;background:#0f6cbd;color:#fff;text-decoration:none;font-weight:750;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
.details{border-top:1px solid var(--line);padding:24px 28px 28px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.item{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fbfdff;min-width:0}
.item.full{grid-column:1/-1}.label{font-size:.78rem;color:var(--muted);margin-bottom:5px}.value{font-weight:720;overflow-wrap:anywhere;line-height:1.45}
.hash{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:.78rem;word-break:break-all}
.badge{display:inline-block;border-radius:999px;padding:6px 10px;font-size:.76rem;font-weight:800}
.badge.success{background:var(--success-bg);color:var(--success)}
.badge.warning{background:var(--warning-bg);color:var(--warning)}
.badge.danger{background:var(--danger-bg);color:var(--danger)}
.latest-box{margin-top:14px;border:1px solid #f1cf82;background:var(--warning-bg);border-radius:16px;padding:14px;line-height:1.6}
.footer{text-align:center;color:var(--muted);font-size:.78rem;margin-top:16px;line-height:1.6}
@media(max-width:640px){main{padding:14px}.hero{padding:22px;flex-direction:column}.form-box,.details{padding-left:22px;padding-right:22px}.grid{grid-template-columns:1fr}form{flex-direction:column}.item.full{grid-column:auto}}
</style>
</head>
<body>
<main>
<div class="shell">
    <div class="brand">
        <div class="logo">RP</div>
        <div><strong>Roster Pro Verification</strong><span>ระบบตรวจสอบฉบับตารางเวรที่อนุมัติแล้ว</span></div>
    </div>

    <section class="card <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
        <div class="hero">
            <div class="state-icon" aria-hidden="true"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></div>
            <div>
                <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="lead"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <div class="form-box">
            <form method="get" action="index.php">
                <input type="hidden" name="c" value="verify">
                <input type="hidden" name="a" value="revision">
                <input name="code" maxlength="32" minlength="32"
                       pattern="[A-Fa-f0-9]{32}"
                       value="<?= htmlspecialchars($requestedCode, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="VERIFICATION CODE 32 ตัวอักษร"
                       aria-label="Verification Code" required>
                <button type="submit">ตรวจสอบ</button>
            </form>
        </div>

        <?php if ($verification): ?>
        <div class="details">
            <div class="grid">
                <div class="item">
                    <div class="label">Revision Code</div>
                    <div class="value"><?= htmlspecialchars((string)$verification['revision_code'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="item">
                    <div class="label">สถานะเอกสาร</div>
                    <div class="value">
                        <?php if (empty($verification['integrity_valid'])): ?>
                            <span class="badge danger">Integrity Failed</span>
                        <?php elseif (!empty($verification['is_latest'])): ?>
                            <span class="badge success">Verified · Latest</span>
                        <?php else: ?>
                            <span class="badge warning">Verified · Superseded</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="item full">
                    <div class="label">หน่วยบริการ</div>
                    <div class="value"><?= htmlspecialchars((string)$verification['hospital_name'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="item">
                    <div class="label">ประจำเดือน</div>
                    <div class="value"><?= htmlspecialchars($monthText, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="item">
                    <div class="label">อนุมัติเมื่อ</div>
                    <div class="value"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime((string)$verification['approved_at'])), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="item full">
                    <div class="label">ผู้อนุมัติ</div>
                    <div class="value">
                        <?= htmlspecialchars((string)$verification['approved_name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($verification['approved_position'])): ?>
                            · <?= htmlspecialchars((string)$verification['approved_position'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="item full">
                    <div class="label">SHA-256 Content Hash</div>
                    <div class="value hash"><?= htmlspecialchars((string)$verification['content_hash'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="item full">
                    <div class="label">Verification Code</div>
                    <div class="value hash"><?= htmlspecialchars((string)$verification['verification_code'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>

            <?php if (!empty($verification['integrity_valid']) && empty($verification['is_latest'])): ?>
            <div class="latest-box">
                <strong>มีฉบับใหม่กว่า:</strong>
                <?= htmlspecialchars((string)$verification['latest_revision_code'], ENT_QUOTES, 'UTF-8') ?>
                <div style="margin-top:8px">
                    <a class="button"
                       href="index.php?c=verify&amp;a=revision&amp;code=<?= rawurlencode((string)$verification['latest_verification_code']) ?>">
                        ตรวจสอบฉบับล่าสุด
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <div class="footer">
        หน้านี้ใช้ยืนยันความถูกต้องของเอกสารเท่านั้น และไม่แสดงรายละเอียดตารางเวรหรือข้อมูลค่าตอบแทน
    </div>
</div>
</main>
</body>
</html>
