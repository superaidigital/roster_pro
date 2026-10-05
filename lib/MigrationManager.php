<?php
declare(strict_types=1);

/**
 * Production-safe SQL migration manager for MariaDB/MySQL.
 *
 * MySQL/MariaDB DDL may auto-commit. Failed migrations therefore block
 * subsequent deploys until an operator explicitly reconciles and retries them.
 */
final class MigrationManager {
    private PDO $db;
    private string $migrationDir;
    private string $manifestPath;
    private string $lockName;

    public function __construct(
        PDO $db,
        ?string $migrationDir = null,
        string $lockName = 'roster_pro_schema_migrations'
    ) {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrationDir = $migrationDir ?: dirname(__DIR__) . '/database/migrations';
        $this->manifestPath = $this->migrationDir . '/manifest.json';
        $this->lockName = $lockName;
    }

    public function ensureTrackingTable(): void {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(190) NOT NULL,
                checksum CHAR(64) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'applied',
                batch INT UNSIGNED NOT NULL DEFAULT 1,
                applied_by VARCHAR(190) NULL,
                started_at DATETIME NULL,
                applied_at DATETIME NULL,
                execution_ms INT UNSIGNED NULL,
                error_message TEXT NULL,
                PRIMARY KEY (version),
                KEY idx_schema_migrations_status (status, applied_at),
                KEY idx_schema_migrations_batch (batch, applied_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function trackingTableExists(): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'schema_migrations'"
        );
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    public function migrationFiles(): array {
        if (!is_file($this->manifestPath)) {
            throw new RuntimeException('Missing database/migrations/manifest.json');
        }

        $manifest = json_decode((string)file_get_contents($this->manifestPath), true);
        if (!is_array($manifest) || !isset($manifest['migrations']) || !is_array($manifest['migrations'])) {
            throw new RuntimeException('Invalid migration manifest.');
        }

        $ordered = [];
        $seen = [];

        foreach ($manifest['migrations'] as $version) {
            $version = (string)$version;
            if (!preg_match('/^[0-9]{8}_[a-z0-9_]+\.sql$/', $version)) {
                throw new RuntimeException("Invalid migration filename in manifest: {$version}");
            }
            if (isset($seen[$version])) {
                throw new RuntimeException("Duplicate migration in manifest: {$version}");
            }

            $path = $this->migrationDir . '/' . $version;
            if (!is_file($path)) {
                throw new RuntimeException("Manifest references missing migration: {$version}");
            }

            $sql = (string)file_get_contents($path);
            if (stripos($sql, 'DELIMITER ') !== false) {
                throw new RuntimeException("Migration uses unsupported DELIMITER syntax: {$version}");
            }

            $ordered[] = [
                'version' => $version,
                'path' => $path,
                'checksum' => hash('sha256', $sql),
                'sql' => $sql,
            ];
            $seen[$version] = true;
        }

        foreach (glob($this->migrationDir . '/*.sql') ?: [] as $path) {
            $version = basename($path);
            if (!isset($seen[$version])) {
                throw new RuntimeException("Unlisted migration SQL file: {$version}");
            }
        }

        return $ordered;
    }

    public function status(): array {
        $files = $this->migrationFiles();
        $fileMap = [];
        foreach ($files as $file) {
            $fileMap[$file['version']] = true;
        }

        $rows = [];
        if ($this->trackingTableExists()) {
            $stmt = $this->db->query("SELECT * FROM schema_migrations ORDER BY batch ASC, version ASC");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[(string)$row['version']] = $row;
            }
        }

        $result = [];
        foreach ($files as $file) {
            $version = $file['version'];
            $row = $rows[$version] ?? null;

            if (!$row) {
                $state = 'pending';
            } elseif (!hash_equals((string)$row['checksum'], (string)$file['checksum'])) {
                $state = 'checksum_mismatch';
            } else {
                $state = strtolower((string)$row['status']);
                if (!in_array($state, ['running', 'applied', 'failed'], true)) {
                    $state = 'failed';
                }
            }

            $result[] = [
                'version' => $version,
                'checksum' => $file['checksum'],
                'state' => $state,
                'batch' => $row !== null ? (int)($row['batch'] ?? 0) : null,
                'applied_at' => $row['applied_at'] ?? null,
                'execution_ms' => $row !== null && $row['execution_ms'] !== null
                    ? (int)$row['execution_ms']
                    : null,
                'error_message' => $row['error_message'] ?? null,
            ];
        }

        foreach ($rows as $version => $row) {
            if (!isset($fileMap[$version])) {
                $result[] = [
                    'version' => $version,
                    'checksum' => (string)$row['checksum'],
                    'state' => 'orphaned_applied_migration',
                    'batch' => (int)($row['batch'] ?? 0),
                    'applied_at' => $row['applied_at'] ?? null,
                    'execution_ms' => $row['execution_ms'] !== null ? (int)$row['execution_ms'] : null,
                    'error_message' => 'Recorded migration is no longer present in the manifest.',
                ];
            }
        }

        return $result;
    }

    public function summary(): array {
        $counts = [
            'pending' => 0,
            'running' => 0,
            'applied' => 0,
            'failed' => 0,
            'checksum_mismatch' => 0,
            'orphaned_applied_migration' => 0,
        ];

        foreach ($this->status() as $row) {
            $state = (string)$row['state'];
            if (!isset($counts[$state])) {
                $counts[$state] = 0;
            }
            $counts[$state]++;
        }

        $counts['blocking'] = $counts['running']
            + $counts['failed']
            + $counts['checksum_mismatch']
            + $counts['orphaned_applied_migration'];

        return $counts;
    }

