# Roster Pro — Production Performance & Scalability Runbook

## เป้าหมาย

Phase 7 ลดภาระฐานข้อมูลและเตรียมระบบสำหรับข้อมูล/ผู้ใช้ที่เพิ่มขึ้น โดยยังคงความถูกต้องของ workflow เดิม:

- Index-friendly date range queries
- SQL-side aggregation แทนการโหลดข้อมูลจำนวนมากไปคำนวณใน PHP
- Short-lived aggregate dashboard cache
- Cache stampede protection ด้วย file lock
- Slow-request monitoring
- Performance diagnostics ในหน้า System Status
- Performance Contract CI
- Synthetic Performance Regression บน MariaDB 10.11

## 1. Dashboard Cache

Executive Dashboard cache เฉพาะข้อมูล aggregate ระดับองค์กร เช่น:

- จำนวนบุคลากร
- จำนวนผู้ขึ้นเวร
- pending leave / swap
- งบประมาณประมาณการ
- roster status
- workload ต่อหน่วย
- leave trend
- risk hospital
- daily usage ต่อหน่วย

ข้อมูลรายบุคคลต่อไปนี้ **ไม่เข้า shared file cache**:

- fatigue staff list
- recent leave list
- recent activity log

ค่าเริ่มต้น:

~~~text
DASHBOARD_CACHE_TTL=20
PERFORMANCE_CACHE_DIR=storage/cache
~~~

TTL ถูกจำกัดในระบบไว้ที่ 5–300 วินาที

ทุก Dashboard response ของผู้บริหารมี header:

~~~text
X-Dashboard-Cache: HIT|MISS
Server-Timing: dashboard;dur=<milliseconds>
~~~

ใช้ DevTools > Network ตรวจผลได้

## 2. Cache Safety

`SimpleCache`:

- อยู่ภายนอก `public/`
- directory permission พยายามตั้งเป็น 0700
- cache/lock file พยายามตั้งเป็น 0600
- ใช้ SHA-256 key filename
- เขียน temp file แล้ว atomic rename
- ใช้ `flock(LOCK_EX)` ป้องกัน cache stampede
- ตรวจ TTL ก่อนคืนค่า
- background worker ลบ expired cache และ stale lock files

หาก cache ใช้งานไม่ได้ Dashboard จะ fallback ไป query database โดยตรง

## 3. Query Optimization

Dashboard ใช้ date ranges:

~~~sql
shift_date >= :month_start
AND shift_date < :next_month_start
~~~

แทน:

~~~sql
shift_date LIKE 'YYYY-MM-%'
~~~

Daily log query ใช้:

~~~sql
created_at >= :day_start
AND created_at < :next_day_start
~~~

แทน:

~~~sql
DATE(created_at) = :date
~~~

ทำให้ B-tree index ใช้งานได้โดยไม่ต้อง apply function กับ indexed column

## 4. Budget Aggregation

เดิม Dashboard โหลด shift + pay rate ทุกแถวเข้า PHP แล้ว loop คำนวณ

Phase 7 เปลี่ยนเป็น:

~~~sql
COALESCE(SUM(CASE ... END), 0)
~~~

ฐานข้อมูลคืนยอดรวมเพียงค่าเดียว ลด:

- network transfer
- PHP memory
- PHP iteration
- request latency เมื่อจำนวน shift โตขึ้น

## 5. Scalability Indexes

Migration:

~~~text
database/migrations/20261005_performance_scalability.sql
~~~

เพิ่ม:

~~~text
shifts.idx_shifts_date_hospital_user
logs.idx_logs_created_id
logs.idx_logs_created_action_user
roster_status.idx_roster_status_month_status_hospital
shift_swaps.idx_shift_swaps_status_hospital_created
users.idx_users_active_scope
employee_licenses.idx_employee_licenses_status_expire_user
~~~

ตรวจ Production:

~~~bash
php scripts/performance_check.php
~~~

## 6. Slow Request Monitoring

Front controller ลงทะเบียน `PerformanceMonitor`

