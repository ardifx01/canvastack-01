<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * RowAttributeTrait
 * - Handles row attributes (e.g., clickable rows) for DataTables
 */
trait RowAttributeTrait
{
    /**
     * Setup clickable row attributes if configured
     */
    protected function setupRowAttributesTrait($datatables, $data, string $tableName): void
    {
        $columnData = $data->datatables->columns ?? [];
        $attributes = ['class' => null, 'rlp' => null];

        if (isset($columnData[$tableName]) && !empty($columnData[$tableName]['clickable']) && count($columnData[$tableName]['clickable']) >= 1) {
            $attributes['class'] = 'row-list-url';
            $attributes['rlp'] = function ($model) {
                return diy_unescape_html(encode_id(intval($model->id)));
            };
        }

        $datatables->setRowAttr($attributes);
    }
}