<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * StatusHandlerTrait
 * - Provides processing of status-like columns with safe guards
 */
trait StatusHandlerTrait
{
    /**
     * Process status columns with special formatting
     * Mirrors legacy behavior; hook point for future customization
     */
    protected function processStatusColumnsTrait($datatables, $modelData): void
    {
        $statusColumns = [
            'flag_status' => function($model) { return diy_unescape_html(diy_form_internal_flag_status($model->flag_status)); },
            'active' => function($model) { return diy_form_set_active_value($model->active); },
            'update_status' => function($model) { return diy_form_set_active_value($model->update_status); },
            'request_status' => function($model) { return diy_form_request_status(true, $model->request_status); },
            'ip_address' => function($model) { return $model->ip_address === '::1' ? diy_form_get_client_ip() : $model->ip_address; }
        ];

        try {
            $modelResults = $modelData->get();
            if (!is_object($modelResults) && !is_array($modelResults)) { return; }
            foreach ($modelResults as $model) {
                foreach ($statusColumns as $column => $callback) {
                    if (!empty($model->$column)) { $datatables->editColumn($column, $callback); }
                }
                break; // one sample row is enough to detect column presence
            }
        } catch (\Throwable $e) {
            \Log::error('Error in processStatusColumnsTrait', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
        }
    }
}