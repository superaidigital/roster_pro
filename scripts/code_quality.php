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

// 6) Destructive/state-changing actions must not be literal GET links.
$mutationActions = [
    'delete','bulk_delete','toggle','action','clear_roster','randomize_roster',
    'save_signatures','update_order','process_approval','save_balance',
    'process_new_year','read_all','test_line_notify','complete_followup'
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
