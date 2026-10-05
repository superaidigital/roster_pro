# Roster Pro — High Availability & Production Cutover Runbook

## เป้าหมาย

Phase 9 ทำให้การนำระบบขึ้น Production และการสลับ release มี control plane ที่แยกจากฐานข้อมูล พร้อม health probes ที่เหมาะกับ load balancer และ fail-safe cutover workflow

องค์ประกอบหลัก:

- Database-independent Maintenance Mode
- Liveness / Readiness แยกกัน
- Immutable Release Identity
- Pre-Cutover Gate ที่ยอมรับ pending migrations อย่างปลอดภัย
- Final Go-Live Gate ที่ต้องเป็น OK เต็มรูปแบบ
- Cutover Orchestrator
- Health-gated Safe Resume
- Failure-safe behavior: cutover ล้มแล้ว maintenance คงเปิด
- CI ทดสอบ successful cutover และ failed cutover recovery

## 1. Release Identity

ทุก Production release ต้องกำหนด:

~~~text
APP_RELEASE_ID=<immutable-release-id>
~~~

ตัวอย่าง:

~~~text
APP_RELEASE_ID=2026.10.05-rc3-a1b2c3d
~~~

Release ID ควรอ้างอิง build/deployment artifact ที่ย้อนตรวจได้ เช่น Git commit, image tag หรือ release number

Health endpoint จะส่ง:

~~~text
X-Release-ID: <release-id>
~~~

และ JSON มี `release_id`

`scripts/cutover.php --release=...` จะปฏิเสธทันทีหากค่าไม่ตรงกับ `APP_RELEASE_ID` ของ instance ที่กำลัง deploy

## 2. Maintenance Control Plane

Maintenance state อยู่ที่:

~~~text
storage/runtime/maintenance.json
~~~

หรือ path ที่กำหนดด้วย:

~~~text
MAINTENANCE_STATE_DIR=storage/runtime
~~~

ไฟล์นี้:

- อยู่นอก `public/`
- เขียนแบบ temp + atomic rename
- permission พยายามตั้งเป็น 0600
- directory permission พยายามตั้งเป็น 0700
- ไม่พึ่งฐานข้อมูล

เหตุผลที่ไม่ใช้ `system_settings.maintenance_mode` เป็น traffic control เพราะช่วง migration หรือ DB outage ฐานข้อมูลอาจไม่พร้อมอ่านค่า

หน้า Settings จึงแสดงสถานะเท่านั้น ไม่เปิด/ปิด maintenance ผ่าน checkbox

## 3. Liveness Probe

Endpoint:

~~~text
/?c=health&a=live
~~~

หน้าที่:

- บอกว่า PHP application process ยังตอบสนอง
- ไม่ query ฐานข้อมูล
- ไม่ตรวจ migration
- ตอบ HTTP 200 แม้อยู่ใน maintenance
- แสดง release ID

ตัวอย่าง:

~~~json
{
  "status": "alive",
  "release_id": "2026.10.05-a1b2c3",
  "maintenance": true
}
~~~

ใช้สำหรับ container/process restart decision

ห้ามใช้ liveness เป็น traffic readiness

## 4. Readiness Probe

Endpoint:

~~~text
/?c=health&a=ready
~~~

หรือ backward-compatible:

~~~text
/?c=health&a=index
~~~

Readiness ตรวจ:

- maintenance control plane
- database
- migration state
- queue/reliability
- critical events

ผล:

- HTTP 200: รับ traffic ได้
- HTTP 503: ห้ามส่ง traffic เข้า instance

ระหว่าง maintenance:

~~~text
HTTP 503
Retry-After: <seconds>
~~~

แต่ liveness ยัง HTTP 200

## 5. Fail-Closed Control Plane

หาก maintenance state directory/config ใช้งานไม่ได้:

- Web traffic: 503
- Readiness: 503
- Liveness: 200 พร้อม `control_plane_ok=false`

นี่ช่วยแยก:

- process ยังมีชีวิต
- แต่ instance ไม่ปลอดภัยพอที่จะรับ user traffic

## 6. เปิด Maintenance ด้วยมือ

~~~bash
php scripts/maintenance.php enable \
  --confirm=MAINTENANCE \
  --reason="Scheduled production maintenance" \
  --retry-after=120 \
  --release="$APP_RELEASE_ID"
