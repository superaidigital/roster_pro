<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';

final class SecurityCompliance {
    public static function assess(?PDO $db = null): array {
        $root = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
        $checks = [];
        $production = security_is_production();

        $add = static function(
            string $id,
            string $label,
            string $status,
            int $weight,
            string $detail
        ) use (&$checks): void {
            $status = strtoupper($status);
            if (!in_array($status, ['PASS', 'WARN', 'FAIL', 'INFO'], true)) {
                $status = 'INFO';
            }

            $checks[] = [
                'id' => $id,
                'label' => $label,
                'status' => $status,
                'weight' => max(0, $weight),
                'detail' => $detail,
            ];
        };

        $baseUrl = trim((string)(getenv('APP_BASE_URL') ?: ''));
        $baseParts = $baseUrl !== '' ? parse_url($baseUrl) : false;
        $httpsBase = is_array($baseParts)
            && strtolower((string)($baseParts['scheme'] ?? '')) === 'https'
            && !empty($baseParts['host']);
        $add(
            'https_base_url',
            'Production HTTPS base URL',
            $production ? ($httpsBase ? 'PASS' : 'FAIL') : ($httpsBase ? 'PASS' : 'INFO'),
            10,
            $httpsBase ? 'APP_BASE_URL uses HTTPS.' : 'APP_BASE_URL is not an HTTPS URL.'
        );

        $releaseId = trim((string)(getenv('APP_RELEASE_ID') ?: ''));
        $releaseOk = $releaseId !== '' && strtolower($releaseId) !== 'unknown';
        $add(
            'release_identity',
            'Immutable release identity',
            $production ? ($releaseOk ? 'PASS' : 'FAIL') : ($releaseOk ? 'PASS' : 'INFO'),
            4,
            $releaseOk ? 'APP_RELEASE_ID is configured.' : 'APP_RELEASE_ID is missing.'
        );

        $cronKey = (string)(getenv('ROSTER_CRON_KEY') ?: '');
        $cronStrong = strlen($cronKey) >= 32
            && !preg_match('/replace-with|changeme|password|secret/i', $cronKey);
        $add(
            'cron_secret',
            'Cron/API secret strength',
            $production ? ($cronStrong ? 'PASS' : 'FAIL') : ($cronStrong ? 'PASS' : 'INFO'),
            7,
            $cronStrong ? 'ROSTER_CRON_KEY is non-placeholder and at least 32 characters.' : 'ROSTER_CRON_KEY is weak or placeholder.'
        );

        $dbUser = trim((string)(getenv('DB_USER') ?: 'root'));
        $dbPasswordRaw = getenv('DB_PASSWORD');
        $dbPassword = $dbPasswordRaw !== false ? (string)$dbPasswordRaw : '';

        $add(
            'db_user',
            'Database runtime account',
            $production ? (strtolower($dbUser) !== 'root' ? 'PASS' : 'FAIL') : (strtolower($dbUser) !== 'root' ? 'PASS' : 'WARN'),
            9,
            strtolower($dbUser) === 'root' ? 'DB_USER is root.' : 'DB_USER is not root.'
        );

        $add(
            'db_password',
            'Database password configured',
            $production ? ($dbPassword !== '' ? 'PASS' : 'FAIL') : ($dbPassword !== '' ? 'PASS' : 'WARN'),
            7,
            $dbPassword !== '' ? 'DB_PASSWORD is configured.' : 'DB_PASSWORD is blank.'
        );

        $proxyEnabled = filter_var(getenv('TRUST_PROXY_HEADERS') ?: '0', FILTER_VALIDATE_BOOLEAN);
        $trustedProxyIps = security_trusted_proxy_ips();
        $proxyOk = !$proxyEnabled || $trustedProxyIps !== [];
        $add(
            'proxy_trust',
            'Reverse proxy trust boundary',
            $proxyOk ? 'PASS' : 'FAIL',
            7,
            $proxyEnabled
                ? ($trustedProxyIps !== [] ? 'Proxy headers accepted only from configured IPs.' : 'TRUST_PROXY_HEADERS is enabled without TRUSTED_PROXY_IPS.')
                : 'Proxy headers are not trusted.'
        );

        $cspMode = security_csp_mode();
        $cspStatus = $cspMode === 'enforce'
            ? 'PASS'
            : ($cspMode === 'report-only' ? 'WARN' : ($production ? 'FAIL' : 'INFO'));
        $add(
            'csp',
            'Content Security Policy',
            $cspStatus,
            8,
            $cspMode === 'enforce'
                ? 'CSP is enforced.'
                : ($cspMode === 'report-only'
                    ? 'CSP is Report-Only while inline/CDN usage is migrated.'
                    : 'CSP is disabled.')
        );

        $idle = security_session_idle_timeout();
        $absolute = security_session_absolute_timeout();
        $regen = security_session_regen_interval();
        $sessionOk = $idle <= 28800 && $absolute <= 43200 && $regen <= 1800;
        $add(
            'session_lifetime',
            'Authenticated session lifetime',
            $sessionOk ? 'PASS' : 'WARN',
            7,
            "idle={$idle}s absolute={$absolute}s regenerate={$regen}s"
        );

        $displayErrors = filter_var((string)ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN);
        $add(
            'display_errors',
            'PHP display_errors',
            $production ? (!$displayErrors ? 'PASS' : 'FAIL') : (!$displayErrors ? 'PASS' : 'WARN'),
            5,
            $displayErrors ? 'display_errors is enabled.' : 'display_errors is disabled.'
        );

        $exposePhp = filter_var((string)ini_get('expose_php'), FILTER_VALIDATE_BOOLEAN);
        $add(
            'expose_php',
            'PHP version exposure',
            $production ? (!$exposePhp ? 'PASS' : 'WARN') : (!$exposePhp ? 'PASS' : 'INFO'),
            3,
            $exposePhp ? 'expose_php is enabled.' : 'expose_php is disabled.'
        );

        $allowUrlInclude = filter_var((string)ini_get('allow_url_include'), FILTER_VALIDATE_BOOLEAN);
        $add(
            'url_include',
            'Remote PHP includes',
            !$allowUrlInclude ? 'PASS' : 'FAIL',
            8,
            $allowUrlInclude ? 'allow_url_include is enabled.' : 'allow_url_include is disabled.'
        );

        foreach ([
            ['backup_path', 'Backup storage outside public', getenv('BACKUP_DIR') ?: 'storage/backups', 6],
            ['runtime_path', 'Maintenance runtime state outside public', getenv('MAINTENANCE_STATE_DIR') ?: 'storage/runtime', 5],
            ['private_upload_path', 'Private medical uploads outside public', 'storage/private/med_certs', 7],
        ] as [$id, $label, $configured, $weight]) {
            $path = self::resolvePath($root, (string)$configured);
            $public = realpath($root . '/public');
            $insidePublic = $public !== false
                && self::pathWithin($path, $public);

            $add(
                (string)$id,
                (string)$label,
                !$insidePublic ? 'PASS' : 'FAIL',
                (int)$weight,
                $insidePublic ? 'Configured path resolves under public/.' : 'Configured path is outside public/.'
            );
        }

        $legacyMedCert = $root . '/uploads/med_certs';
        $legacyFiles = [];
        if (is_dir($legacyMedCert)) {
            foreach (scandir($legacyMedCert) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || $entry === '.htaccess') continue;
                if (is_file($legacyMedCert . '/' . $entry)) $legacyFiles[] = $entry;
            }
        }
        $add(
            'legacy_med_certs',
            'Legacy web-adjacent medical files',
            $legacyFiles === [] ? 'PASS' : 'FAIL',
            6,
            $legacyFiles === [] ? 'No legacy medical files remain under uploads/med_certs.' : count($legacyFiles) . ' legacy medical file(s) remain.'
        );

