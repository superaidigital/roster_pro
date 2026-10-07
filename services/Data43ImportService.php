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

        $inspection = $this->extractSafe($zipPath, $extractDir);

        return [
            'archive_sha256' => $archiveHash,
            'files' => $inspection['files'],
            'spatial_metrics' => $inspection['spatial_metrics'],
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
            $spatialMetrics = [];

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

                $normalizedFileCode = substr($fileCode, 0, 100);

                $result[] = [
                    'file_code' => $normalizedFileCode,
                    'original_filename' => basename($name),
                    'extension' => $ext,
                    'file_sha256' => hash_file('sha256', $target),
                    'row_count' => $rowCount,
                    'file_size' => filesize($target) ?: 0,
                    'status' => 'VALID',
                    'error_message' => null,
                ];

                // Spatial analytics is intentionally aggregate-only.
                // XLSX remains metadata-only in this phase to avoid loading large workbooks into memory.
                if (in_array($ext, ['csv', 'txt'], true)) {
                    foreach ($this->extractSpatialMetricsFromText($target, $normalizedFileCode) as $metric) {
                        $spatialMetrics[] = $metric;
                    }
                }
            }

            return [
                'files' => $result,
                'spatial_metrics' => $spatialMetrics,
            ];
        } finally {
            $zip->close();
        }
    }

    private function extractSpatialMetricsFromText(string $path, string $fileCode): array {
        $fh = fopen($path, 'rb');
        if (!$fh) return [];

        $delimiter = $this->detectDelimiter($path);
        $header = fgetcsv($fh, 0, $delimiter, '"', '\\');
        if (!is_array($header) || empty($header)) {
            fclose($fh);
            return [];
        }

        $headerMap = [];
        foreach ($header as $index => $column) {
            $key = strtoupper(trim((string)$column));
            $key = preg_replace('/[^A-Z0-9_]/', '', $key) ?: '';
            if ($key !== '') $headerMap[$key] = (int)$index;
        }

        $areaCols = [
            'CHANGWAT' => $this->findHeaderIndex($headerMap, ['CHANGWAT','PROVINCE','PROV_CODE']),
            'AMPUR' => $this->findHeaderIndex($headerMap, ['AMPUR','AMPHOE','DISTRICT','AMP_CODE']),
            'TAMBON' => $this->findHeaderIndex($headerMap, ['TAMBON','SUBDISTRICT','TAM_CODE']),
            'VILLAGE' => $this->findHeaderIndex($headerMap, ['VILLAGE','MOO','VILLAGE_NO']),
            'LATITUDE' => $this->findHeaderIndex($headerMap, ['LATITUDE','LAT']),
            'LONGITUDE' => $this->findHeaderIndex($headerMap, ['LONGITUDE','LON','LNG']),
        ];

        // No geographic dimensions in this file.
        if ($areaCols['CHANGWAT'] === null &&
            $areaCols['AMPUR'] === null &&
            $areaCols['TAMBON'] === null &&
            $areaCols['VILLAGE'] === null) {
            fclose($fh);
            return [];
        }

        $metricCode = $this->metricCodeForFile($fileCode);
        $groups = [];

        try {
            while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
                if (!is_array($row) || $row === [null]) continue;

                $changwat = $this->areaValue($row, $areaCols['CHANGWAT'], 2);
                $ampur = $this->areaValue($row, $areaCols['AMPUR'], 2);
                $tambon = $this->areaValue($row, $areaCols['TAMBON'], 2);
                $village = $this->areaValue($row, $areaCols['VILLAGE'], 2);

                // We only keep administrative codes, never address text or citizen identifiers.
                $levels = [];
                if ($ampur !== null) {
                    $levels[] = ['AMPUR', $changwat, $ampur, null, null];
                }
                if ($tambon !== null) {
                    $levels[] = ['TAMBON', $changwat, $ampur, $tambon, null];
                }
                if ($village !== null) {
                    $levels[] = ['VILLAGE', $changwat, $ampur, $tambon, $village];
                }

                $lat = $this->coordinateValue($row, $areaCols['LATITUDE'], -90, 90);
                $lng = $this->coordinateValue($row, $areaCols['LONGITUDE'], -180, 180);

                foreach ($levels as [$level, $cw, $ap, $tb, $vl]) {
                    $key = implode('|', [
                        $level,
                        $cw ?? '',
                        $ap ?? '',
                        $tb ?? '',
                        $vl ?? '',
                        $fileCode,
                        $metricCode,
                    ]);

                    if (!isset($groups[$key])) {
                        $groups[$key] = [
                            'area_level' => $level,
                            'changwat_code' => $cw,
                            'ampur_code' => $ap,
                            'tambon_code' => $tb,
                            'village_code' => $vl,
                            'source_file_code' => $fileCode,
                            'metric_code' => $metricCode,
                            'metric_value' => 0,
                            'lat_sum' => 0.0,
                            'lng_sum' => 0.0,
                            'geo_point_count' => 0,
                        ];
                    }

                    $groups[$key]['metric_value']++;

                    if ($lat !== null && $lng !== null) {
                        $groups[$key]['lat_sum'] += $lat;
                        $groups[$key]['lng_sum'] += $lng;
                        $groups[$key]['geo_point_count']++;
                    }
                }
            }
        } finally {
            fclose($fh);
        }

        $metrics = [];
        foreach ($groups as $group) {
            $points = (int)$group['geo_point_count'];
            $metrics[] = [
                'area_level' => $group['area_level'],
                'changwat_code' => $group['changwat_code'],
                'ampur_code' => $group['ampur_code'],
                'tambon_code' => $group['tambon_code'],
                'village_code' => $group['village_code'],
                'source_file_code' => $group['source_file_code'],
                'metric_code' => $group['metric_code'],
                'metric_value' => (int)$group['metric_value'],
                'centroid_lat' => $points > 0 ? round($group['lat_sum'] / $points, 7) : null,
                'centroid_lng' => $points > 0 ? round($group['lng_sum'] / $points, 7) : null,
                'geo_point_count' => $points,
            ];
        }

        return $metrics;
    }

    private function detectDelimiter(string $path): string {
        $sample = '';
        $fh = fopen($path, 'rb');
        if ($fh) {
            $sample = (string)fgets($fh);
            fclose($fh);
        }

        $candidates = [
            ',' => substr_count($sample, ','),
            '|' => substr_count($sample, '|'),
            "\t" => substr_count($sample, "\t"),
            ';' => substr_count($sample, ';'),
        ];

        arsort($candidates);
        $delimiter = (string)array_key_first($candidates);
        return (($candidates[$delimiter] ?? 0) > 0) ? $delimiter : ',';
    }

    private function findHeaderIndex(array $headerMap, array $aliases): ?int {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $headerMap)) {
                return (int)$headerMap[$alias];
            }
        }
        return null;
    }

    private function areaValue(array $row, ?int $index, int $maxLength): ?string {
        if ($index === null || !array_key_exists($index, $row)) return null;
        $value = preg_replace('/[^0-9]/', '', trim((string)$row[$index]));
        if ($value === '') return null;

        // Administrative codes are stored as provided up to the expected component length.
        return substr(str_pad($value, $maxLength, '0', STR_PAD_LEFT), -$maxLength);
    }

    private function coordinateValue(array $row, ?int $index, float $min, float $max): ?float {
        if ($index === null || !array_key_exists($index, $row)) return null;
        $raw = trim((string)$row[$index]);
        if ($raw === '' || !is_numeric($raw)) return null;
        $value = (float)$raw;
        return ($value >= $min && $value <= $max) ? $value : null;
    }

    private function metricCodeForFile(string $fileCode): string {
        $code = strtoupper($fileCode);
        if (str_contains($code, 'HOME')) return 'HOUSEHOLD_RECORDS';
        if (str_contains($code, 'ADDRESS')) return 'ADDRESS_RECORDS';
        if (str_contains($code, 'PERSON')) return 'PERSON_RECORDS';
        return 'RECORDS';
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