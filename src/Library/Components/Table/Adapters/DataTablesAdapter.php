<?php

namespace Incodiy\Codiy\Library\Components\Table\Adapters;

use Incodiy\Codiy\Library\Components\Table\Contracts\DataProviderInterface;
use Incodiy\Codiy\Library\Components\Table\Contracts\DataResponse;

/**
 * DataTablesAdapter
 * 
 * Adapter that transforms generic DataProvider output into jQuery DataTables
 * specific format. This class handles all DataTables-specific presentation
 * logic while keeping the data provider clean and framework-agnostic.
 * 
 * This design enables easy swapping to different frontend technologies
 * (React, Vue, Tailwind tables, etc.) by creating new adapters.
 */
class DataTablesAdapter
{
    /**
     * Data provider instance
     * 
     * @var DataProviderInterface
     */
    private DataProviderInterface $dataProvider;

    /**
     * DataTables configuration
     * 
     * @var array
     */
    private array $config;

    /**
     * Action configuration
     * 
     * @var array
     */
    private array $actionConfig;

    /**
     * Create new DataTablesAdapter instance
     * 
     * @param DataProviderInterface $dataProvider Data provider instance
     * @param array $config DataTables configuration
     * @param array $actionConfig Action column configuration
     */
    public function __construct(
        DataProviderInterface $dataProvider,
        array $config = [],
        array $actionConfig = []
    ) {
        $this->dataProvider = $dataProvider;
        $this->config = $config;
        $this->actionConfig = $actionConfig;
    }

    /**
     * Render DataTables response
     * 
     * @param array $requestConfig Request configuration
     * @return array DataTables-formatted response
     */
    public function render(array $requestConfig = []): array
    {
        \Log::info("🎯 Enhanced DataTablesAdapter::render() called", [
            'table_name' => $requestConfig['difta']['name'] ?? 'unknown',
            'has_columns' => isset($requestConfig['columns']),
            'columns_count' => is_array($requestConfig['columns'] ?? null) ? count($requestConfig['columns']) : 0,
            'first_column' => $requestConfig['columns'][0]['data'] ?? 'not_found',
            'request_keys' => array_keys($requestConfig),
            'columns_raw' => isset($requestConfig['columns']) ? array_slice($requestConfig['columns'], 0, 3) : 'not_set'
        ]);
        
        try {
            // Extract request parameters
            $filters = $this->extractFilters($requestConfig);
            $sorting = $this->extractSorting($requestConfig);
            $pagination = $this->extractPagination($requestConfig);
            
            // Apply parameters to data provider
            $this->dataProvider->applyFilters($filters);
            
            // CRITICAL: Handle sorting with proper model configuration support
            $this->applySortingFromConfig($sorting);
            
            $this->dataProvider->applyPagination($pagination['start'], $pagination['length']);

            // Get data response
            $dataResponse = $this->dataProvider->getData($requestConfig);
            
            // Transform to DataTables format
            $formatted = $this->formatForDataTables($dataResponse, $requestConfig);
            
            // Add action column if configured
            if ($this->shouldAddActionColumn()) {
                $formatted = $this->addActionColumn($formatted, $dataResponse);
            }

            \Log::info("🎯 DataTables response generated", [
                'total_records' => $dataResponse->total,
                'filtered_records' => $dataResponse->filtered,
                'returned_records' => count($formatted['data']),
                'has_action_column' => $this->shouldAddActionColumn()
            ]);

            return $formatted;

        } catch (\Exception $e) {
            // Check if this is a relational filter exception that should trigger fallback
            if (strpos($e->getMessage(), "relational filters") !== false || 
                strpos($e->getMessage(), "Fallback to Legacy required") !== false) {
                \Log::info("🔄 Re-throwing relational filter exception for fallback handling");
                throw $e; // Re-throw to allow main fallback handler to catch it
            }
            
            \Log::error("❌ Error in DataTablesAdapter", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->createErrorResponse($e, $requestConfig);
        }
    }

    /**
     * Extract filters from request configuration
     * 
     * @param array $requestConfig Request configuration
     * @return array Extracted filters
     */
    private function extractFilters(array $requestConfig): array
    {
        $filters = [];
        
        // Extract from various request formats
        if (isset($requestConfig['filters']) && is_array($requestConfig['filters'])) {
            $filters = $this->sanitizeFilterArray($requestConfig['filters']);
        }

        // Extract from form data
        foreach ($requestConfig as $key => $value) {
            if ($this->isValidFilterParameter($key, $value)) {
                $filters[$key] = $this->sanitizeFilterValue($value);
            }
        }

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '' && (!is_array($value) || !empty($value));
        });

