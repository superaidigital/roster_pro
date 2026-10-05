<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/MigrationManager.php';

$options = getopt('', ['strict', 'allow-pending', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/preflight.php [--strict] [--allow-pending]\n";
    exit(0);
}

$strict = isset($options['strict']);
$allowPending = isset($options['allow-pending']);
$root = dirname(__DIR__);
$fails = [];
$warnings = [];
$passes = [];

$pass = static function(string $message) use (&$passes): void {
    $passes[] = $message;
};
$warn = static function(string $message) use (&$warnings): void {
    $warnings[] = $message;
};
$fail = static function(string $message) use (&$fails): void {
    $fails[] = $message;
};

version_compare(PHP_VERSION, '8.2.0', '>=')
    ? $pass('PHP version >= 8.2')
    : $fail('PHP 8.2+ is required');

extension_loaded('pdo_mysql')
    ? $pass('pdo_mysql extension loaded')
    : $fail('pdo_mysql extension is required');

function_exists('gzopen')
    ? $pass('zlib/gzip support available')
    : $fail('zlib extension is required for compressed backups');

foreach (['storage', 'storage/backups'] as $relative) {
    $path = $root . '/' . $relative;
    if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
        $fail("Unable to create {$relative}");
        continue;
    }
    is_writable($path)
        ? $pass("{$relative} is writable")
        : $fail("{$relative} is not writable");
}

$realBackup = realpath($root . '/storage/backups');
$realPublic = realpath($root . '/public');
if ($realBackup !== false && $realPublic !== false
    && ($realBackup === $realPublic || str_starts_with($realBackup, $realPublic . DIRECTORY_SEPARATOR))) {
    $fail('Backup directory must not be inside public/');
} else {
    $pass('Backup directory is outside public/');
}

$freeBytes = @disk_free_space($root . '/storage');
if (is_float($freeBytes) || is_int($freeBytes)) {
    if ($freeBytes < 512 * 1024 * 1024) {
        $warn('Less than 512 MB free disk space is available');
    } else {
        $pass('At least 512 MB free disk space is available');
    }
}

$dumpCandidates = [];
$dumpOverride = trim((string)(getenv('DB_DUMP_BIN') ?: ''));
if ($dumpOverride !== '') {
    $dumpCandidates[] = $dumpOverride;
} else {
    $dumpCandidates = ['mariadb-dump', 'mysqldump'];
}

