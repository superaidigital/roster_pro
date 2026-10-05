<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/SecurityCompliance.php';

$options = getopt('', ['strict', 'min-score:', 'json', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/security_check.php [--strict] [--min-score=85] [--json]\n";
    exit(0);
}

$strict = isset($options['strict']) || security_is_production();
$minScore = max(0, min(100, (int)($options['min-score'] ?? (getenv('SECURITY_MIN_SCORE') ?: 85))));

$db = null;
$dbError = null;
try {
    $db = (new Database())->getConnectionOrThrow();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$assessment = SecurityCompliance::assess($db);
if ($dbError !== null) {
    $assessment['status'] = 'FAIL';
    $assessment['failures'] = (int)$assessment['failures'] + 1;
    $assessment['checks'][] = [
        'id' => 'database_connection',
        'label' => 'Database connection',
        'status' => 'FAIL',
        'weight' => 10,
        'detail' => 'Database connection failed.',
    ];
}

if (isset($options['json'])) {
    echo json_encode($assessment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo "Roster Pro Security Compliance\n";
    echo str_repeat('=', 34) . "\n";
    echo "Score: {$assessment['score']}/100  Grade: {$assessment['grade']}  Status: {$assessment['status']}\n\n";

    foreach ($assessment['checks'] as $check) {
        printf(
            "[%-4s] %-36s %s\n",
            (string)$check['status'],
            (string)$check['label'],
            (string)$check['detail']
        );
    }

    echo "\nFailures: {$assessment['failures']}  Warnings: {$assessment['warnings']}\n";
}

$failed = (int)$assessment['failures'] > 0;
$belowScore = (int)$assessment['score'] < $minScore;

if ($strict && ($failed || $belowScore)) {
    fwrite(STDERR, "SECURITY_CHECK_FAILED\n");
    if ($belowScore) {
        fwrite(STDERR, "Security score {$assessment['score']} is below required {$minScore}.\n");
    }
    exit(2);
}

echo "SECURITY_CHECK_OK\n";
exit(0);
