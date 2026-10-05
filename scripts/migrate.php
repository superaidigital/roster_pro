<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/MigrationManager.php';

$options = getopt('', [
    'status',
    'dry-run',
    'baseline',
    'confirm:',
    'retry-failed',
    'help',
]);

if (isset($options['help'])) {
    echo <<<TXT
Roster Pro migration runner

Usage:
  php scripts/migrate.php --status
  php scripts/migrate.php --dry-run
  php scripts/migrate.php
  php scripts/migrate.php --retry-failed
  php scripts/migrate.php --baseline --confirm=SCHEMA-LOADED

Rules:
  --status        Show migration states without changing schema.
  --dry-run       Show pending/blocking migrations without executing them.
  --baseline      Mark all current migrations applied. Use only immediately
                  after loading database/schema.sql or after a manual schema audit.
  --retry-failed  Explicitly retry a failed/running migration after reconciliation.

TXT;
    exit(0);
}

$actor = trim((string)(getenv('DEPLOY_ACTOR') ?: ''));
if ($actor === '') {
    $user = function_exists('get_current_user') ? get_current_user() : 'cli';
    $host = gethostname() ?: 'unknown-host';
    $actor = $user . '@' . $host;
}
$actor = mb_substr($actor, 0, 190, 'UTF-8');

try {
    $db = (new Database())->getConnectionOrThrow();
    $manager = new MigrationManager($db);

    $printStatus = static function(MigrationManager $manager): array {
        $rows = $manager->status();
        echo str_pad('STATE', 29) . str_pad('MIGRATION', 54) . "APPLIED_AT
";
        echo str_repeat('-', 108) . "
";

        foreach ($rows as $row) {
            $state = strtoupper((string)$row['state']);
            $version = (string)$row['version'];
            $appliedAt = (string)($row['applied_at'] ?? '-');
            echo str_pad($state, 29) . str_pad($version, 54) . $appliedAt . "
";
            if (!empty($row['error_message'])) {
                echo "  ERROR: " . preg_replace('/\s+/', ' ', (string)$row['error_message']) . "
";
            }
        }

        $summary = $manager->summary();
        echo "
Summary: "
            . "applied={$summary['applied']} "
            . "pending={$summary['pending']} "
            . "failed={$summary['failed']} "
            . "running={$summary['running']} "
            . "checksum_mismatch={$summary['checksum_mismatch']} "
            . "orphaned={$summary['orphaned_applied_migration']}
";

        return $summary;
    };

    if (isset($options['status'])) {
        $summary = $printStatus($manager);
        exit($summary['blocking'] > 0 ? 2 : 0);
    }

    if (isset($options['dry-run'])) {
        $summary = $printStatus($manager);
        if ($summary['blocking'] > 0) {
            fwrite(STDERR, "
BLOCKED: resolve migration errors before deployment.
");
            exit(2);
        }
        echo $summary['pending'] > 0
            ? "
DRY RUN: {$summary['pending']} migration(s) would be applied.
"
            : "
DRY RUN: database migration state is current.
";
        exit(0);
    }

    if (isset($options['baseline'])) {
        if (($options['confirm'] ?? '') !== 'SCHEMA-LOADED') {
            fwrite(
                STDERR,
                "Baseline refused. Re-run with --baseline --confirm=SCHEMA-LOADED only after loading the current canonical schema or completing a manual schema audit.
"
            );
            exit(2);
        }

        $count = $manager->baselineAll($actor);
        echo "BASELINE OK: recorded {$count} migration(s) as applied.
";
        $printStatus($manager);
        exit(0);
    }

    $before = $manager->summary();
    if ($before['blocking'] > 0 && !isset($options['retry-failed'])) {
        $printStatus($manager);
        fwrite(STDERR, "
Migration blocked by failed/running/checksum/orphaned state.
");
        exit(2);
    }

    $applied = $manager->migrate(isset($options['retry-failed']), $actor);

    if (!$applied) {
        echo "No pending migrations.
";
    } else {
        foreach ($applied as $version) {
            echo "APPLIED {$version}
";
        }
    }

    $after = $printStatus($manager);
    exit($after['blocking'] > 0 || $after['pending'] > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Migration runner failed: {$e->getMessage()}
");
    exit(1);
}
