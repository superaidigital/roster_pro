<?php
final class Data43PrivacyService
{
    public const SMALL_CELL_MAX = 4;

    /**
     * Primary + complementary suppression.
     *
     * Rows with count 1..4 are suppressed. If a group contains any primary
     * suppression, one additional non-zero row is suppressed (the smallest
     * eligible row) so a hidden value cannot be reconstructed by subtraction
     * from an exact parent total.
     */
    public static function suppressRows(array $rows, string $countKey = 'metric_value'): array
    {
        $primaryIndexes = [];
        $eligible = [];

        foreach ($rows as $i => $row) {
            $count = max(0, (int)($row[$countKey] ?? 0));
            $rows[$i]['privacy_suppressed'] = false;
            $rows[$i]['privacy_reason'] = null;

            if ($count > 0 && $count <= self::SMALL_CELL_MAX) {
                $rows[$i]['privacy_suppressed'] = true;
                $rows[$i]['privacy_reason'] = 'SMALL_CELL';
                $primaryIndexes[] = $i;
            } elseif ($count > self::SMALL_CELL_MAX) {
                $eligible[$i] = $count;
            }
        }

        if ($primaryIndexes && $eligible) {
            asort($eligible, SORT_NUMERIC);
            $complementaryIndex = array_key_first($eligible);
            if ($complementaryIndex !== null) {
                $rows[$complementaryIndex]['privacy_suppressed'] = true;
                $rows[$complementaryIndex]['privacy_reason'] = 'COMPLEMENTARY';
            }
        }

        return $rows;
    }

    public static function publicCount(array $row, string $countKey = 'metric_value'): ?int
    {
        if (!empty($row['privacy_suppressed'])) return null;
        return max(0, (int)($row[$countKey] ?? 0));
    }

    public static function displayCount(array $row, string $countKey = 'metric_value'): string
    {
        if (!empty($row['privacy_suppressed'])) return '<5 / suppressed';
        return number_format(max(0, (int)($row[$countKey] ?? 0)));
    }
}
?>