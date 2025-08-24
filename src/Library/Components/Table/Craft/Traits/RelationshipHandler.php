<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * RelationshipHandler (standalone)
 * - High-level helpers to wire declarative relations and dot columns
 */
trait RelationshipHandler
{
    /**
     * Apply declared relations and dot columns into base query by adding selects and joins
     */
    protected function applyDeclaredRelations($query, Model $baseModel, array $declaredRelations = [], array $dotColumns = []): void
    {
        // Add select mappings for dot columns
        if (!empty($dotColumns) && method_exists($this, 'mapDotColumnsToSelects')) {
            $selects = $this->mapDotColumnsToSelects($query, $baseModel, array_keys($dotColumns));
            if (!empty($selects)) {
                $query->addSelect($selects);
            }
        }
    }
}