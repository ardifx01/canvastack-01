<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

trait OrderingHandlerTrait
{
    // Apply default ordering from config and frontend ordering
    private function applyOrderingTrait($datatables, $query, $config)
    {
        // Default ordering from config
        if (!empty($config['default_order']) && is_array($config['default_order'])) {
            foreach ($config['default_order'] as $orderConfig) {
                $column = $orderConfig['column'] ?? 'id';
                $direction = strtolower($orderConfig['direction'] ?? 'asc');
                $allowedDirections = ['asc', 'desc'];
                if (!in_array($direction, $allowedDirections, true)) {
                    $direction = 'asc';
                }
                $datatables->order(function ($q) use ($column, $direction) {
                    // Resolve relation.column using Eloquent metadata
                    $model = method_exists($q, 'getModel') ? $q->getModel() : null;
                    if ($model && false !== strpos($column, '.') && method_exists($this, 'resolveRelationColumn')) {
                        [$qualified, $joins] = $this->resolveRelationColumn($model, $column);
                        if (!empty($joins) && method_exists($this, 'applyRelationJoins')) { $this->applyRelationJoins($q, $joins); }
                        $q->orderBy($qualified, $direction);
                    } else {
                        $q->orderBy($column, $direction);
                    }
                });
            }
        }

        // Frontend ordering from DataTables
        $this->handleDataTablesOrderingTrait($datatables);
    }

    private function handleDataTablesOrderingTrait($datatables)
    {
        if (!function_exists('request')) {
            return; // in unit context without Laravel request
        }
        try {
            $request = request();
        } catch (\Throwable $e) {
            return; // no request container in unit
        }
        if ($request && method_exists($request, 'has') && $request->has('order') && is_array($request->get('order'))) {
            $orderData = $request->get('order')[0] ?? null;
            $columns = $request->get('columns', []);
            if ($orderData && isset($columns[$orderData['column']])) {
                $columnName = $columns[$orderData['column']]['data'];
                $direction = $orderData['dir'];
                $datatables->order(function ($q) use ($columnName, $direction) {
                    $qualified = $columnName;

                    // 1) Legacy explicit mapping from config
                    $registry  = config('data-providers.model_registry', []);
                    $baseTable = method_exists($q, 'from') ? $q->from : (property_exists($q, 'from') ? $q->from : '');
                    $tableCfg  = $registry[$baseTable] ?? [];
                    $colMap    = $tableCfg['custom_relationships']['columns'] ?? [];
                    if (isset($colMap[$columnName]['select'])) {
                        $qualified = $colMap[$columnName]['select'];
                        if (!empty($colMap[$columnName]['joins']) && method_exists($this, 'applyRelationJoins')) {
                            $this->applyRelationJoins($q, $colMap[$columnName]['joins']);
                        }
                    }

                    // 2) Zero-config relation resolver
                    if ($qualified === $columnName && method_exists($this, 'resolveRelationColumn')) {
                        $model = method_exists($q, 'getModel') ? $q->getModel() : null;
                        if ($model) {
                            if (false !== strpos($columnName, '.')) {
                                [$mapped, $joins] = $this->resolveRelationColumn($model, $columnName);
                                if (!empty($joins) && method_exists($this, 'applyRelationJoins')) { $this->applyRelationJoins($q, $joins); }
                                $qualified = $mapped;
                            } elseif (false !== strpos($columnName, '_')) {
                                [$rel, $field] = explode('_', $columnName, 2);
                                if (method_exists($model, $rel)) {
                                    $path = $rel . '.' . $field;
                                    [$mapped, $joins] = $this->resolveRelationColumn($model, $path);
                                    if (!empty($joins) && method_exists($this, 'applyRelationJoins')) { $this->applyRelationJoins($q, $joins); }
                                    $qualified = $mapped;
                                }
                            }
                        }
                    }

                    $q->orderBy($qualified, $direction);
                });
            }
        }
    }
}