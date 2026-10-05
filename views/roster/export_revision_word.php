<?php
// Immutable official roster revision export.
// This view must use only the revision payload; do not query live roster/personnel data here.

$revision = is_array($revision ?? null) ? $revision : [];
$monthYear = (string)($revision['month_year'] ?? date('Y-m'));
$parts = explode('-', $monthYear);
$year = (int)($parts[0] ?? date('Y'));
$month = (int)($parts[1] ?? date('m'));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$thaiMonths = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
$monthText = ($thaiMonths[$month] ?? '') . ' ' . ($year + 543);
$revisionCode = (string)($revision['revision_code'] ?? 'REV');
$hospitalName = (string)($revision['hospital_name'] ?? 'หน่วยบริการ');
$staffs = is_array($revision['staff'] ?? null) ? $revision['staff'] : [];
$shifts = is_array($revision['shifts'] ?? null) ? $revision['shifts'] : [];
$holidays = is_array($revision['holidays'] ?? null) ? $revision['holidays'] : [];
$paySummary = is_array($revision['pay_summary'] ?? null) ? $revision['pay_summary'] : [];

$holidaySet = [];
foreach ($holidays as $holiday) {
    $date = (string)($holiday['holiday_date'] ?? '');
    if ($date !== '') {
        $holidaySet[$date] = (string)($holiday['holiday_name'] ?? 'วันหยุด');
    }
}

$shiftByUserDay = [];
foreach ($shifts as $shift) {
    $uid = (int)($shift['user_id'] ?? 0);
    $date = (string)($shift['shift_date'] ?? '');
    $type = trim((string)($shift['shift_type'] ?? ''));
    if ($uid <= 0 || $date === '' || $type === '') continue;
    $day = (int)substr($date, 8, 2);
    $shiftByUserDay[$uid][$day] = $type;
}

$signatureImg = static function($signature): string {
    $signature = trim((string)$signature);
    if ($signature === '' || !preg_match('#^data:image/png;base64,[A-Za-z0-9+/=\\r\\n]+$#', $signature)) {
        return '';
    }
    return '<img src="' . htmlspecialchars($signature, ENT_QUOTES, 'UTF-8') .
           '" alt="ลายเซ็น" style="max-width:145px;max-height:55px;">';
};

$preparedSignature = $signatureImg($revision['prepared_signature'] ?? null);
$reviewedSignature = $signatureImg($revision['reviewed_signature'] ?? null);
$approvedSignature = $signatureImg($revision['approved_signature'] ?? null);

