<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$warnings = [];

function addError(array &$errors, string $message): void {
    $errors[] = $message;
}

function phpFilesUnder(string $dir): array {
    if (!is_dir($dir)) return [];
    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

// 1) Repository hygiene: canonical MVC paths only.
foreach (glob($root . '/*.sql') ?: [] as $file) {
    addError($errors, 'Database dump must not be committed at repository root: ' . basename($file));
}

// Database/backup artifacts must never be web-accessible.
$publicDir = $root . '/public';
if (is_dir($publicDir)) {
    $publicIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($publicIterator as $publicFile) {
        if (!$publicFile->isFile()) continue;

        $name = strtolower($publicFile->getFilename());
        $extension = strtolower($publicFile->getExtension());

        if (
            $extension === 'sql'
            || str_ends_with($name, '.sql.gz')
            || str_ends_with($name, '.sql.zip')
            || preg_match('/(?:backup|dump).*(?:sql|gz|zip)$/i', $name)
        ) {
            addError(
                $errors,
                'Database/backup artifact must not be stored under public/: '
                . ltrim(str_replace($publicDir, '', $publicFile->getPathname()), DIRECTORY_SEPARATOR)
            );
        }
    }
}
foreach (glob($root . '/*Controller.php') ?: [] as $file) {
    addError($errors, 'Duplicate root controller detected: ' . basename($file));
}
foreach (glob($root . '/*Model.php') ?: [] as $file) {
    addError($errors, 'Duplicate root model detected: ' . basename($file));
}
if (is_file($root . '/database.php')) {
    addError($errors, 'Duplicate root database.php detected; use config/database.php');
}

foreach (phpFilesUnder($root . '/public') as $unusedPhpFile) {
    // PHP under public is checked elsewhere when applicable.
}
if (is_dir($root . '/public')) {
    $publicIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/public', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($publicIterator as $publicFile) {
        if ($publicFile->isFile() && strtolower($publicFile->getExtension()) === 'sql') {
            addError(
                $errors,
                'Database dump must not be committed under public web root: ' .
                ltrim(str_replace($root, '', $publicFile->getPathname()), DIRECTORY_SEPARATOR)
            );
        }
    }
}

// 2) Sanitized schema must never contain application data.
$schemaPath = $root . '/database/schema.sql';
if (!is_file($schemaPath)) {
    addError($errors, 'Missing database/schema.sql');
} else {
    $schema = (string) file_get_contents($schemaPath);
    if (preg_match('/\bINSERT\s+INTO\b/i', $schema)) {
        addError($errors, 'database/schema.sql must be schema-only (INSERT INTO found)');
    }
    if (preg_match('/\$2[aby]\$\d{2}\$/', $schema)) {
        addError($errors, 'database/schema.sql contains a password hash');
    }
}

// 3) Required literal includes from canonical controllers must resolve.
$controllerDir = $root . '/controllers';
$controllerFiles = phpFilesUnder($controllerDir);
$controllers = [];

foreach ($controllerFiles as $file) {
    $content = (string) file_get_contents($file);
    $base = basename($file, '.php');
    if (!str_ends_with($base, 'Controller')) continue;

    preg_match_all('/public\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $content, $methodMatches);
    $controllerName = strtolower(substr($base, 0, -10));
    $controllers[$controllerName] = array_fill_keys($methodMatches[1] ?? [], true);

    preg_match_all('/require_once\s+[\'"]([^\'"]+)[\'"]\s*;/', $content, $requireMatches);
    foreach ($requireMatches[1] ?? [] as $required) {
        if (str_contains($required, '$')) continue;
        $resolved = $root . '/' . ltrim($required, '/');
        if (!is_file($resolved)) {
            addError($errors, basename($file) . ' requires missing file: ' . $required);
        }
    }
}

// 4) Literal MVC routes in views/public JS must point to real controller actions.
$routeFiles = array_merge(
    phpFilesUnder($root . '/views'),
    glob($root . '/public/js/*.js') ?: []
);
$routePattern = '/index\.php\?[^\'"`s>]*?c=([A-Za-z0-9_]+)[^\'"`s>]*?(?:&|&amp;)a=([A-Za-z0-9_]+)/i';

foreach ($routeFiles as $file) {
    $content = (string) file_get_contents($file);
    if (!preg_match_all($routePattern, $content, $matches, PREG_SET_ORDER)) continue;

    foreach ($matches as $match) {
        $controller = strtolower($match[1]);
        $action = $match[2];

        if (!isset($controllers[$controller])) {
            addError($errors, basename($file) . " links to missing controller: {$controller}");
            continue;
        }
        if (!isset($controllers[$controller][$action])) {
            addError($errors, basename($file) . " links to missing action: {$controller}::{$action}()");
        }
    }
}

// 4.5) Runtime method contracts for non-inherited classes.
// PHP lint cannot detect calls to methods that were removed during a refactor.
foreach (array_merge(phpFilesUnder($root . '/controllers'), phpFilesUnder($root . '/models')) as $file) {
    $content = (string) file_get_contents($file);
    $rel = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);

    if (!preg_match('/class\s+[A-Za-z_][A-Za-z0-9_]*(?:\s+extends\s+[A-Za-z_][A-Za-z0-9_]*)?\s*\{/i', $content, $classMatch)) {
        continue;
    }

    // Skip inherited classes because a method may intentionally come from the parent.
    if (preg_match('/class\s+[A-Za-z_][A-Za-z0-9_]*\s+extends\s+/i', $classMatch[0])) {
        continue;
    }

    preg_match_all(
        '/(?:public|protected|private)\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i',
        $content,
        $definedMatches
    );
    $definedMethods = array_fill_keys($definedMatches[1] ?? [], true);

    preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $content, $calledMatches);
    foreach (array_unique($calledMatches[1] ?? []) as $calledMethod) {
        if (!isset($definedMethods[$calledMethod])) {
            addError($errors, $rel . ': calls undefined local method $this->' . $calledMethod . '()');
        }
    }
}

