<?php
declare(strict_types=1);

class RosterAuditModel {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function validateMonth(string $monthYear): void {
        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $monthYear)) {
            throw new InvalidArgumentException('Invalid audit month.');
        }
    }

    private function encodeJson($value): ?string {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode audit payload.');
        }

        return $json;
    }

    public function record(
        int $hospitalId,
        string $monthYear,
        ?int $actorUserId,
        string $actionType,
        $before = null,
        $after = null,
        array $metadata = [],
        ?int $targetUserId = null,
        ?string $shiftDate = null,
        string $entityType = 'ROSTER'
    ): int {
        if ($hospitalId <= 0) {
            throw new InvalidArgumentException('Invalid hospital for audit.');
        }
        $this->validateMonth($monthYear);

        $actionType = strtoupper(trim($actionType));
        $entityType = strtoupper(trim($entityType));

        if (!preg_match('/^[A-Z0-9_]{2,40}$/', $actionType)) {
            throw new InvalidArgumentException('Invalid audit action type.');
        }
        if (!preg_match('/^[A-Z0-9_]{2,30}$/', $entityType)) {
            throw new InvalidArgumentException('Invalid audit entity type.');
        }

        if ($shiftDate !== null) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $shiftDate);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $shiftDate || substr($shiftDate, 0, 7) !== $monthYear) {
                throw new InvalidArgumentException('Invalid audit shift date.');
            }
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO roster_audit_logs
                (hospital_id, month_year, actor_user_id, action_type, entity_type,
                 target_user_id, shift_date, before_json, after_json, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $hospitalId,
            $monthYear,
            $actorUserId,
            $actionType,
            $entityType,
            $targetUserId,
            $shiftDate,
            $this->encodeJson($before),
            $this->encodeJson($after),
            $this->encodeJson($metadata ?: null),
        ]);

        $id = (int)$this->conn->lastInsertId();
        if ($id <= 0) {
            throw new RuntimeException('Unable to create roster audit event.');
        }

        return $id;
    }

    public function listEvents(int $hospitalId, string $monthYear, int $limit = 40): array {
        if ($hospitalId <= 0) {
            return [];
        }
        $this->validateMonth($monthYear);
        $limit = max(1, min(100, $limit));

        $stmt = $this->conn->prepare(
            "SELECT a.id, a.hospital_id, a.month_year, a.actor_user_id,
                    a.action_type, a.entity_type, a.target_user_id, a.shift_date,
                    a.before_json, a.after_json, a.metadata_json, a.created_at,
                    actor.name AS actor_name,
                    target.name AS target_name
             FROM roster_audit_logs a
             LEFT JOIN users actor ON actor.id = a.actor_user_id
             LEFT JOIN users target ON target.id = a.target_user_id
             WHERE a.hospital_id = ? AND a.month_year = ?
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$hospitalId, $monthYear]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            foreach (['before_json' => 'before', 'after_json' => 'after', 'metadata_json' => 'metadata'] as $jsonKey => $decodedKey) {
                $decoded = null;
                if (!empty($row[$jsonKey])) {
                    $decoded = json_decode((string)$row[$jsonKey], true);
                }
                $row[$decodedKey] = is_array($decoded) ? $decoded : null;
                unset($row[$jsonKey]);
            }
        }
        unset($row);

        return $rows;
    }
}
