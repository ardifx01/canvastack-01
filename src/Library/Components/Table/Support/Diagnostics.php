<?php

namespace Incodiy\Codiy\Library\Components\Table\Support;

/**
 * Diagnostics utilities for Table System (non-intrusive checks and reports)
 */
final class Diagnostics
{
    /**
     * Check if relational dot columns are missing from a result set definition
     *
     * @param array $columns    Column specs requested (e.g., ['group.group_info', 'username'])
     * @param array $resolved   Resolved/selected columns (aliases after processing)
     * @return bool
     */
    public static function missingRelationalCols(array $columns, array $resolved): bool
    {
        $requestedDots = array_values(array_filter($columns, function ($c) {
            return is_string($c) && false !== strpos($c, '.');
        }));
        if (empty($requestedDots)) return false;

        // normalize resolved aliases
        $resolvedAliases = [];
        foreach ($resolved as $alias => $spec) {
            if (is_string($alias)) {
                $resolvedAliases[] = $alias;
            } elseif (is_string($spec)) {
                // attempt to extract alias from `table.col as alias` or `table.col:Label`
                if (preg_match('/\bas\s+(\w+)$/i', $spec, $m)) {
                    $resolvedAliases[] = $m[1];
                }
            }
        }

        foreach ($requestedDots as $dot) {
            $alias = str_replace(['.', ' '], ['_', ''], $dot);
            if (!in_array($alias, $resolvedAliases, true)) {
                return true; // at least one requested dot column not present
            }
        }
        return false;
    }

    /**
     * Produce a small report context
     */
    public static function report(array $columns, array $resolved): array
    {
        return [
            'requested' => $columns,
            'resolved'  => $resolved,
        ];
    }
}