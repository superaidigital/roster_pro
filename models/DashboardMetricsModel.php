<?php
declare(strict_types=1);

final class DashboardMetricsModel {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getExecutiveAggregates(?int $hospitalId, string $monthYear, string $today): array {
        [$monthStart, $nextMonthStart] = $this->monthBounds($monthYear);
        $tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');

        $totalHospitals = $hospitalId === null
            ? (int)$this->db->query(
                "SELECT COUNT(*) FROM hospitals
                 WHERE id <> 0 AND deleted_at IS NULL AND is_active = 1"
            )->fetchColumn()
            : 1;

        $staffSql = "
            SELECT COUNT(*)
            FROM users
            WHERE role NOT IN ('SUPERADMIN','ADMIN')
              AND deleted_at IS NULL
              AND is_deleted = 0
        ";
        $staffParams = [];
        if ($hospitalId !== null) {
            $staffSql .= " AND hospital_id = ?";
            $staffParams[] = $hospitalId;
        }
        $totalStaff = $this->scalar($staffSql, $staffParams);

        $dutySql = "
            SELECT COUNT(DISTINCT user_id)
            FROM shifts
            WHERE shift_date = ?
              AND shift_type NOT IN ('', 'ย', 'OFF', 'L', 'O')
        ";
        $dutyParams = [$today];
        if ($hospitalId !== null) {
            $dutySql .= " AND hospital_id = ?";
            $dutyParams[] = $hospitalId;
        }
        $onDutyToday = $this->scalar($dutySql, $dutyParams);

        if ($hospitalId === null) {
            $pendingLeaves = $this->scalar(
                "SELECT COUNT(*) FROM leave_requests WHERE status = 'PENDING'"
            );
        } else {
            $pendingLeaves = $this->scalar(
                "SELECT COUNT(*)
                 FROM leave_requests lr
                 INNER JOIN users u ON u.id = lr.user_id
                 WHERE lr.status = 'PENDING'
                   AND u.hospital_id = ?",
                [$hospitalId]
            );
        }

        $swapSql = "
            SELECT COUNT(*)
            FROM shift_swaps
            WHERE status IN ('PENDING_TARGET','PENDING_DIRECTOR')
        ";
        $swapParams = [];
        if ($hospitalId !== null) {
            $swapSql .= " AND hospital_id = ?";
            $swapParams[] = $hospitalId;
        }
        $pendingSwaps = $this->scalar($swapSql, $swapParams);

        $budgetSql = "
            SELECT COALESCE(SUM(
                CASE
                    WHEN s.shift_type IN ('ร','N') THEN pr.rate_r
                    WHEN s.shift_type IN ('บ','A') THEN pr.rate_b
                    WHEN s.shift_type IN ('ย','O') THEN pr.rate_y
                    WHEN s.shift_type = 'บ/ร' THEN pr.rate_b + pr.rate_r
                    WHEN s.shift_type = 'ย/บ' THEN pr.rate_y + pr.rate_b
                    ELSE 0
                END
            ), 0)
            FROM shifts s
            INNER JOIN users u ON u.id = s.user_id
            INNER JOIN pay_rates pr ON pr.id = u.pay_rate_id
            WHERE s.shift_date >= ?
              AND s.shift_date < ?
        ";
        $budgetParams = [$monthStart, $nextMonthStart];
        if ($hospitalId !== null) {
            $budgetSql .= " AND s.hospital_id = ?";
            $budgetParams[] = $hospitalId;
        }
        $estimatedBudget = (float)$this->scalarRaw($budgetSql, $budgetParams);

        [$statusCounts, $waitingHospitals] = $this->rosterStatus($hospitalId, $monthYear);

        $workloadSql = "
            SELECT h.short_name, COUNT(s.id) AS total_shifts
            FROM shifts s
            INNER JOIN hospitals h ON h.id = s.hospital_id
            WHERE s.shift_date >= ?
              AND s.shift_date < ?
              AND s.shift_type NOT IN ('', 'ย', 'OFF')
        ";
        $workloadParams = [$monthStart, $nextMonthStart];
        if ($hospitalId !== null) {
            $workloadSql .= " AND s.hospital_id = ?";
            $workloadParams[] = $hospitalId;
        }
        $workloadSql .= "
            GROUP BY s.hospital_id, h.short_name
            ORDER BY total_shifts DESC
            LIMIT 5
        ";
        $workload = $this->rows($workloadSql, $workloadParams);