// 4.6) PWA manifest assets must resolve inside the repository.
$manifestPath = $root . '/manifest.json';
if (is_file($manifestPath)) {
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
        addError($errors, 'manifest.json is not valid JSON');
    } else {
        foreach (($manifest['icons'] ?? []) as $icon) {
            $src = (string)($icon['src'] ?? '');
            if ($src === '' || preg_match('#^https?://#i', $src)) {
                continue;
            }
            $assetPath = $root . '/' . ltrim(preg_replace('#^\./#', '', $src), '/');
            if (!is_file($assetPath)) {
                addError($errors, 'manifest.json references missing icon: ' . $src);
            }
        }
    }
}

// 5) Security/runtime regression patterns.
$scanFiles = array_merge(
    phpFilesUnder($root . '/controllers'),
    phpFilesUnder($root . '/models'),
    phpFilesUnder($root . '/views')
);
$scanFiles[] = $root . '/index.php';

foreach ($scanFiles as $file) {
    if (!is_file($file)) continue;
    $content = (string) file_get_contents($file);
    $rel = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);

    $forbidden = [
        '/CURLOPT_SSL_VERIFYPEER\s*,\s*false/i' => 'TLS peer verification disabled',
        '/CURLOPT_SSL_VERIFYHOST\s*,\s*false/i' => 'TLS host verification disabled',
        '/\broster_details\b/i' => 'legacy roster_details table reference',
        '/\brequest_user_id\b/i' => 'legacy request_user_id column reference',
        '/\?>\s*public\s+function\b/s' => 'PHP class method appears after closing PHP tag',
        '/->exec\s*\(\s*[\'\"]\s*(?:ALTER|CREATE|DROP)\s+TABLE\b/i' => 'runtime schema DDL found in controller/model; use migrations instead',
    ];

    foreach ($forbidden as $pattern => $description) {
        if (preg_match($pattern, $content)) {
            addError($errors, $rel . ': ' . $description);
        }
    }

    if (preg_match('/<button\b[^>]*>(?:(?!<\/button>)[\s\S]){0,600}<\/a>/i', $content)) {
        addError($errors, $rel . ': malformed HTML (button closed with </a>)');
    }
    if (preg_match('/<a\b[^>]*>(?:(?!<\/a>)[\s\S]){0,600}<\/button>/i', $content)) {
        addError($errors, $rel . ': malformed HTML (anchor closed with </button>)');
    }


    if (
        str_starts_with($rel, 'controllers' . DIRECTORY_SEPARATOR)
        && (
            preg_match('/[\'"]password[\'"]\s*=>\s*[\'"](?:123456|password|admin)[\'"]/i', $content)
            || preg_match('/\?\?\s*[\'"](?:123456|password|admin)[\'"]/i', $content)
        )
    ) {
        addError($errors, $rel . ': predictable default password detected');
    }

    if (
        preg_match('/json_encode\s*\(\s*\[[^\]]*\$e->getMessage\s*\(\)[^\]]*\]\s*\)/is', $content)
        || preg_match('/[\'"](?:message|error)[\'"]\s*=>\s*\$e->getMessage\s*\(\)/i', $content)
    ) {
        $warnings[] = $rel . ': exception message may be exposed in JSON response';
    }
}

// 5.5) Authorization scope regression guards for personnel/profile access.
$staffControllerPath = $root . '/controllers/StaffController.php';
if (is_file($staffControllerPath)) {
    $staffController = (string) file_get_contents($staffControllerPath);
    if (substr_count($staffController, '$this->canManageTargetUser(') < 7) {
        addError($errors, 'controllers/StaffController.php: personnel mutations must enforce target authorization scope');
    }
    if (!preg_match('/targetHospitalId\s*!==\s*\$currentHospitalId/', $staffController)) {
        addError($errors, 'controllers/StaffController.php: missing same-hospital authorization boundary');
    }
}

$profileControllerPath = $root . '/controllers/ProfileController.php';
if (is_file($profileControllerPath)) {
    $profileController = (string) file_get_contents($profileControllerPath);
    if (!preg_match('/currentRole\s*!==\s*[\'"]DIRECTOR[\'"]/', $profileController)
        || !preg_match('/targetHospitalId[^;]{0,240}hospital_id/s', $profileController)) {
        addError($errors, 'controllers/ProfileController.php: director profile access must be scoped to own hospital');
    }
}

// 5.6) Structured roster audit trail must remain wired into roster mutations.
$auditModelPath = $root . '/models/RosterAuditModel.php';
if (!is_file($auditModelPath)) {
    addError($errors, 'models/RosterAuditModel.php: structured roster audit model must remain available');
}

$ajaxAuditPath = $root . '/controllers/AjaxController.php';
if (is_file($ajaxAuditPath)) {
    $ajaxAudit = (string) file_get_contents($ajaxAuditPath);
    foreach (['SHIFT_SET', 'SHIFT_DELETE', 'ROSTER_COPY_PREVIOUS', 'ROSTER_AUTO_SCHEDULE', 'ROSTER_STATUS_CHANGE'] as $requiredAuditAction) {
        if (strpos($ajaxAudit, "'{$requiredAuditAction}'") === false) {
            addError($errors, "controllers/AjaxController.php: missing roster audit action {$requiredAuditAction}");
        }
    }
}

$rosterAuditPath = $root . '/controllers/RosterController.php';
if (is_file($rosterAuditPath)) {
    $rosterAudit = (string) file_get_contents($rosterAuditPath);
    foreach (['ROSTER_CLEAR', 'ROSTER_RANDOMIZE', 'ROSTER_RESTORE', 'SNAPSHOT_CREATE'] as $requiredAuditAction) {
        if (strpos($rosterAudit, "'{$requiredAuditAction}'") === false) {
            addError($errors, "controllers/RosterController.php: missing roster audit action {$requiredAuditAction}");
        }
    }
}

