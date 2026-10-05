# Roster Pro — Disaster Recovery & Business Continuity Runbook

## เป้าหมาย

Phase 8 ทำให้การสำรองข้อมูลเป็นกระบวนการที่พิสูจน์การกู้คืนได้จริง ไม่ใช่เพียงมีไฟล์ backup

ระบบใช้หลักสำคัญ:

- Backup integrity ด้วย SHA-256
- Restore-safe dump format v2
- Isolated restore drill ไปยังฐาน `dr_drill_*`
- RPO/RTO ที่วัดได้
- Critical table verification
- Automatic cleanup หลัง drill
- Recovery readiness gate
- DR history ใน Observability
- CI restore drill บน MariaDB 10.11

## 1. เป้าหมาย RPO / RTO

ค่าเริ่มต้น:

~~~text
DR_MAX_RPO_SECONDS=86400
DR_MAX_RTO_SECONDS=900
DR_MAX_DRILL_AGE_DAYS=7
~~~

ความหมาย:

- RPO 86,400 วินาที = ยอมรับข้อมูลย้อนหลังได้สูงสุด 24 ชั่วโมง
- RTO 900 วินาที = เป้าหมายกู้คืนไม่เกิน 15 นาที
- Restore Drill ต้องมีผล PASS อย่างน้อยทุก 7 วัน

สำหรับระบบที่ข้อมูลเปลี่ยนบ่อย ควรลด RPO และเพิ่มความถี่ backup

## 2. Backup Format v2

Backup รุ่นใหม่สร้างด้วย:

~~~bash
php scripts/backup_database.php --label=scheduled
~~~

Manifest จะมี:

~~~json
{
  "format_version": 2,
  "restore_scope": "database_contents",
  "contains_database_ddl": false
}
~~~

Format v2 จงใจไม่มี:

~~~sql
CREATE DATABASE ...
DROP DATABASE ...
ALTER DATABASE ...
USE ...
~~~

เพื่อลดความเสี่ยงที่ restore drill จะเปลี่ยนฐาน Production โดยไม่ตั้งใจ

## 3. Routine Backup

แนะนำให้เรียกทุกชั่วโมง แต่ให้ script ตัดสินใจเองว่าถึงรอบ backup หรือยัง:

~~~bash
php scripts/backup_if_due.php
~~~

ค่าเริ่มต้น:

~~~text
BACKUP_INTERVAL_SECONDS=21600
~~~

เท่ากับสร้าง routine backup ทุก 6 ชั่วโมง

ตัวอย่าง Linux cron:

~~~cron
5 * * * * cd /var/www/roster_pro && /usr/bin/php scripts/backup_if_due.php >> storage/backup-cron.log 2>&1
~~~

ตัวอย่าง Windows Task Scheduler:

~~~text
Program: C:\xampp\php\php.exe
Arguments: C:\xampp\htdocs\roster_pro\scripts\backup_if_due.php
Schedule: Hourly
~~~

## 4. Verify Backup

ทุก backup ต้องผ่าน:

~~~bash
php scripts/verify_backup.php --file=storage/backups/<backup>.sql.gz
~~~

ระบบตรวจ:

- ไฟล์อยู่ใน BACKUP_DIR
- checksum sidecar มีรูปแบบถูกต้อง
- SHA-256 ตรง
- manifest checksum ตรง
- manifest filename ตรง

## 5. Restore Drill

ใช้ backup ล่าสุดที่ label เป็น scheduled:

~~~bash
php scripts/restore_drill.php --latest --label=scheduled
~~~

หรือระบุไฟล์:

~~~bash
php scripts/restore_drill.php --file=storage/backups/<backup>.sql.gz
~~~

Restore Drill จะ:

1. Verify checksum
2. Require format v2
3. Scan dump เพื่อหาคำสั่ง database-selection DDL
4. ตรวจ backup source ให้ตรง DB_NAME
5. สร้างฐานชั่วคราว `dr_drill_*`
6. Stream gzip เข้า MariaDB โดยไม่สร้างไฟล์ SQL ชั่วคราว
7. ตรวจ critical tables
8. วัด RPO
9. วัด RTO
10. บันทึกผลลง `disaster_recovery_drills`
11. DROP ฐานชั่วคราวเสมอ

หาก cleanup ฐานชั่วคราวไม่สำเร็จ Drill จะถือว่า FAIL

## 6. สิทธิ์ฐานข้อมูลสำหรับ Drill

Application DB user ไม่จำเป็นต้องมี CREATE/DROP DATABASE

แนะนำสร้าง credential แยกสำหรับ DR:

~~~text
DR_DB_ADMIN_USER=<drill-admin>
DR_DB_ADMIN_PASSWORD=<secret>
~~~

