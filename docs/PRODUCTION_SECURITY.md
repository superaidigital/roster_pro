# Roster Pro — Production Security Hardening & Compliance

## เป้าหมาย

Phase 10 เพิ่ม security controls ที่ตรวจสอบและบังคับใช้ได้ใน CI/Production โดยไม่ทำให้ UI เดิมเสียหายจากการเปิด CSP แบบเข้มงวดทันที

องค์ประกอบ:

- Session/cookie hardening
- Trusted reverse-proxy boundary
- HTTP security headers
- CSP Report-Only migration path
- Login throttling แบบ account fingerprint + IP
- Secure upload validation
- Settings mutation allowlist
- Secret non-reflection
- Query-string secret removal
- Database privilege checks
- Security Scorecard
- Repository secret scan
- CycloneDX SBOM
- Production Security CI
- Go-Live Security Gate

## 1. Session Security

Production ใช้ cookie:

~~~text
ROSTERSESSID
Secure
HttpOnly
SameSite=Lax
~~~

และตั้ง:

~~~text
session.use_strict_mode=1
session.use_only_cookies=1
session.use_trans_sid=0
~~~

ค่าที่แนะนำ:

~~~text
SESSION_IDLE_TIMEOUT_SECONDS=28800
SESSION_ABSOLUTE_TIMEOUT_SECONDS=43200
SESSION_REGEN_INTERVAL_SECONDS=900
~~~

หลัง login ระบบจะบันทึก:

- authenticated time
- last activity
- last session-id regeneration

เมื่อเกิน idle/absolute timeout จะ invalidate authenticated session และให้ login ใหม่

## 2. Reverse Proxy Trust

Roster Pro จะไม่เชื่อ `X-Forwarded-Proto` อัตโนมัติ

ถ้ามี reverse proxy/load balancer:

~~~text
TRUST_PROXY_HEADERS=1
TRUSTED_PROXY_IPS=10.0.0.10,10.0.0.11
~~~

ระบบจะอ่าน forwarded HTTPS header เฉพาะเมื่อ `REMOTE_ADDR` ตรงกับรายการ trusted IP

ห้ามตั้ง:

~~~text
TRUST_PROXY_HEADERS=1
TRUSTED_PROXY_IPS=
~~~

Production preflight จะ FAIL

## 3. HTTP Security Headers

Dynamic application responses ส่ง:

~~~text
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: ...
Cross-Origin-Opener-Policy: same-origin
X-Permitted-Cross-Domain-Policies: none
Cache-Control: private, no-store
~~~

เมื่อ request ถูกตรวจว่าเป็น HTTPS:

~~~text
Strict-Transport-Security: max-age=31536000; includeSubDomains
~~~

## 4. Content Security Policy

UI ปัจจุบันยังมี:

- inline script
- inline style
- Bootstrap CDN
- Bootstrap Icons CDN
- SweetAlert2 CDN
- jQuery CDN
- Google Fonts

ดังนั้น Phase 10 ไม่เปิด strict CSP แบบหักดิบ

ค่าเริ่มต้น Production:

~~~text
CSP_MODE=report-only
~~~

Header:

~~~text
Content-Security-Policy-Report-Only
~~~

Policy จำกัดอย่างน้อย:

~~~text
default-src 'self'
base-uri 'self'
form-action 'self'
frame-ancestors 'self'
object-src 'none'
upgrade-insecure-requests
~~~

แต่ยังมี `'unsafe-inline'` เพื่อ compatibility

ขั้นก่อนเปลี่ยนเป็น:

~~~text
CSP_MODE=enforce
~~~

ควรย้าย inline JS/CSS ไปไฟล์ static หรือใช้ nonce/hash และตรวจ report ให้สะอาดก่อน

## 5. Login Protection

Rate limit ทำสองระดับ:

1. credential fingerprint + IP
2. failed login ทั้งหมดจาก IP

Username ไม่ถูกเขียนตรงลง failure-log detail อีก แต่ใช้ SHA-256 fingerprint แบบย่อ

ค่า default:

- account/IP: 5 ครั้ง / 15 นาที
- IP รวม: 25 ครั้ง / 15 นาที

## 6. Secure Uploads

Helper:

~~~text
lib/SecureUpload.php
~~~

ใช้กับ:

- Medical Certificate
- Hospital Logo

ตรวจ:

- `is_uploaded_file()`
- max size
- MIME ด้วย Fileinfo
- allowlist MIME → extension
- image signature/structure ด้วย `getimagesize()`
- maximum dimensions
- PDF ต้องขึ้นต้นด้วย `%PDF-`
- random filename ด้วย `random_bytes()`
- MIME ตรวจซ้ำหลัง move
- private upload ห้ามอยู่ใต้ `public/`
- private file permission พยายามตั้ง 0640

Medical Certificate:

~~~text
storage/private/med_certs
~~~

ไม่สามารถเข้าถึงตรงผ่าน URL

## 7. Legacy Medical Uploads

Directory:

~~~text
uploads/med_certs
~~~

คงไว้เฉพาะ compatibility และมี deny-all `.htaccess`

Security Scorecard จะ FAIL หากยังพบ medical files จริงใน directory นี้

เป้าหมายคือ migrate file เก่าออกไป:

~~~text
storage/private/med_certs
~~~

แล้วให้ระบบ serve ผ่าน authorization endpoint เท่านั้น

## 8. System Settings Allowlist

`SettingsController::update_system()` จะไม่เขียน POST key แบบ arbitrary อีก

General settings อนุญาตเฉพาะ:

~~~text
system_name
system_short_name
~~~

LINE/integration section อนุญาตเฉพาะ key ที่กำหนด

`maintenance_mode` ไม่สามารถเขียนผ่าน system settings ได้

Production maintenance ใช้ runtime control plane จาก Phase 9 เท่านั้น

## 9. Secret Non-Reflection

Stored integration token จะไม่ถูกใส่กลับเป็น:

~~~html
value="<secret>"
~~~

ใน Settings HTML

เมื่อ token มีอยู่ UI แสดงเพียงว่าตั้งค่าแล้ว

หากไม่กรอกค่าใหม่ Controller จะเก็บค่าเดิมไว้

## 10. Cron / Automation Secrets

Production ไม่อนุญาต legacy web backup cron

ใช้:

~~~bash
php scripts/backup_if_due.php
~~~

แทน

Non-production compatibility endpoint:

- ต้อง POST
- secret ต้องอยู่ใน `X-Roster-Cron-Key`
- ไม่รับ `?key=...`

เหตุผลคือ query-string secrets อาจรั่วไปยัง:

- browser history
- access log
- reverse proxy log
- analytics
- Referer header

## 11. Database Account

Production preflight จะ FAIL เมื่อ:

~~~text
DB_USER=root
~~~

Security Scorecard ตรวจ server-level privileges เช่น:

- GRANT OPTION
- FILE
- SUPER
- CREATE USER
- SHUTDOWN

Runtime account ควรมีเท่าที่ application ต้องใช้

สำหรับ migration ที่ต้อง DDL ให้ใช้ account ที่มีสิทธิ์เฉพาะ database นั้น และลดสิทธิ์หลัง cutover หาก infrastructure ยังใช้ credential ชุดเดียวกัน

ห้ามใช้ global root account สำหรับ runtime

## 12. Security Scorecard

CLI:

~~~bash
php -d display_errors=0 -d expose_php=0 scripts/security_check.php --strict
~~~

กำหนดขั้นต่ำ:

~~~text
SECURITY_MIN_SCORE=85
~~~

ตรวจ:

- HTTPS
- release identity
- cron secret
- non-root DB account
- DB password
- proxy trust
- CSP
- session lifetime
- display_errors
- expose_php
- allow_url_include
- backup/runtime/private paths
- legacy medical files
- executable public uploads
- dangerous DB privileges

หน้า:

~~~text
Settings → System Status
~~~

แสดง:

- Security Score
- Grade
- PASS/WARN/FAIL
- รายการ control แต่ละข้อ

## 13. PHP Production Runtime

แนะนำ php.ini:

