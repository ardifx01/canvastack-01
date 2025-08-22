<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * Phase 1: ModelInitializerTrait
 * - Extracts real initialization logic under trait path
 * - Calls class helpers (detectDataSource, createModelFromSource)
 */
trait ModelInitializerTrait
{
    /**
     * Initialize model using trait path (real logic)
     *
     * @param array $method
     * @param object $data
     * @return mixed
     */
    private function initModelFromConfig($method, $data)
    {
        // Fix: Check if 'difta' key exists in proper format to prevent "Undefined array key" error
        $diftaName = null;
        // Check for nested difta structure: $method['difta']['name']
        if (isset($method['difta']) && isset($method['difta']['name'])) {
            $diftaName = $method['difta']['name'];
        }
        // Check for flat difta structure from form data: $method['difta[name]']
        elseif (isset($method['difta[name]'])) {
            $diftaName = $method['difta[name]'];
        }
        // Fallback: try to find any difta-related key
        else {
            if (!is_array($method)) {
                \Log::warning("⚠️  method is not array", [
                    'method_type' => gettype($method),
                    'method_value' => $method
                ]);
            } else {
                foreach ($method as $key => $value) {
                    if (strpos($key, 'difta') !== false && !empty($value)) {
                        $diftaName = $value;
                        break;
                    }
                }
            }
        }

        if (empty($diftaName)) {
            throw new \InvalidArgumentException('Missing required difta configuration in method. Available keys: ' . implode(', ', array_keys((array)$method)));
        }

        if (empty($data->datatables->model[$diftaName])) {
            throw new \InvalidArgumentException("Model configuration not found for: {$diftaName}. Available models: " . implode(', ', array_keys((array)($data->datatables->model ?? []))));
        }

        $modelConfig = $data->datatables->model[$diftaName];

        // Universal Data Source Detection via class helper
        $dataSource = $this->detectDataSource($modelConfig);
        \Log::info("🔍 Detected data source type (trait): {$dataSource['type']} for {$diftaName}");

        // Create model via class helper
        return $this->createModelFromSource($dataSource);
    }
}