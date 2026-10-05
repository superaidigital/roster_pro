<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/DeploymentHealth.php';

try {
    $db = (new Database())->getConnectionOrThrow();
    $result = DeploymentHealth::check($db);

    echo json_encode([
        'status' => $result['status'],
        'checks' => $result['checks'],
        'migrations' => $result['migration_summary'],
            'reliability' => $result['reliability'] ?? null,
        'timestamp' => date(DATE_ATOM),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    exit(($result['status'] ?? '') === 'ok' ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, "Health check failed.\n");
    exit(2);
}
