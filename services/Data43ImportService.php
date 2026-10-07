<?php
class Data43ImportService {
    private const MAX_ZIP_BYTES = 209715200; // 200 MB
    private const MAX_ENTRIES = 300;
    private const MAX_TOTAL_UNCOMPRESSED = 1073741824; // 1 GB
    private const MAX_ENTRY_BYTES = 268435456; // 256 MB
    private const MAX_COMPRESSION_RATIO = 200;

    public function inspectUploadedZip(array $upload, string $workDir): array {
        $this->validateUpload($upload);

        if (!is_dir($workDir) && !mkdir($workDir, 0750, true) && !is_dir($workDir)) {
            throw new RuntimeException('ไม่สามารถสร้างพื้นที่ประมวลผลชั่วคราวได้');
        }

        $zipPath = $workDir . DIRECTORY_SEPARATOR . 'submission.zip';
        if (!move_uploaded_file($upload['tmp_name'], $zipPath)) {
            throw new RuntimeException('ไม่สามารถบันทึกไฟล์ ZIP ชั่วคราวได้');
        }

        $archiveHash = hash_file('sha256', $zipPath);
        $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extracted';
        mkdir($extractDir, 0750, true);

        $files = $this->extractSafe($zipPath, $extractDir);

        return [
            'archive_sha256' => $archiveHash,
            'files' => $files,
        ];
    }

    private function validateUpload(array $upload): void {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ');
        }
        if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
        }

        $size = (int)($upload['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_ZIP_BYTES) {
            throw new RuntimeException('ไฟล์ ZIP ต้องมีขนาดไม่เกิน 200 MB');
        }

        $name = (string)($upload['name'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('รองรับเฉพาะไฟล์ .zip');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($upload['tmp_name']);
        if (!in_array($mime, [
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream'
        ], true)) {
            throw new RuntimeException('ชนิดไฟล์ไม่ใช่ ZIP ที่รองรับ');
        }
    }

    private function extractSafe(string $zipPath, string $extractDir): array {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP Zip extension ยังไม่ได้เปิดใช้งาน');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ไม่สามารถเปิดไฟล์ ZIP ได้');
        }

        try {
            if ($zip->numFiles <= 0) {
                throw new RuntimeException('ZIP ไม่มีไฟล์ข้อมูล');
            }
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('ZIP มีจำนวนไฟล์มากเกินกำหนด');
            }

            $totalUncompressed = 0;
            $result = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
                if (!is_array($stat)) continue;

                $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));
                if ($name === '' || str_ends_with($name, '/')) continue;

                $this->assertSafePath($name);

                $uncompressed = (int)($stat['size'] ?? 0);
                $compressed = max(1, (int)($stat['comp_size'] ?? 1));

                if ($uncompressed > self::MAX_ENTRY_BYTES) {
                    throw new RuntimeException('พบไฟล์ภายใน ZIP ที่มีขนาดใหญ่เกินกำหนด');
                }

                $ratio = $uncompressed / $compressed;
                if ($ratio > self::MAX_COMPRESSION_RATIO) {
                    throw new RuntimeException('ZIP มีอัตราการบีบอัดผิดปกติ');
                }

                $totalUncompressed += $uncompressed;
                if ($totalUncompressed > self::MAX_TOTAL_UNCOMPRESSED) {
                    throw new RuntimeException('ขนาดข้อมูลหลังแตก ZIP มากเกินกำหนด');
                }

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
                    continue;
                }

                $targetName = bin2hex(random_bytes(8)) . '.' . $ext;
                $target = $extractDir . DIRECTORY_SEPARATOR . $targetName;

                $in = $zip->getStream((string)($stat['name'] ?? ''));
                if (!$in) {
                    throw new RuntimeException('ไม่สามารถอ่านไฟล์ภายใน ZIP ได้');
                }

                $out = fopen($target, 'wb');
                if (!$out) {
                    fclose($in);
                    throw new RuntimeException('ไม่สามารถสร้างไฟล์ชั่วคราวได้');
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);

                $rowCount = null;
                if (in_array($ext, ['csv', 'txt'], true)) {
                    $rowCount = $this->countTextRows($target);
                }

                $base = pathinfo($name, PATHINFO_FILENAME);
                $fileCode = strtoupper(preg_replace('/[^A-Za-z0-9_\-]/', '', $base) ?: 'UNKNOWN');

                $result[] = [
                    'file_code' => substr($fileCode, 0, 100),
                    'original_filename' => basename($name),
                    'extension' => $ext,
                    'file_sha256' => hash_file('sha256', $target),
                    'row_count' => $rowCount,
                    'file_size' => filesize($target) ?: 0,
                    'status' => 'VALID',
                    'error_message' => null,
                ];
            }

            return $result;
        } finally {
            $zip->close();
        }
    }

    private function countTextRows(string $path): int {
        $fh = fopen($path, 'rb');
        if (!$fh) return 0;

        $count = 0;
        try {
            while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
                if ($row === [null] || $row === false) continue;
                $count++;
            }
        } finally {
            fclose($fh);
        }

        return max(0, $count - 1); // ตัด header
    }

    private function assertSafePath(string $name): void {
        if (
            str_contains($name, "\0") ||
            str_starts_with($name, '/') ||
            preg_match('/^[A-Za-z]:\//', $name)
        ) {
            throw new RuntimeException('พบ path ที่ไม่ปลอดภัยใน ZIP');
        }

        foreach (explode('/', $name) as $part) {
            if ($part === '..') {
                throw new RuntimeException('ตรวจพบ Zip Slip');
            }
        }
    }

    public static function recursiveDelete(string $path): void {
        if (!file_exists($path)) return;

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            self::recursiveDelete($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}
?>