    public function baselineAll(string $actor): int {
        $files = $this->migrationFiles();
        $this->ensureTrackingTable();
        $this->acquireLock(10);

        try {
            $existing = (int)$this->db->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn();
            if ($existing > 0) {
                throw new RuntimeException('Baseline refused: schema_migrations is not empty.');
            }

            $stmt = $this->db->prepare(
                "INSERT INTO schema_migrations
                    (version, checksum, status, batch, applied_by, started_at, applied_at, execution_ms)
                 VALUES (?, ?, 'applied', 1, ?, NOW(), NOW(), 0)"
            );

            foreach ($files as $file) {
                $stmt->execute([$file['version'], $file['checksum'], $actor]);
            }

            return count($files);
        } finally {
            $this->releaseLock();
        }
    }

    public function migrate(bool $retryFailed, string $actor): array {
        $files = $this->migrationFiles();
        $this->ensureTrackingTable();
        $this->acquireLock(30);

        $appliedNow = [];
        try {
            $existingRows = $this->loadTrackingRows();
            $batch = (int)$this->db->query(
                "SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations"
            )->fetchColumn();

            foreach ($files as $file) {
                $version = $file['version'];
                $existing = $existingRows[$version] ?? null;

                if ($existing) {
                    if (!hash_equals((string)$existing['checksum'], (string)$file['checksum'])) {
                        throw new RuntimeException(
                            "Checksum mismatch for {$version}. Applied migration files are immutable."
                        );
                    }

                    $state = strtolower((string)$existing['status']);
                    if ($state === 'applied') {
                        continue;
                    }

                    if (in_array($state, ['failed', 'running'], true) && !$retryFailed) {
                        throw new RuntimeException(
                            "Migration {$version} is {$state}. Inspect the partial schema and rerun with --retry-failed only after reconciliation."
                        );
                    }
                }

                $this->markRunning($file, $batch, $actor);
                $started = hrtime(true);

                try {
                    foreach ($this->splitSqlStatements((string)$file['sql']) as $statement) {
                        $this->db->exec($statement);
                    }

                    $executionMs = max(0, (int)round((hrtime(true) - $started) / 1_000_000));
                    $this->markApplied($version, $executionMs);
                    $appliedNow[] = $version;
                } catch (Throwable $e) {
                    $executionMs = max(0, (int)round((hrtime(true) - $started) / 1_000_000));
                    $this->markFailed($version, $executionMs, $e->getMessage());
                    throw new RuntimeException(
                        "Migration failed: {$version}. DDL may be partially applied. " . $e->getMessage(),
                        0,
                        $e
                    );
                }

                $existingRows = $this->loadTrackingRows();
            }
        } finally {
            $this->releaseLock();
        }

        return $appliedNow;
    }

    private function loadTrackingRows(): array {
        $rows = [];
        $stmt = $this->db->query("SELECT * FROM schema_migrations ORDER BY version");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(string)$row['version']] = $row;
        }
        return $rows;
    }

    private function markRunning(array $file, int $batch, string $actor): void {
        $stmt = $this->db->prepare(
            "INSERT INTO schema_migrations
                (version, checksum, status, batch, applied_by, started_at, applied_at, execution_ms, error_message)
             VALUES (?, ?, 'running', ?, ?, NOW(), NULL, NULL, NULL)
             ON DUPLICATE KEY UPDATE
                checksum = VALUES(checksum),
                status = 'running',
                batch = VALUES(batch),
                applied_by = VALUES(applied_by),
                started_at = NOW(),
                applied_at = NULL,
                execution_ms = NULL,
                error_message = NULL"
        );
        $stmt->execute([$file['version'], $file['checksum'], $batch, $actor]);
    }

    private function markApplied(string $version, int $executionMs): void {
        $stmt = $this->db->prepare(
            "UPDATE schema_migrations
             SET status = 'applied',
                 applied_at = NOW(),
                 execution_ms = ?,
                 error_message = NULL
             WHERE version = ?"
        );
        $stmt->execute([$executionMs, $version]);
    }

    private function markFailed(string $version, int $executionMs, string $message): void {
        $safeMessage = mb_substr($message, 0, 4000, 'UTF-8');
        $stmt = $this->db->prepare(
            "UPDATE schema_migrations
             SET status = 'failed',
                 execution_ms = ?,
                 error_message = ?
             WHERE version = ?"
        );
        $stmt->execute([$executionMs, $safeMessage, $version]);
    }

    private function acquireLock(int $timeoutSeconds): void {
        $stmt = $this->db->prepare("SELECT GET_LOCK(?, ?)");
        $stmt->execute([$this->lockName, $timeoutSeconds]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Unable to acquire database migration lock.');
        }
    }

    private function releaseLock(): void {
        try {
            $stmt = $this->db->prepare("SELECT RELEASE_LOCK(?)");
            $stmt->execute([$this->lockName]);
        } catch (Throwable $e) {
            error_log('Unable to release migration lock: ' . $e->getMessage());
        }
    }

    /**
     * Split ordinary migration SQL while respecting quoted strings and comments.
     * Stored procedure DELIMITER syntax is intentionally unsupported.
     */
    public function splitSqlStatements(string $sql): array {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $length = strlen($sql);
        $buffer = '';
        $statements = [];
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $backtick = chr(96);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= "\n";
                }
                continue;
            }

            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                }
                continue;
            }

            if ($quote !== null) {
                $buffer .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }

                if ($char === $quote) {
                    if (($quote === "'" || $quote === '"') && $next === $quote) {
                        $buffer .= $next;
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2])))
                || $char === '#') {
                $lineComment = true;
                if ($char === '-') {
                    $i++;
                }
                continue;
            }

            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === $backtick) {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}
