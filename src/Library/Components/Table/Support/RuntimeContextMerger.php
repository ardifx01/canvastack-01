<?php

namespace Incodiy\Codiy\Library\Components\Table\Support;

use Incodiy\Codiy\Library\Components\Table\Craft\DatatableRuntime;

/**
 * Merge runtime-declared relations and dot columns from DatatableRuntime
 */
final class RuntimeContextMerger
{
    /**
     * Merge declared_relations and dot_columns into requestConfig when available
     *
     * @param array $requestConfig
     * @return array
     */
    public static function merge(array $requestConfig): array
    {
        $tableName = $requestConfig['difta']['name'] ?? null;
        if (!$tableName) {
            return $requestConfig;
        }

        try {
            $rt = DatatableRuntime::get($tableName);
            if ($rt && isset($rt->datatables)) {
                if (!empty($rt->datatables->declared_relations) && empty($requestConfig['declared_relations'])) {
                    $requestConfig['declared_relations'] = $rt->datatables->declared_relations;
                }
                if (empty($requestConfig['dot_columns'])) {
                    if (!empty($rt->datatables->dot_columns) && is_array($rt->datatables->dot_columns)) {
                        $requestConfig['dot_columns'] = $rt->datatables->dot_columns;
                    } else {
                        // conservative fallback example for common 'group' relation
                        $requestConfig['dot_columns'] = [
                            'group.group_info as group_info',
                            'group.group_name as group_name'
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // keep silent on runtime merge failure
        }

        return $requestConfig;
    }
}