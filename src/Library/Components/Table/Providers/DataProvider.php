<?php

namespace Incodiy\Codiy\Library\Components\Table\Providers;

use Incodiy\Codiy\Library\Components\Table\Contracts\DataProviderInterface;
use Incodiy\Codiy\Library\Components\Table\Contracts\DataResponse;
use Incodiy\Codiy\Library\Components\Table\Registry\ModelRegistry;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * DataProvider
 * 
 * Core data provider implementation that replaces model-specific logic
 * with a flexible, configurable approach. This class focuses purely on
 * data processing without presentation concerns, enabling clean separation
 * between backend and frontend logic.
 */
class DataProvider implements DataProviderInterface
{
    /**
     * Model registry for dynamic model resolution
     * 
     * @var ModelRegistry
     */
    private ModelRegistry $modelRegistry;

    /**
     * Current model configuration
     * 
     * @var array
     */
    private array $modelConfig;

    /**
     * Current data source (Eloquent, Query Builder, etc.)
     * 
     * @var mixed
     */
    private $dataSource;

    /**
     * Applied filters
     * 
     * @var array
     */
    private array $appliedFilters = [];

    /**
     * Applied sorting
     * 
     * @var array
     */
    private array $appliedSorting = [];

    /**
     * Applied pagination
     * 
     * @var array
     */
    private array $appliedPagination = [];

    /**
     * Total record count (before filtering)
     * 
     * @var int|null
     */
    private ?int $totalCount = null;

    /**
     * Filtered record count (after filtering)
     * 
     * @var int|null
     */
    private ?int $filteredCount = null;

    /**
     * Create new DataProvider instance
     * 
     * @param ModelRegistry|null $modelRegistry Model registry instance
     */
    public function __construct(ModelRegistry $modelRegistry = null)
    {
        $this->modelRegistry = $modelRegistry ?? new ModelRegistry();
    }