        \Log::info("🔍 Filters extracted and sanitized", [
            'original_count' => isset($requestConfig['filters']) ? count($requestConfig['filters'] ?? []) : 0,
            'final_count' => count($filters),
            'filter_keys' => array_keys($filters)
        ]);

        return $filters;
    }

    /**
     * CRITICAL FIX: Sanitize filter array to prevent nested array issues
     * 
     * @param array $filters Raw filter array
     * @return array Sanitized filter array
     */
    private function sanitizeFilterArray(array $filters): array
    {
        $sanitized = [];
        
        foreach ($filters as $key => $value) {
            $cleanValue = $this->sanitizeFilterValue($value);
            if ($cleanValue !== null && $cleanValue !== '') {
                $sanitized[$key] = $cleanValue;
            }
        }
        
        return $sanitized;
    }

    /**
     * CRITICAL FIX: Sanitize individual filter value
     * 
     * @param mixed $value Filter value to sanitize
     * @return mixed Sanitized value
     */
    private function sanitizeFilterValue($value)
    {
        // Handle arrays - flatten if nested
        if (is_array($value)) {
            $flattened = $this->flattenArray($value);
            return !empty($flattened) ? $flattened : null;
        }
        
        // Handle objects
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return $this->sanitizeString((string) $value);
            }
            return null;
        }
        
        // Handle scalars with enhanced security
        return $this->sanitizeString((string) $value);
    }

    /**
     * OPTIMIZED: Flatten nested arrays safely and efficiently
     * 
     * @param array $array Potentially nested array
     * @return array Flat array
     */
    private function flattenArray(array $array): array
    {
        $flattened = [];
        $stack = [$array];
        
        // PERFORMANCE: Use iterative approach
        while (!empty($stack)) {
            $current = array_shift($stack);
            
            if (is_array($current)) {
                foreach ($current as $item) {
                    if (is_array($item)) {
                        $stack[] = $item;
                    } else if (!is_object($item)) {
                        $sanitized = $this->sanitizeString((string) $item);
                        if ($sanitized !== null && $sanitized !== '') {
                            $flattened[] = $sanitized;
                        }
                    }
                }
            }
        }
        
        return array_values(array_unique($flattened));
    }

    /**
     * CRITICAL SECURITY: Sanitize string for database queries
     * (Matches DataProvider implementation for consistency)
     * 
     * @param string $input Raw string input
     * @return string|null Sanitized string or null if invalid
     */
    private function sanitizeString(string $input): ?string
    {
        $sanitized = trim($input);
        
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
        $sanitized = preg_replace('/[^\w\s,\-@.+_\/]/', '', $sanitized);
        $sanitized = trim($sanitized);
        
        return $sanitized === '' ? null : $sanitized;
    }

    /**
     * Extract sorting from request configuration
     * 
     * @param array $requestConfig Request configuration
     * @return array Extracted sorting
     */
    private function extractSorting(array $requestConfig): array
    {
        $sorting = [];

        // DataTables format: order[0][column] and order[0][dir]
        if (isset($requestConfig['order']) && is_array($requestConfig['order'])) {
            $order = $requestConfig['order'][0] ?? [];
            if (isset($order['column'], $order['dir'])) {
                $columnIndex = (int) $order['column'];
                $columns = $requestConfig['columns'] ?? [];
                
                if (isset($columns[$columnIndex]['data'])) {
                    $sorting = [
                        'column' => $columns[$columnIndex]['data'],
                        'direction' => $order['dir']
                    ];
                }
            }
        }

        return $sorting;
    }

    /**
     * Extract pagination from request configuration
     * 
     * @param array $requestConfig Request configuration
     * @return array Extracted pagination
     */
    private function extractPagination(array $requestConfig): array
    {
        return [
            'start' => (int) ($requestConfig['start'] ?? 0),
            'length' => (int) ($requestConfig['length'] ?? 10)
        ];
    }

    /**
     * Apply sorting with proper model configuration support
     * 
     * @param array $sorting Extracted sorting from request
     * @return void
     */
    private function applySortingFromConfig(array $sorting): void
    {
        // Get model configuration
        $modelInfo = $this->dataProvider->getMetadata();
        $primaryKey = $modelInfo['primary_key'] ?? 'id';
        
        // If DataTables sent sorting, use it
        if (!empty($sorting['column']) && !empty($sorting['direction'])) {
            $this->dataProvider->applySorting($sorting['column'], $sorting['direction']);
            \Log::info("📊 Using DataTables sorting", [
                'column' => $sorting['column'],
                'direction' => $sorting['direction']
            ]);
            return;
        }
        
        // Check for model-specific default ordering
        if (isset($modelInfo['default_order']) && is_array($modelInfo['default_order'])) {
            $defaultColumn = $modelInfo['default_order'][0] ?? null;
            $defaultDirection = $modelInfo['default_order'][1] ?? 'asc';
            
            if ($defaultColumn) {
                $this->dataProvider->applySorting($defaultColumn, $defaultDirection);
                \Log::info("📊 Using model default ordering", [
                    'column' => $defaultColumn,
                    'direction' => $defaultDirection
                ]);
                return;
            }
        }
        
        // Fall back to primary key if it exists and is not null
        if ($primaryKey && $primaryKey !== 'null' && $primaryKey !== null) {
            $this->dataProvider->applySorting($primaryKey, 'asc');
            \Log::info("📊 Using primary key ordering", [
                'column' => $primaryKey,
                'direction' => 'asc'
            ]);
            return;
        }
        
        // No ordering for tables without primary key
        \Log::info("📊 No ordering applied - table has no primary key and no default ordering");
    }

    /**
     * Format data response for DataTables
     * 
     * @param DataResponse $dataResponse Data response
     * @param array $requestConfig Request configuration
     * @return array DataTables-formatted response
     */
    private function formatForDataTables(DataResponse $dataResponse, array $requestConfig): array
    {
        // Process data records and add DT_RowIndex if needed
        $processedData = $this->processDataRecords($dataResponse->data);

        // Jika konfigurasi mengaktifkan numbering (index_lists) tetapi kolom DT_RowIndex
        // tidak dikirim dari client, kita tambahkan otomatis agar tidak terjadi warning.
        if (!($requestConfig['columns'] ?? null)) {
            // Pastikan struktur columns ada agar deteksi DT_RowIndex bisa berjalan
            $requestConfig['columns'] = [];
        }

        $forceIndex = (bool) ($this->config['index_lists'] ?? false);
        if ($forceIndex) {
            $hasIndexInColumns = false;
            foreach ($requestConfig['columns'] as $col) {
                if (isset($col['data']) && $col['data'] === 'DT_RowIndex') {
                    $hasIndexInColumns = true;
                    break;
                }
            }
            if (!$hasIndexInColumns) {
                // Sisipkan definisi kolom DT_RowIndex di depan untuk sinkron dengan UI yang biasanya menaruh nomor di kolom pertama
                array_unshift($requestConfig['columns'], ['data' => 'DT_RowIndex']);
            }
        }

        $dataWithIndex = $this->addDTRowIndexIfNeeded($processedData, $requestConfig);
        
        return [
            'draw' => (int) ($requestConfig['draw'] ?? 1),
            'recordsTotal' => $dataResponse->total,
            'recordsFiltered' => $dataResponse->filtered,
            'data' => $dataWithIndex
        ];
    }
    
    /**
     * Add DT_RowIndex column if needed by DataTables configuration
     * 
     * @param array $data Processed data records
     * @param array $requestConfig Request configuration
     * @return array Data with DT_RowIndex if needed
     */
    private function addDTRowIndexIfNeeded(array $data, array $requestConfig): array
    {
        // ENHANCED DEBUGGING: Log request config for DT_RowIndex detection
        \Log::info("🔢 DT_RowIndex Detection Debug", [
            'has_columns' => isset($requestConfig['columns']),
            'columns_count' => is_array($requestConfig['columns'] ?? null) ? count($requestConfig['columns']) : 0,
            'first_column_data' => $requestConfig['columns'][0]['data'] ?? 'not_found',
            'data_count' => count($data)
        ]);
        
        // Check if DT_RowIndex column is expected
        $hasDTRowIndex = false;
        if (isset($requestConfig['columns']) && is_array($requestConfig['columns'])) {
            foreach ($requestConfig['columns'] as $index => $column) {
                if (isset($column['data']) && $column['data'] === 'DT_RowIndex') {
                    $hasDTRowIndex = true;
                    \Log::info("✅ DT_RowIndex column found at index: " . $index);
                    break;
                }
            }
        }
        
        if (!$hasDTRowIndex) {
            \Log::info("❌ DT_RowIndex not required, returning data as-is");
            return $data; // No DT_RowIndex needed
        }
        
        // Calculate starting row number for pagination
        $start = $requestConfig['start'] ?? 0;
        
        \Log::info("🔢 Adding DT_RowIndex column to data", [
            'rows_count' => count($data),
            'start_index' => $start,
            'row_numbering_starts_at' => $start + 1
        ]);
        
        // Add DT_RowIndex to each row
        $dataWithIndex = [];
        foreach ($data as $index => $row) {
            $row = (array) $row; // Ensure it's an array
            $row['DT_RowIndex'] = $start + $index + 1; // 1-based row numbering
            $dataWithIndex[] = $row;
        }
        
        return $dataWithIndex;
    }

    /**
     * Process data records for DataTables
     * 
     * @param array $data Raw data records
     * @return array Processed data records
     */
    private function processDataRecords(array $data): array
    {
        $processed = [];

        foreach ($data as $record) {
            $processedRecord = $this->processRecord($record);
            $processed[] = $processedRecord;
        }

        return $processed;
    }

    /**
     * Process single record
     * 
     * @param array|object $record Single data record
     * @return array Processed record
     */
    private function processRecord($record): array
    {
        // Convert object to array if needed
        if (is_object($record)) {
            $record = (array) $record;
        }

        // Apply any record-level processing here
        // (image processing, date formatting, etc.)
        return $this->applyRecordTransformations($record);
    }

    /**
     * Apply transformations to a record
     * 
     * @param array $record Record data
     * @return array Transformed record
     */
    private function applyRecordTransformations(array $record): array
    {
        // Image processing
        $record = $this->processImageFields($record);
        
        // Date formatting
        $record = $this->processDateFields($record);
        
        // Status formatting
        $record = $this->processStatusFields($record);

        return $record;
    }

    /**
     * Process image fields in record
     * 
     * @param array $record Record data
     * @return array Record with processed images
     */
    private function processImageFields(array $record): array
    {
        $imageExtensions = config('datatables.image_extensions', ['jpg', 'jpeg', 'png', 'gif']);
        
        foreach ($record as $field => $value) {
            if ($this->isImageField($field, $value, $imageExtensions)) {
                $record[$field] = $this->generateImageHtml($value, $field);
            }
        }

        return $record;
    }

    /**
     * Process date fields in record
     * 
     * @param array $record Record data
     * @return array Record with formatted dates
     */
    private function processDateFields(array $record): array
    {
        $dateFields = ['created_at', 'updated_at', 'deleted_at'];
        
        foreach ($dateFields as $field) {
            if (isset($record[$field]) && $record[$field]) {
                $record[$field] = $this->formatDate($record[$field]);
            }
        }

        return $record;
    }

    /**
     * Process status fields in record
     * 
     * @param array $record Record data
     * @return array Record with formatted status
     */
    private function processStatusFields(array $record): array
    {
        if (isset($record['active'])) {
            $record['active'] = $this->formatStatus($record['active']);
        }

        return $record;
    }

    /**
     * CRITICAL FIX: Check if parameter is a valid filter
     * 
     * @param string $name Parameter name
     * @param mixed $value Parameter value
     * @return bool True if valid filter
     */
    private function isValidFilterParameter(string $name, $value): bool
    {
        // HARD-CODED EXCLUSION: DataTables control parameters that should NEVER be database filters
        $datatables_control_params = [
            'draw', 'columns', 'order', 'start', 'length', 'search',
            'renderDataTables', 'difta', '_token', '_', 'method',
            'data', 'action', 'submit', 'submit_button'
        ];
        
        // Config-based exclusion as secondary check
        $config_reserved = config('datatables.reserved_parameters', []);
        $all_reserved = array_merge($datatables_control_params, $config_reserved);
        
        // Strict exclusion logic
        $is_reserved = in_array($name, $all_reserved, true);
        $is_empty = empty($value) && $value !== '0' && $value !== 0;
        $is_special = $name === 'filters' || strpos($name, 'csrf') !== false;
        
        \Log::debug("🔍 Filter parameter validation", [
            'parameter' => $name,
            'value_type' => gettype($value),
            'value_sample' => is_string($value) ? substr((string)$value, 0, 50) : $value,
            'is_reserved' => $is_reserved,
            'is_empty' => $is_empty,
            'is_special' => $is_special,
            'final_result' => !$is_reserved && !$is_empty && !$is_special
        ]);
        
        return !$is_reserved && !$is_empty && !$is_special;
    }

    /**
     * Check if should add action column
     * 
     * @return bool True if action column should be added
     */
    private function shouldAddActionColumn(): bool
    {
        return !empty($this->actionConfig) && ($this->actionConfig['enabled'] ?? true);
    }

    /**
     * Add action column to formatted response
     * 
     * @param array $formatted Formatted response
     * @param DataResponse $dataResponse Original data response
     * @return array Response with action column
     */
    private function addActionColumn(array $formatted, DataResponse $dataResponse): array
    {
        $actionList = $this->actionConfig['actions'] ?? config('datatables.default_actions', []);
        
        foreach ($formatted['data'] as &$record) {
            $record['action'] = $this->generateActionButtons($record, $actionList);
        }

        return $formatted;
    }

    /**
     * Generate action buttons for a record
     * 
     * @param array $record Record data
     * @param array $actionList List of actions
     * @return string Action buttons HTML
     */
    private function generateActionButtons(array $record, array $actionList): string
    {
        $buttons = [];
        $recordId = $record['id'] ?? 'unknown';

        foreach ($actionList as $action) {
            $buttons[] = $this->generateActionButton($action, $recordId, $record);
        }

        return implode(' ', $buttons);
    }

    /**
     * Generate single action button
     * 
     * @param string $action Action type
     * @param mixed $recordId Record ID
     * @param array $record Full record data
     * @return string Button HTML
     */
    private function generateActionButton(string $action, $recordId, array $record): string
    {
        $buttonClass = "btn btn-sm btn-{$this->getActionButtonClass($action)}";
        $buttonIcon = $this->getActionButtonIcon($action);
        $buttonTitle = ucfirst($action);

        return "<button class='{$buttonClass}' data-action='{$action}' data-id='{$recordId}' title='{$buttonTitle}'>
                    <i class='{$buttonIcon}'></i>
                </button>";
    }

    /**
     * Get CSS class for action button
     * 
     * @param string $action Action type
     * @return string CSS class
     */
    private function getActionButtonClass(string $action): string
    {
        $classes = [
            'view' => 'info',
            'edit' => 'primary',
            'delete' => 'danger',
            'insert' => 'success'
        ];

        return $classes[$action] ?? 'secondary';
    }

    /**
     * Get icon for action button
     * 
     * @param string $action Action type
     * @return string Icon class
     */
    private function getActionButtonIcon(string $action): string
    {
        $icons = [
            'view' => 'fas fa-eye',
            'edit' => 'fas fa-edit',
            'delete' => 'fas fa-trash',
            'insert' => 'fas fa-plus'
        ];

        return $icons[$action] ?? 'fas fa-cog';
    }

    /**
     * Check if field contains image data
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array $imageExtensions Valid image extensions
     * @return bool True if field contains image
     */
    private function isImageField(string $field, $value, array $imageExtensions): bool
    {
        if (empty($value) || !is_string($value)) {
            return false;
        }

        foreach ($imageExtensions as $extension) {
            if (strpos(strtolower($value), '.' . $extension) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate image HTML
     * 
     * @param string $imagePath Image path
     * @param string $field Field name
     * @return string Image HTML
     */
    private function generateImageHtml(string $imagePath, string $field): string
    {
        $imageUrl = asset($imagePath);
        return "<img src='{$imageUrl}' alt='{$field}' class='img-thumbnail' style='max-width: 100px; max-height: 100px;'>";
    }

    /**
     * Format date value
     * 
     * @param string $date Date string
     * @return string Formatted date
     */
    private function formatDate(string $date): string
    {
        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format status value
     * 
     * @param mixed $status Status value
     * @return string Formatted status
     */
    private function formatStatus($status): string
    {
        $isActive = (bool) $status;
        $class = $isActive ? 'success' : 'danger';
        $text = $isActive ? 'Active' : 'Inactive';
        
        return "<span class='badge badge-{$class}'>{$text}</span>";
    }

    /**
     * Create error response for DataTables
     * 
     * @param \Exception $exception Exception that occurred
     * @param array $requestConfig Request configuration
     * @return array Error response
     */
    private function createErrorResponse(\Exception $exception, array $requestConfig): array
    {
        return [
            'draw' => (int) ($requestConfig['draw'] ?? 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'DataTables processing error: ' . $exception->getMessage()
        ];
    }
}