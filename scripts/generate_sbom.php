<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['output:', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/generate_sbom.php [--output=build/sbom.cdx.json]\n";
    exit(0);
}

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Unable to resolve repository root.\n");
    exit(2);
}

$output = (string)($options['output'] ?? 'build/sbom.cdx.json');
if (!str_starts_with($output, DIRECTORY_SEPARATOR)) {
    $output = $root . '/' . ltrim($output, '/\\');
}

$components = [];
$seen = [];

$add = static function(string $name, string $version, string $purl = '', string $type = 'library') use (&$components, &$seen): void {
    $key = strtolower($name . '@' . $version . '|' . $purl);
    if (isset($seen[$key])) return;
    $seen[$key] = true;

    $component = [
        'type' => $type,
        'name' => $name,
        'version' => $version !== '' ? $version : 'unknown',
    ];
    if ($purl !== '') $component['purl'] = $purl;
    $components[] = $component;
};

$scanFiles = [
    $root . '/views/layouts/header.php',
    $root . '/views/auth/login.php',
];

foreach ($scanFiles as $file) {
    if (!is_file($file)) continue;
    $content = (string)file_get_contents($file);

    if (preg_match_all('#https://cdn\.jsdelivr\.net/npm/([A-Za-z0-9._-]+)@([^/\"\']+)#', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $name = (string)$match[1];
            $version = (string)$match[2];
            $add($name, $version, 'pkg:npm/' . rawurlencode($name) . '@' . rawurlencode($version));
        }
    }

    if (preg_match_all('#https://code\.jquery\.com/jquery-([0-9.]+)\.min\.js#', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $version = (string)$match[1];
            $add('jquery', $version, 'pkg:npm/jquery@' . rawurlencode($version));
        }
    }

    if (str_contains($content, 'fonts.googleapis.com') && str_contains($content, 'family=Sarabun')) {
        $add('Google Fonts Sarabun', 'external', '', 'data');
    }
}

usort($components, static fn(array $a, array $b): int => strcmp((string)$a['name'], (string)$b['name']));

$releaseId = trim((string)(getenv('APP_RELEASE_ID') ?: 'unknown'));
$serial = 'urn:uuid:' . self_uuid_v4();

$document = [
    'bomFormat' => 'CycloneDX',
    'specVersion' => '1.6',
    'serialNumber' => $serial,
    'version' => 1,
    'metadata' => [
        'timestamp' => gmdate('c'),
        'component' => [
            'type' => 'application',
            'name' => 'Roster Pro',
            'version' => $releaseId !== '' ? $releaseId : 'unknown',
        ],
        'tools' => [
            'components' => [[
                'type' => 'application',
                'name' => 'Roster Pro built-in SBOM generator',
                'version' => '1',
            ]],
        ],
    ],
    'components' => $components,
];

$dir = dirname($output);
if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
    fwrite(STDERR, "Unable to create SBOM output directory.\n");
    exit(2);
}

$json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($json) || file_put_contents($output, $json . PHP_EOL) === false) {
    fwrite(STDERR, "Unable to write SBOM.\n");
    exit(2);
}

echo "SBOM_OK\n";
echo "output={$output}\n";
echo "components=" . count($components) . "\n";
exit(0);

function self_uuid_v4(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}
