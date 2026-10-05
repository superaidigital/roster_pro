<?php
declare(strict_types=1);

final class BackgroundJobModel {
    private PDO $db;
    private string $claimLock = 'roster_pro_background_job_claim';

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function enqueue(
        string $jobType,
        array $payload = [],
        int $maxAttempts = 3,
        int $priority = 100,
        ?string $availableAt = null,
        ?string $dedupeKey = null
    ): ?int {
        $jobType = strtoupper(trim($jobType));
        if (!preg_match('/^[A-Z0-9_.-]{2,80}$/', $jobType)) {
            throw new InvalidArgumentException('Invalid background job type.');
        }

        $maxAttempts = max(1, min(20, $maxAttempts));
        $priority = max(-32768, min(32767, $priority));
        $availableAt = $availableAt ?: date('Y-m-d H:i:s');

        if ($dedupeKey !== null) {
            $dedupeKey = trim($dedupeKey);
            if ($dedupeKey === '' || mb_strlen($dedupeKey, 'UTF-8') > 190) {
                throw new InvalidArgumentException('Invalid background job dedupe key.');
            }
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payloadJson)) {
            throw new RuntimeException('Unable to encode background job payload.');
        }

        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO background_jobs
                (job_type, dedupe_key, payload_json, status, priority, attempts, max_attempts,
                 available_at, created_at, updated_at)
             VALUES (?, ?, ?, 'PENDING', ?, 0, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $jobType,
            $dedupeKey,
            $payloadJson,
            $priority,
            $maxAttempts,
            $availableAt,
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return (int)$this->db->lastInsertId();
    }

    public function recoverStale(int $olderThanSeconds = 600): int {
        $olderThanSeconds = max(60, min(86400, $olderThanSeconds));
        $threshold = date('Y-m-d H:i:s', time() - $olderThanSeconds);

        $stmt = $this->db->prepare(
            "UPDATE background_jobs
             SET status = CASE WHEN attempts >= max_attempts THEN 'FAILED' ELSE 'RETRY' END,
                 available_at = NOW(),
                 lock_token = NULL,
                 locked_at = NULL,
                 last_error = CONCAT(
                    COALESCE(last_error, ''),
                    CASE WHEN COALESCE(last_error, '') = '' THEN '' ELSE '\n' END,
                    'Recovered stale RUNNING job'
                 ),
                 updated_at = NOW()
             WHERE status = 'RUNNING'
               AND locked_at IS NOT NULL
               AND locked_at < ?"
        );
        $stmt->execute([$threshold]);
        return $stmt->rowCount();
    }

    public function claimNext(): ?array {
        $this->acquireClaimLock();

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->query(
                "SELECT *
                 FROM background_jobs
                 WHERE status IN ('PENDING', 'RETRY')
                   AND available_at <= NOW()
                   AND attempts < max_attempts
                 ORDER BY priority ASC, available_at ASC, id ASC
                 LIMIT 1
                 FOR UPDATE"
            );
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->db->commit();
                return null;
            }

            $lockToken = bin2hex(random_bytes(16));
            $update = $this->db->prepare(
                "UPDATE background_jobs
                 SET status = 'RUNNING',
                     attempts = attempts + 1,
                     lock_token = ?,
                     locked_at = NOW(),
                     updated_at = NOW()
                 WHERE id = ?
                   AND status IN ('PENDING', 'RETRY')"
            );
            $update->execute([$lockToken, (int)$job['id']]);

            if ($update->rowCount() !== 1) {
                $this->db->rollBack();
                return null;
            }

            $this->db->commit();

            $job['status'] = 'RUNNING';
            $job['attempts'] = (int)$job['attempts'] + 1;
            $job['lock_token'] = $lockToken;
            $payload = json_decode((string)$job['payload_json'], true);
            $job['payload'] = is_array($payload) ? $payload : [];

