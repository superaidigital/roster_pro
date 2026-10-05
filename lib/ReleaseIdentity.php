<?php
declare(strict_types=1);

final class ReleaseIdentity {
    public static function current(): string {
        $value = trim((string)(getenv('APP_RELEASE_ID') ?: ''));
        if ($value === '') {
            return 'unknown';
        }

        $clean = preg_replace('/[^A-Za-z0-9._:@+\/-]+/', '-', $value) ?? '';
        $clean = trim($clean, '-');
        return $clean !== '' ? substr($clean, 0, 120) : 'unknown';
    }
}