~~~ini
display_errors=Off
log_errors=On
expose_php=Off
allow_url_include=Off

session.use_strict_mode=1
session.use_only_cookies=1
session.use_trans_sid=0
~~~

Error details ควรอยู่ใน server log/observability ไม่แสดงต่อผู้ใช้

## 14. Repository Secret Scan

รัน:

~~~bash
php scripts/secret_scan.php
~~~

ตรวจ high-confidence patterns เช่น:

- Private keys
- AWS access keys
- GitHub tokens
- Google API keys
- OpenAI-style keys

Scanner จงใจไม่ใช้ generic password regex ที่กว้างเกินไปเพื่อลด false positive

ผลที่ต้องได้:

~~~text
SECRET_SCAN_OK
~~~

## 15. Software Bill of Materials

สร้าง CycloneDX:

~~~bash
php scripts/generate_sbom.php --output=build/sbom.cdx.json
~~~

SBOM ระบุ:

- Roster Pro release
- frontend CDN dependencies ที่ตรวจพบ
- package/version เมื่อ URL ระบุ version

CI จะเก็บ:

~~~text
roster-pro-cyclonedx-sbom
~~~

เป็น artifact

ปัจจุบัน repository ไม่มี Composer/npm dependency manifest จึงเน้น runtime/frontend external components ที่อ้างจาก source

## 16. Production Security CI

Workflow:

~~~text
.github/workflows/production-security.yml
~~~

CI จะ:

1. Load canonical schema
2. สร้าง DML-only DB runtime user
3. Production preflight
4. Security score gate
5. Secret scan
6. CycloneDX SBOM
7. Validate SBOM JSON
8. Start application server
9. Request ผ่าน trusted HTTPS proxy simulation
10. ตรวจ security headers
11. ตรวจ Secure / HttpOnly / SameSite cookie
12. ตรวจ CSP critical directives
13. Upload SBOM artifact

## 17. Go-Live Integration

`scripts/go_live_check.php` รัน:

~~~text
Performance Contract
Security Compliance
Recovery Readiness
~~~

Production จะใช้ Security Compliance แบบ strict

ดังนั้น traffic จะไม่ถูกเปิดถ้า Security Score/critical controls ไม่ผ่าน

## 18. Production Checklist

ก่อน Go-Live:

- [ ] APP_BASE_URL เป็น HTTPS
- [ ] APP_RELEASE_ID ถูกต้อง
- [ ] DB_USER ไม่ใช่ root
- [ ] DB_PASSWORD ไม่ว่าง
- [ ] ไม่มี global dangerous DB privilege
- [ ] TRUST_PROXY_HEADERS ตั้งถูกต้อง
- [ ] TRUSTED_PROXY_IPS ระบุจริงหากใช้ proxy
- [ ] CSP_MODE อย่างน้อย report-only
- [ ] Session timeout อยู่ใน policy
- [ ] display_errors Off
- [ ] expose_php Off
- [ ] allow_url_include Off
- [ ] Backup/private/runtime storage อยู่นอก public
- [ ] ไม่มี medical file ใน legacy uploads/med_certs
- [ ] ไม่มี executable file ใน public/uploads
- [ ] Stored integration token ไม่ถูก render ลง HTML
- [ ] Secret scan PASS
- [ ] SBOM สร้างได้
- [ ] Security Score >= SECURITY_MIN_SCORE
- [ ] Production Security CI PASS
- [ ] Go-Live Check PASS

## 19. CSP Roadmap

Phase 10 ใช้ Report-Only โดยตั้งใจ

ขั้นถัดไปสำหรับ CSP enforcement:

1. inventory inline scripts/styles
2. ย้าย JS ไป `public/js`
3. ย้าย CSS ไป `public/css`
4. pin CDN versions
5. เพิ่ม Subresource Integrity หรือ self-host assets
6. ตัด `unsafe-inline`
7. ทดสอบ Report-Only
8. เปลี่ยน `CSP_MODE=enforce`

อย่าเปิด strict CSP ก่อน migration เพราะจะทำให้ UI/JavaScript เดิมหยุดทำงาน
