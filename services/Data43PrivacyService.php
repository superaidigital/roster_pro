<?php
final class Data43PrivacyService
{
    public const SMALL_CELL_MAX = 4;
    public const DEFAULT_MIN_DENOMINATOR = 20;

    /**
     * Primary + complementary suppression for a single peer group.
     *
     * Primary suppression:
     * - numerator 1..4
     * - optional small denominator 1..(minDenominator-1)
     *
     * Complementary suppression:
     * - if any row in the group is primary-suppressed, suppress at least one
     *   additional non-zero eligible row so the hidden value cannot be
     *   reconstructed from an exact group/parent total.
     */
    public static function suppressRows(
        array $rows,
        string $countKey = 'metric_value',
        ?string $denominatorKey = null,
        int $minDenominator = 0
    ): array {
        $primaryIndexes = [];
        $eligible = [];

        foreach ($rows as $i => $row) {
            $count = max(0, (int)($row[$countKey] ?? 0));
            $rows[$i]['privacy_suppressed'] = false;
            $rows[$i]['privacy_reason'] = null;

            $smallDenominator = false;
            if ($denominatorKey !== null && $minDenominator > 0) {
                $denominator = max(0, (int)($row[$denominatorKey] ?? 0));
                $smallDenominator = $denominator > 0 && $denominator < $minDenominator;
            }

            if ($count > 0 && $count <= self::SMALL_CELL_MAX) {
                $rows[$i]['privacy_suppressed'] = true;
                $rows[$i]['privacy_reason'] = 'SMALL_CELL';
                $primaryIndexes[] = $i;
            } elseif ($smallDenominator) {
                $rows[$i]['privacy_suppressed'] = true;
                $rows[$i]['privacy_reason'] = 'SMALL_DENOMINATOR';
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

    /**
     * Spatial suppression must be applied within each parent geography.
     * Example: village rows are grouped by province+district+subdistrict,
     * not once across the whole province, otherwise a parent subtotal could
     * reveal a hidden village value.
     */
    public static function suppressSpatialRows(
        array $rows,
        string $countKey = 'metric_value',
        ?string $denominatorKey = null,
        int $minDenominator = 0
    ): array {
        if (!$rows) return [];

        $groups = [];
        foreach ($rows as $index => $row) {
            $key = self::spatialParentKey($row);
            $groups[$key][] = ['index'=>$index,'row'=>$row];
        }

        $result = $rows;
        foreach ($groups as $items) {
            $peerRows = array_map(static fn(array $item): array => $item['row'], $items);
            $suppressed = self::suppressRows($peerRows, $countKey, $denominatorKey, $minDenominator);

            foreach ($items as $offset => $item) {
                $result[$item['index']] = $suppressed[$offset];
            }
        }

        return array_values($result);
    }

    private static function spatialParentKey(array $row): string
    {
        $cw = (string)($row['changwat_code'] ?? '');
        $ap = (string)($row['ampur_code'] ?? '');
        $tb = (string)($row['tambon_code'] ?? '');
        $vl = (string)($row['village_code'] ?? '');

        if ($vl !== '') return implode('|', ['VILLAGE',$cw,$ap,$tb]);
        if ($tb !== '') return implode('|', ['TAMBON',$cw,$ap]);
        if ($ap !== '') return implode('|', ['AMPUR',$cw]);
        return 'CHANGWAT|ROOT';
    }

    public static function publicCount(array $row, string $countKey = 'metric_value'): ?int
    {
        if (!empty($row['privacy_suppressed'])) return null;
        return max(0, (int)($row[$countKey] ?? 0));
    }

    public static function displayCount(array $row, string $countKey = 'metric_value'): string
    {
        if (!empty($row['privacy_suppressed'])) return 'ปกปิด';
        return number_format(max(0, (int)($row[$countKey] ?? 0)));
    }
}
?>