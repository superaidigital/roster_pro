<?php
require_once __DIR__ . '/Data43StandardV241.php';

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
        if (!is_dir($extractDir) && !mkdir($extractDir, 0750, true) && !is_dir($extractDir)) {
            throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์แตกไฟล์ได้');
        }

        $inspection = $this->extractSafe($zipPath, $extractDir);

        return [
            'archive_sha256' => $archiveHash,
            'files' => $inspection['files'],
            'spatial_metrics' => $inspection['spatial_metrics'],
            'quality_summary' => $inspection['quality_summary'],
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
        if (!in_array($mime, ['application/zip','application/x-zip-compressed','application/octet-stream'], true)) {
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
            if ($zip->numFiles <= 0) throw new RuntimeException('ZIP ไม่มีไฟล์ข้อมูล');
            if ($zip->numFiles > self::MAX_ENTRIES) throw new RuntimeException('ZIP มีจำนวนไฟล์มากเกินกำหนด');

            $totalUncompressed = 0;
            $files = [];
            $textFiles = [];
            $detectedCodes = [];
            $unknownFiles = [];
            $headerIssues = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
                if (!is_array($stat)) continue;

                $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));
                if ($name === '' || str_ends_with($name, '/')) continue;
                $this->assertSafePath($name);

                $uncompressed = (int)($stat['size'] ?? 0);
                $compressed = max(1, (int)($stat['comp_size'] ?? 1));
                if ($uncompressed > self::MAX_ENTRY_BYTES) throw new RuntimeException('พบไฟล์ภายใน ZIP ที่มีขนาดใหญ่เกินกำหนด');
                if (($uncompressed / $compressed) > self::MAX_COMPRESSION_RATIO) throw new RuntimeException('ZIP มีอัตราการบีบอัดผิดปกติ');

                $totalUncompressed += $uncompressed;
                if ($totalUncompressed > self::MAX_TOTAL_UNCOMPRESSED) throw new RuntimeException('ขนาดข้อมูลหลังแตก ZIP มากเกินกำหนด');

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['csv','txt','xlsx'], true)) continue;

                $target = $extractDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.' . $ext;
                $in = $zip->getStream((string)($stat['name'] ?? ''));
                if (!$in) throw new RuntimeException('ไม่สามารถอ่านไฟล์ภายใน ZIP ได้');
                $out = fopen($target, 'wb');
                if (!$out) {
                    fclose($in);
                    throw new RuntimeException('ไม่สามารถสร้างไฟล์ชั่วคราวได้');
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);

                $canonical = Data43StandardV241::canonicalFileCode(basename($name));
                $rawBase = strtoupper(preg_replace('/[^A-Za-z0-9_\-]/', '', pathinfo($name, PATHINFO_FILENAME)) ?: 'UNKNOWN');
                $fileCode = $canonical ?? substr($rawBase, 0, 100);
                $rowCount = in_array($ext, ['csv','txt'], true) ? $this->countTextRows($target) : null;
                $status = $canonical ? 'VALID' : 'SKIPPED';
                $error = $canonical ? null : 'ไม่สามารถจับคู่ชื่อไฟล์กับโครงสร้างมาตรฐาน Version 2.4.1';
                if ($canonical && $ext === 'xlsx') {
                    $status = 'ERROR';
                    $error = 'ไฟล์ XLSX ยังไม่รองรับการตรวจ row/schema แบบ streaming กรุณาส่ง CSV หรือ TXT';
                }

                if ($canonical) {
                    $detectedCodes[$canonical] = true;
                } else {
                    $unknownFiles[] = basename($name);
                }

                if ($canonical && in_array($ext, ['csv','txt'], true)) {
                    $missingHeaders = $this->missingCriticalHeaders($target, $canonical);
                    if ($missingHeaders) {
                        $headerIssues[$canonical] = $missingHeaders;
                        $error = 'ขาดคอลัมน์สำคัญ: ' . implode(', ', $missingHeaders);
                    }
                    $textFiles[$canonical] ??= [];
                    $textFiles[$canonical][] = ['path'=>$target,'code'=>$canonical,'name'=>basename($name)];
                }

                $files[] = [
                    'file_code' => $fileCode,
                    'original_filename' => basename($name),
                    'extension' => $ext,
                    'file_sha256' => hash_file('sha256', $target),
                    'row_count' => $rowCount,
                    'file_size' => filesize($target) ?: 0,
                    'status' => $status,
                    'error_message' => $error,
                ];
            }

            $expectedCodes = Data43StandardV241::rphstExpectedCodes();
            $detectedExpected = array_values(array_intersect($expectedCodes, array_keys($detectedCodes)));
            $missingExpected = array_values(array_diff($expectedCodes, $detectedExpected));

            $linkage = $this->buildSpatialLinkage($textFiles);
            $spatialMetrics = $this->buildSpatialMetrics($textFiles, $linkage);

            return [
                'files' => $files,
                'spatial_metrics' => $spatialMetrics,
                'quality_summary' => [
                    'standard_version' => Data43StandardV241::VERSION,
                    'profile_code' => Data43StandardV241::PROFILE,
                    'catalog_count' => Data43StandardV241::totalStructures(),
                    'expected_files' => Data43StandardV241::rphstExpectedCount(),
                    'detected_expected_files' => count($detectedExpected),
                    'detected_expected_codes' => $detectedExpected,
                    'missing_expected_codes' => $missingExpected,
                    'unknown_files' => $unknownFiles,
                    'header_issues' => $headerIssues,
                    'linked_people' => count($linkage['people']),
                    'linked_homes' => count($linkage['homes']),
                    'unresolved_people' => (int)($linkage['unresolved_people'] ?? 0),
                    'address_only_people' => (int)($linkage['address_only_people'] ?? 0),
                ],
            ];
        } finally {
            $zip->close();
        }
    }

    private function buildSpatialLinkage(array $textFiles): array {
        $homes = [];
        $addresses = [];
        $villageCentroids = [];
        $people = [];
        $unresolvedPeople = 0;
        $addressOnlyPeople = 0;

        foreach ($this->fileParts($textFiles, 'HOME') as $part) {
            $this->walkTextRows($part['path'], function(array $row, array $h) use (&$homes): void {
                $hid = $this->stringValue($row, $this->findHeaderIndex($h, ['HID']));
                if ($hid === null) return;
                $area = $this->areaFromRow($row, $h);
                if (!$this->hasArea($area)) return;

                // Household coordinates are sensitive. They are used only transiently and
                // will be replaced by VILLAGE centroid when village master data exists.
                $area['lat'] = $this->coordinateValue($row, $this->findHeaderIndex($h, ['LATITUDE','LAT']), -90, 90);
                $area['lng'] = $this->coordinateValue($row, $this->findHeaderIndex($h, ['LONGITUDE','LON','LNG']), -180, 180);
                $homes[$hid] = $area;
            });
        }

        foreach ($this->fileParts($textFiles, 'VILLAGE') as $part) {
            $this->walkTextRows($part['path'], function(array $row, array $h) use (&$villageCentroids): void {
                $rawVid = strtoupper(trim((string)($this->stringValue($row, $this->findHeaderIndex($h, ['VID'])) ?? '')));
                if (!preg_match('/^[0-9]{6}[0-9A-Z]{2}$/', $rawVid)) return;

                $area = [
                    'changwat'=>substr($rawVid,0,2),
                    'ampur'=>substr($rawVid,2,2),
                    'tambon'=>substr($rawVid,4,2),
                    'village'=>substr($rawVid,6,2),
                    'lat'=>$this->coordinateValue($row, $this->findHeaderIndex($h, ['LATITUDE','LAT']), -90, 90),
                    'lng'=>$this->coordinateValue($row, $this->findHeaderIndex($h, ['LONGITUDE','LON','LNG']), -180, 180),
                ];
                $villageCentroids[$rawVid] = $area;
            });
        }

        // ADDRESS is retained only for quality diagnostics. It is not used as the
        // residential prevalence fallback because the standard states in-area
        // residential location is represented by HOME.
        foreach ($this->fileParts($textFiles, 'ADDRESS') as $part) {
            $this->walkTextRows($part['path'], function(array $row, array $h) use (&$addresses): void {
                $pid = $this->stringValue($row, $this->findHeaderIndex($h, ['PID']));
                if ($pid === null) return;
                $area = $this->areaFromRow($row, $h);
                if (!$this->hasArea($area)) return;
                $addresses[$pid] = $area;
            });
        }

        foreach ($this->fileParts($textFiles, 'PERSON') as $part) {
            $this->walkTextRows($part['path'], function(array $row, array $h) use (
                &$people, $homes, $addresses, $villageCentroids, &$unresolvedPeople, &$addressOnlyPeople
            ): void {
                $pid = $this->stringValue($row, $this->findHeaderIndex($h, ['PID']));
                if ($pid === null) return;

                $hid = $this->stringValue($row, $this->findHeaderIndex($h, ['HID']));
                $area = ($hid !== null && isset($homes[$hid])) ? $homes[$hid] : null;

                if (!is_array($area) || !$this->hasArea($area)) {
                    $unresolvedPeople++;
                    if (isset($addresses[$pid])) $addressOnlyPeople++;
                    return;
                }

                // Prefer public-area village centroid over household coordinates.
                $vid = ($area['changwat'] ?? '') . ($area['ampur'] ?? '') . ($area['tambon'] ?? '') . ($area['village'] ?? '');
                if (isset($villageCentroids[$vid])) {
                    $area['lat'] = $villageCentroids[$vid]['lat'];
                    $area['lng'] = $villageCentroids[$vid]['lng'];
                } else {
                    // Do not allow household coordinates to flow into analytics.
                    $area['lat'] = null;
                    $area['lng'] = null;
                }

                $people[$pid] = [
                    'area'=>$area,
                    'birth'=>$this->stringValue($row, $this->findHeaderIndex($h, ['BIRTH'])),
                    'typearea'=>$this->stringValue($row, $this->findHeaderIndex($h, ['TYPEAREA'])),
                    'discharge'=>$this->stringValue($row, $this->findHeaderIndex($h, ['DISCHARGE'])),
                ];
            });
        }

        return [
            'homes'=>$homes,
            'addresses'=>$addresses,
            'villages'=>$villageCentroids,
            'people'=>$people,
            'unresolved_people'=>$unresolvedPeople,
            'address_only_people'=>$addressOnlyPeople,
        ];
    }

    private function buildSpatialMetrics(array $textFiles, array $linkage): array {
        $groups = [];

        foreach ($linkage['homes'] as $area) {
            $this->accumulateMetric($groups, $area, 'HOME', 'GEO_REFERENCE', null);
        }
        foreach ($linkage['villages'] as $area) {
            $this->accumulateMetric($groups, $area, 'VILLAGE', 'GEO_REFERENCE', null);
        }

        foreach ($linkage['people'] as $pid => $person) {
            $typearea = (string)($person['typearea'] ?? '');
            $discharge = (string)($person['discharge'] ?? '');
            if (!in_array($typearea, ['1','3','5'], true)) continue;
            if (in_array($discharge, ['1','2','3'], true)) continue;

            $area = $person['area'];
            $identity = hash('sha256', 'PERSON|' . $pid);
            $this->accumulateMetric($groups, $area, 'PERSON', 'POPULATION', $identity);
            $age = $this->ageFromBirth((string)($person['birth'] ?? ''));
            if ($age !== null && $age >= 60) {
                $this->accumulateMetric($groups, $area, 'PERSON', 'ELDERLY', $identity);
            }
            if ($age !== null && $age >= 35) {
                $this->accumulateMetric($groups, $area, 'PERSON', 'NCD_SCREEN_TARGET', $identity);
            }
        }

        foreach ($this->fileParts($textFiles, 'CHRONIC') as $part) {
            $this->walkTextRows($part['path'], function(array $row, array $h) use (&$groups, $linkage): void {
                $pid = $this->stringValue($row, $this->findHeaderIndex($h, ['PID']));
                if ($pid === null || !isset($linkage['people'][$pid])) return;
                $area = $linkage['people'][$pid]['area'];
                $diag = strtoupper(preg_replace('/[^A-Z0-9.]/', '', (string)($this->stringValue($row, $this->findHeaderIndex($h, ['CHRONIC'])) ?? '')) ?: '');
                if ($diag === '') return;
                $identity = hash('sha256', 'CHRONIC|' . $pid);
                $this->accumulateMetric($groups, $area, 'CHRONIC', 'NCD', $identity);
                if (preg_match('/^E1[0-4]/', $diag)) $this->accumulateMetric($groups, $area, 'CHRONIC', 'DM', $identity);
                if (preg_match('/^I1[0-5]/', $diag)) $this->accumulateMetric($groups, $area, 'CHRONIC', 'HT', $identity);
            });
        }

        foreach ($this->fileParts($textFiles, 'DISABILITY') as $part) {
            $this->walkTextRows($part['path'], function(array $row, array $h) use (&$groups, $linkage): void {
                $pid = $this->stringValue($row, $this->findHeaderIndex($h, ['PID']));
                if ($pid === null || !isset($linkage['people'][$pid])) return;
                $identity = hash('sha256', 'DISABILITY|' . $pid);
                $this->accumulateMetric($groups, $linkage['people'][$pid]['area'], 'DISABILITY', 'DISABLED', $identity);
            });
        }

        foreach ($this->fileParts($textFiles, 'ANC') as $part) {
            $this->walkTextRows($part['path'], function(array $row, array $h) use (&$groups, $linkage): void {
                $pid = $this->stringValue($row, $this->findHeaderIndex($h, ['PID']));
                if ($pid === null || !isset($linkage['people'][$pid])) return;
                $seq = $this->stringValue($row, $this->findHeaderIndex($h, ['SEQ']));
                $date = $this->stringValue($row, $this->findHeaderIndex($h, ['DATE_SERV']));
                $identity = hash('sha256', 'ANC|' . $pid . '|' . ($seq ?? '') . '|' . ($date ?? ''));
                $this->accumulateMetric($groups, $linkage['people'][$pid]['area'], 'ANC', 'ANC', $identity);
            });
        }

        foreach ($this->fileParts($textFiles, 'SERVICE') as $part) {
            $this->walkTextRows($part['path'], function(array $row, array $h) use (&$groups, $linkage): void {
                $pid = $this->stringValue($row, $this->findHeaderIndex($h, ['PID']));
                if ($pid === null || !isset($linkage['people'][$pid])) return;
                $seq = $this->stringValue($row, $this->findHeaderIndex($h, ['SEQ']));
                $hosp = $this->stringValue($row, $this->findHeaderIndex($h, ['HOSPCODE']));
                $identity = hash('sha256', 'SERVICE|' . ($hosp ?? '') . '|' . ($seq ?? '') . '|' . $pid);
                $this->accumulateMetric($groups, $linkage['people'][$pid]['area'], 'SERVICE', 'SERVICE', $identity);
            });
        }

        foreach ($this->fileParts($textFiles, 'NCDSCREEN') as $part) {
            $this->walkTextRows($part['path'], function(array $row, array $h) use (&$groups, $linkage): void {
                $pid = $this->stringValue($row, $this->findHeaderIndex($h, ['PID']));
                if ($pid === null || !isset($linkage['people'][$pid])) return;
                $seq = $this->stringValue($row, $this->findHeaderIndex($h, ['SEQ']));
                $date = $this->stringValue($row, $this->findHeaderIndex($h, ['DATE_SERV']));
                $identity = hash('sha256', 'NCDSCREEN|' . $pid . '|' . ($seq ?? '') . '|' . ($date ?? ''));
                $this->accumulateMetric($groups, $linkage['people'][$pid]['area'], 'NCDSCREEN', 'NCD_SCREEN', $identity);
            });
        }

        $metrics = [];
        foreach ($groups as $group) {
            $points = (int)$group['geo_point_count'];
            $metrics[] = [
                'area_level'=>$group['area_level'],
                'changwat_code'=>$group['changwat_code'],
                'ampur_code'=>$group['ampur_code'],
                'tambon_code'=>$group['tambon_code'],
                'village_code'=>$group['village_code'],
                'source_file_code'=>$group['source_file_code'],
                'metric_code'=>$group['metric_code'],
                'metric_value'=>(int)$group['metric_value'],
                'centroid_lat'=>$points > 0 ? round($group['lat_sum'] / $points, 7) : null,
                'centroid_lng'=>$points > 0 ? round($group['lng_sum'] / $points, 7) : null,
                'geo_point_count'=>$points,
            ];
        }
        return $metrics;
    }

    private function accumulateMetric(array &$groups, array $area, string $source, string $metric, ?string $identity): void {
        foreach ($this->areaLevels($area) as [$level,$cw,$ap,$tb,$vl]) {
            $key = implode('|', [$level,$cw ?? '',$ap ?? '',$tb ?? '',$vl ?? '',$source,$metric]);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'area_level'=>$level,'changwat_code'=>$cw,'ampur_code'=>$ap,'tambon_code'=>$tb,'village_code'=>$vl,
                    'source_file_code'=>$source,'metric_code'=>$metric,'metric_value'=>0,
                    'lat_sum'=>0.0,'lng_sum'=>0.0,'geo_point_count'=>0,'_seen'=>[],
                ];
            }
            if ($identity !== null && isset($groups[$key]['_seen'][$identity])) continue;
            if ($identity !== null) $groups[$key]['_seen'][$identity] = true;
            $groups[$key]['metric_value']++;
            $lat = $area['lat'] ?? null;
            $lng = $area['lng'] ?? null;
            if ((is_float($lat) || is_int($lat)) && (is_float($lng) || is_int($lng))) {
                $groups[$key]['lat_sum'] += (float)$lat;
                $groups[$key]['lng_sum'] += (float)$lng;
                $groups[$key]['geo_point_count']++;
            }
        }
    }

    private function areaLevels(array $area): array {
        $cw = $area['changwat'] ?? null;
        $ap = $area['ampur'] ?? null;
        $tb = $area['tambon'] ?? null;
        $vl = $area['village'] ?? null;
        $levels = [];
        if ($cw !== null) $levels[] = ['CHANGWAT',$cw,null,null,null];
        if ($cw !== null && $ap !== null) $levels[] = ['AMPUR',$cw,$ap,null,null];
        if ($cw !== null && $ap !== null && $tb !== null) $levels[] = ['TAMBON',$cw,$ap,$tb,null];
        if ($cw !== null && $ap !== null && $tb !== null && $vl !== null && $vl !== '99') $levels[] = ['VILLAGE',$cw,$ap,$tb,$vl];
        return $levels;
    }

    private function areaFromRow(array $row, array $h): array {
        return [
            'changwat'=>$this->areaValue($row, $this->findHeaderIndex($h, ['CHANGWAT']), 2),
            'ampur'=>$this->areaValue($row, $this->findHeaderIndex($h, ['AMPUR','AMPHOE']), 2),
            'tambon'=>$this->areaValue($row, $this->findHeaderIndex($h, ['TAMBON']), 2),
            'village'=>$this->villageValue($row, $this->findHeaderIndex($h, ['VILLAGE','MOO'])),
            'lat'=>null,
            'lng'=>null,
        ];
    }

    private function hasArea(array $area): bool {
        return !empty($area['changwat']) && !empty($area['ampur']) && !empty($area['tambon']);
    }

    private function fileParts(array $textFiles, string $code): array {
        $parts = $textFiles[$code] ?? [];
        if (isset($parts['path'])) return [$parts]; // backward compatibility
        return is_array($parts) ? array_values($parts) : [];
    }

    private function missingCriticalHeaders(string $path, string $fileCode): array {
        $headerMap = $this->readHeaderMap($path);
        if (!$headerMap) return Data43StandardV241::criticalHeaders($fileCode);
        $missing = [];
        foreach (Data43StandardV241::criticalHeaders($fileCode) as $column) {
            if (!array_key_exists($column, $headerMap)) $missing[] = $column;
        }
        return $missing;
    }

    private function walkTextRows(string $path, callable $callback): void {
        $delimiter = $this->detectDelimiter($path);
        $fh = fopen($path, 'rb');
        if (!$fh) return;
        try {
            $header = fgetcsv($fh, 0, $delimiter, '"', '\\');
            if (!is_array($header) || !$header) return;
            $headerMap = $this->headerMap($header);
            while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
                if (!is_array($row) || $row === [null]) continue;
                $callback($row, $headerMap);
            }
        } finally {
            fclose($fh);
        }
    }

    private function readHeaderMap(string $path): array {
        $delimiter = $this->detectDelimiter($path);
        $fh = fopen($path, 'rb');
        if (!$fh) return [];
        try {
            $header = fgetcsv($fh, 0, $delimiter, '"', '\\');
            return is_array($header) ? $this->headerMap($header) : [];
        } finally {
            fclose($fh);
        }
    }

    private function headerMap(array $header): array {
        $map = [];
        foreach ($header as $index=>$column) {
            $key = strtoupper(trim((string)$column));
            $key = preg_replace('/[^A-Z0-9_]/', '', $key) ?: '';
            if ($key !== '') $map[$key] = (int)$index;
        }
        return $map;
    }

    private function detectDelimiter(string $path): string {
        $sample = '';
        $fh = fopen($path, 'rb');
        if ($fh) {
            $sample = (string)fgets($fh);
            fclose($fh);
        }
        $candidates = [','=>substr_count($sample, ','),'|'=>substr_count($sample, '|'),"\t"=>substr_count($sample, "\t"),';'=>substr_count($sample, ';')];
        arsort($candidates);
        $delimiter = (string)array_key_first($candidates);
        return (($candidates[$delimiter] ?? 0) > 0) ? $delimiter : ',';
    }

    private function findHeaderIndex(array $headerMap, array $aliases): ?int {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $headerMap)) return (int)$headerMap[$alias];
        }
        return null;
    }

    private function stringValue(array $row, ?int $index): ?string {
        if ($index === null || !array_key_exists($index, $row)) return null;
        $value = trim((string)$row[$index]);
        return $value === '' ? null : $value;
    }

    private function areaValue(array $row, ?int $index, int $maxLength): ?string {
        $raw = $this->stringValue($row, $index);
        if ($raw === null) return null;
        $value = preg_replace('/[^0-9]/', '', $raw);
        if ($value === '') return null;
        $value = substr(str_pad($value, $maxLength, '0', STR_PAD_LEFT), -$maxLength);
        return $value === str_repeat('9', $maxLength) ? null : $value;
    }

    private function villageValue(array $row, ?int $index): ?string {
        $raw = strtoupper((string)($this->stringValue($row, $index) ?? ''));
        if ($raw === '' || $raw === '99') return null;
        if (preg_match('/^[A-Z][0-9]$/', $raw)) return $raw;
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if ($digits === '') return null;
        return substr(str_pad($digits, 2, '0', STR_PAD_LEFT), -2);
    }

    private function coordinateValue(array $row, ?int $index, float $min, float $max): ?float {
        $raw = $this->stringValue($row, $index);
        if ($raw === null || !is_numeric($raw)) return null;
        $value = (float)$raw;
        return ($value >= $min && $value <= $max) ? $value : null;
    }

    private function ageFromBirth(string $birth): ?int {
        $digits = preg_replace('/[^0-9]/', '', $birth);
        if (strlen($digits) !== 8) return null;
        $year=(int)substr($digits,0,4); $month=(int)substr($digits,4,2); $day=(int)substr($digits,6,2);
        if ($year > 2400) $year -= 543;
        if (!checkdate($month,$day,$year)) return null;
        try {
            $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$day));
            $today = new DateTimeImmutable('today');
            if ($date > $today) return null;
            return (int)$date->diff($today)->y;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function countTextRows(string $path): int {
        $delimiter = $this->detectDelimiter($path);
        $fh = fopen($path, 'rb');
        if (!$fh) return 0;
        $count = 0;
        try {
            while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
                if ($row === [null] || $row === false) continue;
                $count++;
            }
        } finally { fclose($fh); }
        return max(0, $count - 1);
    }

    private function assertSafePath(string $name): void {
        if (str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name)) {
            throw new RuntimeException('พบ path ที่ไม่ปลอดภัยใน ZIP');
        }
        foreach (explode('/', $name) as $part) {
            if ($part === '..') throw new RuntimeException('ตรวจพบ Zip Slip');
        }
    }

    public static function recursiveDelete(string $path): void {
        if (!file_exists($path)) return;
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
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