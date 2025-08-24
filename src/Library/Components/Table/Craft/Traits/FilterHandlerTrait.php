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
     * @param mixed  $modelData
     * @param array  $filters
     * @param string $tableName
     * @param string $firstField
     * @return mixed
     */
    private function applyRequestFilters($modelData, array $filters, string $tableName, string $firstField)
    {
        // Keep behavior parity with legacy: if no filters, ensure a safe default where clause
        if (empty($filters) || !is_array($filters)) {
            \Log::info('❌ NO FILTERS APPLIED - returning default WHERE clause (trait)');
            
            // Special handling for temp tables that don't have 'id' column
            if (strpos($tableName, 'temp_') === 0) {
                // For temp tables, don't add WHERE clause as they might not have the expected primary key
                \Log::info('🔧 Skipping default WHERE clause for temp table', [
                    'table' => $tableName,
                    'first_field' => $firstField
                ]);
                return $modelData;
            }
            
            return $modelData->where("{$tableName}.{$firstField}", '!=', null);
        }

        // Use trait-local processor (kept conservative to avoid regressions)
        $processedFilters = $this->processFilters($filters, $tableName);

        if (empty($processedFilters)) {
            \Log::info('❌ PROCESSED FILTERS EMPTY - returning default WHERE clause (trait)');
            
            // Special handling for temp tables that don't have 'id' column
            if (strpos($tableName, 'temp_') === 0) {
                \Log::info('🔧 Skipping default WHERE clause for temp table (processed filters empty)', [
                    'table' => $tableName,
                    'first_field' => $firstField
                ]);
                return $modelData;
            }
            
            return $modelData->where("{$tableName}.{$firstField}", '!=', null);
        }

        \DB::enableQueryLog();
        foreach ($processedFilters as $col => $payload) {
            // payload can be scalar, array, or [value, op]
            $value = $payload;
            $op    = 'LIKE';
            if (is_array($payload) && array_key_exists('value', $payload)) {
                $value = $payload['value'];
                $op    = strtoupper((string)($payload['op'] ?? 'LIKE'));
            } elseif (is_array($payload) && count($payload) === 2 && isset($payload[0])) {
                $value = $payload[0];
                $op    = strtoupper((string)($payload[1] ?? 'LIKE'));
            }

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

            if (is_array($value) && !array_key_exists('value', $payload)) {
                $flat = array_values(array_unique(array_filter($value, static function ($v) {
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

                // Operator-aware single filter if host includes helper (optional)
                if (method_exists($this, 'applySingleFilter')) {
                    $modelData = $this->applySingleFilter($modelData, $qualified, $value, $op);
                } else {
                    $needle = is_array($value) ? reset($value) : (string) $value;
                    $modelData = $modelData->where($qualified, 'LIKE', '%' . $needle . '%');
                }
            }
        }
        $queries = \DB::getQueryLog();
        \Log::info('📊 SQL QUERIES WITH FILTERS (trait)', ['queries' => $queries]);

        return $modelData;
    }

    /**
     * Delegate conditions application to legacy method to avoid behavior change.
     * This keeps refactor safe while centralizing the entry point.
     */
    private function applyConditionsTrait($builder, $data, string $tableName)
    {
        if (method_exists($this, 'applyConditions')) {
            // Call legacy/class implementation to preserve behavior
            return $this->applyConditions($builder, $data, $tableName);
        }
        // No-op if legacy method missing
        return $builder;
    }

    /**
     * Process raw filters: sanitize keys, drop reserved/invalid, keep structure.
     * Very conservative to avoid regressions.
     */
    private function processFilters(array $filters, string $tableName): array
    {
        $reserved = [];
        $allowed  = null; // null means allow all
        try {
            if (function_exists('config')) {
                $reserved = (array) config('datatables.reserved_parameters', []);
                $allowed  = config('datatables.allowed_filters', null);
                if (is_array($allowed)) {
                    // sanitize allowed entries
                    $allowed = array_values(array_unique(array_filter(array_map(function ($n) {
                        return preg_replace('/[^A-Za-z0-9_\\.]/', '', (string)$n);
                    }, $allowed), function ($n) { return $n !== ''; })));
                } else {
                    $allowed = null;
                }
            }
        } catch (\Throwable $e) {}

        $out = [];
        foreach ($filters as $name => $value) {
            // Skip reserved and empty values
            if (in_array($name, $reserved, true)) { continue; }
            if (is_array($value)) {
                $allEmpty = true;
                foreach ($value as $v) { if ($v !== null && $v !== '') { $allEmpty = false; break; } }
                if ($allEmpty) { continue; }
            } else {
                if ($value === null || $value === '') { continue; }
            }

            // Basic name sanitation: allow letters, numbers, dot, underscore
            $safeName = preg_replace('/[^A-Za-z0-9_\.]/', '', (string)$name);
            if ($safeName === '') { continue; }

            // Enforce allowlist when provided
            if (is_array($allowed) && !in_array($safeName, $allowed, true)) { continue; }

            // Allow operators via structure [value, op] or ['value'=>x,'op'=>y]
            if (is_array($value) && array_key_exists('value', $value)) {
                $out[$safeName] = [
                    'value' => $value['value'],
                    'op'    => strtoupper((string)($value['op'] ?? 'LIKE')),
                ];
                continue;
            }

            $out[$safeName] = $value;
        }
        return $out;
    }

    /**
     * Validate a single filter parameter using legacy rules via class method
     */
    private function validateFilterParam($name, $value): bool
    {
        return $this->isValidFilterParameter($name, $value);
    }
}