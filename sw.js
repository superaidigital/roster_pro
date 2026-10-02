// ที่อยู่ไฟล์: sw.js (ต้องวางไว้ที่โฟลเดอร์ Root นอกสุด คู่กับ index.php)

const CACHE_NAME = 'rosterpro-cache-v1.0';

// ไฟล์คงที่ที่ต้องการแคชเก็บไว้ในเครื่องเพื่อความรวดเร็ว
const urlsToCache = [
    './manifest.json',
    // หากมีไฟล์ CSS/JS ของตัวเองในเครื่อง สามารถเพิ่มลงในนี้ได้ เช่น
    // './assets/css/style.css',
];

// 1. Install Event: ติดตั้ง Service Worker และโหลดไฟล์เข้า Cache
self.addEventListener('install', event => {
    self.skipWaiting(); // บังคับให้ SW ทำงานทันทีโดยไม่ต้องรอ
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
    );
});

// 2. Activate Event: เคลียร์ Cache เวอร์ชั่นเก่าทิ้ง
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// 3. Fetch Event: ดักจับการดึงข้อมูล (Network-First Strategy)
// เหมาะสำหรับ PHP เพราะเราต้องการข้อมูลที่อัปเดตล่าสุดเสมอ แต่ถ้าเน็ตหลุดถึงจะไปดึงจากแคช
self.addEventListener('fetch', event => {
    // ข้ามการแคชสำหรับ API หรือ Method POST เพื่อป้องกันการทำงานผิดพลาด
    if (event.request.method !== 'GET' || event.request.url.includes('api')) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // ถ้าโหลดจากเน็ตสำเร็จ ให้เอาไปอัปเดตใน Cache ด้วย
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }
                const responseToCache = response.clone();
                caches.open(CACHE_NAME)
                    .then(cache => {
                        cache.put(event.request, responseToCache);
                    });
                return response;
            })
            .catch(() => {
                // กรณี Offline (เน็ตหลุด) ให้พยายามดึงข้อมูลจาก Cache มาแสดงแทน
                return caches.match(event.request);
            })
    );
});