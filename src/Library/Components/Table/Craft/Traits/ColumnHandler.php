<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * ColumnHandler
 * - Column utilities: raw columns, ordering defaults, visibility helpers
 */
trait ColumnHandler
{
    /**
     * Normalize raw columns array from config and register to Yajra instance
     */
    protected function setupRawColumns($datatables, array $config): void
    {
        $raw = $config['raw_columns'] ?? ($config['rawColumns'] ?? []);
        if (empty($raw)) { return; }
        if (method_exists($datatables, 'rawColumns')) {
            $datatables->rawColumns($raw);
        }
    }

    /**
     * Apply safe default ordering when none provided
     */
    protected function setupOrdering($datatables, array $config, string $fallbackColumn = 'id'): void
    {
        $defaults = $config['default_order'] ?? [];
        if (!empty($defaults)) { return; } // handled elsewhere
        if (method_exists($datatables, 'order')) {
            $datatables->order(function ($q) use ($fallbackColumn) {
                $q->orderBy($fallbackColumn, 'desc');
            });
        }
    }

    /**
     * Hide columns by blacklist
     */
    protected function hideBlacklistedColumns(array $columns, array $blacklist): array
    {
        if (empty($blacklist)) { return $columns; }
        return array_values(array_filter($columns, static function($c) use ($blacklist) {
            $name = is_array($c) ? ($c['data'] ?? $c['name'] ?? '') : (string) $c;
            foreach ($blacklist as $blk) {
                if ($blk === $name) { return false; }
            }
            return true;
        }));
    }
}