Credential นี้ควรถูกเก็บใน environment/secret manager และจำกัด host ให้เหมาะสม

ไม่ควรเก็บ password ลง repository หรือไฟล์ runbook

## 7. Critical Tables

ค่าเริ่มต้น:

~~~text
DR_CRITICAL_TABLES=users,hospitals,shifts,roster_status,system_settings,schema_migrations,disaster_recovery_drills
~~~

ทุก table ต้อง:

- มีอยู่หลัง restore
- query ได้
- อยู่ใน isolated restore database

ปรับรายการได้ตามระบบ Production จริง

## 8. Recovery Readiness Gate

รัน:

~~~bash
php scripts/recovery_check.php
~~~

จะ FAIL เมื่อ:

- ไม่มี scheduled backup
- checksum/manifest ไม่ผ่าน
- backup เกิน RPO
- ไม่มี successful restore drill
- drill ล่าสุดเกินรอบที่กำหนด
- drill ล่าสุด fail
- RPO/RTO ล่าสุดเกินเป้า

เหมาะสำหรับ:

- Production health check
- release checklist
- monitoring cron
- incident readiness review

## 9. Observability

หน้า:

~~~text
System Health & Reliability
~~~

แสดง Disaster Recovery Readiness:

- PASS / FAIL / STALE / UNKNOWN
- Last successful drill age
- RPO
- RTO
- จำนวน drill
- Failed drills 30 วัน
- ประวัติ restore drill

Production สามารถตั้ง:

~~~text
DR_ENFORCE_HEALTH=1
~~~

เมื่อเปิด ค่า DR posture จะมีผลต่อ Overall Health

## 10. ตารางซ้อมกู้คืนที่แนะนำ

ขั้นต่ำ:

- Routine backup: ทุก 6 ชั่วโมง
- Checksum verify: ทุก backup
- Restore drill: สัปดาห์ละ 1 ครั้ง
- Recovery readiness check: อย่างน้อยวันละครั้ง
- Full business continuity review: รายไตรมาส

ตัวอย่าง cron:

~~~cron
5 * * * * cd /var/www/roster_pro && /usr/bin/php scripts/backup_if_due.php >> storage/backup-cron.log 2>&1
15 3 * * 0 cd /var/www/roster_pro && /usr/bin/php scripts/restore_drill.php --latest --label=scheduled >> storage/drill-cron.log 2>&1
30 6 * * * cd /var/www/roster_pro && /usr/bin/php scripts/recovery_check.php >> storage/recovery-check.log 2>&1
~~~

## 11. เหตุการณ์จริง — ห้าม Restore ทับ Production ทันที

เมื่อฐาน Production เสีย:

1. หยุด write traffic หรือเปิด maintenance mode
2. ระบุ backup ล่าสุดที่ checksum ผ่าน
3. เก็บ backup ของฐานเสียก่อน ถ้ายังอ่านได้
4. Restore ไปฐานใหม่ ไม่ใช่ชื่อ Production เดิม
5. Run schema/health verification
6. ตรวจ critical tables
7. ตรวจข้อมูลตัวอย่างตาม business workflow
8. บันทึกเวลาจริงเพื่อเทียบ RTO
9. เปลี่ยน DB_NAME/connection ไปฐานใหม่
10. Smoke test ระบบ
11. เปิด traffic แบบควบคุม
12. เก็บฐานเสียไว้เพื่อ forensic จน incident ปิด

แนวทางนี้ลดความเสี่ยงจากการเขียนทับหลักฐานหรือทำให้ rollback ยากขึ้น

## 12. Business Continuity Checklist

ก่อนประกาศ Production Ready:

- [ ] BACKUP_DIR อยู่นอก public/
- [ ] backup v2 สร้างได้
- [ ] checksum verify ผ่าน
- [ ] scheduled backup cadence ต่ำกว่า RPO
- [ ] DR admin credential แยกจาก app credential ถ้าเป็นไปได้
- [ ] restore drill PASS
- [ ] critical tables ผ่านครบ
- [ ] RPO อยู่ในเป้า
- [ ] RTO อยู่ในเป้า
- [ ] ไม่มีฐาน dr_drill_* ค้าง
- [ ] recovery_check.php PASS
- [ ] Observability แสดง DR status ถูกต้อง
- [ ] ผู้ดูแลรู้ขั้นตอน maintenance / cutover / rollback

## 13. CI

Workflow:

~~~text
.github/workflows/disaster-recovery.yml
~~~

CI จะทดสอบ:

- canonical schema restore
- baseline migration history
- continuity marker
- backup format v2
- checksum
- isolated restore
- critical tables
- RPO/RTO
- drill registry
- cleanup
- tampered backup rejection

DR CI ต้องผ่านก่อน merge Phase 8
