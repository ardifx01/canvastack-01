<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * FormatHandlerTrait
 * - Handles formatted column processing (number/date formatting, etc.)
 */
trait FormatHandlerTrait
{
    /**
     * Apply format_data rules to datatable columns
     */
    protected function processFormattedColumnsTrait($datatables, $data, string $tableName): void
    {
        if (!isset($data->datatables->columns) ||
            !isset($data->datatables->columns[$tableName]) ||
            empty($data->datatables->columns[$tableName]['format_data'])) {
            \Log::info('No format_data configuration for table', ['table' => $tableName]);
            return;
        }

        $formatData = $data->datatables->columns[$tableName]['format_data'];
        if (!is_array($formatData)) { return; }

        foreach ($formatData as $field => $format) {
            $datatables->editColumn($format['field_name'], function ($row) use ($field, $format) {
                if ($field !== $format['field_name']) { return null; }
                $attributes = method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row;
                if (empty($attributes[$field])) { return null; }
                return diy_format(
                    $attributes[$field],
                    $format['decimal_endpoint'] ?? 0,
                    $format['separator'] ?? '.',
                    $format['format_type'] ?? 'number'
                );
            });
        }
    }
}