        $leaveTrendSql = "
            SELECT lq.leave_type, COUNT(lr.id) AS count_leave
            FROM leave_requests lr
            INNER JOIN leave_quotas lq ON lq.id = lr.leave_type_id
            INNER JOIN users u ON u.id = lr.user_id
            WHERE lr.start_date >= ?
              AND lr.start_date < ?
              AND lr.status = 'APPROVED'
        ";
        $leaveTrendParams = [$monthStart, $nextMonthStart];
        if ($hospitalId !== null) {
            $leaveTrendSql .= " AND u.hospital_id = ?";
            $leaveTrendParams[] = $hospitalId;
        }
        $leaveTrendSql .= " GROUP BY lq.leave_type ORDER BY count_leave DESC";
        $leaveTrendRows = $this->rows($leaveTrendSql, $leaveTrendParams);

        $leaveLabels = [];
        $leaveData = [];
        foreach ($leaveTrendRows as $row) {
            $leaveLabels[] = (string)$row['leave_type'];
            $leaveData[] = (int)$row['count_leave'];
        }

        $riskSql = "
            SELECT h.name, COUNT(s.id) AS on_duty
            FROM hospitals h
            LEFT JOIN shifts s
              ON s.hospital_id = h.id
             AND s.shift_date = ?
             AND s.shift_type NOT IN ('', 'ย', 'OFF', 'O', 'L')
            WHERE h.id <> 0
              AND h.deleted_at IS NULL
              AND h.is_active = 1
        ";
        $riskParams = [$today];
        if ($hospitalId !== null) {
            $riskSql .= " AND h.id = ?";
            $riskParams[] = $hospitalId;
        }
        $riskSql .= "
            GROUP BY h.id, h.name
            HAVING COUNT(s.id) <= 1
            ORDER BY on_duty ASC, h.name ASC
        ";
        $riskHospitals = $this->rows($riskSql, $riskParams);

        $usageSql = "
            SELECT
                h.name AS hospital_name,
                COUNT(l.id) AS total_actions,
                SUM(CASE WHEN l.action = 'LOGIN' THEN 1 ELSE 0 END) AS count_login,
                SUM(CASE WHEN l.action IN ('CREATE','UPDATE','DELETE','IMPORT','RESTORE') THEN 1 ELSE 0 END) AS count_manage,
                SUM(CASE WHEN l.action = 'EXPORT' THEN 1 ELSE 0 END) AS count_export,
                MAX(l.created_at) AS last_active
            FROM logs l
            INNER JOIN users u ON u.id = l.user_id
            LEFT JOIN hospitals h ON h.id = u.hospital_id
            WHERE l.created_at >= ?
              AND l.created_at < ?
        ";
        $usageParams = [$today . ' 00:00:00', $tomorrow . ' 00:00:00'];
        if ($hospitalId !== null) {
            $usageSql .= " AND u.hospital_id = ?";
            $usageParams[] = $hospitalId;
        }
        $usageSql .= "
            GROUP BY h.id, h.name
            ORDER BY total_actions DESC
            LIMIT 15
        ";

        return [
            'total_hospitals' => $totalHospitals,
            'total_staff' => $totalStaff,
            'on_duty_today' => $onDutyToday,
            'pending_leaves' => $pendingLeaves,
            'pending_swaps' => $pendingSwaps,
            'estimated_budget' => $estimatedBudget,
            'status_counts' => $statusCounts,
            'waiting_hospitals' => $waitingHospitals,
            'workload_data' => $workload,
            'leave_trends_labels' => $leaveLabels,
            'leave_trends_data' => $leaveData,
            'risk_hospitals' => $riskHospitals,
            'today_usages' => $this->rows($usageSql, $usageParams),
        ];
    }

