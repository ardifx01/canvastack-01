<?php

namespace Incodiy\Codiy\Library\Components\Table\Registry;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * ModelRegistry
 * 
 * Dynamic model registry that replaces hard-coded model mappings.
 * Provides flexible, configurable model resolution with auto-discovery
 * capabilities for improved maintainability and scalability.
 */
class ModelRegistry
{
    /**
     * Registry cache key prefix
     */
    private const CACHE_PREFIX = 'model_registry_';

    /**
     * Model configurations from config file
     * 
     * @var array
     */
    private array $modelConfigs;

    /**
     * Auto-discovery settings
     * 
     * @var array
     */
    private array $autoDiscoveryConfig;

    /**
     * Resolved model cache
     * 
     * @var array
     */
    private array $resolvedModels = [];

    /**
     * Create new ModelRegistry instance
     */
    public function __construct()
    {
        try {
            // Try to load configuration with proper error handling
            $this->modelConfigs = $this->loadConfig('data-providers.model_registry', []);
            $this->autoDiscoveryConfig = $this->loadConfig('data-providers.auto_discovery', [
                'enabled' => true,
                'model_namespaces' => [
                    'Incodiy\Codiy\Models\Admin\System\\',
                    'App\Models\\',
                    'App\\',
                ],
                'cache_discoveries' => false // Disable caching when config unavailable
            ]);
        } catch (\Throwable $e) {
            // Fallback to minimal configuration if config loading fails
            $this->modelConfigs = $this->getDefaultModelConfigs();
            $this->autoDiscoveryConfig = [
                'enabled' => true,
                'model_namespaces' => [
                    'Incodiy\Codiy\Models\Admin\System\\',
                    'App\Models\\',
                    'App\\',
                ],
                'cache_discoveries' => false
            ];
            
            // Log warning if possible
            if (function_exists('\\Log') && method_exists('\\Log', 'warning')) {
                \Log::warning('ModelRegistry: Configuration loading failed, using fallback', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Safely load configuration with fallback
     */
    private function loadConfig(string $key, array $default = []): array
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
     * Get default model configurations for critical tables
     */
    private function getDefaultModelConfigs(): array
    {
        return [
            'users' => [
                'class' => 'Incodiy\Codiy\Models\Admin\System\User',
                'type' => 'eloquent',
                'primary_key' => 'id',
                'table_name' => 'users',
                'relationships' => [],
                'default_columns' => ['id', 'username', 'fullname', 'email', 'active'],
                'searchable_columns' => ['username', 'fullname', 'email'],
                'sortable_columns' => ['id', 'username', 'fullname', 'email', 'created_at']
            ],
            'base_group' => [
                'class' => 'Incodiy\Codiy\Models\Admin\System\Group',
                'type' => 'eloquent',
                'primary_key' => 'id',
                'table_name' => 'base_group',
                'relationships' => [],
                'default_columns' => ['id', 'group_name', 'group_alias', 'group_info', 'active'],
                'searchable_columns' => ['group_name', 'group_alias', 'group_info'],
                'sortable_columns' => ['id', 'group_name', 'group_alias', 'created_at']
            ]
        ];
    }

    /**
     * Resolve model configuration by table name
     * 
     * @param string $tableName Table name to resolve
     * @return array Model configuration
     * @throws \InvalidArgumentException If model cannot be resolved
     */
    public function resolve(string $tableName): array
    {
        // Check cache first
        if (isset($this->resolvedModels[$tableName])) {
            return $this->resolvedModels[$tableName];
        }

        // Try explicit configuration first
        if (isset($this->modelConfigs[$tableName])) {
            $config = $this->modelConfigs[$tableName];
            $this->resolvedModels[$tableName] = $this->normalizeConfig($config, $tableName);
            return $this->resolvedModels[$tableName];
        }

        // MULTI-TABLE SUPPORT: Check if we can adapt existing model configuration
        $adaptedConfig = $this->tryAdaptExistingModel($tableName);
        if ($adaptedConfig) {
            $this->resolvedModels[$tableName] = $adaptedConfig;
            return $adaptedConfig;
        }

        // Try auto-discovery if enabled
        if ($this->autoDiscoveryConfig['enabled'] ?? false) {
            $config = $this->autoDiscoverModel($tableName);
            if ($config) {
                $this->resolvedModels[$tableName] = $config;
                
                // Cache discovery result if enabled
                if ($this->autoDiscoveryConfig['cache_discoveries'] ?? false) {
                    $this->cacheDiscovery($tableName, $config);
                }
                
                return $config;
            }
        }

        throw new \InvalidArgumentException("Model configuration not found for table: {$tableName}");
    }
    
    /**
     * Try to adapt existing model configuration for different table
     * This supports multi-table scenarios where one controller uses multiple tables
     * 
     * @param string $tableName Target table name
     * @return array|null Adapted configuration or null
     */
    private function tryAdaptExistingModel(string $tableName): ?array
    {
        // Look for models that might be related to this table
        foreach ($this->modelConfigs as $configTableName => $config) {
            // Skip exact matches (already handled above)
            if ($configTableName === $tableName) {
                continue;
            }
            
            // Check if tables are from same "family" (same base model)
            if ($this->isRelatedTable($configTableName, $tableName)) {
                \Log::info("🔄 Adapting model configuration for multi-table scenario", [
                    'original_table' => $configTableName,
                    'target_table' => $tableName,
                    'model_class' => $config['class'] ?? 'unknown'
                ]);
                
                // Create adapted configuration
                $adaptedConfig = $config;
                $adaptedConfig['table_name'] = $tableName;
                $adaptedConfig['adapted_from'] = $configTableName;
                
                // CRITICAL: Preserve connection from original configuration
                if (isset($config['connection'])) {
                    $adaptedConfig['connection'] = $config['connection'];
                    \Log::info("🔄 Preserving connection in adapted config", [
                        'original_table' => $configTableName,
                        'target_table' => $tableName,
                        'connection' => $config['connection']
                    ]);
                }
                
                // If it's an Eloquent model, create dynamic instance with different table
                if (isset($config['class']) && class_exists($config['class'])) {
                    try {
                        $model = new $config['class']();
                        if (method_exists($model, 'setTable')) {
                            $model->setTable($tableName);
                        }
                        
                        // CRITICAL: Preserve database connection from original model
                        $originalModel = new $config['class']();
                        $connection = $originalModel->getConnectionName();
                        if ($connection && method_exists($model, 'setConnection')) {
                            $model->setConnection($connection);
                            \Log::info("🔄 Setting connection for adapted model", [
                                'table' => $tableName,
                                'connection' => $connection
                            ]);
                        }
                        
                        $adaptedConfig['model_instance'] = $model;
                    } catch (\Exception $e) {
                        \Log::warning("Could not create adapted model instance: " . $e->getMessage());
                    }
                }
                
                return $this->normalizeConfig($adaptedConfig, $tableName);
            }
        }
        
        return null;
    }
    
    /**
     * Check if two tables are related (same model family)
     * 
     * @param string $table1 First table
     * @param string $table2 Second table  
     * @return bool True if tables are related
     */
    private function isRelatedTable(string $table1, string $table2): bool
    {
        // Extract base patterns
        $pattern1 = $this->extractTablePattern($table1);
        $pattern2 = $this->extractTablePattern($table2);
        
        // Check common patterns
        $commonPatterns = [
            'report_data_.*_program_keren_pro',
            'report_data_.*_program_',
            'base_.*',
            'user_.*'
        ];
        
        foreach ($commonPatterns as $pattern) {
            if (preg_match("/{$pattern}/", $table1) && preg_match("/{$pattern}/", $table2)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract pattern from table name for comparison
     * 
     * @param string $tableName Table name
     * @return string Extracted pattern
     */
    private function extractTablePattern(string $tableName): string
    {
        // Remove specific identifiers but keep base pattern
        $pattern = preg_replace('/_(summary|detail|monthly|outlets|national)(_.*)?$/', '', $tableName);
        return $pattern;
    }

    /**
     * Check if model configuration exists
     * 
     * @param string $tableName Table name to check
     * @return bool True if configuration exists
     */
    public function exists(string $tableName): bool
    {
        try {
            $this->resolve($tableName);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Get all registered model configurations
     * 
     * @return array All model configurations
     */
    public function getAll(): array
    {
        return $this->modelConfigs;
    }

    /**
     * Register a new model configuration
     * 
     * @param string $tableName Table name
     * @param array $config Model configuration
     * @return self For method chaining
     */
    public function register(string $tableName, array $config): self
    {
        $this->modelConfigs[$tableName] = $config;
        
        // Clear cached resolution for this table
        unset($this->resolvedModels[$tableName]);
        
        return $this;
    }

    /**
     * Auto-discover model configuration for a table
     * 
     * @param string $tableName Table name
     * @return array|null Model configuration or null if not found
     */
    private function autoDiscoverModel(string $tableName): ?array
    {
        // Check cache first
        $cacheKey = self::CACHE_PREFIX . 'discovery_' . $tableName;
        $cacheDuration = $this->autoDiscoveryConfig['cache_duration'] ?? 3600;
        
        if ($this->autoDiscoveryConfig['cache_discoveries'] ?? false) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Try to find model class
        $modelClass = $this->findModelClass($tableName);
        if (!$modelClass) {
            return null;
        }

        // Instantiate model to get real configuration
        $modelInstance = new $modelClass();
        
        // Smart primary key detection
        $primaryKey = $this->detectPrimaryKey($modelInstance, $tableName);
        
        // Smart default ordering detection
        $defaultOrder = $this->detectDefaultOrdering($modelInstance, $tableName, $primaryKey);
        
        // Create auto-discovered configuration
        $config = [
            'class' => $modelClass,
            'type' => 'eloquent',
            'primary_key' => $primaryKey,
            'table_name' => $tableName,
            'connection' => $modelInstance->getConnectionName(), // ✅ Get actual connection
            'timestamps' => $modelInstance->usesTimestamps(), // ✅ Get actual timestamp usage
            'relationships' => [],
            'default_columns' => $this->inferDefaultColumns($tableName, $modelInstance),
            'searchable_columns' => $this->inferSearchableColumns($tableName, $modelInstance),
            'sortable_columns' => $this->inferSortableColumns($tableName, $modelInstance),
            'default_order' => $defaultOrder, // ✅ Smart ordering
            'auto_discover' => true,
            'auto_discovered' => true,
            'discovered_at' => now()->toISOString()
        ];

        \Log::info("🔍 Auto-discovered model configuration", [
            'table' => $tableName,
            'class' => $modelClass,
            'config' => $config
        ]);

        return $config;
    }

    /**
     * Find model class for table name
     * 
     * @param string $tableName Table name
     * @return string|null Model class name or null if not found
     */
    private function findModelClass(string $tableName): ?string
    {
        $namespaces = $this->autoDiscoveryConfig['model_namespaces'] ?? [];
        
        // Try multiple naming strategies
        $modelNames = $this->generateModelNameVariations($tableName);
        
        foreach ($namespaces as $namespace) {
            foreach ($modelNames as $modelName) {
                $fullClassName = rtrim($namespace, '\\') . '\\' . $modelName;
                
                if (class_exists($fullClassName)) {
                    return $fullClassName;
                }
            }
        }

        return null;
    }

    /**
     * Generate multiple model name variations for better discovery
     * 
     * @param string $tableName Table name
     * @return array Array of possible model names
     */
    private function generateModelNameVariations(string $tableName): array
    {
        $variations = [];
        
        // Strategy 1: Standard Laravel convention
        $variations[] = $this->tableToModelName($tableName);
        
        // Strategy 2: For report tables with pattern "view_report_data_summary_*"
        if (str_starts_with($tableName, 'view_report_data_summary_')) {
            $reportName = str_replace('view_report_data_summary_', '', $tableName);
            $variations[] = Str::studly($reportName); // trikom_wireless → TrikomWireless
        }
        
        // Strategy 3: For report tables with pattern "report_*"
        if (str_starts_with($tableName, 'report_')) {
            $reportName = str_replace('report_', '', $tableName);
            $variations[] = Str::studly($reportName);
        }
        
        // Strategy 4: Extract meaningful parts (for complex names)
        if (str_contains($tableName, '_')) {
            $parts = explode('_', $tableName);
            // Take last 2-3 meaningful parts
            $meaningfulParts = array_slice($parts, -2);
            $variations[] = Str::studly(implode('_', $meaningfulParts));
            
            // Also try just the last part
            $variations[] = Str::studly(end($parts));
        }
        
        // Strategy 5: Just the table name as-is (studly case)
        $variations[] = Str::studly($tableName);
        
        // Remove duplicates and return
        return array_unique($variations);
    }

    /**
     * Smart primary key detection
     * 
     * @param \Illuminate\Database\Eloquent\Model $model Model instance
     * @param string $tableName Table name
     * @return string|null Primary key or null if none
     */
    private function detectPrimaryKey($model, string $tableName): ?string
    {
        try {
            // Get primary key from model
            $primaryKey = $model->getKeyName();
            
            // Check if the primary key column actually exists in database
            $connection = $model->getConnectionName();
            $columns = \Schema::connection($connection)->getColumnListing($tableName);
            
            if (in_array($primaryKey, $columns)) {
                \Log::info("✅ Primary key detected from model", [
                    'table' => $tableName,
                    'primary_key' => $primaryKey
                ]);
                return $primaryKey;
            }
            
            // Primary key doesn't exist in table, check for common alternatives
            $commonKeys = ['id', 'uuid', $tableName . '_id'];
            foreach ($commonKeys as $key) {
                if (in_array($key, $columns)) {
                    \Log::info("✅ Alternative primary key found", [
                        'table' => $tableName,
                        'primary_key' => $key
                    ]);
                    return $key;
                }
            }
            
            // No primary key found (common for views)
            \Log::info("ℹ️ No primary key found - likely a view/report table", [
                'table' => $tableName,
                'available_columns' => array_slice($columns, 0, 5)
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            \Log::warning("⚠️ Primary key detection failed", [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Smart default ordering detection
     * 
     * @param \Illuminate\Database\Eloquent\Model $model Model instance
     * @param string $tableName Table name  
     * @param string|null $primaryKey Primary key
     * @return array Default ordering [column, direction]
     */
    private function detectDefaultOrdering($model, string $tableName, ?string $primaryKey): array
    {
        try {
            $connection = $model->getConnectionName();
            $columns = \Schema::connection($connection)->getColumnListing($tableName);
            
            // Strategy 1: Use primary key if available
            if ($primaryKey && in_array($primaryKey, $columns)) {
                return [$primaryKey, 'desc'];
            }
            
            // Strategy 2: Look for common ordering columns
            $orderingColumns = [
                'created_at' => 'desc',
                'updated_at' => 'desc', 
                'period' => 'desc',
                'period_string' => 'desc',
                'date' => 'desc',
                'timestamp' => 'desc'
            ];
            
            foreach ($orderingColumns as $column => $direction) {
                if (in_array($column, $columns)) {
                    \Log::info("✅ Smart ordering detected", [
                        'table' => $tableName,
                        'column' => $column,
                        'direction' => $direction
                    ]);
                    return [$column, $direction];
                }
            }
            
            // Strategy 3: Use first column as fallback
            if (!empty($columns)) {
                $firstColumn = $columns[0];
                \Log::info("✅ Fallback ordering using first column", [
                    'table' => $tableName,
                    'column' => $firstColumn
                ]);
                return [$firstColumn, 'asc'];
            }
            
            // No columns found - should not happen
            return ['id', 'asc']; // Emergency fallback
            
        } catch (\Exception $e) {
            \Log::warning("⚠️ Default ordering detection failed", [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            return ['id', 'asc']; // Emergency fallback
        }
    }

    /**
     * Convert table name to model name
     * 
     * @param string $tableName Table name
     * @return string Model name
     */
    private function tableToModelName(string $tableName): string
    {
        $conventions = $this->autoDiscoveryConfig['table_to_model_conventions'] ?? [];
        
        $modelName = $tableName;
        
        // Apply plural to singular conversion
        if ($conventions['plural_to_singular'] ?? true) {
            $modelName = Str::singular($modelName);
        }
        
        // Apply case conversion
        if (($conventions['snake_case'] ?? 'PascalCase') === 'PascalCase') {
            $modelName = Str::studly($modelName);
        }
        
        return $modelName;
    }

    /**
     * Infer default columns for a table
     * 
     * @param string $tableName Table name
     * @param \Illuminate\Database\Eloquent\Model $model Model instance
     * @return array Default columns
     */
    private function inferDefaultColumns(string $tableName, $model): array
    {
        try {
            $connection = $model->getConnectionName();
            $allColumns = \Schema::connection($connection)->getColumnListing($tableName);
            
            // Exclude system/sensitive columns
            $excludeColumns = config('data-providers.auto_discovery.exclude_columns', [
                'password', 'remember_token', 'created_at', 'updated_at'
            ]);
            
            $columns = array_diff($allColumns, $excludeColumns);
            
            // Limit columns for performance
            $limit = config('data-providers.auto_discovery.default_columns_limit', 20);
            $columns = array_slice($columns, 0, $limit);
            
            \Log::info("✅ Auto-inferred default columns", [
                'table' => $tableName,
                'total_columns' => count($allColumns),
                'selected_columns' => count($columns),
                'columns' => $columns
            ]);
            
            return array_values($columns);
            
        } catch (\Exception $e) {
            \Log::warning("⚠️ Column inference failed, using fallback", [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to basic defaults
            return ['id', 'name'];
        }
    }

    /**
     * Infer searchable columns for a table
     * 
     * @param string $tableName Table name
     * @param \Illuminate\Database\Eloquent\Model $model Model instance
     * @return array Searchable columns
     */
    private function inferSearchableColumns(string $tableName, $model): array
    {
        try {
            $connection = $model->getConnectionName();
            $allColumns = \Schema::connection($connection)->getColumnListing($tableName);
            
            // Common searchable column patterns
            $searchablePatterns = [
                'name', 'title', 'description', 'email', 'username', 'fullname', 
                'region', 'cluster', 'outlet_name', 'partner_name', 'category',
                'type', 'status', 'code'
            ];
            
            $searchableColumns = [];
            foreach ($allColumns as $column) {
                foreach ($searchablePatterns as $pattern) {
                    if (str_contains(strtolower($column), strtolower($pattern))) {
                        $searchableColumns[] = $column;
                        break;
                    }
                }
            }
            
            // If no pattern matches, use string/text columns
            if (empty($searchableColumns)) {
                // Try to detect text columns via database schema (simplified approach)
                $textColumns = array_filter($allColumns, function($column) {
                    return !in_array(strtolower($column), ['id', 'created_at', 'updated_at', 'deleted_at']);
                });
                $searchableColumns = array_slice($textColumns, 0, 5); // Limit to first 5
            }
            
            return array_values($searchableColumns);
            
        } catch (\Exception $e) {
            \Log::warning("⚠️ Searchable column inference failed", [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            return ['name'];
        }
    }

    /**
     * Infer sortable columns for a table
     * 
     * @param string $tableName Table name
     * @param \Illuminate\Database\Eloquent\Model $model Model instance
     * @return array Sortable columns
     */
    private function inferSortableColumns(string $tableName, $model): array
    {
        try {
            $connection = $model->getConnectionName();
            $allColumns = \Schema::connection($connection)->getColumnListing($tableName);
            
            // Sortable column priorities
            $sortablePriorities = [
                'id', 'uuid', 'period', 'period_string', 'date', 'created_at', 'updated_at',
                'name', 'title', 'status', 'type', 'total', 'amount', 'count', 'performance_metric'
            ];
            
            $sortableColumns = [];
            
            // Add columns based on priority
            foreach ($sortablePriorities as $priority) {
                if (in_array($priority, $allColumns)) {
                    $sortableColumns[] = $priority;
                }
            }
            
            // Add numeric-looking columns (simplified heuristic)
            foreach ($allColumns as $column) {
                if (!in_array($column, $sortableColumns)) {
                    if (str_contains(strtolower($column), 'total') || 
                        str_contains(strtolower($column), 'count') ||
                        str_contains(strtolower($column), 'amount') ||
                        str_contains(strtolower($column), 'metric')) {
                        $sortableColumns[] = $column;
                    }
                }
            }
            
            // Ensure we don't return too many sortable columns
            return array_slice($sortableColumns, 0, 8);
            
        } catch (\Exception $e) {
            \Log::warning("⚠️ Sortable column inference failed", [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            return ['id'];
        }
    }

    /**
     * Normalize model configuration
     * 
     * @param array $config Raw configuration
     * @param string $tableName Table name
     * @return array Normalized configuration
     */
    private function normalizeConfig(array $config, string $tableName): array
    {
        $defaults = config('data-providers.defaults', []);
        
        return array_merge($defaults, [
            'table_name' => $tableName,
            'configured' => true,
            'resolved_at' => now()->toISOString()
        ], $config);
    }

    /**
     * Cache auto-discovery result
     * 
     * @param string $tableName Table name
     * @param array $config Configuration to cache
     * @return void
     */
    private function cacheDiscovery(string $tableName, array $config): void
    {
        $cacheKey = self::CACHE_PREFIX . 'discovery_' . $tableName;
        $cacheDuration = $this->autoDiscoveryConfig['cache_duration'] ?? 3600;
        
        Cache::put($cacheKey, $config, $cacheDuration);
        
        \Log::info("💾 Cached auto-discovery result", [
            'table' => $tableName,
            'cache_key' => $cacheKey,
            'cache_duration' => $cacheDuration
        ]);
    }

    /**
     * Clear all caches
     * 
     * @return void
     */
    public function clearCache(): void
    {
        $this->resolvedModels = [];
        
        // Clear Laravel cache entries
        foreach ($this->modelConfigs as $tableName => $config) {
            $cacheKey = self::CACHE_PREFIX . 'discovery_' . $tableName;
            Cache::forget($cacheKey);
        }
        
        \Log::info("🧹 ModelRegistry cache cleared");
    }

    /**
     * Get registry statistics
     * 
     * @return array Statistics about the registry
     */
    public function getStats(): array
    {
        return [
            'configured_models' => count($this->modelConfigs),
            'resolved_models' => count($this->resolvedModels),
            'auto_discovery_enabled' => $this->autoDiscoveryConfig['enabled'] ?? false,
            'cache_enabled' => $this->autoDiscoveryConfig['cache_discoveries'] ?? false,
            'available_namespaces' => $this->autoDiscoveryConfig['model_namespaces'] ?? [],
            'supported_types' => array_keys(config('data-providers.data_source_types', []))
        ];
    }
}