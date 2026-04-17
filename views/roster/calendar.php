<?php
// ที่อยู่ไฟล์: views/roster/calendar.php
// ชื่อไฟล์: calendar.php

$thai_days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
$thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thai_months_full = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

function getBsColor($color_theme) {
    if($color_theme == 'green') return 'success';
    if($color_theme == 'purple') return 'danger';
    if($color_theme == 'gray') return 'secondary';
    if($color_theme == 'blue') return 'info';
    return 'primary';
}

if ($view_mode == 'day') {
    $header_text = "วันที่: " . date('d', strtotime($start_date)) . " " . $thai_months[date('n', strtotime($start_date))] . " " . (date('Y', strtotime($start_date)) + 543);
} elseif ($view_mode == 'month') {
    $header_text = "เดือน: " . $thai_months_full[date('n', strtotime($start_date))] . " " . (date('Y', strtotime($start_date)) + 543);
} else {
    $header_text = "สัปดาห์: " . date('d', strtotime($start_date)) . " " . $thai_months[date('n', strtotime($start_date))] . " - " . date('d', strtotime($end_date)) . " " . $thai_months[date('n', strtotime($end_date))] . " " . (date('Y', strtotime($end_date)) + 543);
}

$staff_types = [];
foreach($staffs as $st) {
    if(!in_array($st['type'], $staff_types)) {
        $staff_types[] = $st['type'];
    }
}
sort($staff_types); 

// จำลองข้อมูลชื่อ รพ.สต. สำหรับแสดงในหน้าสรุป (ในระบบจริงควรดึงจาก $hospitalModel)
$hospital_names = [
    'h1' => 'รพ.สต. บ้านโคก',
    'h2' => 'รพ.สต. หนองแวง',
    'h3' => 'รพ.สต. โนนดินแดง'
];
?>