header("Content-Type: application/vnd.ms-word; charset=utf-8");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header(
    'Content-Disposition: attachment;filename="' .
    rawurlencode("ตารางเวร_{$revisionCode}_{$hospitalName}.doc") .
    '"'
);
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?= htmlspecialchars($revisionCode, ENT_QUOTES, 'UTF-8') ?></title>
<style>
@page Section1 {
    size: 841.9pt 595.3pt;
    mso-page-orientation: landscape;
    margin: 18pt;
}
div.Section1 { page: Section1; }
body { font-family: "TH Sarabun New", "TH SarabunPSK", sans-serif; font-size: 14pt; color: #111; }
.center { text-align: center; }
.bold { font-weight: bold; }
.small { font-size: 10pt; }
.meta { margin: 8px 0 10px; width: 100%; border-collapse: collapse; }
.meta td { border: 1px solid #bbb; padding: 3px 6px; font-size: 10pt; }
table.roster { width: 100%; border-collapse: collapse; font-size: 10pt; }
table.roster th, table.roster td { border: 1px solid #111; padding: 1px; text-align: center; vertical-align: middle; }
table.roster th { background: #fff7b2; }
.name { text-align: left !important; padding-left: 4px !important; min-width: 100px; }
.holiday { background: #ffe4e6; }
.summary { background: #fff7b2; font-weight: bold; }
.money { background: #e2efda; font-weight: bold; }
.signature-table { width: 100%; border-collapse: collapse; margin-top: 18px; }
.signature-table td { width: 33.33%; text-align: center; vertical-align: top; border: none; font-size: 13pt; line-height: 1.25; padding: 4px; }
.sig-box { height: 58px; display: block; }
.integrity { margin-top: 14px; border: 1px solid #b8c2cc; background: #f8fafc; padding: 6px 8px; font-size: 9pt; word-break: break-all; }
.verification-table { width:100%; border-collapse:collapse; margin-top:12px; }
.verification-table td { border:1px solid #b8c2cc; padding:8px; vertical-align:middle; }
.verification-code { font-family:Consolas,monospace; font-size:10pt; letter-spacing:.5px; word-break:break-all; }
.verify-url { font-size:8pt; word-break:break-all; color:#334155; }
</style>
</head>
<body>
<div class="Section1">
    <div class="center bold" style="font-size:17pt;">
        ตารางเวรเจ้าหน้าที่ปฏิบัติงานในหน่วยบริการ<br>
        นอกเวลาราชการและวันหยุดราชการ ประจำเดือน
        <?= htmlspecialchars($monthText, ENT_QUOTES, 'UTF-8') ?><br>
        <?= htmlspecialchars($hospitalName, ENT_QUOTES, 'UTF-8') ?>
    </div>

    <table class="meta">
        <tr>
            <td><b>ฉบับทางการ:</b> <?= htmlspecialchars($revisionCode, ENT_QUOTES, 'UTF-8') ?></td>
            <td><b>Revision:</b> <?= (int)($revision['revision_no'] ?? 0) ?></td>
            <td><b>อนุมัติเมื่อ:</b> <?= htmlspecialchars((string)($revision['approved_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>

    <table class="roster">
        <thead>
        <tr>
            <th style="width:22px;">ที่</th>
            <th style="width:120px;">ชื่อ-สกุล</th>
            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $weekend = (int)date('N', strtotime($date)) >= 6;
                $isHoliday = $weekend || isset($holidaySet[$date]);
            ?>
                <th class="<?= $isHoliday ? 'holiday' : '' ?>"><?= $day ?></th>
            <?php endfor; ?>
            <th>ร</th>
            <th>ย</th>
            <th>บ</th>
            <th>รวม</th>
            <th style="width:55px;">ค่าเวร</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $rowNo = 1;
        $grandR = $grandY = $grandB = $grandTotal = 0;
        $grandPay = 0.0;

        foreach ($staffs as $staff):
            $uid = (int)($staff['id'] ?? 0);
            $sumR = $sumY = $sumB = 0;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $value = (string)($shiftByUserDay[$uid][$day] ?? '');
                $types = preg_split('/[\\/,\\s]+/', trim($value)) ?: [];
                foreach ($types as $type) {
                    $type = ['A' => 'บ', 'N' => 'ร', 'O' => 'ย', 'M' => 'ช'][$type] ?? $type;
                    if ($type === 'ร') $sumR++;
                    elseif ($type === 'ย') $sumY++;
                    elseif ($type === 'บ') $sumB++;
                }
            }

            $total = $sumR + $sumY + $sumB;
            $pay = 0.0;
            if (isset($paySummary[$uid]['pay'])) {
                $pay = (float)$paySummary[$uid]['pay'];
            } elseif (isset($paySummary[(string)$uid]['pay'])) {
                $pay = (float)$paySummary[(string)$uid]['pay'];
            }

            $grandR += $sumR;
            $grandY += $sumY;
            $grandB += $sumB;
            $grandTotal += $total;
            $grandPay += $pay;
        ?>
        <tr>
            <td><?= $rowNo++ ?></td>
            <td class="name">
                <b><?= htmlspecialchars((string)($staff['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></b><br>
                <span class="small"><?= htmlspecialchars((string)($staff['type'] ?? $staff['position'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $weekend = (int)date('N', strtotime($date)) >= 6;
                $isHoliday = $weekend || isset($holidaySet[$date]);
                $value = (string)($shiftByUserDay[$uid][$day] ?? '');
            ?>
                <td class="<?= $isHoliday ? 'holiday' : '' ?>"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></td>
            <?php endfor; ?>
            <td class="summary"><?= $sumR ?></td>
            <td class="summary"><?= $sumY ?></td>
            <td class="summary"><?= $sumB ?></td>
            <td class="summary"><?= $total ?></td>
            <td class="money"><?= number_format($pay) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="<?= $daysInMonth + 2 ?>" class="summary" style="text-align:right;padding-right:8px;">รวม</td>
            <td class="summary"><?= $grandR ?></td>
            <td class="summary"><?= $grandY ?></td>
            <td class="summary"><?= $grandB ?></td>
            <td class="summary"><?= $grandTotal ?></td>
            <td class="money"><?= number_format($grandPay) ?></td>
        </tr>
        </tbody>
    </table>

    <div class="small" style="margin-top:8px;">
        <b>หมายเหตุ:</b> บ = เวรบ่าย, ร = เวรเรียกตาม On call, ย = วันหยุดราชการ
    </div>

    <table class="signature-table">
        <tr>
            <td>
                <span class="sig-box"><?= $preparedSignature ?></span>
                ลงชื่อ.......................................................ผู้จัดทำ<br>
                (<?= htmlspecialchars((string)($revision['prepared_name'] ?? '.......................................................'), ENT_QUOTES, 'UTF-8') ?>)<br>
                <?= htmlspecialchars((string)($revision['prepared_position'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                <span class="small"><?= htmlspecialchars((string)($revision['prepared_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td>
                <span class="sig-box"><?= $reviewedSignature ?></span>
                ลงชื่อ.......................................................ผู้ตรวจสอบ<br>
                (<?= htmlspecialchars((string)($revision['reviewed_name'] ?? '.......................................................'), ENT_QUOTES, 'UTF-8') ?>)<br>
                <?= htmlspecialchars((string)($revision['reviewed_position'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                <span class="small"><?= htmlspecialchars((string)($revision['reviewed_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td>
                <span class="sig-box"><?= $approvedSignature ?></span>
                ลงชื่อ.......................................................ผู้อนุมัติ<br>
                (<?= htmlspecialchars((string)($revision['approved_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)<br>
                <?= htmlspecialchars((string)($revision['approved_position'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                <span class="small"><?= htmlspecialchars((string)($revision['approved_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </td>
        </tr>
    </table>

    <table class="verification-table">
        <tr>
            <td style="width:150px;text-align:center;">
                <img src="<?= htmlspecialchars((string)($qrImageUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                     alt="QR Code ตรวจสอบเอกสาร"
                     width="130" height="130">
            </td>
            <td>
                <div style="font-size:12pt;font-weight:bold;margin-bottom:6px;">สแกน QR เพื่อตรวจสอบเอกสาร</div>
                <div style="font-size:9pt;margin-bottom:5px;">Verification Code</div>
                <div class="verification-code">
                    <?= htmlspecialchars((string)($revision['verification_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div style="font-size:9pt;margin-top:8px;">Verification URL</div>
                <div class="verify-url">
                    <?= htmlspecialchars((string)($verificationUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div style="font-size:8pt;margin-top:7px;color:#64748b;">
                    QR Code มีเฉพาะ URL สำหรับตรวจสอบ Revision และไม่บรรจุข้อมูลตารางเวรหรือค่าตอบแทน
                </div>
            </td>
        </tr>
    </table>

    <div class="integrity">
        <b>Document Integrity:</b>
        SHA-256 <?= htmlspecialchars((string)($revision['content_hash'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
        เอกสารฉบับนี้สร้างจากข้อมูล Revision แบบ immutable และผ่านการตรวจสอบความถูกต้องก่อนส่งออก
    </div>
</div>
</body>
</html>
