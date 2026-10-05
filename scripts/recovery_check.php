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

$maxRpoSeconds = max(60, min(2592000, (int)(getenv('DR_MAX_RPO_SECONDS') ?: 86400)));
$maxRtoSeconds = max(10, min(86400, (int)(getenv('DR_MAX_RTO_SECONDS') ?: 900)));
$maxDrillAgeDays = max(1, min(365, (int)(getenv('DR_MAX_DRILL_AGE_DAYS') ?: 7)));
$backupLabel = trim((string)(getenv('DR_BACKUP_LABEL') ?: 'scheduled'));

$errors = [];
$result = [
    'backup_status' => 'UNKNOWN',
    'drill_status' => 'UNKNOWN',
    'latest_backup' => null,
    'backup_age_seconds' => null,
    'latest_successful_drill_at' => null,
    'drill_age_hours' => null,
    'rpo_seconds' => null,
    'rto_ms' => null,
    'targets' => [
        'rpo_seconds' => $maxRpoSeconds,
        'rto_ms' => $maxRtoSeconds * 1000,
        'drill_age_days' => $maxDrillAgeDays,
    ],
];

try {
    $latest = BackupVerifier::latest($backupLabel !== '' ? $backupLabel : null);
    if ($latest === null) {
        $errors[] = 'No qualifying backup was found.';
        $result['backup_status'] = 'MISSING';
    } else {
        $verified = BackupVerifier::verify($latest, true);
        $manifest = $verified['manifest'];
        $result['latest_backup'] = basename($verified['file']);

        if ((int)($manifest['format_version'] ?? 0) < 2
            || (string)($manifest['restore_scope'] ?? '') !== 'database_contents') {
            $errors[] = 'Latest backup is not restore-safe format v2.';
            $result['backup_status'] = 'UNSAFE_FORMAT';
        } else {
            $referenceRaw = (string)($manifest['started_at'] ?? $manifest['created_at'] ?? '');
            if ($referenceRaw === '') {
                $errors[] = 'Latest backup has no timing metadata.';
                $result['backup_status'] = 'TIME_UNKNOWN';
            } else {
                $reference = new DateTimeImmutable($referenceRaw);
                $age = max(0, time() - $reference->getTimestamp());
                $result['backup_age_seconds'] = $age;
                if ($age > $maxRpoSeconds) {
                    $errors[] = "Backup age {$age}s exceeds RPO {$maxRpoSeconds}s.";
                    $result['backup_status'] = 'STALE';
                } else {
                    $result['backup_status'] = 'PASS';
                }
            }
        }
    }

    $db = (new Database())->getConnectionOrThrow();
    $model = new DisasterRecoveryDrillModel($db);

    if (!$model->tableExists()) {
        $errors[] = 'Disaster recovery drill registry is missing.';
        $result['drill_status'] = 'REGISTRY_MISSING';
    } else {
        $latestAny = $model->latest();
        $latestSuccess = $model->latestSuccessful();

        if (!is_array($latestSuccess)) {
            $errors[] = 'No successful restore drill has been recorded.';
            $result['drill_status'] = 'MISSING';
        } else {
            $completed = new DateTimeImmutable((string)$latestSuccess['completed_at']);
            $ageHours = max(0, (int)floor((time() - $completed->getTimestamp()) / 3600));
            $result['latest_successful_drill_at'] = (string)$latestSuccess['completed_at'];
            $result['drill_age_hours'] = $ageHours;
            $result['rpo_seconds'] = isset($latestSuccess['rpo_seconds']) ? (int)$latestSuccess['rpo_seconds'] : null;
            $result['rto_ms'] = isset($latestSuccess['rto_ms']) ? (int)$latestSuccess['rto_ms'] : null;

            if ($ageHours > ($maxDrillAgeDays * 24)) {
                $errors[] = "Last successful restore drill is older than {$maxDrillAgeDays} days.";
                $result['drill_status'] = 'STALE';
            } elseif ($result['rpo_seconds'] === null || $result['rpo_seconds'] > $maxRpoSeconds) {
                $errors[] = 'Last successful drill exceeded the configured RPO.';
                $result['drill_status'] = 'RPO_EXCEEDED';
            } elseif ($result['rto_ms'] === null || $result['rto_ms'] > ($maxRtoSeconds * 1000)) {
                $errors[] = 'Last successful drill exceeded the configured RTO.';
                $result['drill_status'] = 'RTO_EXCEEDED';
            } else {
                $result['drill_status'] = 'PASS';
            }
        }

        if (is_array($latestAny) && strtoupper((string)($latestAny['status'] ?? '')) !== 'PASS') {
            $errors[] = 'The most recent restore drill failed.';
            $result['drill_status'] = 'LATEST_FAILED';
        }
    }
} catch (Throwable $e) {
    $errors[] = mb_substr($e->getMessage(), 0, 500, 'UTF-8');
}

$result['status'] = $errors === [] ? 'PASS' : 'FAIL';
$result['errors'] = $errors;

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo $errors === [] ? "RECOVERY_CHECK_OK\n" : "RECOVERY_CHECK_FAILED\n";

exit($errors === [] ? 0 : 2);