~~~

ตรวจ:

~~~bash
php scripts/maintenance.php status
~~~

การ disable โดยตรง:

~~~bash
php scripts/maintenance.php disable --confirm=RESUME
~~~

คำสั่ง disable โดยตรงควรใช้เฉพาะกรณีที่ operator ทราบสถานะระบบแน่นอน

สำหรับ Production หลัง deploy/rollback ให้ใช้ `resume_traffic.php` แทน เพราะมี gates ก่อนเปิด traffic

## 7. Pre-Cutover Gate

~~~bash
php scripts/cutover_precheck.php
~~~

Production precheck:

- Maintenance ต้องยังไม่เปิด
- APP_RELEASE_ID ต้องมี
- DB ต้องเชื่อมต่อได้
- migration ห้าม blocked
- pending migration อนุญาตได้ เพราะกำลังจะ deploy
- failed/delayed background jobs ต้องไม่มี
- open ERROR/CRITICAL ต้องไม่มี
- Production preflight แบบ strict
- Recovery readiness ต้อง PASS

Production ไม่อนุญาต:

~~~text
--skip-recovery
~~~

ใน development/CI สามารถใช้เพื่อทดสอบ flow ได้

## 8. Cutover

คำสั่งหลัก:

~~~bash
php scripts/cutover.php \
  --confirm=CUTOVER \
  --release="$APP_RELEASE_ID"
~~~

ถ้าต้อง legacy bridge:

~~~bash
php scripts/cutover.php \
  --confirm=CUTOVER \
  --release="$APP_RELEASE_ID" \
  --legacy-bridge
~~~

ลำดับ:

1. Cutover Precheck
2. เปิด Maintenance
3. Pre-deploy backup
4. Migration
5. Strict health
6. Performance contract
7. Go-Live Gate ขณะ maintenance
8. ปิด Maintenance
9. Final Go-Live Gate
10. CUTOVER_OK

## 9. Failure Behavior

ถ้า step หลังเปิด maintenance ล้ม:

~~~text
CUTOVER_FAILED_MAINTENANCE_REMAINS_ON
~~~

ระบบจะไม่ปิด maintenance อัตโนมัติ

นี่เป็น intentional safety behavior

ห้ามแก้โดยลบ `storage/runtime/maintenance.json` ทันที

ให้:

1. ตรวจ root cause
2. แก้ code/config/DB
3. ตรวจ backup
4. ตรวจ migration status
5. ตรวจ health
6. ใช้ Safe Resume

## 10. Safe Resume

หลังแก้ failed cutover หรือ rollback release แล้ว:

~~~bash
php scripts/resume_traffic.php --confirm=RESUME
~~~

ลำดับ:

1. Go-Live Gate โดยยอมให้ maintenance active
2. ต้องผ่าน health
3. ต้องผ่าน performance
4. ต้องผ่าน recovery readiness
5. ปิด maintenance
6. รัน Final Go-Live Gate ซ้ำ
7. TRAFFIC_RESUMED_OK

Production ไม่อนุญาต skip recovery

## 11. Go-Live Gate

~~~bash
php scripts/go_live_check.php
~~~

ต้องผ่าน:

- Maintenance inactive
- APP_RELEASE_ID configured
- DeploymentHealth = OK
- Production preflight
- Performance Contract
- Disaster Recovery readiness

ถ้ามี queue backlog, failed job, error event, migration pending หรือ recovery posture ไม่พร้อม จะไม่ควรเปิด traffic

## 12. Load Balancer / Reverse Proxy

แนะนำ:

Liveness:

~~~text
GET /?c=health&a=live
Expected: 200
~~~

Readiness:

~~~text
GET /?c=health&a=ready
Expected: 200
Failure: 503
~~~

Load balancer ควรนำ instance ออกจาก traffic เมื่อ readiness เป็น 503 แต่ไม่ restart process เพียงเพราะ maintenance

Process supervisor/container orchestrator ควรพิจารณา restart เมื่อ liveness ล้มเท่านั้น

## 13. Low-Downtime Deployment Pattern

หากมี 2 instances ขึ้นไป:

