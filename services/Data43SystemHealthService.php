<?php
require_once __DIR__ . '/Data43CryptoService.php';

final class Data43SystemHealthService
{
    public function __construct(private PDO $db) {}

    public function inspect(): array
    {
        $checks = [];

        $checks[] = $this->checkExtension('PDO MySQL', extension_loaded('pdo_mysql'), 'เปิด extension pdo_mysql');
        $checks[] = $this->checkExtension('ZIP', class_exists('ZipArchive'), 'เปิด extension zip');
        $checks[] = $this->checkExtension('Multibyte', extension_loaded('mbstring'), 'เปิด extension mbstring');
        $cryptoReady = function_exists('sodium_crypto_secretbox') || function_exists('openssl_encrypt');
        $checks[] = $this->checkExtension('Encryption', $cryptoReady, 'เปิด Sodium หรือ OpenSSL');

        foreach ([
            'data43_submissions',
            'data43_submission_files',
            'data43_area_metrics',
            'data43_quality_summary',
            'data43_quality_issues',
            'data43_records',
            'data43_search_tokens',
            'data43_record_audit',
        ] as $table) {
            $checks[] = $this->checkTable($table);
        }

        foreach ([
            ['data43_submissions','standard_version'],
            ['data43_submissions','profile_code'],
            ['hospitals','hospital_code9'],
        ] as [$table,$column]) {
            $checks[] = $this->checkColumn($table,$column);
        }

        $checks[] = $this->checkEncryptionKey();
        $checks[] = $this->checkHospitalCode9Coverage();

        $projectRoot = dirname(__DIR__);
        foreach ([
            $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'data43_temp',
            $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'data43_export',
        ] as $path) {
            $checks[] = $this->checkWritableDirectory($path);
        }

        $error = 0;
        $warning = 0;
        foreach ($checks as $check) {
            if ($check['status'] === 'ERROR') $error++;
            elseif ($check['status'] === 'WARNING') $warning++;
        }

        return [
            'status' => $error > 0 ? 'ERROR' : ($warning > 0 ? 'WARNING' : 'OK'),
            'error_count' => $error,
            'warning_count' => $warning,
            'checks' => $checks,
        ];
    }

    private function checkExtension(string $label, bool $ok, string $fix): array
    {
        return [
            'group' => 'PHP',
            'name' => $label,
            'status' => $ok ? 'OK' : 'ERROR',
            'detail' => $ok ? 'พร้อมใช้งาน' : 'ยังไม่พร้อมใช้งาน',
            'fix' => $ok ? null : $fix,
        ];
    }

