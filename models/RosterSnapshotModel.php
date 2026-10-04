<?php
declare(strict_types=1);

class RosterSnapshotModel {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function validateMonth(string $monthYear): void {
        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $monthYear)) {
            throw new InvalidArgumentException('Invalid roster month.');
        }
    }

    private function getRosterStatus(int $hospitalId, string $monthYear): string {
        $stmt = $this->conn->prepare(
            "SELECT status FROM roster_status WHERE hospital_id = ? AND month_year = ? LIMIT 1"
        );
        $stmt->execute([$hospitalId, $monthYear]);
        return strtoupper((string)($stmt->fetchColumn() ?: 'DRAFT'));
    }

    private function getCurrentShifts(int $hospitalId, string $monthYear): array {
        $stmt = $this->conn->prepare(
            "SELECT s.user_id, s.shift_date, s.shift_type
             FROM shifts s
             JOIN users u ON u.id = s.user_id
             WHERE s.hospital_id = ?
               AND u.hospital_id = ?
               AND s.shift_date LIKE ?
             ORDER BY s.shift_date ASC, s.user_id ASC, s.id ASC"
        );
        $stmt->execute([$hospitalId, $hospitalId, $monthYear . '-%']);

        return array_map(
            static fn(array $row): array => [
                'user_id' => (int)$row['user_id'],
                'shift_date' => (string)$row['shift_date'],
                'shift_type' => (string)$row['shift_type'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function createSnapshot(
        int $hospitalId,
        string $monthYear,
        ?int $createdBy,
        string $kind = 'MANUAL',
        ?string $label = null,
        bool $protected = false
    ): int {
        if ($hospitalId <= 0) {
            throw new InvalidArgumentException('Invalid hospital.');
        }
        $this->validateMonth($monthYear);

        $kind = strtoupper(trim($kind));
        if (!preg_match('/^[A-Z0-9_]{2,30}$/', $kind)) {
            throw new InvalidArgumentException('Invalid snapshot kind.');
        }

        $shifts = $this->getCurrentShifts($hospitalId, $monthYear);
        $json = json_encode($shifts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode roster snapshot.');
        }

        $checksum = hash('sha256', $json);
        $status = $this->getRosterStatus($hospitalId, $monthYear);
        $safeLabel = trim((string)$label);
        if ($safeLabel === '') {
            $safeLabel = null;
        } elseif (mb_strlen($safeLabel, 'UTF-8') > 160) {
            $safeLabel = mb_substr($safeLabel, 0, 160, 'UTF-8');
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO roster_snapshots
                (hospital_id, month_year, snapshot_kind, label, status_snapshot,
                 shift_count, shifts_json, checksum, is_protected, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $hospitalId,
            $monthYear,
            $kind,
            $safeLabel,
            $status,
            count($shifts),
            $json,
            $checksum,
            $protected ? 1 : 0,
            $createdBy,
        ]);

        $snapshotId = (int)$this->conn->lastInsertId();
        if ($snapshotId <= 0) {
            throw new RuntimeException('Unable to create roster snapshot.');
        }

        $this->pruneSnapshots($hospitalId, $monthYear, 30);
        return $snapshotId;
    }

    public function listSnapshots(int $hospitalId, string $monthYear, int $limit = 15): array {
        $this->validateMonth($monthYear);
        $limit = max(1, min(50, $limit));

        $stmt = $this->conn->prepare(
            "SELECT rs.id, rs.hospital_id, rs.month_year, rs.snapshot_kind, rs.label,
                    rs.status_snapshot, rs.shift_count, rs.checksum, rs.is_protected,
                    rs.created_by, rs.created_at, u.name AS created_by_name
             FROM roster_snapshots rs
             LEFT JOIN users u ON u.id = rs.created_by
             WHERE rs.hospital_id = ? AND rs.month_year = ?
             ORDER BY rs.created_at DESC, rs.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$hospitalId, $monthYear]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSnapshot(int $snapshotId, int $hospitalId, string $monthYear): ?array {
        $this->validateMonth($monthYear);
        $stmt = $this->conn->prepare(
            "SELECT * FROM roster_snapshots
             WHERE id = ? AND hospital_id = ? AND month_year = ?
             LIMIT 1"
        );
        $stmt->execute([$snapshotId, $hospitalId, $monthYear]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function restoreSnapshot(
        int $snapshotId,
        int $hospitalId,
        string $monthYear,
        ?int $actorId
    ): array {
        if ($hospitalId <= 0 || $snapshotId <= 0) {
            throw new InvalidArgumentException('Invalid snapshot restore request.');
        }
        $this->validateMonth($monthYear);

        $snapshot = $this->getSnapshot($snapshotId, $hospitalId, $monthYear);
        if (!$snapshot) {
            throw new RuntimeException('Snapshot not found.');
        }

        $rows = json_decode((string)$snapshot['shifts_json'], true);
        if (!is_array($rows)) {
            throw new RuntimeException('Snapshot data is invalid.');
        }

        $userIds = [];
        foreach ($rows as $row) {
            $uid = (int)($row['user_id'] ?? 0);
            $date = (string)($row['shift_date'] ?? '');
            $type = trim((string)($row['shift_type'] ?? ''));

            $dateObj = DateTime::createFromFormat('Y-m-d', $date);
            if (
                $uid <= 0
                || !$dateObj
                || $dateObj->format('Y-m-d') !== $date
                || substr($date, 0, 7) !== $monthYear
                || $type === ''
                || mb_strlen($type, 'UTF-8') > 20
            ) {
                throw new RuntimeException('Snapshot contains invalid shift data.');
            }
            $userIds[$uid] = true;
        }

        if ($userIds) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $params = array_merge([$hospitalId], array_keys($userIds));
            $stmtUsers = $this->conn->prepare(
                "SELECT id FROM users
                 WHERE hospital_id = ?
                   AND id IN ({$placeholders})"
            );
            $stmtUsers->execute($params);
            $found = array_map('intval', $stmtUsers->fetchAll(PDO::FETCH_COLUMN));
            sort($found);
            $expected = array_map('intval', array_keys($userIds));
            sort($expected);

            if ($found !== $expected) {
                throw new RuntimeException('Snapshot references personnel no longer assigned to this hospital.');
            }
        }

        $startedHere = !$this->conn->inTransaction();
        if ($startedHere) {
            $this->conn->beginTransaction();
        }

        try {
            $beforeRestoreId = $this->createSnapshot(
                $hospitalId,
                $monthYear,
                $actorId,
                'BEFORE_RESTORE',
                'สำรองอัตโนมัติก่อนย้อนเวอร์ชัน',
                false
            );

            $delete = $this->conn->prepare(
                "DELETE FROM shifts WHERE hospital_id = ? AND shift_date LIKE ?"
            );
            $delete->execute([$hospitalId, $monthYear . '-%']);

            $insert = $this->conn->prepare(
                "INSERT INTO shifts (hospital_id, user_id, shift_date, shift_type)
                 VALUES (?, ?, ?, ?)"
            );
            foreach ($rows as $row) {
                $insert->execute([
                    $hospitalId,
                    (int)$row['user_id'],
                    (string)$row['shift_date'],
                    (string)$row['shift_type'],
                ]);
            }

            // Any restored roster must return to DRAFT for review instead of silently
            // inheriting an old approval state.
            $status = $this->conn->prepare(
                "INSERT INTO roster_status (hospital_id, month_year, status, pay_summary, updated_at)
                 VALUES (?, ?, 'DRAFT', NULL, NOW())
                 ON DUPLICATE KEY UPDATE
                    status = 'DRAFT',
                    pay_summary = NULL,
                    updated_at = NOW()"
            );
            $status->execute([$hospitalId, $monthYear]);

            if ($startedHere) {
                $this->conn->commit();
            }

            return [
                'snapshot_id' => $snapshotId,
                'before_restore_snapshot_id' => $beforeRestoreId,
                'restored_shift_count' => count($rows),
            ];
        } catch (Throwable $e) {
            if ($startedHere && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    private function pruneSnapshots(int $hospitalId, string $monthYear, int $keep): void {
        $keep = max(10, min(100, $keep));
        $stmt = $this->conn->prepare(
            "DELETE FROM roster_snapshots
             WHERE hospital_id = ?
               AND month_year = ?
               AND is_protected = 0
               AND id NOT IN (
                    SELECT id FROM (
                        SELECT id
                        FROM roster_snapshots
                        WHERE hospital_id = ?
                          AND month_year = ?
                          AND is_protected = 0
                        ORDER BY created_at DESC, id DESC
                        LIMIT {$keep}
                    ) latest
               )"
        );
        $stmt->execute([$hospitalId, $monthYear, $hospitalId, $monthYear]);
    }
}
