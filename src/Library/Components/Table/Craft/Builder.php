<?php
namespace Incodiy\Codiy\Library\Components\Table\Craft;

use Incodiy\Codiy\Models\Admin\System\DynamicTables;
use Incodiy\Codiy\Library\Components\Table\Craft\Method\Post;
use Incodiy\Codiy\Library\Components\Table\Craft\Search;
use Incodiy\Codiy\Library\Components\Table\Craft\DatatableRuntime;

/**
 * Created on 21 Apr 2021
 * Time Created	: 08:13:39
 *
 * @filesource	Builder.php
 *
 * @author		wisnuwidi@incodiy.com - 2021
 * @copyright	wisnuwidi
 * @email		wisnuwidi@incodiy.com
 */

/**
 * Table Builder Class
 * 
 * Handles the construction and rendering of data tables with various features:
 * - Dynamic column management
 * - Header customization and merging
 * - Server-side and client-side filtering
 * - Color customization
 * - Action buttons and numbering
 * 
 * This class has been refactored to follow SOLID principles and improve maintainability.
 */
class Builder {
	use Scripts;
	
	public $model;
	public $method = 'GET';
	
	/**
	 * Security mode configuration
	 * When true, forces POST method for sensitive operations
	 * 
	 * @var bool
	 */
	protected $secureMode = false;
	
	/**
	 * Allowed methods for data table operations
	 * 
	 * @var array
	 */
	protected $allowedMethods = ['GET', 'POST'];
	
	/**
	 * Set the HTTP method for table operations
	 * 
	 * @param string $method HTTP method (GET/POST)
	 * @throws InvalidArgumentException If method is not allowed
	 * @return self
	 */
	public function setMethod($method) {
		$method = strtoupper($method);
		
		if (!in_array($method, $this->allowedMethods)) {
			throw new \InvalidArgumentException("Method '{$method}' is not allowed. Allowed methods: " . implode(', ', $this->allowedMethods));
		}
		
		// Force POST method if secure mode is enabled
		if ($this->secureMode && $method === 'GET') {
			$method = 'POST';
		}
		
		$this->method = $method;
		
		return $this;
	}
	
	/**
	 * Enable or disable secure mode
	 * In secure mode, all data table operations will use POST method
	 * 
	 * @param bool $secure Whether to enable secure mode
	 * @return self
	 */
	public function setSecureMode(bool $secure = true): self {
		$this->secureMode = $secure;
		
		// Force POST method if secure mode is enabled
		if ($secure) {
			$this->method = 'POST';
		}
		
		return $this;
	}
	
	/**
	 * Check if secure mode is enabled
	 * 
	 * @return bool
	 */
	public function isSecureMode(): bool {
		return $this->secureMode;
	}
	
	/**
	 * Get current HTTP method
	 * 
	 * @return string
	 */
	public function getMethod(): string {
		return $this->method;
	}
	
	/**
	 * Set method to POST for secure operations
	 * 
	 * @return self
	 */
	public function usePostMethod(): self {
		$this->setMethod('POST');
		return $this;
	}
	
	/**
	 * Set method to GET for standard operations
	 * 
	 * @return self
	 */
	public function useGetMethod(): self {
		if (!$this->secureMode) {
			$this->setMethod('GET');
		}
		return $this;
	}
	
	/**
	 * Main table generation method
	 * 
	 * Orchestrates the entire table building process including data preparation,
	 * model setup, header generation, and final HTML construction.
	 * 
	 * @param string $name Table name
	 * @param array $columns Column configuration
	 * @param array $attributes Table attributes and settings
	 * @param string|null $label Optional custom table label
	 * @return string Complete HTML table structure
	 */
	protected function table($name, $columns = [], $attributes = [], $label = null) {
		$data = $this->prepareTableData($name, $columns, $attributes);
		$this->setupModelAndConfiguration($name, $data, $attributes);
		$this->processFormulation($data, $name);
		
		$tableTitle = $this->generateTableTitle($name, $label);
		$tableAttributes = $this->prepareTableAttributes($name, $attributes);
		$table = $this->renderTableStructure($data, $name, $tableAttributes);
		
		// Register datatable runtime context for POST processing
		$runtime = new \stdClass();
		$runtime->datatables = new \stdClass();
		$runtime->datatables->model = $this->model ?? [];
		$runtime->datatables->columns = $data[$name]['columns'] ?? [];
		$runtime->datatables->conditions = $data[$name]['attributes']['conditions'] ?? [];
		$runtime->datatables->modelProcessing = $data[$name]['attributes']['model_processing'] ?? [];
		$runtime->datatables->useFieldTargetURL = $data[$name]['attributes']['field_target_url'] ?? 'id';
		$runtime->datatables->records = ['index_lists' => !empty($data[$name]['attributes']['numbering'])];

		// Persist declarative relations and dot column mapping for AJAX requests (Phase 1)
try {
	$runtime->datatables->declared_relations = $attributes[$name]['declared_relations'] ?? [];
	$runtime->datatables->dot_columns        = $attributes[$name]['dot_columns']        ?? [];
} catch (\Throwable $e) {}

DatatableRuntime::set($name, $runtime);


		return $this->buildCompleteTableHtml($data, $name, $table, $tableTitle, $attributes);
	}

