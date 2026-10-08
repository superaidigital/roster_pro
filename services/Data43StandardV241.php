<?php
/**
 * Standard health-data catalog derived from MOPH data structure manual Version 2.4.1.
 *
 * This class intentionally stores only structure metadata used by the importer:
 * - canonical file codes 1..52
 * - whether the manual marks รพ.สต. as a recording unit
 * - minimum headers needed for safe linkage / analytics
 *
 * It is not a replacement for the full MOPH code-set dictionaries.
 */
final class Data43StandardV241
{
    public const VERSION = '2.4.1';
    public const PROFILE = 'RPHST_V241';

    public static function catalog(): array
    {
        return [
            ['no'=>1,'code'=>'PERSON','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>2,'code'=>'ADDRESS','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>3,'code'=>'DEATH','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>4,'code'=>'CHRONIC','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>5,'code'=>'CARD','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>6,'code'=>'HOME','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>7,'code'=>'VILLAGE','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>8,'code'=>'DISABILITY','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>9,'code'=>'PROVIDER','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>10,'code'=>'WOMEN','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>11,'code'=>'DRUGALLERGY','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>12,'code'=>'FUNCTIONAL','types'=>['SERVICE'],'rphst'=>false],
            ['no'=>13,'code'=>'ICF','types'=>['SERVICE'],'rphst'=>false],
            ['no'=>14,'code'=>'SERVICE','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>15,'code'=>'DIAGNOSIS_OPD','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>16,'code'=>'DRUG_OPD','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>17,'code'=>'PROCEDURE_OPD','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>18,'code'=>'CHARGE_OPD','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>19,'code'=>'SURVEILLANCE','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>20,'code'=>'ACCIDENT','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>21,'code'=>'LABFU','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>22,'code'=>'CHRONICFU','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>23,'code'=>'ADMISSION','types'=>['SERVICE'],'rphst'=>false],
            ['no'=>24,'code'=>'DIAGNOSIS_IPD','types'=>['SERVICE'],'rphst'=>false],
            ['no'=>25,'code'=>'DRUG_IPD','types'=>['SERVICE'],'rphst'=>false],
            ['no'=>26,'code'=>'PROCEDURE_IPD','types'=>['SERVICE'],'rphst'=>false],
            ['no'=>27,'code'=>'CHARGE_IPD','types'=>['SERVICE'],'rphst'=>false],
            ['no'=>28,'code'=>'APPOINTMENT','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>29,'code'=>'DENTAL','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>30,'code'=>'REHABILITATION','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>31,'code'=>'NCDSCREEN','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>32,'code'=>'FP','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>33,'code'=>'PRENATAL','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>34,'code'=>'ANC','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>35,'code'=>'LABOR','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>36,'code'=>'POSTNATAL','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>37,'code'=>'NEWBORN','types'=>['ACCUMULATED'],'rphst'=>true],
            ['no'=>38,'code'=>'NEWBORNCARE','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>39,'code'=>'EPI','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>40,'code'=>'NUTRITION','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>41,'code'=>'SPECIALPP','types'=>['SEMI_SURVEY'],'rphst'=>true],
            ['no'=>42,'code'=>'COMMUNITY_ACTIVITY','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>43,'code'=>'COMMUNITY_SERVICE','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>44,'code'=>'CARE_REFER','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>45,'code'=>'CLINICAL_REFER','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>46,'code'=>'DRUG_REFER','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>47,'code'=>'INVESTIGATION_REFER','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>48,'code'=>'PROCEDURE_REFER','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>49,'code'=>'REFER_HISTORY','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>50,'code'=>'REFER_RESULT','types'=>['SERVICE'],'rphst'=>true],
            ['no'=>51,'code'=>'DATA_CORRECT','types'=>['CORRECTION'],'rphst'=>true],
            ['no'=>52,'code'=>'POLICY','types'=>['ACCUMULATED','SERVICE','POLICY'],'rphst'=>true],
        ];
    }

    public static function catalogByCode(): array
    {
        $out = [];
        foreach (self::catalog() as $row) {
            $out[$row['code']] = $row;
        }
        return $out;
    }

    public static function rphstExpectedCodes(): array
    {
        return array_values(array_map(
            static fn(array $row): string => $row['code'],
            array_filter(self::catalog(), static fn(array $row): bool => $row['rphst'])
        ));
    }

    public static function totalStructures(): int
    {
        return count(self::catalog());
    }

    public static function rphstExpectedCount(): int
    {
        return count(self::rphstExpectedCodes());
    }

    public static function canonicalFileCode(string $filename): ?string
    {
        $base = strtoupper(pathinfo(str_replace('\\','/',$filename), PATHINFO_FILENAME));
        $base = preg_replace('/[^A-Z0-9]+/', '_', $base) ?: '';
        $haystack = '_' . trim($base, '_') . '_';

        $codes = array_keys(self::catalogByCode());
        usort($codes, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            if (str_contains($haystack, '_' . $code . '_')) {
                return $code;
            }
            if (str_starts_with(trim($base, '_'), $code)) {
                $next = substr(trim($base, '_'), strlen($code), 1);
                if ($next === '' || $next === '_' || ctype_digit($next)) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * Minimum columns used for structure/linkage validation.
     * These are deliberately narrower than the complete 2.4.1 table definitions.
     */
    public static function criticalHeaders(string $fileCode): array
    {
        return match ($fileCode) {
            'PERSON' => ['HOSPCODE','PID','HID','BIRTH','DISCHARGE','TYPEAREA','D_UPDATE'],
            'ADDRESS' => ['HOSPCODE','PID','ADDRESSTYPE','VILLAGE','TAMBON','AMPUR','CHANGWAT','D_UPDATE'],
            'CHRONIC' => ['HOSPCODE','PID','DATE_DIAG','CHRONIC','TYPEDISCH','D_UPDATE'],
            'HOME' => ['HOSPCODE','HID','VILLAGE','TAMBON','AMPUR','CHANGWAT','D_UPDATE'],
            'VILLAGE' => ['HOSPCODE','VID','D_UPDATE'],
            'DISABILITY' => ['HOSPCODE','PID','DISABTYPE','DATE_DETECT','D_UPDATE'],
            'SERVICE' => ['HOSPCODE','PID','SEQ','DATE_SERV'],
            'NCDSCREEN' => ['HOSPCODE','PID','DATE_SERV','SBP_1','DBP_1'],
            'PRENATAL' => ['HOSPCODE','PID','GRAVIDA','D_UPDATE'],
            'ANC' => ['HOSPCODE','PID','DATE_SERV','GRAVIDA','GA','ANCRESULT','D_UPDATE'],
            default => [],
        };
    }

    public static function primaryKeyHeaders(string $fileCode): array
    {
        return match ($fileCode) {
            'PERSON' => ['HOSPCODE','PID'],
            'HOME' => ['HOSPCODE','HID'],
            'VILLAGE' => ['HOSPCODE','VID'],
            'CHRONIC' => ['HOSPCODE','PID','DATE_DIAG','CHRONIC'],
            'DISABILITY' => ['HOSPCODE','PID','DISABTYPE'],
            'SERVICE' => ['HOSPCODE','PID','SEQ'],
            'PRENATAL' => ['HOSPCODE','PID','GRAVIDA'],
            default => [],
        };
    }
}
?>