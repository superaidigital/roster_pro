<?php
// ที่อยู่ไฟล์: views/roster/print.php

// 🌟 ฟังก์ชันช่วยคำนวณเรทเงิน
if (!function_exists('calculatePayRatesPHP')) {
    function calculatePayRatesPHP($staff, $pay_rates_db) {
        if (!empty($staff['pay_rate_id']) && !empty($pay_rates_db)) {
            foreach ($pay_rates_db as $group) {
                if ($group['id'] == $staff['pay_rate_id']) {
                    return ['ร' => $group['rate_r'], 'ย' => $group['rate_y'], 'บ' => $group['rate_b']];
                }
            }
        }
        return ['ร' => 0, 'ย' => 0, 'บ' => 0];
    }
}

$hospital_name = $hospital_name ?? 'หน่วยบริการ';
$hospital_info = $hospital_info ?? [];
$month_text = $month_text ?? '';
$thai_year = $thai_year ?? (date('Y') + 543);
$days_in_month = $days_in_month ?? 31;
$staffs = $staffs ?? [];
$shifts = $shifts ?? [];
$pay_rates_db = $pay_rates_db ?? [];

$month_year = $selected_month ?? date('Y-m'); 
if (isset($_GET['month'])) $month_year = $_GET['month'];
$exp = explode('-', $month_year);
$year_num = $exp[0] ?? date('Y');
$month_num = $exp[1] ?? date('m');

