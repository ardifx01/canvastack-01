<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

trait RelationshipHandlerTrait
{
    // Setup relationships via dot column mapping and safe joins
    private function setupRelationshipsTrait($query, array $config)
    {
        try {
            // Debug logging
            if (config('datatables.debug', false)) {
                \Log::info("🔧 setupRelationshipsTrait called", [
                    'config_keys' => array_keys($config),
                    'dot_columns' => $config['dot_columns'] ?? 'not_set',
                    'declared_relations' => $config['declared_relations'] ?? 'not_set',
                    'columns' => $config['columns'] ?? 'not_set'
                ]);
            }
            
            // Resolve base model and table
            $model = method_exists($query, 'getModel') ? $query->getModel() : null;
            $baseTable = method_exists($model, 'getTable') ? $model->getTable() : null;
            if (!$model || !$baseTable) { 
                if (config('datatables.debug', false)) {
                    \Log::warning("⚠️ setupRelationshipsTrait: No model or table", [
                        'has_model' => $model !== null,
                        'has_table' => $baseTable !== null
                    ]);
                }
                return $query; 
            }

            // Collect dot columns from config. Support both associative ['path' => 'alias'] and indexed string forms.
            $dotColumnsAssoc = [];
            $dotColumnsList  = [];
            if (!empty($config['dot_columns']) && is_array($config['dot_columns'])) {
                foreach ($config['dot_columns'] as $k => $v) {
                    if (is_string($k) && false !== strpos($k, '.')) {
                        $alias = (is_string($v) && $v !== '') ? $v : str_replace('.', '_', $k);
                        $dotColumnsAssoc[$k] = $alias;
                    } elseif (is_string($v)) {
                        $entry = $v;
                        $path = $entry; $alias = null;
                        if (stripos($entry, ' as ') !== false) {
                            [$path, $alias] = preg_split('/\s+as\s+/i', $entry, 2);
                            $path = trim($path); $alias = trim($alias);
                        }
                        if ($path && false !== strpos($path, '.')) {
                            if (!$alias) { $alias = str_replace('.', '_', $path); }
                            $dotColumnsAssoc[$path] = $alias;
                        }
                    }
                }
            }

            // If none provided, try to infer from columns definition if available
            if (empty($dotColumnsAssoc) && !empty($config['columns']) && is_array($config['columns'])) {
                foreach ($config['columns'] as $col) {
                    $name = is_array($col) ? ($col['name'] ?? $col['data'] ?? null) : (is_string($col) ? $col : null);
                    if (!$name) { continue; }
                    // Inference 1: dot notation already provided in columns
                    if (false !== strpos($name, '.')) {
                        $dotColumnsAssoc[$name] = str_replace('.', '_', $name);
                        continue;
                    }
                    // Inference 2: underscore alias like relation_field => relation.field
                    // Example: group_name => group.name, group_info => group.info
                    if (false !== strpos($name, '_')) {
                        $parts = explode('_', $name, 2);
                        $rel   = $parts[0] ?? '';
                        $field = $parts[1] ?? '';
                        if ($rel && $field && method_exists($model, $rel)) {
                            $path = $rel . '.' . $field; // keep underscores inside field if any
                            $dotColumnsAssoc[$path] = $name; // alias uses original column name
                        }
                    }
                }
            }

            if (empty($dotColumnsAssoc)) { return $query; }

            // Ensure base table columns are selected (avoid dropping existing selects)
            try {
                // If no explicit select, add baseTable.*
                $query->addSelect($baseTable . '.*');
            } catch (\Throwable $e) {
                try { $query->select($baseTable . '.*'); } catch (\Throwable $e2) { /* noop */ }
            }

            // Apply joins and add selects with explicit aliases derived from mapping
            foreach ($dotColumnsAssoc as $path => $alias) {
                try {
                    if (method_exists($this, 'resolveRelationColumn')) {
                        [$qualified, $joins] = $this->resolveRelationColumn($model, $path);
                        if (!empty($joins) && method_exists($this, 'applyRelationJoins')) { $this->applyRelationJoins($query, $joins); }
                        // Add safe select with alias
                        $query->addSelect($qualified . ' as ' . $alias);
                        // Special-case guard: group.id to satisfy downstream needs when base_user_group join exists
                        // This prevents SQLSTATE[42S22] on base_user_group.id selections in legacy paths
                        if (strpos($path, 'group.') === 0 && !in_array($alias, ['group_id', 'groupid'], true)) {
                            try {
                                // Attempt to also select group.id as group_id if relation provides it
                                $groupIdQualified = preg_replace('/\.[^.]+$/', '.id', $qualified);
                                $query->addSelect($groupIdQualified . ' as group_id');
                            } catch (\Throwable $g) { /* noop */ }
                        }
                    }
                } catch (\Throwable $e) { /* continue mapping others */ }
            }

            // If any group.* relation columns are requested, ensure pivot fields are selected too
            try {
                $needsGroupPivot = false;
                foreach (array_keys($dotColumnsAssoc) as $p) {
                    if (strpos($p, 'group.') === 0) { $needsGroupPivot = true; break; }
                }
                if ($needsGroupPivot && method_exists($model, 'group')) {
                    $relation = $model->group();
                    if ($relation instanceof BelongsToMany) {
                        $pivotTable = $relation->getTable();
                        // Guard against duplicate selects
                        try { $query->addSelect($pivotTable . '.group_id as pivot_group_id'); } catch (\Throwable $e) { /* noop */ }
                        try { $query->addSelect($pivotTable . '.id as base_user_group_id'); } catch (\Throwable $e) { /* noop */ }
                    }
                }
            } catch (\Throwable $e) { /* noop */ }
        } catch (\Throwable $e) {
            // Keep legacy behavior if anything goes wrong
            // Intentionally silent to avoid breaking legacy path
        }

        return $query;
    }