// 5.7) Approved roster revisions are append-only official records.
$revisionModelPath = $root . '/models/RosterRevisionModel.php';
if (!is_file($revisionModelPath)) {
    addError($errors, 'models/RosterRevisionModel.php: immutable approved roster revision model must remain available');
} else {
    $revisionModel = (string) file_get_contents($revisionModelPath);
    foreach (['createApprovedRevision', 'verifyRevision', 'revision_code', 'content_hash'] as $requiredRevisionToken) {
        if (strpos($revisionModel, $requiredRevisionToken) === false) {
            addError($errors, "models/RosterRevisionModel.php: missing immutable revision token {$requiredRevisionToken}");
        }
    }
    if (preg_match('/public\\s+function\\s+(?:delete|update)\\s*\\(/i', $revisionModel)) {
        addError($errors, 'models/RosterRevisionModel.php: official revisions must not expose update/delete methods');
    }
}

$ajaxRevisionPath = $root . '/controllers/AjaxController.php';
if (is_file($ajaxRevisionPath)) {
    $ajaxRevision = (string) file_get_contents($ajaxRevisionPath);
    if (strpos($ajaxRevision, 'createApprovedRevision(') === false
        || strpos($ajaxRevision, 'ElectronicSignature::isValid') === false) {
        addError($errors, 'controllers/AjaxController.php: approval must validate a signature and create an official revision');
    }
}

$officialExportPath = $root . '/views/roster/export_revision_word.php';
if (!is_file($officialExportPath)) {
    addError($errors, 'views/roster/export_revision_word.php: immutable official roster export must remain available');
}

// 5.8) Public document verification must stay narrow and privacy-minimized.
$frontControllerPath = $root . '/index.php';
if (is_file($frontControllerPath)) {
    $frontController = (string) file_get_contents($frontControllerPath);
    if (strpos($frontController, "\$publicVerifyActions = ['index', 'revision'];") === false) {
        addError($errors, 'index.php: Public verification route must remain narrowly allowlisted');
    }
}

$verifyControllerPath = $root . '/controllers/VerifyController.php';
$verifyViewPath = $root . '/views/verify/revision.php';
if (!is_file($verifyControllerPath) || !is_file($verifyViewPath)) {
    addError($errors, 'Public roster verification controller/view must remain available');
} else {
    $verifyController = (string) file_get_contents($verifyControllerPath);
    $verifyView = (string) file_get_contents($verifyViewPath);

    if (strpos($verifyController, 'getPublicVerification(') === false) {
        addError($errors, 'controllers/VerifyController.php: must use privacy-minimized public verification lookup');
    }

    foreach (['staff_json', 'shifts_json', 'pay_summary_json', 'prepared_signature', 'reviewed_signature', 'approved_signature'] as $sensitiveToken) {
        if (strpos($verifyView, $sensitiveToken) !== false) {
            addError($errors, "views/verify/revision.php: public verification leaks sensitive token {$sensitiveToken}");
        }
    }
}

$revisionVerifyModelPath = $root . '/models/RosterRevisionModel.php';
if (is_file($revisionVerifyModelPath)) {
    $revisionVerifyModel = (string) file_get_contents($revisionVerifyModelPath);
    foreach (['verificationCodeForHash', 'getPublicVerification', 'verification_code_valid'] as $token) {
        if (strpos($revisionVerifyModel, $token) === false) {
            addError($errors, "models/RosterRevisionModel.php: missing public verification integrity token {$token}");
        }
    }
}

$revisionExportPath = $root . '/views/roster/export_revision_word.php';
if (is_file($revisionExportPath)) {
    $revisionExport = (string) file_get_contents($revisionExportPath);
    if (strpos($revisionExport, '$qrImageUrl') === false
        || strpos($revisionExport, "revision['verification_code']") === false) {
        addError($errors, 'views/roster/export_revision_word.php: official export must include QR verification and readable code');
    }
}

// 5.9) Production deployment and migration safety guards.
$deploymentFiles = [
    'lib/MigrationManager.php',
    'lib/DeploymentHealth.php',
    'scripts/migrate.php',
    'scripts/preflight.php',
    'scripts/backup_database.php',
    'scripts/verify_backup.php',
    'scripts/deploy_database.php',
    'scripts/health_check.php',
    'controllers/HealthController.php',
    'database/migrations/manifest.json',
];
foreach ($deploymentFiles as $relativePath) {
    if (!is_file($root . '/' . $relativePath)) {
        addError($errors, 'Missing deployment safety file: ' . $relativePath);
    }
}

$migrationManifestPath = $root . '/database/migrations/manifest.json';
if (is_file($migrationManifestPath)) {
    $manifest = json_decode((string) file_get_contents($migrationManifestPath), true);
    $listedMigrations = is_array($manifest['migrations'] ?? null)
        ? array_values($manifest['migrations'])
        : [];

    if (!$listedMigrations) {
        addError($errors, 'database/migrations/manifest.json has no migrations');
    } else {
        if (count($listedMigrations) !== count(array_unique($listedMigrations))) {
            addError($errors, 'database/migrations/manifest.json contains duplicate migrations');
        }

        $diskMigrations = array_map('basename', glob($root . '/database/migrations/*.sql') ?: []);
        sort($diskMigrations);
        $manifestSet = $listedMigrations;
        sort($manifestSet);

        if ($diskMigrations !== $manifestSet) {
            addError($errors, 'Migration manifest must list every .sql migration exactly once');
        }

        $position = array_flip($listedMigrations);
        $dependencyPairs = [
            ['20261004_field_visits.sql', '20261004_field_followup.sql'],
            ['20261005_roster_revisions.sql', '20261005_roster_revision_verification.sql'],
        ];
        foreach ($dependencyPairs as [$before, $after]) {
            if (!isset($position[$before], $position[$after]) || $position[$before] >= $position[$after]) {
                addError($errors, "Migration dependency order invalid: {$before} must precede {$after}");
            }
        }
    }
}