$dumpAvailable = false;
foreach ($dumpCandidates as $candidate) {
    $descriptors = [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open([$candidate, '--version'], $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        continue;
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit === 0) {
        $dumpAvailable = true;
        $pass('Database dump binary available: ' . basename($candidate));
        break;
    }
}
if (!$dumpAvailable) {
    $fail('mariadb-dump/mysqldump not found; set DB_DUMP_BIN if installed outside PATH');
}

$appEnv = strtolower(trim((string)(getenv('APP_ENV') ?: 'development')));
if ($appEnv === 'production') {
    $baseUrl = trim((string)(getenv('APP_BASE_URL') ?: ''));
    $baseParts = $baseUrl !== '' ? parse_url($baseUrl) : false;
    if (!$baseParts || strtolower((string)($baseParts['scheme'] ?? '')) !== 'https' || empty($baseParts['host'])) {
        $fail('Production requires APP_BASE_URL with an https:// URL for verification QR codes');
    } else {
        $pass('Production APP_BASE_URL uses HTTPS');
    }

    $cronKey = (string)(getenv('ROSTER_CRON_KEY') ?: '');
    if (strlen($cronKey) < 32 || str_contains(strtolower($cronKey), 'replace-with')) {
        $fail('Production ROSTER_CRON_KEY must be a non-placeholder secret of at least 32 characters');
    } else {
        $pass('ROSTER_CRON_KEY length is acceptable');
    }

    $dbUser = (string)(getenv('DB_USER') ?: 'root');
    $dbPasswordRaw = getenv('DB_PASSWORD');
    $dbPassword = $dbPasswordRaw !== false ? (string)$dbPasswordRaw : '';
    if (strtolower($dbUser) === 'root') {
        $fail('Production DB_USER must not be root; use a dedicated application account');
    } else {
        $pass('Production DB_USER is not root');
    }
    if ($dbPassword === '') {
        $fail('Production DB_PASSWORD must not be blank');
    } else {
        $pass('Production DB password is configured');
    }

    $trustProxy = filter_var(getenv('TRUST_PROXY_HEADERS') ?: '0', FILTER_VALIDATE_BOOLEAN);
    $trustedProxyIps = trim((string)(getenv('TRUSTED_PROXY_IPS') ?: ''));
    if ($trustProxy && $trustedProxyIps === '') {
        $fail('TRUST_PROXY_HEADERS=1 requires explicit TRUSTED_PROXY_IPS');
    } else {
        $pass('Production proxy trust configuration is valid');
    }

    $cspMode = strtolower(trim((string)(getenv('CSP_MODE') ?: 'report-only')));
    if (!in_array($cspMode, ['report-only', 'enforce'], true)) {
        $fail('Production CSP_MODE must be report-only or enforce');
    } else {
        $pass('Production CSP mode is configured: ' . $cspMode);
    }

    $idleTimeout = (int)(getenv('SESSION_IDLE_TIMEOUT_SECONDS') ?: 28800);
    $absoluteTimeout = (int)(getenv('SESSION_ABSOLUTE_TIMEOUT_SECONDS') ?: 43200);
    $regenInterval = (int)(getenv('SESSION_REGEN_INTERVAL_SECONDS') ?: 900);
    if ($idleTimeout <= 0 || $idleTimeout > 28800
        || $absoluteTimeout < $idleTimeout || $absoluteTimeout > 43200
        || $regenInterval <= 0 || $regenInterval > 1800) {
        $fail('Production session timeout configuration exceeds hardening limits');
    } else {
        $pass('Production session timeout configuration is within hardening limits');
    }
} else {
    $warn("APP_ENV={$appEnv}; production-only hardening checks are not enforced");
}

try {
    $db = (new Database())->getConnectionOrThrow();
    $db->query('SELECT 1')->fetchColumn();
    $pass('Database connection healthy');

    $requiredBaseTables = ['users', 'hospitals', 'pay_rates', 'system_menus'];
    $tableStmt = $db->prepare(
        "SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    foreach ($requiredBaseTables as $table) {
        $tableStmt->execute([$table]);
        if ((int)$tableStmt->fetchColumn() > 0) {
            $pass("Base table exists: {$table}");
        } else {
            $fail("Missing base table: {$table}. Load database/schema.sql for fresh install or run the legacy bridge before upgrade.");
        }
    }

    $manager = new MigrationManager($db);
    $files = $manager->migrationFiles();
    $pass('Migration manifest valid (' . count($files) . ' files)');

    $summary = $manager->summary();
    if ($summary['blocking'] > 0) {
        $fail(
            'Migration state is blocked: '
            . "failed={$summary['failed']}, running={$summary['running']}, "
            . "checksum_mismatch={$summary['checksum_mismatch']}, orphaned={$summary['orphaned_applied_migration']}"
        );
    } else {
        $pass('No failed/running/checksum/orphaned migration state');
    }

    if ($summary['pending'] > 0) {
        if ($allowPending) {
            $pass("{$summary['pending']} migration(s) pending and explicitly allowed for cutover");
        } else {
            $warn("{$summary['pending']} migration(s) are pending");
        }
    } else {
        $pass('No pending migrations');
    }
} catch (Throwable $e) {
    $fail('Database/preflight check failed: ' . $e->getMessage());
}

echo "Roster Pro Production Preflight\n";
echo str_repeat('=', 34) . "\n";
foreach ($passes as $message) {
    echo "[PASS] {$message}\n";
}
foreach ($warnings as $message) {
    echo "[WARN] {$message}\n";
}
foreach ($fails as $message) {
    echo "[FAIL] {$message}\n";
}

echo "\nResult: " . count($passes) . " pass, " . count($warnings) . " warning, " . count($fails) . " fail\n";

if ($fails) {
    exit(2);
}
if ($strict && $warnings) {
    exit(3);
}
exit(0);
