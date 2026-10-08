<?php
class LeaveDocumentService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public static function placeholderCatalog(): array {
        return [
            '{{request_id}}' => 'เลขที่คำขอ',
            '{{employee_name}}' => 'ชื่อผู้ลา',
            '{{position}}' => 'ตำแหน่ง',
            '{{employee_type}}' => 'ประเภทบุคลากร',
            '{{hospital_name}}' => 'หน่วยบริการ/สังกัด',
            '{{leave_type}}' => 'ประเภทการลา',
            '{{reason}}' => 'เหตุผลการลา',
            '{{start_date}}' => 'วันที่เริ่มลา (ค.ศ.)',
            '{{end_date}}' => 'วันที่สิ้นสุด (ค.ศ.)',
            '{{start_date_th}}' => 'วันที่เริ่มลา (ไทย)',
            '{{end_date_th}}' => 'วันที่สิ้นสุด (ไทย)',
            '{{num_days}}' => 'จำนวนวันลา',
            '{{leave_days_previous}}' => 'ลามาแล้ว (วันทำการ)',
            '{{leave_days_current}}' => 'ลาครั้งนี้ (วันทำการ)',
            '{{leave_days_total}}' => 'รวมเป็น (วันทำการ)',
            '{{submitted_date_th}}' => 'วันที่ยื่นคำขอ',
            '{{status}}' => 'สถานะใบลา',
            '{{approved_date_th}}' => 'วันที่อนุมัติ',
            '{{approver_name}}' => 'ชื่อผู้อนุมัติ',
            '{{employee_signature}}' => 'ลายเซ็นผู้ยื่น (ข้อความสถานะใน Phase 12.1)',
            '{{approver_signature}}' => 'ลายเซ็นผู้อนุมัติ (ข้อความสถานะใน Phase 12.1)',
        ];
    }

    public function buildLeaveData(int $requestId): array {
        $stmt = $this->db->prepare("
            SELECT lr.*,
                   lq.leave_type,
                   u.name AS employee_name,
                   u.position,
                   u.employee_type,
                   u.signature_path AS employee_signature,
                   u.hospital_id,
                   h.name AS hospital_name,
                   approver.name AS approver_name,
                   approver.signature_path AS approver_signature
            FROM leave_requests lr
            JOIN leave_quotas lq ON lr.leave_type_id = lq.id
            JOIN users u ON lr.user_id = u.id
            LEFT JOIN hospitals h ON u.hospital_id = h.id
            LEFT JOIN users approver ON lr.approved_by = approver.id
            WHERE lr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('ไม่พบข้อมูลใบลา');
        }


        // Leave balances and quota deductions in this application are assigned to
        // the fiscal year containing the request's START date (1 Oct - 30 Sep).
        // For "ลามาแล้ว" include only earlier, completed, APPROVED leave of the
        // SAME employee and SAME leave type. Exclude cancelled, rejected, pending,
        // future and the current request, including when already approved.
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$row['start_date']);
        if (!$start || $start->format('Y-m-d') !== (string)$row['start_date']) {
            throw new RuntimeException('วันที่เริ่มลาของใบคำขอไม่ถูกต้อง');
        }
        $year = (int)$start->format('Y');
        $fiscalYearStart = (int)$start->format('n') >= 10 ? $year : $year - 1;
        $from = sprintf('%04d-10-01', $fiscalYearStart);
        $until = sprintf('%04d-10-01', $fiscalYearStart + 1);

        $history = $this->db->prepare("
            SELECT COALESCE(SUM(num_days), 0)
            FROM leave_requests
            WHERE user_id = :user_id
              AND leave_type_id = :leave_type_id
              AND id <> :request_id
              AND status = 'APPROVED'
              AND start_date >= :fiscal_start
              AND start_date < :fiscal_end
              AND end_date < :request_start
        ");
        $history->execute([
            ':user_id' => (int)$row['user_id'],
            ':leave_type_id' => (int)$row['leave_type_id'],
            ':request_id' => (int)$row['id'],
            ':fiscal_start' => $from,
            ':fiscal_end' => $until,
            ':request_start' => $start->format('Y-m-d'),
        ]);

        // Keep the already-approved/requested num_days basis used by LeaveModel.
        // Some special leave types are calendar-day based; never silently convert
        // those records to business days when generating a document.
        $previous = round((float)$history->fetchColumn(), 2);
        $current = round((float)($row['num_days'] ?? 0), 2);
        $row['leave_days_previous'] = $previous;
        $row['leave_days_current'] = $current;
        $row['leave_days_total'] = round($previous + $current, 2);
        $row['leave_days_fiscal_year'] = $fiscalYearStart + 1;

        return $row;
    }

    public function replacementMap(array $leave): array {
        return [
            '{{request_id}}' => (string)($leave['id'] ?? ''),
            '{{employee_name}}' => (string)($leave['employee_name'] ?? ''),
            '{{position}}' => (string)($leave['position'] ?? ''),
            '{{employee_type}}' => (string)($leave['employee_type'] ?? ''),
            '{{hospital_name}}' => (string)($leave['hospital_name'] ?? ''),
            '{{leave_type}}' => (string)($leave['leave_type'] ?? ''),
            '{{reason}}' => (string)($leave['reason'] ?? ''),
            '{{start_date}}' => (string)($leave['start_date'] ?? ''),
            '{{end_date}}' => (string)($leave['end_date'] ?? ''),
            '{{start_date_th}}' => $this->thaiDate((string)($leave['start_date'] ?? '')),
            '{{end_date_th}}' => $this->thaiDate((string)($leave['end_date'] ?? '')),
            '{{num_days}}' => $this->formatNumber($leave['num_days'] ?? 0),
            '{{leave_days_previous}}' => $this->formatNumber($leave['leave_days_previous'] ?? 0),
            '{{leave_days_current}}' => $this->formatNumber($leave['leave_days_current'] ?? $leave['num_days'] ?? 0),
            '{{leave_days_total}}' => $this->formatNumber($leave['leave_days_total'] ?? (($leave['leave_days_previous'] ?? 0) + ($leave['leave_days_current'] ?? $leave['num_days'] ?? 0))),
            '{{submitted_date_th}}' => $this->thaiDateTime((string)($leave['created_at'] ?? '')),
            '{{status}}' => $this->statusLabel((string)($leave['status'] ?? '')),
            '{{approved_date_th}}' => $this->thaiDateTime((string)($leave['approved_at'] ?? '')),
            '{{approver_name}}' => (string)($leave['approver_name'] ?? ''),
            '{{employee_signature}}' => !empty($leave['employee_signature']) ? '[มีลายเซ็นอิเล็กทรอนิกส์]' : '',
            '{{approver_signature}}' => !empty($leave['approver_signature']) ? '[มีลายเซ็นผู้อนุมัติ]' : '',
        ];
    }

    public function renderDocx(string $sourcePath, string $outputPath, array $replace): void {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP Zip extension ยังไม่ได้เปิดใช้งาน');
        }

        if (!is_file($sourcePath)) {
            throw new RuntimeException('ไม่พบไฟล์ Template');
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0750, true) && !is_dir($outputDir)) {
            throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เอกสารได้');
        }

        if (!copy($sourcePath, $outputPath)) {
            throw new RuntimeException('ไม่สามารถคัดลอก Template ได้');
        }

        $zip = new ZipArchive();
        if ($zip->open($outputPath) !== true) {
            @unlink($outputPath);
            throw new RuntimeException('ไม่สามารถเปิดไฟล์ DOCX ได้');
        }

        $xmlFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $name)) {
                $xmlFiles[] = $name;
            }
        }

        foreach ($xmlFiles as $xmlName) {
            $xml = $zip->getFromName($xmlName);
            if (!is_string($xml)) continue;

            foreach ($replace as $key => $value) {
                $xml = str_replace($key, htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8'), $xml);
            }

            $zip->addFromString($xmlName, $xml);
        }

        $zip->close();
    }

    public function scanDocxPlaceholders(string $sourcePath): array {
        if (!class_exists('ZipArchive') || !is_file($sourcePath)) return [];

        $zip = new ZipArchive();
        if ($zip->open($sourcePath) !== true) return [];

        $found = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $name)) continue;

            $xml = (string)$zip->getFromName($name);
            if (preg_match_all('/\{\{[a-z0-9_]+\}\}/i', html_entity_decode(strip_tags($xml)), $matches)) {
                foreach ($matches[0] as $token) $found[$token] = true;
            }
        }
        $zip->close();

        return array_keys($found);
    }

    private function thaiDate(string $date): string {
        if ($date === '') return '';
        $ts = strtotime($date);
        if (!$ts) return '';

        $months = [
            1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
            5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
            9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
        ];
        return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . ((int)date('Y', $ts) + 543);
    }

    private function thaiDateTime(string $date): string {
        if ($date === '') return '';
        $ts = strtotime($date);
        if (!$ts) return '';
        return $this->thaiDate(date('Y-m-d', $ts)) . ' ' . date('H:i', $ts) . ' น.';
    }

    private function formatNumber($number): string {
        $value = (float)$number;
        return floor($value) == $value ? (string)(int)$value : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    private function statusLabel(string $status): string {
        return match (strtoupper($status)) {
            'APPROVED' => 'อนุมัติแล้ว',
            'REJECTED' => 'ไม่อนุมัติ',
            'CANCELLED' => 'ยกเลิกแล้ว',
            'CANCEL_REQUESTED' => 'รออนุมัติยกเลิก',
            default => 'รอพิจารณา',
        };
    }
}
?>