$revisionMigrationPath = $root . '/database/migrations/20261005_roster_revisions.sql';
if (is_file($revisionMigrationPath)) {
    $revisionMigration = (string) file_get_contents($revisionMigrationPath);
    if (strpos($revisionMigration, 'holidays_json') === false) {
        addError($errors, 'Roster revision migration must include holidays_json to match the canonical schema');
    }
}

$schemaForDeployPath = $root . '/database/schema.sql';
if (is_file($schemaForDeployPath)) {
    $schemaForDeploy = (string) file_get_contents($schemaForDeployPath);
    if (strpos($schemaForDeploy, 'CREATE TABLE `schema_migrations`') === false) {
        addError($errors, 'database/schema.sql must include schema_migrations for fresh-install baseline tracking');
    }
}

foreach ([
    'scripts/migrate.php',
    'scripts/preflight.php',
    'scripts/backup_database.php',
    'scripts/verify_backup.php',
    'scripts/deploy_database.php',
    'scripts/health_check.php',
] as $cliScript) {
    $fullPath = $root . '/' . $cliScript;
    if (is_file($fullPath)) {
        $cliContent = (string) file_get_contents($fullPath);
        if (strpos($cliContent, "PHP_SAPI !== 'cli'") === false) {
            addError($errors, "{$cliScript}: deployment tooling must remain CLI-only");
        }
    }
}

if (is_file($frontControllerPath)) {
    $frontController = (string) file_get_contents($frontControllerPath);
    if (strpos($frontController, "\$publicHealthActions = ['index', 'live', 'ready'];") === false
        || strpos($frontController, '$isHealthRoute') === false) {
        addError($errors, 'index.php: public health endpoints must remain limited to index/live/ready');
    }
}

$healthControllerPath = $root . '/controllers/HealthController.php';
if (is_file($healthControllerPath)) {
    $healthController = (string) file_get_contents($healthControllerPath);
    if (strpos($healthController, 'DeploymentHealth::check') === false
        || strpos($healthController, "'status'") === false) {
        addError($errors, 'controllers/HealthController.php: health endpoint must use the minimal deployment health service');
    }
}

$backupViewPath = $root . '/views/settings/backup.php';
if (is_file($backupViewPath)) {
    $backupView = (string) file_get_contents($backupViewPath);
    if (stripos($backupView, 'public/uploads/Backup') !== false) {
        addError($errors, 'views/settings/backup.php: backup storage must not point under public/');
    }
}

// 5.10) Production observability and retry reliability guards.
$observabilityFiles = [
    'lib/AppMonitor.php',
    'lib/ObservabilityService.php',
    'lib/BackupRetention.php',
    'models/AppEventModel.php',
    'models/BackgroundJobModel.php',
    'controllers/ObservabilityController.php',
    'views/observability/index.php',
    'scripts/job_worker.php',
    'scripts/schedule_jobs.php',
    'database/migrations/20261005_observability_reliability.sql',
];
foreach ($observabilityFiles as $relativePath) {
    if (!is_file($root . '/' . $relativePath)) {
        addError($errors, 'Missing observability/reliability file: ' . $relativePath);
    }
}

$frontMonitorPath = $root . '/index.php';
if (is_file($frontMonitorPath)) {
    $frontMonitor = (string) file_get_contents($frontMonitorPath);
    if (strpos($frontMonitor, "require_once 'lib/AppMonitor.php';") === false
        || strpos($frontMonitor, 'AppMonitor::register();') === false) {
        addError($errors, 'index.php: global AppMonitor must remain registered');
    }
}

$appMonitorPath = $root . '/lib/AppMonitor.php';
if (is_file($appMonitorPath)) {
    $appMonitor = (string) file_get_contents($appMonitorPath);
    foreach (['set_exception_handler', 'register_shutdown_function', '[REDACTED]', 'fingerprint'] as $token) {
        if (strpos($appMonitor, $token) === false) {
            addError($errors, "lib/AppMonitor.php: missing monitoring safety token {$token}");
        }
    }
}

$backgroundJobPath = $root . '/models/BackgroundJobModel.php';
if (is_file($backgroundJobPath)) {
    $backgroundJob = (string) file_get_contents($backgroundJobPath);
    foreach (['GET_LOCK', 'claimNext', 'retryFailed', 'recoverStale', 'max_attempts'] as $token) {
        if (strpos($backgroundJob, $token) === false) {
            addError($errors, "models/BackgroundJobModel.php: missing durable queue token {$token}");
        }
    }
}

$notificationModelPath = $root . '/models/NotificationModel.php';
if (is_file($notificationModelPath)) {
    $notificationModel = (string) file_get_contents($notificationModelPath);
    if (strpos($notificationModel, 'queueNotification(') === false
        || strpos($notificationModel, "'IN_APP_NOTIFICATION'") === false) {
        addError($errors, 'models/NotificationModel.php: retryable notification queue API must remain available');
    }
}

$observabilityControllerPath = $root . '/controllers/ObservabilityController.php';
if (is_file($observabilityControllerPath)) {
    $observabilityController = (string) file_get_contents($observabilityControllerPath);
    if (strpos($observabilityController, "['ADMIN', 'SUPERADMIN']") === false
        || strpos($observabilityController, 'security_is_valid_post_csrf()') === false) {
        addError($errors, 'controllers/ObservabilityController.php: admin authorization and CSRF protection are required');
    }
}

$observabilityViewPath = $root . '/views/observability/index.php';
if (is_file($observabilityViewPath)) {
    $observabilityView = (string) file_get_contents($observabilityViewPath);
    foreach (['payload_json', 'context_json'] as $sensitiveRawField) {
        if (strpos($observabilityView, $sensitiveRawField) !== false) {
            addError($errors, "views/observability/index.php: raw sensitive field must not be rendered: {$sensitiveRawField}");
        }
    }
}

