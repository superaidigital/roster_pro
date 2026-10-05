<?php
declare(strict_types=1);

final class AppEventModel {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function record(array $event): int {
        $fingerprint = (string)($event['fingerprint'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
            throw new InvalidArgumentException('Invalid observability fingerprint.');
        }

        $severity = strtoupper((string)($event['severity'] ?? 'ERROR'));
        if (!in_array($severity, ['INFO', 'WARNING', 'ERROR', 'CRITICAL'], true)) {
            $severity = 'ERROR';
        }

        $category = strtoupper(trim((string)($event['category'] ?? 'APPLICATION')));
        $category = preg_replace('/[^A-Z0-9_.-]+/', '_', $category) ?: 'APPLICATION';

        $message = mb_substr(trim((string)($event['message'] ?? 'Unknown application event')), 0, 1000, 'UTF-8');
        $exceptionClass = trim((string)($event['exception_class'] ?? ''));
        $sourceFile = trim((string)($event['source_file'] ?? ''));
        $sourceLine = isset($event['source_line']) ? max(0, (int)$event['source_line']) : null;
        $route = trim((string)($event['route'] ?? ''));
        $requestId = trim((string)($event['request_id'] ?? ''));
        $userId = isset($event['user_id']) && (int)$event['user_id'] > 0 ? (int)$event['user_id'] : null;
        $hospitalId = isset($event['hospital_id']) && (int)$event['hospital_id'] > 0 ? (int)$event['hospital_id'] : null;

        $context = $event['context'] ?? [];
        if (!is_array($context)) {
            $context = ['value' => (string)$context];
        }
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($contextJson)) {
            $contextJson = '{}';
        }

        $stmt = $this->db->prepare(
            "INSERT INTO observability_events
                (fingerprint, severity, category, message, exception_class, source_file, source_line,
                 route, request_id, user_id, hospital_id, context_json, occurrence_count,
                 status, first_seen_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'OPEN', NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                severity = VALUES(severity),
                category = VALUES(category),
                message = VALUES(message),
                exception_class = VALUES(exception_class),
                source_file = VALUES(source_file),
                source_line = VALUES(source_line),
                route = VALUES(route),
                request_id = VALUES(request_id),
                user_id = VALUES(user_id),
                hospital_id = VALUES(hospital_id),
                context_json = VALUES(context_json),
                occurrence_count = occurrence_count + 1,
                status = 'OPEN',
                resolved_at = NULL,
                resolved_by = NULL,
                last_seen_at = NOW(),
                id = LAST_INSERT_ID(id)"
        );
        $stmt->execute([
            $fingerprint,
            $severity,
            mb_substr($category, 0, 50, 'UTF-8'),
            $message,
            $exceptionClass !== '' ? mb_substr($exceptionClass, 0, 190, 'UTF-8') : null,
            $sourceFile !== '' ? mb_substr($sourceFile, 0, 500, 'UTF-8') : null,
            $sourceLine,
            $route !== '' ? mb_substr($route, 0, 190, 'UTF-8') : null,
            $requestId !== '' ? mb_substr($requestId, 0, 64, 'UTF-8') : null,
            $userId,
            $hospitalId,
            $contextJson,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function resolve(int $eventId, int $resolvedBy): bool {
        $stmt = $this->db->prepare(
            "UPDATE observability_events
             SET status = 'RESOLVED', resolved_at = NOW(), resolved_by = ?
             WHERE id = ?"
        );
        $stmt->execute([$resolvedBy > 0 ? $resolvedBy : null, $eventId]);
        return $stmt->rowCount() > 0;
    }

    public function reopen(int $eventId): bool {
        $stmt = $this->db->prepare(
            "UPDATE observability_events
             SET status = 'OPEN', resolved_at = NULL, resolved_by = NULL
             WHERE id = ?"
        );
        $stmt->execute([$eventId]);
        return $stmt->rowCount() > 0;
    }

    public function getOpenEvents(int $limit = 100): array {
        $limit = max(1, min(500, $limit));
        $stmt = $this->db->prepare(
            "SELECT *
             FROM observability_events
             WHERE status = 'OPEN'
             ORDER BY
               CASE severity
                 WHEN 'CRITICAL' THEN 1
                 WHEN 'ERROR' THEN 2
                 WHEN 'WARNING' THEN 3
                 ELSE 4
               END,
               last_seen_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentEvents(int $limit = 50): array {
        $limit = max(1, min(500, $limit));
        $stmt = $this->db->prepare(
            "SELECT *
             FROM observability_events
             ORDER BY last_seen_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function summary(): array {
        $sql = "
            SELECT
                SUM(status = 'OPEN') AS open_total,
                SUM(status = 'OPEN' AND severity = 'CRITICAL') AS critical_open,
                SUM(status = 'OPEN' AND severity = 'ERROR') AS error_open,
                SUM(last_seen_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS events_24h,
                SUM(status = 'OPEN' AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS open_24h
            FROM observability_events
        ";
        $row = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'open_total' => (int)($row['open_total'] ?? 0),
            'critical_open' => (int)($row['critical_open'] ?? 0),
            'error_open' => (int)($row['error_open'] ?? 0),
            'events_24h' => (int)($row['events_24h'] ?? 0),
            'open_24h' => (int)($row['open_24h'] ?? 0),
        ];
    }

    public function deleteResolvedOlderThan(int $days): int {
        $days = max(7, min(3650, $days));
        $threshold = date('Y-m-d H:i:s', time() - ($days * 86400));
        $stmt = $this->db->prepare(
            "DELETE FROM observability_events
             WHERE status = 'RESOLVED'
               AND resolved_at < ?"
        );
        $stmt->execute([$threshold]);
        return $stmt->rowCount();
    }
}
