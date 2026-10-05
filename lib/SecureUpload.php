<?php
declare(strict_types=1);

final class SecureUpload {
    public static function store(
        array $upload,
        string $directory,
        array $allowedMimeExtensions,
        int $maxBytes,
        string $prefix,
        bool $private = true
    ): array {
        $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Uploaded file is invalid.');
        }

        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('Uploaded file size is outside the allowed limit.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string)$finfo->file($tmp));
        if (!isset($allowedMimeExtensions[$mime])) {
            throw new RuntimeException('Uploaded file type is not allowed.');
        }

        $extension = strtolower((string)$allowedMimeExtensions[$mime]);
        if (!preg_match('/^[a-z0-9]{2,8}$/', $extension)) {
            throw new RuntimeException('Configured upload extension is invalid.');
        }

        self::validateContent($tmp, $mime);

        $root = realpath(dirname(__DIR__));
        if ($root === false) {
            throw new RuntimeException('Unable to resolve application root.');
        }

        $targetDir = $directory;
        if (!str_starts_with($targetDir, DIRECTORY_SEPARATOR)
            && !preg_match('/^[A-Za-z]:[\\\\\/]/', $targetDir)) {
            $targetDir = $root . '/' . ltrim($targetDir, '/\\');
        }

        $public = realpath($root . '/public');
        $normalizedTarget = rtrim(str_replace('\\', '/', $targetDir), '/');
        if ($private && $public !== false) {
            $normalizedPublic = rtrim(str_replace('\\', '/', $public), '/');
            if ($normalizedTarget === $normalizedPublic
                || str_starts_with($normalizedTarget, $normalizedPublic . '/')) {
                throw new RuntimeException('Private uploads must not be stored under public/.');
            }
        }

        $dirMode = $private ? 0750 : 0755;
        if (!is_dir($targetDir) && !mkdir($targetDir, $dirMode, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to prepare upload directory.');
        }

        $safePrefix = preg_replace('/[^A-Za-z0-9_-]+/', '_', $prefix) ?: 'upload';
        $name = $safePrefix . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException('Unable to persist uploaded file.');
        }

        @chmod($destination, $private ? 0640 : 0644);

        $storedMime = strtolower((string)$finfo->file($destination));
        if ($storedMime !== $mime) {
            @unlink($destination);
            throw new RuntimeException('Stored upload MIME type changed unexpectedly.');
        }

        self::validateContent($destination, $storedMime);

        return [
            'path' => self::relativePath($root, $destination),
            'absolute_path' => $destination,
            'mime' => $storedMime,
            'size' => (int)(filesize($destination) ?: $size),
            'filename' => $name,
        ];
    }

    private static function validateContent(string $path, string $mime): void {
        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $info = @getimagesize($path);
            if (!is_array($info) || empty($info[0]) || empty($info[1])) {
                throw new RuntimeException('Uploaded image structure is invalid.');
            }

            if ((int)$info[0] > 12000 || (int)$info[1] > 12000) {
                throw new RuntimeException('Uploaded image dimensions are too large.');
            }

            $expectedType = $mime === 'image/jpeg' ? IMAGETYPE_JPEG : IMAGETYPE_PNG;
            if ((int)($info[2] ?? 0) !== $expectedType) {
                throw new RuntimeException('Uploaded image signature does not match MIME type.');
            }
            return;
        }

        if ($mime === 'application/pdf') {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                throw new RuntimeException('Unable to inspect uploaded PDF.');
            }
            $signature = fread($handle, 5);
            fclose($handle);
            if ($signature !== '%PDF-') {
                throw new RuntimeException('Uploaded PDF signature is invalid.');
            }
        }
    }

    private static function relativePath(string $root, string $absolute): string {
        $rootNormalized = rtrim(str_replace('\\', '/', $root), '/');
        $absoluteNormalized = str_replace('\\', '/', $absolute);
        if (str_starts_with($absoluteNormalized, $rootNormalized . '/')) {
            return substr($absoluteNormalized, strlen($rootNormalized) + 1);
        }
        return $absoluteNormalized;
    }
}
