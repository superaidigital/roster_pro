<?php
// ที่อยู่ไฟล์: views/roster/export_word.php

// 🌟 ฟังก์ชันช่วยคำนวณเรทเงิน (อัปเดตใหม่ให้ดึงจาก pay_rate_id โดยตรง)
if (!function_exists('calculatePayRatesPHP')) {
    function calculatePayRatesPHP($staff, $pay_rates_db) {
        // 1. ตรวจสอบว่าพนักงานคนนี้มีรหัสกลุ่มสายงาน (pay_rate_id) หรือไม่
        if (!empty($staff['pay_rate_id']) && !empty($pay_rates_db)) {
            foreach ($pay_rates_db as $group) {
                if ($group['id'] == $staff['pay_rate_id']) {
                    // ถ้าตรงกับกลุ่มไหน ให้ดึงเรทกลุ่มนั้นมาใช้เลย
                    return ['ร' => $group['rate_r'], 'ย' => $group['rate_y'], 'บ' => $group['rate_b']];
                }
            }
        }
        
        // 2. ถ้ายังไม่ได้จัดกลุ่ม (หรือหาไม่เจอ) ให้คืนค่าเป็น 0 เพื่อป้องกันความผิดพลาด
        return ['ร' => 0, 'ย' => 0, 'บ' => 0];
    }
}

// ป้องกันตัวแปรว่างกรณีไม่ได้โหลดมาจาก Controller โดยตรง
$hospital_name = $hospital_name ?? 'หน่วยบริการ';
$hospital_info = $hospital_info ?? [];
$month_text = $month_text ?? '';
$thai_year = $thai_year ?? (date('Y') + 543);
$days_in_month = $days_in_month ?? 31;
$staffs = $staffs ?? [];
$shifts = $shifts ?? [];
$pay_rates_db = $pay_rates_db ?? [];

// 🌟 ดึงข้อมูลวันเดือนปีสำหรับเช็ควันหยุด (ดึงจาก $selected_month ถ้ามี หรือสร้างจาก $month_text)
$month_year = $selected_month ?? date('Y-m'); 
if (isset($_GET['month'])) $month_year = $_GET['month'];
$exp = explode('-', $month_year);
$year_num = $exp[0] ?? date('Y');
$month_num = $exp[1] ?? date('m');

// 🌟 ดึงข้อมูลวันหยุดล่วงหน้า เพื่อทำไฮไลท์สีแดงอ่อน
$holiday_cache = [];
for ($i = 1; $i <= $days_in_month; $i++) {
    $d_str = "$year_num-$month_num-" . str_pad($i, 2, '0', STR_PAD_LEFT);
    $day_of_week = date('N', strtotime($d_str));
    
    $is_holiday = false;
    // เช็คเสาร์-อาทิตย์ (6=เสาร์, 7=อาทิตย์)
    if ($day_of_week == 6 || $day_of_week == 7) {
        $is_holiday = true;
    }
    // เช็ควันหยุดนักขัตฤกษ์ (ถ้ามีการโหลด Model มาแล้ว หรือมี Array ส่งมา)
    if (isset($holidayModel) && method_exists($holidayModel, 'isHoliday')) {
        if ($holidayModel->isHoliday($d_str)) $is_holiday = true;
    } elseif (isset($holidays) && is_array($holidays)) {
        if (in_array($d_str, $holidays)) $is_holiday = true;
    }
    
    $holiday_cache[$i] = $is_holiday;
}