foreach (['scripts/job_worker.php', 'scripts/schedule_jobs.php'] as $cliScript) {
    $fullPath = $root . '/' . $cliScript;
    if (is_file($fullPath)) {
        $cliContent = (string) file_get_contents($fullPath);
        if (strpos($cliContent, "PHP_SAPI !== 'cli'") === false) {
            addError($errors, "{$cliScript}: reliability worker tooling must remain CLI-only");
        }
    }
}

// 5.11) Performance and scalability guards.
$performanceFiles = [
    'lib/SimpleCache.php',
    'lib/PerformanceMonitor.php',
    'models/DashboardMetricsModel.php',
    'scripts/performance_check.php',
    'database/migrations/20261005_performance_scalability.sql',
];
foreach ($performanceFiles as $relativePath) {
    if (!is_file($root . '/' . $relativePath)) {
        addError($errors, 'Missing performance/scalability file: ' . $relativePath);
    }
}

$dashboardControllerPath = $root . '/controllers/DashboardController.php';
if (is_file($dashboardControllerPath)) {
    $dashboardController = (string) file_get_contents($dashboardControllerPath);
    foreach ([
        'DashboardMetricsModel',
        'SimpleCache',
        'X-Dashboard-Cache',
        'Server-Timing',
    ] as $token) {
        if (strpos($dashboardController, $token) === false) {
            addError($errors, "controllers/DashboardController.php: missing performance token {$token}");
        }
    }
}

$dashboardMetricsPath = $root . '/models/DashboardMetricsModel.php';
if (is_file($dashboardMetricsPath)) {
    $dashboardMetrics = (string) file_get_contents($dashboardMetricsPath);

    if (preg_match('/DATE\s*\(\s*l\.created_at\s*\)/i', $dashboardMetrics)) {
        addError($errors, 'models/DashboardMetricsModel.php: DATE(logs.created_at) blocks range-index usage');
    }
    if (preg_match('/shift_date\s+LIKE/i', $dashboardMetrics)) {
        addError($errors, 'models/DashboardMetricsModel.php: shift month queries must use date ranges, not LIKE');
    }
    if (strpos($dashboardMetrics, 'COALESCE(SUM(') === false) {
        addError($errors, 'models/DashboardMetricsModel.php: budget aggregation must remain in SQL');
    }
}

$logsControllerPath = $root . '/controllers/LogsController.php';
if (is_file($logsControllerPath)) {
    $logsController = (string) file_get_contents($logsControllerPath);
    if (preg_match('/DATE\s*\(\s*l\.created_at\s*\)/i', $logsController)) {
        addError($errors, 'controllers/LogsController.php: log date filter must remain index-friendly');
    }
    if (strpos($logsController, 'l.created_at >= :date_start') === false
        || strpos($logsController, 'l.created_at < :date_end') === false) {
        addError($errors, 'controllers/LogsController.php: missing bounded created_at date range');
    }
}

$frontPerformancePath = $root . '/index.php';
if (is_file($frontPerformancePath)) {
    $frontPerformance = (string) file_get_contents($frontPerformancePath);
    if (strpos($frontPerformance, "require_once 'lib/PerformanceMonitor.php';") === false
        || strpos($frontPerformance, 'PerformanceMonitor::register();') === false) {
        addError($errors, 'index.php: slow request performance monitor must remain registered');
    }
}

$simpleCachePath = $root . '/lib/SimpleCache.php';
if (is_file($simpleCachePath)) {
    $simpleCache = (string) file_get_contents($simpleCachePath);
    foreach (['flock', 'rename', 'public/', 'expires_at'] as $token) {
        if (strpos($simpleCache, $token) === false) {
            addError($errors, "lib/SimpleCache.php: missing cache safety token {$token}");
        }
    }
}

// 5.12) Disaster recovery and business continuity guards.
$drFiles = [
    'lib/BackupVerifier.php',
    'models/DisasterRecoveryDrillModel.php',
    'scripts/backup_if_due.php',
    'scripts/restore_drill.php',
    'scripts/recovery_check.php',
    'database/migrations/20261005_disaster_recovery.sql',
    '.github/workflows/disaster-recovery.yml',
    'docs/DISASTER_RECOVERY.md',
];
foreach ($drFiles as $relativePath) {
    if (!is_file($root . '/' . $relativePath)) {
        addError($errors, 'Missing disaster recovery file: ' . $relativePath);
    }
}

$backupScriptPath = $root . '/scripts/backup_database.php';
if (is_file($backupScriptPath)) {
    $backupScript = (string) file_get_contents($backupScriptPath);
    foreach ([
        "'format_version' => 2",
        "'restore_scope' => 'database_contents'",
        "'contains_database_ddl' => false",
        "'--no-tablespaces'",
    ] as $token) {
        if (strpos($backupScript, $token) === false) {
            addError($errors, "scripts/backup_database.php: missing restore-safe backup token {$token}");
        }
    }
    if (strpos($backupScript, "'--databases'") !== false) {
        addError($errors, 'scripts/backup_database.php: --databases must not be used by restore-safe backup format');
    }
}

$backupVerifierPath = $root . '/lib/BackupVerifier.php';
if (is_file($backupVerifierPath)) {
    $backupVerifier = (string) file_get_contents($backupVerifierPath);
    foreach ([
        'hash_equals',
        'assertRestoreSafeDump',
        'CREATE|DROP|ALTER',
        "preg_match('/^\\\\s*USE\\\\s+/i'",
        'backupRoot',
    ] as $token) {
        if (strpos($backupVerifier, $token) === false) {
            addError($errors, "lib/BackupVerifier.php: missing restore safety token {$token}");
        }
    }
}