// สร้าง Cache สำหรับไฮไลท์วันหยุด
$holiday_cache = [];
for ($i = 1; $i <= $days_in_month; $i++) {
    $d_str = "$year_num-$month_num-" . str_pad($i, 2, '0', STR_PAD_LEFT);
    $day_of_week = date('N', strtotime($d_str));
    $is_holiday = ($day_of_week == 6 || $day_of_week == 7);
    
    if (isset($holidays) && is_array($holidays)) {
        if (in_array($d_str, $holidays)) $is_holiday = true;
    }
    $holiday_cache[$i] = $is_holiday;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พิมพ์ตารางเวร - <?= htmlspecialchars($hospital_name) ?></title>
    <!-- ใช้ฟอนต์ Sarabun เพื่อความเป็นทางการแบบเอกสารราชการ -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        /* 🌟 ตั้งค่าหน้ากระดาษ A4 แนวนอน (Landscape) สำหรับสั่ง Print */
        @page { 
            size: A4 landscape; 
            margin: 10mm 15mm; 
        }
        
        body { 
            font-family: 'Sarabun', sans-serif; 
            font-size: 13pt; 
            color: #000;
            background-color: #f4f6f9;
        }

        .print-container {
            background: #fff;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .text-center { text-align: center; }
        .text-left { text-align: left; padding-left: 5px; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: 700; }
        
        /* สไตล์ตารางหลัก */
        table.roster-table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-top: 15px;
            font-size: 11pt; 
        }
        table.roster-table th, table.roster-table td { 
            border: 1px solid #000; 
            padding: 3px 2px; 
            text-align: center; 
            vertical-align: middle; 
            word-wrap: break-word;
        }
        table.roster-table th { 
            background-color: #ffff99 !important; /* สีเหลืองอ่อนบังคับพริ้นต์ */
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
            font-weight: bold;
        }
        .summary-col { background-color: #ffff99 !important; -webkit-print-color-adjust: exact; font-weight: bold; }
        .money-col { background-color: #e2efda !important; -webkit-print-color-adjust: exact; font-weight: bold; }
        
        .bg-holiday { background-color: #fee2e2 !important; -webkit-print-color-adjust: exact; }
        .text-danger { color: #dc2626 !important; -webkit-print-color-adjust: exact; }

        /* สไตล์ลายเซ็นท้ายตาราง */
        table.signature-table {
            width: 100%;
            margin-top: 30px;
            border: none;
            page-break-inside: avoid; /* ป้องกันลายเซ็นขาดครึ่งหน้า */
        }
        table.signature-table td {
            border: none;
            text-align: center;
            vertical-align: top;
            font-size: 13pt;
            line-height: 1.5;
        }
        .note-text {
            font-size: 11pt;
            margin-top: 10px;
            line-height: 1.2;
        }

        /* 🌟 เครื่องมือควบคุม (เฉพาะตอนแสดงบนจอ ห้ามพริ้นต์ออกไป) */
        .controls {
            position: fixed;
            top: 20px; right: 20px;
            display: flex; gap: 10px;
            z-index: 1000;
        }
        .btn-print {
            background: #0d6efd; color: #fff; border: none; padding: 10px 20px;
            border-radius: 50rem; font-family: 'Sarabun'; font-weight: bold; cursor: pointer;
            box-shadow: 0 4px 10px rgba(13,110,253,0.3); font-size: 14pt;
        }
        .btn-close {
            background: #6c757d; color: #fff; border: none; padding: 10px 20px;
            border-radius: 50rem; font-family: 'Sarabun'; font-weight: bold; cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2); font-size: 14pt;
        }

        @media print {
            .controls { display: none !important; }
            .print-container { box-shadow: none; padding: 0; margin: 0; }
            body { background-color: #fff; }
        }
    </style>
</head>
<body onload="setTimeout(() => window.print(), 500)">

    <div class="controls">
        <button class="btn-print" onclick="window.print()"><i class="bi bi-printer-fill"></i> พิมพ์เอกสาร</button>
        <button class="btn-close" onclick="window.close()"><i class="bi bi-x-circle-fill"></i> ปิดหน้าต่าง</button>
    </div>

    <div class="print-container">
        <div class="text-center fw-bold" style="font-size: 15pt; line-height: 1.4;">
            รายละเอียดแนบท้ายคำสั่ง องค์การบริหารส่วนจังหวัดศรีสะเกษ ที่......../........ ลงวันที่......................................<br>
            ตารางเวรเจ้าหน้าที่ปฏิบัติงานในหน่วยบริการ นอกเวลาราชการและวันหยุดราชการ ประจำเดือน <?= $month_text ?> พ.ศ. <?= $thai_year ?><br>
            <?= htmlspecialchars($hospital_name) ?>
        </div>

        <table class="roster-table">
            <thead>
                <tr>
                    <th style="width: 2%;">ที่</th>
                    <th style="width: 15%;">ชื่อ-สกุล</th>
                    <?php for ($i = 1; $i <= $days_in_month; $i++): ?>
                        <th style="width: 2%;" class="<?= $holiday_cache[$i] ? 'bg-holiday text-danger' : '' ?>"><?= $i ?></th>
                    <?php endfor; ?>
                    <th style="width: 3%;">ร</th>
                    <th style="width: 3%;">ย</th>
                    <th style="width: 3%;">บ</th>
                    <th style="width: 4%;">รวม</th>
                    <th style="width: 6%;">ค่าเวร</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $grand_r = 0; $grand_y = 0; $grand_b = 0; $grand_total = 0; $grand_pay = 0;

                foreach ($staffs as $staff): 
                    $my_shifts = [];
                    $sum_r = 0; $sum_y = 0; $sum_b = 0;

                    foreach ($shifts as $s) {
                        if ($s['user_id'] == $staff['id']) {
                            $day = (int)date('d', strtotime($s['shift_date']));
                            $val = trim($s['shift_type']);
                            $my_shifts[$day] = $val;
                            
                            if ($val === 'ร' || $val === 'N') $sum_r++;
                            elseif ($val === 'ย' || $val === 'O') $sum_y++;
                            elseif ($val === 'บ' || $val === 'A') $sum_b++;
                            elseif ($val === 'บ/ร') { $sum_b++; $sum_r++; }
                            elseif ($val === 'ย/บ') { $sum_y++; $sum_b++; }
                        }
                    }

                    $total_shifts = $sum_r + $sum_y + $sum_b;
                    
                    $rates = calculatePayRatesPHP($staff, $pay_rates_db);
                    $pay = ($sum_r * $rates['ร']) + ($sum_y * $rates['ย']) + ($sum_b * $rates['บ']);

                    $grand_r += $sum_r; $grand_y += $sum_y; $grand_b += $sum_b;
                    $grand_total += $total_shifts; $grand_pay += $pay;
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-left">
                        <span class="fw-bold" style="font-size: 11pt;"><?= htmlspecialchars($staff['name']) ?></span><br>
                        <span style="font-size: 9pt; color: #555;"><?= htmlspecialchars($staff['type']) ?></span>
                    </td>
                    
                    <?php for ($i = 1; $i <= $days_in_month; $i++): 
                        $shift_val = isset($my_shifts[$i]) ? $my_shifts[$i] : '';
                        $is_holiday = $holiday_cache[$i];
                        $text_class = ($shift_val == 'ร' || $shift_val == 'ย') ? 'text-danger fw-bold' : '';
                    ?>
                        <td class="<?= $is_holiday ? 'bg-holiday' : '' ?> <?= $text_class ?>"><?= htmlspecialchars($shift_val) ?></td>
                    <?php endfor; ?>
                    
                    <td class="summary-col"><?= $sum_r ?></td>
                    <td class="summary-col"><?= $sum_y ?></td>
                    <td class="summary-col"><?= $sum_b ?></td>
                    <td class="summary-col text-primary"><?= $total_shifts ?></td>
                    <td class="money-col"><?= $pay > 0 ? number_format($pay) : '0' ?></td>
                </tr>
                <?php endforeach; ?>
                
                <tr>
                    <td colspan="<?= $days_in_month + 2 ?>" class="text-right fw-bold summary-col" style="padding-right: 15px;">รวม</td>
                    <td class="summary-col"><?= $grand_r ?></td>
                    <td class="summary-col"><?= $grand_y ?></td>
                    <td class="summary-col"><?= $grand_b ?></td>
                    <td class="summary-col text-primary"><?= $grand_total ?></td>
                    <td class="money-col"><?= number_format($grand_pay) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="note-text">
            <b>หมายเหตุ:</b> ตัวอักษรสีแดง หมายถึง เบิกค่าตอบแทนนอกเวลาราชการและวันหยุดราชการ<br>
            ปฏิบัติงานนอกเวลาราชการ ระหว่างเวลา 16.31 - 20.30 น. (บ) | 
            เวรเรียกตาม On call เวลา 20.31 - 08.29 น. (ร) และวันหยุดราชการระหว่างเวลา 08.30-16.30 น. (ย)
        </div>

        <table class="signature-table">
            <tr>
                <td style="width: 33%;">
                    ลงชื่อ.......................................................ผู้จัดทำเวร<br>
                    (.......................................................)<br>
                    .......................................................<br>
                </td>
                <td style="width: 33%;">
                    ลงชื่อ.......................................................ผู้ตรวจสอบ<br>
                    (.......................................................)<br>
                    .......................................................<br>
                </td>
                <td style="width: 33%;">
                    ลงชื่อ.......................................................ผู้ควบคุม<br>
                    (<?= htmlspecialchars($hospital_info['director_name'] ?? '.......................................................') ?>)<br>
                    <?= htmlspecialchars($hospital_info['director_position'] ?? 'ผู้อำนวยการ รพ.สต.') ?><br>
                    <span style="font-size: 11pt;">ปฏิบัติราชการแทน นายกองค์การบริหารส่วนจังหวัดศรีสะเกษ</span>
                </td>
            </tr>
        </table>

    </div>
</body>
</html>