    /**
     * Initialize data provider with configuration
     * 
     * @param array $config Configuration array
     * @return self For method chaining
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function initialize(array $config): self
    {
        $this->validateConfig($config);
        
        // Extract table name from config
        $tableName = $config['table_name'] ?? $config['difta']['name'] ?? null;
        if (!$tableName) {
            throw new \InvalidArgumentException('Table name not found in configuration');
        }

        // Resolve model configuration
        $this->modelConfig = $this->modelRegistry->resolve($tableName);
        
        // Create data source based on model configuration
        $this->dataSource = $this->createDataSource($this->modelConfig, $tableName);
        
        \Log::info("🔧 DataProvider initialized", [
            'table' => $tableName,
            'type' => $this->modelConfig['type'],
            'class' => $this->modelConfig['class'] ?? 'N/A'
        ]);

        return $this;
    }

    /**
     * Get data based on configuration
     * 
     * @param array $config Configuration parameters
     * @return DataResponse Clean, generic data response
     */
    public function getData(array $config): DataResponse
    {
        if (!$this->dataSource) {
            throw new \RuntimeException('DataProvider not initialized. Call initialize() first.');
        }

        // Reset counts for fresh calculation
        $this->totalCount = null;
        $this->filteredCount = null;

        // Phase 1: Declarative Relations API wiring
        $declared = $config['declared_relations'] ?? [];
        $dotCols  = $config['dot_columns']        ?? [];
        $baseModel = null;

        // If eloquent, apply eager loading
        if ($this->dataSource instanceof EloquentBuilder) {
            // Determine base model from builder
            $baseModel = $this->dataSource->getModel();
            if (!empty($declared)) {
                try { $this->dataSource->with($declared); } catch (\Throwable $e) {}
            }
        }

        // Parse alias mapping for dot-columns: support either associative ['path' => 'alias'] or string entries
        $selects = [];
        if (!empty($dotCols) && ($this->dataSource instanceof EloquentBuilder || $this->dataSource instanceof QueryBuilder)) {
            foreach ($dotCols as $key => $value) {
                $path = null; $alias = null;
                if (is_string($key)) {
                    // Associative form: ['relation.path' => 'alias']
                    $path = $key;
                    $alias = is_string($value) && $value !== '' ? $value : str_replace('.', '_', $path);
                } else {
                    // Indexed form: string with optional "as"
                    $entry = $value;
                    $path  = $entry; $alias = null;
                    if (is_string($entry) && stripos($entry, ' as ') !== false) {
                        [$path, $alias] = preg_split('/\s+as\s+/i', $entry, 2);
                        $path = trim($path); $alias = trim($alias);
                    }
                    if (!$alias) { $alias = str_replace('.', '_', $path); }
                }

                // Resolve qualified column via relationship resolver when possible
                $qualified = null;
                if ($baseModel && method_exists($this, 'resolveRelationColumn')) {
                    try {
                        [$qualifiedCol, $joins] = $this->resolveRelationColumn($baseModel, $path);
                        $qualified = $qualifiedCol;
                        if (!empty($joins) && method_exists($this, 'applyRelationJoins')) {
                            $this->applyRelationJoins($this->dataSource, $joins);
                        }
                    } catch (\Throwable $e) {}
                }
                if (!$qualified) {
                    $qualified = is_string($path) && strpos($path, '.') === false ? $path : $alias;
                }

                try {
                    if (method_exists($this->dataSource, 'addSelect')) {
                        $this->dataSource->addSelect([$qualified . ' as ' . $alias]);
                    } else if (method_exists($this->dataSource, 'selectRaw')) {
                        $this->dataSource->selectRaw($qualified . ' as ' . $alias);
                    }
                } catch (\Throwable $e) {
                    try { $this->dataSource->selectRaw($qualified . ' as ' . $alias); } catch (\Throwable $e2) {}
                }
                $selects[$alias] = $qualified;
            }
        }

        // Phase 2: Apply custom_relationships from config
        $customRels = $this->modelConfig['custom_relationships'] ?? [];
        if (!empty($customRels) && isset($customRels['columns']) && ($this->dataSource instanceof EloquentBuilder || $this->dataSource instanceof QueryBuilder)) {
            $this->applyCustomRelationships($customRels['columns']);
        }

        // Get the actual data
        $data = $this->fetchData();
        
        // Prepare column metadata
        $columns = $this->prepareColumnMetadata();

        return new DataResponse(
            data: $data,
            total: $this->getTotalCount(),
            filtered: $this->getFilteredCount(),
            columns: $columns,
            pagination: $this->appliedPagination,
            filters: $this->appliedFilters,
            sorting: $this->appliedSorting,
            metadata: $this->prepareMetadata()
        );
    }

    /**
     * Get metadata about the data source
     * 
     * @return array Metadata including columns, types, relationships
     */
    public function getMetadata(): array
    {
        return [
            'table_name' => $this->modelConfig['table_name'] ?? 'unknown',
            'model_class' => $this->modelConfig['class'] ?? null,
            'type' => $this->modelConfig['type'] ?? 'unknown',
            'primary_key' => $this->modelConfig['primary_key'] ?? 'id',
            'default_columns' => $this->modelConfig['default_columns'] ?? [],
            'searchable_columns' => $this->modelConfig['searchable_columns'] ?? [],
            'sortable_columns' => $this->modelConfig['sortable_columns'] ?? [],
            'default_order' => $this->modelConfig['default_order'] ?? null, // ✅ Add default ordering
            'relationships' => $this->modelConfig['relationships'] ?? [],
            'supports_relationships' => $this->supportsRelationships(),
            'supports_scopes' => $this->supportsScopes(),
            'configured' => $this->modelConfig['configured'] ?? false,
            'auto_discovered' => $this->modelConfig['auto_discovered'] ?? false
        ];
    }