$restoreDrillPath = $root . '/scripts/restore_drill.php';
if (is_file($restoreDrillPath)) {
    $restoreDrill = (string) file_get_contents($restoreDrillPath);
    foreach ([
        "PHP_SAPI !== 'cli'",
        'BackupVerifier::verify',
        'BackupVerifier::assertRestoreSafeDump',
        "str_starts_with(\$targetDatabase, 'dr_drill_')",
        'DROP DATABASE',
        'DR_MAX_RPO_SECONDS',
        'DR_MAX_RTO_SECONDS',
        'critical_tables_verified',
    ] as $token) {
        if (strpos($restoreDrill, $token) === false) {
            addError($errors, "scripts/restore_drill.php: missing DR safety token {$token}");
        }
    }
}

$recoveryCheckPath = $root . '/scripts/recovery_check.php';
if (is_file($recoveryCheckPath)) {
    $recoveryCheck = (string) file_get_contents($recoveryCheckPath);
    foreach ([
        'DR_MAX_RPO_SECONDS',
        'DR_MAX_RTO_SECONDS',
        'DR_MAX_DRILL_AGE_DAYS',
        'latestSuccessful',
        'RECOVERY_CHECK_OK',
    ] as $token) {
        if (strpos($recoveryCheck, $token) === false) {
            addError($errors, "scripts/recovery_check.php: missing recovery gate token {$token}");
        }
    }
}

$observabilityServiceDrPath = $root . '/lib/ObservabilityService.php';
if (is_file($observabilityServiceDrPath)) {
    $observabilityServiceDr = (string) file_get_contents($observabilityServiceDrPath);
    if (strpos($observabilityServiceDr, 'disasterRecoverySummary') === false
        || strpos($observabilityServiceDr, 'recentRecoveryDrills') === false) {
        addError($errors, 'lib/ObservabilityService.php: disaster recovery posture must remain integrated');
    }
}

// 5.13) High availability and production cutover guards.
$haFiles = [
    'lib/MaintenanceMode.php',
    'lib/ReleaseIdentity.php',
    'lib/CommandRunner.php',
    'scripts/maintenance.php',
    'scripts/cutover_precheck.php',
    'scripts/go_live_check.php',
    'scripts/cutover.php',
    'scripts/resume_traffic.php',
    '.github/workflows/high-availability.yml',
    'docs/PRODUCTION_CUTOVER.md',
];
foreach ($haFiles as $relativePath) {
    if (!is_file($root . '/' . $relativePath)) {
        addError($errors, 'Missing high-availability/cutover file: ' . $relativePath);
    }
}

$healthControllerPath = $root . '/controllers/HealthController.php';
if (is_file($healthControllerPath)) {
    $healthController = (string) file_get_contents($healthControllerPath);
    foreach ([
        'public function live()',
        'public function ready()',
        'MaintenanceMode::safeStatus()',
        "header('X-Release-ID: '",
        "'ready' =>",
    ] as $token) {
        if (strpos($healthController, $token) === false) {
            addError($errors, "controllers/HealthController.php: missing HA token {$token}");
        }
    }

    $liveStart = strpos($healthController, 'public function live()');
    $readyStart = strpos($healthController, 'public function ready()');
    if ($liveStart !== false && $readyStart !== false && $readyStart > $liveStart) {
        $liveBody = substr($healthController, $liveStart, $readyStart - $liveStart);
        if (strpos($liveBody, 'new Database') !== false
            || strpos($liveBody, 'DeploymentHealth::check') !== false) {
            addError($errors, 'controllers/HealthController.php: liveness must remain database-independent');
        }
    }
}

$frontHaPath = $root . '/index.php';
if (is_file($frontHaPath)) {
    $frontHa = (string) file_get_contents($frontHaPath);
    foreach ([
        "'live', 'ready'",
        'MaintenanceMode::safeStatus()',
        'MaintenanceMode::renderUnavailable',
    ] as $token) {
        if (strpos($frontHa, $token) === false) {
            addError($errors, "index.php: missing maintenance/readiness token {$token}");
        }
    }
}

$maintenanceLibPath = $root . '/lib/MaintenanceMode.php';
if (is_file($maintenanceLibPath)) {
    $maintenanceLib = (string) file_get_contents($maintenanceLibPath);
    foreach (['storage/runtime', 'safeStatus', 'Retry-After', 'rename(', '0600', 'public/'] as $token) {
        if (strpos($maintenanceLib, $token) === false) {
            addError($errors, "lib/MaintenanceMode.php: missing control-plane safety token {$token}");
        }
    }
}

$settingsViewPath = $root . '/views/settings/system.php';
if (is_file($settingsViewPath)) {
    $settingsView = (string) file_get_contents($settingsViewPath);
    if (strpos($settingsView, 'name="settings[maintenance_mode]"') !== false) {
        addError($errors, 'views/settings/system.php: maintenance must not be controlled by legacy DB checkbox');
    }
}

$preflightHaPath = $root . '/scripts/preflight.php';
if (is_file($preflightHaPath)) {
    $preflightHa = (string) file_get_contents($preflightHaPath);
    if (strpos($preflightHa, "'allow-pending'") === false
        || strpos($preflightHa, '$allowPending') === false) {
        addError($errors, 'scripts/preflight.php: explicit cutover pending-migration mode is required');
    }
}

$cutoverPath = $root . '/scripts/cutover.php';
if (is_file($cutoverPath)) {
    $cutover = (string) file_get_contents($cutoverPath);
    foreach ([
        'scripts/cutover_precheck.php',
        'MaintenanceMode::enable',
        'MaintenanceMode::disable',
        'CUTOVER_FAILED_MAINTENANCE_REMAINS_ON',
        'hash_equals($currentReleaseId, $releaseId)',
        'scripts/go_live_check.php',
    ] as $token) {
        if (strpos($cutover, $token) === false) {
            addError($errors, "scripts/cutover.php: missing cutover safety token {$token}");
        }
    }
}