        $publicUploadExecutable = self::findExecutableUploads($root . '/public/uploads');
        $add(
            'public_upload_exec',
            'Executable files in public uploads',
            $publicUploadExecutable === [] ? 'PASS' : 'FAIL',
            8,
            $publicUploadExecutable === [] ? 'No executable/script uploads detected.' : implode(', ', array_slice($publicUploadExecutable, 0, 5))
        );

        if ($db instanceof PDO) {
            try {
                $grants = $db->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN);
                $grantText = strtoupper(implode("
", array_map('strval', $grants)));
                $dangerous = [];
                foreach (['GRANT OPTION', 'FILE', 'SUPER', 'CREATE USER', 'SHUTDOWN'] as $token) {
                    if (str_contains($grantText, $token)) $dangerous[] = $token;
                }

                $add(
                    'db_grants',
                    'Dangerous database server privileges',
                    $dangerous === [] ? 'PASS' : ($production ? 'FAIL' : 'WARN'),
                    8,
                    $dangerous === [] ? 'No dangerous global/server privileges detected.' : 'Detected: ' . implode(', ', $dangerous)
                );
            } catch (Throwable $e) {
                $add(
                    'db_grants',
                    'Dangerous database server privileges',
                    'WARN',
                    8,
                    'Unable to inspect SHOW GRANTS.'
                );
            }
        } else {
            $add(
                'db_grants',
                'Dangerous database server privileges',
                'INFO',
                8,
                'Database connection was not supplied.'
            );
        }

        $possible = 0;
        $earned = 0.0;
        $failCount = 0;
        $warnCount = 0;

        foreach ($checks as $check) {
            $weight = (int)$check['weight'];
            $possible += $weight;
            if ($check['status'] === 'PASS') {
                $earned += $weight;
            } elseif ($check['status'] === 'WARN') {
                $earned += $weight * 0.5;
                $warnCount++;
            } elseif ($check['status'] === 'FAIL') {
                $failCount++;
            }
        }

        $score = $possible > 0 ? (int)round(($earned / $possible) * 100) : 0;
        $grade = match (true) {
            $score >= 95 => 'A',
            $score >= 85 => 'B',
            $score >= 75 => 'C',
            $score >= 65 => 'D',
            default => 'F',
        };

        return [
            'status' => $failCount > 0 ? 'FAIL' : ($warnCount > 0 ? 'WARN' : 'PASS'),
            'score' => $score,
            'grade' => $grade,
            'failures' => $failCount,
            'warnings' => $warnCount,
            'checks' => $checks,
            'production' => $production,
            'generated_at' => date(DATE_ATOM),
        ];
    }

    private static function resolvePath(string $root, string $path): string {
        if (!str_starts_with($path, DIRECTORY_SEPARATOR)
            && !preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = $root . '/' . ltrim($path, '/\\');
        }

        $real = realpath($path);
        return $real !== false ? $real : $path;
    }

    private static function pathWithin(string $candidate, string $root): bool {
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');
        return $candidate === $root || str_starts_with($candidate, $root . '/');
    }

    private static function findExecutableUploads(string $directory): array {
        if (!is_dir($directory)) return [];

        $bad = [];
        $extensions = ['php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'htaccess'];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $name = strtolower($file->getFilename());
            $extension = strtolower($file->getExtension());
            if (in_array($extension, $extensions, true) || $name === '.htaccess') {
                $bad[] = $file->getPathname();
            }
        }

        return $bad;
    }
}