	private function prepareTableData($name, $columns, $attributes) {
		$data = [];
		$model = $this->createTableModel($name, $attributes);
		
		$data[$name]['name'] = $name;
		$data[$name]['columns'] = $columns[$name];
		$data[$name]['attributes'] = $attributes[$name];
		
		if (!empty($attributes[$name]['model']) && 'sql' === $attributes[$name]['model']) {
			$data[$name]['model'] = 'sql';
			$data[$name]['sql'] = $attributes[$name]['query'] ?? '';
		} else {
			$data[$name]['model'] = $attributes[$name]['model'] ?? get_class($model);
		}
		
		return $data;
	}

	private function createTableModel($name, $attributes) {
		$model = null;
		
		if (!empty($attributes[$name]['model'])) {
			if ('sql' !== $attributes[$name]['model']) {
				$model = new $attributes[$name]['model']();
			}
		} else {
			$model = new DynamicTables(null, $this->connection);
			$model->setTable($name);
			$attributes[$name]['model'] = get_class($model);
		}
		
		return $model;
	}

	private function setupModelAndConfiguration($name, $data, $attributes) {
		$model = null;
		
		if (!empty($attributes[$name]['model']) && 'sql' !== $attributes[$name]['model']) {
			$model = new $attributes[$name]['model']();
		} elseif (empty($attributes[$name]['model'])) {
			$model = new DynamicTables(null, $this->connection);
			$model->setTable($name);
		}
		
		if (!empty($model)) {
			$this->model[$name]['type'] = 'model';
			$this->model[$name]['source'] = $model;
		} else {
			$this->model[$name]['type'] = 'sql';
			$this->model[$name]['source'] = $data[$name]['sql'];
		}
		
		if (!empty($attributes[$name])) {
			$this->serverSide = $attributes[$name]['server_side']['status'] ?? false;
			$this->customURL = $attributes[$name]['server_side']['custom_url'] ?? null;
		}
	}

	private function processFormulation(&$data, $name) {
		if (!empty($data[$name]['attributes']['conditions']['formula'])) {
			if (!empty($data[$name]['columns']['lists'])) {
				$data[$name]['columns']['lists'] = $this->setFormulaColumns(
					$data[$name]['columns']['lists'], 
					$data[$name]
				);
			}
		}
	}

	private function generateTableTitle($name, $label) {
		if (false === $name) {
			return '';
		}

		$list = ' List(s)';
		if (diy_string_contained($label, ':setLabelTable')) {
			$list = '';
			$label = str_replace(':setLabelTable', '', $label);
		}

		$titleText = empty($label) 
			? ucwords(str_replace('_', ' ', $name)) . $list
			: ucwords(str_replace('_', ' ', $label)) . $list;

		return '<div class="panel-heading"><div class="pull-left"><h3 class="panel-title">' . $titleText . '</h3></div><div class="clearfix"></div></div>';
	}

	private function prepareTableAttributes($name, $attributes) {
		$tableID = $attributes[$name]['attributes']['table_id'] ?? 'table_' . $name;
		$tableClass = $attributes[$name]['attributes']['table_class'] ?? 'table table-striped';
		
		$baseTableAttributes = ['id' => $tableID, 'class' => $tableClass];
		$tableAttributes = $baseTableAttributes;
		
		if (!empty($attributes[$name]['attributes']['add_attributes'])) {
			$tableAttributes = array_merge_recursive($baseTableAttributes, $attributes[$name]['attributes']['add_attributes']);
		}
		
		return $tableAttributes;
	}

	private function renderTableStructure($data, $name, $tableAttributes) {
		$table = '<div class="panel-body no-padding">';
		$table .= '<table' . $this->setAttributes($tableAttributes) . '>';
		$table .= $this->header($data[$name]);
		$table .= '</table>';
		$table .= '</div>';
		
		return $table;
	}

	private function buildCompleteTableHtml($data, $name, $table, $tableTitle, $attributes) {
		$tableID = $attributes[$name]['attributes']['table_id'] ?? 'table_' . $name;
		$datatable_columns = $this->body($data[$name]);
		
		$html = '<div class="row">';
		$html .= '<div class="col-md-12">';
		$html .= '<div class="panel">' . $tableTitle . '<br />';
		$html .= '<div class="relative diy-table-box-' . $tableID . '">';
		
		if (!empty($this->filter_contents[$tableID]['id']) && $tableID === $this->filter_contents[$tableID]['id']) {
			$html .= '<span class="diy-dt-search-box hide" id="diy-' . $tableID . '-search-box">' . $this->filterButton($this->filter_contents[$tableID]) . '</span>';
			$html .= $this->filterModalbox($this->filter_contents[$tableID]);
		}
		
		$html .= $table . $datatable_columns;
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		
		return $html;
	}
	