    /**
     * Get total count of records (before filtering)
     * 
     * @return int Total record count
     */
    public function getTotalCount(): int
    {
        if ($this->totalCount === null) {
            $this->totalCount = $this->calculateTotalCount();
        }
        
        return $this->totalCount;
    }

    /**
     * Get filtered count of records (after filtering)
     * 
     * @return int Filtered record count
     */
    public function getFilteredCount(): int
    {
        if ($this->filteredCount === null) {
            $this->filteredCount = $this->calculateFilteredCount();
        }
        
        return $this->filteredCount;
    }

    /**
     * Apply filters to the data source
     * 
     * @param array $filters Array of filter criteria
     * @return self For method chaining
     */
    public function applyFilters(array $filters): self
    {
        // Filter out control parameters before applying to database
        $validFilters = $this->filterValidParameters($filters);

        // Split into relational dot-notation filters and simple filters
        $relationalFilters = [];
        $simpleFilters = [];
        foreach ($validFilters as $column => $value) {
            if (is_string($column) && strpos($column, '.') !== false) {
                [$relation, $field] = explode('.', $column, 2);
                if ($relation && $field) {
                    $relationalFilters[$relation][$field] = $value;
                    continue;
                }
            }
            $simpleFilters[$column] = $value;
        }

        $this->appliedFilters = $validFilters;

        // Apply simple filters
        foreach ($simpleFilters as $column => $value) {
            $this->applyFilter($column, $value);
        }

        // Apply relational filters using whereHas on Eloquent relations
        if (!empty($relationalFilters) && $this->dataSource instanceof EloquentBuilder) {
            foreach ($relationalFilters as $relation => $criteria) {
                $this->dataSource->whereHas($relation, function ($q) use ($criteria) {
                    foreach ($criteria as $field => $val) {
                        if (is_array($val)) {
                            $flat = $this->flattenAndSanitizeArray($val);
                            if (!empty($flat)) { $q->whereIn($field, $flat); }
                        } else {
                            $san = $this->sanitizeFilterValue($val);
                            if ($san !== null && $san !== '') { $q->where($field, 'LIKE', "%{$san}%"); }
                        }
                    }
                });
            }
        }

        // Reset filtered count for recalculation
        $this->filteredCount = null;

        \Log::info("🔍 Filters applied", [
            'original_count' => count($filters),
            'valid_count' => count($validFilters),
            'relational_relations' => array_keys($relationalFilters),
            'simple_count' => count($simpleFilters),
            'valid_filters' => $validFilters
        ]);

        return $this;
    }
    
