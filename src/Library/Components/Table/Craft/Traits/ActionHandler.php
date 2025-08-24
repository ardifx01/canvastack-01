<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * ActionHandler
 * - Provides safe action list determination and basic action utilities
 * - Designed to be non-invasive: can be mixed into Datatables orchestrator later
 */
trait ActionHandler
{
    /**
     * Determine final action list with pass-by-reference safety
     * Accepts: true (use defaults), array overrides, or empty
     */
    protected function determineActionList($actions): array
    {
        if ($actions === true) {
            return method_exists($this, 'getDefaultActions')
                ? (array) $this->getDefaultActions()
                : ['view', 'insert', 'edit', 'delete'];
        }

        if (is_array($actions)) {
            // Fix: Only variables should be passed by reference
            $defaults  = method_exists($this, 'getDefaultActions') ? (array) $this->getDefaultActions() : [];
            $overrides = $actions;
            return function_exists('array_merge_recursive_distinct')
                ? array_merge_recursive_distinct($defaults, $overrides)
                : array_values(array_unique(array_merge($defaults, $overrides)));
        }

        return [];
    }

    /**
     * Route→action mapping. Default matches Datatables current behavior:
     *   'index,show,view' => 'view', 'create,insert' => 'insert', 'edit,modify,update' => 'edit', 'destroy,delete' => 'delete'
     */
    protected function getRouteActionMapping(): array
    {
        if (function_exists('config')) {
            $map = (array) config('datatables.route_action_mapping', []);
            if (!empty($map)) { return $map; }
        }
        return [
            'index,show,view'   => 'view',
            'create,insert'     => 'insert',
            'edit,modify,update'=> 'edit',
            'destroy,delete'    => 'delete',
        ];
    }

    /**
     * Simple renderer for action buttons (Blade/HTML). Non-breaking helper.
     */
    protected function renderActionButtons(array $actions, array $row, array $privileges = []): string
    {
        // If orchestrator exposes privilege filter, use it
        if (method_exists($this, 'filterActionsByPrivileges')) {
            $actions = $this->filterActionsByPrivileges($actions, $privileges);
        }

        $map = $this->getRouteActionMapping();
        $html = [];
        foreach ($actions as $action) {
            $key = is_array($action) ? ($action['key'] ?? 'view') : (string) $action;
            $label = is_array($action) ? ($action['label'] ?? ucfirst($key)) : ucfirst($key);
            $method = is_array($action) ? ($action['method'] ?? 'GET') : 'GET';
            $url = '#';
            if (is_array($action) && !empty($action['url'])) {
                $url = $action['url'];
            } elseif (isset($row['id'])) {
                // Find first mapping key that contains our action key
                $routeKey = $key;
                foreach ($map as $routes => $act) {
                    if ($act === $key) { $routeKey = explode(',', $routes)[0]; break; }
                }
                $url = "/{$routeKey}/{$row['id']}";
            }
            $html[] = '<a class="btn btn-xs btn-primary" data-method="' . htmlspecialchars($method) . '" href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
        }
        return implode(' ', $html);
    }

    /**
     * Compose action config and data for addActionColumn, mirroring current behavior.
     */
    protected function composeActionData($modelData, array $actionConfig, $data): array
    {
        $defaultActions = method_exists($this, 'getDefaultActions') ? (array) $this->getDefaultActions() : ['view','insert','edit','delete'];
        $actionList = $defaultActions;
        if (isset($actionConfig['list']) && is_array($actionConfig['list']) && !empty($actionConfig['list'])) {
            $actionList = $actionConfig['list'];
        }

        $removed = $this->determineRemovedActionsCompat($actionConfig, $data);

        return [
            'model' => $modelData,
            'current_url' => function_exists('diy_current_url') ? diy_current_url() : (request()->fullUrl() ?? ''),
            'action' => [
                'data' => $actionList,
                'removed' => $removed,
            ],
        ];
    }

    /**
     * Backward-compatible resolver for removed actions.
     */
    protected function determineRemovedActionsCompat(array $actionConfig, $data): array
    {
        $baseRemoved = $data->datatables->button_removed ?? [];
        if (method_exists($this, 'set_module_privileges')) {
            $priv = $this->set_module_privileges();
            if (($priv['role_group'] ?? 0) <= 1) {
                return $baseRemoved;
            }
        }
        if (!empty($actionConfig['removed'])) {
            return $actionConfig['removed'];
        }
        return is_array($baseRemoved) ? $baseRemoved : [];
    }
}