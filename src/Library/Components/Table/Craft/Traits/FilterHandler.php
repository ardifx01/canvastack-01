<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * FilterHandler (standalone helpers complementing FilterHandlerTrait)
 * - Adds operator-aware filtering and allowlist safety if host enables
 */
trait FilterHandler
{
    /**
     * Apply a single filter condition with operator semantics
     * Supported ops: =, !=, >, >=, <, <=, LIKE, IN, BETWEEN
     */
    protected function applySingleFilter($builder, string $qualifiedColumn, $value, string $op = 'LIKE')
    {
        $op = strtoupper($op);
        switch ($op) {
            case '=':
            case '!=':
            case '>':
            case '>=':
            case '<':
            case '<=':
                return $builder->where($qualifiedColumn, $op, $value);
            case 'IN':
                $vals = is_array($value) ? $value : [$value];
                return $builder->whereIn($qualifiedColumn, array_values(array_unique($vals)));
            case 'BETWEEN':
                if (is_array($value) && count($value) === 2) {
                    return $builder->whereBetween($qualifiedColumn, [$value[0], $value[1]]);
                }
                return $builder;
            case 'LIKE':
            default:
                $needle = is_array($value) ? reset($value) : (string) $value;
                return $builder->where($qualifiedColumn, 'LIKE', '%' . $needle . '%');
        }
    }
}