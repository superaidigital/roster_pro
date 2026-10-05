<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Unable to resolve repository root.\n");
    exit(2);
}

$patterns = [
    'private_key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
    'aws_access_key' => '/\bAKIA[0-9A-Z]{16}\b/',
    'github_token' => '/\bgh[pousr]_[A-Za-z0-9_]{30,}\b/',
    'google_api_key' => '/\bAIza[0-9A-Za-z_-]{35}\b/',
    'openai_key' => '/\bsk-[A-Za-z0-9_-]{24,}\b/',
];

$extensions = ['php', 'js', 'json', 'yml', 'yaml', 'xml', 'ini', 'conf', 'htaccess'];
$rootFiles = ['index.php', 'manifest.json', '.gitignore'];
$violations = [];

$shouldScan = static function(string $path) use ($root, $extensions, $rootFiles): bool {
    $relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    if ($relative === '' || str_starts_with($relative, '.git/')) return false;
    if (str_starts_with($relative, 'storage/backups/')) return false;
    if (str_starts_with($relative, 'public/uploads/')) return false;
    if ($relative === '.env.example' || str_starts_with($relative, 'docs/')) return false;

    if (in_array($relative, $rootFiles, true)) return true;
    $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    return in_array($extension, $extensions, true);
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (!$shouldScan($path)) continue;

    $size = (int)$file->getSize();
    if ($size <= 0 || $size > 2 * 1024 * 1024) continue;

    $content = file_get_contents($path);
    if (!is_string($content) || str_contains($content, "\0")) continue;

    foreach ($patterns as $name => $pattern) {
        if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
            $offset = (int)($match[0][1] ?? 0);
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
            $violations[] = [
                'type' => $name,
                'file' => ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR),
                'line' => $line,
            ];
        }
    }
}

if ($violations !== []) {
    echo "SECRET_SCAN_FAILED\n";
    foreach ($violations as $violation) {
        echo sprintf(
            "- %s:%d [%s]\n",
            $violation['file'],
            $violation['line'],
            $violation['type']
        );
    }
    exit(2);
}

echo "SECRET_SCAN_OK\n";
exit(0);