    private function checkTable(string $table): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?"
            );
            $stmt->execute([$table]);
            $ok = (int)$stmt->fetchColumn() === 1;
        } catch (Throwable $e) {
            $ok = false;
        }

        return [
            'group' => 'Database',
            'name' => $table,
            'status' => $ok ? 'OK' : 'ERROR',
            'detail' => $ok ? 'พบตาราง' : 'ไม่พบตาราง',
            'fix' => $ok ? null : 'รัน migration ที่เกี่ยวข้องกับ ' . $table,
        ];
    }

    private function checkColumn(string $table, string $column): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
            );
            $stmt->execute([$table,$column]);
            $ok = (int)$stmt->fetchColumn() === 1;
        } catch (Throwable $e) {
            $ok = false;
        }

        return [
            'group' => 'Database Column',
            'name' => $table . '.' . $column,
            'status' => $ok ? 'OK' : 'ERROR',
            'detail' => $ok ? 'พร้อมใช้งาน' : 'ยังไม่มีคอลัมน์',
            'fix' => $ok ? null : 'รัน migration ล่าสุดของ Data43',
        ];
    }

    private function checkEncryptionKey(): array
    {
        try {
            new Data43CryptoService();
            return [
                'group' => 'Security',
                'name' => 'DATA43 encryption key',
                'status' => 'OK',
                'detail' => 'พร้อมเข้ารหัส/ถอดรหัส',
                'fix' => null,
            ];
        } catch (Throwable $e) {
            return [
                'group' => 'Security',
                'name' => 'DATA43 encryption key',
                'status' => 'ERROR',
                'detail' => $e->getMessage(),
                'fix' => 'ตั้งค่า DATA43_RECORD_KEY สำหรับ Production หรือให้ Development สร้าง key ถาวร',
            ];
        }
    }

    private function checkHospitalCode9Coverage(): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'hospitals'
                   AND column_name = 'hospital_code9'"
            );
            $stmt->execute();
            if ((int)$stmt->fetchColumn() !== 1) {
                return [
                    'group' => 'Data Standard',
                    'name' => 'HOSPCODE9 coverage',
                    'status' => 'ERROR',
                    'detail' => 'ตาราง hospitals ยังไม่มี hospital_code9',
                    'fix' => 'รัน migration 20261007_data43_hospital_code9.sql',
                ];
            }

            $row = $this->db->query(
                "SELECT
                    SUM(CASE WHEN is_active=1 AND deleted_at IS NULL AND COALESCE(hospital_code,'') <> '0' THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN is_active=1 AND deleted_at IS NULL AND COALESCE(hospital_code,'') <> '0'
                              AND (hospital_code9 IS NULL OR TRIM(hospital_code9)='' OR hospital_code9 NOT REGEXP '^[0-9]{9}
        $ok = true;
        if (!is_dir($path)) {
            $ok = @mkdir($path, 0750, true) || is_dir($path);
        }
        if ($ok) $ok = is_writable($path);

        return [
            'group' => 'Storage',
            'name' => $path,
            'status' => $ok ? 'OK' : 'ERROR',
            'detail' => $ok ? 'เขียนได้' : 'ไม่สามารถเขียนได้',
            'fix' => $ok ? null : 'ตรวจสิทธิ์โฟลเดอร์ของ Apache/PHP',
        ];
    }
}
?>)
                             THEN 1 ELSE 0 END) AS missing_count
                 FROM hospitals"
            )->fetch(PDO::FETCH_ASSOC) ?: ['active_count'=>0,'missing_count'=>0];

            $missing = (int)($row['missing_count'] ?? 0);
            $active = (int)($row['active_count'] ?? 0);

            return [
                'group' => 'Data Standard',
                'name' => 'HOSPCODE9 coverage',
                'status' => $missing > 0 ? 'WARNING' : 'OK',
                'detail' => $missing > 0
                    ? "มีหน่วยบริการ {$missing}/{$active} แห่งที่ยังไม่มี HOSPCODE9 9 หลัก"
                    : "หน่วยบริการที่ใช้งานมี HOSPCODE9 ครบ {$active} แห่ง",
                'fix' => $missing > 0 ? 'กรอก hospital_code9 ให้ครบก่อนส่งออกแฟ้มที่มาตรฐานกำหนด' : null,
            ];
        } catch (Throwable $e) {
            return [
                'group' => 'Data Standard',
                'name' => 'HOSPCODE9 coverage',
                'status' => 'WARNING',
                'detail' => 'ตรวจสอบ HOSPCODE9 ไม่สำเร็จ',
                'fix' => 'ตรวจ schema ตาราง hospitals',
            ];
        }
    }

    private function checkWritableDirectory(string $path): array
    {
        $ok = true;
        if (!is_dir($path)) {
            $ok = @mkdir($path, 0750, true) || is_dir($path);
        }
        if ($ok) $ok = is_writable($path);

        return [
            'group' => 'Storage',
            'name' => $path,
            'status' => $ok ? 'OK' : 'ERROR',
            'detail' => $ok ? 'เขียนได้' : 'ไม่สามารถเขียนได้',
            'fix' => $ok ? null : 'ตรวจสิทธิ์โฟลเดอร์ของ Apache/PHP',
        ];
    }
}
?>