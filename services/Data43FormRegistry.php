<?php
final class Data43FormRegistry
{
    public static function schemas(): array
    {
        return [
            'PERSON' => [
                'label'=>'บุคคล',
                'icon'=>'bi-person-vcard',
                'pk'=>['HOSPCODE','PID'],
                'fields'=>[
                    ['name'=>'HOSPCODE','label'=>'รหัสหน่วยบริการ','type'=>'auto','required'=>true],
                    ['name'=>'HOSPCODE9','label'=>'รหัสหน่วยบริการ 9 หลัก','type'=>'auto'],
                    ['name'=>'PID','label'=>'PID','type'=>'auto','required'=>true],
                    ['name'=>'CID','label'=>'เลขบัตรประชาชน','type'=>'text','maxlength'=>13],
                    ['name'=>'HID','label'=>'HID / บ้าน','type'=>'home-search'],
                    ['name'=>'PRENAME','label'=>'คำนำหน้า','type'=>'text'],
                    ['name'=>'NAME','label'=>'ชื่อ','type'=>'text','required'=>true],
                    ['name'=>'LNAME','label'=>'นามสกุล','type'=>'text','required'=>true],
                    ['name'=>'SEX','label'=>'เพศ','type'=>'select','options'=>['1'=>'ชาย','2'=>'หญิง']],
                    ['name'=>'BIRTH','label'=>'วันเกิด','type'=>'thai-date','required'=>true],
                    ['name'=>'TYPEAREA','label'=>'สถานะบุคคลในพื้นที่','type'=>'select','required'=>true,'options'=>[
                        '1'=>'มีชื่อในทะเบียนบ้านและอยู่จริง',
                        '2'=>'มีชื่อในทะเบียนบ้านแต่ไม่อยู่จริง',
                        '3'=>'อาศัยอยู่จริงแต่ทะเบียนบ้านอยู่นอกพื้นที่',
                        '4'=>'อยู่นอกพื้นที่/มารับบริการ',
                        '5'=>'อาศัยในพื้นที่แต่ไม่มีทะเบียนบ้านในพื้นที่',
                    ]],
                    ['name'=>'DISCHARGE','label'=>'สถานะจำหน่าย','type'=>'select','options'=>[
                        '1'=>'ตาย','2'=>'ย้าย','3'=>'สาบสูญ','9'=>'ไม่จำหน่าย'
                    ]],
                    ['name'=>'DDISCHARGE','label'=>'วันที่จำหน่าย','type'=>'thai-date'],
                    ['name'=>'D_UPDATE','label'=>'วันเวลาปรับปรุง','type'=>'auto','required'=>true],
                ],
            ],
            'HOME' => [
                'label'=>'บ้าน',
                'icon'=>'bi-house-heart',
                'pk'=>['HOSPCODE','HID'],
                'fields'=>[
                    ['name'=>'HOSPCODE','label'=>'รหัสหน่วยบริการ','type'=>'auto','required'=>true],
                    ['name'=>'HID','label'=>'HID','type'=>'auto','required'=>true],
                    ['name'=>'HOUSE','label'=>'บ้านเลขที่','type'=>'text'],
                    ['name'=>'VILLAGE','label'=>'หมู่บ้าน','type'=>'text','placeholder'=>'01 หรือ A0'],
                    ['name'=>'TAMBON','label'=>'ตำบล (รหัส 2 หลัก)','type'=>'text','maxlength'=>2],
                    ['name'=>'AMPUR','label'=>'อำเภอ (รหัส 2 หลัก)','type'=>'text','maxlength'=>2],
                    ['name'=>'CHANGWAT','label'=>'จังหวัด (รหัส 2 หลัก)','type'=>'text','maxlength'=>2],
                    ['name'=>'LATITUDE','label'=>'Latitude','type'=>'decimal','readonly'=>true],
                    ['name'=>'LONGITUDE','label'=>'Longitude','type'=>'decimal','readonly'=>true],
                    ['name'=>'D_UPDATE','label'=>'วันเวลาปรับปรุง','type'=>'auto','required'=>true],
                ],
            ],
            'ADDRESS' => [
                'label'=>'ที่อยู่',
                'icon'=>'bi-geo-alt',
                'pk'=>['HOSPCODE','PID','ADDRESSTYPE'],
                'fields'=>[
                    ['name'=>'HOSPCODE','label'=>'รหัสหน่วยบริการ','type'=>'auto','required'=>true],
                    ['name'=>'PID','label'=>'บุคคล','type'=>'person-search','required'=>true],
                    ['name'=>'ADDRESSTYPE','label'=>'ประเภทที่อยู่','type'=>'select','required'=>true,'options'=>['1'=>'ทะเบียนบ้าน','2'=>'ที่อยู่ปัจจุบัน']],
                    ['name'=>'HOUSE_ID','label'=>'รหัสบ้านตามทะเบียน','type'=>'text'],
                    ['name'=>'HOUSE','label'=>'บ้านเลขที่','type'=>'text'],
                    ['name'=>'VILLAGE','label'=>'หมู่บ้าน','type'=>'text'],
                    ['name'=>'TAMBON','label'=>'ตำบล','type'=>'text','maxlength'=>2],
                    ['name'=>'AMPUR','label'=>'อำเภอ','type'=>'text','maxlength'=>2],
                    ['name'=>'CHANGWAT','label'=>'จังหวัด','type'=>'text','maxlength'=>2],
                    ['name'=>'D_UPDATE','label'=>'วันเวลาปรับปรุง','type'=>'auto','required'=>true],
                ],
            ],
            'CHRONIC' => [
                'label'=>'โรคเรื้อรัง',
                'icon'=>'bi-heart-pulse',
                'pk'=>['HOSPCODE','PID','DATE_DIAG','CHRONIC'],
                'fields'=>[
                    ['name'=>'HOSPCODE','label'=>'รหัสหน่วยบริการ','type'=>'auto','required'=>true],
                    ['name'=>'PID','label'=>'บุคคล','type'=>'person-search','required'=>true],
                    ['name'=>'CID','label'=>'CID','type'=>'auto-person'],
                    ['name'=>'DATE_DIAG','label'=>'วันที่วินิจฉัย','type'=>'thai-date','required'=>true],
                    ['name'=>'CHRONIC','label'=>'รหัส ICD-10-TM','type'=>'text','required'=>true,'placeholder'=>'เช่น E11.9'],
                    ['name'=>'HOSP_DX','label'=>'หน่วยบริการที่วินิจฉัย','type'=>'text'],
                    ['name'=>'TYPEDISCH','label'=>'สถานะโรค','type'=>'select','options'=>[
                        '01'=>'หาย','02'=>'ตาย','03'=>'ยังรักษาอยู่','04'=>'ไม่ทราบ','05'=>'รอจำหน่าย/เฝ้าระวัง',
                        '06'=>'ขาดการรักษา','07'=>'ครบการรักษา','08'=>'ภาวะสงบ','09'=>'ปฏิเสธการรักษา','10'=>'ออกจากพื้นที่','11'=>'กลับเป็นซ้ำ'
                    ]],
                    ['name'=>'D_UPDATE','label'=>'วันเวลาปรับปรุง','type'=>'auto','required'=>true],
                ],
            ],
            'DEATH' => [
                'label'=>'เสียชีวิต',
                'icon'=>'bi-file-medical',
                'pk'=>['HOSPCODE','PID'],
                'fields'=>[
                    ['name'=>'HOSPCODE','label'=>'รหัสหน่วยบริการ','type'=>'auto','required'=>true],
                    ['name'=>'PID','label'=>'บุคคล','type'=>'person-search','required'=>true],
                    ['name'=>'CID','label'=>'CID','type'=>'auto-person'],
                    ['name'=>'DDEATH','label'=>'วันที่เสียชีวิต','type'=>'thai-date','required'=>true],
                    ['name'=>'CDEATH','label'=>'สาเหตุการตายหลัก (ICD-10-TM)','type'=>'text','required'=>true],
                    ['name'=>'CAUSEDEATH_A','label'=>'สาเหตุ A','type'=>'text'],
                    ['name'=>'CAUSEDEATH_B','label'=>'สาเหตุ B','type'=>'text'],
                    ['name'=>'CAUSEDEATH_C','label'=>'สาเหตุ C','type'=>'text'],
                    ['name'=>'CAUSEDEATH_D','label'=>'สาเหตุ D','type'=>'text'],
                    ['name'=>'D_UPDATE','label'=>'วันเวลาปรับปรุง','type'=>'auto','required'=>true],
                ],
            ],
        ];
    }

    public static function get(string $code): ?array
    {
        $code = strtoupper(trim($code));
        $all = self::schemas();
        return isset($all[$code]) ? ['code'=>$code] + $all[$code] : null;
    }

    public static function codes(): array
    {
        return array_keys(self::schemas());
    }

    public static function exportHeaders(string $code): array
    {
        $schema = self::get($code);
        if (!$schema) return [];
        return array_values(array_map(static fn(array $f): string => $f['name'], $schema['fields']));
    }
}
?>