	private $columnManipulated = [];
	private function checkColumnLabel($check_labels, $columns) {
		$labels = [];
		foreach ($columns as $icol => $vcol) {
			if (!empty($this->labels[$vcol])) {
				$this->columnManipulated[$this->labels[$vcol]] = $vcol;
				$labels[$icol] = $this->labels[$vcol];
			} else {
				$this->columnManipulated[$vcol] = $vcol;
				$labels[$icol] = $vcol;
			}
		}
		
		return $labels;
	}
	
	private function header($data = []) {
		$headerConfig = $this->prepareHeaderConfiguration($data);
		$columns = $this->processColumnsForHeader($data, $headerConfig);
		$colorSettings = $this->getColorSettings($data['attributes']);
		
		return $this->buildHeaderHtml($columns, $headerConfig, $colorSettings, $data['attributes']);
	}

	private function prepareHeaderConfiguration($data) {
		$columns = $data['columns'];
		$attributes = $data['attributes'];
		
		$config = [
			'sortable' => !empty($data['columns']['sortable']) ? $data['columns']['sortable'] : false,
			'actions' => !empty($attributes['actions']) ? $attributes['actions'] : false,
			'numbering' => !empty($attributes['numbering']) ? $attributes['numbering'] : false,
			'widthColumn' => [],
			'hiddenColumn' => !empty($data['columns']['hidden_columns']) ? $data['columns']['hidden_columns'] : [],
			'alignColumn' => $this->extractAlignmentConfiguration($columns),
			'mergeColumn' => $this->processMergeColumns($columns)
		];

		// Column widths
		if (!empty($attributes['attributes']['column_width'])) {
			$config['widthColumn'] = $attributes['attributes']['column_width'];
		}

		return $config;
	}

	private function extractAlignmentConfiguration($columns) {
		$alignColumn = [];
		
		if (!empty($columns['align'])) {
			foreach ($columns['align'] as $align => $column_data) {
				if (true === $column_data['header']) {
					foreach ($column_data['columns'] as $field) {
						$alignColumn['header'][$field] = $align;
					}
				}
			}
		}
		
		return $alignColumn;
	}

	private function processMergeColumns($columns) {
		if (empty($columns['merge'])) {
			return null;
		}

		if (!empty($this->labels)) {
			$merged_labels = [];
			foreach ($columns['merge'] as $colmergename => $colmerged) {
				$merged_labels[$colmergename]['position'] = $colmerged['position'];
				$merged_labels[$colmergename]['counts'] = $colmerged['counts'];
				$merged_labels[$colmergename]['columns'] = $this->checkColumnLabel($this->labels, $colmerged['columns']);
			}
			return !empty($merged_labels) ? $merged_labels : $columns['merge'];
		}

		return $columns['merge'];
	}

	private function processColumnsForHeader($data, $config) {
		$columns = !empty($data['columns']['lists']) ? $data['columns']['lists'] : [];
		
		// Add numbering
		if (true === $config['numbering'] && !in_array('id', $columns)) {
			$columns = array_merge(['number_lists'], $columns);
		}
		
		// Add actions column
		if (!empty($config['actions'])) {
			$columns[] = 'action';
		}
		
		// Apply labels if available
		if (!empty($this->labels)) {
			$columns = $this->checkColumnLabel($this->labels, $columns);
		}
		
		return $columns;
	}

	private function getColorSettings($attributes) {
		$bgColor = $attributes['attributes']['bg_color'] ?? null;
		$tableColor = $this->backgroundColor($bgColor);
		
		return [
			'columnColor' => $tableColor['columns'] ?? [],
			'headerColor' => $tableColor['header'] ?? null
		];
	}

	private function buildHeaderHtml($columns, $config, $colorSettings, $attributes) {
		$dataColumns = !empty($this->columnManipulated) ? $this->columnManipulated : [];
		
		if (!empty($config['mergeColumn'])) {
			$headerTable = '<thead>';
			if (!empty($config['alignColumn']['header'])) {
				$attributes['attributes']['column']['class'] = array_merge_recursive(
					$attributes['attributes']['column']['class'] ?? [], 
					$config['alignColumn']['header']
				);
			}
			$headerTable .= $this->mergeColumns($config['mergeColumn'], $columns, $attributes);
			$headerTable .= '</thead>';
		} else {
			$headerTable = '<thead><tr>';
			$headerTable .= $this->buildRegularHeaderColumns($columns, $config, $colorSettings, $dataColumns);
			$headerTable .= '</tr></thead>';
		}
		
		return $headerTable;
	}

	private function buildRegularHeaderColumns($columns, $config, $colorSettings, $dataColumns) {
		$headerCells = '';
		
		foreach ($columns as $column) {
			$id = $this->generateColumnId($column, $dataColumns);
			$class = $this->generateColumnClass($column, $config);
			$headerLabel = ucwords(str_replace('_', ' ', $column));
			
			$headerCells .= $this->renderHeaderCell($column, $headerLabel, $id, $class, $config, $colorSettings);
		}
		
		return $headerCells;
	}

