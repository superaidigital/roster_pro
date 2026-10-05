<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/BackgroundJobModel.php';

try {
    $db = (new Database())->getConnectionOrThrow();
    $jobs = new BackgroundJobModel($db);
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));

    $scheduled = 0;
    $enqueue = static function(?int $id) use (&$scheduled): void {
        if ($id !== null) $scheduled++;
    };

    $enqueue($jobs->enqueue(
        'HEALTH_SNAPSHOT',
        [],
        3,
        20,
        null,
        'health:' . $now->format('YmdH')
    ));

    $enqueue($jobs->enqueue(
        'CLEANUP_NOTIFICATIONS',
        ['days' => max(7, (int)(getenv('NOTIFICATION_RETENTION_DAYS') ?: 30))],
        3,
        80,
        null,
        'notification-cleanup:' . $now->format('Ymd')
    ));

    $enqueue($jobs->enqueue(
        'BACKUP_RETENTION',
        [
            'backup_dir' => (string)(getenv('BACKUP_DIR') ?: 'storage/backups'),
            'retention_days' => max(1, (int)(getenv('BACKUP_RETENTION_DAYS') ?: 30)),
            'keep_minimum' => max(1, (int)(getenv('BACKUP_KEEP_MIN') ?: 5)),
        ],
        3,
        90,
        null,
        'backup-retention:' . $now->format('Ymd')
    ));

    $enqueue($jobs->enqueue(
        'OBSERVABILITY_RETENTION',
        [
            'event_days' => max(7, (int)(getenv('OBSERVABILITY_RETENTION_DAYS') ?: 90)),
            'job_days' => max(7, (int)(getenv('JOB_HISTORY_RETENTION_DAYS') ?: 30)),
        ],
        3,
        100,
        null,
        'observability-retention:' . $now->format('Ymd')
    ));

    echo "SCHEDULE_OK queued={$scheduled}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Scheduler failed: {$e->getMessage()}\n");
    exit(1);
}