    // Resolve relation.column and return [qualified, joins]
    private function resolveRelationColumn(Model $baseModel, string $path): array
    {
        $parts = explode('.', $path);
        $column = array_pop($parts);
        $currentModel = $baseModel;
        $currentTable = $currentModel->getTable();
        $joins = [];

        foreach ($parts as $relationName) {
            if (!method_exists($currentModel, $relationName)) {
                return [$currentTable . '.' . $column, $joins];
            }
            $relation = $currentModel->{$relationName}();
            if (!($relation instanceof \Illuminate\Database\Eloquent\Relations\Relation)) {
                return [$currentTable . '.' . $column, $joins];
            }
            $related = $relation->getRelated();
            $relatedTable = $related->getTable();

            if ($relation instanceof BelongsTo) {
                $foreignQualified = method_exists($relation, 'getQualifiedForeignKeyName') ? $relation->getQualifiedForeignKeyName() : $currentTable . '.' . $relation->getForeignKeyName();
                $ownerQualified   = method_exists($relation, 'getQualifiedOwnerKeyName') ? $relation->getQualifiedOwnerKeyName() : $relatedTable . '.' . ($relation->getOwnerKeyName() ?? $related->getKeyName());
                $joins[] = ['type' => 'left', 'table' => $relatedTable, 'first' => $foreignQualified, 'second' => $ownerQualified];
                $currentModel = $related; $currentTable = $relatedTable;
            } elseif ($relation instanceof HasOne || $relation instanceof HasMany) {
                $foreignKey = method_exists($relation, 'getForeignKeyName') ? $relation->getForeignKeyName() : $currentModel->getForeignKey();
                $relatedQualified = $relatedTable . '.' . $foreignKey;
                $parentQualified  = method_exists($relation, 'getQualifiedParentKeyName') ? $relation->getQualifiedParentKeyName() : $currentTable . '.' . $currentModel->getKeyName();
                $joins[] = ['type' => 'left', 'table' => $relatedTable, 'first' => $relatedQualified, 'second' => $parentQualified];
                $currentModel = $related; $currentTable = $relatedTable;
            } elseif ($relation instanceof BelongsToMany) {
                $pivotTable = $relation->getTable();
                $fkQualified = method_exists($relation, 'getQualifiedForeignPivotKeyName') ? $relation->getQualifiedForeignPivotKeyName() : $pivotTable . '.' . $relation->getForeignPivotKeyName();
                $parentQualified = method_exists($relation, 'getQualifiedParentKeyName') ? $relation->getQualifiedParentKeyName() : $currentTable . '.' . $currentModel->getKeyName();
                $relatedQualified = method_exists($relation, 'getQualifiedRelatedPivotKeyName') ? $relation->getQualifiedRelatedPivotKeyName() : $pivotTable . '.' . $relation->getRelatedPivotKeyName();
                $ownerQualified = method_exists($relation, 'getQualifiedRelatedKeyName') ? $relation->getQualifiedRelatedKeyName() : $relatedTable . '.' . $related->getKeyName();
                $joins[] = ['type' => 'left', 'table' => $pivotTable, 'first' => $fkQualified, 'second' => $parentQualified];
                $joins[] = ['type' => 'left', 'table' => $relatedTable, 'first' => $ownerQualified, 'second' => $relatedQualified];
                $currentModel = $related; $currentTable = $relatedTable;
            } elseif ($relation instanceof HasOneThrough || $relation instanceof HasManyThrough) {
                $firstKey = method_exists($relation, 'getQualifiedFirstKeyName') ? $relation->getQualifiedFirstKeyName() : null;
                $localKey = method_exists($relation, 'getQualifiedLocalKeyName') ? $relation->getQualifiedLocalKeyName() : null;
                $secondKey = method_exists($relation, 'getQualifiedSecondKeyName') ? $relation->getQualifiedSecondKeyName() : null;
                $relatedKey = method_exists($relation, 'getQualifiedRelatedKeyName') ? $relation->getQualifiedRelatedKeyName() : ($relatedTable . '.' . $related->getKeyName());
                $throughTable = method_exists($relation, 'getThroughParent') ? $relation->getThroughParent()->getTable() : null;
                if ($throughTable && $firstKey && $localKey) { $joins[] = ['type' => 'left', 'table' => $throughTable, 'first' => $firstKey, 'second' => $localKey]; }
                if ($throughTable && $secondKey && $relatedKey) { $joins[] = ['type' => 'left', 'table' => $relatedTable, 'first' => $relatedKey, 'second' => $secondKey]; }
                $currentModel = $related; $currentTable = $relatedTable;
            } else {
                return [$currentTable . '.' . $column, $joins];
            }
        }
        return [$currentTable . '.' . $column, $joins];
    }

