<?php
final class Data43ValidationService
{
    public static function validateCid(?string $cid): bool
    {
        $cid = preg_replace('/\D/', '', (string)$cid);
        if (strlen($cid) !== 13 || preg_match('/^(\d)\1{12}$/', $cid)) return false;
        $sum = 0;
        for ($i = 0; $i < 12; $i++) $sum += ((int)$cid[$i]) * (13 - $i);
        $check = (11 - ($sum % 11)) % 10;
        return $check === (int)$cid[12];
    }

    public static function validateDate8(?string $value, bool $allowFuture = false): bool
    {
        if ($value === null || $value === '') return true;
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 8) return false;
        $y=(int)substr($digits,0,4); $m=(int)substr($digits,4,2); $d=(int)substr($digits,6,2);
        if (!checkdate($m,$d,$y)) return false;
        if (!$allowFuture && $digits > date('Ymd')) return false;
        return true;
    }

    public static function validateDateTime14(?string $value): bool
    {
        if ($value === null || $value === '') return true;
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 14) return false;
        $y=(int)substr($digits,0,4); $m=(int)substr($digits,4,2); $d=(int)substr($digits,6,2);
        $h=(int)substr($digits,8,2); $i=(int)substr($digits,10,2); $s=(int)substr($digits,12,2);
        return checkdate($m,$d,$y) && $h<24 && $i<60 && $s<60;
    }

    public static function validateIcd10Tm(?string $code): bool
    {
        $code = strtoupper(trim((string)$code));
        if ($code === '') return true;
        return preg_match('/^[A-TV-Z][0-9]{2}(?:\.?[0-9A-Z]{1,4})?$/', $code) === 1;
    }

    public static function validateCdeath(?string $code): bool
    {
        $code = strtoupper(trim((string)$code));
        if ($code === '') return true;
        if (preg_match('/^[STZ]/', $code)) return false;
        return self::validateIcd10Tm($code);
    }

    public static function buddhistToDate8(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m)) {
            $year=(int)$m[3]; if ($year > 2400) $year -= 543;
            if (!checkdate((int)$m[2], (int)$m[1], $year)) return null;
            return sprintf('%04d%02d%02d',$year,(int)$m[2],(int)$m[1]);
        }
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 8) return null;
        $year=(int)substr($digits,0,4);
        if ($year > 2400) $year -= 543;
        $out=sprintf('%04d%s',$year,substr($digits,4));
        return self::validateDate8($out) ? $out : null;
    }

    public static function normalizeVillage(?string $value): ?string
    {
        $raw = strtoupper(trim((string)$value));
        if ($raw === '' || $raw === '99') return null;
        if (preg_match('/^[A-Z][0-9]$/', $raw)) return $raw;
        if (preg_match('/^\d{1,2}$/', $raw)) return str_pad($raw,2,'0',STR_PAD_LEFT);
        return null;
    }

    public static function validateSchemaFields(array $schema, array $data): array
    {
        $errors = [];

        foreach ((array)($schema['fields'] ?? []) as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name === '') continue;

            $value = trim((string)($data[$name] ?? ''));
            if ($value === '') continue;

            if (isset($field['maxlength']) && mb_strlen($value, 'UTF-8') > (int)$field['maxlength']) {
                $errors[$name] = 'ข้อมูลยาวเกิน ' . (int)$field['maxlength'] . ' ตัวอักษร';
                continue;
            }

            if (($field['type'] ?? '') === 'select' && isset($field['options'])) {
                $allowed = array_map('strval', array_keys((array)$field['options']));
                if (!in_array($value, $allowed, true)) {
                    $errors[$name] = 'ค่าที่เลือกไม่อยู่ในรายการมาตรฐาน';
                    continue;
                }
            }

            if ($name === 'HOSPCODE' && (!preg_match('/^\d{5}$/', $value))) {
                $errors[$name] = 'HOSPCODE ต้องเป็นตัวเลข 5 หลัก';
                continue;
            }

            if ($name === 'HOSPCODE9' && $value !== '' && !preg_match('/^(?:[0-9]{9}|[A-Z]{2}[0-9]{7})$/D', strtoupper($value))) {
                $errors[$name] = 'HOSPCODE9 ต้องเป็นตัวเลข 9 หลักเดิม หรือรหัสใหม่ (อักษร 2 ตัว + ตัวเลข 7 หลัก)';
                continue;
            }

            if (in_array($name, ['TAMBON','AMPUR','CHANGWAT'], true) && !preg_match('/^\d{2}$/', $value)) {
                $errors[$name] = 'รหัสพื้นที่ต้องเป็นตัวเลข 2 หลัก';
                continue;
            }

            if ($name === 'VILLAGE' && self::normalizeVillage($value) === null) {
                $errors[$name] = 'รหัสหมู่บ้านไม่ถูกต้อง';
                continue;
            }

            if (($field['type'] ?? '') === 'decimal' && !is_numeric($value)) {
                $errors[$name] = 'กรุณากรอกเป็นตัวเลข';
                continue;
            }

            if ($name === 'VID' && !preg_match('/^[0-9]{6}[0-9A-Z]{2}$/', strtoupper($value))) {
                $errors[$name] = 'VID ต้องเป็น CCAATTMM จำนวน 8 หลัก/ตัวอักษร เช่น 33010101 หรือ 330101A0';
                continue;
            }

            if ($name === 'TIME_SERV' && !preg_match('/^(?:[01]\d|2[0-3])[0-5]\d[0-5]\d$/', $value)) {
                $errors[$name] = 'เวลาให้บริการต้องเป็น HHMMSS';
                continue;
            }

            if ($name === 'LATITUDE') {
                if (!is_numeric($value) || (float)$value < -90 || (float)$value > 90) {
                    $errors[$name] = 'Latitude ต้องอยู่ระหว่าง -90 ถึง 90';
                }
            }

            if ($name === 'LONGITUDE') {
                if (!is_numeric($value) || (float)$value < -180 || (float)$value > 180) {
                    $errors[$name] = 'Longitude ต้องอยู่ระหว่าง -180 ถึง 180';
                }
            }
        }

        return $errors;
    }

    public static function validateRecord(string $fileCode, array $data): array
    {
        $errors = [];
        $cid = trim((string)($data['CID'] ?? ''));
        if ($cid !== '' && !self::validateCid($cid)) $errors['CID'] = 'เลขบัตรประชาชน 13 หลักไม่ผ่าน checksum';

        foreach (['BIRTH','MOVEIN','DDISCHARGE','DDEATH','DATE_DIAG','DATE_DISCH','DATE_SERV','DATE_DETECT','DATE_DISAB','LMP','DATE_HCT'] as $field) {
            if (!empty($data[$field]) && !self::validateDate8((string)$data[$field])) {
                $errors[$field] = 'วันที่ไม่ถูกต้องหรือเกินวันที่ปัจจุบัน';
            }
        }

        // EDC is an expected delivery date and is allowed to be in the future.
        if (!empty($data['EDC']) && !self::validateDate8((string)$data['EDC'], true)) {
            $errors['EDC'] = 'กำหนดคลอดไม่ใช่วันที่ที่ถูกต้อง';
        }

        foreach (['DIAGCODE','CHRONIC','CDEATH_A','CDEATH_B','CDEATH_C','CDEATH_D','ODISEASE'] as $field) {
            if (!empty($data[$field]) && !self::validateIcd10Tm((string)$data[$field])) {
                $errors[$field] = 'รหัส ICD-10-TM ไม่ถูกต้อง';
            }
        }
        if (!empty($data['CDEATH']) && !self::validateCdeath((string)$data['CDEATH'])) {
            $errors['CDEATH'] = 'CDEATH ต้องเป็น ICD-10-TM และห้ามขึ้นต้นด้วย S/T/Z';
        }

        if ($fileCode === 'PERSON' && in_array((string)($data['DISCHARGE'] ?? ''), ['1','2','3'], true) && empty($data['DDISCHARGE'])) {
            $errors['DDISCHARGE'] = 'เมื่อ DISCHARGE = 1/2/3 ต้องระบุวันที่จำหน่าย';
        }

        if ($fileCode === 'HOME' && !empty($data['OUTDATE']) && !self::validateDateTime14((string)$data['OUTDATE'])) {
            $errors['OUTDATE'] = 'OUTDATE ของ HOME ต้องเป็น YYYYMMDDHHMMSS';
        }

        if ($fileCode === 'VILLAGE' && !empty($data['OUTDATE']) && !self::validateDate8((string)$data['OUTDATE'])) {
            $errors['OUTDATE'] = 'OUTDATE ของ VILLAGE ต้องเป็น YYYYMMDD';
        }

        if ($fileCode === 'CHRONIC') {
            $status = (string)($data['TYPEDISCH'] ?? '');
            if ($status !== '' && !in_array($status, ['03','05'], true) && empty($data['DATE_DISCH'])) {
                $errors['DATE_DISCH'] = 'สถานะโรคนี้ควรระบุวันที่จำหน่าย';
            }
        }
        if ($fileCode === 'DEATH' && empty($data['DDEATH'])) {
            $errors['DDEATH'] = 'กรุณาระบุวันที่เสียชีวิต';
        }

        if ($fileCode === 'SERVICE') {
            if ((string)($data['TYPEIN'] ?? '') === '3' && empty($data['REFERINHOSP'])) {
                $errors['REFERINHOSP'] = 'เมื่อ TYPEIN = 3 ต้องระบุหน่วยบริการที่ส่งมา';
            }
            if ((string)($data['TYPEOUT'] ?? '') === '3' && empty($data['REFEROUTHOSP'])) {
                $errors['REFEROUTHOSP'] = 'เมื่อ TYPEOUT = 3 ต้องระบุหน่วยบริการที่ส่งต่อ';
            }
        }

        if ($fileCode === 'NCDSCREEN') {
            $bsTest = (string)($data['BSTEST'] ?? '');
            if ($bsTest !== '' && $bsTest !== '9' && trim((string)($data['BSLEVEL'] ?? '')) === '') {
                $errors['BSLEVEL'] = 'เมื่อมีการตรวจน้ำตาล ต้องระบุระดับน้ำตาลในเลือด';
            }
        }

        if ($fileCode === 'HOME') {
            $lat = trim((string)($data['LATITUDE'] ?? ''));
            $lng = trim((string)($data['LONGITUDE'] ?? ''));
            if (($lat === '') xor ($lng === '')) {
                $errors[$lat === '' ? 'LATITUDE' : 'LONGITUDE'] = 'Latitude และ Longitude ต้องบันทึกเป็นคู่';
            }
        }

        if ($fileCode === 'ANC') {
            $ga = trim((string)($data['GA'] ?? ''));
            if ($ga !== '' && (!ctype_digit($ga) || (int)$ga < 0 || (int)$ga > 45)) {
                $errors['GA'] = 'อายุครรภ์ต้องอยู่ระหว่าง 0-45 สัปดาห์';
            }
        }

        if ($fileCode === 'PRENATAL') {
            $lmp = preg_replace('/\D/', '', (string)($data['LMP'] ?? ''));
            $edc = preg_replace('/\D/', '', (string)($data['EDC'] ?? ''));
            if (strlen($lmp) === 8 && strlen($edc) === 8 && $edc < $lmp) {
                $errors['EDC'] = 'กำหนดคลอดต้องไม่น้อยกว่าวันแรกของประจำเดือนครั้งสุดท้าย';
            }
        }

        return $errors;
    }
}
?>