$goLivePath = $root . '/scripts/go_live_check.php';
if (is_file($goLivePath)) {
    $goLive = (string) file_get_contents($goLivePath);
    foreach ([
        'DeploymentHealth::check',
        'scripts/performance_check.php',
        'scripts/recovery_check.php',
        'recovery checks cannot be skipped in production',
        'GO_LIVE_CHECK_OK',
    ] as $token) {
        if (strpos($goLive, $token) === false) {
            addError($errors, "scripts/go_live_check.php: missing go-live token {$token}");
        }
    }
}

// 5.14) Production security and compliance guards.
$securityHardeningFiles = [
    'lib/SecurityCompliance.php',
    'lib/SecureUpload.php',
    'scripts/security_check.php',
    'scripts/secret_scan.php',
    'scripts/generate_sbom.php',
    '.github/workflows/production-security.yml',
    'docs/PRODUCTION_SECURITY.md',
];
foreach ($securityHardeningFiles as $relativePath) {
    if (!is_file($root . '/' . $relativePath)) {
        addError($errors, 'Missing production security file: ' . $relativePath);
    }
}

$securityConfigPath = $root . '/config/security.php';
if (is_file($securityConfigPath)) {
    $securityConfig = (string) file_get_contents($securityConfigPath);
    foreach ([
        'security_is_trusted_proxy_request',
        'TRUSTED_PROXY_IPS',
        'Content-Security-Policy-Report-Only',
        'SESSION_IDLE_TIMEOUT_SECONDS',
        'session.use_strict_mode',
        'session.use_trans_sid',
        "session_name('ROSTERSESSID')",
        'security_mark_authenticated_session',
        'security_destroy_session',
        'security_login_identity_fingerprint',
    ] as $token) {
        if (strpos($securityConfig, $token) === false) {
            addError($errors, "config/security.php: missing production hardening token {$token}");
        }
    }

    if (strpos($securityConfig, "HTTP_X_FORWARDED_PROTO") !== false
        && strpos($securityConfig, 'security_is_trusted_proxy_request()') === false) {
        addError($errors, 'config/security.php: forwarded proto must remain behind explicit trusted-proxy validation');
    }
}

$authSecurityPath = $root . '/controllers/AuthController.php';
if (is_file($authSecurityPath)) {
    $authSecurity = (string) file_get_contents($authSecurityPath);
    foreach (['security_mark_authenticated_session();', 'security_destroy_session();'] as $token) {
        if (strpos($authSecurity, $token) === false) {
            addError($errors, "controllers/AuthController.php: missing session lifecycle token {$token}");
        }
    }
}

$headerSecurityPath = $root . '/views/layouts/header.php';
if (is_file($headerSecurityPath)) {
    $headerSecurity = (string) file_get_contents($headerSecurityPath);
    if (strpos($headerSecurity, "\$sys_settings['maintenance_mode']") !== false) {
        addError($errors, 'views/layouts/header.php: legacy DB maintenance control must not return');
    }
}

$settingsSecurityPath = $root . '/controllers/SettingsController.php';
if (is_file($settingsSecurityPath)) {
    $settingsSecurity = (string) file_get_contents($settingsSecurityPath);

    if (strpos($settingsSecurity, "\$_GET['key']") !== false) {
        addError($errors, 'controllers/SettingsController.php: cron/API secrets must not be accepted from query strings');
    }
    foreach ([
        'array_intersect_key',
        'HTTP_X_ROSTER_CRON_KEY',
        'security_is_production()',
        'SecurityCompliance::assess',
        'SecureUpload::store',
    ] as $token) {
        if (strpos($settingsSecurity, $token) === false) {
            addError($errors, "controllers/SettingsController.php: missing settings/upload security token {$token}");
        }
    }
}

$settingsSecurityViewPath = $root . '/views/settings/system.php';
if (is_file($settingsSecurityViewPath)) {
    $settingsSecurityView = (string) file_get_contents($settingsSecurityViewPath);
    if (strpos($settingsSecurityView, "value=\"<?= htmlspecialchars(\$settings['line_notify_token']") !== false) {
        addError($errors, 'views/settings/system.php: stored integration secret must not be rendered into HTML');
    }
    if (strpos($settingsSecurityView, 'autocomplete="new-password"') === false) {
        addError($errors, 'views/settings/system.php: secret input must remain non-prefilled');
    }
}

$secureUploadPath = $root . '/lib/SecureUpload.php';
if (is_file($secureUploadPath)) {
    $secureUpload = (string) file_get_contents($secureUploadPath);
    foreach ([
        'is_uploaded_file',
        'FILEINFO_MIME_TYPE',
        'getimagesize',
        "signature !== '%PDF-'",
        'random_bytes',
        'Private uploads must not be stored under public/',
        'move_uploaded_file',
    ] as $token) {
        if (strpos($secureUpload, $token) === false) {
            addError($errors, "lib/SecureUpload.php: missing upload safety token {$token}");
        }
    }
}

$leaveUploadPath = $root . '/controllers/LeaveController.php';
if (is_file($leaveUploadPath)) {
    $leaveUpload = (string) file_get_contents($leaveUploadPath);
    if (strpos($leaveUpload, 'SecureUpload::store') === false
        || strpos($leaveUpload, "'storage/private/med_certs'") === false) {
        addError($errors, 'controllers/LeaveController.php: medical certificates must use private SecureUpload storage');
    }
}

$goLiveSecurityPath = $root . '/scripts/go_live_check.php';
if (is_file($goLiveSecurityPath)) {
    $goLiveSecurity = (string) file_get_contents($goLiveSecurityPath);
    if (strpos($goLiveSecurity, 'scripts/security_check.php') === false
        || strpos($goLiveSecurity, "\$securityCommand[] = '--strict';") === false) {
        addError($errors, 'scripts/go_live_check.php: Production security compliance must remain a traffic gate');
    }
}

