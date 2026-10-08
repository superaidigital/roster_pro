<?php
/**
 * Aggregate-only spatial map contract. No raw 43-file rows, precise patient coordinates,
 * or hidden small-cell values are exposed by this service.
 */
final class Data43SpatialMapService
{
    public const MIN_CELL = 5;
    private PDO $db;

    public function __construct(PDO $db) { $this->db = $db; }

    public function boundaryReady(): bool
    {
        try {
            return (bool)$this->db->query("SHOW TABLES LIKE 'data43_geo_boundaries'")->fetchColumn();
        } catch (Throwable $e) { return false; }
    }

    public function validateMonth(string $month): string
    {
        if (!preg_match('/^(20[0-9]{2})-(0[1-9]|1[0-2])$/D', $month)) {
            throw new InvalidArgumentException('รอบเดือนต้องอยู่ในรูป YYYY-MM (ค.ศ.)');
        }
        return $month;
    }

    public function options(string $month, ?int $hospitalId): array
    {
        $sql = "
            SELECT DISTINCT m.metric_code, m.source_file_code
            FROM data43_area_metrics m
            JOIN data43_submissions s ON s.id = m.submission_id
            JOIN hospitals h ON h.id = m.hospital_id AND h.is_active = 1 AND h.deleted_at IS NULL
            JOIN (
                SELECT hospital_id, MAX(id) latest_id FROM data43_submissions
                WHERE report_month = :month AND status IN ('COMPLETE','INCOMPLETE')
                GROUP BY hospital_id
            ) latest ON latest.latest_id = s.id
            WHERE m.report_month = :month2";
        $params = [':month' => $month, ':month2' => $month];
        if ($hospitalId !== null) {
            $sql .= " AND m.hospital_id = :hospital";
            $params[':hospital'] = $hospitalId;
        }
        $sql .= " ORDER BY CASE m.metric_code WHEN 'PERSON_RECORDS' THEN 0 ELSE 1 END, m.source_file_code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assertIndicator(array $options, string $metric, string $source): void
    {
        foreach ($options as $option) {
            if ($metric === (string)$option['metric_code'] && $source === (string)$option['source_file_code']) return;
        }
        throw new InvalidArgumentException('ไม่พบตัวชี้วัดและแฟ้มข้อมูลสำหรับรอบเดือนนี้');
    }

    public static function areaCode(string $level, array $row): string
    {
        $cw = (string)($row['changwat_code'] ?? '');
        $ap = (string)($row['ampur_code'] ?? '');
        $tb = (string)($row['tambon_code'] ?? '');
        if (!preg_match('/^[0-9]{2}$/D', $cw)) return '';
        if ($level === 'PROVINCE') return $cw;
        if (!preg_match('/^[0-9]{2}$/D', $ap)) return '';
        if ($level === 'AMPUR') return $cw . $ap;
        return preg_match('/^[0-9]{2}$/D', $tb) ? $cw . $ap . $tb : '';
    }

    public function aggregate(string $month, string $level, string $metric, string $source, ?int $hospitalId, ?string $parent): array
    {
        $levels = ['PROVINCE' => 'AMPUR', 'AMPUR' => 'AMPUR', 'TAMBON' => 'TAMBON'];
        if (!isset($levels[$level])) throw new InvalidArgumentException('ระดับพื้นที่ไม่ถูกต้อง');
        if ($level === 'AMPUR' && !preg_match('/^[0-9]{2}$/D', (string)$parent)) throw new InvalidArgumentException('ต้องระบุจังหวัด');
        if ($level === 'TAMBON' && !preg_match('/^[0-9]{4}$/D', (string)$parent)) throw new InvalidArgumentException('ต้องระบุอำเภอ');
        $select = $level === 'PROVINCE'
            ? "m.changwat_code"
            : ($level === 'AMPUR' ? "m.changwat_code, m.ampur_code" : "m.changwat_code, m.ampur_code, m.tambon_code");
        $sql = "
          SELECT {$select}, SUM(m.metric_value) AS n,
                 COUNT(DISTINCT m.hospital_id) AS facility_count
          FROM data43_area_metrics m
          JOIN data43_submissions s ON s.id = m.submission_id
          JOIN hospitals h ON h.id = m.hospital_id AND h.is_active=1 AND h.deleted_at IS NULL
          JOIN (
            SELECT hospital_id,MAX(id) latest_id FROM data43_submissions
            WHERE report_month=:month AND status IN ('COMPLETE','INCOMPLETE')
            GROUP BY hospital_id
          ) latest ON latest.latest_id=s.id
          WHERE m.report_month=:month2 AND m.area_level=:source_level
            AND m.metric_code=:metric AND m.source_file_code=:source";
        $params = [
            ':month' => $month, ':month2' => $month, ':source_level' => $levels[$level],
            ':metric' => $metric, ':source' => $source
        ];
        if ($hospitalId !== null) {
            $sql .= " AND m.hospital_id=:hospital";
            $params[':hospital'] = $hospitalId;
        }
        if ($level === 'AMPUR') {
            $sql .= " AND m.changwat_code=:province";
            $params[':province'] = $parent;
        } elseif ($level === 'TAMBON') {
            $sql .= " AND m.changwat_code=:province AND m.ampur_code=:ampur";
            $params[':province'] = substr((string)$parent, 0, 2);
            $params[':ampur'] = substr((string)$parent, 2, 2);
        }
        $sql .= " GROUP BY {$select}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $code = self::areaCode($level, $row);
            if ($code === '') continue; // Unjoinable records are excluded, never misplaced on map.
            $n = (int)$row['n'];
            $result[$code] = [
                'code' => $code,
                'count' => $n >= self::MIN_CELL ? $n : null,
                'suppressed' => $n > 0 && $n < self::MIN_CELL,
                'has_data' => $n > 0,
                'facility_count' => (int)$row['facility_count'],
                'band' => self::band($n)
            ];
        }
        return $result;
    }