// ตั้งค่า Header ให้เบราว์เซอร์รับรู้ว่าเป็นไฟล์ Microsoft Word (.doc)
header("Content-Type: application/vnd.ms-word; charset=utf-8");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-disposition: attachment;filename=ตารางเวร_{$hospital_name}_{$month_text}_{$thai_year}.doc");

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>ตารางเวร <?= $month_text ?></title>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        /* สั่งให้ MS Word ตั้งค่าหน้ากระดาษเป็นแนวนอน (Landscape) A4 อัตโนมัติ */
        @page Section1 { 
            size: 841.9pt 595.3pt; 
            mso-page-orientation: landscape; 
            margin: 20.0pt 20.0pt 20.0pt 20.0pt; /* ลดขอบกระดาษลงเพื่อให้พอดี 1 หน้า */
            mso-header-margin: 20.0pt; 
            mso-footer-margin: 20.0pt; 
            mso-paper-source: 0; 
        }
        div.Section1 { page: Section1; }

        body { 
            font-family: 'TH SarabunPSK', 'TH Sarabun New', sans-serif; 
            font-size: 14pt; /* ลดขนาดฟอนต์ภาพรวมลงเพื่อให้เนื้อหาพอดีหน้า */
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; padding-left: 5px; }
        .fw-bold { font-weight: bold; }
        
        /* สไตล์ตารางหลัก */
        table.roster-table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-top: 10px;
            font-size: 11pt; /* 🌟 ลดขนาดฟอนต์ในตารางเพื่อให้พอดีกับ A4 แนวนอน */
        }
        table.roster-table th, table.roster-table td { 
            border: 1px solid black; 
            padding: 1px 1px; /* ลด Padding */
            text-align: center; 
            vertical-align: middle; 
            mso-border-alt: solid windowtext .5pt;
            word-wrap: break-word;
        }
        table.roster-table th { 
            background-color: #ffff99; /* สีเหลืองอ่อนตามต้นฉบับ */
            font-weight: bold;
            padding: 3px 0px;
        }
        .summary-col { background-color: #ffff99; font-weight: bold; }
        .money-col { background-color: #e2efda; font-weight: bold; } /* สีเขียวอ่อนช่องจำนวนเงิน */
        
        /* 🌟 สีแดงอ่อนสำหรับวันหยุด */
        .bg-holiday {
            background-color: #ffe4e6;
        }

        /* สไตล์ลายเซ็นท้ายตาราง */
        table.signature-table {
            width: 100%;
            margin-top: 20px;
            border: none;
        }
        table.signature-table td {
            border: none;
            text-align: center;
            vertical-align: top;
            font-size: 14pt;
            line-height: 1.5;
        }
        .note-text {
            font-size: 12pt;
            margin-top: 10px;
            line-height: 1.2;
        }
        .text-danger { color: red; }
    </style>
</head>
<body>
<div class="Section1">

    <div class="text-center fw-bold" style="font-size: 16pt;">
        รายละเอียดแนบท้ายคำสั่ง องค์การบริหารส่วนจังหวัดศรีสะเกษ ที่......../........ ลงวันที่......................................<br>
        ตารางเวรเจ้าหน้าที่ปฏิบัติงานในหน่วยบริการ นอกเวลาราชการและวันหยุดราชการ ประจำเดือน <?= $month_text ?> พ.ศ. <?= $thai_year ?><br>
        <?= htmlspecialchars($hospital_name) ?>
    </div>

    <table class="roster-table">
        <thead>
            <tr>
                <th width="20">ที่</th>
                <th width="120">ชื่อ-สกุล</th>
                <?php for ($i = 1; $i <= $days_in_month; $i++): ?>
                    <!-- 🌟 ระบายสีแดงอ่อนที่หัวตารางสำหรับวันหยุด -->
                    <th width="16" class="<?= $holiday_cache[$i] ? 'bg-holiday' : '' ?>"><?= $i ?></th>
                <?php endfor; ?>
                <th width="20">ร</th>
                <th width="20">ย</th>
                <th width="20">บ</th>
                <th width="25">รวม</th>
                <th width="45">ค่าเวร</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            // ตัวแปรเก็บผลรวมทั้งหมดในแนวตั้ง
            $grand_r = 0; $grand_y = 0; $grand_b = 0; $grand_total = 0; $grand_pay = 0;

            foreach ($staffs as $staff): 
                $my_shifts = [];
                $sum_r = 0; $sum_y = 0; $sum_b = 0;

                // แมปข้อมูลเวรของแต่ละคนและนับจำนวน
                foreach ($shifts as $s) {
                    if ($s['user_id'] == $staff['id']) {
                        $day = (int)date('d', strtotime($s['shift_date']));
                        $val = trim($s['shift_type']);
                        $my_shifts[$day] = $val;
                        
                        // นับจำนวนตามประเภทกะ
                        if ($val === 'ร' || $val === 'N') $sum_r++;
                        elseif ($val === 'ย' || $val === 'O') $sum_y++;
                        elseif ($val === 'บ' || $val === 'A') $sum_b++;
                        elseif ($val === 'บ/ร') { $sum_b++; $sum_r++; }
                        elseif ($val === 'ย/บ') { $sum_y++; $sum_b++; }
                    }
                }

                $total_shifts = $sum_r + $sum_y + $sum_b;
                
                // คำนวณเงินค่าตอบแทน
                $rates = calculatePayRatesPHP($staff, $pay_rates_db);
                $pay = ($sum_r * $rates['ร']) + ($sum_y * $rates['ย']) + ($sum_b * $rates['บ']);

                // บวกเข้าผลรวมใหญ่ของทั้งหน่วยบริการ
                $grand_r += $sum_r;
                $grand_y += $sum_y;
                $grand_b += $sum_b;
                $grand_total += $total_shifts;
                $grand_pay += $pay;
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-left">
                    <span class="fw-bold" style="font-size: 11pt;"><?= htmlspecialchars($staff['name']) ?></span><br>
                    <span style="font-size: 9pt; color: #555;">
                        <?= htmlspecialchars($staff['type']) ?>
                    </span>
                </td>
                
                <?php for ($i = 1; $i <= $days_in_month; $i++): 
                    $shift_val = isset($my_shifts[$i]) ? $my_shifts[$i] : '';
                    $is_holiday = $holiday_cache[$i];
                    
                    // 🌟 เปลี่ยนสีตัวอักษรเป็นสีแดงหากเป็นกะ ร หรือ ย
                    $text_style = ($shift_val == 'ร' || $shift_val == 'ย') ? 'color: red; font-weight: bold;' : '';
                ?>
                    <!-- 🌟 ระบายสีแดงอ่อนสำหรับวันหยุด -->
                    <td class="<?= $is_holiday ? 'bg-holiday' : '' ?>" style="<?= $text_style ?>"><?= htmlspecialchars($shift_val) ?></td>
                <?php endfor; ?>
                
                <td class="summary-col"><?= $sum_r ?></td>
                <td class="summary-col"><?= $sum_y ?></td>
                <td class="summary-col"><?= $sum_b ?></td>
                <td class="summary-col"><?= $total_shifts ?></td>
                <td class="money-col"><?= $pay > 0 ? number_format($pay) : '0' ?></td>
            </tr>
            <?php endforeach; ?>
            
            <!-- แถวสรุปผลรวมด้านล่างสุด -->
            <tr>
                <td colspan="<?= $days_in_month + 2 ?>" class="text-center fw-bold summary-col" style="text-align: right; padding-right: 15px;">รวม</td>
                <td class="summary-col"><?= $grand_r ?></td>
                <td class="summary-col"><?= $grand_y ?></td>
                <td class="summary-col"><?= $grand_b ?></td>
                <td class="summary-col"><?= $grand_total ?></td>
                <td class="money-col"><?= number_format($grand_pay) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- หมายเหตุด้านล่างตาราง -->
    <div class="note-text">
        <b>หมายเหตุ:</b> วงกลมสีแดง หมายถึง เบิกค่าตอบแทนนอกเวลาราชการและวันหยุดราชการ<br>
        ปฏิบัติงานนอกเวลาราชการ ระหว่างเวลา 16.31 - 20.30 น. (บ) | 
        เวรเรียกตาม On call เวลา 20.31 - 08.29 น. (ร) และวันหยุดราชการระหว่างเวลา 08.30-16.30 น. (ย)
    </div>

    <!-- ส่วนลายเซ็นท้ายตาราง -->
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
                <span style="font-size: 12pt;">ปฏิบัติราชการแทน นายกองค์การบริหารส่วนจังหวัดศรีสะเกษ</span>
            </td>
        </tr>
    </table>

</div>
</body>
</html>