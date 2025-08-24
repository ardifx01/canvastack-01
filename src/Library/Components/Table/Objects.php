<?php

declare(strict_types=1);

namespace Incodiy\Codiy\Library\Components\Table;

use Incodiy\Codiy\Library\Components\Table\Craft\Builder;
use Incodiy\Codiy\Library\Components\Table\Craft\Scripts;
use Incodiy\Codiy\Library\Components\Form\Elements\Tab;
use Incodiy\Codiy\Library\Components\Charts\Objects as Chart;

/**
 * Table Objects Component
 * 
 * Handles table rendering, chart integration, and data manipulation for datatables
 * 
 * @author    wisnuwidi@incodiy.com - 2021
 * @copyright wisnuwidi
 * @email     wisnuwidi@incodiy.com
 */
class Objects extends Builder {
    use Tab, Scripts;
    
    // Constants
    private const OPEN_TAB_HTML = '--[openTabHTMLForm]--';
    private const DEFAULT_TABLE_CLASS = 'table animated fadeIn table-striped table-default table-bordered table-hover dataTable repeater display responsive nowrap';
    private const ALL_COLUMNS_KEY = 'all::columns';
    private const DEFAULT_CONNECTION = 'mysql';
    
    // Public properties - Table structure
    public array $elements = [];
    public array $element_name = [];
    public array $records = [];
    public array $columns = [];
    public array $labels = [];
    public array $relations = [];
    public array $relational_data = [];
    // Guard to avoid duplicate relation-key update churn
    private array $processedRelationKeyUpdates = [];
    public array $filter_scripts = [];
    public array $hidden_columns = [];
    public ?string $labelTable = null;
    public ?string $connection = null;
    public $search_columns = false;
    
    // Private properties - Configuration
    private array $params = [];
    private array $chartOptions = [];
    private array $syncElements = [];
    private bool $setDatatable = true;
    private string $tableType = 'datatable';
    private string $all_columns = self::ALL_COLUMNS_KEY;
    private array $originalFieldsBeforeValidation = [];
    
    public function __construct() {
        $this->initializeDefaults();
    }
    
    /**
     * Initialize default values and configurations
     */
    private function initializeDefaults(): void {
        $this->element_name['table'] = $this->tableType;
        $this->variables['table_class'] = self::DEFAULT_TABLE_CLASS;
        // Declarative Relations API: prepare container for declared relations and dot columns
        $this->variables['declared_relations'] = [];
        $this->variables['dot_columns'] = [];
    }
    
    /**
     * Set the HTTP method for table requests
     */
    public function method(string $method): void {
        $this->method = $method;
    }
    
    /**
     * Set the table label
     */
    public function label(string $label): void {
        $this->labelTable = $label;
    }
    
    /**
     * Create a new chart canvas instance
     */
    private function createChartCanvas(): Chart {
        return new Chart();
    }
    
    /**
     * Set chart options for rendering
     */
    public function chartOptions(string $optionName, array $optionValues = []): void {
        $this->chartOptions[$optionName] = $optionValues;
    }
    
    /**
     * Create and configure chart for table
     */
    public function chart(
        string $chartType, 
        array $fieldsets = [], 
        $format = null, 
        ?string $category = null, 
        ?string $group = null, 
        ?string $order = null
    ): void {
        $chart = $this->setupChart($chartType, $fieldsets, $format, $category, $group, $order);
        $this->processChartElements($chart, $chartType, $fieldsets, $format, $category, $group, $order);
    }
    
    /**
     * Setup chart instance and configuration
     */
    private function setupChart(
        string $chartType, 
        array $fieldsets, 
        $format, 
        ?string $category, 
        ?string $group, 
        ?string $order
    ): Chart {
        $chart = $this->createChartCanvas();
        $chart->connection = $this->connection;
        $chart->syncWith($this);
        
        $this->applyChartOptions($chart);
        $chart->{$chartType}($this->tableName, $fieldsets, $format, $category, $group, $order);
        
        return $chart;
    }
    
    /**
     * Apply configured chart options to chart instance
     */
    private function applyChartOptions(Chart $chart): void {
        if (empty($this->chartOptions)) {
            return;
        }
        
        foreach ($this->chartOptions as $optName => $optValues) {
            $chart->{$optName}($optValues);
        }
        
        $this->chartOptions = [];
    }
    
    /**
     * Process chart elements and sync with table
     */
    private function processChartElements(
        Chart $chart, 
        string $chartType, 
        array $fieldsets, 
        $format, 
        ?string $category, 
        ?string $group, 
        ?string $order
    ): void {
        $this->element_name['chart'] = $chart->chartLibrary;
        $tableIdentity = $this->tableID[$this->tableName];
        
        $canvas = $this->buildChartCanvas($chart, $tableIdentity);
        $defaultPageFilters = $this->getDefaultPageFilters($tableIdentity);
        
        $this->configureSyncElements($tableIdentity, $chart, $chartType, $fieldsets, $format, $category, $group, $order, $defaultPageFilters);
        
        $chart->modifyFilterTable($this->syncElements[$tableIdentity]);
        
        $syncElements = $this->buildSyncElements($tableIdentity, $canvas, $chart);
        $this->draw(['chart' => $this->tableID[$this->tableName]], $syncElements);
    }
    
    /**
     * Build chart canvas structure
     */
    private function buildChartCanvas(Chart $chart, string $tableIdentity): array {
        return [
            'chart' => [
                $tableIdentity => $chart->elements
            ]
        ];
    }
    
    /**
     * Get default page filters for table
     */
    private function getDefaultPageFilters(string $tableIdentity): array {
        return !empty($this->filter_contents[$tableIdentity]['conditions']['where']) 
            ? $this->filter_contents[$tableIdentity]['conditions']['where'] 
            : [];
    }
    
    /**
     * Configure sync elements for chart integration
     */
    private function configureSyncElements(
        string $tableIdentity, 
        Chart $chart, 
        string $chartType, 
        array $fieldsets, 
        $format, 
        ?string $category, 
        ?string $group, 
        ?string $order, 
        array $defaultPageFilters
    ): void {
        $this->syncElements[$tableIdentity]['identity']['chart_info'] = $chart->identities;
        $this->syncElements[$tableIdentity]['identity']['filter_table'] = "{$tableIdentity}_cdyFILTERForm";
        
        $this->syncElements[$tableIdentity]['datatables'] = [
            'type' => $chartType,
            'source' => $this->tableName,
            'fields' => $fieldsets,
            'format' => $format,
            'category' => $category,
            'group' => $group,
            'order' => $order,
            'page_filter' => ['where' => $defaultPageFilters]
        ];
    }
    
    /**
     * Build sync elements for rendering
     */
    private function buildSyncElements(string $tableIdentity, array $canvas, Chart $chart): array {
        $tableElement = $this->elements[$tableIdentity];
        $canvasElement = $canvas['chart'][$tableIdentity];
        
        return [
            'chart' => [
                $tableIdentity => $tableElement . $chart->script_chart['js'] . implode('', $canvasElement)
            ]
        ];
    }
    
    /**
     * Draw/render elements and handle scripts processing
     */
    private function draw($initial, $data = []): void {
        if ($data) {
            $this->processDrawData($initial, $data);
            $this->processFilterScripts();
        } else {
            $this->elements[] = $initial;
        }
    }
    
    /**
     * Process drawing data for rendering
     */
    private function processDrawData($initial, $data): void {
        if (is_array($initial)) {
            $multiElements = [];
            foreach ($initial as $syncElements) {
                if (is_array($data)) {
                    foreach ($data as $dataValue) {
                        $initData = $dataValue[$syncElements];
                        $multiElements[$syncElements] = is_array($initData) 
                            ? implode('', $initData) 
                            : $initData;
                    }
                    $this->elements[$syncElements] = $multiElements[$syncElements];
                }
            }
        } else {
            $this->elements[$initial] = $data;
        }
    }
    
    /**
     * Process filter scripts for CSS and JS inclusion
     */
    private function processFilterScripts(): void {
        if (empty($this->filter_object->add_scripts)) {
            return;
        }
        
        if (array_key_exists('add_js', $this->filter_object->add_scripts)) {
            $this->processAdvancedScripts();
        } else {
            $this->filter_scripts = $this->filter_object->add_scripts;
        }
    }
    
    /**
     * Process advanced script handling with CSS and JS separation
     */
    private function processAdvancedScripts(): void {
        $scriptCss = $this->extractAndCleanScript('css');
        $scriptJs = $this->extractAndCleanScript('js');
        $scriptAdd = $this->filter_object->add_scripts['add_js'];
        unset($this->filter_object->add_scripts['add_js']);
        
        $this->filter_scripts['css'] = $scriptCss;
        
        $jsScripts = array_merge($scriptJs, $scriptAdd);
        foreach ($jsScripts as $js) {
            $this->filter_scripts['js'][] = $js;
        }
    }
    
