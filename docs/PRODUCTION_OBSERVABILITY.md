# Roster Pro — Production Observability & Reliability Runbook

## เป้าหมาย

Phase 6 เพิ่มระบบติดตามและกู้การทำงานของ Production โดยไม่ต้องพึ่งระบบภายนอกเพิ่มเติม:

- Error/Event Monitoring พร้อม fingerprint deduplication
- Request ID สำหรับ trace ปัญหา
- Durable Background Job Queue บน MariaDB
- Automatic Retry + Exponential Backoff
- Failed Job Center + Manual Retry
- Health Snapshot History
- Backup Retention
- Notification / Event / Job History Retention
- Public readiness health + strict deployment health

## 1. หลัง Deploy

รัน migration ตาม Phase 5:

~~~bash
php scripts/deploy_database.php --confirm=DEPLOY
~~~

ตรวจ:

~~~bash
php scripts/migrate.php --status
php scripts/health_check.php
~~~

เปิดหน้า Admin:

~~~text
/index.php?c=observability
~~~

## 2. Scheduler และ Worker

### Linux Cron

แนะนำ:

~~~cron
* * * * * cd /var/www/roster_pro && /usr/bin/php scripts/job_worker.php --max-jobs=20 >> storage/job-worker.log 2>&1
7 * * * * cd /var/www/roster_pro && /usr/bin/php scripts/schedule_jobs.php >> storage/job-scheduler.log 2>&1
~~~

Worker รันทุก 1 นาทีเพื่อดึงงานพร้อมทำ ส่วน scheduler รันทุกชั่วโมงและใช้ dedupe key ป้องกันสร้างงานซ้ำ

### Windows Task Scheduler / XAMPP

สร้าง 2 Tasks:

1. Job Worker
   - Program: path ไปยัง `php.exe`
   - Arguments: `scripts\job_worker.php --max-jobs=20`
   - Start in: project root
   - Repeat every 1 minute

2. Reliability Scheduler
   - Program: path ไปยัง `php.exe`
   - Arguments: `scripts\schedule_jobs.php`
   - Start in: project root
   - Repeat every 1 hour

## 3. Job Types ปัจจุบัน

- `IN_APP_NOTIFICATION` — แจ้งเตือนภายในระบบแบบ retry ได้
- `HEALTH_SNAPSHOT` — บันทึกสุขภาพระบบ
- `CLEANUP_NOTIFICATIONS` — ล้าง notification เก่า
- `BACKUP_RETENTION` — ลบ backup หมดอายุโดยคงจำนวนขั้นต่ำ
- `OBSERVABILITY_RETENTION` — ล้าง resolved events / completed jobs / health snapshots เก่า

## 4. Retry Policy

Background job มีสถานะ:

- `PENDING`
- `RUNNING`
- `RETRY`
- `DONE`
- `FAILED`

เมื่อ handler ล้มเหลว:

1. attempts เพิ่มขึ้น
2. ถ้ายังไม่ถึง max attempts → `RETRY`
3. available_at ถูกเลื่อนไปด้วย exponential backoff
4. ถ้าเกิน max attempts → `FAILED`
5. Admin ตรวจ error แล้วกด Retry จาก Failed Job Center ได้

Worker ที่ตายกลางทางจะทิ้งงาน `RUNNING`; worker รอบถัดไปเรียก stale recovery และนำงานกลับ `RETRY` หรือ `FAILED`

## 5. Error Monitoring

Front controller ลงทะเบียน `AppMonitor`

ระบบจับ:

- PHP Warning / Recoverable Error
- Uncaught Exception
- Fatal Error
- Background Job Exception

ทุก request มี:

~~~text
X-Request-ID: <id>
~~~

ใช้ Request ID เทียบกับ Dashboard เวลาผู้ใช้แจ้งปัญหา

### Event Deduplication

เหตุการณ์ซ้ำใช้ fingerprint เดียวกันและเพิ่ม `occurrence_count`

เมื่อ Admin กด Resolve:

- status → `RESOLVED`
- resolved_at / resolved_by ถูกบันทึก

