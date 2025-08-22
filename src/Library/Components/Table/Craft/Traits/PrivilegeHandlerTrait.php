<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * Phase 1: PrivilegeHandlerTrait
 * - Exposes privilege filtering via trait API; keeps legacy logic
 */
trait PrivilegeHandlerTrait
{
    /**
     * Filter action list by privileges via trait
     *
     * @param array $actionList
     * @param array $privileges
     * @return array
     */
    private function filterActionsByPrivilegeTrait(array $actionList, array $privileges): array
    {
        return $this->filterActionsByPrivileges($actionList, $privileges);
    }
}