    /**
     * Extract and clean script data by type
     */
    private function extractAndCleanScript(string $type): array {
        $script = [];
        if (isset($this->filter_object->add_scripts[$type])) {
            $script = $this->filter_object->add_scripts[$type];
            unset($this->filter_object->add_scripts[$type]);
        }
        return $script;
    }
    
    /**
     * Render table object with tab support
     */
    public function render($object) {
        $tabObj = is_array($object) ? implode('', $object) : '';
        
        return diy_string_contained($tabObj, self::OPEN_TAB_HTML) 
            ? $this->renderTab($object) 
            : $object;
    }
    
    /**
     * Set datatable type configuration
     */
    public function setDatatableType(bool $set = true): void {
        $this->setDatatable = $set;
        $this->tableType = $set ? 'datatable' : 'self::table';
        $this->element_name['table'] = $this->tableType;
    }
    
    /**
     * Set table name
     */
    public function setName(string $tableName): void {
        $this->variables['table_name'] = $tableName;
    }
    
    /**
     * Set table fields
     */
    public function setFields(array $fields): void {
        $this->variables['table_fields'] = $fields;
        // Capture dot-notation columns and infer relations with alias mapping
        $dotMap = [];
        $declared = $this->variables['declared_relations'] ?? [];
        foreach ($fields as $col) {
            if (!is_string($col)) { continue; }
            // Support "field:Label" pattern, then optional "as" alias
            $base = explode(':', $col)[0];
            if (stripos($base, ' as ') !== false) {
                [$path, $alias] = preg_split('/\s+as\s+/i', $base, 2);
                $path  = trim($path);
                $alias = trim($alias);
            } else {
                $path  = $base;
                $alias = null;
            }
            if (strpos($path, '.') !== false) {
                if (!$alias || $alias === '') { $alias = str_replace('.', '_', $path); }
                $dotMap[$path] = $alias;
                $rel = explode('.', $path, 2)[0];
                if ($rel !== '' && !in_array($rel, $declared, true)) { $declared[] = $rel; }
            }
        }
        $this->variables['dot_columns'] = $dotMap; // ['path' => 'alias']
        $this->variables['declared_relations'] = array_values(array_unique($declared));
    }
    
    /**
     * Declarative Relations API: declare Eloquent relations to use.
     * Accepts string or array, merges uniquely, and stores for runtime.
     */
    public function useRelation($relations): self {
        $declared = $this->variables['declared_relations'] ?? [];
        if (is_string($relations) && $relations !== '') {
            if (!in_array($relations, $declared, true)) { $declared[] = $relations; }
        } elseif (is_array($relations)) {
            foreach ($relations as $r) {
                if (is_string($r) && $r !== '' && !in_array($r, $declared, true)) { $declared[] = $r; }
            }
        }
        $this->variables['declared_relations'] = $declared;
        return $this;
    }
    
    /**
     * Set table data model
     */
    public function model(string $model): void {
        $this->variables['table_data_model'] = $model;
    }
    
    /**
     * Run model function for temporary table creation and rendering
     * Can be used before calling $this->table->list() function
     *
     * @param object $modelObject The model object to process
     * @param string $functionName The function name to call (supports :: separator)
     * @param bool $strict Whether to use strict processing mode
     */
    public function runModel(object $modelObject, string $functionName, bool $strict): void {
        $connection = $this->connection ?? self::DEFAULT_CONNECTION;
        
        [$modelFunction, $tableFunction] = $this->parseFunctionName($functionName);
        
        $this->variables['model_processing'] = [
            'model' => $modelObject,
            'function' => $modelFunction,
            'connection' => $connection,
            'table' => $tableFunction,
            'strict' => $strict
        ];
    }
    
    /**
     * Parse function name and determine model and table function names
     */
    private function parseFunctionName(string $functionName): array {
        if (!diy_string_contained($functionName, '::')) {
            return [$functionName, $functionName];
        }
        
        $split = explode('::', $functionName);
        return [$split[0], "{$split[1]}_{$split[0]}"];
    }
    
    /**
     * Set SQL query for table data
     */
    public function query(string $sql): void {
        $this->variables['query'] = $sql;
        $this->model('sql');
    }
    
    /**
     * Set server-side processing mode
     */
    public function setServerSide(bool $serverSide = true): void {
        $this->variables['table_server_side'] = $serverSide;
    }
    
    /**
     * Merge multiple columns into a single column with combined label and values
     *
     * @param string $label The combined column label
     * @param array $mergedColumns Columns to be merged
     * @param string $labelPosition Label position (top, bottom, left, right)
     *
     * Example:
     * $this->mergeColumns('Name', ['first_name', 'last_name'], 'top');
     * This will merge 'first_name' and 'last_name' columns into a single column
     * with 'Name' label and combined values, positioned at the top.
     */
    public function mergeColumns(string $label, array $mergedColumns = [], string $labelPosition = 'top'): void {
        $this->variables['merged_columns'][$label] = [
            'position' => $labelPosition,
            'counts' => count($mergedColumns),
            'columns' => $mergedColumns
        ];
    }
    
    /**
     * Set columns to be hidden in the datatable
     */
    public function setHiddenColumns(array $fields = []): void {
        $this->variables['hidden_columns'] = $fields;
    }
    
    /**
     * Set fixed columns that remain visible during horizontal scrolling
     *
     * @param int|null $leftPos Column index to fix on the left (0 = first column, 1 = second column, etc.)
     * @param int|null $rightPos Column index to fix on the right (0 = first column, 1 = second column, etc.)
     *
     * Example:
     * $this->fixedColumns(0, 1);
     * This will fix the first and last columns in place during scrolling.
     */
    public function fixedColumns(?int $leftPos = null, ?int $rightPos = null): void {
        if ($leftPos !== null) {
            $this->variables['fixed_columns']['left'] = $leftPos;
        }
        if ($rightPos !== null) {
            $this->variables['fixed_columns']['right'] = $rightPos;
        }
    }
    
    /**
     * Clear previously set fixed columns configuration
     *
     * Example:
     * $this->fixedColumns(0, 1);
     * $this->clearFixedColumns();
     * This will remove the fixed columns setting and won't render fixed columns in datatable.
     */
    public function clearFixedColumns(): void {
        unset($this->variables['fixed_columns']);
    }
    
    
    /**
     * Set column alignment in datatable
     *
     * @param string $align Alignment value: "left", "center", or "right"
     * @param array $columns Columns to set alignment for (empty = all columns)
     * @param bool $header Apply to column headers
     * @param bool $body Apply to column body content
     *
     * Example:
     * $this->setAlignColumns('center', ['name', 'address'], true, false);
     * This will center-align "name" and "address" columns in headers only.
     */
    public function setAlignColumns(string $align, array $columns = [], bool $header = true, bool $body = true): void {
        $this->variables['text_align'][$align] = [
            'columns' => $columns,
            'header' => $header,
            'body' => $body
        ];
    }
    
    /**
     * Set columns to right alignment
     *
     * @param array $columns Columns to right-align (empty = all columns)
     * @param bool $header Apply to column headers
     * @param bool $body Apply to column body content
     *
     * Example:
     * $this->setRightColumns(['price', 'total'], true, false);
     * This will right-align "price" and "total" columns in headers only.
     */
    public function setRightColumns(array $columns = [], bool $header = true, bool $body = true): void {
        $this->setAlignColumns('right', $columns, $header, $body);
    }
    
    /**
     * Set columns to center alignment
     *
     * @param array $columns Columns to center-align (empty = all columns)
     * @param bool $header Apply to column headers (default: true)
     * @param bool $body Apply to column body content (default: false)
     *
     * Example:
     * $this->setCenterColumns(['status', 'action'], true, false);
     * This will center-align "status" and "action" columns in headers only.
     */
    public function setCenterColumns(array $columns = [], bool $header = true, bool $body = false): void {
        $this->setAlignColumns('center', $columns, $header, $body);
    }
    
    /**
     * Set columns to left alignment
     *
     * @param array $columns Columns to left-align (empty = all columns)
     * @param bool $header Apply to column headers (default: true)
     * @param bool $body Apply to column body content (default: true)
     *
     * Example:
     * $this->setLeftColumns(['name', 'description'], true, false);
     * This will left-align "name" and "description" columns in headers only.
     */
    public function setLeftColumns(array $columns = [], bool $header = true, bool $body = true): void {
        $this->setAlignColumns('left', $columns, $header, $body);
    }
    
