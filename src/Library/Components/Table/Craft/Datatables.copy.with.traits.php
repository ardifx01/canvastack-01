<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft;

use Incodiy\Codiy\Models\Admin\System\DynamicTables;
use Incodiy\Codiy\Controllers\Core\Craft\Includes\Privileges;
use Yajra\DataTables\DataTables as DataTable;

// Enhanced Phase 2: New Architecture Imports
use Incodiy\Codiy\Library\Components\Table\Contracts\DataProviderInterface;
use Incodiy\Codiy\Library\Components\Table\Providers\DataProvider;
use Incodiy\Codiy\Library\Components\Table\Registry\ModelRegistry;
use Incodiy\Codiy\Library\Components\Table\Adapters\DataTablesAdapter;

/**
 * Datatables processor for handling table operations
 * 
 * Created on 21 Apr 2021
 * Time Created : 12:45:06
 *
 * @filesource Datatables.php
 *
 * @author     wisnuwidi@incodiy.com - 2021
 * @copyright  wisnuwidi
 * @email      wisnuwidi@incodiy.com
 */
class Datatables
{
    use Privileges;
    // Phase 1 Traits (wrappers)
    use \Incodiy\Codiy\Library\Components\Table\Craft\Traits\ModelInitializerTrait;
    use \Incodiy\Codiy\Library\Components\Table\Craft\Traits\FilterHandlerTrait;
    use \Incodiy\Codiy\Library\Components\Table\Craft\Traits\PrivilegeHandlerTrait;
    use \Incodiy\Codiy\Library\Components\Table\Craft\Traits\OrderingHandlerTrait;
    use \Incodiy\Codiy\Library\Components\Table\Craft\Traits\RelationshipHandlerTrait;
    // New Action utilities
    use \Incodiy\Codiy\Library\Components\Table\Craft\Traits\ActionHandler { 
        determineActionList as protected traitDetermineActionList;
        getRouteActionMapping as protected traitGetRouteActionMapping;
    }
    // Status column processing
    use \Incodiy\Codiy\Library\Components\Table\Craft\Traits\StatusHandlerTrait;
    // Row attributes
    use \Incodiy\Codiy\Library\Components\Table\Craft\Traits\RowAttributeTrait;
    // Format and formula handlers
    use \Incodiy\Codiy\Library\Components\Table\Craft\Traits\FormatHandlerTrait;
    use \Incodiy\Codiy\Library\Components\Table\Craft\Traits\FormulaHandlerTrait;

    /**
     * Filter model array
     */
    public $filter_model = [];

    /**
     * Filter datatables array
     */
    public $filter_datatables = [];

    /**
     * Enhanced Phase 2: New Architecture Components
     */
    
    /**
     * Model registry for dynamic model resolution
     * 
     * @var ModelRegistry
     */
    private ModelRegistry $modelRegistry;

    /**
     * Data provider for clean data processing
     * 
     * @var DataProviderInterface
     */
    private DataProviderInterface $dataProvider;

    /**
     * Enhanced architecture enabled flag
     * 
     * @var bool
     */
    private bool $useEnhancedArchitecture = true;

    /**
     * Safely load configuration with fallback
     */
    private function safeConfig(string $key, $default = null)
    {
        try {
            // Check if Laravel app is available and config service exists
            if (function_exists('app') && app()->bound('config')) {
                return config($key, $default);
            } elseif (function_exists('config')) {
                return config($key, $default);
            }
        } catch (\Throwable $e) {
            // Config loading failed, return default
        }
        
        return $default;
    }

    /**
     * Get valid image extensions from config
     */
    private function getImageExtensions()
    {
        return $this->safeConfig('datatables.image_extensions', ['jpg', 'jpeg', 'png', 'gif']);
    }

    /**
     * Get default pagination settings from config
     */
    private function getDefaultPagination()
    {
        return $this->safeConfig('datatables.default_pagination', [
            'start' => 0,
            'length' => 10,
            'total' => 0
        ]);
    }

    /**
     * Get default actions from config
     */
    private function getDefaultActions()
    {
        return $this->safeConfig('datatables.default_actions', ['view', 'insert', 'edit', 'delete']);
    }

    /**
     * Get blacklisted fields from config
     */
    private function getBlacklistedFields()
    {
        return $this->safeConfig('datatables.blacklisted_fields', ['password', 'action', 'no']);
    }

    /**
     * Get reserved parameters from config
     */
    private function getReservedParameters()
    {
        return $this->safeConfig('datatables.reserved_parameters', [
            'renderDataTables', 'draw', 'columns', 'order', 'start', 
            'length', 'search', 'difta', '_token', '_'
        ]);
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize Enhanced Architecture Components (Phase 2)
        $this->initializeEnhancedArchitecture();
    }

