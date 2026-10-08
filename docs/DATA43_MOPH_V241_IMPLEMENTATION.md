# การปรับระบบนำส่งข้อมูลตามมาตรฐาน MOPH Version 2.4.1

เอกสารนี้สรุปกติกาที่ใช้ในโมดูล "นำส่งข้อมูล 43 แฟ้ม" หลังเทียบกับคู่มือ
**การจัดเก็บและจัดส่งข้อมูลตามโครงสร้างมาตรฐานข้อมูลด้านสุขภาพ Version 2.4.1 ปีงบประมาณ 2568**

> ชื่อเมนู "43 แฟ้ม" ยังคงไว้เพื่อความคุ้นเคยของผู้ใช้ แต่ Standard Catalog ในระบบรองรับ
> โครงสร้างลำดับ 1-52 ตามคู่มือฉบับที่ใช้วิเคราะห์

## 1. Profile รพ.สต.

ระบบกำหนด `RPHST_V241` จากช่อง "หน่วยงานที่บันทึก" ในคู่มือ

โครงสร้างที่ไม่บังคับใน Profile รพ.สต. เพราะคู่มือไม่ได้ทำเครื่องหมาย รพ.สต.:
- FUNCTIONAL
- ICF
- ADMISSION
- DIAGNOSIS_IPD
- DRUG_IPD
- PROCEDURE_IPD
- CHARGE_IPD

ดังนั้น Profile นี้มี expected structures = 45 จาก catalog 52 รายการ

> จำนวน expected_files เป็น profile-specific ไม่ควร hard-code คำว่า 43 ใน query/dashboard

## 2. ความสัมพันธ์สำหรับ Spatial Analytics

### แกนบุคคลและพื้นที่

```
PERSON.PID
   |
   +-- PERSON.HID ------> HOME.HID
   |                        |
   |                        +--> CHANGWAT
   |                        +--> AMPUR
   |                        +--> TAMBON
   |                        +--> VILLAGE
   |                        +--> LATITUDE/LONGITUDE
   |
   +-- fallback --------> ADDRESS.PID
                            |
                            +--> CHANGWAT/AMPUR/TAMBON/VILLAGE

VILLAGE.VID = CCAATTMM
   +--> village master / village centroid
```

แฟ้มสุขภาพที่ไม่มีมิติพื้นที่โดยตรงต้องผูก `PID -> PERSON -> HOME/ADDRESS`
ก่อน aggregate ตามพื้นที่

## 3. Indicator mapping

| Indicator | Source | Unit | Linkage / rule |
| --- | --- | --- | --- |
| POPULATION | PERSON | คน | PID, เฉพาะประชากรที่ระบบกำหนดเป็นฐานพื้นที่ |
| ELDERLY | PERSON | คน | คำนวณอายุจาก BIRTH >= 60 |
| DM | CHRONIC | คน | CHRONIC ICD-10-TM E10-E14, dedupe PID |
| HT | CHRONIC | คน | CHRONIC ICD-10-TM I10-I15, dedupe PID |
| NCD | CHRONIC | คน | ผู้มี record โรคเรื้อรัง, dedupe PID |
| DISABLED | DISABILITY | คน | dedupe PID เพื่อไม่ให้นับซ้ำเมื่อมีหลาย DISABTYPE |
| ANC | ANC | ครั้งบริการ | PID + SEQ + DATE_SERV |
| SERVICE | SERVICE | visit | HOSPCODE + PID + SEQ |
| NCD_SCREEN | NCDSCREEN | ครั้งคัดกรอง | PID + SEQ + DATE_SERV |
| GEO_REFERENCE | HOME/VILLAGE | aggregate reference | ใช้ centroid พื้นที่เท่านั้น |

## 4. กติกาสำคัญจากมาตรฐาน

- `PERSON.PID` เป็นทะเบียนบุคคลสำหรับเชื่อมแฟ้มอื่น
- `PERSON.HID` อ้างอิง `HOME.HID`
- HOME มีรหัสพื้นที่และพิกัดครัวเรือน
- VILLAGE ใช้ `VID = CCAATTMM`
- SERVICE หนึ่ง visit ใช้ SEQ เดียวกัน แม้รับบริการหลายคลินิก
- CHRONIC อาจมีหลายรหัสโรคต่อคน จึงต้อง dedupe รายบุคคลเมื่อทำ KPI จำนวนผู้ป่วย
- DISABILITY หนึ่งคนอาจมีหลาย DISABTYPE จึงต้อง dedupe PID เมื่อทำ KPI "ผู้พิการ"
- ANC หนึ่งครั้งบริการมีหนึ่ง record และเชื่อม PRENATAL ได้
- NCDSCREEN เป็นข้อมูล "การคัดกรอง" ไม่ใช่ทะเบียนผู้ป่วย DM/HT; DM/HT prevalence ในระบบจึงใช้ CHRONIC

## 5. Privacy / PDPA

Spatial dashboard ไม่ persist CID/PID/HID หรือพิกัดรายบุคคลลง `data43_area_metrics`.
Identifier ใช้เฉพาะระหว่างประมวลผลใน memory เพื่อ linkage/deduplication แล้วบันทึกเฉพาะ aggregate.

พื้นที่ที่มีจำนวน 1-4 รายแสดงเป็น `<5` และไม่ใช้ค่าจริงในการไล่สี Choropleth.

## 6. Migration

Existing installation:

```sql
SOURCE database/migrations/20261007_data43_standard_v241.sql;
SOURCE database/migrations/20261007_data43_spatial_choropleth.sql;
```

หลัง migration ให้นำส่ง ZIP รอบเดือนที่ต้องการวิเคราะห์ใหม่ เพื่อ rebuild aggregate ด้วย linkage รุ่น 2.4.1


## 7. Data43 v2 hardening

รอบปรับปรุงนี้เพิ่มหลักการ production-safety ดังนี้

- Metric Registry แยกนิยาม numerator/denominator/unit ออกจาก Controller/View
- DM/HT/NCD/DISABLED/SERVICE ใช้ rate ต่อ 1,000 เมื่อมีประชากรฐาน
- ELDERLY แสดงร้อยละของประชากร
- ANC เป็นจำนวนครั้ง ไม่บังคับหารประชากร
- NCD_SCREEN ใช้กลุ่มเป้าหมายอายุ 35 ปีขึ้นไปเป็น denominator
- รองรับไฟล์ชนิดเดียวกันหลาย part ภายใน ZIP
- XLSX จะไม่ถูกถือว่า VALID แบบ metadata-only อีกต่อไป หากยังไม่มี streaming reader
- ADDRESS ไม่ถูกใช้เป็น fallback ที่อยู่อาศัยสำหรับ prevalence; คนที่หา HOME ไม่พบถูกนับเป็น unresolved
- VILLAGE.VID รองรับรูปแบบตัวอักษร เช่น A0/B9/Z9
- Analytics ใช้ VILLAGE centroid ก่อน household coordinates และไม่ส่ง household coordinates ไปยัง aggregate
- เพิ่ม primary + complementary suppression สำหรับ small cell
- เพิ่มตาราง data43_quality_summary เพื่อเก็บ diagnostics แบบ aggregate

### Migration เพิ่มเติม

```sql
SOURCE database/migrations/20261007_data43_quality_v2.sql;
```

หลังอัปเดต migration ให้ re-import รอบเดือนที่ต้องการวิเคราะห์ใหม่ เพื่อให้ denominator, privacy flags และ linkage diagnostics ถูกสร้างจาก logic รุ่นล่าสุด
