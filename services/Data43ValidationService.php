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
        return preg_match('/^[A-TV-Z][0-9]{2}(?:\.[0-9A-Z]{1,4})?$/', $code) === 1;
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

    public static function validateRecord(string $fileCode, array $data): array
    {
        $errors = [];
        $cid = trim((string)($data['CID'] ?? ''));
        if ($cid !== '' && !self::validateCid($cid)) $errors['CID'] = 'เลขบัตรประชาชน 13 หลักไม่ผ่าน checksum';

        foreach (['BIRTH','DDISCHARGE','DDEATH','DATE_DIAG','DATE_SERV','DATE_DETECT'] as $field) {
            if (!empty($data[$field]) && !self::validateDate8((string)$data[$field])) {
                $errors[$field] = 'วันที่ไม่ถูกต้องหรือเกินวันที่ปัจจุบัน';
            }
        }

        foreach (['DIAGCODE','CHRONIC','CAUSEDEATH_A','CAUSEDEATH_B','CAUSEDEATH_C','CAUSEDEATH_D'] as $field) {
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
        if ($fileCode === 'DEATH' && empty($data['DDEATH'])) $errors['DDEATH'] = 'กรุณาระบุวันที่เสียชีวิต';

        return $errors;
    }
}
?>