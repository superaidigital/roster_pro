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
foreach (glob($root . '/*Controller.php') ?: [] as $file) {
    addError($errors, 'Duplicate root controller detected: ' . basename($file));
}
foreach (glob($root . '/*Model.php') ?: [] as $file) {
    addError($errors, 'Duplicate root model detected: ' . basename($file));
}
if (is_file($root . '/database.php')) {
    addError($errors, 'Duplicate root database.php detected; use config/database.php');
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

    if (preg_match('/json_encode\s*\([\s\S]{0,500}\$e->getMessage\s*\(\)/i', $content)) {
        $warnings[] = $rel . ': exception message may be exposed in JSON response';
    }
}

// 6) Destructive/state-changing actions must not be literal GET links.
$mutationActions = [
    'delete','bulk_delete','toggle','action','clear_roster','randomize_roster',
    'save_signatures','update_order','process_approval','save_balance',
    'process_new_year','read_all','test_line_notify'
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
