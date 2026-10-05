<?php
declare(strict_types=1);

class RosterRevisionModel {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function validateMonth(string $monthYear): void {
        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $monthYear)) {
            throw new InvalidArgumentException('Invalid roster revision month.');
        }
    }

    private function encode($value): string {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode roster revision payload.');
        }
        return $json;
    }

    private function signer(?int $userId): ?array {
        if (!$userId) {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT id, name,
                    COALESCE(NULLIF(position, ''), NULLIF(type, ''), '') AS signer_position,
                    signature_path
             FROM users
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'position' => (string)($row['signer_position'] ?? ''),
            'signature' => $row['signature_path'] !== null ? (string)$row['signature_path'] : null,
        ];
    }

    private function calculateHash(array $payload): string {
        return hash('sha256', $this->encode($payload));
    }

    private function getNextRevisionNo(int $hospitalId, string $monthYear): int {
        $stmt = $this->conn->prepare(
            "SELECT revision_no
             FROM roster_revisions
             WHERE hospital_id = ? AND month_year = ?
             ORDER BY revision_no DESC
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$hospitalId, $monthYear]);
        $latest = $stmt->fetchColumn();
        return ((int)($latest ?: 0)) + 1;
    }

    public function createApprovedRevision(
        int $hospitalId,
        string $monthYear,
        int $snapshotId,
        int $approvedBy
    ): int {
        if ($hospitalId <= 0 || $snapshotId <= 0 || $approvedBy <= 0) {
            throw new InvalidArgumentException('Invalid approved revision request.');
        }
        $this->validateMonth($monthYear);

        $statusStmt = $this->conn->prepare(
            "SELECT *
             FROM roster_status
             WHERE hospital_id = ? AND month_year = ?
             LIMIT 1"
        );
        $statusStmt->execute([$hospitalId, $monthYear]);
        $status = $statusStmt->fetch(PDO::FETCH_ASSOC);
        if (!$status || strtoupper((string)$status['status']) !== 'APPROVED') {
            throw new RuntimeException('Only an approved roster can create an official revision.');
        }

        $snapshotStmt = $this->conn->prepare(
            "SELECT id, hospital_id, month_year, snapshot_kind, shifts_json, checksum, is_protected
             FROM roster_snapshots
             WHERE id = ? AND hospital_id = ? AND month_year = ?
             LIMIT 1"
        );
        $snapshotStmt->execute([$snapshotId, $hospitalId, $monthYear]);
        $snapshot = $snapshotStmt->fetch(PDO::FETCH_ASSOC);
        if (!$snapshot || (int)$snapshot['is_protected'] !== 1) {
            throw new RuntimeException('Approved revision requires a protected roster snapshot.');
        }

        $shiftsJson = (string)$snapshot['shifts_json'];
        $expectedChecksum = (string)$snapshot['checksum'];
        if ($expectedChecksum === '' || !hash_equals($expectedChecksum, hash('sha256', $shiftsJson))) {
            throw new RuntimeException('Approved snapshot integrity check failed.');
        }

        $decodedShifts = json_decode($shiftsJson, true);
        if (!is_array($decodedShifts)) {
            throw new RuntimeException('Approved snapshot payload is invalid.');
        }

        $hospitalStmt = $this->conn->prepare("SELECT name FROM hospitals WHERE id = ? LIMIT 1");
        $hospitalStmt->execute([$hospitalId]);
        $hospitalName = (string)($hospitalStmt->fetchColumn() ?: 'หน่วยบริการ');

        $staffStmt = $this->conn->prepare(
            "SELECT id, name, type, position, employee_type, position_number, pay_rate_id, display_order
             FROM users
             WHERE hospital_id = ?
               AND is_deleted = 0
               AND is_active = 1
               AND (show_in_roster = 1 OR show_in_roster IS NULL)
             ORDER BY display_order ASC, name ASC"
        );
        $staffStmt->execute([$hospitalId]);
        $staffRows = array_map(
            static function(array $row): array {
                return [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'type' => (string)($row['type'] ?? ''),
                    'position' => (string)($row['position'] ?? ''),
                    'employee_type' => (string)($row['employee_type'] ?? ''),
                    'position_number' => (string)($row['position_number'] ?? ''),
                    'pay_rate_id' => $row['pay_rate_id'] !== null ? (int)$row['pay_rate_id'] : null,
                    'display_order' => (int)($row['display_order'] ?? 0),
                ];
            },
            $staffStmt->fetchAll(PDO::FETCH_ASSOC)
        );

        $preparedId = !empty($status['creator_id']) ? (int)$status['creator_id'] : null;
        $reviewedId = !empty($status['director_id'])
            ? (int)$status['director_id']
            : (!empty($status['reviewer_id']) ? (int)$status['reviewer_id'] : null);

        $prepared = $this->signer($preparedId);
        $reviewed = $this->signer($reviewedId);
        $approved = $this->signer($approvedBy);
        if (!$approved) {
            throw new RuntimeException('Approver account was not found.');
        }

        $revisionNo = $this->getNextRevisionNo($hospitalId, $monthYear);
        $revisionCode = sprintf('REV-%s-%03d', $monthYear, $revisionNo);
        $staffJson = $this->encode($staffRows);

        $holidayStmt = $this->conn->prepare(
            "SELECT holiday_date, holiday_name, holiday_type
             FROM holidays
             WHERE is_active = 1
               AND status = 'APPROVED'
               AND (hospital_id = ? OR hospital_id IS NULL OR hospital_id = 0)
               AND holiday_date LIKE ?
             ORDER BY holiday_date ASC, id ASC"
        );
        $holidayStmt->execute([$hospitalId, $monthYear . '-%']);
        $holidayRows = array_map(
            static fn(array $row): array => [
                'holiday_date' => (string)$row['holiday_date'],
                'holiday_name' => (string)$row['holiday_name'],
                'holiday_type' => (string)($row['holiday_type'] ?? 'REGULAR'),
            ],
            $holidayStmt->fetchAll(PDO::FETCH_ASSOC)
        );
        $holidaysJson = $this->encode($holidayRows);

        $paySummary = null;
        if (!empty($status['pay_summary'])) {
            $decodedPay = json_decode((string)$status['pay_summary'], true);
            if (is_array($decodedPay)) {
                $paySummary = $decodedPay;
            }
        }
        $paySummaryJson = $paySummary !== null ? $this->encode($paySummary) : null;

        $preparedAt = !empty($status['submitted_at']) ? (string)$status['submitted_at'] : null;
        $approvedAt = date('Y-m-d H:i:s');
        $reviewedAt = $reviewed ? $approvedAt : null;

        $hashPayload = [
            'hospital_id' => $hospitalId,
            'hospital_name' => $hospitalName,
            'month_year' => $monthYear,
            'revision_no' => $revisionNo,
            'revision_code' => $revisionCode,
            'snapshot_id' => $snapshotId,
            'snapshot_checksum' => $expectedChecksum,
            'prepared' => $prepared,
            'prepared_at' => $preparedAt,
            'reviewed' => $reviewed,
            'reviewed_at' => $reviewedAt,
            'approved' => $approved,
            'approved_at' => $approvedAt,
            'staff' => $staffRows,
            'holidays' => $holidayRows,
            'shifts' => $decodedShifts,
            'pay_summary' => $paySummary,
        ];
        $contentHash = $this->calculateHash($hashPayload);

        $stmt = $this->conn->prepare(
            "INSERT INTO roster_revisions
                (hospital_id, hospital_name, month_year, revision_no, revision_code, snapshot_id,
                 prepared_by, prepared_name, prepared_position, prepared_signature, prepared_at,
                 reviewed_by, reviewed_name, reviewed_position, reviewed_signature, reviewed_at,
                 approved_by, approved_name, approved_position, approved_signature, approved_at,
                 staff_json, holidays_json, shifts_json, pay_summary_json, content_hash)
             VALUES (?, ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $hospitalId,
            $hospitalName,
            $monthYear,
            $revisionNo,
            $revisionCode,
            $snapshotId,
            $prepared['id'] ?? null,
            $prepared['name'] ?? null,
            $prepared['position'] ?? null,
            $prepared['signature'] ?? null,
            $preparedAt,
            $reviewed['id'] ?? null,
            $reviewed['name'] ?? null,
            $reviewed['position'] ?? null,
            $reviewed['signature'] ?? null,
            $reviewedAt,
            $approved['id'],
            $approved['name'],
            $approved['position'],
            $approved['signature'],
            $approvedAt,
            $staffJson,
            $holidaysJson,
            $shiftsJson,
            $paySummaryJson,
            $contentHash,
        ]);

        $id = (int)$this->conn->lastInsertId();
        if ($id <= 0) {
            throw new RuntimeException('Unable to create approved roster revision.');
        }
        return $id;
    }

    public function listRevisions(int $hospitalId, string $monthYear, int $limit = 20): array {
        if ($hospitalId <= 0) {
            return [];
        }
        $this->validateMonth($monthYear);
        $limit = max(1, min(50, $limit));

        $stmt = $this->conn->prepare(
            "SELECT id, hospital_id, hospital_name, month_year, revision_no, revision_code,
                    snapshot_id, prepared_by, prepared_name, prepared_position, prepared_at,
                    reviewed_by, reviewed_name, reviewed_position, reviewed_at,
                    approved_by, approved_name, approved_position, approved_at,
                    content_hash, created_at
             FROM roster_revisions
             WHERE hospital_id = ? AND month_year = ?
             ORDER BY revision_no DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$hospitalId, $monthYear]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRevision(int $revisionId, int $hospitalId): ?array {
        if ($revisionId <= 0 || $hospitalId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT *
             FROM roster_revisions
             WHERE id = ? AND hospital_id = ?
             LIMIT 1"
        );
        $stmt->execute([$revisionId, $hospitalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        foreach (['staff_json' => 'staff', 'holidays_json' => 'holidays', 'shifts_json' => 'shifts', 'pay_summary_json' => 'pay_summary'] as $jsonKey => $decodedKey) {
            $decoded = null;
            if ($row[$jsonKey] !== null && $row[$jsonKey] !== '') {
                $decoded = json_decode((string)$row[$jsonKey], true);
            }
            $row[$decodedKey] = is_array($decoded) ? $decoded : null;
        }

        return $row;
    }

    public function verifyRevision(int $revisionId, int $hospitalId): bool {
        $revision = $this->getRevision($revisionId, $hospitalId);
        if (!$revision) {
            return false;
        }

        $snapshotStmt = $this->conn->prepare(
            "SELECT checksum FROM roster_snapshots WHERE id = ? AND hospital_id = ? LIMIT 1"
        );
        $snapshotStmt->execute([(int)$revision['snapshot_id'], $hospitalId]);
        $snapshotChecksum = (string)($snapshotStmt->fetchColumn() ?: '');

        $payload = [
            'hospital_id' => (int)$revision['hospital_id'],
            'hospital_name' => (string)$revision['hospital_name'],
            'month_year' => (string)$revision['month_year'],
            'revision_no' => (int)$revision['revision_no'],
            'revision_code' => (string)$revision['revision_code'],
            'snapshot_id' => (int)$revision['snapshot_id'],
            'snapshot_checksum' => $snapshotChecksum,
            'prepared' => $revision['prepared_by'] ? [
                'id' => (int)$revision['prepared_by'],
                'name' => (string)$revision['prepared_name'],
                'position' => (string)($revision['prepared_position'] ?? ''),
                'signature' => $revision['prepared_signature'] !== null ? (string)$revision['prepared_signature'] : null,
            ] : null,
            'prepared_at' => $revision['prepared_at'],
            'reviewed' => $revision['reviewed_by'] ? [
                'id' => (int)$revision['reviewed_by'],
                'name' => (string)$revision['reviewed_name'],
                'position' => (string)($revision['reviewed_position'] ?? ''),
                'signature' => $revision['reviewed_signature'] !== null ? (string)$revision['reviewed_signature'] : null,
            ] : null,
            'reviewed_at' => $revision['reviewed_at'],
            'approved' => [
                'id' => (int)$revision['approved_by'],
                'name' => (string)$revision['approved_name'],
                'position' => (string)($revision['approved_position'] ?? ''),
                'signature' => $revision['approved_signature'] !== null ? (string)$revision['approved_signature'] : null,
            ],
            'approved_at' => $revision['approved_at'],
            'staff' => $revision['staff'] ?? [],
            'holidays' => $revision['holidays'] ?? [],
            'shifts' => $revision['shifts'] ?? [],
            'pay_summary' => $revision['pay_summary'],
        ];

        return hash_equals((string)$revision['content_hash'], $this->calculateHash($payload));
    }
}