    public function getExecutiveLiveDetails(?int $hospitalId, string $monthYear): array {
        [$monthStart, $nextMonthStart] = $this->monthBounds($monthYear);

        $fatigueSql = "
            SELECT
                u.name,
                u.type AS position,
                h.short_name AS hosp_name,
                COUNT(s.id) AS shift_count
            FROM shifts s
            INNER JOIN users u ON u.id = s.user_id
            INNER JOIN hospitals h ON h.id = u.hospital_id
            WHERE s.shift_date >= ?
              AND s.shift_date < ?
              AND s.shift_type NOT IN ('', 'ย', 'OFF', 'O', 'L')
        ";
        $fatigueParams = [$monthStart, $nextMonthStart];
        if ($hospitalId !== null) {
            $fatigueSql .= " AND u.hospital_id = ?";
            $fatigueParams[] = $hospitalId;
        }
        $fatigueSql .= "
            GROUP BY s.user_id, u.name, u.type, h.short_name
            HAVING COUNT(s.id) > 24
            ORDER BY shift_count DESC
            LIMIT 5
        ";

        $leaveSql = "
            SELECT
                lr.id,
                lr.user_id,
                lr.leave_type_id,
                lr.start_date,
                lr.end_date,
                lr.num_days,
                lr.status,
                lr.created_at,
                u.name AS user_name,
                lq.leave_type
            FROM leave_requests lr
            INNER JOIN users u ON u.id = lr.user_id
            INNER JOIN leave_quotas lq ON lq.id = lr.leave_type_id
            WHERE lr.status = 'PENDING'
        ";
        $leaveParams = [];
        if ($hospitalId !== null) {
            $leaveSql .= " AND u.hospital_id = ?";
            $leaveParams[] = $hospitalId;
        }
        $leaveSql .= " ORDER BY lr.created_at DESC, lr.id DESC LIMIT 5";

        $logSql = "
            SELECT
                l.id,
                l.user_id,
                l.action,
                l.details,
                l.created_at,
                u.name AS user_name
            FROM logs l
            INNER JOIN users u ON u.id = l.user_id
        ";
        $logParams = [];
        if ($hospitalId !== null) {
            $logSql .= " WHERE u.hospital_id = ?";
            $logParams[] = $hospitalId;
        }
        $logSql .= " ORDER BY l.created_at DESC, l.id DESC LIMIT 6";

        return [
            'fatigue_staff' => $this->rows($fatigueSql, $fatigueParams),
            'recent_leaves' => $this->rows($leaveSql, $leaveParams),
            'recent_logs' => $this->rows($logSql, $logParams),
        ];
    }

    private function rosterStatus(?int $hospitalId, string $monthYear): array {
        $sql = "
            SELECT h.name, rs.status
            FROM hospitals h
            LEFT JOIN roster_status rs
              ON rs.hospital_id = h.id
             AND rs.month_year = ?
            WHERE h.id <> 0
              AND h.deleted_at IS NULL
              AND h.is_active = 1
        ";
        $params = [$monthYear];
        if ($hospitalId !== null) {
            $sql .= " AND h.id = ?";
            $params[] = $hospitalId;
        }

        $rows = $this->rows($sql, $params);
        $counts = ['APPROVED' => 0, 'SUBMITTED' => 0, 'DRAFT' => 0, 'WAITING' => 0];
        $waiting = [];

        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if ($status === 'APPROVED') {
                $counts['APPROVED']++;
            } elseif ($status === 'SUBMITTED') {
                $counts['SUBMITTED']++;
            } elseif (in_array($status, ['DRAFT', 'REQUEST_EDIT'], true)) {
                $counts['DRAFT']++;
            } else {
                $counts['WAITING']++;
                $waiting[] = (string)$row['name'];
            }
        }

        return [$counts, $waiting];
    }

    private function monthBounds(string $monthYear): array {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthYear)) {
            throw new InvalidArgumentException('Invalid dashboard month.');
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $monthYear . '-01');
        if (!$start) {
            throw new InvalidArgumentException('Invalid dashboard month.');
        }

        return [$start->format('Y-m-d'), $start->modify('first day of next month')->format('Y-m-d')];
    }

    private function scalar(string $sql, array $params = []): int {
        return (int)$this->scalarRaw($sql, $params);
    }

    private function scalarRaw(string $sql, array $params = []): mixed {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? 0 : $value;
    }

    private function rows(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
