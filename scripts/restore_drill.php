<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/BackupVerifier.php';
require_once __DIR__ . '/../models/DisasterRecoveryDrillModel.php';

final class RecoveryDrillException extends RuntimeException {
    public string $drillCode;

    public function __construct(string $drillCode, string $message) {
        parent::__construct($message);
        $this->drillCode = $drillCode;
    }
}

$options = getopt('', ['file:', 'latest', 'label:', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php scripts/restore_drill.php --file=storage/backups/file.sql.gz\n";
    echo "  php scripts/restore_drill.php --latest [--label=scheduled]\n";
    exit(0);
}

$sourceDatabase = (string)(getenv('DB_NAME') ?: 'roster_pro_db');
$host = (string)(getenv('DB_HOST') ?: '127.0.0.1');
$port = (string)(getenv('DB_PORT') ?: '3306');
$adminUser = (string)(getenv('DR_DB_ADMIN_USER') ?: (getenv('DB_USER') ?: 'root'));
$adminPasswordRaw = getenv('DR_DB_ADMIN_PASSWORD');
$adminPassword = $adminPasswordRaw !== false
    ? (string)$adminPasswordRaw
    : (string)(getenv('DB_PASSWORD') ?: '');
$clientBin = trim((string)(getenv('DB_CLIENT_BIN') ?: 'mariadb'));

$maxRpoSeconds = max(60, min(2592000, (int)(getenv('DR_MAX_RPO_SECONDS') ?: 86400)));
$maxRtoSeconds = max(10, min(86400, (int)(getenv('DR_MAX_RTO_SECONDS') ?: 900)));

$criticalTablesRaw = trim((string)(getenv('DR_CRITICAL_TABLES')
    ?: 'users,hospitals,shifts,roster_status,system_settings,schema_migrations,disaster_recovery_drills'));
$criticalTables = array_values(array_filter(array_unique(array_map(
    static fn(string $value): string => trim($value),
    explode(',', $criticalTablesRaw)
)), static fn(string $value): bool => preg_match('/^[A-Za-z0-9_]{1,64}$/', $value) === 1));

$targetSuffix = date('YmdHis') . '_' . bin2hex(random_bytes(3));
$safeSource = preg_replace('/[^A-Za-z0-9_]+/', '_', $sourceDatabase) ?: 'source';
$targetDatabase = substr('dr_drill_' . $safeSource . '_' . $targetSuffix, 0, 64);

$startedAt = date('Y-m-d H:i:s');
$startedNano = hrtime(true);
$status = 'FAIL';
$failureCode = 'UNEXPECTED';
$failureMessage = null;
$backupFilename = '';
$backupSha = str_repeat('0', 64);
$backupCreatedAt = null;
$rpoSeconds = null;
$rtoMs = null;
$tablesVerified = 0;
$criticalVerified = 0;
$targetCreated = false;
$adminDb = null;

$file = '';
try {
    if (!empty($options['file'])) {
        $file = (string)$options['file'];
    } elseif (isset($options['latest'])) {
        $label = isset($options['label']) ? trim((string)$options['label']) : null;
        $latest = BackupVerifier::latest($label !== '' ? $label : null);
        if ($latest === null) {
            throw new RecoveryDrillException('BACKUP_NOT_FOUND', 'No matching backup is available.');
        }
        $file = $latest;
    } else {
        throw new RecoveryDrillException('BACKUP_REQUIRED', 'Specify --file or --latest.');
    }

    $verified = BackupVerifier::verify($file, true);
    $backupFilename = basename((string)$verified['file']);
    $backupSha = (string)$verified['sha256'];
    $manifest = $verified['manifest'];

    if ((int)($manifest['format_version'] ?? 0) < 2
        || (string)($manifest['restore_scope'] ?? '') !== 'database_contents'
        || (bool)($manifest['contains_database_ddl'] ?? true) !== false) {
        throw new RecoveryDrillException(
            'UNSAFE_BACKUP_FORMAT',
            'Restore drill requires backup format_version 2 database_contents without database DDL.'
        );
    }

    $manifestDatabase = (string)($manifest['database'] ?? '');
    if ($manifestDatabase === '' || $manifestDatabase !== $sourceDatabase) {
        throw new RecoveryDrillException(
            'SOURCE_MISMATCH',
            'Backup source database does not match DB_NAME.'
        );
    }

    BackupVerifier::assertRestoreSafeDump((string)$verified['file']);

    $backupCreatedRaw = (string)($manifest['created_at'] ?? '');
    $snapshotReferenceRaw = (string)($manifest['started_at'] ?? $backupCreatedRaw);
    if ($backupCreatedRaw === '' || $snapshotReferenceRaw === '') {
        throw new RecoveryDrillException('BACKUP_TIME_MISSING', 'Backup manifest timing is missing.');
    }

    try {
        $backupCreated = new DateTimeImmutable($backupCreatedRaw);
        $snapshotReference = new DateTimeImmutable($snapshotReferenceRaw);
    } catch (Throwable $e) {
        throw new RecoveryDrillException('BACKUP_TIME_INVALID', 'Backup manifest timing is invalid.');
    }

    $backupCreatedAt = $backupCreated->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('Y-m-d H:i:s');
    $rpoSeconds = max(0, time() - $snapshotReference->getTimestamp());

    if (!preg_match('/^dr_drill_[A-Za-z0-9_]+$/', $targetDatabase)
        || $targetDatabase === $sourceDatabase) {
        throw new RecoveryDrillException('TARGET_INVALID', 'Generated restore target is unsafe.');
    }

    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $adminDb = new PDO(
        $dsn,
        $adminUser,
        $adminPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $adminDb->exec(
        "CREATE DATABASE {$targetDatabase}
         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    $targetCreated = true;

    $previousMysqlPwd = getenv('MYSQL_PWD');
    putenv('MYSQL_PWD=' . $adminPassword);

    $command = [
        $clientBin,
        '--protocol=tcp',
        '--host=' . $host,
        '--port=' . $port,
        '--user=' . $adminUser,
        '--default-character-set=utf8mb4',
        $targetDatabase,
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($command, $descriptors, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        if ($previousMysqlPwd === false) putenv('MYSQL_PWD');
        else putenv('MYSQL_PWD=' . $previousMysqlPwd);
        throw new RecoveryDrillException('CLIENT_START_FAILED', 'Unable to start database restore client.');
    }

    $gz = gzopen((string)$verified['file'], 'rb');
    if ($gz === false) {
        proc_terminate($process);
        proc_close($process);
        if ($previousMysqlPwd === false) putenv('MYSQL_PWD');
        else putenv('MYSQL_PWD=' . $previousMysqlPwd);
        throw new RecoveryDrillException('BACKUP_OPEN_FAILED', 'Unable to open compressed backup.');
    }

    $streamFailed = false;
    while (!gzeof($gz)) {
        $chunk = gzread($gz, 1024 * 1024);
        if ($chunk === false) {
            $streamFailed = true;
            break;
        }
        if ($chunk !== '' && @fwrite($pipes[0], $chunk) === false) {
            $streamFailed = true;
            break;
        }
    }
    gzclose($gz);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($previousMysqlPwd === false) putenv('MYSQL_PWD');
    else putenv('MYSQL_PWD=' . $previousMysqlPwd);

    if ($streamFailed || $exitCode !== 0) {
        $safeError = trim((string)$stderr);
        if ($safeError === '') $safeError = 'Restore client exited with code ' . $exitCode;
        throw new RecoveryDrillException(
            'RESTORE_FAILED',
            mb_substr($safeError, 0, 900, 'UTF-8')
        );
    }

    $targetDsn = "mysql:host={$host};port={$port};dbname={$targetDatabase};charset=utf8mb4";
    $targetDb = new PDO(
        $targetDsn,
        $adminUser,
        $adminPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $tableStmt = $targetDb->prepare(
        "SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?
           AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    );
    $tableStmt->execute([$targetDatabase]);
    $restoredTables = array_map(
        static fn(array $row): string => (string)$row['TABLE_NAME'],
        $tableStmt->fetchAll(PDO::FETCH_ASSOC)
    );
    $tablesVerified = count($restoredTables);

    if ($tablesVerified === 0) {
        throw new RecoveryDrillException('EMPTY_RESTORE', 'Restore completed without any base tables.');
    }

    foreach ($criticalTables as $table) {
        if (!in_array($table, $restoredTables, true)) {
            throw new RecoveryDrillException(
                'CRITICAL_TABLE_MISSING',
                'Critical restored table is missing: ' . $table
            );
        }

        $targetDb->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $criticalVerified++;
    }

    $rtoMs = max(0, (int)round((hrtime(true) - $startedNano) / 1000000));

    if ($rpoSeconds > $maxRpoSeconds) {
        throw new RecoveryDrillException(
            'RPO_EXCEEDED',
            "Backup age {$rpoSeconds}s exceeds configured RPO {$maxRpoSeconds}s."
        );
    }

    if ($rtoMs > ($maxRtoSeconds * 1000)) {
        throw new RecoveryDrillException(
            'RTO_EXCEEDED',
            "Recovery time {$rtoMs}ms exceeds configured RTO " . ($maxRtoSeconds * 1000) . 'ms.'
        );
    }

    $status = 'PASS';
    $failureCode = null;
    $failureMessage = null;
} catch (RecoveryDrillException $e) {
    $failureCode = $e->drillCode;
    $failureMessage = $e->getMessage();
} catch (Throwable $e) {
    $failureCode = 'UNEXPECTED';
    $failureMessage = mb_substr($e->getMessage(), 0, 900, 'UTF-8');
} finally {
    if ($rtoMs === null) {
        $rtoMs = max(0, (int)round((hrtime(true) - $startedNano) / 1000000));
    }

    if ($targetCreated && $adminDb instanceof PDO) {
        try {
            if (!str_starts_with($targetDatabase, 'dr_drill_') || $targetDatabase === $sourceDatabase) {
                throw new RuntimeException('Unsafe cleanup target.');
            }
            $adminDb->exec("DROP DATABASE {$targetDatabase}");
        } catch (Throwable $cleanupError) {
            $status = 'FAIL';
            $failureCode = 'CLEANUP_FAILED';
            $failureMessage = mb_substr($cleanupError->getMessage(), 0, 900, 'UTF-8');
        }
    }
}

$completedAt = date('Y-m-d H:i:s');

try {
    $sourceDb = (new Database())->getConnectionOrThrow();
    $model = new DisasterRecoveryDrillModel($sourceDb);
    if ($model->tableExists()) {
        $model->record([
            'backup_filename' => $backupFilename !== '' ? $backupFilename : basename($file),
            'backup_sha256' => $backupSha,
            'backup_created_at' => $backupCreatedAt,
            'source_database' => $sourceDatabase,
            'restore_database' => $targetDatabase,
            'status' => $status,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'rpo_seconds' => $rpoSeconds,
            'rto_ms' => $rtoMs,
            'tables_verified' => $tablesVerified,
            'critical_tables_verified' => $criticalVerified,
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
        ]);
    }
} catch (Throwable $recordError) {
    if ($status === 'PASS') {
        $status = 'FAIL';
        $failureCode = 'RESULT_RECORD_FAILED';
        $failureMessage = 'Restore succeeded but the drill result could not be recorded.';
    }
}

$result = [
    'status' => $status,
    'backup' => $backupFilename !== '' ? $backupFilename : basename($file),
    'source_database' => $sourceDatabase,
    'restore_database' => $targetDatabase,
    'rpo_seconds' => $rpoSeconds,
    'rpo_target_seconds' => $maxRpoSeconds,
    'rto_ms' => $rtoMs,
    'rto_target_ms' => $maxRtoSeconds * 1000,
    'tables_verified' => $tablesVerified,
    'critical_tables_verified' => $criticalVerified,
    'failure_code' => $failureCode,
    'failure_message' => $failureMessage,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo $status === 'PASS' ? "RESTORE_DRILL_OK\n" : "RESTORE_DRILL_FAILED\n";

exit($status === 'PASS' ? 0 : 2);