    /**
     * Check if filters contain relational columns that require JOINs
     * 
     * @param array $filters Filter array
     * @return bool True if relational filters found
     */
    private function hasRelationalFilters(array $filters): bool
    {
        // Common relational columns that require JOINs
        $relationalColumns = ['group_info', 'group_name', 'user_group_info'];
        
        foreach ($filters as $column => $value) {
            if (in_array($column, $relationalColumns)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get relational filters from filter array
     * 
     * @param array $filters Filter array
     * @return array Relational filters
     */
    private function getRelationalFilters(array $filters): array
    {
        $relationalColumns = ['group_info', 'group_name', 'user_group_info'];
        $relationalFilters = [];
        
        foreach ($filters as $column => $value) {
            if (in_array($column, $relationalColumns)) {
                $relationalFilters[$column] = $value;
            }
        }
        
        return $relationalFilters;
    }

    /**
     * CRITICAL FIX: Filter out DataTables control parameters
     * 
     * @param array $filters Raw filter array
     * @return array Valid filters only
     */
    private function filterValidParameters(array $filters): array
    {
        // DataTables control parameters that should NEVER be database filters
        $control_params = [
            'draw', 'columns', 'order', 'start', 'length', 'search',
            'renderDataTables', 'difta', '_token', '_', 'method',
            'data', 'action', 'submit', 'submit_button', 'filters',
            'filterDataTables',
            // CRITICAL FIX: Configuration metadata should not be database filters
            'declared_relations', 'dot_columns'
        ];
        
        $validFilters = [];
        
        foreach ($filters as $name => $value) {
            // Exclude control parameters
            if (in_array($name, $control_params, true)) {
                \Log::debug("🚫 Excluded control parameter from database filter", [
                    'parameter' => $name,
                    'value_type' => gettype($value)
                ]);
                continue;
            }
            
            // Exclude empty values (but allow '0')
            if (empty($value) && $value !== '0' && $value !== 0) {
                \Log::debug("🚫 Excluded empty filter value", [
                    'parameter' => $name,
                    'value' => $value
                ]);
                continue;
            }
            
            // Exclude CSRF and token parameters
            if (strpos($name, 'csrf') !== false || strpos($name, 'token') !== false) {
                \Log::debug("🚫 Excluded security parameter from database filter", [
                    'parameter' => $name
                ]);
                continue;
            }
            
            // Include valid filter
            $validFilters[$name] = $value;
            \Log::debug("✅ Included valid filter", [
                'parameter' => $name,
                'value_type' => gettype($value)
            ]);
        }
        
        return $validFilters;
    }

    /**
     * Apply sorting to the data source
     * 
     * @param string $column Column to sort by
     * @param string $direction Sort direction (asc/desc)
     * @return self For method chaining
     */
    public function applySorting(string $column, string $direction = 'asc'): self
    {
        $this->appliedSorting = ['column' => $column, 'direction' => $direction];
        
        if ($this->dataSource instanceof EloquentBuilder || $this->dataSource instanceof QueryBuilder) {
            $this->dataSource->orderBy($column, $direction);
        }

        \Log::info("📊 Sorting applied", [
            'column' => $column,
            'direction' => $direction
        ]);

        return $this;
    }

    /**
     * Apply pagination to the data source
     * 
     * @param int $start Starting record
     * @param int $length Number of records to fetch
     * @return self For method chaining
     */
    public function applyPagination(int $start, int $length): self
    {
        $this->appliedPagination = [
            'start' => $start,
            'length' => $length,
            'page' => floor($start / $length) + 1
        ];

        if ($this->dataSource instanceof EloquentBuilder || $this->dataSource instanceof QueryBuilder) {
            $this->dataSource->skip($start)->take($length);
        }

        \Log::info("📄 Pagination applied", [
            'start' => $start,
            'length' => $length,
            'page' => $this->appliedPagination['page']
        ]);

        return $this;
    }

    /**
     * Validate configuration
     * 
     * @param array $config Configuration to validate
     * @return bool True if valid
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function validateConfig(array $config): bool
    {
        // Basic validation
        if (empty($config)) {
            throw new \InvalidArgumentException('Configuration cannot be empty');
        }

        // Check for required data source indicators
        $hasTableName = isset($config['table_name']) || isset($config['difta']['name']);
        
        if (!$hasTableName) {
            throw new \InvalidArgumentException('Configuration must contain table_name or difta.name');
        }

        return true;
    }

    /**
     * Create data source based on model configuration
     * 
     * @param array $modelConfig Model configuration
     * @param string $tableName Target table name
     * @return mixed Data source instance
     */
    private function createDataSource(array $modelConfig, string $tableName = null)
    {
        $type = $modelConfig['type'] ?? 'eloquent';
        
        switch ($type) {
            case 'eloquent':
                return $this->createEloquentSource($modelConfig, $tableName);
                
            case 'query_builder':
                return $this->createQueryBuilderSource($modelConfig);
                
            case 'raw_sql':
                return $this->createRawSqlSource($modelConfig);
                
            case 'string_table':
                return $this->createStringTableSource($modelConfig);
                
            default:
                throw new \InvalidArgumentException("Unsupported data source type: {$type}");
        }
    }

    /**
     * Create Eloquent data source
     * 
     * @param array $modelConfig Model configuration
     * @param string $tableName Target table name
     * @return EloquentBuilder
     */
    private function createEloquentSource(array $modelConfig, string $tableName = null): EloquentBuilder
    {
        // Check if we have a pre-configured adapted model instance
        if (isset($modelConfig['model_instance'])) {
            \Log::info("🔄 Using adapted model instance for multi-table scenario", [
                'table' => $tableName,
                'adapted_from' => $modelConfig['adapted_from'] ?? 'unknown',
                'connection' => $modelConfig['model_instance']->getConnectionName()
            ]);
            return $modelConfig['model_instance']->newQuery();
        }
        
        $modelClass = $modelConfig['class'];
        
        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class not found: {$modelClass}");
        }

        $model = new $modelClass();
        
        // CRITICAL: Set connection from configuration if specified
        if (isset($modelConfig['connection']) && method_exists($model, 'setConnection')) {
            $model->setConnection($modelConfig['connection']);
            \Log::info("🔄 Setting explicit connection from config", [
                'model_class' => $modelClass,
                'table' => $tableName,
                'connection' => $modelConfig['connection']
            ]);
        }
        
        // MULTI-TABLE SUPPORT: Set specific table name if provided and different from model default
        if ($tableName && method_exists($model, 'setTable') && $model->getTable() !== $tableName) {
            \Log::info("🔄 Dynamically setting table name for model", [
                'model_class' => $modelClass,
                'original_table' => $model->getTable(),
                'new_table' => $tableName,
                'connection' => $model->getConnectionName()
            ]);
            $model->setTable($tableName);
        }
        
        return $model->newQuery();
    }

    /**
     * Create Query Builder data source
     * 
     * @param array $modelConfig Model configuration
     * @return QueryBuilder
     */
    private function createQueryBuilderSource(array $modelConfig): QueryBuilder
    {
        $tableName = $modelConfig['table_name'];
        return DB::table($tableName);
    }

    /**
     * Create Raw SQL data source
     * 
     * @param array $modelConfig Model configuration
     * @return QueryBuilder
     */
    private function createRawSqlSource(array $modelConfig): QueryBuilder
    {
        $sql = $modelConfig['sql'] ?? null;
        if (!$sql) {
            throw new \InvalidArgumentException('SQL query is required for raw_sql type');
        }

        return DB::select($sql);
    }

    /**
     * Create String Table data source
     * 
     * @param array $modelConfig Model configuration
     * @return QueryBuilder
     */
    private function createStringTableSource(array $modelConfig): QueryBuilder
    {
        $tableName = $modelConfig['table_name'];
        return DB::table($tableName);
    }

    /**
     * Apply single filter to data source
     * 
     * @param string $column Column name
     * @param mixed $value Filter value
     * @return void
     */
    private function applyFilter(string $column, $value): void
    {
        if ($this->dataSource instanceof EloquentBuilder || $this->dataSource instanceof QueryBuilder) {
            if (is_array($value)) {
                // CRITICAL FIX: Flatten nested arrays and sanitize for whereIn
                $flatValue = $this->flattenAndSanitizeArray($value);
                
                if (!empty($flatValue)) {
                    $this->dataSource->whereIn($column, $flatValue);
                } else {
                    // If empty after flattening, treat as no filter
                    \Log::warning("🔍 Empty filter value after flattening", [
                        'column' => $column,
                        'original_value' => $value
                    ]);
                }
            } else {
                // Handle single value filters
                $sanitizedValue = $this->sanitizeFilterValue($value);
                if ($sanitizedValue !== null && $sanitizedValue !== '') {
                    $this->dataSource->where($column, 'LIKE', "%{$sanitizedValue}%");
                }
            }
        }
    }

    /**
     * Flatten nested arrays and sanitize values for database queries
     * 
     * CRITICAL: Prevents "Nested arrays may not be passed to whereIn method" error
     * OPTIMIZED: Fast iterative approach instead of recursive
     * 
     * @param array $value Potentially nested array
     * @return array Flat, sanitized array safe for whereIn
     */
    private function flattenAndSanitizeArray(array $value): array
    {
        $flattened = [];
        $stack = [$value];
        
        // PERFORMANCE: Use iterative approach instead of recursive
        while (!empty($stack)) {
            $current = array_shift($stack);
            
            if (is_array($current)) {
                foreach ($current as $item) {
                    if (is_array($item)) {
                        $stack[] = $item;  // Add to stack for processing
                    } else {
                        // CRITICAL FIX: Handle all data types safely
                        $sanitized = $this->processItemForFlattening($item);
                        if ($sanitized !== null && $sanitized !== '') {
                            $flattened[] = $sanitized;
                        }
                    }
                }
            }
        }
        
        // Remove duplicates efficiently
        $result = array_values(array_unique($flattened));
        
        // Only log if debug is enabled to improve performance
        if (config('app.debug', false)) {
            \Log::info("🔧 Array flattened for whereIn", [
                'original_count' => $this->countArrayElements($value),
                'flattened_count' => count($result),
                'sample_values' => array_slice($result, 0, 3)
            ]);
        }
        
        return $result;
    }

    /**
     * CRITICAL FIX: Process individual item for flattening safely
     * 
     * @param mixed $item Item to process
     * @return string|null Processed string or null
     */
    private function processItemForFlattening($item): ?string
    {
        // Handle null/empty
        if ($item === null || $item === '') {
            return null;
        }
        
        // Handle objects
        if (is_object($item)) {
            if (method_exists($item, '__toString')) {
                return $this->sanitizeString((string) $item);
            }
            // Skip objects that can't be converted to string
            return null;
        }
        
        // Handle resources
        if (is_resource($item)) {
            return null;
        }
        
        // Handle booleans
        if (is_bool($item)) {
            return $item ? '1' : '0';
        }
        
        // Handle scalars (string, int, float)
        return $this->sanitizeString((string) $item);
    }

    /**
     * Sanitize individual filter value
     * 
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized value or null if invalid
     */
    private function sanitizeFilterValue($value)
    {
        // Skip arrays (handled separately)
        if (is_array($value)) {
            return null;
        }
        
        // Handle objects
        if (is_object($value)) {
            // Try to convert to string
            if (method_exists($value, '__toString')) {
                return $this->sanitizeString((string) $value);
            }
            return null;
        }
        
        // Convert to string and sanitize
        return $this->sanitizeString((string) $value);
    }

    /**
     * CRITICAL SECURITY: Sanitize string for database queries
     * 
     * @param string $input Raw string input
     * @return string|null Sanitized string or null if invalid
     */
    private function sanitizeString(string $input): ?string
    {
        // Trim whitespace
        $sanitized = trim($input);
        
        // Return null for empty strings
        if ($sanitized === '') {
            return null;
        }
        
        // SECURITY: Remove SQL injection patterns (enhanced)
        $dangerous_patterns = [
            '/;\s*--/',             // ; --
            '/;\s*\/\*/',           // ; /*
            '/--\s*/',              // -- comments
            '/\/\*.*?\*\//i',       // /* comments */
            '/\'\s*OR\s*\'/i',      // ' OR '
            '/\'\s*AND\s*\'/i',     // ' AND '
            '/UNION\s+/i',          // UNION (any context)
            '/SELECT\s+/i',         // SELECT statements
            '/DROP\s+/i',           // DROP anything
            '/DELETE\s+/i',         // DELETE anything
            '/INSERT\s+/i',         // INSERT anything
            '/UPDATE\s+/i',         // UPDATE anything
            '/EXEC\s*\(/i',         // EXEC(
            '/EXECUTE\s+/i',        // EXECUTE
            '/SCRIPT\s*>/i',        // SCRIPT>
            '/javascript:/i',       // javascript:
            '/vbscript:/i',         // vbscript:
            '/<\s*script/i',        // <script
            '/\.\.\//i',            // ../
            '/xp_\w+/i',           // xp_ procedures
            '/sp_\w+/i',           // sp_ procedures
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            $sanitized = preg_replace($pattern, '', $sanitized);
        }
        
        // Remove dangerous characters but keep essential ones for search
        // Allow: letters, numbers, spaces, hyphens, dots, @, +, underscore, comma, slash
        $sanitized = preg_replace('/[^\w\s,\-@.+_\\/]/', '', $sanitized);
        
        // Additional trim after cleaning
        $sanitized = trim($sanitized);
        
        return $sanitized === '' ? null : $sanitized;
    }

    /**
     * OPTIMIZED: Count array elements efficiently 
     * 
     * @param array $array Array to count
     * @return int Total element count
     */
    private function countArrayElements(array $array): int
    {
        $count = 0;
        $stack = [$array];
        
        while (!empty($stack)) {
            $current = array_shift($stack);
            if (is_array($current)) {
                $count += count($current);
                foreach ($current as $item) {
                    if (is_array($item)) {
                        $stack[] = $item;
                    }
                }
            }
        }
        
        return $count;
    }

    /**
     * Fetch data from data source
     * 
     * @return array Data records
     */
    private function fetchData(): array
    {
        if ($this->dataSource instanceof EloquentBuilder || $this->dataSource instanceof QueryBuilder) {
            return $this->dataSource->get()->toArray();
        }

        return [];
    }

    /**
     * Calculate total count
     * 
     * @return int Total record count
     */
    private function calculateTotalCount(): int
    {
        if ($this->dataSource instanceof EloquentBuilder || $this->dataSource instanceof QueryBuilder) {
            // Clone query and remove pagination to get total count
            $query = clone $this->dataSource;
            return $query->count();
        }

        return 0;
    }

    /**
     * Calculate filtered count
     * 
     * @return int Filtered record count
     */
    private function calculateFilteredCount(): int
    {
        if ($this->dataSource instanceof EloquentBuilder || $this->dataSource instanceof QueryBuilder) {
            // Clone query, remove pagination but keep filters
            $query = clone $this->dataSource;
            $query->skip(0)->take(PHP_INT_MAX); // Remove pagination
            return $query->count();
        }

        return 0;
    }

    /**
     * Prepare column metadata
     * 
     * @return array Column definitions
     */
    private function prepareColumnMetadata(): array
    {
        $columns = [];
        $defaultColumns = $this->modelConfig['default_columns'] ?? ['id'];

        // Merge in dot column aliases if provided at request time (ensures frontend sees alias keys)
        $request = request();
        $dotCols = [];
        try {
            if ($request && is_array($request->all())) {
                $dotCols = $request->get('dot_columns', []);
                if (!is_array($dotCols)) { $dotCols = []; }
            }
        } catch (\Throwable $e) {}

        $aliases = [];
        foreach ($dotCols as $entry) {
            $path = $entry; $alias = null;
            if (is_string($entry) && stripos($entry, ' as ') !== false) {
                [$path, $alias] = preg_split('/\s+as\s+/i', $entry, 2);
                $path = trim($path); $alias = trim($alias);
            }
            if (!$alias) { $alias = is_string($path) ? str_replace('.', '_', $path) : null; }
            if ($alias) { $aliases[] = $alias; }
        }

        $finalColumns = array_values(array_unique(array_merge($defaultColumns, $aliases)));
        
        foreach ($finalColumns as $column) {
            $columns[] = [
                'name' => $column,
                'title' => ucfirst(str_replace('_', ' ', $column)),
                'searchable' => in_array($column, $this->modelConfig['searchable_columns'] ?? []),
                'sortable' => in_array($column, $this->modelConfig['sortable_columns'] ?? []),
                'type' => $this->inferColumnType($column)
            ];
        }

        return $columns;
    }

    /**
     * Prepare additional metadata
     * 
     * @return array Additional metadata
     */
    private function prepareMetadata(): array
    {
        return [
            'provider' => 'DataProvider',
            'version' => '2.0.0',
            'generated_at' => now()->toISOString(),
            'model_config' => $this->modelConfig,
            'supports_relationships' => $this->supportsRelationships(),
            'supports_scopes' => $this->supportsScopes()
        ];
    }

    /**
     * Check if data source supports relationships
     * 
     * @return bool
     */
    private function supportsRelationships(): bool
    {
        return $this->dataSource instanceof EloquentBuilder;
    }

    /**
     * Check if data source supports scopes
     * 
     * @return bool
     */
    private function supportsScopes(): bool
    {
        return $this->dataSource instanceof EloquentBuilder;
    }

    /**
     * Infer column type based on column name
     * 
     * @param string $column Column name
     * @return string Inferred type
     */
    private function inferColumnType(string $column): string
    {
        // Basic type inference based on common patterns
        if (in_array($column, ['id', 'user_id', 'group_id'])) {
            return 'integer';
        }
        
        if (str_ends_with($column, '_at')) {
            return 'datetime';
        }
        
        if (in_array($column, ['email'])) {
            return 'email';
        }
        
        if (in_array($column, ['active', 'is_active', 'enabled'])) {
            return 'boolean';
        }

        return 'string';
    }

    /**
     * Apply custom relationships from config
     * 
     * @param array $customColumns Custom relationship columns configuration
     * @return void
     */
    private function applyCustomRelationships(array $customColumns): void
    {
        $appliedJoins = []; // Track applied joins to avoid duplicates
        
        foreach ($customColumns as $alias => $config) {
            if (!isset($config['select']) || !isset($config['joins'])) {
                continue;
            }

            // Add select for the custom column
            $selectClause = $config['select'] . ' as ' . $alias;
            try {
                if (method_exists($this->dataSource, 'addSelect')) {
                    $this->dataSource->addSelect([$selectClause]);
                } else if (method_exists($this->dataSource, 'selectRaw')) {
                    $this->dataSource->selectRaw($selectClause);
                }
            } catch (\Throwable $e) {
                \Log::warning("Failed to add select for custom relationship", [
                    'alias' => $alias,
                    'select' => $config['select'],
                    'error' => $e->getMessage()
                ]);
                continue;
            }

            // Apply joins (avoid duplicates)
            foreach ($config['joins'] as $join) {
                if (!isset($join['type'], $join['table'], $join['first'], $join['operator'], $join['second'])) {
                    continue;
                }

                // Create unique join key to avoid duplicates
                $joinKey = $join['table'] . '|' . $join['first'] . '|' . $join['operator'] . '|' . $join['second'];
                if (isset($appliedJoins[$joinKey])) {
                    continue; // Skip duplicate join
                }

                try {
                    $joinType = strtolower($join['type']);
                    switch ($joinType) {
                        case 'left':
                            $this->dataSource->leftJoin($join['table'], $join['first'], $join['operator'], $join['second']);
                            break;
                        case 'right':
                            $this->dataSource->rightJoin($join['table'], $join['first'], $join['operator'], $join['second']);
                            break;
                        case 'inner':
                        case 'join':
                            $this->dataSource->join($join['table'], $join['first'], $join['operator'], $join['second']);
                            break;
                        default:
                            $this->dataSource->leftJoin($join['table'], $join['first'], $join['operator'], $join['second']);
                    }
                    
                    $appliedJoins[$joinKey] = true; // Mark as applied
                    
                } catch (\Throwable $e) {
                    \Log::warning("Failed to apply join for custom relationship", [
                        'alias' => $alias,
                        'join' => $join,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        \Log::info("✅ Custom relationships applied", [
            'columns_count' => count($customColumns),
            'columns' => array_keys($customColumns),
            'unique_joins_applied' => count($appliedJoins)
        ]);
    }
}