1. Instance A/B ให้บริการ
2. นำ A ออกจาก LB
3. deploy A
4. readiness A ต้อง 200
5. นำ A กลับเข้า LB
6. นำ B ออกจาก LB
7. deploy B
8. readiness B ต้อง 200
9. นำ B กลับเข้า LB

สำหรับ migration ที่ backward-compatible วิธีนี้ช่วยลด downtime

หาก migration ไม่ backward-compatible ให้ใช้ global maintenance + coordinated cutover

## 14. Database Migration Rule

เพื่อรองรับ rolling/low-downtime deployment:

แนะนำ migration แบบ Expand → Migrate → Contract

ตัวอย่าง:

1. Expand: เพิ่ม column/table/index ใหม่โดยไม่ลบของเดิม
2. Deploy code ที่อ่านได้ทั้ง schema เก่า/ใหม่
3. Migrate data
4. Deploy code ที่ใช้ schema ใหม่
5. Contract: ลบ schema เก่าใน release ภายหลัง

หลีกเลี่ยง DROP/RENAME ที่ทำให้ old instance ใช้งานไม่ได้ใน migration เดียวกับ code rollout

## 15. Rollback / Cutback

Phase 9 ไม่ restore database อัตโนมัติเมื่อ application cutover fail เพราะ database rollback อัตโนมัติอาจทำให้ข้อมูลใหม่สูญหาย

แนวทาง:

1. Maintenance ต้องเปิด
2. เปลี่ยน application artifact/symlink/container กลับ release ก่อนหน้า
3. ตั้ง APP_RELEASE_ID ให้ตรง release ที่ rollback
4. หาก schema ยัง compatible ไม่ต้อง restore DB
5. ถ้า schema incompatible ใช้ DR Runbook ประเมิน restore/cut-forward
6. รัน:
   ~~~bash
   php scripts/resume_traffic.php --confirm=RESUME
   ~~~
7. เปิด traffic เฉพาะเมื่อ gate ผ่าน

หลักคือ prefer **application rollback / database roll-forward** มากกว่าการ restore database โดยอัตโนมัติ

## 16. Production Environment

ขั้นต่ำ:

~~~text
APP_ENV=production
APP_BASE_URL=https://...
APP_RELEASE_ID=<immutable-id>

MAINTENANCE_STATE_DIR=storage/runtime
MAINTENANCE_RETRY_AFTER=120

DB_HOST=...
DB_NAME=...
DB_USER=<least-privilege-user>
DB_PASSWORD=<secret>

DR_MAX_RPO_SECONDS=86400
DR_MAX_RTO_SECONDS=900
DR_MAX_DRILL_AGE_DAYS=7
DR_ENFORCE_HEALTH=1
~~~

## 17. Go-Live Checklist

ก่อน Production traffic:

- [ ] APP_RELEASE_ID ตรง artifact ที่ deploy
- [ ] HTTPS ถูกต้อง
- [ ] Production preflight PASS แบบ strict
- [ ] Recovery Check PASS
- [ ] Restore Drill ล่าสุดไม่ stale
- [ ] RPO/RTO อยู่ในเป้า
- [ ] Migration ไม่มี blocking
- [ ] Queue failed/delayed = 0
- [ ] Open Critical/Error = 0
- [ ] Performance Contract PASS
- [ ] Liveness 200
- [ ] Readiness 200
- [ ] Maintenance inactive
- [ ] Backup ก่อน deploy ตรวจ checksum แล้ว
- [ ] Rollback artifact พร้อมใช้งาน
- [ ] Operator รู้วิธี Safe Resume

## 18. CI

Workflow:

~~~text
.github/workflows/high-availability.yml
~~~

CI ทดสอบ:

- liveness 200
- readiness 200
- เปิด maintenance
- liveness ยัง 200
- readiness กลายเป็น 503
- user route เป็น 503
- Retry-After ถูกต้อง
- Safe Resume
- successful cutover
- simulated failure หลัง maintenance เปิด
- failure ต้องคง maintenance ON
- health-gated recovery
- final readiness 200

รวมกับ workflows เดิม:

- PHP Lint
- Runtime Smoke
- Migration Safety
- Performance Regression
- Disaster Recovery Drill

ทุกชุดควรผ่านก่อน merge Phase 9
