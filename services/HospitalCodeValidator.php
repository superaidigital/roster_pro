<?php
/**
 * MOPH facility registry (https://hcode.moph.go.th/about/)
 * Legacy 9-digit codes are numeric; new 9-character codes are AA + 7 digits.
 * Format validation is not a substitute for checking the authoritative registry.
 */
final class HospitalCodeValidator
{
    public static function old9(?string $code): bool
    {
        return preg_match('/^[0-9]{9}$/D', trim((string)$code)) === 1;
    }

    public static function new9(?string $code): bool
    {
        return preg_match('/^[A-Z]{2}[0-9]{7}$/D', strtoupper(trim((string)$code))) === 1;
    }

    public static function presentAndValid(?string $old9, ?string $new9): bool
    {
        return self::old9($old9) || self::new9($new9);
    }
}
