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
        || strpos($ajaxRevision, 'กรุณาบันทึกลายเซ็นอิเล็กทรอนิกส์ในโปรไฟล์') === false) {
        addError($errors, 'controllers/AjaxController.php: approval must require a signature and create an official revision');
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
    if (strpos($frontController, "$publicVerifyActions = ['index', 'revision'];") === false) {
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

// 6) Destructive/state-changing actions must not be literal GET links.
$mutationActions = [
    'delete','bulk_delete','toggle','action','clear_roster','randomize_roster',
    'save_signatures','update_order','process_approval','save_balance',
    'process_new_year','read','read_all','read_notif','read_all_notif',
    'delete_notif','delete_all_notif','logout','import_csv','do_backup',
    'do_server_backup','delete_server_backup','save_holiday','toggle_holiday',
    'delete_holiday','update_system','save_hospital','test_line','test_line_notify',
    'complete_followup','create_snapshot','restore_snapshot'
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
