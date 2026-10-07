<?php
// ที่อยู่ไฟล์: models/HolidayModel.php

class HolidayModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ดึงวันหยุดทั้งหมด (กรองตามปีได้)
    public function getAllHolidays($year = null) {
        $query = "SELECT * FROM holidays ";
        if ($year) {
            $query .= "WHERE YEAR(holiday_date) = :year ";
        }
        $query .= "ORDER BY holiday_date ASC";
        
        $stmt = $this->conn->prepare($query);
        if ($year) {
            $stmt->bindParam(':year', $year);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================
    // 🛠️ ส่วนที่เพิ่มเข้ามาเพื่อแก้ไข Fatal Error: isHoliday()
    // =========================================================
    
    // ตรวจสอบว่าวันที่ระบุเป็นวันหยุดหรือไม่ (ส่งกลับค่า true / false)
    public function isHoliday($date) {
        try {
            // เช็คว่าเป็นวันหยุดและเปิดใช้งานอยู่ (is_active = 1)
            $stmt = $this->conn->prepare("SELECT * FROM holidays WHERE holiday_date = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$date]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? true : false;
        } catch (PDOException $e) {
            // Fallback เผื่อกรณีที่ฐานข้อมูลยังไม่ได้อัปเดตคอลัมน์ is_active
            $stmt = $this->conn->prepare("SELECT * FROM holidays WHERE holiday_date = ? LIMIT 1");
            $stmt->execute([$date]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? true : false;
        }
    }

    // ดึงวันหยุดตามเดือน-ปี (เอาไว้ใช้ตรวจสอบรวดเดียวตอนจัดตารางเวร)
    public function getHolidaysByMonth($year, $month) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM holidays WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ? AND is_active = 1");
            $stmt->execute([$year, $month]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // =========================================================

    // เพิ่มวันหยุดแบบ Manual
    public function addHoliday($date, $name, $type = 'REGULAR') {
        try {
            $stmt = $this->conn->prepare("INSERT INTO holidays (holiday_date, holiday_name, holiday_type, is_active) VALUES (?, ?, ?, 1)");
            return $stmt->execute([$date, $name, $type]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // ลบวันหยุด
    public function deleteHoliday($id) {
        $stmt = $this->conn->prepare("DELETE FROM holidays WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // เปิด-ปิด การใช้วันหยุด
    public function toggleStatus($id, $status) {
        $stmt = $this->conn->prepare("UPDATE holidays SET is_active = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    // ซิงค์ข้อมูลวันหยุดจาก API อัตโนมัติ
    // Nager.Date เป็น provider แรก; หาก Thailand ไม่มีข้อมูล (เช่น HTTP 204)
    // จะ fallback ไปยังชุดวันหยุดราชการไทยที่ตรวจสอบไว้ในระบบสำหรับปีที่รองรับ
    public function syncHolidaysFromAPI($year) {
        $year = (int)$year;

        if ($year < 2020 || $year > 2100) {
            return [
                'success' => false,
                'message' => 'ปีที่ต้องการซิงค์ไม่ถูกต้อง'
            ];
        }

        $provider = 'Nager.Date';
        $api_holidays = $this->fetchNagerDateHolidays($year);

        if (!$api_holidays['success']) {
            // Thailand บางปี Nager.Date ตอบ 204 No Content
            // ใช้ข้อมูลสำรองที่ตรวจสอบไว้แทน หากมีชุดข้อมูลปีนั้น
            $fallback = $this->getThailandGovernmentHolidayFallback($year);

            if (empty($fallback)) {
                return [
                    'success' => false,
                    'message' => $api_holidays['message']
                        . ' และยังไม่มีชุดข้อมูลสำรองวันหยุดราชการไทยสำหรับปี ' . ($year + 543)
                ];
            }

            $provider = 'ชุดข้อมูลวันหยุดราชการไทยสำรอง';
            $api_holidays = [
                'success' => true,
                'holidays' => $fallback,
                'fallback' => true,
                'provider_message' => $api_holidays['message']
            ];
        }

        $holidays = $api_holidays['holidays'] ?? [];
        if (!is_array($holidays) || empty($holidays)) {
            return [
                'success' => false,
                'message' => 'ไม่พบข้อมูลวันหยุดสำหรับปี ' . ($year + 543)
            ];
        }

        $existing_stmt = $this->conn->prepare(
            "SELECT holiday_date FROM holidays WHERE YEAR(holiday_date) = ?"
        );
        $existing_stmt->execute([$year]);
        $existing_dates = array_fill_keys(
            $existing_stmt->fetchAll(PDO::FETCH_COLUMN),
            true
        );

        $insert_stmt = $this->conn->prepare(
            "INSERT INTO holidays
                (holiday_date, holiday_name, holiday_type, is_active)
             VALUES (?, ?, ?, 1)"
        );

        $added = 0;
        $skipped = 0;

        try {
            $this->conn->beginTransaction();

            foreach ($holidays as $day) {
                $date = trim((string)($day['date'] ?? ''));
                $name = trim((string)(
                    $day['localName']
                    ?? $day['local_name']
                    ?? $day['name']
                    ?? ''
                ));

                if (!$this->isValidIsoDateForYear($date, $year) || $name === '') {
                    continue;
                }

                if (isset($existing_dates[$date])) {
                    $skipped++;
                    continue;
                }

                $type = strtoupper(trim((string)($day['holiday_type'] ?? '')));
                if (!in_array($type, ['REGULAR', 'COMPENSATION', 'SPECIAL'], true)) {
                    $type = 'REGULAR';

                    if (mb_strpos($name, 'ชดเชย') !== false ||
                        stripos($name, 'substitution') !== false ||
                        stripos($name, 'observed') !== false) {
                        $type = 'COMPENSATION';
                    } elseif (mb_strpos($name, 'พิเศษ') !== false ||
                              stripos($name, 'special') !== false) {
                        $type = 'SPECIAL';
                    }
                }

                if ($insert_stmt->execute([$date, $name, $type])) {
                    $added++;
                    $existing_dates[$date] = true;
                }
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Holiday sync error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ไม่สามารถบันทึกข้อมูลวันหยุดลงฐานข้อมูลได้'
            ];
        }

        return [
            'success' => true,
            'added' => $added,
            'skipped' => $skipped,
            'provider' => $provider,
            'fallback' => !empty($api_holidays['fallback']),
            'provider_message' => $api_holidays['provider_message'] ?? null
        ];
    }

    private function fetchNagerDateHolidays(int $year): array {
        $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/TH";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: RosterPro/1.0'
            ],
        ]);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            error_log('Nager.Date transport error: ' . $curl_error);
            return [
                'success' => false,
                'message' => 'เชื่อมต่อ Nager.Date ไม่สำเร็จ'
            ];
        }

        if ($http_code === 204) {
            return [
                'success' => false,
                'message' => 'Nager.Date ไม่มีข้อมูลประเทศไทยสำหรับปี ' . ($year + 543) . ' (HTTP 204)'
            ];
        }

        if ($http_code !== 200) {
            return [
                'success' => false,
                'message' => 'Nager.Date ตอบกลับ HTTP ' . $http_code
            ];
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || empty($decoded)) {
            return [
                'success' => false,
                'message' => 'Nager.Date ไม่ส่งข้อมูลวันหยุดกลับมา'
            ];
        }

        return [
            'success' => true,
            'holidays' => $decoded
        ];
    }

    private function isValidIsoDateForYear(string $date, int $year): bool {
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) {
            return false;
        }

        $dt = DateTime::createFromFormat('!Y-m-d', $date);
        return $dt
            && $dt->format('Y-m-d') === $date
            && (int)$dt->format('Y') === $year;
    }

    private function getThailandGovernmentHolidayFallback(int $year): array {
        // ปี 2569: ชุดวันหยุดราชการทั่วประเทศที่ใช้กับการคำนวณวันลา
        // ไม่รวมวันหยุดเฉพาะพื้นที่ เช่น กทม. หรือจังหวัดที่ประกาศเฉพาะกิจภายหลัง
        $datasets = [
            2026 => [
                ['date' => '2026-01-01', 'localName' => 'วันขึ้นปีใหม่', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-01-02', 'localName' => 'วันหยุดราชการเพิ่มเป็นกรณีพิเศษ', 'holiday_type' => 'SPECIAL'],
                ['date' => '2026-03-03', 'localName' => 'วันมาฆบูชา', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-04-06', 'localName' => 'วันจักรี', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-04-13', 'localName' => 'วันสงกรานต์', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-04-14', 'localName' => 'วันสงกรานต์', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-04-15', 'localName' => 'วันสงกรานต์', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-05-01', 'localName' => 'วันแรงงานแห่งชาติ', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-05-04', 'localName' => 'วันฉัตรมงคล', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-05-13', 'localName' => 'วันพืชมงคล', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-06-01', 'localName' => 'ชดเชยวันวิสาขบูชา', 'holiday_type' => 'COMPENSATION'],
                ['date' => '2026-06-03', 'localName' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้าสุทิดา พระบรมราชินี', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-07-28', 'localName' => 'วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-07-29', 'localName' => 'วันอาสาฬหบูชา', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-07-30', 'localName' => 'วันเข้าพรรษา', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-08-12', 'localName' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระบรมราชชนนีพันปีหลวง และวันแม่แห่งชาติ', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-10-13', 'localName' => 'วันนวมินทรมหาราช', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-10-23', 'localName' => 'วันปิยมหาราช', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-12-07', 'localName' => 'ชดเชยวันคล้ายวันพระบรมราชสมภพ รัชกาลที่ 9 วันชาติ และวันพ่อแห่งชาติ', 'holiday_type' => 'COMPENSATION'],
                ['date' => '2026-12-10', 'localName' => 'วันรัฐธรรมนูญ', 'holiday_type' => 'REGULAR'],
                ['date' => '2026-12-31', 'localName' => 'วันสิ้นปี', 'holiday_type' => 'REGULAR'],
            ],
        ];

        return $datasets[$year] ?? [];
    }
}
?>