ค่าเริ่มต้น:

~~~text
SLOW_REQUEST_THRESHOLD_MS=1500
~~~

ถ้า request ช้ากว่า threshold จะบันทึก event:

~~~text
category = PERFORMANCE_SLOW_REQUEST
severity = WARNING
~~~

พร้อม:

- route
- duration_ms
- peak memory MB
- threshold

ไม่มีการเขียน performance event ใน request ปกติที่เร็วกว่า threshold

Slow request fingerprint แยกตาม route

## 7. System Status

Admin > Settings > System Status แสดง:

- Dashboard Cache TTL
- Slow Request Threshold
- PHP OPcache Enabled/Disabled
- จำนวน performance cache files
- ขนาด performance cache

สำหรับ Production แนะนำเปิด PHP OPcache

## 8. CI Gates

### Performance Contract

~~~bash
php scripts/performance_check.php
~~~

ตรวจ:

- required indexes
- EXPLAIN possible indexes
- dashboard date-range query pattern
- SQL budget aggregation
- ห้ามกลับไปใช้ `DATE(logs.created_at)`
- cache MISS → HIT โดย producer ทำงานเพียงครั้งเดียว

### Synthetic Performance Regression

~~~bash
php tests/performance_regression.php
~~~

Fixture โดยประมาณ:

- 4 hospitals
- 80 users
- 2,480 shifts
- 12,000 logs
- 600 leave requests
- 200 swap requests

ตรวจ:

- global executive aggregates
- local hospital scope
- bounded live-detail queries
- cache hit
- populated log query plan

Default CI guardrails:

~~~text
PERF_MAX_DASHBOARD_MS=5000
PERF_MAX_LIVE_MS=3000
PERF_MAX_CACHE_HIT_MS=1000
~~~

Threshold ตั้งใจกว้างเพื่อจับ regression ระดับใหญ่ ไม่ใช้เป็น SLA จริง

## 9. Production Tuning

แนะนำเริ่มต้น:

~~~text
DASHBOARD_CACHE_TTL=20
SLOW_REQUEST_THRESHOLD_MS=1500
~~~

ถ้าผู้ใช้พร้อมกันมาก:

- เพิ่ม Dashboard cache TTL เป็น 30–60 วินาที
- ตรวจ slow-request events ก่อนเพิ่ม hardware
- ตรวจ MariaDB slow query log
- ตรวจ OPcache
- ตรวจ disk/cache growth
- ใช้ `php scripts/performance_check.php` หลัง schema/index changes

ไม่แนะนำเพิ่ม TTL เกิน 5 นาทีสำหรับ operational dashboard

## 10. Performance Investigation Flow

เมื่อผู้ใช้แจ้งว่าระบบช้า:

1. เปิด System Health & Reliability
2. ดู `PERFORMANCE_SLOW_REQUEST`
3. ระบุ route ที่ช้า
4. ดู Request ID หากมี incident เดียวกัน
5. ตรวจ System Status
6. ตรวจ OPcache
7. ตรวจ Dashboard cache HIT/MISS
8. รัน:
   ~~~bash
   php scripts/performance_check.php
   ~~~
9. ตรวจ MariaDB slow query log
10. ใช้ EXPLAIN กับ query เป้าหมายก่อนเพิ่ม index ใหม่

## 11. Release Gate

ก่อนเปิด Production traffic:

- [ ] PHP Lint ผ่าน
- [ ] Code Quality ผ่าน
- [ ] Schema Contract ผ่าน
- [ ] Migration Safety ผ่าน
- [ ] Runtime Smoke ผ่าน
- [ ] Performance Contract ผ่านทั้ง upgrade/fresh schema
- [ ] Performance Regression ผ่าน
- [ ] `php scripts/performance_check.php` ผ่านบน Production schema
- [ ] storage/cache เขียนได้และไม่อยู่ใต้ public/
- [ ] OPcache ตรวจสอบแล้ว
- [ ] Slow Request threshold ตั้งเหมาะสม