    // Apply joins once with extra guard against duplicate table joins
    private function applyRelationJoins($builder, array $joins): void
    {
        if (!property_exists($this, 'diyAppliedJoins')) { $this->diyAppliedJoins = []; }

        // Inspect existing joins on the builder to prevent duplicate table/alias
        $existingTables = [];
        try {
            $query = method_exists($builder, 'getQuery') ? $builder->getQuery() : null;
            if ($query && property_exists($query, 'joins') && is_array($query->joins)) {
                foreach ($query->joins as $join) {
                    // Laravel JoinClause exposes table name via ->table
                    if (is_object($join) && property_exists($join, 'table')) {
                        $existingTables[] = strtolower((string) $join->table);
                    }
                }
            }
        } catch (\Throwable $e) { /* noop */ }

        foreach ($joins as $j) {
            $table = strtolower($j['table']);
            $sig = strtolower(($j['type'] ?? 'left') . '|' . $table . '|' . $j['first'] . '|' . $j['second']);

            // Skip if already applied by our own guard
            if (in_array($sig, $this->diyAppliedJoins, true)) { continue; }
            // Skip if builder already has a join for the same table/alias
            if (in_array($table, $existingTables, true)) { continue; }

            $type = strtolower($j['type'] ?? 'left');
            if ('left' === $type) { $builder->leftJoin($j['table'], $j['first'], '=', $j['second']); }
            else { $builder->join($j['table'], $j['first'], '=', $j['second']); }

            // Track both signature and table to avoid re-adding in later phases
            $this->diyAppliedJoins[] = $sig;
            $existingTables[] = $table;
        }
    }

    // Declarative Relations API: expose a protected helper to map columns into selects and joins
    protected function mapDotColumnsToSelects($baseQuery, Model $baseModel, array $dotColumns): array
    {
        $selects = [];
        $allJoins = [];
        foreach ($dotColumns as $dot) {
            [$qualified, $joins] = $this->resolveRelationColumn($baseModel, $dot);
            $selects[] = $qualified . ' as ' . str_replace('.', '_', $dot);
            $allJoins = array_merge($allJoins, $joins);
        }
        $this->applyRelationJoins($baseQuery, $allJoins);
        return $selects;
    }
}