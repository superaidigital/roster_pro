// ที่อยู่ไฟล์: public/js/app.js
// ชื่อไฟล์: app.js

function drag(ev) {
    const userId = ev.currentTarget.getAttribute('data-userid');
    const userName = ev.currentTarget.getAttribute('data-username');
    const color = ev.currentTarget.getAttribute('data-color');
    
    ev.dataTransfer.setData("userId", userId);
    ev.dataTransfer.setData("userName", userName);
    ev.dataTransfer.setData("color", color);
}

function allowDrop(ev) {
    ev.preventDefault();
}

function drop(ev) {
    ev.preventDefault();
    let target = ev.target;
    
    // หาเป้าหมายที่แท้จริง (.shift-cell หรือ TD)
    while(target && !target.classList.contains('shift-cell') && target.tagName !== 'TD') { 
        target = target.parentElement; 
    }

    if(!target) return;

    const date = target.getAttribute('data-date');
    const shift = target.getAttribute('data-shift');
    
    const userId = ev.dataTransfer.getData("userId");
    const userName = ev.dataTransfer.getData("userName");
    const color = ev.dataTransfer.getData("color");

    if(!userId || !date || !shift) return;

    // 1. ดักจับการลงกะซ้ำ
    const shiftCell = target.closest('.shift-cell');
    if (shiftCell) {
        const existingShift = shiftCell.querySelector(`.shift-badge[data-userid="${userId}"]`);
        if (existingShift) {
            alert(`ไม่สามารถจัดเวรได้!\n${userName} มีชื่อในเวรนี้อยู่แล้ว (ห้ามลงซ้ำกะเดียวกัน)`);
            return; 
        }
    }

    // 2. ตรวจสอบเงื่อนไข "วันลา"
    if (window.leaveData) {
        const isLeave = window.leaveData.find(l => l.user_id === userId && l.leave_date === date);
        if (isLeave) {
            alert(`ข้อผิดพลาด!\nไม่สามารถจัดเวรให้ ${userName} ได้\nเนื่องจากติด "${isLeave.leave_type}" ในวันที่ ${date}`);
            return;
        }
    }

    // 3. หา Container สำหรับแปะป้ายชื่อ (แก้ไขให้รองรับทั้งมุมมองสัปดาห์ และ มุมมองเดือน)
    let container = target.classList.contains('drop-container') ? target : target.querySelector('.drop-container');
    if(!container) return;

    fetch('index.php?c=ajax&a=save_shift', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId,
            date: date,
            shift_type: shift
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            const shiftId = data.shift_id;
            
            // ตรวจสอบว่าเป็นมุมมองเดือนหรือไม่ เพื่อสร้างป้ายชื่อขนาดให้เหมาะสม
            const isMonthView = container.classList.contains('flex-wrap');
            let badgeHTML = '';

            if (isMonthView) {
                // ป้ายชื่อแบบ "จิ๋ว" สำหรับตารางรายเดือน
                const shortName = userName.split(' ')[0].substring(0, 10);
                badgeHTML = `
                    <div class="shift-badge badge bg-white text-dark border shadow-sm px-1 py-0 me-1 mb-1 d-flex align-items-center group-hover" id="shift-${shiftId}" data-userid="${userId}" style="font-size: 10px; font-weight: 500;">
                        <span class="bg-${color} rounded-circle me-1" style="width: 4px; height: 4px;"></span>
                        <span class="text-truncate" style="max-width: 45px;">${shortName}</span>
                        <i class="bi bi-x text-danger ms-1 btn-delete d-none" style="cursor:pointer;" onclick="removeShift(${shiftId})"></i>
                    </div>
                `;
            } else {
                // ป้ายชื่อแบบ "ปกติ" สำหรับตารางรายสัปดาห์/วัน
                badgeHTML = `
                    <div class="shift-badge bg-white border rounded shadow-sm p-1 d-flex align-items-center position-relative group-hover" id="shift-${shiftId}" data-userid="${userId}">
                        <div class="bg-${color} rounded-pill me-2" style="width: 4px; height: 16px;"></div>
                        <span class="text-truncate fw-bold text-dark" style="font-size: 12px; flex-grow: 1;">${userName}</span>
                        <button class="btn btn-sm btn-danger p-0 px-1 btn-delete position-absolute end-0 me-1 d-none" onclick="removeShift(${shiftId})">
                            <i class="bi bi-trash" style="font-size:10px;"></i>
                        </button>
                    </div>
                `;
            }

            container.insertAdjacentHTML('beforeend', badgeHTML);
        } else {
            alert('ข้อผิดพลาดจากระบบ: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
    });
}

function removeShift(shiftId) {
    if(!confirm("คุณต้องการลบผู้ปฏิบัติงานออกจากเวรนี้ใช่หรือไม่?")) return;

    fetch('index.php?c=ajax&a=delete_shift', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ shift_id: shiftId })
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            const elementToRemove = document.getElementById('shift-' + shiftId);
            if(elementToRemove) {
                elementToRemove.style.transition = "opacity 0.2s";
                elementToRemove.style.opacity = "0";
                setTimeout(() => elementToRemove.remove(), 200);
            }
        } else { alert('เกิดข้อผิดพลาด: ' + data.message); }
    })
    .catch(error => { console.error('Error:', error); alert('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์'); });
}

function randomizeShifts(startDate, endDate) {
    if(!confirm("ระบบจะล้างตารางเวรเดิมที่อยู่ในช่วงวันที่หน้าจอ แล้วจัดเวรใหม่แบบสุ่มทั้งหมด\nคุณต้องการดำเนินการต่อหรือไม่?")) return;

    const btn = document.getElementById('btn-randomize');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>กำลังประมวลผล...';
    btn.disabled = true;

    fetch('index.php?c=ajax&a=randomize_shifts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ start_date: startDate, end_date: endDate })
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            window.location.reload();
        } else {
            alert('ข้อผิดพลาดจากระบบ: ' + data.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => { 
        console.error('Error:', error); 
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function clearShifts(startDate, endDate) {
    if(!confirm("⚠️ คำเตือน: ระบบจะทำการ 'ลบเวรทั้งหมด' ในช่วงวันที่กำลังแสดงผลอยู่นี้ทิ้งทั้งหมด\n\nคุณแน่ใจหรือไม่ที่จะล้างตาราง? (การกระทำนี้ไม่สามารถกู้คืนได้)")) return;

    const btn = document.getElementById('btn-clear');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>กำลังล้าง...';
    btn.disabled = true;

    fetch('index.php?c=ajax&a=clear_shifts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ start_date: startDate, end_date: endDate })
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            window.location.reload();
        } else {
            alert('ข้อผิดพลาดจากระบบ: ' + data.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => { 
        console.error('Error:', error); 
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}