    /**
     * Set background color for table columns
     *
     * @param string $color Background color in hex format (e.g., #ffffff)
     * @param string|null $textColor Text color in hex format (e.g., #000000)
     * @param array|null $columns Specific columns to apply color to (null = all columns)
     * @param bool $header Apply to column headers (default: true)
     * @param bool $body Apply to column body content (default: false)
     *
     * Example:
     * $this->setBackgroundColor('#f5f5f5', '#000000', ['name', 'address'], true, false);
     * This will set background color #f5f5f5 and text color #000000 for "name" and "address" columns in headers only.
     */
    public function setBackgroundColor(
        string $color, 
        ?string $textColor = null, 
        ?array $columns = null, 
        bool $header = true, 
        bool $body = false
    ): void {
        $this->variables['background_color'][$color] = [
            'code' => $color,
            'text' => $textColor,
            'columns' => $columns,
            'header' => $header,
            'body' => $body
        ];
    }
    
    /**
     * Set column width in datatable
     *
     * @param string $fieldName Column name to set width for
     * @param int|false $width Width value in pixels (false = auto width)
     *
     * Example:
     * $this->setColumnWidth('name', 200);
     * This will set "name" column width to 200px.
     */
    public function setColumnWidth(string $fieldName, $width = false): void {
        $this->variables['column_width'][$fieldName] = $width;
    }
    
    /**
     * Add custom HTML attributes to table element
     *
     * @param array $attributes Array of key-value pairs for HTML attributes
     *                         Example: ['class' => 'my-class', 'style' => 'width:100%;']
     *
     * Example:
     * $this->addAttributes(['class' => 'table-striped', 'style' => 'width:100%;']);
     * This will add 'class' and 'style' attributes to the table element.
     */
    public function addAttributes(array $attributes = []): void {
        $this->variables['add_table_attributes'] = $attributes;
    }
    
    /**
     * Set overall table element width
     *
     * @param int $width Table width value
     * @param string $measurement Unit of measurement ('px', '%', 'em', etc.)
     *
     * Example:
     * $this->setWidth(1000, 'px');
     * This will set the table width to 1000px.
     */
    public function setWidth(int $width, string $measurement = 'px'): void {
        $this->addAttributes(['style' => "min-width:{$width}{$measurement};"]);
    }
    
    /**
     * Check and configure column set for operations
     *
     * Determines whether to apply operations to specific columns or all columns
     * based on the input parameter.
     *
     * @param mixed $columns Columns to check (array of specific columns or null/false)
     * @return array Returns array with 'all::columns' key or the provided columns
     *
     * Examples:
     * - checkColumnSet(null) returns ['all::columns' => true]
     * - checkColumnSet(false) returns ['all::columns' => false]  
     * - checkColumnSet(['name', 'email']) returns ['name', 'email']
     */
    private function checkColumnSet($columns): array {
        if (empty($columns)) {
            return [$this->all_columns => $columns !== false];
        }
        
        return $columns;
    }
    
    /**
     * Relational Data
     *
     * Properti ini digunakan untuk menyimpan data hasil relasi antara tabel.
     * Data yang disimpan berupa array associative yang berisi kunci relasi
     * dan nilai berupa array yang berisi data relasi.
     *
     * Contoh penggunaan:
     *
     * // Misal kita memiliki relasi antara tabel users dan tabel roles
     * // dengan nama relasi "user_roles"
     * $this->relational_data = [
     *     'user_roles' => [
     *         'user_id' => 1,
     *         'role_id' => 1,
     *         'role_name' => 'Admin',
     *     ],
     * ];
     *
     * // Maka kita dapat mengakses data relasi dengan cara berikut:
     * $role_name = $this->relational_data['user_roles']['role_name'];
     */
    
    /**
     * Process and draw relation data for table display
     *
     * Extracts and stores relational data from model relationships for table rendering.
     * Handles pivot relationships and field replacements with :: separator.
     *
     * @param object $relation The relation object containing data
     * @param string $relationFunction Name of the relation function
     * @param string $fieldname Target field name (supports :: separator for replacement)
     * @param string $label Display label for the field
     */
    private function drawRelationData(object $relation, string $relationFunction, string $fieldname, string $label): void {
        [$dataRelate, $relateKey] = $this->extractRelationData($relation, $relationFunction);
        [$fieldname, $fieldReplacement] = $this->parseFieldname($fieldname);
        
        $dataRelation = $dataRelate[$fieldname] ?? null;
        
        if (empty($dataRelation)) {
            return;
        }
        
        $fieldset = $fieldReplacement ?? $fieldname;
        $this->storeRelationFieldData($relationFunction, $fieldset, $label, $relateKey, $dataRelation);
        $this->processPivotData($relation, $relationFunction, $fieldset, $relateKey);
    }
    
    /**
     * Extract relation data and key from relation object
     */
    private function extractRelationData(object $relation, string $relationFunction): array {
        if (!empty($relation->{$relationFunction})) {
            return [
                $relation->{$relationFunction}->getAttributes(),
                (int) $relation['id']
            ];
        }
        
        $attributes = $relation->getAttributes();
        return [$attributes, (int) $attributes['id']];
    }
    
    /**
     * Parse fieldname for replacement patterns (::)
     */
    private function parseFieldname(string $fieldname): array {
        if (!diy_string_contained($fieldname, '::')) {
            return [$fieldname, null];
        }
        
        $parts = explode('::', $fieldname);
        return [$parts[1], $parts[0]];
    }
    
    /**
     * Store relation field data in relational_data array
     */
    private function storeRelationFieldData(
        string $relationFunction, 
        string $fieldset, 
        string $label, 
        int $relateKey, 
        $dataValue
    ): void {
        // Initialize structure if not exists
        if (!isset($this->relational_data[$relationFunction]['field_target'][$fieldset])) {
            $this->relational_data[$relationFunction]['field_target'][$fieldset] = [
                'field_name' => $fieldset,
                'field_label' => $label,
                'relation_data' => []
            ];
        }
        
        // PERFORMANCE FIX: Prevent duplicate storage
        $existingData = $this->relational_data[$relationFunction]['field_target'][$fieldset]['relation_data'][$relateKey] ?? null;
        if ($existingData && $existingData['field_value'] === $dataValue) {
            // Data already exists and is identical, skip storage and logging
            return;
        }
        
        // Merge relation data instead of overwriting
        $this->relational_data[$relationFunction]['field_target'][$fieldset]['relation_data'][$relateKey] = [
            'field_value' => $dataValue,
            'user_id' => $relateKey,  // Store for debugging
            'group_id' => $relateKey  // Default, might be overridden by pivot data
        ];
        
        // Only log when actually storing new/updated data (reduced to DEBUG level)
        \Log::debug("📝 STORING RELATION DATA", [
            'function' => $relationFunction,
            'field' => $fieldset,
            'relate_key' => $relateKey,
            'value' => $dataValue,
            'total_relations' => count($this->relational_data[$relationFunction]['field_target'][$fieldset]['relation_data'] ?? []),
            'action' => $existingData ? 'updated' : 'created'
        ]);
    }
    
    /**
     * Process pivot table data if exists
     */
    private function processPivotData(object $relation, string $relationFunction, string $fieldset, int $relateKey): void {
        if (empty($relation->pivot)) {
            return;
        }
        
        foreach ($relation->pivot->getAttributes() as $pivotField => $pivotData) {
            $this->relational_data[$relationFunction]['field_target'][$fieldset]['relation_data'][$relateKey][$pivotField] = $pivotData;
            
            // For user-group relationships, use group_id as the actual key for lookup
            if ($pivotField === 'group_id' && !empty($pivotData)) {
                $actualGroupId = intval($pivotData);

                // Skip if we've already processed this mapping to avoid log/data churn
                $guardKey = $relationFunction . '|' . $fieldset . '|' . $relateKey . '->' . $actualGroupId;
                if (isset($this->processedRelationKeyUpdates[$guardKey])) {
                    continue;
                }
                $this->processedRelationKeyUpdates[$guardKey] = true;

                \Log::info("🔄 UPDATING RELATION KEY for user-group", [
                    'original_key' => $relateKey,
                    'pivot_group_id' => $actualGroupId,
                    'field' => $fieldset,
                    'relation' => $relationFunction
                ]);
                
                // Copy data to correct group_id key and update group_id reference
                if ($relateKey !== $actualGroupId) {
                    $relationData = $this->relational_data[$relationFunction]['field_target'][$fieldset]['relation_data'][$relateKey];
                    $relationData['group_id'] = $actualGroupId;
                    $relationData['user_id'] = $relateKey;
                    $this->relational_data[$relationFunction]['field_target'][$fieldset]['relation_data'][$actualGroupId] = $relationData;
                    
                    // Keep original key for compatibility but mark it
                    $this->relational_data[$relationFunction]['field_target'][$fieldset]['relation_data'][$relateKey]['is_user_key'] = true;
                }
            }
        }
    }
    
