<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/BackgroundJobModel.php';
require_once __DIR__ . '/../models/NotificationModel.php';
require_once __DIR__ . '/../lib/ObservabilityService.php';
require_once __DIR__ . '/../lib/BackupRetention.php';
require_once __DIR__ . '/../lib/AppMonitor.php';
require_once __DIR__ . '/../lib/SimpleCache.php';

$options = getopt('', ['once', 'max-jobs:', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/job_worker.php [--once] [--max-jobs=20]\n";
    exit(0);
}

$maxJobs = isset($options['once'])
    ? 1
    : max(1, min(500, (int)($options['max-jobs'] ?? 20)));

try {
    $db = (new Database())->getConnectionOrThrow();
    $jobs = new BackgroundJobModel($db);
    $jobs->recoverStale((int)(getenv('JOB_STALE_SECONDS') ?: 600));

    $processed = 0;
    $failed = 0;

    for ($i = 0; $i < $maxJobs; $i++) {
        $job = $jobs->claimNext();
        if (!$job) {
            break;
        }

        $jobId = (int)$job['id'];
        $lockToken = (string)$job['lock_token'];
        $jobType = strtoupper((string)$job['job_type']);
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];

        try {
            switch ($jobType) {
                case 'IN_APP_NOTIFICATION':
                    $notification = new NotificationModel($db);
                    $ok = $notification->addNotification(
                        (int)($payload['user_id'] ?? 0),
                        (string)($payload['type'] ?? 'INFO'),
                        (string)($payload['title'] ?? ''),
                        (string)($payload['message'] ?? ''),
                        isset($payload['link']) ? (string)$payload['link'] : null
                    );
                    if (!$ok) {
                        throw new RuntimeException('Unable to persist in-app notification.');
                    }
                    break;

                case 'HEALTH_SNAPSHOT':
                    (new ObservabilityService($db))->captureHealthSnapshot();
                    break;

                case 'CLEANUP_NOTIFICATIONS':
                    $days = max(7, min(3650, (int)($payload['days'] ?? 30)));
                    (new NotificationModel($db))->deleteOldNotifications($days);
                    break;

                case 'BACKUP_RETENTION':
                    $backupDir = (string)($payload['backup_dir'] ?? (getenv('BACKUP_DIR') ?: 'storage/backups'));
                    $days = max(1, min(3650, (int)($payload['retention_days'] ?? 30)));
                    $keepMin = max(1, min(500, (int)($payload['keep_minimum'] ?? 5)));
                    BackupRetention::cleanup($backupDir, $days, $keepMin);
                    break;

                case 'OBSERVABILITY_RETENTION':
                    $eventDays = max(7, min(3650, (int)($payload['event_days'] ?? 90)));
                    $jobDays = max(7, min(3650, (int)($payload['job_days'] ?? 30)));
                    (new ObservabilityService($db))->cleanup($eventDays, $jobDays);
                    break;

                case 'CLEANUP_PERFORMANCE_CACHE':
                    $cacheDir = (string)($payload['cache_dir'] ?? (getenv('PERFORMANCE_CACHE_DIR') ?: 'storage/cache'));
                    (new SimpleCache($cacheDir))->clearExpired();
                    break;

                default:
                    throw new RuntimeException('Unsupported background job type: ' . $jobType);
            }

            if (!$jobs->complete($jobId, $lockToken)) {
                throw new RuntimeException('Unable to mark background job complete.');
            }

            $processed++;
            echo "DONE job={$jobId} type={$jobType}\n";
        } catch (Throwable $e) {
            $failed++;
            $nextStatus = $jobs->fail($jobId, $lockToken, $e->getMessage());
            AppMonitor::recordThrowable(
                $e,
                'BACKGROUND_JOB',
                [
                    'job_id' => $jobId,
                    'job_type' => $jobType,
                    'attempt' => (int)$job['attempts'],
                    'next_status' => $nextStatus,
                ]
            );
            fwrite(STDERR, "FAIL job={$jobId} type={$jobType} status={$nextStatus}\n");
        }
    }

    echo "WORKER_DONE processed={$processed} failed={$failed}\n";
    exit($failed > 0 ? 2 : 0);
} catch (Throwable $e) {
    AppMonitor::recordThrowable($e, 'BACKGROUND_WORKER');
    fwrite(STDERR, "Worker failed: {$e->getMessage()}\n");
    exit(1);
}
