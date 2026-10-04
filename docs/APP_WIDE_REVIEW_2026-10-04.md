# Roster Pro — App-wide UX, Security & Performance Review

วันที่ตรวจ: 2026-10-04  
Branch: `refactor/app-ux-security-v1`

## เป้าหมาย

ตรวจระบบทั้งแอปในมุม Responsive UI/UX, Accessibility, Security, Performance, Runtime Safety และความพร้อมต่อการพัฒนาฟีเจอร์ในระยะถัดไป

## สิ่งที่ปรับปรุงแล้ว

### UI/UX & Accessibility

- เพิ่ม Skip Link และ `main` landmark
- เพิ่ม `:focus-visible` ที่มองเห็นชัดสำหรับ Keyboard Navigation
- ปรับ touch target สำหรับอุปกรณ์จอสัมผัส
- เพิ่ม `prefers-reduced-motion`
- ป้องกัน layout overflow สำหรับ card, table, media และ container
- ปรับ Login form ให้ label/input เชื่อมด้วย `for/id`
- Password toggle เปลี่ยนเป็น semantic button พร้อม ARIA state
- Notification card รองรับ Enter/Space
- Roster UI V2 มี KPI, Coverage Heatmap, Quick Filter และ Quick Paint

### Security

- เพิ่ม HTTP security headers
- Route `c/a` ถูก validate และ dispatch เฉพาะ public callable action
- Login มี rate limit และ regenerate session หลังล็อกอิน
- Logout เป็น POST + CSRF
- Notification actions เป็น POST + CSRF และ scope ตามเจ้าของข้อมูล
- ปิดช่องโหว่ open redirect จาก notification link
- Settings mutations บังคับ CSRF
- Hospital logo upload ตรวจ MIME, ขนาด, uploaded-file และใช้ชื่อสุ่ม
- Medical certificate upload ตรวจ MIME, ขนาด และชื่อไฟล์สุ่ม
- CSV import จำกัดขนาดไฟล์ จำนวนแถว และ validate ข้อมูล
- Report filters ป้องกัน non-admin เปลี่ยน `hospital_id` เพื่อดูหน่วยอื่น
- Shift swap ตรวจบุคลากรสังกัดเดียวกันและตรวจเวรจริงจากฐานข้อมูลก่อนสร้างคำขอ
- Roster บังคับเฉพาะบุคลากรในสังกัดทั้ง UI และ Server

### Performance

เพิ่ม index สำหรับ query ที่ใช้บ่อย:

- `shifts(hospital_id, shift_date)`
- `shifts(hospital_id, user_id, shift_date)`
- `users(hospital_id, is_active, is_deleted, show_in_roster, display_order)`
- `leave_requests(user_id, status, start_date, end_date)`
- `leave_requests(status, start_date, end_date)`
- `notifications(user_id, is_read, created_at)`
- `shift_swaps(hospital_id, status, created_at)`
- `holidays(hospital_id, holiday_date)`
- `logs(user_id, action, ip_address, created_at)`

Migration: `database/migrations/20261004_performance_indexes.sql`

## Test Matrix

| Case | Expected result |
|---|---|
| Invalid controller/action | 400/404 โดยไม่ fatal |
| Attempt private controller method | ไม่สามารถ dispatch ได้ |
| External notification redirect | ถูกปฏิเสธและ fallback เป็น local route |
| Cross-user notification read/delete | ไม่สามารถอ่าน/ลบของผู้ใช้อื่น |
| Login failure repeated | ถูก rate limit |
| Logout via GET | ไม่เปลี่ยน state |
| Invalid report month/year | fallback เป็นเดือน/ปีปัจจุบัน |
| Non-admin requests another hospital report | server บังคับใช้หน่วยของผู้ใช้ |
| Fake logo/medical file extension | MIME validation ปฏิเสธ |
| Oversized uploads | ปฏิเสธก่อนบันทึก |
| Large CSV | จำกัด 2 MB และสูงสุด 5,000 data rows |
| Swap with staff in another unit | ปฏิเสธ |
| Swap using stale/fake hidden shift value | ปฏิเสธจากข้อมูลจริงใน DB |
| Roster same-unit enforcement | แสดง/บันทึก/validate เฉพาะบุคลากรในสังกัด |

CI ที่ใช้ตรวจ: PHP lint, schema contract, code-quality audit และ runtime smoke test.

## ฟีเจอร์แนะนำระยะถัดไป

### P1 — Production Safety

1. Roster Snapshot + Undo / Restore Version
2. Audit Trail แบบ before/after สำหรับข้อมูลสำคัญ
3. Centralized authorization policy ลด logic role ซ้ำใน Controller
4. Private document delivery สำหรับใบรับรองแพทย์ แทนการเปิดไฟล์โดย path ตรง
5. Local/vendor asset bundle ลด dependency CDN สำหรับระบบ Intranet/PWA

### P2 — Operational UX

1. Global Search / Command Palette
2. Saved Filters ต่อผู้ใช้
3. In-app Notification Center แบบ realtime/polling ที่มี retry state
4. Roster conflict panel ที่กดแล้วเลื่อนไปยัง cell ปัญหา
5. Bulk action + Undo สำหรับตาราง/บุคลากร
6. Draft autosave / unsaved-change indicator ในฟอร์มยาว

### P3 — Smart Scheduling

1. Availability / วันไม่สะดวกของบุคลากร
2. Skill-based scheduling จากใบอนุญาตและประวัติอบรม
3. Fairness Score, Fatigue Score, Coverage Score
4. Budget guard / ค่าเวรประมาณการก่อน Publish
5. Explainable Auto Schedule แสดงเหตุผลว่า AI/Rule Engine เลือกใครเพราะอะไร
6. Electronic sign-off + immutable approved roster snapshot

## Technical Debt ที่ควรทำต่อ

- Refactor controller ที่มี business logic ยาวออกเป็น Service classes
- ลด query แบบ N+1 ใน Report/Overview ด้วย aggregate query
- เพิ่ม pagination/server-side filtering สำหรับหน้าที่ข้อมูลโต
- เพิ่ม browser E2E tests (Playwright) สำหรับ Login, Roster, Leave, Swap, Notifications
- เพิ่ม visual regression สำหรับ Mobile 360px, Tablet 768px, Desktop 1366/1440px
- พิจารณา CSP หลังลด inline script/style และย้าย third-party CDN asset เป็น local bundle