    /**
     * Process model relationships for table display
     *
     * @param object $model The model object containing relationships
     * @param string $relationFunction Name of the relation method
     * @param string $fieldDisplay Field to display from related model
     * @param array $filterForeignKeys Foreign key filters for complex relationships
     * @param string|null $label Display label (auto-generated if null)
     * @param string|null $fieldConnect Connection field for field replacement
     */
    private function processRelationship(
        object $model, 
        string $relationFunction, 
        string $fieldDisplay, 
        array $filterForeignKeys = [], 
        ?string $label = null, 
        ?string $fieldConnect = null
    ): void {
        $relationalData = $model->with($relationFunction)->get();
        
        if ($relationalData->isEmpty()) {
            return;
        }
        
        $label = $label ?? ucwords(diy_clean_strings($fieldDisplay, ' '));
        
        foreach ($relationalData as $item) {
            $this->processRelationItem($item, $relationFunction, $fieldDisplay, $label, $fieldConnect);
        }
        
        if (!empty($filterForeignKeys)) {
            $this->relational_data[$relationFunction]['foreign_keys'] = $filterForeignKeys;
        }
    }
    
    /**
     * Process individual relation item
     */
    private function processRelationItem(
        object $item, 
        string $relationFunction, 
        string $fieldDisplay, 
        string $label, 
        ?string $fieldConnect
    ): void {
        if (empty($item->{$relationFunction})) {
            return;
        }
        
        if (diy_is_collection($item->{$relationFunction})) {
            foreach ($item->{$relationFunction} as $relation) {
                $this->drawRelationData($relation, $relationFunction, $fieldDisplay, $label);
            }
        } else {
            $displayField = $fieldConnect ? "{$fieldConnect}::{$fieldDisplay}" : $fieldDisplay;
            $this->drawRelationData($item, $relationFunction, $displayField, $label);
        }
    }
    
    /**
     * Set simple relation data for table
     *
     * @param object $model The model object
     * @param string $relationFunction Relation method name
     * @param string $fieldDisplay Field to display
     * @param array $filterForeignKeys Foreign key filters
     * @param string|null $label Display label
     */
    public function relations(
        object $model, 
        string $relationFunction, 
        string $fieldDisplay, 
        array $filterForeignKeys = [], 
        ?string $label = null
    ): void {
        $this->processRelationship($model, $relationFunction, $fieldDisplay, $filterForeignKeys, $label);
    }
    
    /**
     * Replace field values with relational data
     *
     * @param object $model The model object
     * @param string $relationFunction Relation method name  
     * @param string $fieldDisplay Field to display from relation
     * @param string|null $label Display label
     * @param string|null $fieldConnect Connection field name
     */
    public function fieldReplacementValue(
        object $model, 
        string $relationFunction, 
        string $fieldDisplay, 
        ?string $label = null, 
        ?string $fieldConnect = null
    ): void {
        $this->processRelationship($model, $relationFunction, $fieldDisplay, [], $label, $fieldConnect);
    }
    
    /**
     * Set default ordering for table columns
     *
     * @param string $column Column name to order by
     * @param string $order Order direction ('asc' or 'desc')
     */
    public function orderBy(string $column, string $order = 'asc'): void {
        $this->variables['orderby_column'] = [
            'column' => $column, 
            'order' => strtolower($order)
        ];
    }
    
    /**
     * Set columns as sortable
     *
     * @param array|string|null $columns Columns to make sortable (null = all columns)
     */
    public function sortable($columns = null): void {
        $this->variables['sortable_columns'] = $this->checkColumnSet($columns);
    }
    
    /**
     * Set columns as clickable
     *
     * @param array|string|null $columns Columns to make clickable (null = all columns)
     */
    public function clickable($columns = null): void {
        $this->variables['clickable_columns'] = $this->checkColumnSet($columns);
    }
    
    /**
     * Set columns as searchable in datatable
     *
     * Configures which columns can be used for search filtering.
     * If no columns specified, all columns will be searchable by default.
     *
     * @param array|string|null|false $columns Columns to make searchable
     *                                         - null: all columns searchable
     *                                         - false: no columns searchable  
     *                                         - array: specific columns searchable
     *
     * Examples:
     * - $this->searchable(); // All columns searchable
     * - $this->searchable(['name', 'email']); // Only name and email searchable
     * - $this->searchable(false); // No columns searchable
     */
    public function searchable($columns = null): void {
        $this->variables['searchable_columns'] = $this->checkColumnSet($columns);
        
        $this->search_columns = $this->determineSearchColumns($columns);
    }
    
    /**
     * Determine which columns to use for search functionality
     */
    private function determineSearchColumns($columns) {
        if (empty($columns)) {
            return $columns === false ? false : $this->all_columns;
        }
        
        return $columns;
    }
    
    /**
     * Set Searching Data Filter
     *
     * @param string $column
     * 		: field name target
     * @param string $type
     * 		: inputbox     [no relational data $relate auto set with false],
     *         datebox      [no relational data $relate auto set with false],
     *         daterangebox [no relational data $relate auto set with false],
     *         selectbox    [single or multi],
     *         checkbox,
     *         radiobox
     * @param boolean|string|array $relate
     * 		: if false = no relational Data
     * 		: if true  = relational data set to all others columns/fieldname members
     * 		: if (string) fieldname / other column = relate to just one that column target was setted
     * 		: if (array) fieldnames / others any columns = relate to any that column target was setted
     */
    public function filterGroups($column, $type, $relate = false) {
        $filters           = [];
        $filters['column'] = $column;
        $filters['type']   = $type;
        $filters['relate'] = $relate;
        
        $this->variables['filter_groups'][] = $filters;
    }
    
    /**
     * Mengatur batasan jumlah baris yang akan ditampilkan saat pemuatan awal.
     *
     * Fungsi ini digunakan untuk mengatur jumlah baris yang ditampilkan ketika tabel
     * pertama kali dimuat. Pengguna dapat menentukan jumlah baris dalam bentuk angka
     * atau menggunakan string '*' atau 'all' untuk menampilkan semua baris.
     *
     * @param mixed $limit : Batasan jumlah baris yang akan ditampilkan. Bisa berupa
     *                       integer untuk jumlah baris tertentu atau string '*'/'all'
     *                       untuk menampilkan semua baris.
     *
     * Contoh penggunaan:
     *
     * // Menampilkan 10 baris pada pemuatan awal
     * $this->displayRowsLimitOnLoad(10);
     *
     * // Menampilkan semua baris pada pemuatan awal
     * $this->displayRowsLimitOnLoad('all');
     */
    public function displayRowsLimitOnLoad($limit = 10) {
        if (is_string($limit)) {
            if (in_array(strtolower($limit), ['*', 'all'])) {
                $this->variables['on_load']['display_limit_rows'] = '*';
            }
        } else {
            $this->variables['on_load']['display_limit_rows'] = intval($limit);
        }
    }
    
    public function clearOnLoad() {
        unset($this->variables['on_load']['display_limit_rows']);
    }
    
    protected $filter_model = [];
    public function filterModel(array $data = []) {
        $this->filter_model = $data;
    }
    
    private function check_column_exist($table_name, $fields, $connection = 'mysql') {
        $fieldset = [];
        foreach ($fields as $field) {
            if (diy_check_table_columns($table_name, $field, $connection)) {
                $fieldset[] = $field;
            }
        }
        
        return $fieldset;
    }
    
    private $clear_variables = null;
    private function clearVariables($clear_set = true) {
        $this->clear_variables = $clear_set;
        if (true === $this->clear_variables) {
            $this->clear_all_variables();
        }
    }
    
    public function clear($clear_set = true) {
        return $this->clearVariables($clear_set);
    }
    
    public function clearVar($name) {
        $this->variables[$name] = [];
    }
    
    
    public $useFieldTargetURL = 'id';
    public function setUrlValue($field = 'id') {
        $this->variables['url_value'] = $field;
        $this->useFieldTargetURL = $field;
    }
    
    private $variables = [];
    private function clear_all_variables() {
        $this->variables['on_load']              = [];
        $this->variables['url_value']            = [];
        $this->variables['merged_columns']       = [];
        $this->variables['text_align']           = [];
        $this->variables['background_color']     = [];
        $this->variables['attributes']           = [];
        $this->variables['orderby_column']       = [];
        $this->variables['sortable_columns']     = [];
        $this->variables['clickable_columns']    = [];
        $this->variables['searchable_columns']   = [];
        $this->variables['filter_groups']        = [];
        $this->variables['column_width']         = [];
        $this->variables['format_data']          = [];
        $this->variables['add_table_attributes'] = [];
        $this->variables['fixed_columns']        = [];
        $this->variables['model_processing']     = [];
    }
    