	private function generateColumnId($column, $dataColumns) {
		$columnForId = !empty($dataColumns[$column]) ? $dataColumns[$column] : $column;
		return $this->setAttributes(['id' => diy_decrypt(diy_encrypt($columnForId))]);
	}

	private function generateColumnClass($column, $config) {
		$classAttributes = '';
		
		if (in_array($column, $config['hiddenColumn'])) {
			$classAttributes .= ' diy-hide-column';
		}
		
		if (!empty($config['alignColumn']['header'][$column])) {
			$classAttributes .= $config['alignColumn']['header'][$column];
		}
		
		if ('action' === strtolower($column)) {
			$classAttributes .= ' diy-column-action';
		}
		
		return !empty($classAttributes) ? $this->setAttributes(['class' => $classAttributes]) : null;
	}

	private function renderHeaderCell($column, $headerLabel, $id, $class, $config, $colorSettings) {
		$columnLower = strtolower($column);
		$headerColor = $colorSettings['headerColor'];
		
		if (in_array($columnLower, ['no', 'id', 'nik'])) {
			return "<th width=\"50\"{$headerColor}>{$headerLabel}</th>";
		}
		
		if ('number_lists' === $columnLower) {
			return '<th width="30"' . $headerColor . '>No</th>' .
				   '<th width="30"' . $headerColor . '>ID</th>';
		}
		
		$widthAttr = '';
		if (!empty($config['widthColumn'][$columnLower])) {
			$widthAttr = ' width="' . $config['widthColumn'][$columnLower] . '"';
		}
		
		$columnColor = $colorSettings['columnColor'][$column] ?? '';
		
		return "<th{$id}{$class}{$headerColor}{$columnColor}{$widthAttr}>{$headerLabel}</th>";
	}
	
	private function mergeColumns($mergeColumn = [], $columns = [], $attributes = []) {
		$mergeConfig = $this->prepareMergeConfiguration($mergeColumn, $columns, $attributes);
		$modifiedColumns = $this->processColumnsForMerging($mergeConfig['columns'], $mergeConfig['mergeColumn'], $mergeConfig);
		$mergedTable = $this->buildMergedRowTable($modifiedColumns['originalColumns'], $mergeConfig['mergeColumn'], $mergeConfig);
		$headerTable = $this->buildMergeHeaderTable($modifiedColumns['modifiedColumns'], $mergeConfig, $attributes);
		
		return '<tr>' . $headerTable . '</tr>' . $mergedTable;
	}

	private function prepareMergeConfiguration($mergeColumn, $columns, $attributes) {
		$columns = $this->checkColumnLabel($this->labels, $columns);
		$dataColumns = $this->columnManipulated;
		$colorSettings = $this->extractMergeColorSettings($attributes);
		
		// Extract column configuration for merged cells
		$hiddenColumn = !empty($attributes['columns']['hidden_columns']) ? $attributes['columns']['hidden_columns'] : [];
		$alignColumn = $this->extractAlignmentConfiguration($attributes['columns'] ?? []);
		$widthColumn = !empty($attributes['attributes']['column_width']) ? $attributes['attributes']['column_width'] : [];
		
		return [
			'mergeColumn' => $mergeColumn,
			'columns' => $columns,
			'dataColumns' => $dataColumns,
			'setMergeText' => '::merge::',
			'columnColor' => $colorSettings['columnColor'],
			'headerColor' => $colorSettings['headerColor'],
			'hiddenColumn' => $hiddenColumn,
			'alignColumn' => $alignColumn,
			'widthColumn' => $widthColumn
		];
	}

	private function extractMergeColorSettings($attributes) {
		$columnColor = [];
		$headerColor = null;
		
		if (!empty($attributes['attributes']['bg_color'])) {
			$tableColor = $this->backgroundColor($attributes['attributes']['bg_color']);
			$columnColor = $tableColor['columns'] ?? [];
			$headerColor = $tableColor['header'] ?? null;
		}
		
		return [
			'columnColor' => $columnColor,
			'headerColor' => $headerColor
		];
	}

	private function processColumnsForMerging($columns, $mergeColumn, $config) {
		$originalColumns = $columns;
		$modifiedColumns = $columns;
		
		foreach ($columns as $index => $column) {
			if ($this->isColumnInMerge($column, $mergeColumn)) {
				$mergeInfo = $this->findColumnMergeInfo($column, $mergeColumn);
				unset($modifiedColumns[$index]);
				$modifiedColumns[$index] = $mergeInfo['label'] . $config['setMergeText'] . $mergeInfo['counts'];
			}
		}
		
		$modifiedColumns = array_unique($modifiedColumns);
		ksort($modifiedColumns);
		
		return [
			'originalColumns' => $originalColumns,
			'modifiedColumns' => $modifiedColumns
		];
	}