            return $job;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        } finally {
            $this->releaseClaimLock();
        }
    }

    public function complete(int $jobId, string $lockToken): bool {
        $stmt = $this->db->prepare(
            "UPDATE background_jobs
             SET status = 'DONE',
                 completed_at = NOW(),
                 lock_token = NULL,
                 locked_at = NULL,
                 last_error = NULL,
                 updated_at = NOW()
             WHERE id = ? AND status = 'RUNNING' AND lock_token = ?"
        );
        $stmt->execute([$jobId, $lockToken]);
        return $stmt->rowCount() === 1;
    }

    public function fail(int $jobId, string $lockToken, string $error): string {
        $stmt = $this->db->prepare(
            "SELECT attempts, max_attempts
             FROM background_jobs
             WHERE id = ? AND status = 'RUNNING' AND lock_token = ?
             LIMIT 1"
        );
        $stmt->execute([$jobId, $lockToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('Background job lock no longer matches.');
        }

        $attempts = (int)$row['attempts'];
        $maxAttempts = (int)$row['max_attempts'];
        $status = $attempts >= $maxAttempts ? 'FAILED' : 'RETRY';

        $backoffSeconds = min(3600, max(30, 30 * (2 ** max(0, $attempts - 1))));
        $availableAt = date('Y-m-d H:i:s', time() + $backoffSeconds);

        $update = $this->db->prepare(
            "UPDATE background_jobs
             SET status = ?,
                 available_at = ?,
                 lock_token = NULL,
                 locked_at = NULL,
                 last_error = ?,
                 updated_at = NOW()
             WHERE id = ? AND lock_token = ?"
        );
        $update->execute([
            $status,
            $availableAt,
            mb_substr($error, 0, 4000, 'UTF-8'),
            $jobId,
            $lockToken,
        ]);

        return $status;
    }

    public function retryFailed(int $jobId): bool {
        $stmt = $this->db->prepare(
            "UPDATE background_jobs
             SET status = 'RETRY',
                 attempts = 0,
                 available_at = NOW(),
                 locked_at = NULL,
                 lock_token = NULL,
                 last_error = NULL,
                 completed_at = NULL,
                 updated_at = NOW()
             WHERE id = ? AND status = 'FAILED'"
        );
        $stmt->execute([$jobId]);
        return $stmt->rowCount() === 1;
    }

    public function getFailedJobs(int $limit = 100): array {
        $limit = max(1, min(500, $limit));
        $stmt = $this->db->prepare(
            "SELECT *
             FROM background_jobs
             WHERE status = 'FAILED'
             ORDER BY updated_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentJobs(int $limit = 100): array {
        $limit = max(1, min(500, $limit));
        $stmt = $this->db->prepare(
            "SELECT *
             FROM background_jobs
             ORDER BY id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function summary(): array {
        $row = $this->db->query(
            "SELECT
                SUM(status IN ('PENDING', 'RETRY')) AS pending,
                SUM(status = 'RUNNING') AS running,
                SUM(status = 'FAILED') AS failed,
                SUM(status = 'DONE') AS done,
                SUM(status IN ('PENDING','RETRY') AND available_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)) AS delayed
             FROM background_jobs"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'pending' => (int)($row['pending'] ?? 0),
            'running' => (int)($row['running'] ?? 0),
            'failed' => (int)($row['failed'] ?? 0),
            'done' => (int)($row['done'] ?? 0),
            'delayed' => (int)($row['delayed'] ?? 0),
        ];
    }

    public function deleteCompletedOlderThan(int $days): int {
        $days = max(7, min(3650, $days));
        $threshold = date('Y-m-d H:i:s', time() - ($days * 86400));

        $stmt = $this->db->prepare(
            "DELETE FROM background_jobs
             WHERE status = 'DONE'
               AND completed_at IS NOT NULL
               AND completed_at < ?"
        );
        $stmt->execute([$threshold]);
        return $stmt->rowCount();
    }

    private function acquireClaimLock(): void {
        $stmt = $this->db->prepare("SELECT GET_LOCK(?, 5)");
        $stmt->execute([$this->claimLock]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Unable to acquire background job claim lock.');
        }
    }

    private function releaseClaimLock(): void {
        try {
            $stmt = $this->db->prepare("SELECT RELEASE_LOCK(?)");
            $stmt->execute([$this->claimLock]);
        } catch (Throwable $e) {
            error_log('Unable to release background job claim lock: ' . $e->getMessage());
        }
    }
}