    public $conditions = [];
    public function where($field_name, $logic_operator = false, $value = false) {
        $this->conditions['where'] = [];
        if (is_array($field_name)) {
            foreach ($field_name as $fieldname => $fieldvalue) {
                $this->conditions['where'][] = [
                    'field_name' => $fieldname,
                    'operator'   => '=',
                    'value'      => $fieldvalue
                ];
            }
        } else {
            $this->conditions['where'][] = [
                'field_name' => $field_name,
                'operator'   => $logic_operator,
                'value'      => $value
            ];
        }
    }
    
    /**
     * Filter Table
     *
     * @param array $filters
     * 		: $this->model_filters
     * @return array
     */
    public function filterConditions($filters = []) {
        return $this->where($filters);
    }
    
    /**
     * Buat Kondisi Kolom Berdasarkan Nilai Tertentu
     *
     * Fungsi ini digunakan untuk membuat kondisi kolom berdasarkan nilai tertentu.
     * Kondisi ini berguna untuk mengatur tampilan kolom berdasarkan nilai yang di dapat dari database.
     *
     * @param string $field_name
     * 		: Nama kolom yang akan di set kondisi.
     * @param string $target
     * 		: Target kolom yang akan di set kondisi. Bisa berupa 'row', 'cell', atau 'field_name'.
     * 		: Jika target adalah 'row', maka kondisi akan di set ke baris yang berisi data kolom tersebut.
     * 		: Jika target adalah 'cell', maka kondisi akan di set ke kolom yang berisi data tersebut.
     * 		: Jika target adalah 'field_name', maka kondisi akan di set ke kolom yang berisi data tersebut.
     * @param string $logic_operator
     * 		: Operator logika yang digunakan untuk membandingkan nilai kolom dengan nilai yang di set.
     * 		: Bisa berupa '==', '!=', '===', '!==', '>', '<', '>=', '<='.
     * @param string $value
     * 		: Nilai yang di set sebagai perbandingan dengan nilai kolom.
     * @param string $rule
     * 		: Aturan yang digunakan untuk mengatur tampilan kolom berdasarkan nilai yang di dapat.
     * 		: Bisa berupa 'css style', 'prefix', 'suffix', 'prefix&suffix', 'replace', 'integer', 'float', 'float|2'.
     * @param string|array $action
     * 		: Aksi yang akan di lakukan jika kondisi terpenuhi.
     * 		: Jika di set sebagai string, maka akan menggantikan url button dengan url yang di set.
     * 		: Jika di set sebagai array, maka akan di gunakan untuk aturan 'prefix&suffix'.
     * 		: Array pertama akan di set sebagai prefix dan array terakhir akan di set sebagai suffix.
     *
     * Contoh penggunaan:
     * $this->table->columnCondition('text_field', 'cell', '!==', 'Testing', 'prefix', '! ');
     * maka kolom "text_field" akan di set dengan prefix "!" jika nilai kolom tidak sama dengan "Testing".
     *
     * Contoh lain:
     * $this->table->columnCondition('user_status', 'action', '==', 'Disabled', 'replace', 'url::action_check|danger|volume-off');
     * maka kolom "user_status" akan di set dengan menggantikan url button dengan url "action_check" jika nilai kolom sama dengan "Disabled".
     */
    public function columnCondition(string $field_name, string $target, string $logic_operator = null, string $value = null, string $rule, $action) {
        $this->conditions['columns'][] = [
            'field_name'     => $field_name,
            'field_target'   => $target,
            'logic_operator' => $logic_operator,
            'value'          => $value,
            'rule'           => $rule,
            'action'         => $action
        ];
    }
    
    public $formula = [];
    
    /**
     * Membuat Formula Untuk Menghitung Nilai Kolom
     *
     * Fungsi ini digunakan untuk membuat formula yang dapat digunakan untuk menghitung nilai kolom tertentu.
     * Formula ini dapat digunakan untuk menghitung nilai kolom yang dihitung berdasarkan beberapa kolom lainnya.
     *
     * @param string $name
     * 		: Nama dari formula yang akan dibuat.
     * 		: Nama ini akan digunakan sebagai nama kolom yang dihitung.
     * @param string $label
     * 		: Label dari formula yang akan dibuat.
     * 		: Label ini akan digunakan sebagai nama tampilan dari kolom yang dihitung.
     * @param array $field_lists
     * 		: Daftar kolom yang akan digunakan untuk menghitung nilai formula.
     * 		: Kolom-kolom ini harus berupa array yang berisi nama-nama kolom yang diinginkan.
     * @param string $logic
     * 		: Operator logika yang digunakan untuk menghitung nilai formula.
     * 		: Operator logika ini dapat berupa '+', '-', '*', '/', '%', '||', '&&'.
     * @param string $node_location
     * 		: Lokasi node yang akan di isi dengan hasil perhitungan formula.
     * 		: Jika di set, maka hasil perhitungan formula akan di isi ke node yang di set.
     * 		: Jika tidak di set, maka hasil perhitungan formula akan di isi ke node yang sama dengan nama formula.
     * @param bool $node_after_node_location
     * 		: Jika true, maka hasil perhitungan formula akan di isi setelah node yang di set.
     * 		: Jika false, maka hasil perhitungan formula akan di isi sebelum node yang di set.
     *
     * Contoh penggunaan:
     * $this->table->formula('total', 'Total', ['harga', 'jumlah'], '*', 'tbody', true);
     * maka akan membuat formula dengan nama 'total' yang akan menghitung nilai kolom 'harga' dan 'jumlah' dengan operator '*' dan akan di isi ke node 'tbody' setelah node yang sama dengan nama formula.
     */
    public function formula(string $name, string $label = null, array $field_lists, string $logic, string $node_location = null, bool $node_after_node_location = true) {
        $this->labels[$name]           = $label;
        $this->conditions['formula'][] = [
            'name'          => $name,
            'label'         => $label,
            'field_lists'   => $field_lists,
            'logic'         => $logic,
            'node_location' => $node_location,
            'node_after'    => $node_after_node_location
        ];
    }
    