    /**
     * Initialize Enhanced Architecture Components
     * 
     * @return void
     */
    private function initializeEnhancedArchitecture(): void
    {
        try {
            // Initialize Model Registry for dynamic model resolution
            $this->modelRegistry = new ModelRegistry();
            
            // Initialize Data Provider for clean data processing
            $this->dataProvider = new DataProvider($this->modelRegistry);
            
            if ($this->safeConfig('datatables.debug', false)) { \Log::info("✅ Enhanced Architecture initialized", [
                'model_registry' => get_class($this->modelRegistry),
                'data_provider' => get_class($this->dataProvider),
                'architecture_version' => '2.0.0'
            ]); }
            
        } catch (\Exception $e) {
            if ($this->safeConfig('datatables.debug', false)) { \Log::warning("⚠️  Enhanced Architecture initialization failed, falling back to legacy", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]); }
            
            // Graceful fallback: disable enhanced architecture
            $this->useEnhancedArchitecture = false;
        }
    }

    /**
     * Main processing method for datatables
     *
     * @param array $method Method configuration
     * @param object $data Data configuration object
     * @param array $filters Applied filters
     * @param array $filter_page Filter page configuration
     * @return mixed Processed datatable data
     */
    public function process($method, $data, $filters = [], $filter_page = [])
    {
        try {
            if (config('datatables.debug', false)) { \Log::info("🚀 DataTables processing started", [
                'method' => $method,
                'has_data' => !empty($data),
                'filters_count' => count($filters),
                'filter_page_count' => count($filter_page),
                'enhanced_architecture' => $this->useEnhancedArchitecture
            ]); } // end debug log

            // Declarative Relations API: merge runtime declared_relations & dot_columns into request method config
            try {
                $tbl = $method['difta']['name'] ?? ($method['difta[name]'] ?? null);
                if ($tbl) {
                    $rt = DatatableRuntime::get($tbl);
                    if ($rt && isset($rt->datatables)) {
                        if (!empty($rt->datatables->declared_relations)) {
                            $method['declared_relations'] = $rt->datatables->declared_relations;
                        }
                        if (!empty($rt->datatables->dot_columns)) {
                            $method['dot_columns'] = $rt->datatables->dot_columns;
                        }
                    }
                }
            } catch (\Throwable $e) {}

            // Enhanced Phase 2: Try enhanced architecture first with graceful fallback
            if ($this->useEnhancedArchitecture) {
                try {
                    $result = $this->processWithEnhancedArchitecture($method, $data, $filters, $filter_page);
                    
                    // Check if result has expected relational columns
                    if ($this->hasMissingRelationalColumns($result)) {
                        if (config('datatables.debug', false)) { \Log::warning("⚠️  Enhanced Architecture missing relational columns, falling back to legacy", [
                            'missing_columns' => $this->getMissingColumns($result)
                        ]); }
                        
                        // Continue with legacy processing
                        $this->useEnhancedArchitecture = false;
                    } else {
                        return $result;
                    }
                    
                } catch (\Exception $e) {
                    if (config('datatables.debug', false)) { \Log::warning("⚠️  Enhanced architecture failed, falling back to legacy", [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]); }
                    
                    // Continue with legacy processing
                    $this->useEnhancedArchitecture = false;
                }
            }
            
            // Legacy processing path
            if (config('datatables.debug', false)) { \Log::info("🔄 Using legacy processing path"); }
            
            // Initialize model and table data
            // Phase 1: use trait wrapper when enabled, else legacy method
            $useTraits = (bool) (config('datatable.use_traits', false));
            $modelData = $useTraits ? $this->initModelFromConfig($method, $data) : $this->initializeModel($method, $data);
            if (config('datatables.debug', false)) { \Log::info("✅ Model initialized", ['model_class' => get_class($modelData), 'use_traits' => $useTraits]); }
            
            $tableName = $this->getTableName($modelData);
            if (config('datatables.debug', false)) { \Log::info("✅ Table name resolved", ['table' => $tableName]); }
            
            // Process model if needed
            if (config('datatables.debug', false)) { \Log::info("🔧 About to process model", [
                'table' => $tableName,
                'has_modelProcessing' => isset($data->datatables->modelProcessing),
                'modelProcessing_tables' => isset($data->datatables->modelProcessing) ? array_keys($data->datatables->modelProcessing) : []
            ]); }
            $this->processModel($data, $tableName);

            // Get configuration data
            $config = $this->getConfiguration($data, $tableName);
            if (config('datatables.debug', false)) { \Log::info("✅ Configuration loaded", [
                'first_field' => $config['firstField'], 
                'blacklists_count' => count($config['blacklists'])
            ]); }

            // Setup privileges and actions
            $actionConfig = $this->setupActions($config, $tableName);
            if (config('datatables.debug', false)) { \Log::info("✅ Actions configured", ['action_list_count' => count($actionConfig['actionList'])]); }

            // Setup relationships and joins
            $modelData = $this->setupRelationships($modelData, $config, $tableName);
            if (config('datatables.debug', false)) { \Log::info("✅ Relationships setup completed"); }

            // Apply conditions and filters
            $modelData = $useTraits
                ? $this->applyConditionsTrait($modelData, $data, $tableName)
                : $this->applyConditions($modelData, $data, $tableName);
            if (config('datatables.debug', false)) { \Log::info("✅ Conditions applied"); }
            
            // Phase 1: route through trait wrapper when enabled
            $modelData = $useTraits
                ? $this->applyRequestFilters($modelData, is_array($filters) ? $filters : [], $tableName, $config['firstField'])
                : $this->applyFilters($modelData, $filters, $tableName, $config['firstField']);
            if (config('datatables.debug', false)) { \Log::info("✅ Filters applied", ['filters_applied' => is_array($filters) ? count($filters) : 0, 'use_traits' => $useTraits]); }

            // Setup pagination
            $paginationConfig = $this->setupPagination($modelData);
            if (config('datatables.debug', false)) { \Log::info("✅ Pagination configured", [
                'start' => $paginationConfig['start'], 
                'length' => $paginationConfig['length']
            ]); }
            
            $modelData->skip($paginationConfig['start'])->take($paginationConfig['length']);

            // Create and configure datatables with safety checks
            try {
                // Defensive check: ensure modelData is valid before passing to DataTables
                if (!$modelData || (!is_object($modelData) && !is_array($modelData))) {
                    throw new \Exception("Invalid modelData type: " . gettype($modelData));
                }
                
                $datatables = $this->createDatatables($modelData, $paginationConfig, $config);
                if (config('datatables.debug', false)) { \Log::info("✅ DataTables instance created"); }
            } catch (\Exception $e) {
                \Log::error("❌ CRITICAL Error in createDatatables", [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'modelData_type' => gettype($modelData),
                    'table' => $tableName
                ]);
                throw $e; // Re-throw as this is critical
            }

            // Apply column modifications with error isolation
            try {
                $this->applyColumnModifications($datatables, $modelData, $data, $tableName, $config);
                if (config('datatables.debug', false)) { \Log::info("✅ Column modifications applied"); }
            } catch (\Exception $e) {
                \Log::error("❌ Error in applyColumnModifications", [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'table' => $tableName
                ]);
            }

            // Setup row attributes with error isolation
            try {
                $this->setupRowAttributes($datatables, $data, $tableName);
                if (config('datatables.debug', false)) { \Log::info("✅ Row attributes configured"); }
            } catch (\Exception $e) {
                \Log::error("❌ Error in setupRowAttributes", [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'table' => $tableName
                ]);
            }

            // Add action column with error isolation (default behavior - always add)
            try {
                $this->addActionColumn($datatables, $modelData, $actionConfig, $data);
                if (config('datatables.debug', false)) { \Log::info("✅ Action column added (default behavior)"); }
            } catch (\Exception $e) {
                \Log::error("❌ Error in addActionColumn", [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'table' => $tableName
                ]);
            }

            // Return final datatable with error isolation
            try {
                // Check if DT_RowIndex is expected in request columns
                $needsIndexColumn = $this->checkIfDTRowIndexNeeded();
                $indexLists = $data->datatables->records['index_lists'] ?? false;
                
                // Override index_lists if DT_RowIndex is expected but not configured
                if ($needsIndexColumn && !$indexLists) {
                    if (config('datatables.debug', false)) { \Log::info("🔢 DT_RowIndex detected in request - forcing index column addition"); }
                    $indexLists = true;
                }
                
                $result = $this->finalizeDatatable($datatables, $indexLists);
                \Log::info("🎉 DataTables processing completed successfully", [
                    'index_column_added' => $indexLists,
                    'dt_rowindex_needed' => $needsIndexColumn
                ]);
            } catch (\Exception $e) {
                \Log::error("❌ CRITICAL Error in finalizeDatatable", [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'table' => $tableName
                ]);
                
                // Emergency fallback
                $result = $datatables->make();
            }
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error("❌ CRITICAL ERROR in DataTables processing", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'method' => $method ?? 'unknown'
            ]);
            
            // Return safe error response for DataTables
            return response()->json([
                'draw' => request('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'DataTables processing error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enhanced Phase 2: Process with Enhanced Architecture
     * 
     * Uses DataProvider and DataTablesAdapter for clean separation between
     * data processing and presentation logic.
     * 
     * @param array $method Method configuration
     * @param object $data Data configuration object
     * @param array $filters Applied filters
     * @param array $filter_page Filter page configuration
     * @return mixed Processed datatable data
     */
    private function processWithEnhancedArchitecture($method, $data, $filters = [], $filter_page = [])
    {
        \Log::info("🚀 Enhanced Architecture processing started", [
            'version' => '2.0.0',
            'data_provider' => get_class($this->dataProvider),
            'model_registry' => get_class($this->modelRegistry)
        ]);

        // Prepare configuration for DataProvider
        $config = $this->prepareEnhancedConfig($method, $data, $filters, $filter_page);
        
        // Initialize DataProvider with configuration
        $this->dataProvider->initialize($config);
        \Log::info("✅ DataProvider initialized with enhanced config");

        // Prepare request configuration for DataTablesAdapter
        $requestConfig = $this->prepareRequestConfig($method, $filters, $filter_page);
        
        // Setup action configuration
        $actionConfig = $this->prepareActionConfig($config, $data);
        
        // Create DataTablesAdapter
        $adapter = new DataTablesAdapter(
            $this->dataProvider,
            $config,
            $actionConfig
        );
        \Log::info("✅ DataTablesAdapter created");

        // Render response using adapter
        $result = $adapter->render($requestConfig);
        
        \Log::info("🎉 Enhanced Architecture processing completed successfully", [
            'total_records' => $result['recordsTotal'] ?? 0,
            'filtered_records' => $result['recordsFiltered'] ?? 0,
            'returned_records' => count($result['data'] ?? [])
        ]);

        return response()->json($result);
    }

    /**
     * Prepare configuration for enhanced DataProvider
     * 
     * @param array $method Method configuration
     * @param object $data Data configuration object
     * @param array $filters Applied filters
     * @param array $filter_page Filter page configuration
     * @return array Enhanced configuration
     */
    private function prepareEnhancedConfig($method, $data, $filters, $filter_page): array
    {
        // Extract table name from method configuration
        $tableName = null;
        
        if (isset($method['difta']) && isset($method['difta']['name'])) {
            $tableName = $method['difta']['name'];
        } elseif (isset($method['difta[name]'])) {
            $tableName = $method['difta[name]'];
        }

        if (!$tableName) {
            throw new \InvalidArgumentException('Table name not found in method configuration');
        }

        // Ambil konfigurasi numbering/index_lists dari runtime data (jika ada)
        $indexLists = false;
        try {
            $indexLists = $data->datatables->records['index_lists'] ?? false;
        } catch (\Throwable $e) {
            $indexLists = false;
        }

        return [
            'table_name' => $tableName,
            'method' => $method,
            'data' => $data,
            'filters' => $filters,
            'filter_page' => $filter_page,
            'enhanced_mode' => true,
            // Kirim flag numbering ke adapter agar bisa memaksa DT_RowIndex
            'index_lists' => (bool) $indexLists,
            // Phase 1 wiring: pass declarative relations & dot columns to provider
            'declared_relations' => $method['declared_relations'] ?? [],
            'dot_columns'        => $method['dot_columns'] ?? [],
        ];
    }

    /**
     * Prepare request configuration for DataTablesAdapter
     * 
     * @param array $method Method configuration
     * @param array $filters Applied filters
     * @param array $filter_page Filter page configuration
     * @return array Request configuration
     */
    private function prepareRequestConfig($method, $filters, $filter_page): array
    {
        $requestData = array_merge($_GET, $_POST);
        
        return array_merge($requestData, [
            'filters' => $filters,
            'filter_page' => $filter_page,
            'method' => $method
        ]);
    }

    /**
     * Prepare action configuration for DataTablesAdapter
     * 
     * @param array $config Configuration
     * @param object $data Data configuration object
     * @return array Action configuration
     */
    private function prepareActionConfig($config, $data): array
    {
        $tableName = $config['table_name'];
        
        // Get action configuration from data object
        $columnData = $data->datatables->columns ?? [];
        $tableConfig = $columnData[$tableName] ?? [];
        $actions = $tableConfig['actions'] ?? [];

        return [
            'enabled' => true,
            'actions' => !empty($actions) && is_array($actions) 
                ? $actions 
                : $this->getDefaultActions(),
            'table' => $tableName
        ];
    }

    /**
     * Initialize model based on configuration with Universal Data Source Support
     * 
     * Supports:
     * - String table names ('users', 'products')  
     * - Raw SQL queries ("SELECT * FROM table")
     * - Laravel Query Builder (DB::table('users')->where('active', 1))
     * - Laravel Eloquent (App\User::all(), App\User::with('groups'))
     */
    private function initializeModel($method, $data)
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
            // Safety check: ensure method is array
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
            throw new \InvalidArgumentException('Missing required difta configuration in method. Available keys: ' . implode(', ', array_keys($method)));
        }
        
        if (empty($data->datatables->model[$diftaName])) {
            throw new \InvalidArgumentException("Model configuration not found for: {$diftaName}. Available models: " . implode(', ', array_keys($data->datatables->model)));
        }

        $modelConfig = $data->datatables->model[$diftaName];
        
        // Universal Data Source Detection
        $dataSource = $this->detectDataSource($modelConfig);
        \Log::info("🔍 Detected data source type: {$dataSource['type']} for {$diftaName}");
        
        return $this->createModelFromSource($dataSource);
    }

    /**
     * Detect data source type from model configuration
     */
    private function detectDataSource($modelConfig)
    {
        $type = $modelConfig['type'] ?? 'auto';
        $source = $modelConfig['source'];
        
        // Legacy support for existing 'model' and 'sql' types
        if ($type === 'model') {
            // Extra safety for legacy 'model' type - check if source is DynamicTables
            if (is_object($source) && strpos(get_class($source), 'DynamicTables') !== false) {
                \Log::warning("⚠️  Legacy 'model' type with DynamicTables detected - auto-converting");
                return $this->autoDetectDataSource($source); // Let auto-detection handle it safely
            }
            return ['type' => 'eloquent_model', 'source' => $source];
        }
        
        if ($type === 'sql') {
            return ['type' => 'raw_sql', 'source' => $source];
        }
        
        // New universal data source types
        if ($type === 'string_table') {
            return ['type' => 'string_table', 'source' => $source];
        }
        
        if ($type === 'raw_sql') {
            return ['type' => 'raw_sql', 'source' => $source];
        }
        
        if ($type === 'query_builder') {
            return ['type' => 'query_builder', 'source' => $source];
        }
        
        if ($type === 'eloquent') {
            return ['type' => 'eloquent', 'source' => $source];
        }
        
        // Auto-detection for backward compatibility and flexibility
        if ($type === 'auto') {
            return $this->autoDetectDataSource($source);
        }
        
        throw new \InvalidArgumentException("Unsupported data source type: {$type}");
    }

    /**
     * Auto-detect data source type based on source content
     */
    private function autoDetectDataSource($source)
    {
        // If it's an object, check instance type
        if (is_object($source)) {
            $className = get_class($source);
            
            \Log::info("🔍 AUTO-DETECTING DATA SOURCE", [
                'source_class' => $className,
                'is_query_builder' => strpos($className, 'Illuminate\Database\Query\Builder') !== false,
                'is_eloquent' => strpos($className, 'Illuminate\Database\Eloquent') !== false,
                'is_dynamic_tables' => strpos($className, 'DynamicTables') !== false
            ]);
            
            // Handle Query Builder
            if (strpos($className, 'Illuminate\Database\Query\Builder') !== false) {
                return ['type' => 'query_builder', 'source' => $source];
            }
            
            // Handle Eloquent models
            if (strpos($className, 'Illuminate\Database\Eloquent') !== false) {
                return ['type' => 'eloquent_model', 'source' => $source];
            }
            
            // Critical Fix: Handle DynamicTables objects
            if (strpos($className, 'DynamicTables') !== false) {
                \Log::warning("⚠️  DynamicTables detected - converting to Query Builder for Yajra compatibility");
                
                // Try to extract SQL from DynamicTables
                if (method_exists($source, 'getSqlQuery') && !empty($source->getSqlQuery())) {
                    $sqlQuery = $source->getSqlQuery();
                    \Log::info("🔄 Converting DynamicTables SQL to Query Builder", [
                        'sql_preview' => substr($sqlQuery, 0, 100)
                    ]);
                    return ['type' => 'raw_sql', 'source' => $sqlQuery];
                }
                
                // Try to get table name from DynamicTables
                if (method_exists($source, 'getTable')) {
                    $tableName = $source->getTable();
                    \Log::info("🔄 Converting DynamicTables table to Query Builder", [
                        'table' => $tableName
                    ]);
                    return ['type' => 'string_table', 'source' => $tableName];
                }
                
                // Emergency fallback for DynamicTables
                \Log::error("❌ Could not convert DynamicTables - using emergency fallback");
                return ['type' => 'string_table', 'source' => 'users'];
            }
            
            // Default for other objects - treat as eloquent model
            \Log::info("📋 Using default eloquent_model type for object");
            return ['type' => 'eloquent_model', 'source' => $source];
        }
        
        // If it's a string, analyze content
        if (is_string($source)) {
            // Check for SQL keywords
            if (preg_match('/^\s*(SELECT|WITH|INSERT|UPDATE|DELETE)\s+/i', trim($source))) {
                return ['type' => 'raw_sql', 'source' => $source];
            }
            
            // Check for Laravel Query Builder patterns
            if (preg_match('/^DB::table\s*\(|^\\?Illuminate\\?Database/', $source)) {
                return ['type' => 'query_builder', 'source' => $source];
            }
            
            // Check for Eloquent patterns
            if (preg_match('/^App\\?|::(all|find|where|with|get|first)\s*\(/', $source)) {
                return ['type' => 'eloquent', 'source' => $source];
            }
            
            // Default to simple table name
            return ['type' => 'string_table', 'source' => $source];
        }
        
        throw new \InvalidArgumentException("Unable to auto-detect data source type for: " . print_r($source, true));
    }

    /**
     * Create model instance from detected data source
     */
    private function createModelFromSource($dataSource)
    {
        switch ($dataSource['type']) {
            case 'string_table':
                return $this->createFromTableName($dataSource['source']);
                
            case 'raw_sql':
                return $this->createFromRawSQL($dataSource['source']);
                
            case 'query_builder':
                return $this->createFromQueryBuilder($dataSource['source']);
                
            case 'eloquent':
                return $this->createFromEloquent($dataSource['source']);
                
            case 'eloquent_model':
                // Extra safety check - ensure we never return DynamicTables to Yajra DataTables
                $model = $dataSource['source'];
                $className = get_class($model);
                
                if (strpos($className, 'DynamicTables') !== false) {
                    \Log::error("🚨 CRITICAL: DynamicTables detected in eloquent_model - converting to safe fallback");
                    
                    // Try to extract table information
                    if (method_exists($model, 'getTable')) {
                        $tableName = $model->getTable();
                        \Log::info("🔄 Converting DynamicTables to Query Builder via table name", [
                            'table' => $tableName,
                            'original_class' => $className
                        ]);
                        return \DB::table($tableName);
                    }
                    
                    // Emergency fallback
                    \Log::error("❌ Emergency fallback from DynamicTables to users table");
                    return \DB::table('users');
                }
                
                return $model; // Already instantiated model (safe)
                
            default:
                throw new \InvalidArgumentException("Unsupported data source type: {$dataSource['type']}");
        }
    }

    /**
     * Create model from simple table name
     */
    private function createFromTableName($tableName)
    {
        \Log::info("🔧 Creating model from table name: {$tableName}");
        
        // Enhanced Phase 2: Try to use model registry for proper connection handling
        if ($this->useEnhancedArchitecture && isset($this->modelRegistry)) {
            try {
                $modelConfig = $this->modelRegistry->resolve($tableName);
                
                // If we have a model class configured, instantiate it
                if (!empty($modelConfig['class'])) {
                    $modelClass = $modelConfig['class'];
                    if (class_exists($modelClass)) {
                        \Log::info("✅ Creating Eloquent model: {$modelClass}");
                        $model = new $modelClass();
                        return $model->newQuery(); // Returns Eloquent Builder with proper connection
                    }
                }
                
                // If we have a specific connection configured, use it
                if (!empty($modelConfig['connection'])) {
                    \Log::info("✅ Using configured connection: {$modelConfig['connection']}");
                    return \DB::connection($modelConfig['connection'])->table($tableName);
                }
                
            } catch (\Exception $e) {
                \Log::warning("⚠️ ModelRegistry failed for {$tableName}, using fallback: " . $e->getMessage());
            }
        }
        
        // Fallback: Use default connection
        \Log::info("🔄 Using default connection for table: {$tableName}");
        return \DB::table($tableName);
    }

    /**
     * Create model from raw SQL query
     */
    private function createFromRawSQL($sqlQuery)
    {
        \Log::info("🔧 Creating model from raw SQL: " . substr($sqlQuery, 0, 100) . "...");
        
        try {
            // Extract table name from SQL for proper table detection
            $tableName = $this->extractTableNameFromSQL($sqlQuery);
            
            \Log::info("🔍 Detected table from SQL", [
                'detected_table' => $tableName,
                'sql_preview' => substr($sqlQuery, 0, 150)
            ]);
            
            // Check if detected table has a proper model (like users -> User)
            if ($tableName && $tableName !== 'dynamic_table') {
                $specificModel = $this->tryCreateSpecificModel($tableName);
                if ($specificModel) {
                    \Log::info("✅ Using specific model instead of raw SQL", [
                        'table' => $tableName,
                        'model_class' => get_class($specificModel)
                    ]);
                    return $specificModel;
                }
            }
            
            // Fallback: Create query builder from raw SQL - compatible with Yajra DataTables
            \Log::info("⚡ Creating Query Builder from raw SQL for DataTables compatibility");
            return \DB::connection('mysql')->table(\DB::raw("({$sqlQuery}) as dynamic_table"));
            
        } catch (\Exception $e) {
            \Log::error("❌ Error processing raw SQL", [
                'error' => $e->getMessage(),
                'sql_preview' => substr($sqlQuery, 0, 100)
            ]);
            
            // Emergency fallback
            return \DB::table('users'); // Safe fallback to avoid engine errors
        }
    }

    /**
     * Extract table name from SQL query
     */
    private function extractTableNameFromSQL($sqlQuery)
    {
        try {
            // Simple regex to extract main table name from SELECT statements
            if (preg_match('/\bfrom\s+`?([a-zA-Z0-9_]+)`?/i', $sqlQuery, $matches)) {
                return $matches[1];
            }
            
            // Alternative pattern for more complex queries
            if (preg_match('/\bfrom\s+([a-zA-Z0-9_]+)/i', $sqlQuery, $matches)) {
                return $matches[1];
            }
            
            return null;
        } catch (\Exception $e) {
            \Log::warning("Could not extract table name from SQL: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Try to create specific model for detected table name
     */
    private function tryCreateSpecificModel($tableName)
    {
        try {
            // Common table -> model mappings
            $modelMappings = [
                'users' => 'Incodiy\Codiy\Models\Admin\System\User',
                'base_group' => 'Incodiy\Codiy\Models\Admin\System\Group', 
                'base_modules' => 'Incodiy\Codiy\Models\Admin\System\Modules',
                'base_user_group' => 'Incodiy\Codiy\Models\Admin\System\Usergroup',
                // CRITICAL FIX: Map temp tables to User model for proper relation support
                'temp_user_never_login' => 'Incodiy\Codiy\Models\Admin\System\User',
                'temp_montly_activity' => 'Incodiy\Codiy\Models\Admin\System\User'
            ];
            
            if (isset($modelMappings[$tableName])) {
                $modelClass = $modelMappings[$tableName];
                
                // Check if class exists before instantiating
                if (class_exists($modelClass)) {
                    \Log::info("🎯 Creating specific model for table", [
                        'table' => $tableName,
                        'model_class' => $modelClass
                    ]);
                    
                    $model = new $modelClass;
                    
                    // CRITICAL FIX: Handle temp tables differently
                    if (strpos($tableName, 'temp_') === 0) {
                        \Log::info("🔧 Creating Query Builder for temp table", [
                            'table' => $tableName,
                            'base_model' => $modelClass
                        ]);
                        
                        // For temp tables, create Query Builder with proper connection
                        $queryBuilder = \DB::table($tableName);
                        
                        // Verify connection is valid
                        $connection = $queryBuilder->getConnection();
                        if (!$connection) {
                            \Log::error("❌ No database connection for temp table", ['table' => $tableName]);
                            return null;
                        }
                        
                        \Log::info("✅ Query Builder created for temp table", [
                            'table' => $tableName,
                            'connection' => get_class($connection)
                        ]);
                        
                        return $queryBuilder;
                    }
                    
                    // CRITICAL FIX: Return Eloquent Builder for regular tables
                    // This enables Zero-Configuration and useRelation() to work properly
                    if ($tableName === 'users') {
                        \Log::info("✅ Using Eloquent Builder for users table (enables Zero-Config relations)");
                        // Return Eloquent Builder with proper relation support
                        return $model->newQuery(); // This enables useRelation('group') to work
                    }
                    
                    return $model->newQuery(); // Always return Eloquent Builder for proper relation support
                }
            }
            
            \Log::info("⚠️  No specific model found for table: {$tableName}");
            return null;
            
        } catch (\Exception $e) {
            \Log::warning("Could not create specific model for {$tableName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create model from Laravel Query Builder
     */
    private function createFromQueryBuilder($queryBuilder)
    {
        \Log::info("🔧 Creating model from Query Builder");
        
        // Handle string representation of Query Builder
        if (is_string($queryBuilder)) {
            try {
                // Safely evaluate the Query Builder string
                // Note: This should be used carefully in production
                $result = eval("return {$queryBuilder};");
                \Log::info("✅ Query Builder string evaluated successfully");
                return $result;
            } catch (\Exception $e) {
                \Log::error("❌ Error evaluating Query Builder string: " . $e->getMessage());
                throw new \InvalidArgumentException("Invalid Query Builder string: {$queryBuilder}");
            }
        }
        
        // Handle actual Query Builder object
        return $queryBuilder;
    }

    /**
     * Create model from Laravel Eloquent
     */
    private function createFromEloquent($eloquentQuery)
    {
        \Log::info("🔧 Creating model from Eloquent query");
        
        // Handle string representation of Eloquent
        if (is_string($eloquentQuery)) {
            try {
                // Safely evaluate the Eloquent string
                // Note: This should be used carefully in production
                $result = eval("return {$eloquentQuery};");
                \Log::info("✅ Eloquent string evaluated successfully");
                return $result;
            } catch (\Exception $e) {
                \Log::error("❌ Error evaluating Eloquent string: " . $e->getMessage());
                throw new \InvalidArgumentException("Invalid Eloquent string: {$eloquentQuery}");
            }
        }
        
        // Handle actual Eloquent object
        return $eloquentQuery;
    }

    /**
     * Get table name from model - handle different object types
     */
    private function getTableName($modelData)
    {
        try {
            $className = get_class($modelData);
            
            \Log::info("🔍 Getting table name from model", [
                'model_class' => $className,
                'has_getTable' => method_exists($modelData, 'getTable'),
                'is_query_builder' => strpos($className, 'Database\Query\Builder') !== false
            ]);
            
            // Handle Eloquent Model (has getTable method)
            if (method_exists($modelData, 'getTable')) {
                $tableName = $modelData->getTable();
                \Log::info("✅ Got table name from Eloquent Model", ['table' => $tableName]);
                return $tableName;
            }
            
            // Handle Query Builder (extract from 'from' property)
            if (strpos($className, 'Database\Query\Builder') !== false) {
                // Try to get table name from Query Builder's from property
                if (property_exists($modelData, 'from') && !empty($modelData->from)) {
                    $fromValue = $modelData->from;
                    
                    // Handle simple table name
                    if (is_string($fromValue) && !strpos($fromValue, ' ')) {
                        \Log::info("✅ Got table name from Query Builder", ['table' => $fromValue]);
                        return $fromValue;
                    }
                }
                
                // Fallback: try to extract from SQL
                try {
                    $sql = $modelData->toSql();
                    
                    // Handle subquery patterns like (SELECT...) as dynamic_table
                    if (preg_match('/from\s+\([^)]+\)\s+as\s+`?([^`\s]+)`?/i', $sql, $matches)) {
                        $tableName = $matches[1];
                        \Log::info("✅ Extracted table name from Query Builder subquery", ['table' => $tableName]);
                        return $tableName;
                    }
                    
                    // Handle regular table patterns  
                    if (preg_match('/from\s+`?([^`\s\()]+)`?/i', $sql, $matches)) {
                        $tableName = $matches[1];
                        \Log::info("✅ Extracted table name from Query Builder SQL", ['table' => $tableName]);
                        return $tableName;
                    }
                } catch (\Exception $e) {
                    \Log::warning("Could not extract table from Query Builder SQL: " . $e->getMessage());
                }
                
                // Emergency fallback for Query Builder
                \Log::warning("⚠️  Could not determine table name from Query Builder - using fallback");
                return 'users'; // Safe fallback
            }
            
            // Handle DynamicTables or other objects
            if (strpos($className, 'DynamicTables') !== false) {
                \Log::warning("🚨 DynamicTables detected in getTableName - should have been converted earlier");
                return 'users'; // Safe fallback
            }
            
            \Log::error("❌ Unknown model type in getTableName", ['class' => $className]);
            return 'users'; // Ultimate fallback
            
        } catch (\Exception $e) {
            \Log::error("❌ Error in getTableName", [
                'error' => $e->getMessage(),
                'model_class' => get_class($modelData)
            ]);
            
            return 'users'; // Emergency fallback
        }
    }

    /**
     * Process model if model processing is configured
     */
    private function processModel($data, $tableName)
    {
        // Check if modelProcessing configuration exists safely
        if (isset($data->datatables->modelProcessing) && 
            !empty($data->datatables->modelProcessing[$tableName])) {
            \Log::info("🔧 Processing model configuration", ['table' => $tableName]);
            
            // CRITICAL DEBUG: Log the model processing data before calling
            $modelProcessingData = $data->datatables->modelProcessing[$tableName];
            \Log::info("🔍 MODEL PROCESSING DEBUG", [
                'table' => $tableName,
                'model_class' => isset($modelProcessingData['model']) ? get_class($modelProcessingData['model']) : 'NO_MODEL',
                'function' => $modelProcessingData['function'] ?? 'NO_FUNCTION',
                'connection' => $modelProcessingData['connection'] ?? 'NO_CONNECTION',
                'strict' => $modelProcessingData['strict'] ?? 'NO_STRICT'
            ]);
            
            diy_model_processing_table($data->datatables->modelProcessing, $tableName);
            
            \Log::info("✅ Model processing completed", ['table' => $tableName]);
        } else {
            \Log::info("ℹ️ No model processing needed", ['table' => $tableName]);
        }
    }

    /**
     * Get basic configuration for datatable processing
     */
    private function getConfiguration($data, $tableName)
    {
        try {
            $columnData = $data->datatables->columns ?? [];
            
            \Log::info("🔧 Getting configuration for table", [
                'table' => $tableName,
                'has_column_data' => !empty($columnData),
                'available_tables' => array_keys($columnData),
                'has_table_config' => isset($columnData[$tableName])
            ]);
            
            $firstField = 'id';
            $blacklists = $this->getBlacklistedFields();
            
            // Check if table configuration exists
            if (!isset($columnData[$tableName])) {
                \Log::warning("⚠️  No column configuration found for table: $tableName - using defaults");
                
                // Create default configuration
                $columnData[$tableName] = [
                    'lists' => ['id'],
                    'button_removed' => [],
                    'orderby' => []
                ];
            }
            
            // Check if 'lists' array exists and has values
            $tableConfig = $columnData[$tableName];
            $tableLists = $tableConfig['lists'] ?? ['id'];
            
            if (!in_array('id', $tableLists) && !empty($tableLists)) {
                $firstField = $tableLists[0];
                $blacklists[] = 'id';
                \Log::info("📝 Using custom first field", ['first_field' => $firstField]);
            }

            $config = [
                'privileges' => $this->set_module_privileges(),
                'columnData' => $columnData,
                'firstField' => $firstField,
                'blacklists' => $blacklists,
                'buttonsRemoval' => $tableConfig['button_removed'] ?? [],
                'orderBy' => $tableConfig['orderby'] ?? []
            ];
            
            \Log::info("✅ Configuration loaded successfully", [
                'first_field' => $firstField,
                'blacklists_count' => count($blacklists),
                'buttons_removed' => count($config['buttonsRemoval']),
                'order_by_rules' => count($config['orderBy'])
            ]);
            
            return $config;
            
        } catch (\Exception $e) {
            \Log::error("❌ Error in getConfiguration", [
                'table' => $tableName,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            
            // Return safe default configuration
            return [
                'privileges' => [],
                'columnData' => [],
                'firstField' => 'id',
                'blacklists' => $this->getBlacklistedFields(),
                'buttonsRemoval' => [],
                'orderBy' => []
            ];
        }
    }

    /**
     * Setup actions and privileges
     */
    private function setupActions($config, $tableName)
    {
        $columnData = $config['columnData'];
        $privileges = $config['privileges'];

        // Check if table configuration and actions exist safely
        if (!isset($columnData[$tableName]) || empty($columnData[$tableName]['actions']) || !is_array($columnData[$tableName]['actions'])) {
            \Log::info("📝 No actions configuration found for table", ['table' => $tableName]);
            return [
                'actionList' => [], 
                'list' => false, 
                'allowed' => [],
                'removed' => []
            ];
        }

        $actionList = $this->determineActionList($columnData[$tableName]['actions']);
        $useTraits = (bool) (config('datatable.use_traits', false));
        $allowedActions = $useTraits
            ? $this->filterActionsByPrivilegeTrait($actionList, $privileges)
            : $this->filterActionsByPrivileges($actionList, $privileges);
        $removedPrivileges = array_diff($actionList, $allowedActions);

        \Log::info("✅ Actions setup completed", [
            'table' => $tableName,
            'total_actions' => count($actionList),
            'allowed_actions' => count($allowedActions),
            'removed_actions' => count($removedPrivileges)
        ]);

        return [
            'actionList' => $actionList,  // This is what the code expects!
            'list' => $actionList,        // Keep compatibility 
            'allowed' => $allowedActions,
            'removed' => $removedPrivileges
        ];
    }

    /**
     * Determine the list of actions based on configuration
     */
    private function determineActionList($actions)
    {
        // Delegate to trait implementation for pass-by-ref safety
        return $this->traitDetermineActionList($actions);
    }

    /**
     * Setup relationships dynamically based on model capabilities and foreign keys
     */
    private function setupRelationships($modelData, $config, $tableName)
    {
        \Log::info("🔄 Setting up relationships for table: {$tableName}");
        
        // Priority 1: Check if model has specific relationship method (for complex relationships)
        try {
            // Check if we have an Eloquent Builder with a model
            if (method_exists($modelData, 'getModel') && $modelData->getModel()) {
                $modelClass = get_class($modelData->getModel());
                \Log::info("🔍 Model class: {$modelClass}");
                
                // For Eloquent models, check for relationship methods
                if (method_exists($modelClass, 'getUserInfo') && $tableName === 'users') {
                \Log::info('✅ Found getUserInfo method in User model - using model relationship');
                
                // Create new instance and get query with relationships  
                $userModel = new $modelClass;
                $relationQuery = $userModel->getUserInfo(false, false); // Return query builder
                
                // Debug query to ensure it's correct
                \Log::info('🔍 Relationship Query SQL: ' . $relationQuery->toSql());
                \Log::info('🔍 Relationship Query Bindings: ' . json_encode($relationQuery->getBindings()));
                
                // Test sample data retrieval
                $testData = clone $relationQuery;
                $sampleData = $testData->limit(1)->get();
                \Log::info('🔍 Sample relationship data: ' . json_encode($sampleData->toArray()));
                
                return $relationQuery;
                }
            } else {
                \Log::info("ℹ️ No Eloquent model available for {$tableName}, skipping model relationships");
            }
        } catch (\Exception $e) {
            \Log::error('❌ Error in model relationship detection: ' . $e->getMessage());
            \Log::info('🔄 Falling back to foreign key joins');
        }
        
        // Priority 2: Use configured foreign keys (for standard relationships)
        $columnData = $config['columnData'];
        
        // Check if table configuration and foreign keys exist safely
        if (!isset($columnData[$tableName]) || empty($columnData[$tableName]['foreign_keys'])) {
            \Log::info("ℹ️ No foreign keys found for {$tableName}, using base model");
            return $modelData;
        }

        \Log::info("🔗 Setting up foreign key joins for {$tableName}");
        $joinFields = ["{$tableName}.*"];
        $fieldsets = [];

        // Safety check: ensure foreign_keys is array
        $foreignKeys = $columnData[$tableName]['foreign_keys'];
        if (!is_array($foreignKeys)) {
            \Log::warning("⚠️  foreign_keys is not array", [
                'foreign_keys_type' => gettype($foreignKeys),
                'foreign_keys_value' => $foreignKeys,
                'table' => $tableName
            ]);
            return $modelData;
        }

        // Build joins array and apply via guarded helper to avoid duplicates
        $joins = [];
        foreach ($foreignKeys as $foreignKey => $localKey) {
            $tables = explode('.', $foreignKey);
            $foreignTable = $tables[0];
            $joins[] = ['type' => 'left', 'table' => $foreignTable, 'first' => $foreignKey, 'second' => $localKey];
            $fieldsets[$foreignTable] = diy_get_table_columns($foreignTable);
        }
        if (!empty($joins) && method_exists($this, 'applyRelationJoins')) {
            $this->applyRelationJoins($modelData, $joins);
        } else {
            // Fallback if helper missing
            foreach ($joins as $j) { $modelData = $modelData->leftJoin($j['table'], $j['first'], '=', $j['second']); }
        }

        // Safety check: ensure fieldsets is array and each fieldset is array
        if (!is_array($fieldsets)) {
            \Log::warning("⚠️  fieldsets is not array", [
                'fieldsets_type' => gettype($fieldsets),
                'table' => $tableName
            ]);
            return $modelData->select($joinFields);
        }

        foreach ($fieldsets as $foreignTable => $fields) {
            // Safety check: ensure fields is array
            if (!is_array($fields)) {
                \Log::warning("⚠️  fieldset fields is not array", [
                    'fields_type' => gettype($fields),
                    'foreign_table' => $foreignTable,
                    'table' => $tableName
                ]);
                continue;
            }

            foreach ($fields as $field) {
                if ($field === 'id') {
                    $joinFields[] = "{$foreignTable}.{$field} as {$foreignTable}_{$field}";
                } else {
                    $joinFields[] = "{$foreignTable}.{$field}";
                }
            }
        }

        return $modelData->select($joinFields);
    }

    /**
     * Filter actions based on user privileges
     */
    private function filterActionsByPrivileges($actionList, $privileges)
    {
        // Safety check: ensure actionList is array
        if (!is_array($actionList)) {
            \Log::warning("⚠️  actionList is not array", [
                'actionList_type' => gettype($actionList),
                'actionList_value' => $actionList
            ]);
            return [];
        }

        if ($privileges['role_group'] <= 1) {
            return $actionList;
        }

        if (empty($privileges['role'])) {
            return [];
        }

        // Extra safety check: ensure privileges['role'] is array
        if (!is_array($privileges['role'])) {
            \Log::warning("⚠️  privileges['role'] is not array", [
                'role_type' => gettype($privileges['role']),
                'role_value' => $privileges['role']
            ]);
            return [];
        }

        $baseInfo = routelists_info()['base_info'];
        if (strpos(json_encode($privileges['role']), $baseInfo) === false) {
            return [];
        }

        $allowedActions = [];
        $routeMapping = $this->getRouteActionMapping();

        // Safety check: ensure routeMapping is array
        if (!is_array($routeMapping)) {
            \Log::warning("⚠️  routeMapping is not array", [
                'routeMapping_type' => gettype($routeMapping),
                'routeMapping_value' => $routeMapping
            ]);
            return [];
        }

        foreach ($privileges['role'] as $role) {
            if (!diy_string_contained($role, $baseInfo)) {
                continue;
            }

            $routeName = routelists_info($role)['last_info'];
            foreach ($routeMapping as $routes => $action) {
                if (in_array($routeName, explode(',', $routes))) {
                    $allowedActions[$action] = $action;
                    break;
                }
            }
        }

        $result = [];
        foreach ($actionList as $action) {
            if (isset($allowedActions[$action]) || !in_array($action, $this->getDefaultActions())) {
                $result[] = $action;
            }
        }

        return $result;
    }

    /**
     * Get mapping between route names and actions
     */
    private function getRouteActionMapping()
    {
        // Delegate to trait to keep mapping consistent and overridable by config
        return $this->traitGetRouteActionMapping();
    }



    /**
     * Apply where conditions to the model
     */
    private function applyConditions($modelData, $data, $tableName)
    {
        // Check if conditions configuration exists safely
        if (!isset($data->datatables->conditions) || 
            !isset($data->datatables->conditions[$tableName]) ||
            empty($data->datatables->conditions[$tableName]['where'])) {
            \Log::info("📝 No conditions found for table", ['table' => $tableName]);
            return $modelData;
        }

        \Log::info("🔍 Applying conditions", ['table' => $tableName]);
        $whereConditions = $this->parseWhereConditions(
            $data->datatables->conditions[$tableName]['where']
        );

        if (!empty($whereConditions['simple'])) {
            $modelData = $modelData->where($whereConditions['simple']);
        }

        if (!empty($whereConditions['in']) && is_array($whereConditions['in'])) {
            foreach ($whereConditions['in'] as $field => $values) {
                if (is_array($values) && !empty($values)) {
                    $modelData = $modelData->whereIn($field, $values);
                }
            }
        }

        return $modelData;
    }

    /**
     * Parse where conditions into simple and whereIn conditions
     */
    private function parseWhereConditions($conditions)
    {
        $whereConditions = ['simple' => [], 'in' => []];

        // Safety check: ensure conditions is array
        if (!is_array($conditions)) {
            \Log::warning("⚠️  conditions parameter is not array", [
                'conditions_type' => gettype($conditions),
                'conditions_value' => $conditions
            ]);
            return $whereConditions;
        }

        foreach ($conditions as $condition) {
            if (!is_array($condition['value'])) {
                $whereConditions['simple'][] = [
                    $condition['field_name'],
                    $condition['operator'],
                    $condition['value']
                ];
            } else {
                $whereConditions['in'][$condition['field_name']] = $condition['value'];
            }
        }

        return $whereConditions;
    }

    /**
     * Apply additional filters from request parameters
     */
    private function applyFilters($modelData, $filters, $tableName, $firstField)
    {
        // Debug logging
        \Log::info('🔍 APPLY FILTERS DEBUG', [
            'tableName' => $tableName,
            'firstField' => $firstField,
            'filters_input' => $filters,
            'filters_empty' => empty($filters),
            'filters_is_array' => is_array($filters),
            'model_class' => get_class($modelData),
            'query_builder_info' => 'Ready to apply WHERE conditions'
        ]);

        if (empty($filters) || !is_array($filters)) {
            \Log::info('❌ NO FILTERS APPLIED - returning default WHERE clause');
            return $modelData->where("{$tableName}.{$firstField}", '!=', null);
        }

        $processedFilters = $this->processFilters($filters);

        \Log::info('🔧 PROCESSED FILTERS RESULT', [
            'processedFilters' => $processedFilters,
            'processedFilters_empty' => empty($processedFilters)
        ]);

        if (empty($processedFilters)) {
            \Log::info('❌ PROCESSED FILTERS EMPTY - returning default WHERE clause');
            return $modelData->where("{$tableName}.{$firstField}", '!=', null);
        }

        \Log::info('✅ APPLYING FILTERS TO QUERY', ['filters' => $processedFilters]);
        
        // Enable SQL query logging to see actual database queries
        \DB::enableQueryLog();
        
        // Apply each processed filter with qualified columns and proper operators
        foreach ($processedFilters as $col => $val) {
            $qualified = (strpos($col, '.') === false) ? "{$tableName}.{$col}" : $col;
            if (is_array($val)) {
                $flat = array_values(array_unique(array_filter($val, static function($v) {
                    return $v !== null && $v !== '';
                })));
                if (!empty($flat)) {
                    $modelData = $modelData->whereIn($qualified, $flat);
                }
            } else {
                $modelData = $modelData->where($qualified, 'LIKE', '%' . $val . '%');
            }
        }
        
        // Log the SQL queries that were executed
        $queries = \DB::getQueryLog();
        \Log::info('📊 SQL QUERIES WITH FILTERS', ['queries' => $queries]);
        
        return $modelData;
    }

    /**
     * Process filters from request parameters
     */
    private function processFilters($filters)
    {
        \Log::info('🔍 PROCESS FILTERS START', ['input_filters' => $filters]);
        
        // Safety check: ensure filters is array
        if (!is_array($filters)) {
            \Log::warning("⚠️  filters parameter is not array", [
                'filters_type' => gettype($filters),
                'filters_value' => $filters
            ]);
            return [];
        }
        
        $validFilters = [];

        foreach ($filters as $name => $value) {
            $isValid = $this->isValidFilterParameter($name, $value);
            
            \Log::info('🔍 FILTER PARAMETER CHECK', [
                'name' => $name,
                'value' => $value,
                'is_valid' => $isValid
            ]);
            
            if (!$isValid) {
                continue;
            }

            if (!is_array($value)) {
                $validFilters[] = [$name => urldecode($value)];
            } else {
                // Safety check: ensure value is actually iterable
                if (!is_array($value) && !is_object($value)) {
                    \Log::warning("⚠️  filter value is not iterable", [
                        'name' => $name,
                        'value_type' => gettype($value),
                        'value' => $value
                    ]);
                    continue;
                }

                foreach ($value as $val) {
                    $validFilters[] = [$name => urldecode($val)];
                }
            }
        }

        \Log::info('🔍 VALID FILTERS EXTRACTED', ['validFilters' => $validFilters]);

        if (empty($validFilters)) {
            \Log::info('❌ NO VALID FILTERS FOUND');
            return [];
        }

        $consolidated = $this->consolidateFilters($validFilters);
        \Log::info('🔍 CONSOLIDATED FILTERS', ['consolidated' => $consolidated]);
        
        return $consolidated;
    }

    /**
     * CRITICAL FIX: Check if filter parameter is valid
     */
    private function isValidFilterParameter($name, $value)
    {
        // HARD-CODED EXCLUSION: DataTables control parameters
        $datatables_control_params = [
            'draw', 'columns', 'order', 'start', 'length', 'search',
            'renderDataTables', 'difta', '_token', '_', 'method',
            'data', 'action', 'submit', 'submit_button', 'filters',
            'filterDataTables'
        ];
        
        // Config-based exclusion as backup
        $config_reserved = $this->getReservedParameters();
        $all_reserved = array_merge($datatables_control_params, $config_reserved);
        
        // Strict validation
        $is_reserved = in_array($name, $all_reserved, true);
        $is_empty = empty($value) && $value !== '0' && $value !== 0;
        $is_special = strpos($name, 'csrf') !== false || strpos($name, 'token') !== false;
        
        \Log::info('🔍 LEGACY FILTER VALIDATION', [
            'name' => $name,
            'value_type' => gettype($value),
            'value_sample' => is_string($value) ? substr((string)$value, 0, 50) : $value,
            'is_reserved' => $is_reserved,
            'is_empty' => $is_empty,
            'is_special' => $is_special,
            'all_reserved_count' => count($all_reserved),
            'final_result' => !$is_reserved && !$is_empty && !$is_special
        ]);
        
        $result = !$is_reserved && !$is_empty && !$is_special;
        
        \Log::info('🔍 LEGACY FILTER VALIDATION RESULT', [
            'name' => $name,
            'result' => $result ? 'VALID' : 'INVALID'
        ]);
        
        return $result;
    }

    /**
     * Consolidate filters into final format
     */
    private function consolidateFilters($validFilters)
    {
        // Safety check: ensure validFilters is array
        if (!is_array($validFilters)) {
            \Log::warning("⚠️  validFilters is not array", [
                'validFilters_type' => gettype($validFilters),
                'validFilters_value' => $validFilters
            ]);
            return [];
        }

        $consolidated = [];
        
        foreach ($validFilters as $filter) {
            // Safety check: ensure each filter is array
            if (!is_array($filter)) {
                \Log::warning("⚠️  filter item is not array", [
                    'filter_type' => gettype($filter),
                    'filter_value' => $filter
                ]);
                continue;
            }
            
            foreach ($filter as $key => $value) {
                $consolidated[$key] = $value; // Take last value for each key
            }
        }

        return $consolidated;
    }

    /**
     * Setup pagination configuration
     */
    private function setupPagination($modelData)
    {
        $config = $this->getDefaultPagination();
        
        // CRITICAL DEBUG: Log the modelData object before calling count()
        \Log::info("🔍 SETUP PAGINATION DEBUG", [
            'modelData_class' => get_class($modelData),
            'modelData_type' => gettype($modelData),
            'has_connection' => method_exists($modelData, 'getConnection') ? 'YES' : 'NO',
            'connection_value' => method_exists($modelData, 'getConnection') ? 
                ($modelData->getConnection() ? get_class($modelData->getConnection()) : 'NULL') : 'N/A',
            'has_count_method' => method_exists($modelData, 'count') ? 'YES' : 'NO'
        ]);
        
        $config['total'] = $modelData->count();

        // Check both GET and POST data for pagination parameters
        $requestData = array_merge($_GET, $_POST);

        if (!empty($requestData['start'])) {
            $config['start'] = (int) $requestData['start'];
        }

        if (!empty($requestData['length'])) {
            $config['length'] = (int) $requestData['length'];
        }

        return $config;
    }

    /**
     * Create datatables instance with basic configuration
     */
    private function createDatatables($modelData, $paginationConfig, $config)
    {
        $datatables = DataTable::of($modelData)
            ->setTotalRecords($paginationConfig['total'])
            ->setFilteredRecords($paginationConfig['total'])
            ->blacklist($config['blacklists'])
            ->smart(true);

        $this->setupRawColumns($datatables);

        // Ordering wiring via feature flag
        $useTraits = (bool) (config('datatable.use_traits', false));
        if ($useTraits) {
            $this->applyOrderingTrait($datatables, $modelData, [
                'default_order' => !empty($config['orderBy']) ? [
                    ['column' => $config['orderBy']['column'] ?? 'id', 'direction' => $config['orderBy']['order'] ?? 'asc']
                ] : []
            ]);
        } else {
            $this->setupOrdering($datatables, $config['orderBy'], $config['columnData']);
        }

        return $datatables;
    }

    /**
     * Setup raw columns for HTML content
     */
    private function setupRawColumns($datatables)
    {
        $rawColumns = ['action', 'flag_status'];
        
        if (!empty($this->form->imageTagFieldsDatatable)) {
            $imageColumns = array_keys($this->form->imageTagFieldsDatatable);
            $rawColumns = array_merge($rawColumns, $imageColumns);
        }

        $datatables->rawColumns($rawColumns);
    }

    /**
     * Setup column ordering with relationship support
     */
    private function setupOrdering($datatables, $orderBy, $columnData)
    {
        if (!empty($orderBy)) {
            $column = $orderBy['column'];
            $order = $orderBy['order'];
            
            \Log::info('🔄 SETUP ORDERING DEBUG', [
                'column' => $column,
                'order' => $order,
                'orderBy_config' => $orderBy
            ]);
            
            $datatables->order(function ($query) use ($column, $order) {
                // Map relationship columns to actual joined field names
                $relationshipFieldMap = [
                    'group_info' => 'base_group.group_info',
                    'group_name' => 'base_group.group_name', 
                    'group_alias' => 'base_group.group_alias'
                ];
                
                // Use mapped field name if it's a relationship column, otherwise use original
                $orderColumn = $relationshipFieldMap[$column] ?? $column;
                
                \Log::info('✅ APPLYING ORDER BY', [
                    'original_column' => $column,
                    'mapped_column' => $orderColumn,
                    'order_direction' => $order,
                    'is_relationship' => isset($relationshipFieldMap[$column])
                ]);
                
                $query->orderBy($orderColumn, $order);
            });
        }
        
        // Also handle DataTables request ordering (from frontend clicks)
        $useTraits = (bool) (config('datatable.use_traits', false));
        if ($useTraits) {
            $this->handleDataTablesOrderingTrait($datatables);
        } else {
            $this->handleDataTablesOrdering($datatables);
        }
    }

    /**
     * Handle DataTables dynamic ordering from frontend clicks
     */
    private function handleDataTablesOrdering($datatables)
    {
        // Check if there's ordering data from DataTables request
        $request = request();
        if ($request->has('order') && is_array($request->get('order'))) {
            $orderData = $request->get('order')[0] ?? null;
            $columns = $request->get('columns', []);
            
            if ($orderData && isset($columns[$orderData['column']])) {
                $columnName = $columns[$orderData['column']]['data'];
                $direction = $orderData['dir'];
                
                \Log::info('🎯 DATATABLES FRONTEND ORDERING', [
                    'column_index' => $orderData['column'],
                    'column_name' => $columnName,
                    'direction' => $direction,
                    'total_columns' => count($columns)
                ]);
                
                $datatables->order(function ($query) use ($columnName, $direction) {
                    // Map relationship columns to actual joined field names  
                    $relationshipFieldMap = [
                        'group_info' => 'base_group.group_info',
                        'group_name' => 'base_group.group_name',
                        'group_alias' => 'base_group.group_alias'
                    ];
                    
                    // Use mapped field name if it's a relationship column, otherwise use original
                    $orderColumn = $relationshipFieldMap[$columnName] ?? $columnName;
                    
                    \Log::info('✅ APPLYING FRONTEND ORDER BY', [
                        'original_column' => $columnName,
                        'mapped_column' => $orderColumn,
                        'direction' => $direction,
                        'is_relationship' => isset($relationshipFieldMap[$columnName])
                    ]);
                    
                    $query->orderBy($orderColumn, $direction);
                });
            }
        }
    }

    /**
     * Apply various column modifications
     */
    private function applyColumnModifications($datatables, $modelData, $data, $tableName, $config)
    {
        $this->processImageColumns($datatables, $modelData);
        // Relationship wiring behind feature flag
        $useTraits = (bool) (config('datatable.use_traits', false));
        if ($useTraits) {
            $this->setupRelationshipsTrait($modelData, $config);
        } else {
            $this->processRelationalData($datatables, $data, $tableName, $config);
        }

        $this->processStatusColumns($datatables, $modelData);
        $this->processFormulaColumns($datatables, $data, $tableName);
        $this->processFormattedColumns($datatables, $data, $tableName);
        
        \Log::info('🔧 COLUMN MODIFICATIONS APPLIED', [
            'table' => $tableName,
            'image_processing' => 'ENABLED',
            'relational_processing' => $useTraits ? 'TRAIT' : 'ENABLED',
            'status_processing' => 'ENABLED'
        ]);
    }

    /**
     * Process image columns
     */
    private function processImageColumns($datatables, $modelData)
    {
        try {
            $modelResults = $modelData->get();
            
            // Safety check: ensure get() returns collection
            if (!is_object($modelResults) && !is_array($modelResults)) {
                \Log::warning("⚠️  modelData->get() is not iterable", [
                    'result_type' => gettype($modelResults),
                    'result_value' => $modelResults
                ]);
                return;
            }
            
            foreach ($modelResults as $model) {
                $this->imageViewColumn($model, $datatables);
                break; // Only need one iteration to set up columns
            }
        } catch (\Exception $e) {
            \Log::error("❌ Error in processImageColumns", [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Process relational data columns
     */
    private function processRelationalData($datatables, $data, $tableName, $config)
    {
        $columnData = $config['columnData'];
        
        // Debug column configuration
        \Log::info('🔍 PROCESSING RELATIONAL DATA DEBUG', [
            'table' => $tableName,
            'has_columnData' => !empty($columnData),
            'has_table_key' => isset($columnData[$tableName]),
            'has_relations' => isset($columnData[$tableName]) && isset($columnData[$tableName]['relations']),
            'columnData_keys' => !empty($columnData) ? array_keys($columnData) : [],
            'table_keys' => isset($columnData[$tableName]) ? array_keys($columnData[$tableName]) : [],
            'relations_data' => isset($columnData[$tableName]) && isset($columnData[$tableName]['relations']) ? $columnData[$tableName]['relations'] : 'not_set'
        ]);
        
        // Check if table configuration and relations exist safely
        if (!isset($columnData[$tableName]) || empty($columnData[$tableName]['relations'])) {
            \Log::warning('❌ NO RELATIONS CONFIGURATION FOUND', [
                'table' => $tableName,
                'available_config_keys' => !empty($columnData) ? array_keys($columnData) : []
            ]);
            
            // Try alternative: Auto-process relationship columns if no config but data exists
            if ($tableName === 'users') {
                \Log::info('🔧 ATTEMPTING AUTO RELATIONSHIP COLUMN PROCESSING for users table');
                
                // Auto-process known relationship columns for users
                $relationshipColumns = ['group_name', 'group_alias', 'group_info'];
                foreach ($relationshipColumns as $column) {
                    $datatables->editColumn($column, function ($data) use ($column) {
                        // Handle both object and array data access
                        if (is_object($data)) {
                            return $data->$column ?? null;
                        } else {
                            return $data[$column] ?? null;
                        }
                    });
                }
                
                \Log::info('✅ AUTO RELATIONSHIP COLUMNS PROCESSED', [
                    'columns' => $relationshipColumns
                ]);
            }
            
            return;
        }

        $relations = $columnData[$tableName]['relations'];
        
        // Safety check: ensure relations is array
        if (!is_array($relations)) {
            \Log::warning("⚠️  relations is not array", [
                'relations_type' => gettype($relations),
                'relations_value' => $relations,
                'table' => $tableName
            ]);
            return;
        }

        foreach ($relations as $field => $relationConfig) {
            $relationData = $relationConfig['relation_data'];
            
            \Log::info("🔗 PROCESSING RELATION FIELD: {$field}", [
                'field' => $field,
                'relation_data_keys' => array_keys($relationData),
                'relation_data_sample' => array_slice($relationData, 0, 3, true)
            ]);
            
            $datatables->editColumn($field, function ($data) use ($relationData, $field) {
                // Handle both object and array data access
                if (is_object($data)) {
                    $dataId = intval($data->id ?? 0);
                    $groupId = intval($data->group_id ?? 0);
                } else {
                    $dataId = intval($data['id'] ?? 0);
                    $groupId = intval($data['group_id'] ?? 0);
                }
                
                // Priority 1: Try to get relation data by group_id (most logical for user-group relations)
                if (isset($relationData[$groupId]) && !empty($relationData[$groupId]['field_value'])) {
                    $result = $relationData[$groupId]['field_value'];
                    \Log::info("✅ RELATION DATA FOUND by group_id", [
                        'field' => $field,
                        'user_id' => $dataId,
                        'group_id' => $groupId,
                        'result' => $result
                    ]);
                    return $result;
                }
                
                // Priority 2: Try by user ID (fallback for direct user relations)
                if (isset($relationData[$dataId]) && !empty($relationData[$dataId]['field_value'])) {
                    $result = $relationData[$dataId]['field_value'];
                    \Log::info("✅ RELATION DATA FOUND by user_id", [
                        'field' => $field,
                        'user_id' => $dataId,
                        'group_id' => $groupId,
                        'result' => $result
                    ]);
                    return $result;
                }
                
                // Priority 3: Search through all relation data for matching group_id in pivot data
                // Safety check: ensure relationData is array
                if (!is_array($relationData)) {
                    \Log::warning("⚠️  relationData is not array for pivot search", [
                        'relationData_type' => gettype($relationData),
                        'field' => $field
                    ]);
                    return null;
                }
                
                foreach ($relationData as $key => $relData) {
                    if (isset($relData['group_id']) && intval($relData['group_id']) === $groupId) {
                        $result = $relData['field_value'] ?? null;
                        \Log::info("✅ RELATION DATA FOUND by pivot search", [
                            'field' => $field,
                            'user_id' => $dataId,
                            'group_id' => $groupId,
                            'found_key' => $key,
                            'result' => $result
                        ]);
                        return $result;
                    }
                }
                
                \Log::warning("❌ NO RELATION DATA FOUND", [
                    'field' => $field,
                    'user_id' => $dataId,
                    'group_id' => $groupId,
                    'available_keys' => array_keys($relationData)
                ]);
                
                return null;
            });
        }
    }

    /**
     * Process status columns with special formatting
     */
    private function processStatusColumns($datatables, $modelData)
    {
        // Delegate to trait if available for consistency (no behavior change intended)
        if (method_exists($this, 'processStatusColumnsTrait')) {
            return $this->processStatusColumnsTrait($datatables, $modelData);
        }

        $statusColumns = [
            'flag_status' => function($model) {
                return diy_unescape_html(diy_form_internal_flag_status($model->flag_status));
            },
            'active' => function($model) {
                return diy_form_set_active_value($model->active);
            },
            'update_status' => function($model) {
                return diy_form_set_active_value($model->update_status);
            },
            'request_status' => function($model) {
                return diy_form_request_status(true, $model->request_status);
            },
            'ip_address' => function($model) {
                return $model->ip_address === '::1' 
                    ? diy_form_get_client_ip() 
                    : $model->ip_address;
            }
        ];

        try {
            $modelResults = $modelData->get();
            
            // Safety check: ensure get() returns collection
            if (!is_object($modelResults) && !is_array($modelResults)) {
                \Log::warning("⚠️  modelData->get() is not iterable in processStatusColumns", [
                    'result_type' => gettype($modelResults),
                    'result_value' => $modelResults
                ]);
                return;
            }
            
            foreach ($modelResults as $model) {
                foreach ($statusColumns as $column => $callback) {
                    if (!empty($model->$column)) {
                        $datatables->editColumn($column, $callback);
                    }
                }
                break; // Only need one iteration to check columns
            }
        } catch (\Exception $e) {
            \Log::error("❌ Error in processStatusColumns", [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * Process formula columns
     */
    private function processFormulaColumns($datatables, $data, $tableName)
    {
        // Delegate to trait if available; fallback to current implementation
        if (method_exists($this, 'processFormulaColumnsTrait')) {
            return $this->processFormulaColumnsTrait($datatables, $data, $tableName);
        }

        // Check if formula configuration exists safely
        if (!isset($data->datatables->formula) || 
            !isset($data->datatables->formula[$tableName]) || 
            empty($data->datatables->formula[$tableName])) {
            \Log::info("📝 No formula configuration for table", ['table' => $tableName]);
            return;
        }

        $formulas = $data->datatables->formula[$tableName];
        
        // Safety check: ensure formulas is array
        if (!is_array($formulas)) {
            \Log::warning("⚠️  formulas is not array", [
                'formulas_type' => gettype($formulas),
                'table' => $tableName
            ]);
            return;
        }

        // Ensure columns structure exists before accessing
        if (!isset($data->datatables->columns[$tableName]['lists'])) {
            \Log::warning("⚠️  columns lists not found for formula processing", ['table' => $tableName]);
            $data->datatables->columns[$tableName]['lists'] = [];
        }

        // Extra safety: ensure lists is array before passing to helper
        $columnLists = $data->datatables->columns[$tableName]['lists'];
        if (!is_array($columnLists)) {
            \Log::warning("⚠️  column lists is not array for formula processing", [
                'lists_type' => gettype($columnLists),
                'lists_value' => $columnLists,
                'table' => $tableName
            ]);
            $columnLists = [];
        }

        try {
            $data->datatables->columns[$tableName]['lists'] = diy_set_formula_columns($columnLists, $formulas);
        } catch (\Exception $e) {
            \Log::error("❌ Error in diy_set_formula_columns", [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'table' => $tableName
            ]);
            // Keep original lists on error
        }

        foreach ($formulas as $formula) {
            $datatables->editColumn($formula['name'], function ($data) use ($formula) {
                $logic = new Formula($formula, $data);
                return $logic->calculate();
            });
        }
    }

    /**
     * Process formatted data columns
     */
    private function processFormattedColumns($datatables, $data, $tableName)
    {
        // Delegate to trait if available; fallback to current implementation
        if (method_exists($this, 'processFormattedColumnsTrait')) {
            return $this->processFormattedColumnsTrait($datatables, $data, $tableName);
        }

        // Check if format_data configuration exists safely
        if (!isset($data->datatables->columns) ||
            !isset($data->datatables->columns[$tableName]) ||
            empty($data->datatables->columns[$tableName]['format_data'])) {
            \Log::info("📝 No format_data configuration for table", ['table' => $tableName]);
            return;
        }

        $formatData = $data->datatables->columns[$tableName]['format_data'];
        
        // Safety check: ensure formatData is array
        if (!is_array($formatData)) {
            \Log::warning("⚠️  formatData is not array", [
                'formatData_type' => gettype($formatData),
                'table' => $tableName
            ]);
            return;
        }

        foreach ($formatData as $field => $format) {
            $datatables->editColumn($format['field_name'], function ($data) use ($field, $format) {
                if ($field !== $format['field_name']) {
                    return null;
                }

                $attributes = $data->getAttributes();
                if (empty($attributes[$field])) {
                    return null;
                }

                return diy_format(
                    $attributes[$field],
                    $format['decimal_endpoint'],
                    $format['separator'],
                    $format['format_type']
                );
            });
        }
    }

    /**
     * Setup row attributes for clickable rows
     */
    private function setupRowAttributes($datatables, $data, $tableName)
    {
        // Delegate to trait if available; fallback to current implementation
        if (method_exists($this, 'setupRowAttributesTrait')) {
            return $this->setupRowAttributesTrait($datatables, $data, $tableName);
        }

        $columnData = $data->datatables->columns ?? [];
        $attributes = ['class' => null, 'rlp' => null];

        // Check if table configuration and clickable exist safely
        if (isset($columnData[$tableName]) && 
            !empty($columnData[$tableName]['clickable']) && 
            count($columnData[$tableName]['clickable']) >= 1) {
            
            \Log::info("🔗 Setting up clickable row attributes", ['table' => $tableName]);
            $attributes['class'] = 'row-list-url';
            $attributes['rlp'] = function ($model) {
                return diy_unescape_html(encode_id(intval($model->id)));
            };
        }

        $datatables->setRowAttr($attributes);
    }

    /**
     * Add action column to datatables
     */
    private function addActionColumn($datatables, $modelData, $actionConfig, $data)
    {
        // Use trait composer to ensure consistent structure
        $actionData = method_exists($this, 'composeActionData')
            ? $this->composeActionData($modelData, $actionConfig, $data)
            : $this->prepareActionData($modelData, $actionConfig, $data);
        $urlTarget = $data->datatables->useFieldTargetURL;

        $datatables->addColumn('action', function ($model) use ($actionData, $urlTarget) {
            return $this->setRowActionURLs($model, $actionData, $urlTarget);
        });
    }

    /**
     * Prepare action data for button generation
     */
    private function prepareActionData($modelData, $actionConfig, $data)
    {
        // Back-compat path kept for safety; prefer composeActionData from trait
        return $this->composeActionData($modelData, (array) $actionConfig, $data);
    }

    /**
     * Determine which actions should be removed based on privileges
     */
    private function determineRemovedActions($actionConfig, $data)
    {
        $privileges = $this->set_module_privileges();
        $baseRemoved = $data->datatables->button_removed ?? [];

        if ($privileges['role_group'] <= 1) {
            return $baseRemoved;
        }

        if (!empty($actionConfig['removed'])) {
            return $actionConfig['removed'];
        }

        return $baseRemoved;
    }

    /**
     * Check if Enhanced Architecture result is missing expected relational columns
     * 
     * @param mixed $result Result from Enhanced Architecture
     * @return bool True if missing relational columns
     */
    private function hasMissingRelationalColumns($result): bool
    {
        // Extract expected columns from request
        $expectedColumns = $this->getExpectedColumnsFromRequest();
        
        // Check if result has data
        if (!isset($result->original['data']) || empty($result->original['data'])) {
            return false; // No data to check
        }
        
        $firstRow = $result->original['data'][0] ?? [];
        $actualColumns = array_keys((array) $firstRow);
        
        // Common relational columns that might be missing
        $relationalColumns = ['group_info', 'group_name'];
        
        foreach ($relationalColumns as $column) {
            if (in_array($column, $expectedColumns) && !in_array($column, $actualColumns)) {
                \Log::info("🔍 Missing relational column detected", [
                    'expected_column' => $column,
                    'actual_columns' => $actualColumns,
                    'expected_columns' => $expectedColumns
                ]);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get missing columns from Enhanced Architecture result
     * 
     * @param mixed $result Result from Enhanced Architecture
     * @return array Missing columns
     */
    private function getMissingColumns($result): array
    {
        $expectedColumns = $this->getExpectedColumnsFromRequest();
        
        if (!isset($result->original['data']) || empty($result->original['data'])) {
            return [];
        }
        
        $firstRow = $result->original['data'][0] ?? [];
        $actualColumns = array_keys((array) $firstRow);
        
        return array_diff($expectedColumns, $actualColumns);
    }
    
    /**
     * Get expected columns from DataTables request
     * 
     * @return array Expected column names
     */
    private function getExpectedColumnsFromRequest(): array
    {
        try {
            $columns = request('columns', []);
            $expectedColumns = [];
            
            if (is_array($columns)) {
                foreach ($columns as $column) {
                    if (is_array($column) && isset($column['data'])) {
                        $expectedColumns[] = $column['data'];
                    }
                }
            }
            
            return $expectedColumns;
            
        } catch (\Exception $e) {
            \Log::warning("⚠️ Error getting expected columns: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if DT_RowIndex column is expected in the request
     * 
     * @return bool True if DT_RowIndex is expected
     */
    private function checkIfDTRowIndexNeeded(): bool
    {
        try {
            // Check request columns for DT_RowIndex
            $columns = request('columns', []);
            
            if (!is_array($columns)) {
                return false;
            }
            
            foreach ($columns as $column) {
                if (is_array($column) && isset($column['data']) && $column['data'] === 'DT_RowIndex') {
                    \Log::info("🔢 DT_RowIndex column detected in request columns");
                    return true;
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            \Log::warning("⚠️ Error checking for DT_RowIndex: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finalize datatable with index column if needed
     */
    private function finalizeDatatable($datatables, $indexLists)
    {
        if ($indexLists === true) {
            return $datatables->addIndexColumn()->make(true);
        }

        return $datatables->make();
    }

    /**
     * Set row action URLs for buttons
     */
    private function setRowActionURLs($model, $data, $fieldTarget = 'id')
    {
        // Delegate to trait if renderer exists; fallback to legacy helper
        if (method_exists($this, 'renderActionButtons')) {
            // Build a row-like array to satisfy trait renderer signature
            $row = is_array($model) ? $model : (array) $model;
            // Preserve legacy URL generation by relying on diy_table_action_button when available
            if (function_exists('diy_table_action_button')) {
                return diy_table_action_button(
                    $model,
                    $fieldTarget,
                    $data['current_url'],
                    $data['action']['data'],
                    $data['action']['removed']
                );
            }
            return $this->renderActionButtons((array) ($data['action']['data'] ?? []), $row, $this->set_module_privileges());
        }

        return diy_table_action_button(
            $model, 
            $fieldTarget, 
            $data['current_url'], 
            $data['action']['data'], 
            $data['action']['removed']
        );
    }

    /**
     * Process image view column
     */
    private function imageViewColumn($model, $datatables)
    {
        $imageFields = $this->detectImageFields($model);

        // Safety check: ensure imageFields is array
        if (!is_array($imageFields)) {
            \Log::warning("⚠️  imageFields is not array", [
                'imageFields_type' => gettype($imageFields),
                'imageFields_value' => $imageFields
            ]);
            return;
        }

        foreach ($imageFields as $field => $isImage) {
            if (!isset($model->$field)) {
                continue;
            }

            $datatables->editColumn($field, function ($model) use ($field) {
                return $this->generateImageHtml($model->$field, $field);
            });
        }
    }

    /**
     * Detect which fields contain image data
     */
    private function detectImageFields($model)
    {
        $imageFields = [];
        
        // Skip fields that are clearly not images
        $nonImageFields = [
            'id', 'username', 'email', 'password', 'fullname', 'alias', 'created_by', 'updated_by', 
            'created_at', 'updated_at', 'deleted_at', 'active', 'group_id', 'group_name', 
            'group_alias', 'group_info', 'cryptcode', 'remember_token', 'language', 'timezone',
            'first_route', 'reg_date', 'last_visit_date', 'past_visit_date', 'change_password',
            'last_change_password_date', 'expire_date', 'phone', 'address', 'birth_date',
            'birth_place', 'gender', 'email_verified_at', 'ip_address', 'file_info'
        ];

        // Safety check: ensure model is iterable
        if (!is_object($model) && !is_array($model)) {
            \Log::warning("⚠️  model is not iterable for image detection", [
                'model_type' => gettype($model),
                'model_value' => $model
            ]);
            return [];
        }

        foreach ($model as $field => $value) {
            // Skip processing clearly non-image fields
            if (in_array($field, $nonImageFields)) {
                continue;
            }
            
            // Skip null values
            if ($value === null || $value === '') {
                continue;
            }
            
            // Only check fields that might be images (photo, image, file, etc.)
            if (stripos($field, 'photo') !== false || 
                stripos($field, 'image') !== false || 
                stripos($field, 'file') !== false ||
                stripos($field, 'avatar') !== false ||
                stripos($field, 'thumb') !== false) {
                
                $validationResult = $this->checkValidImage($value);
                if ($validationResult !== false) {
                    $imageFields[$field] = $validationResult;
                }
            }
        }

        \Log::info('🖼️ IMAGE FIELDS DETECTION', [
            'detected_fields' => array_keys($imageFields),
            'total_model_fields' => count((array) $model),
            'skipped_non_image_fields' => count($nonImageFields)
        ]);

        return $imageFields;
    }

    /**
     * Generate HTML for image display
     */
    private function generateImageHtml($imagePath, $field)
    {
        $label = ucwords(str_replace('-', ' ', diy_clean_strings($field)));
        $imageCheck = $this->checkValidImage($imagePath);

        if ($imageCheck === false) {
            return $this->extractFilename($imagePath);
        }

        if ($imageCheck !== true) {
            return diy_unescape_html($imageCheck);
        }

        $displayPath = $this->getDisplayPath($imagePath);
        $alt = "imgsrc::{$label}";

        return diy_unescape_html(
            "<center><img class=\"cdy-img-thumb\" src=\"{$displayPath}\" alt=\"{$alt}\" /></center>"
        );
    }

    /**
     * Get display path for image (with thumbnail if available)
     */
    private function getDisplayPath($imagePath)
    {
        $pathParts = explode('/', $imagePath);
        $filename = array_pop($pathParts);
        $thumbnailPath = implode('/', $pathParts) . '/thumb/tnail_' . $filename;

        if (file_exists($this->setAssetPath($thumbnailPath))) {
            return $thumbnailPath;
        }

        return $imagePath;
    }

    /**
     * Extract filename from path
     */
    private function extractFilename($path)
    {
        $pathParts = explode('/', $path);
        return end($pathParts);
    }

    /**
     * Set asset path with proper formatting
     */
    private function setAssetPath($filePath, $http = false, $publicPath = 'public')
    {
        if ($http) {
            $assetsUrl = explode('/', url()->asset('assets'));
            $stringUrl = explode('/', $filePath);
            return implode('/', array_unique(array_merge($assetsUrl, $stringUrl)));
        }

        return str_replace($publicPath . '/', public_path("\\"), $filePath);
    }

    /**
     * Check if string contains valid image
     */
    private function checkValidImage($string, $localPath = true)
    {
        // Skip null/empty values
        if (empty($string) || $string === null) {
            return false;
        }
        
        // First check if string looks like an image path/filename
        $hasImageExtension = false;
        foreach ($this->getImageExtensions() as $extension) {
            if (strpos(strtolower($string), '.' . $extension) !== false) {
                $hasImageExtension = true;
                break;
            }
        }
        
        // If it doesn't look like an image file, return false (don't process as image)
        if (!$hasImageExtension) {
            return false;
        }
        
        // Only if it looks like an image file, check if file exists
        $filePath = $this->setAssetPath($string);

        if (!file_exists($filePath)) {
            // Return HTML warning only for files that should be images but don't exist
            return $this->generateMissingFileHtml($string);
        }

        return true;
    }

    /**
     * Generate HTML for missing file
     */
    private function generateMissingFileHtml($string)
    {
        $pathParts = explode('/', $string);
        $filename = end($pathParts);
        $message = "This File [ {$filename} ] Do Not or Never Exist!";

        return "<div class=\"show-hidden-on-hover missing-file\" title=\"{$message}\">" .
               "<i class=\"fa fa-warning\"></i>&nbsp;{$filename}</div>";
    }

    /**
     * Set filter for datatable processing
     */
    public function filter_datatable($request)
    {
        $this->filter_datatables = $request->all();
    }

    /**
     * Initialize filter datatables with advanced filtering options
     */
    public function init_filter_datatables($get = [], $post = [], $connection = null)
    {
        if (empty($get['filterDataTables'])) {
            return null;
        }

        $filterConfig = $this->parseFilterConfiguration($post);
        $sqlQuery = $this->buildFilterQuery($filterConfig);

        return diy_query($sqlQuery, 'SELECT', $filterConfig['connection']);
    }

    /**
     * Parse filter configuration from POST data
     */
    private function parseFilterConfiguration($post)
    {
        $connection = $post['grabCoDIYC'] ?? null;
        unset($post['grabCoDIYC']);

        $filters = $post['_diyF'] ?? [];
        unset($post['_diyF']);

        $diftaData = explode('::', $post['_difta']);
        $config = [
            'connection' => $connection,
            'filters' => $filters,
            'table' => $diftaData[1],
            'target' => $diftaData[2],
            'previous' => $diftaData[3],
            'foreignKeys' => json_decode($post['_forKeys'] ?? '[]', true)
        ];

        // Clean up POST data
        $reserved = ['filterDataTables', '_difta', '_token', '_n', '_forKeys'];
        
        // Safety check: ensure reserved is array
        if (!is_array($reserved)) {
            \Log::warning("⚠️  reserved keys is not array", [
                'reserved_type' => gettype($reserved),
                'reserved_value' => $reserved
            ]);
        } else {
            foreach ($reserved as $key) {
                unset($post[$key]);
            }
        }

        $config['conditions'] = $post;
        return $config;
    }

    /**
     * Build SQL query for filtering
     */
    private function buildFilterQuery($config)
    {
        $joins = $this->buildJoinClauses($config['foreignKeys']);
        $whereClause = $this->buildWhereClause($config);

        $sql = "SELECT DISTINCT `{$config['target']}` FROM `{$config['table']}`";
        
        if (!empty($joins)) {
            $sql .= " {$joins}";
        }
        
        $sql .= " WHERE {$whereClause}";

        return $sql;
    }

    /**
     * Build JOIN clauses for foreign keys
     */
    private function buildJoinClauses($foreignKeys)
    {
        if (empty($foreignKeys)) {
            return '';
        }

        // Safety check: ensure foreignKeys is array
        if (!is_array($foreignKeys)) {
            \Log::warning("⚠️  foreignKeys is not array in buildJoinClauses", [
                'foreignKeys_type' => gettype($foreignKeys),
                'foreignKeys_value' => $foreignKeys
            ]);
            return '';
        }

        $joinClauses = [];
        foreach ($foreignKeys as $foreignKey => $localKey) {
            $foreignTable = explode('.', $foreignKey)[0];
            $joinClauses[] = "LEFT JOIN {$foreignTable} ON {$foreignKey} = {$localKey}";
        }

        return implode(' ', $joinClauses);
    }

    /**
     * Build WHERE clause for filtering
     */
    private function buildWhereClause($config)
    {
        $conditions = [];

        // Add basic conditions
        // Safety check: ensure config['conditions'] is array
        if (!is_array($config['conditions'])) {
            \Log::warning("⚠️  config['conditions'] is not array", [
                'conditions_type' => gettype($config['conditions']),
                'conditions_value' => $config['conditions']
            ]);
        } else {
            foreach ($config['conditions'] as $field => $value) {
                $conditions[] = "`{$field}` = '{$value}'";
            }
        }

        // Add filter conditions
        // Safety check: ensure config['filters'] is array
        if (!is_array($config['filters'])) {
            \Log::warning("⚠️  config['filters'] is not array", [
                'filters_type' => gettype($config['filters']),
                'filters_value' => $config['filters']
            ]);
        } else {
            foreach ($config['filters'] as $filter) {
                $fieldName = $filter['field_name'];
                $value = $filter['value'];

                if (is_array($value)) {
                    $valueList = implode("', '", $value);
                    $conditions[] = "`{$fieldName}` IN ('{$valueList}')";
                } else {
                    $conditions[] = "`{$fieldName}` = '{$value}'";
                }
            }
        }

        $whereClause = implode(' AND ', $conditions);

        // Add previous conditions if specified
        if ($config['previous'] !== '#null') {
            $previousConditions = $this->parsePreviousConditions($config['previous']);
            $whereClause .= ' AND ' . implode(' AND ', $previousConditions);
        }

        return $whereClause;
    }

    /**
     * Parse previous conditions from encoded string
     */
    private function parsePreviousConditions($previousString)
    {
        $parts = explode("#", $previousString);
        $fields = explode('|', $parts[0]);
        $values = explode('|', $parts[1]);

        // Safety check: ensure fields is array
        if (!is_array($fields)) {
            \Log::warning("⚠️  fields is not array in parsePreviousConditions", [
                'fields_type' => gettype($fields),
                'fields_value' => $fields
            ]);
            return [];
        }

        $conditions = [];
        foreach ($fields as $index => $field) {
            $value = $values[$index] ?? '';
            $conditions[] = "`{$field}` = '{$value}'";
        }

        return $conditions;
    }
}