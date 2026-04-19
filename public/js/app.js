// public/js/app.js

document.addEventListener("DOMContentLoaded", function() {
    
    // โฟลเดอร์หลักของโปรเจกต์ (ปรับเปลี่ยนตามชื่อโฟลเดอร์จริงบน Server)
    const BASE_URL = '/roster_pro';

    // ฟังก์ชันดึงการแจ้งเตือน (AJAX)
    function fetchNotifications() {
        fetch(`${BASE_URL}/ajax/getUnreadNotifications`)
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    updateNotificationUI(data.count, data.data);
                }
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }

    // ฟังก์ชันอัปเดตหน้าจอ UI
    function updateNotificationUI(count, notifications) {
        const badge = document.getElementById('noti-count');
        const headerCount = document.getElementById('noti-header-count');
        const listContainer = document.getElementById('noti-list');

        if (!badge || !headerCount || !listContainer) return; // ป้องกัน Error หากไม่มีอิลิเมนต์ในหน้านี้

        // อัปเดตตัวเลขกระดิ่ง
        if (count > 0) {
            badge.style.display = 'inline-block';
            badge.innerText = count > 99 ? '99+' : count;
            headerCount.innerText = count;
        } else {
            badge.style.display = 'none';
            headerCount.innerText = 0;
        }

        // ล้างรายการเดิมและอัปเดตใหม่
        listContainer.innerHTML = ''; 
        
        if (notifications.length === 0) {
            listContainer.innerHTML = '<a href="#" class="dropdown-item text-center text-muted">ไม่มีการแจ้งเตือนใหม่</a>';
        } else {
            notifications.forEach(noti => {
                const item = document.createElement('a');
                item.href = "#"; 
                item.className = "dropdown-item noti-item";
                item.dataset.id = noti.id;
                
                // โครงสร้าง HTML สำหรับแต่ละการแจ้งเตือน
                item.innerHTML = `
                    <i class="fas fa-envelope mr-2"></i> ${noti.title || 'การแจ้งเตือนใหม่'}
                    <span class="float-right text-muted text-sm">${timeSince(new Date(noti.created_at))}</span>
                `;
                
                // Event Click สำหรับคลิกอ่านข้อความ
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    markAsRead(this.dataset.id, this);
                });

                listContainer.appendChild(item);
                
                // เส้นคั่นระหว่างรายการ
                const divider = document.createElement('div');
                divider.className = "dropdown-divider";
                listContainer.appendChild(divider);
            });
        }
    }

    // ฟังก์ชันส่งสถานะ "อ่านแล้ว" กลับไปยังเซิร์ฟเวอร์
    function markAsRead(id, element) {
        const formData = new FormData();
        formData.append('noti_id', id);

        fetch(`${BASE_URL}/ajax/markNotificationAsRead`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                element.style.opacity = '0.5'; // ทำเอฟเฟกต์สีจางเมื่ออ่านแล้ว
                setTimeout(() => fetchNotifications(), 500); // อัปเดตข้อมูลจำนวนทันที
            }
        });
    }

    // ตัวช่วยแปลงเวลาให้เป็นรูปแบบ "เพิ่งผ่านมา ... นาที"
    function timeSince(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " ปีที่แล้ว";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " เดือนที่แล้ว";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " วันที่แล้ว";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " ชั่วโมงที่แล้ว";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " นาทีที่แล้ว";
        return Math.floor(seconds) + " วินาทีที่แล้ว";
    }

    // เริ่มการทำงานครั้งแรกและตั้งเวลาอัปเดตทุก 30 วินาที
    fetchNotifications();
    setInterval(fetchNotifications, 30000);
});

// กำหนดค่าตั้งต้นของ SweetAlert2 (สามารถนำไปใช้กับหน้าอื่นๆ ได้ด้วย Toast.fire)
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
});