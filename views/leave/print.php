<?php
// ที่อยู่ไฟล์: views/leave/print.php
// ฟังก์ชันแปลงวันที่เป็นภาษาไทย
function thai_date_full($date_str) {
    if (!$date_str) return "-";
    $thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $d = (int)date('d', strtotime($date_str));
    $m = (int)date('m', strtotime($date_str));
    $y = (int)date('Y', strtotime($date_str)) + 543;
    return "$d $thai_months[$m] $y";
}

$is_rest = (strpos($leave['leave_type_name'], 'พักผ่อน') !== false);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แบบใบ<?= htmlspecialchars($leave['leave_type_name']) ?> - <?= htmlspecialchars($leave['user_name']) ?></title>
    <!-- นำเข้าฟอนต์ TH Sarabun New ของราชการ -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Sarabun', sans-serif; 
            font-size: 16pt; /* ขนาดฟอนต์มาตรฐานราชการ */
            color: #000; 
            line-height: 1.4; 
            margin: 0;
            background-color: #525659; 
        }
        .page-a4 { 
            width: 210mm; 
            min-height: 297mm; 
            padding: 20mm 20mm 20mm 25mm; /* ขอบกระดาษ: บน ขวา ล่าง ซ้าย */
            margin: 10mm auto; 
            background: white; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.2); 
            box-sizing: border-box; 
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .header-title { font-size: 20pt; font-weight: bold; margin-bottom: 20px; text-align: center; }
        
        .row { display: flex; width: 100%; clear: both; }
        .col-right { width: 50%; margin-left: 50%; padding-left: 10mm; box-sizing: border-box; }
        
        .content-line { margin-bottom: 8px; }
        .indent { text-indent: 2.5cm; }
        
        .dotted-line { 
            border-bottom: 1px dotted #000; 
            display: inline-block; 
            min-width: 50px; 
            text-align: center; 
        }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14pt; }
        th, td { border: 1px solid #000; padding: 5px; text-align: center; }
        
        /* ซ่อนปุ่มต่างๆ เวลาสั่งพริ้นท์ลงกระดาษ */
        @media print {
            body { background: white; margin: 0; }
            .page-a4 { margin: 0; padding: 15mm 15mm 15mm 20mm; box-shadow: none; border: none; }
            .no-print { display: none !important; }
        }

        /* ตกแต่งแถบปุ่มกดด้านบน */
        .print-toolbar {
            background-color: #343a40; padding: 15px; text-align: center; position: sticky; top: 0; z-index: 1000;
        }
        .btn {
            padding: 8px 20px; font-family: 'Sarabun', sans-serif; font-size: 16pt; font-weight: bold;
            border: none; border-radius: 5px; cursor: pointer; margin: 0 5px;
        }
        .btn-primary { background-color: #0d6efd; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <!-- แถบเครื่องมือ (ซ่อนตอนพริ้นท์) -->
    <div class="print-toolbar no-print">
        <button class="btn btn-primary" onclick="window.print()">🖨️ สั่งพิมพ์เอกสาร</button>
        <button class="btn btn-danger" onclick="window.close()">❌ ปิดหน้าต่างนี้</button>
    </div>

    <!-- กระดาษ A4 -->
    <div class="page-a4">
        
        <!-- หัวเอกสาร -->
        <div class="text-right fw-bold" style="margin-bottom: 10px;">
            แบบใบ<?= htmlspecialchars($leave['leave_type_name']) ?>
        </div>
        
        <div class="col-right content-line" style="margin-top: 20px;">
            เขียนที่ <span class="dotted-line" style="width: 200px;"><?= htmlspecialchars($leave['hospital_name']) ?></span>
        </div>
        
        <div class="col-right content-line">
            วันที่ <span class="dotted-line" style="width: 40px;"><?= date('d', strtotime($leave['created_at'])) ?></span> 
            เดือน <span class="dotted-line" style="width: 80px;"><?= ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'][(int)date('m', strtotime($leave['created_at']))] ?></span> 
            พ.ศ. <span class="dotted-line" style="width: 50px;"><?= date('Y', strtotime($leave['created_at'])) + 543 ?></span>
        </div>
        
        <div class="content-line" style="margin-top: 20px;">
            <span class="fw-bold">เรื่อง</span> ขอ<?= htmlspecialchars($leave['leave_type_name']) ?>
        </div>
        
        <div class="content-line">
            <span class="fw-bold">เรียน</span> ผู้อำนวยการ<?= htmlspecialchars($leave['hospital_name']) ?>
        </div>

        <!-- เนื้อหา -->
        <div class="content-line indent" style="margin-top: 15px;">
            ข้าพเจ้า <span class="dotted-line" style="width: 250px;"><?= htmlspecialchars($leave['user_name']) ?></span> 
            ตำแหน่ง <span class="dotted-line" style="width: 200px;"><?= htmlspecialchars($leave['employee_type']) ?></span>
        </div>
        
        <div class="content-line">
            สังกัด <span class="dotted-line" style="width: 300px;"><?= htmlspecialchars($leave['hospital_name']) ?></span> 
            มีโควตา<?= htmlspecialchars($leave['leave_type_name']) ?>สะสม <span class="dotted-line" style="width: 50px;"><?= floatval($stat['carried']) ?></span> วันทำการ
        </div>

        <div class="content-line">
            ขอ<?= htmlspecialchars($leave['leave_type_name']) ?> เนื่องจาก <span class="dotted-line" style="width: 550px;"><?= htmlspecialchars($leave['reason']) ?></span>
        </div>

        <div class="content-line">
            ตั้งแต่วันที่ <span class="dotted-line" style="width: 200px;"><?= thai_date_full($leave['start_date']) ?></span> 
            ถึงวันที่ <span class="dotted-line" style="width: 200px;"><?= thai_date_full($leave['end_date']) ?></span>
        </div>

        <div class="content-line">
            มีกำหนด <span class="dotted-line" style="width: 60px;"><?= floatval($leave['num_days']) ?></span> วัน
        </div>

        <div class="content-line indent" style="margin-top: 15px;">
            ในระหว่างลาจะติดต่อข้าพเจ้าได้ที่ <span class="dotted-line" style="width: 450px;">.......................................................................................</span>
        </div>
        <div class="content-line">
            <span class="dotted-line" style="width: 100%;">....................................................................................................................................................................</span>
        </div>

        <!-- ลายเซ็น -->
        <div class="row" style="margin-top: 40px;">
            <div style="width: 40%;"></div> <!-- Spacer -->
            <div style="width: 60%; text-align: center;">
                <div class="content-line">(ลงชื่อ) ......................................................... ผู้ขอลา</div>
                <div class="content-line">( <?= htmlspecialchars($leave['user_name']) ?> )</div>
                <div class="content-line">........ / .................... / ........</div>
            </div>
        </div>

        <!-- กล่องตารางสถิติและการพิจารณา -->
        <div class="row" style="margin-top: 30px;">
            
            <!-- ฝั่งซ้าย: ตารางสถิติ -->
            <div style="width: 45%; padding-right: 15px;">
                <div class="fw-bold text-center" style="font-size: 14pt; border-bottom: 1px solid #000; padding-bottom: 5px;">สถิติการลาในปีงบประมาณนี้</div>
                <table>
                    <tr>
                        <th>ลามาแล้ว<br>(วันทำการ)</th>
                        <th>ลาครั้งนี้<br>(วันทำการ)</th>
                        <th>รวมเป็น<br>(วันทำการ)</th>
                    </tr>
                    <tr>
                        <td><?= floatval($stat['used'] - ($leave['status'] == 'APPROVED' ? $leave['num_days'] : 0)) ?></td>
                        <td><?= floatval($leave['num_days']) ?></td>
                        <td><?= floatval($stat['used'] + ($leave['status'] != 'APPROVED' ? $leave['num_days'] : 0)) ?></td>
                    </tr>
                </table>
                <div style="font-size: 12pt; text-align: center; margin-top: 20px;">
                    (ลงชื่อ) .............................................. ผู้ตรวจสอบ<br>
                    ( .............................................. )<br>
                    ........ / .................... / ........
                </div>
            </div>

            <!-- ฝั่งขวา: ช่องเซ็นอนุมัติ -->
            <div style="width: 55%; padding-left: 15px; border-left: 1px solid #000;">
                <div class="fw-bold" style="font-size: 14pt; margin-bottom: 10px;">ความเห็นผู้บังคับบัญชา / คำสั่ง</div>
                
                <div style="font-size: 14pt;">
                    [ &nbsp; ] อนุญาต<br>
                    [ &nbsp; ] ไม่อนุญาต เพราะ .....................................................<br>
                    .........................................................................................
                </div>

                <div style="text-align: center; margin-top: 40px; font-size: 14pt;">
                    (ลงชื่อ) ......................................................... <br>
                    ( ......................................................... )<br>
                    ตำแหน่ง ผู้อำนวยการ<?= htmlspecialchars($leave['hospital_name']) ?><br>
                    ........ / .................... / ........
                </div>
            </div>

        </div>

    </div>
</body>
</html>