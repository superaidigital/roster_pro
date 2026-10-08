<?php
/**
 * Private temporary storage for Data43 import/export jobs.
 *
 * Raw health files must not be placed under the web document root. By default
 * we use the operating system temp directory. DATA43_TEMP_DIR may override it.
 */
final class Data43StorageService
{
    public static function baseRoot(): string
    {
        $configured = trim((string)(getenv('DATA43_TEMP_DIR') ?: ''));
        $root = $configured !== ''
            ? $configured
            : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'roster_pro_data43';

        self::ensureDirectory($root);
        return $root;
    }

    public static function createJobDirectory(string $purpose): string
    {
        $purpose = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $purpose) ?: 'job');
        $dir = self::baseRoot()
            . DIRECTORY_SEPARATOR . $purpose
            . '_'. date('Ymd_His')
            . '_' . bin2hex(random_bytes(8));

        self::ensureDirectory($dir);
        return $dir;
    }

    public static function isOutsideDocumentRoot(string $path): bool
    {
        $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $parent = realpath(dirname($path));

        if (!$documentRoot || !$parent) return true;

        $normalize = static function(string $value): string {
            $value = str_replace('\\', '/', $value);
            return rtrim(strtolower($value), '/') . '/';
        };

        return !str_starts_with($normalize($parent), $normalize($documentRoot));
    }

    public static function recursiveDelete(string $path): void
    {
        if ($path === '' || !file_exists($path)) return;
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            self::recursiveDelete($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('ไม่สามารถสร้างพื้นที่ชั่วคราว Data43 ได้: ' . $path);
        }

        if (!is_writable($path)) {
            throw new RuntimeException('พื้นที่ชั่วคราว Data43 ไม่สามารถเขียนได้: ' . $path);
        }
    }
}
?>