<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * PrivilegeHandler (standalone)
 * - Reusable privilege filtering helpers
 */
trait PrivilegeHandler
{
    /**
     * Basic privilege filter: expects privilege keys equal to action keys
     */
    protected function filterActionsByPrivileges(array $actions, array $privileges): array
    {
        if (empty($privileges)) { return $actions; }
        $allowed = array_map('strval', array_keys(array_filter($privileges, static function ($v) { return (bool)$v; })));
        $out = [];
        foreach ($actions as $a) {
            $key = is_array($a) ? ($a['key'] ?? null) : (string) $a;
            if ($key === null || in_array($key, $allowed, true)) { $out[] = $a; }
        }
        return $out;
    }
}