<?php

namespace Incodiy\Codiy\Library\Components\Table\Support;

/**
 * Final adjustments for DataTables response payloads
 */
final class ResultFinalizer
{
    /**
     * Decide whether to add DT_RowIndex and apply final tweaks
     *
     * @param array $dataRows
     * @param bool  $forceIndex
     * @return array [bool $addedIndex, array $rows]
     */
    public static function finalize(array $dataRows, bool $forceIndex = false): array
    {
        $needsIndex = $forceIndex || self::needsRowIndex($dataRows);
        if ($needsIndex) {
            $i = 1;
            foreach ($dataRows as &$row) {
                if (is_array($row) && !array_key_exists('DT_RowIndex', $row)) {
                    $row['DT_RowIndex'] = $i++;
                }
            }
            unset($row);
        }
        return [$needsIndex, $dataRows];
    }

    private static function needsRowIndex(array $rows): bool
    {
        // add index when rows are non-empty and no DT_RowIndex exists
        if (empty($rows)) return false;
        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists('DT_RowIndex', $row)) {
                return false;
            }
        }
        return true;
    }
}