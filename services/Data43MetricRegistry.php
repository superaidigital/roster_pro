<?php
/**
 * Indicator definitions for Data43 analytics.
 *
 * Keep calculation semantics outside controllers/views so a metric can define
 * its own denominator and presentation unit. This prevents applying
 * "per 1,000 population" to indicators where that denominator is not valid.
 */
final class Data43MetricRegistry
{
    public static function all(): array
    {
        return [
            'DM' => [
                'label' => 'เบาหวาน (DM)',
                'source' => 'CHRONIC',
                'denominator' => 'POPULATION',
                'calculation' => 'RATE_PER_1000',
                'unit' => 'ต่อ 1,000 คน',
                'default_mode' => 'rate',
            ],
            'HT' => [
                'label' => 'ความดันโลหิตสูง (HT)',
                'source' => 'CHRONIC',
                'denominator' => 'POPULATION',
                'calculation' => 'RATE_PER_1000',
                'unit' => 'ต่อ 1,000 คน',
                'default_mode' => 'rate',
            ],
            'NCD' => [
                'label' => 'โรคไม่ติดต่อเรื้อรัง (NCD)',
                'source' => 'CHRONIC',
                'denominator' => 'POPULATION',
                'calculation' => 'RATE_PER_1000',
                'unit' => 'ต่อ 1,000 คน',
                'default_mode' => 'rate',
            ],
            'ELDERLY' => [
                'label' => 'ผู้สูงอายุ',
                'source' => 'PERSON',
                'denominator' => 'POPULATION',
                'calculation' => 'PERCENT',
                'unit' => 'ร้อยละ',
                'default_mode' => 'rate',
            ],
            'DISABLED' => [
                'label' => 'ผู้พิการ',
                'source' => 'DISABILITY',
                'denominator' => 'POPULATION',
                'calculation' => 'RATE_PER_1000',
                'unit' => 'ต่อ 1,000 คน',
                'default_mode' => 'rate',
            ],
            'ANC' => [
                'label' => 'การรับบริการฝากครรภ์ (ANC)',
                'source' => 'ANC',
                'denominator' => null,
                'calculation' => 'COUNT',
                'unit' => 'ครั้ง',
                'default_mode' => 'count',
            ],
            'SERVICE' => [
                'label' => 'การรับบริการ',
                'source' => 'SERVICE',
                'denominator' => 'POPULATION',
                'calculation' => 'RATE_PER_1000',
                'unit' => 'ครั้งต่อ 1,000 คน',
                'default_mode' => 'rate',
            ],
            'NCD_SCREEN' => [
                'label' => 'คัดกรอง NCD',
                'source' => 'NCDSCREEN',
                'denominator' => 'NCD_SCREEN_TARGET',
                'calculation' => 'PERCENT',
                'unit' => 'ร้อยละ',
                'default_mode' => 'rate',
            ],
        ];
    }

    public static function get(string $code): ?array
    {
        $code = strtoupper(trim($code));
        $all = self::all();
        if (!isset($all[$code])) {
            return null;
        }
        return ['code' => $code] + $all[$code];
    }

    public static function allowedCodes(): array
    {
        return array_keys(self::all());
    }

    public static function calculate(string $code, int $numerator, ?int $denominator): ?float
    {
        $def = self::get($code);
        if (!$def) return null;

        return match ($def['calculation']) {
            'COUNT' => (float)$numerator,
            'PERCENT' => ($denominator !== null && $denominator > 0)
                ? round(($numerator / $denominator) * 100, 2)
                : null,
            'RATE_PER_1000' => ($denominator !== null && $denominator > 0)
                ? round(($numerator / $denominator) * 1000, 2)
                : null,
            default => null,
        };
    }

    public static function canUseRateMode(string $code): bool
    {
        $def = self::get($code);
        return $def !== null && $def['calculation'] !== 'COUNT';
    }
}
?>