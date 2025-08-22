<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * Phase 1: FilterHandlerTrait
 * - Centralizes filter processing logic via trait methods
 */
trait FilterHandlerTrait
{
    /**
     * Apply request filters via trait (real logic delegates to class helpers inside Datatables)
     *
     * @param mixed $modelData
     * @param array $filters
     * @param string $tableName
     * @param string $firstField
     * @return mixed
     */
    private function applyRequestFilters($modelData, array $filters, string $tableName, string $firstField)
    {
        // Keep behavior parity with legacy: if no filters, ensure a safe default where clause
        if (empty($filters) || !is_array($filters)) {
            \Log::info('❌ NO FILTERS APPLIED - returning default WHERE clause (trait)');
            return $modelData->where("{$tableName}.{$firstField}", '!=', null);
        }

        // Use existing private helpers from the class
        $processedFilters = $this->processFilters($filters);

        if (empty($processedFilters)) {
            \Log::info('❌ PROCESSED FILTERS EMPTY - returning default WHERE clause (trait)');
            return $modelData->where("{$tableName}.{$firstField}", '!=', null);
        }

        \DB::enableQueryLog();
        foreach ($processedFilters as $col => $val) {
            $qualified = (strpos($col, '.') === false) ? "{$tableName}.{$col}" : $col;

            // 1) Legacy explicit mapping from config (ensures base_group.* targets)
            $registry  = config('data-providers.model_registry', []);
            $tableCfg  = $registry[$tableName] ?? [];
            $colMap    = $tableCfg['custom_relationships']['columns'] ?? [];
            if (isset($colMap[$col]['select'])) {
                $qualified = $colMap[$col]['select'];
                if (!empty($colMap[$col]['joins']) && method_exists($this, 'applyRelationJoins')) {
                    $this->applyRelationJoins($modelData, $colMap[$col]['joins']);
                }
            }

            if (is_array($val)) {
                $flat = array_values(array_unique(array_filter($val, static function($v) {
                    return $v !== null && $v !== '';
                })));
                if (!empty($flat)) {
                    $modelData = $modelData->whereIn($qualified, $flat);
                }
            } else {
                // 2) Zero-config resolver (relation.column or alias like group_info)
                if (!isset($colMap[$col]['select']) && method_exists($this, 'resolveRelationColumn')) {
                    $baseModel = method_exists($modelData, 'getModel') ? $modelData->getModel() : null;
                    if ($baseModel) {
                        if (false !== strpos($col, '.')) {
                            [$mapped, $joins] = $this->resolveRelationColumn($baseModel, $col);
                            if (!empty($joins) && method_exists($this, 'applyRelationJoins')) { $this->applyRelationJoins($modelData, $joins); }
                            $qualified = $mapped;
                        } elseif (false !== strpos($col, '_')) {
                            [$rel, $field] = explode('_', $col, 2);
                            if (method_exists($baseModel, $rel)) {
                                $path = $rel . '.' . $field;
                                [$mapped, $joins] = $this->resolveRelationColumn($baseModel, $path);
                                if (!empty($joins) && method_exists($this, 'applyRelationJoins')) { $this->applyRelationJoins($modelData, $joins); }
                                $qualified = $mapped;
                            }
                        }
                    }
                }
                $modelData = $modelData->where($qualified, 'LIKE', '%' . $val . '%');
            }
        }
        $queries = \DB::getQueryLog();
        \Log::info('📊 SQL QUERIES WITH FILTERS (trait)', ['queries' => $queries]);

        return $modelData;
    }

    /**
     * Validate a single filter parameter using legacy rules via class method
     */
    private function validateFilterParam($name, $value): bool
    {
        return $this->isValidFilterParameter($name, $value);
    }
}