<!-- ส่งข้อมูลบุคลากรทั้งหมดแปลงเป็น JSON ให้ JavaScript เรียกใช้ทำนามบัตร -->
<script>
    window.staffData = <?= json_encode($staffs, JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="w-100 bg-light p-3 p-md-4 min-vh-100 d-flex flex-column">
    
    <div class="row g-3 flex-grow-1">
        
        <!-- 🌟 บังคับให้ขนาดตารางเวรเป็น col-xl-9 col-lg-8 เสมอ เพื่อให้แถบรายชื่ออยู่ด้านขวา -->
        <div class="col-xl-9 col-lg-8 d-flex flex-column" style="transition: all 0.3s ease;">
            <div class="bg-white rounded-3 shadow-sm border overflow-hidden flex-grow-1 d-flex flex-column print-no-border">
                
                <div class="p-3 border-bottom bg-light d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">ตารางปฏิบัติงาน (เวร): <?= $hospital_names[$current_user['hospital_id']] ?? 'รพ.สต.' ?></h5>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <?php if($roster_status == 'DRAFT'): ?>
                                <span class="badge rounded-pill px-3 py-2 fw-normal" style="background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;"><i class="bi bi-clock me-1"></i> กำลังจัดเวร (ร่าง/แก้ไข)</span>
                            <?php elseif($roster_status == 'SUBMITTED'): ?>
                                <span class="badge bg-primary rounded-pill px-3 py-2 fw-normal"><i class="bi bi-send me-1"></i> รอ ผอ. อนุมัติ</span>
                            <?php elseif($roster_status == 'APPROVED'): ?>
                                <span class="badge bg-success rounded-pill px-3 py-2 fw-normal"><i class="bi bi-check-circle me-1"></i> อนุมัติแล้ว</span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill px-3 py-2 fw-normal"><i class="bi bi-exclamation-circle me-1"></i> ยังไม่เริ่มจัด</span>
                            <?php endif; ?>
                            
                            <button class="btn btn-sm" style="background-color: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; border-radius: 50rem; font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#summaryModal">
                                <i class="bi bi-bar-chart-fill"></i> สรุปยอดปฏิบัติงาน
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <!-- ปุ่มสลับมุมมอง เหลือแค่ สัปดาห์ และ เดือน -->
                        <div class="btn-group shadow-sm me-xl-2" role="group">
                            <a href="index.php?c=roster&v=week&date=<?= $base_date ?>" class="btn btn-sm <?= $view_mode == 'week' ? 'btn-primary' : 'btn-light border text-secondary' ?> fw-bold">สัปดาห์</a>
                            <a href="index.php?c=roster&v=month&date=<?= date('Y-m-01', strtotime($base_date)) ?>" class="btn btn-sm <?= $view_mode == 'month' ? 'btn-primary' : 'btn-light border text-secondary' ?> fw-bold">เดือน</a>
                        </div>

                        <?php if($canEdit): ?>
                            <button id="btn-randomize" class="btn btn-sm btn-light border text-primary fw-bold" onclick="randomizeShifts('<?= $start_date ?>', '<?= $end_date ?>')">
                                <i class="bi bi-magic"></i> สุ่มจัดเวร
                            </button>
                            <button id="btn-clear" class="btn btn-sm btn-light border text-danger fw-bold me-2" onclick="clearShifts('<?= $start_date ?>', '<?= $end_date ?>')">
                                <i class="bi bi-trash"></i> ล้างตาราง
                            </button>
                        <?php endif; ?>

                        <button class="btn btn-sm btn-white border text-dark fw-bold" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์</button>

                        <?php if($current_user['role'] == 'SCHEDULER' && ($roster_status == 'DRAFT' || $roster_status == 'NOT_STARTED')): ?>
                            <form action="index.php?c=ajax&a=change_status" method="POST" class="m-0" onsubmit="return confirm('ยืนยันการส่งขออนุมัติ?');">
                                <input type="hidden" name="month_year" value="<?= $month_year ?>">
                                <input type="hidden" name="status" value="SUBMITTED">
                                <button type="submit" class="btn btn-sm btn-primary fw-bold shadow-sm"><i class="bi bi-send"></i> ส่งขออนุมัติ</button>
                            </form>
                        <?php endif; ?>

                        <?php if($current_user['role'] == 'DIRECTOR' && $roster_status == 'SUBMITTED'): ?>
                            <form action="index.php?c=ajax&a=change_status" method="POST" class="m-0 d-flex gap-2" onsubmit="return confirm('ยืนยันการดำเนินการ?');">
                                <input type="hidden" name="month_year" value="<?= $month_year ?>">
                                <button type="submit" name="status" value="DRAFT" class="btn btn-sm btn-outline-danger fw-bold bg-white"><i class="bi bi-arrow-return-left"></i> ส่งกลับแก้ไข</button>
                                <button type="submit" name="status" value="APPROVED" class="btn btn-sm btn-success fw-bold shadow-sm"><i class="bi bi-check-circle"></i> อนุมัติเวร</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center p-2 border-bottom bg-white gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <a href="index.php?c=roster&v=<?= $view_mode ?>&date=<?= $prev_date ?>" class="btn btn-sm btn-light rounded-circle text-muted">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <h6 class="mb-0 fw-bold text-dark mx-2"><?= $header_text ?></h6>
                        <a href="index.php?c=roster&v=<?= $view_mode ?>&date=<?= $next_date ?>" class="btn btn-sm btn-light rounded-circle text-muted">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                    
                    <div class="input-group input-group-sm shadow-sm" style="max-width: 250px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="rosterSearch" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อ, กะเวร หรือวันที่...">
                    </div>
                </div>

                <div class="flex-grow-1 overflow-auto position-relative bg-light bg-opacity-50">
                    
                    <!-- 🌟 กล่องข้อความแจ้งเตือน "ส่งกลับแก้ไข" จะแสดงเฉพาะตอนที่ตารางแก้ไขได้ (DRAFT) -->
                    <?php if($roster_status == 'DRAFT' && $canEdit && $current_user['role'] == 'SCHEDULER'): ?>
                        <div class="alert alert-warning m-3 border border-warning border-opacity-50 shadow-sm d-flex align-items-start rounded-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning mt-1"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">แจ้งเตือนสถานะตารางเวร</h6>
                                <span class="text-dark" style="font-size: 13px;">ตารางเวรเดือนนี้อยู่ในสถานะ <b>ร่าง</b> หรือ <b>ถูกส่งกลับให้แก้ไข</b> 
                                กรุณาตรวจสอบและแก้ไขข้อมูลให้เรียบร้อย จากนั้นกดปุ่ม <span class="badge bg-primary fw-normal px-2"><i class="bi bi-send"></i> ส่งขออนุมัติ</span> ที่มุมขวาบนอีกครั้ง</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($view_mode == 'month'): ?>
                        <!-- มุมมองรายเดือน -->
                        <div class="calendar-month-grid h-100 d-flex flex-column bg-white" id="rosterGrid">
                            <div class="row g-0 border-bottom bg-light text-center fw-bold text-dark" style="font-size: 13px;">
                                <div class="col py-2 text-danger">อา.</div>
                                <div class="col py-2">จ.</div>
                                <div class="col py-2">อ.</div>
                                <div class="col py-2">พ.</div>
                                <div class="col py-2">พฤ.</div>
                                <div class="col py-2">ศ.</div>
                                <div class="col py-2 text-danger">ส.</div>
                            </div>
                            
                            <?php
                            $first_day_of_month = date('w', strtotime($start_date)); 
                            $days_in_month = date('t', strtotime($start_date));
                            $current_day = 1;
                            $cell_count = 0;
                            
                            echo "<div class='row g-0 flex-grow-1'>";
                            for ($i = 0; $i < $first_day_of_month; $i++) {
                                echo "<div class='col border-end border-bottom bg-light bg-opacity-50'></div>";
                                $cell_count++;
                            }
                            
                            while ($current_day <= $days_in_month) {
                                $current_date_str = date('Y-m-', strtotime($start_date)) . str_pad($current_day, 2, '0', STR_PAD_LEFT);
                                $day_of_week = $cell_count % 7;
                                $isWeekend = ($day_of_week == 0 || $day_of_week == 6);
                                $holidayName = $holidayModel->isHoliday($current_date_str);
                                $bgClass = ($isWeekend || $holidayName) ? 'bg-danger bg-opacity-10' : 'bg-white';
                                
                                echo "<div class='col border-end border-bottom d-flex flex-column p-1 {$bgClass} roster-cell' style='min-height: 120px;'>";
                                $textClass = ($isWeekend || $holidayName) ? 'text-danger' : 'text-dark';
                                echo "<div class='text-end fw-bold {$textClass} mb-1' style='font-size: 12px;'>";
                                if($holidayName) echo "<span class='badge bg-danger bg-opacity-25 text-danger border border-danger p-1 me-1' style='font-size:9px;' title='{$holidayName}'><i class='bi bi-star-fill'></i></span>";
                                echo "{$current_day}</div>";
                                
                                $shiftTypes = ['morning' => ['color' => '#ea580c', 'bg' => '#fffaf0', 'label' => 'ช'], 
                                               'afternoon' => ['color' => '#4f46e5', 'bg' => '#f8fafc', 'label' => 'บ'], 
                                               'standby' => ['color' => '#059669', 'bg' => '#ecfdf5', 'label' => 'ร']];
                                
                                echo "<div class='d-flex flex-column gap-1 flex-grow-1'>";
                                foreach($shiftTypes as $type => $ui) {
                                    $dragEvents = $canEdit ? "ondragover='allowDrop(event)' ondrop='drop(event)'" : "";
                                    echo "<div class='shift-cell drop-container rounded border-dotted d-flex flex-wrap p-1' style='background-color: {$ui['bg']}; min-height: 24px;' data-date='{$current_date_str}' data-shift='{$type}' {$dragEvents}>";
                                    echo "<span class='fw-bold me-1' style='font-size: 9px; color: {$ui['color']};'>{$ui['label']}:</span>";
                                    
                                    foreach($shifts as $s) {
                                        if($s['shift_date'] == $current_date_str && $s['shift_type'] == $type) {
                                            $bs_color = getBsColor($s['color_theme']);
                                            $short_name = mb_substr($s['user_name'], 0, strpos($s['user_name'], ' ') ?: 10);
                                            // 🌟 แก้ไข: ใช้ \" ครอบรอบ {$s['user_id']} เพื่อป้องกัน Error "u1 is not defined"
                                            echo "<div class='shift-badge badge bg-white text-dark border shadow-sm px-1 py-0 me-1 mb-1 d-flex align-items-center group-hover cursor-pointer' id='shift-{$s['id']}' data-userid='{$s['user_id']}' style='font-size: 10px; font-weight: 500; transition: all 0.2s;' onclick='showStaffProfile(\"{$s['user_id']}\")'>
                                                    <span class='bg-{$bs_color} rounded-circle me-1' style='width: 4px; height: 4px;'></span>
                                                    <span class='text-truncate' style='max-width: 45px;'>{$short_name}</span>";
                                            if($canEdit) echo "<i class='bi bi-x text-danger ms-1 btn-delete d-none' style='cursor:pointer; font-size:12px;' onclick='event.stopPropagation(); removeShift({$s['id']})'></i>";
                                            echo "</div>";
                                        }
                                    }
                                    echo "</div>";
                                }
                                echo "</div></div>";
                                $current_day++; $cell_count++;
                                if ($cell_count % 7 == 0 && $current_day <= $days_in_month) echo "</div><div class='row g-0 flex-grow-1'>";
                            }
                            while ($cell_count % 7 != 0) { echo "<div class='col border-end border-bottom bg-light bg-opacity-50'></div>"; $cell_count++; }
                            echo "</div>"; 
                            ?>
                        </div>

                    <?php else: ?>
                        <!-- มุมมองรายสัปดาห์ -->
                        <table class="table table-bordered mb-0 bg-white" style="min-width: 800px; table-layout: fixed;">
                            <thead class="bg-light text-center align-middle sticky-top" style="font-size: 13px;">
                                <tr>
                                    <th width="12%" class="py-2 text-dark">วันที่</th>
                                    <th width="22%" class="py-2" style="color: #ea580c; background-color: #f8f9fa;"><i class="bi bi-brightness-high"></i> เช้า (08:30-16:30)</th>
                                    <th width="22%" class="py-2" style="color: #4f46e5; background-color: #f8f9fa;"><i class="bi bi-moon"></i> บ่าย (16:30-00:30)</th>
                                    <th width="22%" class="py-2" style="color: #059669; background-color: #f8f9fa;"><i class="bi bi-telephone"></i> รอ (On-Call)</th>
                                    <th width="22%" class="py-2 text-dark bg-light">หมายเหตุ / วันลา</th>
                                </tr>
                            </thead>
                            <tbody id="rosterTableBody">
                                <?php 
                                foreach($date_list as $date): 
                                    $timestamp = strtotime($date);
                                    $dayOfWeek = date('w', $timestamp);
                                    $dayName = $thai_days[$dayOfWeek];
                                    $dayNum = date('d', $timestamp);
                                    $monthName = $thai_months[date('n', $timestamp)];
                                    $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
                                    $holidayName = $holidayModel->isHoliday($date);
                                    $isHolidayOrWeekend = ($isWeekend || $holidayName);
                                    $bgClass = $isHolidayOrWeekend ? 'bg-danger bg-opacity-10' : '';
                                    $dragEvents = $canEdit ? 'ondragover="allowDrop(event)" ondrop="drop(event)"' : '';
                                ?>
                                <tr class="roster-row <?= $bgClass ?>">
                                    <td class="text-center align-middle p-2">
                                        <div class="fw-bold <?= $isHolidayOrWeekend ? 'text-danger' : 'text-dark' ?> fs-6"><?= $dayName ?></div>
                                        <div class="text-muted" style="font-size: 11px;"><?= $dayNum . ' ' . $monthName ?></div>
                                        <?php if($holidayName): ?>
                                            <div class="badge bg-danger bg-opacity-25 text-danger border border-danger mt-1 text-wrap w-100" style="font-size: 10px; line-height:1.2;">
                                                <?= htmlspecialchars($holidayName) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <?php 
                                    $shiftCols = ['morning' => '#fffaf0', 'afternoon' => '#f8fafc', 'standby' => '#ecfdf5'];
                                    foreach($shiftCols as $type => $bg): ?>
                                    <td class="shift-cell <?= $canEdit ? 'border-dashed' : '' ?> p-1 align-top" style="background-color: <?= $bg ?>;" data-date="<?= $date ?>" data-shift="<?= $type ?>" <?= $dragEvents ?>>
                                        <div class="d-flex flex-column gap-1 drop-container min-vh-25 h-100">
                                            <?php 
                                            foreach($shifts as $s) {
                                                if($s['shift_date'] == $date && $s['shift_type'] == $type) {
                                                    $color = getBsColor($s['color_theme']);
                                                    // 🌟 แก้ไข: ใช้ \" ครอบรอบ {$s['user_id']} เพื่อป้องกัน Error "u1 is not defined"
                                                    echo "<div class='shift-badge bg-white border rounded shadow-sm p-1 d-flex align-items-center position-relative group-hover cursor-pointer' id='shift-{$s['id']}' data-userid='{$s['user_id']}' onclick='showStaffProfile(\"{$s['user_id']}\")'>
                                                        <div class='bg-{$color} rounded-pill me-2' style='width: 4px; height: 16px;'></div>
                                                        <span class='text-truncate fw-bold text-dark' style='font-size: 12px; flex-grow: 1;'>{$s['user_name']}</span>";
                                                    if($canEdit) echo "<button class='btn btn-sm btn-danger p-0 px-1 btn-delete position-absolute end-0 me-1 d-none' onclick='event.stopPropagation(); removeShift({$s['id']})'><i class='bi bi-trash' style='font-size:10px;'></i></button>";
                                                    echo "</div>";
                                                }
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <?php endforeach; ?>

                                    <td class="p-1 align-top" style="background-color: #f8f9fa;">
                                        <?php 
                                        if(isset($leaves) && is_array($leaves)) {
                                            foreach($leaves as $leave) {
                                                if($leave['leave_date'] == $date) {
                                                    $leave_user_name = '';
                                                    foreach($staffs as $st) { 
                                                        if($st['id'] == $leave['user_id']) $leave_user_name = mb_substr($st['name'], 0, strpos($st['name'], ' ') ?: null); 
                                                    }
                                                    if(empty($leave_user_name)) $leave_user_name = 'เจ้าหน้าที่';
                                                    echo "<div class='badge bg-danger bg-opacity-10 text-danger border border-danger w-100 text-start mb-1 p-1' style='font-size:10px; white-space: normal; line-height: 1.2;'>
                                                            <i class='bi bi-person-dash-fill'></i> {$leave_user_name} ({$leave['leave_type']})
                                                          </div>";
                                                }
                                            }
                                        }
                                        ?>
                                        <?php if($canEdit): ?>
                                        <textarea class="form-control form-control-sm border-0 bg-transparent mt-1" style="font-size:11px; resize:none;" rows="2" placeholder="พิมพ์หมายเหตุ..."></textarea>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <?php if ($view_mode != 'month'): ?>
                <div class="p-2 border-top bg-white d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3" id="rosterPaginationWrapper">
                    <div class="text-muted small fw-medium" id="rosterPaginationInfo">แสดงข้อมูล...</div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="text-muted small mb-0 d-none d-sm-block">แสดง:</label>
                        <select id="rosterRowsPerPage" class="form-select form-select-sm text-secondary bg-light border-0 shadow-none cursor-pointer" style="width: auto;">
                            <option value="5">5 วัน</option>
                            <option value="7" selected>7 วัน</option>
                            <option value="14">14 วัน</option>
                            <option value="all">ทั้งหมด</option>
                        </select>
                        <nav><ul class="pagination pagination-sm mb-0 shadow-sm" id="rosterPaginationControls"></ul></nav>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- แถบรายชื่อบุคลากร (Sidebar) อยู่ด้านขวาเสมอ -->
        <div class="col-xl-3 col-lg-4">
            <div class="bg-white rounded-3 shadow-sm border p-0 h-100 d-flex flex-column position-relative" style="max-height: 800px;">
                
                <?php if(!$canEdit): ?>
                <div class="bg-warning bg-opacity-25 text-dark p-2 text-center border-bottom d-flex align-items-center justify-content-center gap-2" style="font-size: 13px; font-weight: bold;">
                    <i class="bi bi-lock-fill text-danger"></i> 
                    <?php 
                        if($roster_status == 'SUBMITTED') echo "รออนุมัติ (แก้ไขไม่ได้)";
                        elseif($roster_status == 'APPROVED') echo "อนุมัติแล้ว (แก้ไขไม่ได้)";
                        else echo "ตารางถูกล็อค";
                    ?>
                </div>
                <?php endif; ?>

                <div class="p-3 border-bottom bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold <?= !$canEdit ? 'text-muted' : 'text-dark' ?> mb-0 d-flex align-items-center">
                            <i class="bi bi-people-fill <?= !$canEdit ? 'text-muted' : 'text-primary' ?> me-2"></i> บุคลากร
                        </h6>
                        <span id="staffCountBadge" class="badge <?= !$canEdit ? 'bg-secondary' : 'bg-primary' ?> rounded-pill"><?= count($staffs) ?> คน</span>
                    </div>
                    
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <select id="staffHospitalFilter" class="form-select form-select-sm shadow-sm border-light font-monospace text-secondary" style="font-size: 11px;" <?= !$canEdit ? 'disabled' : '' ?>>
                                <option value="own">ในสังกัด</option>
                                <option value="external">นอกสังกัด</option>
                                <option value="all">ทั้งหมด</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <select id="staffTypeFilter" class="form-select form-select-sm shadow-sm border-light font-monospace text-secondary" style="font-size: 11px;" <?= !$canEdit ? 'disabled' : '' ?>>
                                <option value="all">ทุกตำแหน่ง</option>
                                <?php foreach($staff_types as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="input-group input-group-sm mb-2 shadow-sm">
                        <span class="input-group-text <?= !$canEdit ? 'bg-light' : 'bg-white' ?> border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="staffSearch" class="form-control border-start-0 ps-0 <?= !$canEdit ? 'bg-light' : '' ?>" placeholder="ค้นหาชื่อ..." <?= !$canEdit ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div class="flex-grow-1 overflow-auto p-2" style="background-color: <?= !$canEdit ? '#e9ecef' : '#f8fafc' ?>;" id="staffListContainer">
                    <?php 
                    foreach($staffs as $staff): 
                        $is_external = (isset($staff['hospital_id']) && $staff['hospital_id'] != $current_user['hospital_id']);
                        $bs_color = getBsColor($staff['color_theme']);
                        
                        // นับจำนวนเวรที่ได้รับมอบหมายในมุมมองปัจจุบัน
                        $mC = 0; $aC = 0; $stC = 0;
                        foreach($shifts as $s) {
                            if($s['user_id'] == $staff['id']) {
                                if($s['shift_type'] == 'morning') $mC++;
                                if($s['shift_type'] == 'afternoon') $aC++;
                                if($s['shift_type'] == 'standby') $stC++;
                            }
                        }
                        $totalShift = $mC + $aC + $stC;
                    ?>
                    <div class="card mb-2 shadow-sm border border-light staff-card <?= $canEdit ? 'draggable-staff' : 'locked-card' ?>" 
                         <?= $canEdit ? 'draggable="true" ondragstart="drag(event)"' : 'draggable="false"' ?>
                         data-userid="<?= $staff['id'] ?>" 
                         data-username="<?= $staff['name'] ?>"
                         data-color="<?= $bs_color ?>"
                         data-is-external="<?= $is_external ? 'true' : 'false' ?>"
                         data-staff-type="<?= htmlspecialchars($staff['type']) ?>">
                        
                        <div class="card-body p-2 d-flex align-items-center">
                            <div class="bg-light text-secondary rounded-circle d-flex justify-content-center align-items-center fw-bold me-2 border" style="width: 32px; height: 32px; font-size:14px;">
                                <?= mb_substr($staff['name'], 0, 1, 'UTF-8') ?>
                            </div>
                            <div class="flex-grow-1 text-truncate">
                                <h6 class="mb-0 fw-bold text-dark staff-name d-flex align-items-center" style="font-size: 13px;">
                                    <?= htmlspecialchars($staff['name']) ?>
                                    <?php if($is_external): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger ms-1" style="font-size: 8px; padding: 2px 4px;">นอก</span>
                                    <?php endif; ?>
                                </h6>
                                <div class="d-flex align-items-center mt-1">
                                    <span class="bg-<?= $bs_color ?> rounded-circle d-inline-block me-1" style="width: 6px; height: 6px;"></span>
                                    <span class="text-muted text-truncate staff-position" style="font-size: 10px;"><?= htmlspecialchars($staff['type']) ?></span>
                                </div>
                            </div>
                            <div class="text-end ms-1">
                                <span class="badge <?= !$canEdit ? 'bg-secondary' : 'bg-light text-dark' ?> border fw-bold" style="font-size: 10px;" title="จำนวนเวรในมุมมองนี้"><?= $totalShift ?> เวร</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
        
    </div>
</div>

<!-- Modal สรุปยอดปฏิบัติงาน (รวมบุคลากรนอกสังกัดที่ขึ้นเวรจริง) -->
<div class="modal fade" id="summaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 bg-light pb-3 rounded-top-4">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-bar-chart-fill text-primary me-2"></i> สรุปยอดปฏิบัติงานและชั่วโมงสะสม
                    <span class="fs-6 text-muted ms-2 fw-normal">(รวมข้อมูลคนในและคนนอกสังกัดที่มาช่วยราชการ)</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0 align-middle text-center">
                        <thead class="table-light text-secondary" style="font-size: 13px;">
                            <tr>
                                <th class="text-start px-4 py-3">ชื่อ - สกุล</th>
                                <th class="text-start py-3">ตำแหน่ง / ต้นสังกัด</th>
                                <th class="py-3" style="color: #ea580c; background-color: #fffaf0;">เวรเช้า</th>
                                <th class="py-3" style="color: #4f46e5; background-color: #f8fafc;">เวรบ่าย</th>
                                <th class="py-3" style="color: #059669; background-color: #ecfdf5;">เวรรอ (On-call)</th>
                                <th class="py-3 fw-bold bg-light text-dark border-start">รวมเวร</th>
                                <th class="py-3 fw-bold bg-primary text-white border-start">ชั่วโมง (ชม.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_m = 0; $total_a = 0; $total_st = 0; $total_all = 0; $grand_total_hours = 0;
                            
                            foreach($staffs as $staff): 
                                $is_external = (isset($staff['hospital_id']) && $staff['hospital_id'] != $current_user['hospital_id']);
                                
                                // นับจำนวนเวรที่ได้รับมอบหมายในมุมมองปัจจุบัน
                                $mC = 0; $aC = 0; $stC = 0;
                                foreach($shifts as $s) {
                                    if($s['user_id'] == $staff['id']) {
                                        if($s['shift_type'] == 'morning') $mC++;
                                        if($s['shift_type'] == 'afternoon') $aC++;
                                        if($s['shift_type'] == 'standby') $stC++;
                                    }
                                }
                                $totalShift = $mC + $aC + $stC;

                                // ตรรกะการแสดงผลบุคลากรนอกสังกัด:
                                // 1. ถ้าเป็นบุคลากรในสังกัดตัวเอง -> แสดงรายชื่อเสมอแม้จะไม่มีเวร
                                // 2. ถ้าเป็นบุคลากรนอกสังกัด -> แสดงเฉพาะคนที่มีชื่อเข้าเวรจริงในเดือนนั้นเท่านั้น เพื่อความกระชับ
                                if($is_external && $totalShift == 0) continue;

                                $staffHours = $totalShift * 8; // คิดที่กะละ 8 ชั่วโมง
                                $total_m += $mC; $total_a += $aC; $total_st += $stC; $total_all += $totalShift; $grand_total_hours += $staffHours;
                            ?>
                            <tr class="<?= $is_external ? 'bg-danger bg-opacity-10' : '' ?>">
                                <td class="text-start px-4 fw-medium text-dark">
                                    <?= htmlspecialchars($staff['name']) ?>
                                    <?php if($is_external): ?>
                                        <span class="badge bg-danger text-white ms-1" style="font-size: 9px; font-weight: normal;">ช่วยราชการ</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-start text-muted" style="font-size: 12px;">
                                    <?= htmlspecialchars($staff['type']) ?><br>
                                    <?php if($is_external): ?>
                                        <span class="text-danger fw-bold"><i class="bi bi-building"></i> <?= $hospital_names[$staff['hospital_id']] ?? 'สังกัดอื่น' ?></span>
                                    <?php else: ?>
                                        <span class="text-success"><i class="bi bi-house-door"></i> ในสังกัด</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #ea580c;"><?= $mC ?: '-' ?></td>
                                <td style="color: #4f46e5;"><?= $aC ?: '-' ?></td>
                                <td style="color: #059669;"><?= $stC ?: '-' ?></td>
                                <td class="fw-bold text-dark bg-light border-start"><?= $totalShift ?: '-' ?></td>
                                <td class="fw-bold text-primary border-start"><?= number_format($staffHours) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold text-dark">
                            <tr>
                                <td colspan="2" class="text-end px-4 py-3">รวมยอดสรุป (กะ/ชม.)</td>
                                <td class="py-3" style="color: #ea580c;"><?= $total_m ?></td>
                                <td class="py-3" style="color: #4f46e5;"><?= $total_a ?></td>
                                <td class="py-3" style="color: #059669;"><?= $total_st ?></td>
                                <td class="py-3 text-dark border-start"><?= $total_all ?></td>
                                <td class="py-3 text-primary fs-5 border-start"><?= number_format($grand_total_hours) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal นามบัตรข้อมูลบุคลากร -->
<div class="modal fade" id="staffProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div id="profile-cover" class="bg-primary" style="height: 80px;"></div>
            <div class="modal-body text-center pt-0 px-4 pb-4 position-relative">
                <div id="profile-avatar-bg" class="bg-white rounded-circle d-inline-flex justify-content-center align-items-center shadow-sm border border-4 border-white position-relative" style="width: 80px; height: 80px; margin-top: -40px; z-index: 1;">
                    <div id="profile-avatar" class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center fw-bold w-100 h-100" style="font-size: 32px;">อ</div>
                </div>
                <div id="profile-tag-container" class="mt-2 mb-1">
                    <span id="profile-tag" class="badge bg-light text-dark border fw-normal" style="font-size: 10px;">เจ้าหน้าที่ รพ.สต.</span>
                </div>
                <h5 id="profile-name" class="fw-bold text-dark mb-1 mt-1">ชื่อ-นามสกุล</h5>
                <p id="profile-type" class="text-secondary mb-3" style="font-size: 13px;">ตำแหน่งวิชาชีพ</p>
                <div class="bg-light rounded-3 p-3 text-start mb-3 border">
                    <div class="d-flex align-items-center mb-2 pb-2 border-bottom">
                        <div class="bg-white rounded p-1 shadow-sm me-2 text-muted"><i class="bi bi-person-badge fs-6"></i></div>
                        <div>
                            <div class="text-muted" style="font-size: 10px;">เลขที่ตำแหน่ง</div>
                            <div id="profile-position" class="fw-bold text-dark" style="font-size: 13px;">-</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-white rounded p-1 shadow-sm me-2 text-success"><i class="bi bi-telephone-fill fs-6"></i></div>
                        <div class="flex-grow-1">
                            <div class="text-muted" style="font-size: 10px;">เบอร์โทรศัพท์ติดต่อ</div>
                            <div id="profile-phone-text" class="fw-bold text-dark" style="font-size: 13px;">-</div>
                        </div>
                    </div>
                </div>
                <a href="#" id="profile-btn-call" class="btn btn-success w-100 fw-bold rounded-pill shadow-sm d-none"><i class="bi bi-telephone-outbound me-2"></i> โทรออกทันที</a>
                <button type="button" class="btn btn-light border w-100 fw-bold rounded-pill mt-2" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<style>
    .border-dashed { border: 1px dashed #cbd5e1; transition: all 0.2s ease; }
    .border-dashed:hover { background-color: #eff6ff !important; border-color: #3b82f6; }
    .border-dotted { border: 1px dotted #cbd5e1; transition: all 0.2s; }
    .border-dotted:hover { border-color: #3b82f6; background-color: white !important; }
    .drop-container { min-height: 60px; }
    .calendar-month-grid .drop-container { min-height: 24px; } 
    .draggable-staff { cursor: grab; transition: transform 0.1s; }
    .draggable-staff:hover { border-color: #93c5fd !important; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    .locked-card { background-color: #f8f9fa !important; opacity: 0.6; cursor: not-allowed; filter: grayscale(100%); border-color: #dee2e6 !important; }
    .group-hover:hover .btn-delete { display: block !important; }
    .cursor-pointer { cursor: pointer; }
    .shift-badge:hover { transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1) !important; border-color: #93c5fd !important; }
    @media print { .modal { display: none !important; } .modal-backdrop { display: none !important; } }
</style>

<script>
    window.leaveData = <?= isset($leaves) ? json_encode($leaves) : '[]' ?>;
    
    function showStaffProfile(userId) {
        const staff = window.staffData.find(s => s.id == userId);
        if (!staff) return;

        const colorMap = {'green':'success', 'purple':'danger', 'gray':'secondary', 'blue':'info', 'primary':'primary'};
        const bsColor = colorMap[staff.color_theme] || 'primary';
        
        document.getElementById('profile-name').textContent = staff.name;
        document.getElementById('profile-type').textContent = staff.type;
        document.getElementById('profile-position').textContent = (staff.position_number && staff.position_number !== '-') ? staff.position_number : 'ไม่มีข้อมูล';
        document.getElementById('profile-avatar').textContent = staff.name ? staff.name.charAt(0) : 'อ';
        document.getElementById('profile-cover').className = `bg-${bsColor}`;
        document.getElementById('profile-avatar').className = `bg-${bsColor} bg-opacity-10 text-${bsColor} rounded-circle d-flex justify-content-center align-items-center fw-bold w-100 h-100`;

        const currentHospitalId = "<?= $current_user['hospital_id'] ?>";
        const tagEl = document.getElementById('profile-tag');
        if (staff.hospital_id !== currentHospitalId) {
            tagEl.className = "badge bg-danger bg-opacity-10 text-danger border border-danger fw-normal";
            tagEl.textContent = "บุคลากรนอกสังกัด";
        } else {
            tagEl.className = "badge bg-primary bg-opacity-10 text-primary border border-primary fw-normal";
            tagEl.textContent = "บุคลากรในสังกัด รพ.สต.";
        }

        const phoneTextEl = document.getElementById('profile-phone-text');
        const btnCallEl = document.getElementById('profile-btn-call');
        if (staff.phone && staff.phone !== '-' && staff.phone.trim() !== '') {
            phoneTextEl.textContent = staff.phone;
            btnCallEl.href = `tel:${staff.phone.replace(/[^0-9]/g, '')}`;
            btnCallEl.classList.remove('d-none');
        } else {
            phoneTextEl.textContent = 'ไม่มีข้อมูลเบอร์โทรศัพท์';
            btnCallEl.classList.add('d-none');
        }

        const profileModal = new bootstrap.Modal(document.getElementById('staffProfileModal'));
        profileModal.show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const rosterSearch = document.getElementById('rosterSearch');
        const rosterTableBody = document.getElementById('rosterTableBody');
        
        if (rosterTableBody) {
            const rows = Array.from(rosterTableBody.querySelectorAll('.roster-row'));
            const paginationInfo = document.getElementById('rosterPaginationInfo');
            const rowsPerPageSelect = document.getElementById('rosterRowsPerPage');
            const paginationControls = document.getElementById('rosterPaginationControls');

            let currentPage = 1;
            let rowsPerPage = 7; 
            let filteredRows = [...rows];

            function updateRosterTable() {
                const searchTerm = rosterSearch ? rosterSearch.value.toLowerCase().trim() : '';
                filteredRows = rows.filter(row => row.textContent.toLowerCase().includes(searchTerm));
                const totalRows = filteredRows.length;
                const totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(totalRows / rowsPerPage);

                if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;

                const startIdx = rowsPerPage === 'all' ? 0 : (currentPage - 1) * rowsPerPage;
                const endIdx = rowsPerPage === 'all' ? totalRows : startIdx + rowsPerPage;

                rows.forEach(row => row.style.display = 'none');
                filteredRows.slice(startIdx, endIdx).forEach(row => row.style.display = '');

                if(paginationInfo) paginationInfo.innerHTML = `แสดง <b>${totalRows > 0 ? startIdx + 1 : 0}</b> ถึง <b>${Math.min(endIdx, totalRows)}</b> จาก <b>${totalRows}</b> วัน`;

                if(paginationControls) {
                    paginationControls.innerHTML = '';
                    if (totalPages > 1) {
                        let prevLi = document.createElement('li');
                        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
                        prevLi.innerHTML = `<a class="page-link cursor-pointer">&laquo;</a>`;
                        if(currentPage > 1) prevLi.onclick = () => { currentPage--; updateRosterTable(); };
                        paginationControls.appendChild(prevLi);

                        for(let i=1; i<=totalPages; i++) {
                            let li = document.createElement('li');
                            li.className = `page-item ${currentPage === i ? 'active' : ''}`;
                            li.innerHTML = `<a class="page-link cursor-pointer">${i}</a>`;
                            li.onclick = () => { currentPage = i; updateRosterTable(); };
                            paginationControls.appendChild(li);
                        }

                        let nextLi = document.createElement('li');
                        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
                        nextLi.innerHTML = `<a class="page-link cursor-pointer">&raquo;</a>`;
                        if(currentPage < totalPages) nextLi.onclick = () => { currentPage++; updateRosterTable(); };
                        paginationControls.appendChild(nextLi);
                    }
                }
            }

            if(rosterSearch) rosterSearch.addEventListener('input', () => { currentPage = 1; updateRosterTable(); });
            if(rowsPerPageSelect) {
                rowsPerPageSelect.addEventListener('change', function() {
                    rowsPerPage = this.value === 'all' ? 'all' : parseInt(this.value);
                    currentPage = 1;
                    updateRosterTable();
                });
            }
            updateRosterTable(); 
        }

        const staffSearch = document.getElementById('staffSearch');
        const staffHospitalFilter = document.getElementById('staffHospitalFilter');
        const staffTypeFilter = document.getElementById('staffTypeFilter'); 
        const staffCards = document.querySelectorAll('.staff-card');
        const staffCountBadge = document.getElementById('staffCountBadge');
        
        function applyStaffFilters() {
            const term = staffSearch ? staffSearch.value.toLowerCase().trim() : '';
            const hospitalCondition = staffHospitalFilter ? staffHospitalFilter.value : 'own';
            const typeCondition = staffTypeFilter ? staffTypeFilter.value : 'all';
            
            let visibleCount = 0; 
            staffCards.forEach(card => {
                const name = card.querySelector('.staff-name').textContent.toLowerCase();
                const position = card.getAttribute('data-staff-type'); 
                const isExternal = card.getAttribute('data-is-external') === 'true';
                const matchSearch = name.includes(term) || position.toLowerCase().includes(term);
                
                let matchHospital = false;
                if (hospitalCondition === 'all') matchHospital = true;
                else if (hospitalCondition === 'own' && !isExternal) matchHospital = true;
                else if (hospitalCondition === 'external' && isExternal) matchHospital = true;

                let matchType = (typeCondition === 'all' || position === typeCondition);
                
                if(matchSearch && matchHospital && matchType) {
                    card.style.display = '';
                    visibleCount++; 
                } else {
                    card.style.display = 'none'; 
                }
            });
            if (staffCountBadge) staffCountBadge.textContent = visibleCount + ' คน';
        }

        if(staffSearch) staffSearch.addEventListener('input', applyStaffFilters);
        if(staffHospitalFilter) staffHospitalFilter.addEventListener('change', applyStaffFilters);
        if(staffTypeFilter) staffTypeFilter.addEventListener('change', applyStaffFilters);
        applyStaffFilters();
    });
</script>