	private function buildMergedRowTable($columns, $mergeColumn, $config) {
		$mergedTable = '<tr>';
		
		foreach ($columns as $column) {
			if ($this->isColumnInMerge($column, $mergeColumn)) {
				$headerLabel = ucwords(str_replace('_', ' ', $column));
				$cellHtml = $this->renderMergedCell($column, $headerLabel, $config);
				$mergedTable .= $cellHtml;
			}
		}
		
		$mergedTable .= '</tr>';
		return $mergedTable;
	}

	private function isColumnInMerge($column, $mergeColumn) {
		foreach ($mergeColumn as $mergeData) {
			if (in_array($column, $mergeData['columns'])) {
				return true;
			}
		}
		return false;
	}

	private function findColumnMergeInfo($column, $mergeColumn) {
		foreach ($mergeColumn as $mergeLabel => $mergeData) {
			if (in_array($column, $mergeData['columns'])) {
				return [
					'label' => $mergeLabel,
					'counts' => $mergeData['counts']
				];
			}
		}
		return ['label' => '', 'counts' => 0];
	}

	private function renderMergedCell($column, $headerLabel, $config) {
		$id = !empty($config['dataColumns'][$column]) ? 
			$this->setAttributes(['id' => diy_decrypt(diy_encrypt($config['dataColumns'][$column]))]) : '';
		
		$columnClass = $this->getMergedCellClass($column, $config);
		$columnColor = $config['columnColor'][$column] ?? '';
		$headerColor = $config['headerColor'];
		
		return "<th{$id}{$columnClass}{$headerColor}{$columnColor}>{$headerLabel}</th>";
	}

	private function getMergedCellClass($column, $config) {
		$classAttributes = '';
		
		// Check for hidden columns
		if (!empty($config['hiddenColumn']) && in_array($column, $config['hiddenColumn'])) {
			$classAttributes .= ' diy-hide-column';
		}
		
		// Check for column alignment
		if (!empty($config['alignColumn']['header'][$column])) {
			$classAttributes .= ' ' . trim($config['alignColumn']['header'][$column]);
		}
		
		// Special handling for action column
		if ('action' === strtolower($column)) {
			$classAttributes .= ' diy-column-action';
		}
		
		// Check for width column classes if needed
		if (!empty($config['widthColumn'][strtolower($column)])) {
			$classAttributes .= ' diy-width-' . $config['widthColumn'][strtolower($column)];
		}
		
		return !empty($classAttributes) ? $this->setAttributes(['class' => trim($classAttributes)]) : '';
	}

	private function buildMergeHeaderTable($columns, $config, $attributes) {
		$headerTable = '';
		
		foreach ($columns as $column) {
			$headerLabel = ucwords(str_replace('_', ' ', str_replace($config['setMergeText'], '', $column)));
			$id = !empty($config['dataColumns'][$column]) ? 
				$this->setAttributes(['id' => diy_decrypt(diy_encrypt($config['dataColumns'][$column]))]) : '';
			
			if (str_contains($column, $config['setMergeText'])) {
				$headerTable .= $this->renderMergeHeaderCell($column, $headerLabel, $config);
			} else {
				$headerTable .= $this->renderRegularMergeHeaderCell($column, $headerLabel, $id, $config, $attributes);
			}
		}
		
		return $headerTable;
	}

	private function renderMergeHeaderCell($column, $headerLabel, $config) {
		$mergeInfo = explode($config['setMergeText'], $column);
		$colspan = intval($mergeInfo[1]);
		$headerLabel = ucwords(str_replace('_', ' ', $mergeInfo[0]));
		$headerColor = $config['headerColor'];
		
		return "<th class=\"merge-column\" colspan=\"{$colspan}\"{$headerColor}>{$headerLabel}</th>";
	}

	private function renderRegularMergeHeaderCell($column, $headerLabel, $id, $config, $attributes) {
		$columnLower = strtolower($column);
		$headerColor = $config['headerColor'];
		
		if (in_array($columnLower, ['no', 'id', 'nik'])) {
			return "<th rowspan=\"2\" width=\"50\"{$headerColor}>{$headerLabel}</th>";
		}
		
		if ('number_lists' === $columnLower) {
			return "<th rowspan=\"2\" width=\"30\"{$headerColor}>No</th><th rowspan=\"2\" width=\"30\"{$headerColor}>ID</th>";
		}
		
		$columnClass = $this->buildMergeColumnClass($column, $attributes);
		$widthAttr = $this->getMergeColumnWidth($column, $attributes);
		$columnColor = $config['columnColor'][$column] ?? '';
		
		return "<th rowspan=\"2\"{$id}{$columnClass}{$headerColor}{$columnColor}{$widthAttr}>{$headerLabel}</th>";
	}

	private function buildMergeColumnClass($column, $attributes) {
		$classAttributes = '';
		
		if (!empty($attributes['attributes']['column']['class'][$column])) {
			$classAttributes .= $attributes['attributes']['column']['class'][$column];
		}
		
		if ('action' === strtolower($column)) {
			$classAttributes .= ' diy-column-action';
		}
		
		return !empty($classAttributes) ? $this->setAttributes(['class' => $classAttributes]) : '';
	}