ถ้า error fingerprint เดิมเกิดใหม่ ระบบจะ reopen เป็น `OPEN` โดยอัตโนมัติ

## 6. Privacy

AppMonitor redact context key ที่เกี่ยวข้องกับ:

- password / passcode
- token / secret / API key
- Authorization / Cookie / Session
- ID card
- bank / account

Dashboard ไม่ render `context_json` หรือ `payload_json` ดิบ

ห้ามนำ request body ทั้งชุดไปบันทึกใน observability context

## 7. Health States

### OK

- DB เชื่อมต่อได้
- migration ไม่มี pending/blocking
- ไม่มี critical error
- ไม่มี failed/delayed queue

### DEGRADED

ตัวอย่าง:

- มี open ERROR
- มี failed job
- queue delayed
- migration pending
- storage เหลือน้อย

Public health endpoint ยังตอบ HTTP 200 เพื่อไม่ให้ load balancer ตัด application ออกจาก traffic แต่สถานะ JSON เป็น `degraded`

### UNHEALTHY

ตัวอย่าง:

- DB ใช้งานไม่ได้
- migration blocking/checksum failure
- มี open CRITICAL event

Public health endpointตอบ HTTP 503

## 8. Health Endpoints

Public minimal readiness:

~~~text
/index.php?c=health&a=index
~~~

CLI strict release gate:

~~~bash
php scripts/health_check.php
~~~

CLI จะ exit non-zero เมื่อสถานะไม่ใช่ `ok` รวมถึง `degraded` เพื่อไม่ให้ deployment ผ่านโดยมี backlog/error ที่ยังไม่ได้จัดการ

## 9. Retention Environment

~~~text
BACKUP_RETENTION_DAYS=30
BACKUP_KEEP_MIN=5
NOTIFICATION_RETENTION_DAYS=30
OBSERVABILITY_RETENTION_DAYS=90
JOB_HISTORY_RETENTION_DAYS=30
JOB_STALE_SECONDS=600
~~~

### Backup Retention

ระบบจะ:

- เรียง backup ใหม่ → เก่า
- เก็บอย่างน้อย `BACKUP_KEEP_MIN` ไฟล์เสมอ
- ลบเฉพาะไฟล์ที่เกิน retention
- ลบ `.sha256` และ `.json` sidecar พร้อม backup
- ปฏิเสธการทำ retention หาก backup directory อยู่ใต้ `public/`

## 10. Incident Flow

เมื่อ Dashboard เป็น DEGRADED/UNHEALTHY:

1. ดู Open Error Events
2. ใช้ Request ID เทียบกับเหตุการณ์จากผู้ใช้
3. ดู occurrence count เพื่อจัดลำดับปัญหาที่เกิดบ่อย
4. ดู Failed Job Center
5. แก้ root cause ก่อนกด Retry
6. กด Retry job
7. รัน worker:
   ~~~bash
   php scripts/job_worker.php --max-jobs=20
   ~~~
8. Capture Health
9. Resolve event เมื่อยืนยันว่าแก้แล้ว
10. รัน:
    ~~~bash
    php scripts/health_check.php
    ~~~

## 11. Manual Commands

สร้าง scheduled jobs ตอนนี้:

~~~bash
php scripts/schedule_jobs.php
~~~

ประมวลผลงานหนึ่งรายการ:

~~~bash
php scripts/job_worker.php --once
~~~

ประมวลผลสูงสุด 100 งาน:

~~~bash
php scripts/job_worker.php --max-jobs=100
~~~

## 12. Release Gate เพิ่มเติม

ก่อนเปิด Production traffic:

- [ ] Migration Safety CI ผ่าน
- [ ] Runtime Smoke ผ่าน
- [ ] Job worker CI ผ่าน
- [ ] `php scripts/health_check.php` = OK
- [ ] Cron/Task Scheduler ของ worker เปิดใช้งาน
- [ ] Reliability scheduler เปิดใช้งาน
- [ ] Failed Jobs = 0
- [ ] Critical Open Events = 0
- [ ] Backup retention policy ตรวจสอบแล้ว
- [ ] หน้า `?c=observability` เปิดได้เฉพาะ ADMIN/SUPERADMIN
