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
    // Lazily join via resolver; this method remains as compatibility no-op
    private function setupRelationshipsTrait($query, array $config)
    {
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

    // Apply joins once
    private function applyRelationJoins($builder, array $joins): void
    {
        if (!property_exists($this, 'diyAppliedJoins')) { $this->diyAppliedJoins = []; }
        foreach ($joins as $j) {
            $sig = strtolower(($j['type'] ?? 'left') . '|' . $j['table'] . '|' . $j['first'] . '|' . $j['second']);
            if (in_array($sig, $this->diyAppliedJoins, true)) continue;
            $type = strtolower($j['type'] ?? 'left');
            if ('left' === $type) { $builder->leftJoin($j['table'], $j['first'], '=', $j['second']); }
            else { $builder->join($j['table'], $j['first'], '=', $j['second']); }
            $this->diyAppliedJoins[] = $sig;
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