	private function getMergeColumnWidth($column, $attributes) {
		$columnLower = strtolower($column);
		if (!empty($attributes['attributes']['column_width'][$columnLower])) {
			return ' width="' . $attributes['attributes']['column_width'][$columnLower] . '"';
		}
		return '';
	}
	
	private function setColumnElements($name, $column_data, $columns) {
		$element = [];
		if (!empty($column_data[$name])) {
			if (!empty($column_data[$name]['all::columns'])) {
				if (true === $column_data[$name]['all::columns']) {
					if (!empty($columns['columns']['lists'])) {
						foreach ($columns['columns']['lists'] as $clickList) {
							$element[$clickList] = true;
						}
					}
				}
			} else {
				foreach ($column_data[$name] as $clicKey) {
					$element[$clicKey] = true;
				}
			}
		}
		
		return $element;
	}
	
	private function setFormulaColumns($columns, $data) {
		return diy_set_formula_columns($columns, $data['attributes']['conditions']['formula']);
	}
	
	public $filter_contents  = [];
	protected $filter_object = [];
	private function body($data = []) {
		$bodyConfig = $this->prepareBodyConfiguration($data);
		$columns = $this->prepareColumnsForBody($data, $bodyConfig);
		$columnElements = $this->extractColumnElements($data['columns'], $data);
		$formulaFields = $this->extractFormulaFields($data);
		
		$dtColumns = $this->buildDataTablesColumns($columns, $columnElements, $formulaFields, $bodyConfig);
		$dtInfo = $this->buildDataTablesInfo($data, $dtColumns, $bodyConfig);
		
		$filterEnabled = $this->setupFiltering($dtInfo, $data, $columnElements, $bodyConfig);
		$filterData = $filterEnabled ? $this->getFilterDataTables() : [];
		
		return $this->generateFinalDataTable($bodyConfig['tableID'], $dtColumns, $dtInfo, $filterData);
	}

	private function prepareBodyConfiguration($data) {
		$attributes = $data['attributes'];
		
		return [
			'name' => $data['name'],
			'attributes' => $attributes,
			'columnData' => $data['columns'],
			'server_side' => $data['attributes']['server_side']['status'],
			'tableID' => $attributes['attributes']['table_id'] ?? null,
			'actions' => !empty($attributes['actions']) ? $attributes['actions'] : false,
			'numbering' => !empty($attributes['numbering']) ? $attributes['numbering'] : false,
			'hiddenColumn' => !empty($data['columns']['hidden_columns']) ? $data['columns']['hidden_columns'] : []
		];
	}

	private function prepareColumnsForBody($data, $config) {
		$columns = $data['columns']['lists'];
		
		if (true === $config['numbering']) {
			$columns = array_merge(['number_lists'], $columns);
		}
		
		if (!empty($config['actions'])) {
			$columns[] = 'action';
		}
		
		return $columns;
	}

	private function extractColumnElements($columnData, $data) {
		return [
			'alignment' => $this->extractBodyAlignment($columnData),
			'sortable' => $this->setColumnElements('sortable', $columnData, $data),
			'searchable' => $this->setColumnElements('searchable', $columnData, $data),
			'clickable' => $this->setColumnElements('clickable', $columnData, $data)
		];
	}

	private function extractBodyAlignment($columnData) {
		$alignment = [];
		
		if (!empty($columnData['align'])) {
			foreach ($columnData['align'] as $align => $col_data) {
				if (true === $col_data['body']) {
					foreach ($col_data['columns'] as $field) {
						$alignment['body'][$field] = $align;
					}
				}
			}
		}
		
		return $alignment;
	}

	private function extractFormulaFields($data) {
		$formula_fields = [];
		
		if (!empty($data['attributes']['conditions']['formula'])) {
			foreach ($data['attributes']['conditions']['formula'] as $formula) {
				$formula_fields[$formula['name']] = $formula['name'];
			}
		}
		
		return $formula_fields;
	}

	private function buildDataTablesColumns($columns, $columnElements, $formulaFields, $config) {
		$dt_columns = [];
		$column_id = $this->prepareServerSideColumn($config, $columns);
		
		foreach ($columns as $column) {
			$columnConfig = $this->createBaseColumnConfig($column, $config['hiddenColumn']);
			
			if ('number_lists' === $column) {
				$dt_columns = array_merge($dt_columns, $this->buildNumberListsColumn($columnConfig, $column_id));
			} elseif (!empty($formulaFields[$column])) {
				$dt_columns[] = $this->buildFormulaColumn($columnConfig, $columnElements);
			} else {
				$dt_columns[] = $this->buildRegularColumn($columnConfig, $columnElements);
			}
		}
		
		return $dt_columns;
	}

