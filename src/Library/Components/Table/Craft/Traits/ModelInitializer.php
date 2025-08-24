<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * ModelInitializer (standalone helpers)
 * - Complements ModelInitializerTrait with utility methods
 */
trait ModelInitializer
{
    protected function isTempTable(string $table): bool
    {
        return strpos($table, 'temp_') === 0;
    }
}