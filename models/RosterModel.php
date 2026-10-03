<?php
// Canonical roster data access for the current schema.

class RosterModel {
    private PDO $conn;
    private string $table_name = 'shifts';

    public function __construct(PDO $db) {
        $this->conn = $db;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getShiftsByMonth($hospital_id, $year, $month): array {
        $stmt = $this->conn->prepare(
            "SELECT id, hospital_id, user_id, shift_date, shift_type
             FROM {$this->table_name}
             WHERE hospital_id = :hospital_id
               AND YEAR(shift_date) = :year
               AND MONTH(shift_date) = :month
             ORDER BY shift_date ASC, user_id ASC"
        );
        $stmt->execute([
            ':hospital_id' => (int)$hospital_id,
            ':year' => (int)$year,
            ':month' => (int)$month,
        ]);

        $shifts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $day = (int)date('j', strtotime($row['shift_date']));
            $shifts[(int)$row['user_id']][$day] = [
                'id' => (int)$row['id'],
                'type' => $row['shift_type'],
                'date' => $row['shift_date'],
            ];
        }

        return $shifts;
    }

    public function saveShifts($hospital_id, $user_id, array $shifts_data): bool {
        try {
            $this->conn->beginTransaction();

            $upsert = $this->conn->prepare(
                "INSERT INTO {$this->table_name}
                    (hospital_id, user_id, shift_date, shift_type)
                 VALUES
                    (:hospital_id, :user_id, :shift_date, :shift_type)
                 ON DUPLICATE KEY UPDATE
                    hospital_id = VALUES(hospital_id),
                    shift_type = VALUES(shift_type)"
            );

            $delete = $this->conn->prepare(
                "DELETE FROM {$this->table_name}
                 WHERE hospital_id = :hospital_id
                   AND user_id = :user_id
                   AND shift_date = :shift_date"
            );

            foreach ($shifts_data as $date => $type) {
                $date = trim((string)$date);
                $type = trim((string)$type);

                if ($date === '') {
                    continue;
                }

                if ($type === '') {
                    $delete->execute([
                        ':hospital_id' => (int)$hospital_id,
                        ':user_id' => (int)$user_id,
                        ':shift_date' => $date,
                    ]);
                    continue;
                }

                $upsert->execute([
                    ':hospital_id' => (int)$hospital_id,
                    ':user_id' => (int)$user_id,
                    ':shift_date' => $date,
                    ':shift_type' => $type,
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('RosterModel::saveShifts failed: ' . $e->getMessage());
            return false;
        }
    }

    public function publishRoster($hospital_id, $year, $month): bool {
        $monthYear = sprintf('%04d-%02d', (int)$year, (int)$month);

        $stmt = $this->conn->prepare(
            "INSERT INTO roster_status (hospital_id, month_year, status, updated_at)
             VALUES (:hospital_id, :month_year, 'APPROVED', NOW())
             ON DUPLICATE KEY UPDATE
                status = 'APPROVED',
                updated_at = NOW()"
        );

        return $stmt->execute([
            ':hospital_id' => (int)$hospital_id,
            ':month_year' => $monthYear,
        ]);
    }
}
