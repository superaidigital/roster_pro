# Roster Pro — Production Deployment & Rollback Guide

เอกสารนี้ใช้สำหรับนำ Roster Pro ขึ้น Production อย่างปลอดภัยหลังชุด Security, Snapshot/Restore, Audit Trail, Immutable Revision และ Document Verification

## 1. หลักการสำคัญ

1. สำรองฐานข้อมูลก่อน migration ทุกครั้ง
2. ห้ามแก้ไขไฟล์ migration ที่มีสถานะ `applied` แล้ว
3. Migration ต้องอยู่ใน `database/migrations/manifest.json` และใช้ลำดับตาม manifest เท่านั้น
4. หาก migration ล้มเหลว ให้หยุด deployment ทันที เพราะ MariaDB/MySQL DDL บางคำสั่ง auto-commit และอาจ rollback ไม่ได้
5. ห้าม restore ฐานข้อมูลอัตโนมัติ เพราะข้อมูลที่เกิดหลัง backup จะสูญหาย
6. Production backup ต้องอยู่นอก `public/`
7. หลัง deploy ต้องผ่าน `health_check.php` ก่อนเปิดให้ผู้ใช้ทำงานต่อ

## 2. Production Requirements

- PHP 8.2+
- PHP extensions: `pdo_mysql`, `zlib`
- MariaDB 10.11+ หรือรุ่นที่รองรับ SQL ใน migration ปัจจุบัน
- `mariadb-dump` หรือ `mysqldump`
- พื้นที่เขียนได้สำหรับ `storage/backups/`
- HTTPS สำหรับ Production

ตัวแปร environment ที่ควรกำหนด:

~~~text
APP_ENV=production
APP_BASE_URL=https://your-production-host.example/roster_pro

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=roster_pro_db
DB_USER=roster_app
DB_PASSWORD=<strong-secret>
DB_TIMEZONE=+07:00

ROSTER_CRON_KEY=<random-secret-at-least-32-characters>
DEPLOY_ACTOR=<name-or-ci-release-id>

# ถ้า dump binary ไม่อยู่ใน PATH
DB_DUMP_BIN=/usr/bin/mariadb-dump

BACKUP_DIR=storage/backups
~~~

`APP_BASE_URL` สำคัญกับ QR Verification ในเอกสาร Official Revision

## 3. ตรวจสอบก่อน Deploy

รัน:

~~~bash
php scripts/preflight.php
php scripts/migrate.php --dry-run
~~~

Preflight จะตรวจอย่างน้อย:

- PHP / extensions
- Database connectivity
- พื้นที่และ permission ของ storage
- dump binary
- backup path ต้องไม่อยู่ใน public
- migration manifest
- failed/running/checksum mismatch
- Production HTTPS / cron secret / DB password

หากมี `[FAIL]` ห้าม deploy ต่อ

## 4. Fresh Install

สร้างฐานข้อมูลว่าง จากนั้นโหลด canonical schema:

~~~bash
mariadb -u <user> -p <database> < database/schema.sql
~~~

เนื่องจาก `schema.sql` เป็นโครงสร้างล่าสุดอยู่แล้ว ให้ baseline migration history:

~~~bash
php scripts/migrate.php --baseline --confirm=SCHEMA-LOADED
php scripts/migrate.php --status
php scripts/health_check.php
~~~

ห้ามใช้ `--baseline` กับฐาน Production เก่าที่ยังไม่ได้ตรวจ schema เพราะคำสั่งนี้จะ mark migration ทุกไฟล์ว่า applied โดยไม่ execute SQL

## 5. Upgrade Existing Production

### 5.1 ฐานข้อมูลรุ่นเก่ามาก / ก่อน legacy bridge

ใช้:

~~~bash
php scripts/deploy_database.php --confirm=DEPLOY --legacy-bridge
~~~

ลำดับการทำงาน:

1. Preflight
2. Pre-deploy backup
3. Legacy compatibility bridge
4. Tracked SQL migrations
5. Health check

### 5.2 ฐานข้อมูลที่ผ่าน legacy bridge แล้ว

ใช้:

~~~bash
php scripts/deploy_database.php --confirm=DEPLOY
~~~

ระบบจะสร้าง backup ก่อน migration เสมอ

## 6. ตรวจสอบ Migration Status

~~~bash
php scripts/migrate.php --status
~~~

สถานะที่พบได้:

- `APPLIED` — เรียบร้อย
- `PENDING` — ยังไม่รัน
- `RUNNING` — เคยเริ่มแต่ยังไม่จบ/โปรเซสอาจหยุดกลางทาง
- `FAILED` — migration ล้มเหลว ต้องตรวจ schema ก่อน retry
- `CHECKSUM_MISMATCH` — ไฟล์ migration ที่บันทึกแล้วถูกเปลี่ยน ห้าม deploy ต่อ
- `ORPHANED_APPLIED_MIGRATION` — DB มี migration ที่ไม่มีใน manifest/repository

Production หลัง deploy ต้องไม่มี pending หรือ blocking state

## 7. กรณี Migration FAILED

หยุด deployment และรัน:

~~~bash
php scripts/migrate.php --status
~~~

จากนั้น:

1. ตรวจ error ของ migration ที่ล้ม
2. ตรวจว่ามี DDL ส่วนใดถูก apply ไปแล้วหรือไม่
3. ห้ามแก้ไฟล์ migration ที่ `applied` แล้ว
4. ถ้า failed migration ถูกออกแบบให้ retry-safe และตรวจ schema แล้ว สามารถใช้:

~~~bash
php scripts/migrate.php --retry-failed
~~~

5. หาก schema อยู่ในสภาพไม่ปลอดภัย ให้ใช้ rollback procedure ด้านล่าง

## 8. Backup

สร้าง pre-deploy backup ด้วยตนเอง:

~~~bash
php scripts/backup_database.php --label=predeploy
~~~

จะได้ไฟล์:

~~~text
storage/backups/<database>_predeploy_YYYYMMDD_HHMMSS.sql.gz
storage/backups/<file>.sql.gz.sha256
storage/backups/<file>.sql.gz.json
~~~

ตรวจ checksum:

~~~bash
php scripts/verify_backup.php --file=storage/backups/<file>.sql.gz
~~~

ต้องได้:

~~~text
BACKUP_VALID
~~~

ก่อนนำไฟล์ไป restore

## 9. Rollback Procedure

### 9.1 Code-only rollback

หาก migration ใหม่ backward-compatible และฐานข้อมูลยังสมบูรณ์ สามารถ rollback application code ไป release ก่อนหน้าได้

หลัง rollback code ให้ตรวจ health และ workflow ที่สำคัญอีกครั้ง

### 9.2 Database restore

Database restore เป็น destructive operation และจะทำให้ข้อมูลที่เกิดหลังเวลาสร้าง backup สูญหาย

ก่อน restore:

1. เปิด Maintenance Mode / หยุด traffic เขียนข้อมูล
2. ตรวจ checksum ของ backup
3. จดเวลา backup และประเมินข้อมูลที่อาจสูญหาย
4. เก็บสำเนาฐานข้อมูลปัจจุบันอีกหนึ่งชุดถ้ายังทำได้

Linux ตัวอย่าง:

~~~bash
php scripts/verify_backup.php --file=storage/backups/<file>.sql.gz

export MYSQL_PWD='<password>'
gzip -dc storage/backups/<file>.sql.gz | \
  mariadb --host=<host> --port=<port> --user=<user> <database>
unset MYSQL_PWD
~~~

จากนั้น checkout application release ที่ตรงกับ backup/schema และรัน:

~~~bash
php scripts/migrate.php --status
php scripts/health_check.php
~~~

ห้ามรัน migration ต่ออัตโนมัติจนกว่าจะยืนยันว่า restore ถูกต้อง

## 10. Health Check

CLI:

~~~bash
php scripts/health_check.php
~~~

Public endpoint สำหรับ load balancer / uptime monitor:

~~~text
/index.php?c=health&a=index
~~~

Endpoint เปิดเผยเฉพาะ:

- overall status
- database status
- migration status/count
- timestamp

ไม่แสดง DB host, DB name, username, password หรือ application data

HTTP:

- `200` — DB ปกติและ migration current
- `503` — DB/migration ต้องตรวจสอบ

## 11. Post-deploy Smoke Checklist

หลัง health ผ่าน ให้ตรวจ:

- Login / Logout
- Dashboard
- เปิด Roster เดือนปัจจุบัน
- แก้เวร 1 ช่องแล้ว Audit Trail เกิด
- Snapshot/Restore ใน test month
- Workflow SUBMITTED → APPROVED
- Official Revision ถูกสร้าง
- ดาวน์โหลด Word ฉบับทางการ
- QR Verification เปิด public verification page
- Revision เก่าแสดง Superseded เมื่อมี Revision ใหม่
- Notification / Leave / Swap ที่ใช้งานจริง
- Backup page ดาวน์โหลดไฟล์ผ่าน authenticated controller ได้

## 12. การเพิ่ม Migration ใหม่

1. สร้างไฟล์ใหม่ เช่น:

~~~text
database/migrations/20261006_example_change.sql
~~~

2. เขียน migration ให้ retry-safe เท่าที่ทำได้ เช่น `CREATE TABLE IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS`
3. เพิ่มชื่อไฟล์ลงท้าย `database/migrations/manifest.json`
4. ห้ามแก้ migration ที่ Production apply แล้ว
5. รัน:

~~~bash
php scripts/schema_contract.php
php scripts/code_quality.php
php scripts/migrate.php --dry-run
~~~

6. ให้ Migration Safety CI ผ่านก่อน merge

## 13. Release Gate

ก่อนเปิด Production traffic ต้องครบทุกข้อ:

- [ ] CI PHP Lint ผ่าน
- [ ] CI Runtime Smoke ผ่าน
- [ ] CI Migration Safety ผ่าน
- [ ] Preflight ไม่มี FAIL
- [ ] Pre-deploy backup สร้างสำเร็จ
- [ ] Backup SHA-256 verify ผ่าน
- [ ] Migration status ไม่มี pending/blocking
- [ ] Health check = OK
- [ ] APP_BASE_URL เป็น HTTPS Production URL
- [ ] QR Verification สแกนได้จากอุปกรณ์ภายนอก
- [ ] ทดสอบ Official Revision อย่างน้อย 1 ฉบับ
- [ ] ผู้ดูแลทราบตำแหน่ง backup และ rollback procedure