	private function prepareServerSideColumn($config, $columns) {
		$column_id = [];
		
		if (false !== $config['server_side']) {
			$firstField = in_array('id', $columns) ? 'id' : $columns[1];
			$column_id['data'] = $firstField;
			$column_id['name'] = $firstField;
		}
		
		return $column_id;
	}

	private function createBaseColumnConfig($column, $hiddenColumns) {
		return [
			'data' => $column,
			'name' => $column,
			'sortable' => false,
			'searchable' => false,
			'class' => in_array($column, $hiddenColumns) ? 'auto-cut-text diy-hide-column' : 'auto-cut-text',
			'onclick' => 'return false'
		];
	}

	private function buildNumberListsColumn($columnConfig, $column_id) {
		$numberColumn = $columnConfig;
		$numberColumn['data'] = 'DT_RowIndex';
		$numberColumn['name'] = 'DT_RowIndex';
		$numberColumn['class'] = 'center un-clickable';
		unset($numberColumn['onclick']);
		
		$columns = [$numberColumn];
		if (!empty($column_id)) {
			$columns[] = $column_id;
		}
		
		return $columns;
	}

	private function buildFormulaColumn($columnConfig, $columnElements) {
		return $this->applyColumnElements($columnConfig, $columnElements, false);
	}

	private function buildRegularColumn($columnConfig, $columnElements) {
		return $this->applyColumnElements($columnConfig, $columnElements, true);
	}

	private function applyColumnElements($columnConfig, $columnElements, $applySortable) {
		$column = $columnConfig['name'];
		
		// Apply alignment
		if (!empty($columnElements['alignment']['body'][$column])) {
			$columnConfig['class'] .= " {$columnElements['alignment']['body'][$column]}";
		}
		
		// Apply sortable (only for regular columns)
		if ($applySortable && !empty($columnElements['sortable'][$column])) {
			$columnConfig['sortable'] = $columnElements['sortable'][$column];
		}
		
		// Apply searchable
		if (!empty($columnElements['searchable'][$column])) {
			$columnConfig['searchable'] = $columnElements['searchable'][$column];
		}
		
		// Apply clickable
		if (!empty($columnElements['clickable'][$column])) {
			unset($columnConfig['onclick']);
			$columnConfig['class'] .= " clickable";
		}
		
		return $columnConfig;
	}

	private function buildDataTablesInfo($data, $dtColumns, $config) {
		$newDataColumns = $this->extractColumnNames($dtColumns);
		
		$dtInfo = [
			'searchable' => [],
			'name' => $config['name']
		];
		
		if (!empty($data['columns']['sortable'])) {
			$dtInfo['sortable'] = $data['columns']['sortable'];
		}
		
		if (!empty($data['attributes']['conditions'])) {
			$dtInfo['conditions'] = $data['attributes']['conditions'];
			$dtInfo['columns'] = $newDataColumns;
		}
		
		if (!empty($data['attributes']['on_load']['display_limit_rows'])) {
			$dtInfo['onload_limit_rows'] = $data['attributes']['on_load']['display_limit_rows'];
		}
		
		if (!empty($data['attributes']['fixed_columns'])) {
			$dtInfo['fixed_columns'] = $data['attributes']['fixed_columns'];
		}
		
		return $dtInfo;
	}

	private function extractColumnNames($dtColumns) {
		$newDataColumns = [];
		
		foreach ($dtColumns as $dtcols) {
			if ('DT_RowIndex' === $dtcols['name']) {
				$newDataColumns[] = 'number_lists';
			} else {
				$newDataColumns[] = $dtcols['name'];
			}
		}
		
		return $newDataColumns;
	}

	private function setupFiltering(&$dtInfo, $data, $columnElements, $config) {
		if (empty($columnElements['searchable'])) {
			return false;
		}
		
		$dtInfo['searchable'] = $data['columns']['searchable'];
		
		if (!empty($data['columns']['filters'])) {
			$this->configureSearchFilters($dtInfo, $data, $config);
		}
		
		return true;
	}

	private function configureSearchFilters(&$dtInfo, $data, $config) {
		$searchData = $this->buildSearchData($data);
		$searchObject = $this->createSearchObject($searchData, $data, $config);
		
		$searchInfo = ['id' => $config['tableID']];
		$searchInfoAttribute = "{$searchInfo['id']}_cdyFILTER";
		
		$this->filter_object = $searchObject;
		$this->filter_contents[$config['tableID']] = array_merge($dtInfo, [
			'id' => $searchInfo['id'],
			'class' => 'dt-button buttons-filter',
			'attributes' => $this->buildModalAttributes($searchInfoAttribute, $config['tableID']),
			'button_label' => '<i class="fa fa-filter"></i> Filter',
			'action_button_removed' => $data['attributes']['buttons_removed'],
			'modal_title' => '<i class="fa fa-filter"></i> &nbsp; Filter',
			'modal_content' => $searchObject->render($searchInfoAttribute, $dtInfo['name'], $data['columns']['filters'])
		]);
	}