    public static function band(int $n): string
    {
        if ($n === 0) return 'none';
        if ($n < self::MIN_CELL) return 'suppressed';
        if ($n < 10) return '5-9';
        if ($n < 25) return '10-24';
        if ($n < 50) return '25-49';
        if ($n < 100) return '50-99';
        return '100+';
    }

    public function boundaries(string $level, ?string $parent): array
    {
        if (!$this->boundaryReady()) return [];
        if (!in_array($level, ['PROVINCE','AMPUR','TAMBON'], true)) throw new InvalidArgumentException('ระดับพื้นที่ไม่ถูกต้อง');
        if ($level === 'AMPUR' && !preg_match('/^[0-9]{2}$/D', (string)$parent)) throw new InvalidArgumentException('รหัสจังหวัดไม่ถูกต้อง');
        if ($level === 'TAMBON' && !preg_match('/^[0-9]{4}$/D', (string)$parent)) throw new InvalidArgumentException('รหัสอำเภอไม่ถูกต้อง');
        $sql = "SELECT area_code,name_th,geometry_json FROM data43_geo_boundaries WHERE area_level=:level";
        $params = [':level' => $level];
        if ($level !== 'PROVINCE') {
            $sql .= " AND parent_code=:parent";
            $params[':parent'] = $parent;
        }
        $sql .= " ORDER BY area_code ASC LIMIT 1000";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $features = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $geometry = json_decode((string)$row['geometry_json'], true);
            if (!is_array($geometry)) continue;
            $features[] = [
                'type' => 'Feature',
                'properties' => ['code' => $row['area_code'], 'name' => $row['name_th']],
                'geometry' => $geometry
            ];
        }
        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    public function searchAreas(string $query): array
    {
        if (!$this->boundaryReady()) return [];
        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < 2) return [];
        $stmt = $this->db->prepare("
          SELECT area_level,area_code,name_th FROM data43_geo_boundaries
          WHERE name_th LIKE :q OR area_code LIKE :code
          ORDER BY FIELD(area_level,'PROVINCE','AMPUR','TAMBON'),name_th LIMIT 25
        ");
        $stmt->execute([':q' => '%' . $query . '%', ':code' => '%' . $query . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function facilities(string $level, string $code): array
    {
        if (!$this->boundaryReady()) return [];
        $len = ['PROVINCE'=>2,'AMPUR'=>4,'TAMBON'=>6][$level] ?? 0;
        if ($len === 0 || !preg_match('/^[0-9]{' . $len . '}$/D', $code)) return [];
        // Only display public facility master fields; never personal health records.
        $stmt = $this->db->prepare("
          SELECT DISTINCT h.id,h.hospital_code,h.name
          FROM hospitals h
          JOIN data43_hospital_service_areas a ON a.hospital_id=h.id
          WHERE h.is_active=1 AND h.deleted_at IS NULL
            AND LEFT(a.tambon_code,:len)=:area
          ORDER BY h.name LIMIT 200
        ");
        $stmt->bindValue(':len', $len, PDO::PARAM_INT);
        $stmt->bindValue(':area', $code);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignArea(int $hospitalId, string $code, bool $assign): void
    {
        if (!preg_match('/^[0-9]{6}$/D', $code)) throw new InvalidArgumentException('รหัสตำบลต้องมี 6 หลัก');
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM hospitals WHERE id=? AND deleted_at IS NULL AND is_active=1");
        $stmt->execute([$hospitalId]);
        if (!(int)$stmt->fetchColumn()) throw new InvalidArgumentException('ไม่พบหน่วยบริการ');
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM data43_geo_boundaries WHERE area_level='TAMBON' AND area_code=?");
        $stmt->execute([$code]);
        if (!(int)$stmt->fetchColumn()) throw new InvalidArgumentException('ไม่พบตำบลในข้อมูลขอบเขต');
        if ($assign) {
            $stmt = $this->db->prepare("INSERT IGNORE INTO data43_hospital_service_areas (hospital_id,tambon_code) VALUES (?,?)");
        } else {
            $stmt = $this->db->prepare("DELETE FROM data43_hospital_service_areas WHERE hospital_id=? AND tambon_code=?");
        }
        $stmt->execute([$hospitalId, $code]);
    }

    /** Import trusted administrative GeoJSON. Must be called by admin + CSRF-protected POST. */
    public function importGeoJson(array $upload, string $level): int
    {
        if (!in_array($level, ['PROVINCE','AMPUR','TAMBON'], true)) throw new InvalidArgumentException('ระดับพื้นที่ไม่ถูกต้อง');
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('อัปโหลดไม่สำเร็จ');
        if (($upload['size'] ?? 0) <= 0 || $upload['size'] > 15728640) throw new InvalidArgumentException('ไฟล์ต้องไม่เกิน 15 MB');
        if (!preg_match('/\.(geojson|json)$/i', (string)($upload['name'] ?? ''))) throw new InvalidArgumentException('ต้องเป็นไฟล์ GeoJSON');
        if (!is_uploaded_file((string)($upload['tmp_name'] ?? ''))) throw new InvalidArgumentException('ไฟล์อัปโหลดไม่ถูกต้อง');
        $doc = json_decode((string)file_get_contents($upload['tmp_name']), true, 64, JSON_THROW_ON_ERROR);
        if (($doc['type'] ?? null) !== 'FeatureCollection' || !is_array($doc['features'] ?? null)) {
            throw new InvalidArgumentException('ต้องเป็น FeatureCollection');
        }
        if (count($doc['features']) < 1 || count($doc['features']) > 10000) throw new InvalidArgumentException('จำนวนพื้นที่ต้องอยู่ระหว่าง 1 ถึง 10,000');
        $length = ['PROVINCE'=>2,'AMPUR'=>4,'TAMBON'=>6][$level];
        $rows = [];
        $vertices = 0;
        foreach ($doc['features'] as $feature) {
            if (!is_array($feature)) throw new InvalidArgumentException('Feature ไม่ถูกต้อง');
            $p = $feature['properties'] ?? [];
            if (!is_array($p)) throw new InvalidArgumentException('Properties ไม่ถูกต้อง');
            $code = preg_replace('/\s+/', '', (string)($p['area_code'] ?? $p['code'] ?? ''));
            if (!preg_match('/^[0-9]{' . $length . '}$/D', $code)) {
                // Also accepts CHANGWAT/AMPUR/TAMBON as two-digit component codes.
                $cw = (string)($p['CHANGWAT'] ?? '');
                $ap = (string)($p['AMPUR'] ?? '');
                $tb = (string)($p['TAMBON'] ?? '');
                $code = $cw . ($level !== 'PROVINCE' ? $ap : '') . ($level === 'TAMBON' ? $tb : '');
            }
            if (!preg_match('/^[0-9]{' . $length . '}$/D', $code)) throw new InvalidArgumentException('Feature ไม่มี area_code รหัสราชการ ' . $length . ' หลัก');
            $name = trim((string)($p['name_th'] ?? $p['NAME_T'] ?? $p['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 180) throw new InvalidArgumentException('Feature ไม่มี name_th');
            $geometry = $feature['geometry'] ?? null;
            if (!is_array($geometry) || !in_array($geometry['type'] ?? '', ['Polygon','MultiPolygon'], true)) {
                throw new InvalidArgumentException('รับเฉพาะ Polygon หรือ MultiPolygon');
            }
            $this->validateCoordinates($geometry['coordinates'] ?? null, $vertices);
            $parent = $level === 'PROVINCE' ? null : substr($code, 0, $length - 2);
            $rows[$code] = [$level,$code,$parent,$name,json_encode($geometry, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
        }
        $stmt = $this->db->prepare("
          INSERT INTO data43_geo_boundaries (area_level,area_code,parent_code,name_th,geometry_json)
          VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE parent_code=VALUES(parent_code),
          name_th=VALUES(name_th),geometry_json=VALUES(geometry_json)
        ");
        $this->db->beginTransaction();
        try {
            foreach ($rows as $values) $stmt->execute($values);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return count($rows);
    }

    private function validateCoordinates(mixed $value, int &$vertices, int $depth=0): void
    {
        if (!is_array($value) || $depth > 8 || !$value) throw new InvalidArgumentException('พิกัด GeoJSON ไม่ถูกต้อง');
        if (count($value) >= 2 && is_numeric($value[0] ?? null) && is_numeric($value[1] ?? null)) {
            $lon = (float)$value[0]; $lat = (float)$value[1];
            if (!is_finite($lon) || !is_finite($lat) || $lon < 95 || $lon > 107 || $lat < 5 || $lat > 22) {
                throw new InvalidArgumentException('พิกัดขอบเขตอยู่นอกประเทศไทย');
            }
            if (++$vertices > 450000) throw new InvalidArgumentException('ไฟล์มีจุดพิกัดมากเกินไป');
            return;
        }
        foreach ($value as $child) $this->validateCoordinates($child, $vertices, $depth + 1);
    }
}
