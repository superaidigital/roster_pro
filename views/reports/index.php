<?php
// ที่อยู่ไฟล์: views/reports/index.php

$thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$selected_month = $_GET['month'] ?? date('Y-m');
$exp = explode('-', $selected_month);
$display_month_text = $thai_months[(int)$exp[1]] . ' ' . ($exp[0] + 543);
?>
<style>
    body { background-color: #f4f6f9; font-family: 'Sarabun', sans-serif; }
    
    .report-card { 
        border: none; 
        border-radius: 1rem; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
        transition: transform 0.2s ease, box-shadow 0.2s ease; 
        background: #fff; 
        cursor: pointer; 
        text-decoration: none; 
        display: block; 
        color: inherit; 
    }
    .report-card:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 8px 25px rgba(0,0,0,0.08); 
        color: inherit;
    }
    
    .icon-circle { 
        width: 60px; 
        height: 60px; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 28px; 
        margin-bottom: 1.25rem; 
    }
</style>

<div class="container-fluid px-3 px-md-4 py-4 min-vh-100">
    
    <!-- 🌟 ส่วนหัว -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-3">
        <div>
            <h3 class="fw-black text-dark mb-1"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i> ศูนย์รวมรายงานสถิติ (Reports Center)</h3>
            <p class="text-muted mb-0" style="font-size: 14px;">เลือกประเภทรายงานที่คุณต้องการดูข้อมูล หรือส่งออกเป็นรูปแบบ Excel</p>
        </div>
    </div>

    <!-- 🌟 แจ้งเตือน -->
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-dark rounded-4 d-flex align-items-center mb-4 p-3 shadow-sm border-start border-warning border-4">
            <i class="bi bi-tools fs-5 me-3 text-warning"></i> <div class="fw-bold" style="font-size: 14px;"><?= $_SESSION['error_msg'] ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <!-- 🌟 Report Cards (เมนูเลือกรายงาน) เปลี่ยนลิงก์เป็น c=report ให้หมด -->
    <div class="row g-4">
        <!-- 1. รายงานสรุปค่าตอบแทน -->
        <div class="col-xl-3 col-md-6">
            <a href="index.php?c=report&a=pay_summary" class="report-card h-100 p-4 border-top border-success border-4">
                <div class="icon-circle bg-success bg-opacity-10 text-success"><i class="bi bi-cash-coin"></i></div>
                <h5 class="fw-bold text-dark mb-2">สรุปค่าตอบแทน</h5>
                <p class="text-muted small mb-0">รายงานยอดเงินค่าเวร/ค่าตอบแทนรายบุคคล ประจำเดือน สามารถแยกตามกลุ่มสายงานได้</p>
                <div class="mt-3 text-success fw-bold small"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</div>
            </a>
        </div>

        <!-- 2. รายงานภาระงาน (Workload) -->
        <div class="col-xl-3 col-md-6">
            <a href="index.php?c=report&a=workload" class="report-card h-100 p-4 border-top border-primary border-4">
                <div class="icon-circle bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-workspace"></i></div>
                <h5 class="fw-bold text-dark mb-2">ภาระงาน (Workload)</h5>
                <p class="text-muted small mb-0">สรุปจำนวนกะการปฏิบัติงาน (เช้า, บ่าย, ดึก) ของบุคลากร เพื่อดูความหนาแน่นและภาระงานรวม</p>
                <div class="mt-3 text-primary fw-bold small"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</div>
            </a>
        </div>

        <!-- 3. รายงานการลาหยุด -->
        <div class="col-xl-3 col-md-6">
            <a href="index.php?c=report&a=leave" class="report-card h-100 p-4 border-top border-danger border-4">
                <div class="icon-circle bg-danger bg-opacity-10 text-danger"><i class="bi bi-calendar2-x"></i></div>
                <h5 class="fw-bold text-dark mb-2">ประวัติการลาหยุด</h5>
                <p class="text-muted small mb-0">ดูประวัติการลางาน แยกตามประเภทการลา (ลาป่วย, ลาพักผ่อน ฯลฯ) ภายในหน่วยบริการ</p>
                <div class="mt-3 text-danger fw-bold small"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</div>
            </a>
        </div>

        <!-- 4. ทะเบียนข้อมูลบุคลากร -->
        <div class="col-xl-3 col-md-6">
            <a href="index.php?c=report&a=staff" class="report-card h-100 p-4 border-top border-warning border-4">
                <div class="icon-circle bg-warning bg-opacity-10 text-warning"><i class="bi bi-people"></i></div>
                <h5 class="fw-bold text-dark mb-2">ทะเบียนบุคลากร</h5>
                <p class="text-muted small mb-0">ส่งออกรายชื่อ ข้อมูลติดต่อ และตำแหน่งของบุคลากรทั้งหมด นำไปวิเคราะห์ต่อได้ทันที</p>
                <div class="mt-3 text-warning fw-bold small"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</div>
            </a>
        </div>
    </div>
    
</div>