// 5.15) Electronic signature and topbar regression guards.
$signatureFiles = [
    'lib/ElectronicSignature.php',
    'database/migrations/20261005_electronic_signatures.sql',
];
foreach ($signatureFiles as $relativePath) {
    if (!is_file($root . '/' . $relativePath)) {
        addError($errors, 'Missing electronic signature file: ' . $relativePath);
    }
}

$signatureServicePath = $root . '/lib/ElectronicSignature.php';
if (is_file($signatureServicePath)) {
    $signatureService = (string) file_get_contents($signatureServicePath);
    foreach ([
        'MAX_BYTES',
        'getimagesizefromstring',
        'IMAGETYPE_PNG',
        'IMAGETYPE_JPEG',
        "hash('sha256'",
        "['DRAW', 'UPLOAD']",
    ] as $token) {
        if (strpos($signatureService, $token) === false) {
            addError($errors, "lib/ElectronicSignature.php: missing signature validation token {$token}");
        }
    }
}

$userModelSignaturePath = $root . '/models/UserModel.php';
if (is_file($userModelSignaturePath)) {
    $userModelSignature = (string) file_get_contents($userModelSignaturePath);
    foreach ([
        'ElectronicSignature::normalize',
        'signature_sha256',
        'signature_method',
        'signature_updated_at',
        'clearSignature',
    ] as $token) {
        if (strpos($userModelSignature, $token) === false) {
            addError($errors, "models/UserModel.php: missing signature persistence token {$token}");
        }
    }
}

$profileSignatureControllerPath = $root . '/controllers/ProfileController.php';
if (is_file($profileSignatureControllerPath)) {
    $profileSignatureController = (string) file_get_contents($profileSignatureControllerPath);
    foreach ([
        'requireSignatureOwner',
        'public function save_signature()',
        'public function delete_signature()',
        'ElectronicSignature::normalize',
        'security_is_valid_post_csrf',
    ] as $token) {
        if (strpos($profileSignatureController, $token) === false) {
            addError($errors, "controllers/ProfileController.php: missing signature workflow token {$token}");
        }
    }
}

$profileSignatureViewPath = $root . '/views/profile/index.php';
if (is_file($profileSignatureViewPath)) {
    $profileSignatureView = (string) file_get_contents($profileSignatureViewPath);
    foreach ([
        'id="nav-signature"',
        'id="signatureCanvas"',
        'id="signatureUpload"',
        'id="signatureForm"',
        'save_signature',
        'delete_signature',
        '$is_signature_owner',
    ] as $token) {
        if (strpos($profileSignatureView, $token) === false) {
            addError($errors, "views/profile/index.php: missing electronic signature UI token {$token}");
        }
    }
}

$ajaxSignaturePath = $root . '/controllers/AjaxController.php';
if (is_file($ajaxSignaturePath)) {
    $ajaxSignature = (string) file_get_contents($ajaxSignaturePath);
    if (strpos($ajaxSignature, 'ElectronicSignature::isValid') === false) {
        addError($errors, 'controllers/AjaxController.php: roster submission/approval must validate the electronic signature image');
    }
}

$headerUiPath = $root . '/views/layouts/header.php';
if (is_file($headerUiPath)) {
    $headerUi = (string) file_get_contents($headerUiPath);
    if (strpos($headerUi, 'ROSTER PRO WORKSPACE') !== false) {
        addError($errors, 'views/layouts/header.php: obsolete ROSTER PRO WORKSPACE kicker must remain removed');
    }
}

$styleUiPath = $root . '/public/css/style.css';
if (is_file($styleUiPath)) {
    $styleUi = (string) file_get_contents($styleUiPath);
    foreach ([
        'Sidebar toggle visibility contract',
        '.top-navbar .rp-mobile-menu-btn',
        '.top-navbar .rp-desktop-menu-btn',
        '@media (min-width: 1024px)',
        '@media (max-width: 1023.98px)',
    ] as $token) {
        if (strpos($styleUi, $token) === false) {
            addError($errors, "public/css/style.css: missing sidebar visibility contract token {$token}");
        }
    }
}

// 6) Destructive/state-changing actions must not be literal GET links.
$mutationActions = [
    'delete','bulk_delete','toggle','action','clear_roster','randomize_roster',
    'save_signatures','update_order','process_approval','save_balance',
    'process_new_year','read','read_all','read_notif','read_all_notif',
    'delete_notif','delete_all_notif','logout','import_csv','do_backup',
    'do_server_backup','delete_server_backup','save_holiday','toggle_holiday',
    'delete_holiday','update_system','save_hospital','test_line','test_line_notify',
    'save_signature','delete_signature',
    'complete_followup','create_snapshot','restore_snapshot',
    'resolve_event','retry_job','capture_health'
];
$mutationAlternation = implode('|', array_map('preg_quote', $mutationActions));
$getMutationPattern = '/<a\b[^>]+href=[\'"][^\'"]*index\.php\?[^\'"]*(?:&|&amp;)a=(' . $mutationAlternation . ')(?:&|&amp;|[\'"])/i';

foreach (phpFilesUnder($root . '/views') as $file) {
    $content = (string) file_get_contents($file);
    if (preg_match_all($getMutationPattern, $content, $matches)) {
        foreach (array_unique($matches[1] ?? []) as $action) {
            addError($errors, ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR) . ': state-changing action linked with GET: ' . $action);
        }
    }
}

echo "Code quality audit\n";
echo "Controllers indexed: " . count($controllers) . "\n";
echo "Files scanned: " . count(array_unique($scanFiles)) . "\n";

if ($warnings) {
    echo "\nWarnings:\n - " . implode("\n - ", array_unique($warnings)) . "\n";
}

if ($errors) {
    fwrite(STDERR, "\nCode quality audit failed:\n - " . implode("\n - ", array_unique($errors)) . "\n");
    exit(1);
}

echo "\nCode quality audit OK.\n";
