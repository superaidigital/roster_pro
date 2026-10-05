<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/MigrationManager.php';

function ok(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

function writeFixture(string $dir, array $files): void {
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create migration test directory.');
    }
    $manifest = [
        'schema_version' => 1,
        'migrations' => array_keys($files),
    ];
    file_put_contents($dir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    foreach ($files as $name => $sql) {
        file_put_contents($dir . '/' . $name, $sql);
    }
}

$db = (new Database())->getConnectionOrThrow();
$db->exec('DROP TABLE IF EXISTS schema_migrations');
$db->exec('DROP TABLE IF EXISTS migration_safety_fixture');

$tempRoot = sys_get_temp_dir() . '/roster_migration_test_' . bin2hex(random_bytes(5));
$successDir = $tempRoot . '/success';
$failureDir = $tempRoot . '/failure';

try {
    writeFixture($successDir, [
        '20990101_create_fixture.sql' => "
            CREATE TABLE migration_safety_fixture (
                id INT NOT NULL AUTO_INCREMENT,
                note VARCHAR(255) NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        '20990102_insert_semicolon.sql' => "
            INSERT INTO migration_safety_fixture (note)
            VALUES ('value;with;semicolons');
        ",
    ]);

    $manager = new MigrationManager($db, $successDir, 'roster_migration_unit_lock');
    $files = $manager->migrationFiles();
    ok(count($files) === 2, 'manifest order loads exactly two synthetic migrations');

    $statements = $manager->splitSqlStatements($files[1]['sql']);
    ok(count($statements) === 1, 'SQL parser does not split semicolons inside a quoted string');

    $applied = $manager->migrate(false, 'ci@test');
    ok($applied === ['20990101_create_fixture.sql', '20990102_insert_semicolon.sql'], 'synthetic migrations apply in manifest order');

    $value = $db->query("SELECT note FROM migration_safety_fixture LIMIT 1")->fetchColumn();
    ok($value === 'value;with;semicolons', 'synthetic migration data is correct');

    $summary = $manager->summary();
    ok($summary['applied'] === 2 && $summary['pending'] === 0 && $summary['blocking'] === 0, 'successful migration summary is healthy');

    file_put_contents(
        $successDir . '/20990101_create_fixture.sql',
        (string)file_get_contents($successDir . '/20990101_create_fixture.sql') . "\n-- changed after apply\n"
    );
    $tampered = new MigrationManager($db, $successDir, 'roster_migration_unit_lock');
    $tamperedSummary = $tampered->summary();
    ok($tamperedSummary['checksum_mismatch'] === 1, 'editing an applied migration triggers checksum mismatch');

    // Reset tracking/fixture to test a partially-applied DDL failure.
    $db->exec('DROP TABLE IF EXISTS schema_migrations');
    $db->exec('DROP TABLE IF EXISTS migration_safety_fixture');

    writeFixture($failureDir, [
        '20990201_create_fixture.sql' => "
            CREATE TABLE migration_safety_fixture (
                id INT NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ",
        '20990202_partial_failure.sql' => "
            ALTER TABLE migration_safety_fixture ADD COLUMN partial_value INT NULL;
            SELECT * FROM table_that_does_not_exist;
        ",
    ]);

    $failedManager = new MigrationManager($db, $failureDir, 'roster_migration_failure_lock');
    $failed = false;
    try {
        $failedManager->migrate(false, 'ci@test');
    } catch (RuntimeException $e) {
        $failed = str_contains($e->getMessage(), 'Migration failed');
    }
    ok($failed, 'migration runner stops on a failed migration');

    $columnStmt = $db->query("SHOW COLUMNS FROM migration_safety_fixture LIKE 'partial_value'");
    ok((bool)$columnStmt->fetch(PDO::FETCH_ASSOC), 'test proves DDL can remain partially applied after failure');

    $failedSummary = $failedManager->summary();
    ok($failedSummary['failed'] === 1 && $failedSummary['blocking'] >= 1, 'failed migration is recorded as a blocking state');

    $blocked = false;
    try {
        $failedManager->migrate(false, 'ci@test');
    } catch (RuntimeException $e) {
        $blocked = str_contains($e->getMessage(), 'is failed');
    }
    ok($blocked, 'subsequent deploy is blocked until explicit reconciliation/retry');

    echo "Migration manager safety test completed successfully.\n";
} finally {
    $db->exec('DROP TABLE IF EXISTS schema_migrations');
    $db->exec('DROP TABLE IF EXISTS migration_safety_fixture');

    $removeTree = static function(string $dir) use (&$removeTree): void {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    };
    $removeTree($tempRoot);
}