	private function buildSearchData($data) {
		return [
			'table_name' => $data['name'],
			'searchable' => $data['columns']['searchable'],
			'columns' => $data['columns']['filters'],
			'relations' => $data['columns']['relations'] ?? [],
			'foreign_keys' => $data['columns']['foreign_keys'] ?? [],
			'filter_groups' => $data['columns']['filter_groups'] ?? null,
			'filter_model' => $data['attributes']['filter_model'] ?? null
		];
	}

	private function createSearchObject($searchData, $data, $config) {
		$dataModel = !empty($data['sql']) ? null : $data['model'];
		$dataSql = !empty($data['sql']) ? $data['sql'] : null;
		$filterQuery = $this->conditions['where'] ?? [];
		
		$searchInfoAttribute = "{$config['tableID']}_cdyFILTER";
		
		return new Search($searchInfoAttribute, $dataModel, $searchData, $dataSql, $this->connection, $filterQuery);
	}

	private function buildModalAttributes($searchInfoAttribute, $tableID) {
		return [
			'id' => $searchInfoAttribute,
			'class' => "modal fade {$tableID}",
			'role' => 'dialog',
			'tabindex' => '-1',
			'aria-hidden' => 'true',
			'aria-controls' => $tableID,
			'aria-labelledby' => $tableID,
			'data-backdrop' => 'static',
			'data-keyboard' => 'true'
		];
	}

	private function generateFinalDataTable($tableID, $dtColumns, $dtInfo, $filterData) {
		$dtColumnsJson = diy_clear_json(json_encode($dtColumns));
		
		// Enhanced method selection with security considerations
		$useServerSide = true;
		
		// Add method info to dtInfo for Scripts.php
		$dtInfo['http_method'] = $this->method;
		$dtInfo['secure_mode'] = $this->secureMode;
		
		return $this->datatables($tableID, $dtColumnsJson, $dtInfo, $useServerSide, $filterData);
	}
	
	private function getFilterDataTables() {
		$requestData = $this->getRequestData();
		
		if (empty($requestData['filters'])) {
			return null;
		}
		
		$inputFilters = $this->extractValidFilterParams($requestData);
		return empty($inputFilters) ? null : '&filters=true&' . implode('&', $inputFilters);
	}

	private function getRequestData() {
		// Support both GET and POST methods
		return 'POST' === $this->method ? $_POST : $_GET;
	}

	private function extractValidFilterParams($requestData = null) {
		if ($requestData === null) {
			$requestData = $this->getRequestData();
		}
		
		$inputFilters = [];
		$excludedParams = $this->getExcludedFilterParams();
		
		foreach ($requestData as $name => $value) {
			if ($this->isValidFilterParam($name, $value, $excludedParams)) {
				$inputFilters[] = "infil[{$name}]={$value}";
			}
		}
		
		return $inputFilters;
	}

	private function getExcludedFilterParams() {
		return [
			'filters', 'renderDataTables', 'draw', 'columns', 
			'order', 'start', 'length', 'search', '_token', '_'
		];
	}

	private function isValidFilterParam($name, $value, $excludedParams) {
		return $name !== 'filters' 
			&& $value !== '' 
			&& !is_array($value) 
			&& !in_array($name, $excludedParams);
	}
	
	private function backgroundColor($attributes = []) {
		if (empty($attributes)) {
			return null;
		}
		
		$tableDataColor = [];
		
		foreach ($attributes as $colorCode => $dataColor) {
			$this->processColorConfiguration($colorCode, $dataColor, $tableDataColor);
		}
		
		return $tableDataColor;
	}

	private function processColorConfiguration($colorCode, $dataColor, &$tableDataColor) {
		$textColor = $this->extractTextColor($dataColor);
		
		if (!empty($dataColor['columns'])) {
			$this->applyColumnColors($colorCode, $dataColor['columns'], $textColor, $tableDataColor);
		}
		
		if (empty($dataColor['columns']) && isset($dataColor['header']) && true === $dataColor['header']) {
			$this->applyHeaderColor($colorCode, $textColor, $tableDataColor);
		}
	}

	private function extractTextColor($dataColor) {
		return !empty($dataColor['text']) ? " color:{$dataColor['text']};" : '';
	}

	private function applyColumnColors($colorCode, $columns, $textColor, &$tableDataColor) {
		foreach ($columns as $columnName) {
			$tableDataColor['columns'][$columnName] = $this->setAttributes([
				'style' => "background-color:{$colorCode} !important;{$textColor}"
			]);
		}
	}

	private function applyHeaderColor($colorCode, $textColor, &$tableDataColor) {
		$tableDataColor['header'] = $this->setAttributes([
			'style' => "background-color:{$colorCode} !important;{$textColor}"
		]);
	}
	
	private function setAttributes($attributes = []) {
		$textAttribute = null;
		if (is_array($attributes)) {
			foreach ($attributes as $key => $value) {
				$textAttribute .= " {$key}=\"{$value}\"";
			}
		}
		
		return $textAttribute;
	}
}