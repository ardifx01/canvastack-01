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

        // Special handling for temp tables (DynamicTables)
        if (strpos($diftaName, 'temp_') === 0) {
            \Log::info("🔧 Detected temp table, creating DynamicTables configuration", [
                'table_name' => $diftaName
            ]);
            
            // Create a synthetic model config for temp tables
            $modelConfig = [
                'table_name' => $diftaName,
                'type' => 'string_table',
                'source' => $diftaName, // Table name as source
                'columns' => $method['columns'] ?? [],
                'searchable' => $method['searchable'] ?? [],
                'sortable' => $method['sortable'] ?? true,
                'clickable' => $method['clickable'] ?? true,
                'primary_key' => $this->detectTempTablePrimaryKey($diftaName), // Detect primary key
                'no_id_column' => true // Flag to indicate this table doesn't use 'id' column
            ];
            
            \Log::info("✅ Created synthetic config for temp table", [
                'table_name' => $diftaName,
                'columns' => $modelConfig['columns']
            ]);
        } else {
            // Regular model lookup
            if (empty($data->datatables->model[$diftaName])) {
                throw new \InvalidArgumentException("Model configuration not found for: {$diftaName}. Available models: " . implode(', ', array_keys((array)($data->datatables->model ?? []))));
            }
            $modelConfig = $data->datatables->model[$diftaName];
        }

        // Universal Data Source Detection via class helper
        $dataSource = $this->detectDataSource($modelConfig);
        \Log::info("🔍 Detected data source type (trait): {$dataSource['type']} for {$diftaName}");

        // Create model via class helper
        return $this->createModelFromSource($dataSource);
    }

    /**
     * Detect primary key for temp tables based on table name
     */
    private function detectTempTablePrimaryKey($tableName)
    {
        // Known temp table primary keys
        $tempTableKeys = [
            'temp_user_never_login' => 'user_id',
            'temp_montly_activity' => 'user_id',
            'temp_monthly_activity' => 'user_id',
        ];

        return $tempTableKeys[$tableName] ?? 'id'; // Default to 'id' if not found
    }
}