    /**
     * Format Data
     *
     * Fungsi ini digunakan untuk mengatur format penampilan data di dalam tabel.
     * Fungsi ini dapat digunakan untuk mengatur format penampilan data berupa angka, boolean, atau string.
     *
     * @param string|array $fields
     * 		: Nama kolom yang akan di format.
     * 		: Jika di set sebagai string, maka hanya kolom dengan nama yang di set yang akan di format.
     * 		: Jika di set sebagai array, maka beberapa kolom dengan nama yang di set akan di format.
     * @param int $decimal_endpoint
     * 		: Jumlah desimal yang akan di tampilkan.
     * 		: Jika di set maka akan menampilkan jumlah desimal yang di set.
     * 		: Jika tidak di set maka akan menampilkan jumlah desimal sesuai dengan default.
     * @param string $separator
     * 		: Pemisah desimal yang akan di gunakan.
     * 		: Jika di set maka akan menggunakan pemisah desimal yang di set.
     * 		: Jika tidak di set maka akan menggunakan pemisah desimal yang default (".").
     * @param string $format
     * 		: Tipe format yang akan di gunakan.
     * 		: Jika di set maka akan menggunakan tipe format yang di set.
     * 		: Jika tidak di set maka akan menggunakan tipe format yang default ("number").
     *
     * Contoh penggunaan:
     * $this->table->format('harga', 2, ',', 'number');
     * maka kolom "harga" akan di format dengan menggunakan 2 desimal, pemisah desimal "," dan tipe format "number".
     */
    public function format($fields, int $decimal_endpoint = 0, $separator = '.', $format = 'number') {
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $this->variables['format_data'][$field] = [
                    'field_name'       => $field,
                    'decimal_endpoint' => $decimal_endpoint,
                    'format_type'      => $format,
                    'separator'        => $separator
                ];
            }
            
        } else {
            $this->variables['format_data'][$fields] = [
                'field_name'          => $fields,
                'decimal_endpoint'    => $decimal_endpoint,
                'format_type'         => $format,
                'separator'           => $separator
            ];
        }
    }
    
    public function set_regular_table() {
        $this->tableType = 'regular';
    }
    
    public $button_removed = [];
    
    /**
     * Menghapus tombol dari daftar tombol yang tersedia.
     *
     * Fungsi ini digunakan untuk menghapus tombol-tombol tertentu dari daftar tombol
     * yang tersedia. Tombol yang dihapus akan disimpan dalam properti $button_removed.
     *
     * @param mixed $remove : Tombol yang akan dihapus. Bisa berupa string untuk satu tombol
     *                        atau array untuk beberapa tombol.
     *
     * Contoh penggunaan:
     *
     * // Menghapus satu tombol
     * $this->removeButtons('edit');
     *
     * // Menghapus beberapa tombol
     * $this->removeButtons(['view', 'delete']);
     *
     * Maka tombol 'edit' atau tombol 'view' dan 'delete' akan dihapus dari daftar tombol yang tersedia.
     */
    public function removeButtons($remove) {
        if (!empty($remove)) {
            if (is_array($remove)) {
                $this->button_removed = $remove;
            } else {
                $this->button_removed = [$remove];
            }
        }
    }
    
    private $defaultButtons = ['view', 'edit', 'delete'];
    
    /**
     * Mengatur aksi tombol untuk tabel.
     *
     * Fungsi ini digunakan untuk mengatur aksi tombol yang tersedia dalam tabel.
     * Jika parameter $default_actions tidak diatur ke true, maka tombol default akan dihapus.
     *
     * @param array $actions : Daftar aksi tombol yang ingin ditetapkan.
     * @param boolean|array $default_actions : Jika diatur ke false, tombol default akan dihapus.
     *                                        Jika diatur ke array, tombol yang sesuai dalam array akan dihapus.
     *
     * Contoh penggunaan:
     *
     * // Mengatur aksi tombol tanpa tombol default
     * $this->setActions(['custom_action1', 'custom_action2'], false);
     *
     * // Mengatur aksi tombol dengan menghapus tombol default 'edit' dan 'delete'
     * $this->setActions(['custom_action1'], ['edit', 'delete']);
     */
    public function setActions($actions = [], $default_actions = true) {
        if (true !== $default_actions) {
            if (is_array($default_actions)) {
                $this->removeButtons($default_actions);
            } else {
                $this->removeButtons($this->defaultButtons);
            }
        }
    }
    
    private $objectInjections = [];
    public $filterPage = [];
    /**
     * Initiate Configuration
     *
     * @param string $connection
     * @param array $object
     */
    public function config($object = []) {
        if (!empty($this->connection)) {
            $this->connection($this->connection);
        }
        
        if (!empty($this->filter_page)) {
            $this->filterPage = $this->filter_page;
        }
    }
    
    public function connection($db_connection) {
        $this->connection = $db_connection;
    }
    
    public function resetConnection() {
        $this->connection = null;
    }
    
    public $modelProcessing = [];
    public $tableName = [];
    public $tableID   = [];
    
    /**
     * Create data table list with comprehensive configuration options
     *
     * Generates a data table display with support for server-side processing, actions, 
     * numbering, relational data, and various customization options.
     *
     * @param string|null $table_name Table name to display (null = use model table)
     * @param array $fields Columns to display (empty = all columns)
     * @param bool|string|array $actions Action buttons configuration
     *                                  - true: default actions (view, edit, delete)
     *                                  - string: custom action format 'action|style|icon'
     *                                  - array: specific actions array
     * @param bool $server_side Enable server-side processing
     * @param bool $numbering Show row numbering
     * @param array $attributes Additional table attributes
     * @param bool $server_side_custom_url Use custom URL for server-side processing
     *
     * Examples:
     * $this->lists('users', ['name', 'email'], true, true, true);
     * $this->lists('users', [], ['view', 'edit'], false, false, ['class' => 'custom']);
     */
    public function lists(
        ?string $table_name = null, 
        array $fields = [], 
        $actions = true, 
        bool $server_side = true, 
        bool $numbering = true, 
        array $attributes = [], 
        bool $server_side_custom_url = false
    ): void {
        // Initialize table processing
        $table_name = $this->initializeTableProcessing($table_name);
        
        // Ensure table name is valid before proceeding
        if (empty($table_name)) {
            throw new \InvalidArgumentException('Table name cannot be empty after initialization');
        }
        
        // Phase 1 enhancement: parse dot-notation fields and explicit aliases ("as")
        // Build mapping: ['relation.path' => 'alias'] and auto-populate declared_relations
        $declaredRelations = $this->variables['declared_relations'] ?? [];
        $dotMap = [];
        foreach ($fields as $col) {
            if (!is_string($col)) { continue; }
            // Strip optional label syntax "field:Label"
            $base = explode(':', $col)[0];
            if (stripos($base, ' as ') !== false) {
                [$path, $alias] = preg_split('/\s+as\s+/i', $base, 2);
                $path  = trim($path);
                $alias = trim($alias);
            } else {
                $path  = $base;
                $alias = null;
            }
            if (strpos($path, '.') !== false) {
                if (!$alias || $alias === '') {
                    $alias = str_replace('.', '_', $path);
                }
                $dotMap[$path] = $alias;
                // Extract and register relation name
                $rel = explode('.', $path, 2)[0];
                if ($rel !== '' && !in_array($rel, $declaredRelations, true)) {
                    $declaredRelations[] = $rel;
                }
            }
        }
        $this->variables['dot_columns'] = $dotMap; // associative map: path => alias
        $this->variables['declared_relations'] = array_values(array_unique($declaredRelations));
        
        $fields = $this->processTableFields($table_name, $fields);

        // Ensure fields is always an array
        if (!is_array($fields)) {
            $fields = [];
        }

        // Replace any dot-path fields in $fields with their alias for downstream consistency
        if (!empty($this->variables['dot_columns']) && is_array($this->variables['dot_columns'])) {
            $pathToAlias = $this->variables['dot_columns']; // ['path' => 'alias']
            foreach ($fields as $idx => $field) {
                if (!is_string($field)) { continue; }
                $base = explode(':', $field)[0];
                // Extract potential "as" alias to avoid double mapping
                if (stripos($base, ' as ') !== false) {
                    [$path, $explicitAlias] = preg_split('/\s+as\s+/i', $base, 2);
                    $path = trim($path);
                    $explicitAlias = trim($explicitAlias);
                    if (isset($pathToAlias[$path])) {
                        $fields[$idx] = $pathToAlias[$path];
                    } else {
                        $fields[$idx] = $explicitAlias ?: str_replace('.', '_', $path);
                    }
                } elseif (strpos($base, '.') !== false) {
                    if (isset($pathToAlias[$base])) {
                        $fields[$idx] = $pathToAlias[$base];
                    } else {
                        $fields[$idx] = str_replace('.', '_', $base);
                    }
                } else {
                    $fields[$idx] = $base; // keep raw field name
                }
            }
        }

        $fields = $this->processRelationalData($table_name, $fields);

        // Configure table settings
        $this->configureSearchColumns($fields);
        $this->configureTableColumns($table_name, $fields, $actions);
        $this->configureTableParameters($table_name, $actions, $numbering, $attributes, $server_side, $server_side_custom_url);
        $this->processConditions($table_name);

        // Render the table
        $this->renderTable($table_name);
    }
    
    /**
     * Initialize table processing and determine table name
     */
    private function initializeTableProcessing(?string $table_name): string {
        // Handle model processing
        if (!empty($this->variables['model_processing'])) {
            if ($table_name !== $this->variables['model_processing']['table']) {
                $table_name = $this->variables['model_processing']['table'];
            }
            $this->modelProcessing[$table_name] = $this->variables['model_processing'];
        }
        
        // Resolve table name if null
        if ($table_name === null) {
            $table_name = $this->resolveTableName();
            $this->variables['table_name'] = $table_name;
        }
        
        $this->tableName = $table_name;
        return $table_name ?? '';
    }
    
    /**
     * Resolve table name from model or query
     */
    private function resolveTableName(): string {
        if (empty($this->variables['table_data_model'])) {
            return '';
        }
        
        if ($this->variables['table_data_model'] === 'sql') {
            $sql = $this->variables['query'] ?? '';
            if ($sql) {
                $table_name = diy_get_table_name_from_sql($sql);
                if ($table_name) {
                    $this->params[$table_name]['query'] = $sql;
                    return $table_name;
                }
            }
            return '';
        }
        
        $table_name = diy_get_model_table($this->variables['table_data_model']);
        return $table_name ?? '';
    }
    
    /**
     * Process table fields and labels
     */
    private function processTableFields(string $table_name, array $fields): array {
        \Log::debug("🔄 PROCESSING TABLE FIELDS", [
            'table_name' => $table_name,
            'input_fields' => $fields,
            'dot_columns' => $this->variables['dot_columns'] ?? [],
            'declared_relations' => $this->variables['declared_relations'] ?? []
        ]);
        
        if (empty($fields)) {
            $defaultFields = $this->getDefaultTableFields($table_name);
            return is_array($defaultFields) ? $defaultFields : [];
        }
        
        // Store original fields before processing
        $originalFields = $fields;
        
        // Extract labels from field definitions (field:label format)  
        $processedFields = $this->extractFieldLabels($fields);
        \Log::debug("📝 AFTER EXTRACT LABELS", ['processed_fields' => $processedFields]);
        
        // Validate and process fields based on table type
        $validatedFields = $this->validateTableFields($table_name, $processedFields);
        \Log::debug("✅ AFTER VALIDATION", ['validated_fields' => $validatedFields]);
        
        // Store original fields for relational processing
        $this->originalFieldsBeforeValidation = $originalFields;
        
        // Ensure we return a valid array
        return is_array($validatedFields) ? $validatedFields : [];
    }
    
    /**
     * Extract field labels from field definitions with colon separator
     */
    private function extractFieldLabels(array $fields): array {
        $processedFields = [];
        
        foreach ($fields as $index => $field) {
            if (diy_string_contained($field, ':')) {
                $parts = explode(':', $field);
                $this->labels[$parts[0]] = $parts[1];
                $processedFields[$index] = $parts[0];
            } else {
                $processedFields[$index] = $field;
            }
        }
        
        return $processedFields;
    }
    
    /**
     * Get default table fields when none specified
     */
    private function getDefaultTableFields(string $table_name): array {
        if (!empty($this->variables['table_fields'])) {
            return $this->validateTableFields($table_name, $this->variables['table_fields']);
        }
        
        $fields = diy_get_table_columns($table_name, $this->connection);
        
        if (empty($fields) && !empty($this->modelProcessing)) {
            if (!diy_schema('hasTable', $table_name)) {
                diy_model_processing_table($this->modelProcessing, $table_name);
            }
            $fields = diy_get_table_columns($table_name);
        }
        
        return is_array($fields) ? $fields : [];
    }
    
    /**
     * Validate and process table fields
     */
    private function validateTableFields(string $table_name, array $fields): array {
        if (empty($fields)) {
            return $this->getDefaultTableFields($table_name);
        }
        
        // Skip validation for view tables
        if (diy_string_contained($table_name, 'view_')) {
            return $fields;
        }
        
        // Separate physical columns from relation columns
        $physicalFields = [];
        $relationFields = [];
        $declaredRelations = $this->variables['declared_relations'] ?? [];
        
        foreach ($fields as $field) {
            $isRelationField = false;
            
            // Check if field belongs to any declared relation
            foreach ($declaredRelations as $relation) {
                if (strpos($field, $relation . '_') === 0) {
                    $relationFields[] = $field;
                    $isRelationField = true;
                    break;
                }
            }
            
            if (!$isRelationField) {
                $physicalFields[] = $field;
            }
        }
        
        // Validate only physical columns against database schema
        $validatedPhysicalFields = $this->check_column_exist($table_name, $physicalFields, $this->connection);
        
        // Combine validated physical fields with relation fields
        $validatedFields = array_merge($validatedPhysicalFields, $relationFields);
        
        \Log::debug("🔍 FIELD VALIDATION BREAKDOWN", [
            'physical_fields' => $physicalFields,
            'relation_fields' => $relationFields,
            'validated_physical' => $validatedPhysicalFields,
            'final_validated' => $validatedFields
        ]);
        
        // Handle model processing if fields validation failed
        if (empty($validatedFields) && !empty($this->modelProcessing)) {
            $validatedFields = $fields; // Keep original fields for model processing
            if (!diy_schema('hasTable', $table_name)) {
                diy_model_processing_table($this->modelProcessing, $table_name);
            }
            $validatedFields = diy_get_table_columns($table_name);
        }
        
        // Ensure we always return an array
        if (is_array($validatedFields) && !empty($validatedFields)) {
            return $validatedFields;
        }
        
        if (is_array($fields) && !empty($fields)) {
            return $fields;
        }
        
        return [];
    }
    
    /**
     * Process relational data and integrate with table fields
     */
    private function processRelationalData(string $table_name, array $fields): array {
        \Log::debug("🔍 PROCESSING RELATIONAL DATA", [
            'table_name' => $table_name,
            'input_fields' => $fields,
            'has_relational_data' => !empty($this->relational_data),
            'relational_data_keys' => array_keys($this->relational_data ?? [])
        ]);
        
        if (empty($this->relational_data)) {
            \Log::debug("⚠️ No relational data found, returning original fields");
            return $fields;
        }
        
        $fieldRelations = $this->extractFieldRelations($table_name);
        $originalFields = $this->originalFieldsBeforeValidation ?? [];
        
        \Log::debug("🔗 FIELD RELATIONS EXTRACTED", [
            'field_relations' => array_keys($fieldRelations),
            'original_fields' => $originalFields
        ]);
        
        $result = $this->integrateRelationalFields($fields, $fieldRelations, $originalFields);
        
        \Log::debug("✅ RELATIONAL DATA PROCESSED", [
            'input_fields' => $fields,
            'output_fields' => $result,
            'fields_added' => array_diff($result, $fields)
        ]);
        
        return $result;
    }
    
    /**
     * Extract field relations from relational data
     */
    private function extractFieldRelations(string $table_name): array {
        $fieldRelations = [];
        
        foreach ($this->relational_data as $relationData) {
            if (!empty($relationData['field_target'])) {
                foreach ($relationData['field_target'] as $fieldName => $relationFields) {
                    $fieldRelations[$fieldName] = $relationFields;
                }
            }
            
            if (!empty($relationData['foreign_keys'])) {
                $this->columns[$table_name]['foreign_keys'] = $relationData['foreign_keys'];
            }
        }
        
        return $fieldRelations;
    }
    
    /**
     * Integrate relational fields with table fields
     */
    private function integrateRelationalFields(array $fields, array $fieldRelations, array $originalFields): array {
        // Ensure fields is a proper array
        if (!is_array($fields)) {
            $fields = [];
        }
        
        if (empty($fieldRelations)) {
            return $fields;
        }
        
        $relations = [];
        $fieldsetChanged = [];
        
        // Check for fields that exist in relations and current fields
        foreach ($fieldRelations as $fieldName => $relationData) {
            if (in_array($fieldName, $fields)) {
                $fieldsetChanged[$fieldName] = $fieldName;
            }
        }
        
        // Find the difference between original and current fields
        $checkFieldSet = array_diff($originalFields, $fields);
        
        // Add changed fields to the check set
        if (!empty($fieldsetChanged)) {
            $fieldsetChangedProcessed = [];
            foreach ($fields as $fieldIndex => $fieldValue) {
                if (isset($fieldsetChanged[$fieldValue])) {
                    $fieldsetChangedProcessed[$fieldIndex] = $fieldsetChanged[$fieldValue];
                    unset($fields[$fieldIndex]);
                }
            }
            if (!empty($fieldsetChangedProcessed)) {
                $checkFieldSet = array_merge($checkFieldSet, $fieldsetChangedProcessed);
            }
        }
        
        // Process relational fields
        if (!empty($checkFieldSet)) {
            foreach ($checkFieldSet as $index => $fieldName) {
                if (isset($fieldRelations[$fieldName]) && is_array($fieldRelations[$fieldName])) {
                    $relationData = $fieldRelations[$fieldName];
                    if (isset($relationData['field_name']) && isset($relationData['field_label'])) {
                        $this->labels[$relationData['field_name']] = $relationData['field_label'];
                        $relations[$index] = $relationData['field_name'];
                        $this->columns[$this->tableName]['relations'][$fieldName] = $relationData;
                    }
                }
            }
        }
        
        // Insert relational fields at appropriate positions
        if (!empty($relations)) {
            // Ensure fields is an array before processing
            if (!is_array($fields)) {
                $fields = [];
            }
            
            foreach ($relations as $index => $relationName) {
                // Ensure index is valid and non-negative
                $safeIndex = is_numeric($index) && $index >= 0 ? $index : count($fields);
                diy_array_insert($fields, $safeIndex, $relationName);
            }
        }
        
        // Ensure we always return a valid array
        return is_array($fields) ? $fields : [];
    }
    
    /**
     * Configure search columns based on field configuration
     */
    private function configureSearchColumns(array $fields): void {
        if (empty($this->search_columns)) {
            $this->search_columns = false;
            return;
        }
        
        if ($this->all_columns === $this->search_columns) {
            $this->search_columns = $fields;
        }
        // If $this->search_columns is already an array, keep it as is
    }
    
    /**
     * Configure table columns settings
     */
    private function configureTableColumns(string $table_name, array $fields, $actions): void {
        \Log::debug("🏗️ CONFIGURING TABLE COLUMNS", [
            'table_name' => $table_name,
            'fields' => $fields,
            'actions' => $actions,
            'has_dot_columns' => !empty($this->variables['dot_columns']),
            'dot_columns' => $this->variables['dot_columns'] ?? [],
            'declared_relations' => $this->variables['declared_relations'] ?? []
        ]);
        
        // Normalize actions
        if ($actions === false) {
            $actions = [];
        }
        
        // Set basic column configuration
        $this->columns[$table_name]['lists'] = $fields;
        $this->columns[$table_name]['actions'] = $actions;
        
        // Apply various column settings
        $this->applyColumnSettings($table_name);
        
        \Log::debug("✅ TABLE COLUMNS CONFIGURED", [
            'final_lists' => $this->columns[$table_name]['lists'] ?? [],
            'searchable' => $this->columns[$table_name]['searchable'] ?? [],
            'filter_groups' => $this->columns[$table_name]['filter_groups'] ?? []
        ]);
    }
    
    /**
     * Apply various column settings from variables
     */
    private function applyColumnSettings(string $table_name): void {
        $settings = [
            'text_align' => 'align',
            'merged_columns' => 'merge',
            'orderby_column' => 'orderby',
            'clickable_columns' => 'clickable',
            'sortable_columns' => 'sortable',
            'searchable_columns' => 'searchable',
            'filter_groups' => 'filter_groups',
            'format_data' => 'format_data'
        ];
        
        foreach ($settings as $variableKey => $columnKey) {
            if (!empty($this->variables[$variableKey])) {
                $this->columns[$table_name][$columnKey] = $this->variables[$variableKey];
            }
        }
        
        // Handle hidden columns with cleanup
        if (!empty($this->variables['hidden_columns'])) {
            $this->columns[$table_name]['hidden_columns'] = $this->variables['hidden_columns'];
            $this->variables['hidden_columns'] = [];
        }
        
        // Handle button removal
        if (!empty($this->button_removed)) {
            $this->columns[$table_name]['button_removed'] = $this->button_removed;
        }
    }
    
    /**
     * Configure table parameters including attributes and server-side settings
     */
    private function configureTableParameters(
        string $table_name, 
        $actions, 
        bool $numbering, 
        array $attributes, 
        bool $server_side, 
        bool $server_side_custom_url
    ): void {
        // Set numbering configuration
        $this->records['index_lists'] = $numbering;
        
        // Generate table ID and configure basic attributes
        $this->configureTableAttributes($table_name, $attributes);
        
        // Configure table parameters
        $this->setTableParameters($table_name, $actions, $numbering, $attributes, $server_side, $server_side_custom_url);
        
        // Apply additional configurations
        $this->applyAdditionalConfigurations($table_name);
    }
    
    /**
     * Configure table attributes including ID, class, and background
     */
    private function configureTableAttributes(string $table_name, array &$attributes): void {
        $this->tableID[$table_name] = diy_clean_strings("CoDIY_{$this->tableType}_{$table_name}_" . diy_random_strings(50, false));
        $attributes['table_id'] = $this->tableID[$table_name];
        $attributes['table_class'] = diy_clean_strings("CoDIY_{$this->tableType}_") . ' ' . $this->variables['table_class'];
        
        if (!empty($this->variables['background_color'])) {
            $attributes['bg_color'] = $this->variables['background_color'];
        }
    }
    
    /**
     * Set main table parameters
     */
    private function setTableParameters(
        string $table_name, 
        $actions, 
        bool $numbering, 
        array $attributes, 
        bool $server_side, 
        bool $server_side_custom_url
    ): void {
        $this->params[$table_name] = array_merge($this->params[$table_name] ?? [], [
            'actions' => $actions,
            'buttons_removed' => $this->button_removed,
            'numbering' => $numbering,
            'attributes' => $attributes,
            'server_side' => [
                'status' => $server_side,
                'custom_url' => $server_side_custom_url
            ]
        ]);
    }
    
    /**
     * Apply additional configurations from variables
     */
    private function applyAdditionalConfigurations(string $table_name): void {
        // Configure on-load settings
        if (!empty($this->variables['on_load']['display_limit_rows'])) {
            $this->params[$table_name]['on_load']['display_limit_rows'] = $this->variables['on_load']['display_limit_rows'];
        }
        
        // Configure fixed columns
        if (!empty($this->variables['fixed_columns'])) {
            $this->params[$table_name]['fixed_columns'] = $this->variables['fixed_columns'];
        }
        
        // Configure column width
        if (!empty($this->variables['column_width'])) {
            $this->params[$table_name]['attributes']['column_width'] = $this->variables['column_width'];
        }
        
        // Configure URL values
        if (!empty($this->variables['url_value'])) {
            $this->params[$table_name]['url_value'] = $this->variables['url_value'];
        }
        
        // Configure additional table attributes
        if (!empty($this->variables['add_table_attributes'])) {
            $this->params[$table_name]['attributes']['add_attributes'] = $this->variables['add_table_attributes'];
        }
        
        // Configure filter model
        if (!empty($this->filter_model)) {
            $this->params[$table_name]['filter_model'] = $this->filter_model;
        }
    }
    
    /**
     * Process conditions configuration for table
     */
    private function processConditions(string $table_name): void {
        if (empty($this->conditions)) {
            return;
        }
        
        $this->params[$table_name]['conditions'] = $this->conditions;
        
        $this->processFormulaConditions($table_name);
        $this->processWhereConditions($table_name);
        $this->processColumnConditions($table_name);
    }
    
    /**
     * Process formula conditions
     */
    private function processFormulaConditions(string $table_name): void {
        if (empty($this->conditions['formula'])) {
            return;
        }
        
        $this->formula[$table_name] = $this->conditions['formula'];
        unset($this->conditions['formula']);
        $this->conditions[$table_name]['formula'] = $this->formula[$table_name];
    }
    
    /**
     * Process where conditions with complex logic
     */
    private function processWhereConditions(string $table_name): void {
        if (empty($this->conditions['where'])) {
            return;
        }
        
        $normalizedConditions = $this->normalizeWhereConditions($this->conditions['where']);
        $this->conditions[$table_name]['where'] = $normalizedConditions;
    }
    
    /**
     * Normalize where conditions structure
     */
    private function normalizeWhereConditions(array $whereConditions): array {
        $whereConds = [];
        
        // Group conditions by field and operator
        foreach ($whereConditions as $condition) {
            $field = $condition['field_name'];
            $operator = $condition['operator'];
            $value = $condition['value'];
            
            $whereConds[$field][$operator]['field_name'][$field] = $field;
            $whereConds[$field][$operator]['operator'][$operator] = $operator;
            $whereConds[$field][$operator]['values'][] = $value;
        }
        
        // Normalize the structure
        $normalized = [];
        foreach ($whereConds as $field => $operators) {
            foreach ($operators as $operator => $data) {
                $normalized[] = [
                    'field_name' => $field,
                    'operator' => $operator,
                    'value' => $data['values']
                ];
            }
        }
        
        return $normalized;
    }
    
    /**
     * Process column conditions
     */
    private function processColumnConditions(string $table_name): void {
        if (empty($this->conditions['columns'])) {
            return;
        }
        
        $this->conditions[$table_name]['columns'] = $this->conditions['columns'];
        unset($this->conditions['columns']);
    }
    
    /**
     * Render the configured table
     */
    private function renderTable(string $table_name): void {
        $label = !empty($this->variables['table_name']) ? $this->variables['table_name'] : null;
        
        if ($this->tableType === 'datatable') {
            $this->renderDatatable($table_name, $this->columns, $this->params, $label);
        } else {
            $this->renderGeneralTable($table_name, $this->columns, $this->params);
        }
    }
    
    /**
     * Render datatable with configured settings
     */
    private function renderDatatable(string $name, array $columns, array $attributes, ?string $label): void {
        // Configure model if available
        if (!empty($this->variables['table_data_model'])) {
            $attributes[$name]['model'] = $this->variables['table_data_model'];
            asort($attributes[$name]);
        }
        
        // Configure search filters
        $columns[$name]['filters'] = !empty($this->search_columns) ? $this->search_columns : [];
        
        // Set HTTP method
        $this->setMethod($this->method);
        
        // Handle label override
        if (!empty($this->labelTable)) {
            $label = $this->labelTable . ':setLabelTable';
            $this->labelTable = null;
        }
        
        // Inject Declarative Relations metadata into attributes so Builder can persist into runtime
        try {
            if (!empty($this->variables['declared_relations'])) {
                $attributes[$name]['declared_relations'] = $this->variables['declared_relations'];
            }
            if (!empty($this->variables['dot_columns'])) {
                $attributes[$name]['dot_columns'] = $this->variables['dot_columns']; // ['path' => 'alias']
            }
        } catch (\Throwable $e) {}

        // Render the table
        $this->draw($this->tableID[$name], $this->table($name, $columns, $attributes, $label));
    }
    
    /**
     * Render general table (non-datatable)
     */
    private function renderGeneralTable(string $name, array $columns, array $attributes): void {
        // Implementation for general table rendering
        // This is a placeholder - the original had dd($columns) which suggests it's incomplete
        $this->draw($this->tableID[$name], $this->table($name, $columns, $attributes));
    }
}