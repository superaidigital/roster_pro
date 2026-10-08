<?php
require_once __DIR__ . '/Data43CryptoService.php';
require_once __DIR__ . '/Data43StorageService.php';

final class Data43SystemHealthService
{
    public function __construct(private PDO $db) {}

    public function inspect(): array
    {
        $checks = [
            $this->simple('PHP','PDO MySQL',extension_loaded('pdo_mysql'),'Enable pdo_mysql'),
            $this->simple('PHP','ZIP',class_exists('ZipArchive'),'Enable PHP zip extension'),
            $this->simple('PHP','mbstring',extension_loaded('mbstring'),'Enable mbstring'),
            $this->simple('PHP','Encryption',function_exists('sodium_crypto_secretbox') || function_exists('openssl_encrypt'),'Enable Sodium or OpenSSL'),
        ];

        foreach ([
            'data43_submissions','data43_submission_files','data43_area_metrics',
            'data43_quality_summary','data43_quality_issues','data43_records',
            'data43_search_tokens','data43_record_audit'
        ] as $table) {
            $checks[] = $this->tableCheck($table);
        }

        foreach ([['data43_submissions','standard_version'],['data43_submissions','profile_code'],['hospitals','hospital_code9'],['hospitals','hospital_code9_new']] as [$table,$column]) {
            $checks[] = $this->columnCheck($table,$column);
        }

        $checks[] = $this->cryptoCheck();
        $checks[] = $this->hospcode9Check();
        $checks[] = $this->storageCheck();

        $errors = count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'ERROR'));
        $warnings = count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'WARNING'));

        return [
            'status'=>$errors ? 'ERROR' : ($warnings ? 'WARNING' : 'OK'),
            'error_count'=>$errors,
            'warning_count'=>$warnings,
            'checks'=>$checks,
        ];
    }

    private function simple(string $group,string $name,bool $ok,string $fix): array
    {
        return ['group'=>$group,'name'=>$name,'status'=>$ok?'OK':'ERROR','detail'=>$ok?'พร้อมใช้งาน':'ยังไม่พร้อมใช้งาน','fix'=>$ok?null:$fix];
    }

    private function tableCheck(string $table): array
    {
        try {
            $stmt=$this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
            $stmt->execute([$table]);
            $ok=(int)$stmt->fetchColumn()===1;
        } catch (Throwable $e) { $ok=false; }

        return ['group'=>'Database','name'=>$table,'status'=>$ok?'OK':'ERROR','detail'=>$ok?'พบตาราง':'ไม่พบตาราง','fix'=>$ok?null:'รัน migration ล่าสุดของ Data43'];
    }

    private function columnCheck(string $table,string $column): array
    {
        try {
            $stmt=$this->db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
            $stmt->execute([$table,$column]);
            $ok=(int)$stmt->fetchColumn()===1;
        } catch (Throwable $e) { $ok=false; }

        return ['group'=>'Database Column','name'=>$table.'.'.$column,'status'=>$ok?'OK':'ERROR','detail'=>$ok?'พร้อมใช้งาน':'ยังไม่มีคอลัมน์','fix'=>$ok?null:'รัน migration ล่าสุดของ Data43'];
    }

    private function cryptoCheck(): array
    {
        try {
            new Data43CryptoService();
            return ['group'=>'Security','name'=>'DATA43 encryption key','status'=>'OK','detail'=>'พร้อมเข้ารหัส/ถอดรหัส','fix'=>null];
        } catch (Throwable $e) {
            return ['group'=>'Security','name'=>'DATA43 encryption key','status'=>'ERROR','detail'=>$e->getMessage(),'fix'=>'Production ให้กำหนด DATA43_RECORD_KEY ผ่าน Apache/PHP environment'];
        }
    }

    private function hospcode9Check(): array
    {
        try {
            if ($this->columnCheck('hospitals','hospital_code9')['status'] !== 'OK') {
                return ['group'=>'Data Standard','name'=>'HOSPCODE9 coverage','status'=>'ERROR','detail'=>'ตาราง hospitals ยังไม่มี hospital_code9','fix'=>'รัน migration 20261007_data43_hospital_code9.sql'];
            }

            $sql="SELECT
                    SUM(CASE WHEN is_active=1 AND deleted_at IS NULL AND COALESCE(hospital_code,'')<>'0' THEN 1 ELSE 0 END) active_count,
                    SUM(CASE WHEN is_active=1 AND deleted_at IS NULL AND COALESCE(hospital_code,'')<>'0'
                              AND NOT (hospital_code9 REGEXP '^[0-9]{9}
                             THEN 1 ELSE 0 END) missing_count
                  FROM hospitals";
            $row=$this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
            $active=(int)($row['active_count']??0);
            $missing=(int)($row['missing_count']??0);

            return [
                'group'=>'Data Standard','name'=>'HOSPCODE9 coverage',
                'status'=>$missing?'WARNING':'OK',
                'detail'=>$missing?"ยังขาดรหัส 9 หลัก (เดิม/ใหม่) {$missing}/{$active} หน่วยบริการ":"มีรหัส 9 หลักเดิมหรือใหม่ครบ {$active} หน่วยบริการ",
                'fix'=>$missing?'ตรวจรหัส 9 หลักเดิม/ใหม่จากทะเบียนทางการ แล้วแก้ไขที่เมนูจัดการ รพ.สต.':null,
            ];
        } catch (Throwable $e) {
            return ['group'=>'Data Standard','name'=>'HOSPCODE9 coverage','status'=>'WARNING','detail'=>'ตรวจ HOSPCODE9 ไม่สำเร็จ','fix'=>'ตรวจ schema ตาราง hospitals'];
        }
    }

    private function storageCheck(): array
    {
        try {
            $root=Data43StorageService::baseRoot();
            $outside=Data43StorageService::isOutsideDocumentRoot($root);
            return [
                'group'=>'Storage','name'=>'Data43 temporary storage',
                'status'=>$outside?'OK':'WARNING',
                'detail'=>$outside?'เขียนได้และอยู่นอก DocumentRoot':'พื้นที่ชั่วคราวยังอยู่ใต้ DocumentRoot',
                'fix'=>$outside?null:'ตั้ง DATA43_TEMP_DIR ให้อยู่นอก htdocs/public',
            ];
        } catch (Throwable $e) {
            return ['group'=>'Storage','name'=>'Data43 temporary storage','status'=>'ERROR','detail'=>$e->getMessage(),'fix'=>'ตรวจสิทธิ์ temp directory ของ Apache/PHP'];
        }
    }
}
?> OR hospital_code9_new REGEXP '^[A-Z]{2}[0-9]{7}
                             THEN 1 ELSE 0 END) missing_count
                  FROM hospitals";
            $row=$this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
            $active=(int)($row['active_count']??0);
            $missing=(int)($row['missing_count']??0);

            return [
                'group'=>'Data Standard','name'=>'HOSPCODE9 coverage',
                'status'=>$missing?'WARNING':'OK',
                'detail'=>$missing?"ยังขาด HOSPCODE9 {$missing}/{$active} หน่วยบริการ":"HOSPCODE9 ครบ {$active} หน่วยบริการ",
                'fix'=>$missing?'กรอก HOSPCODE9 ให้ครบก่อนส่งออกข้อมูลมาตรฐาน':null,
            ];
        } catch (Throwable $e) {
            return ['group'=>'Data Standard','name'=>'HOSPCODE9 coverage','status'=>'WARNING','detail'=>'ตรวจ HOSPCODE9 ไม่สำเร็จ','fix'=>'ตรวจ schema ตาราง hospitals'];
        }
    }

    private function storageCheck(): array
    {
        try {
            $root=Data43StorageService::baseRoot();
            $outside=Data43StorageService::isOutsideDocumentRoot($root);
            return [
                'group'=>'Storage','name'=>'Data43 temporary storage',
                'status'=>$outside?'OK':'WARNING',
                'detail'=>$outside?'เขียนได้และอยู่นอก DocumentRoot':'พื้นที่ชั่วคราวยังอยู่ใต้ DocumentRoot',
                'fix'=>$outside?null:'ตั้ง DATA43_TEMP_DIR ให้อยู่นอก htdocs/public',
            ];
        } catch (Throwable $e) {
            return ['group'=>'Storage','name'=>'Data43 temporary storage','status'=>'ERROR','detail'=>$e->getMessage(),'fix'=>'ตรวจสิทธิ์ temp directory ของ Apache/PHP'];
        }
    }
}
?>)
                             THEN 1 ELSE 0 END) missing_count
                  FROM hospitals";
            $row=$this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
            $active=(int)($row['active_count']??0);
            $missing=(int)($row['missing_count']??0);

            return [
                'group'=>'Data Standard','name'=>'HOSPCODE9 coverage',
                'status'=>$missing?'WARNING':'OK',
                'detail'=>$missing?"ยังขาด HOSPCODE9 {$missing}/{$active} หน่วยบริการ":"HOSPCODE9 ครบ {$active} หน่วยบริการ",
                'fix'=>$missing?'กรอก HOSPCODE9 ให้ครบก่อนส่งออกข้อมูลมาตรฐาน':null,
            ];
        } catch (Throwable $e) {
            return ['group'=>'Data Standard','name'=>'HOSPCODE9 coverage','status'=>'WARNING','detail'=>'ตรวจ HOSPCODE9 ไม่สำเร็จ','fix'=>'ตรวจ schema ตาราง hospitals'];
        }
    }

    private function storageCheck(): array
    {
        try {
            $root=Data43StorageService::baseRoot();
            $outside=Data43StorageService::isOutsideDocumentRoot($root);
            return [
                'group'=>'Storage','name'=>'Data43 temporary storage',
                'status'=>$outside?'OK':'WARNING',
                'detail'=>$outside?'เขียนได้และอยู่นอก DocumentRoot':'พื้นที่ชั่วคราวยังอยู่ใต้ DocumentRoot',
                'fix'=>$outside?null:'ตั้ง DATA43_TEMP_DIR ให้อยู่นอก htdocs/public',
            ];
        } catch (Throwable $e) {
            return ['group'=>'Storage','name'=>'Data43 temporary storage','status'=>'ERROR','detail'=>$e->getMessage(),'fix'=>'ตรวจสิทธิ์ temp directory ของ Apache/PHP'];
        }
    }
}
?>