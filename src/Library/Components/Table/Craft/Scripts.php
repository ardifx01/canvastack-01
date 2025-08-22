<?php
namespace Incodiy\Codiy\Library\Components\Table\Craft;

/**
 * Created on 22 May 2021
 * Time Created : 00:29:19
 *
 * @filesource Scripts.php
 *
 * @author    wisnuwidi@incodiy.com - 2021
 * @copyright wisnuwidi
 * @email     wisnuwidi@incodiy.com
 */
 
trait Scripts {
		
		// Runtime registry for datatable context
		private static $datatableRuntime = [];

		public static function setDatatableRuntime(string $tableName, $context): void {
			if (!empty($tableName) && is_object($context)) {
				self::$datatableRuntime[$tableName] = $context;
			}
		}

		public static function getDatatableRuntime(string $tableName) {
			return self::$datatableRuntime[$tableName] ?? null;
		}
	
	// HTTP Configuration
	private $datatablesMode = 'GET';
	private $strictGetUrls  = true;
	private $strictColumns  = true;
	// Note: $secureMode and $method are defined in Builder.php
	
	// Performance Optimization
	private static $configCache = [];
	private static $templateCache = [];
	private $memoryOptimization = true;
	
	// DataTables Configuration Constants
	private const DEFAULT_SCROLL_HEIGHT = 300;
	private const MAX_ROWS_LIMIT = 9999999999;
	private const DEFAULT_SEARCH_DELAY = 500;
	private const DEFAULT_PAGE_LIMITS = [10, 25, 50, 100, 250, 500, 1000];
	private const DEFAULT_ONLOAD_LIMIT = 10;
	
	// Security Constants
	private const ALLOWED_HTTP_METHODS = ['GET', 'POST'];
	private const ALLOWED_OPERATORS = ['=', '==', '===', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];
	private const ALLOWED_TARGETS = ['row', 'cell', 'column'];
	private const ALLOWED_RULES = ['prefix', 'suffix', 'prefix&suffix', 'replace'];
	
	// JavaScript Template Constants
	private const JS_WRAPPER_TEMPLATE = '<script type="text/javascript">jQuery(function($) {%s});%s</script>';
	private const DATATABLE_BASIC_OPTIONS = [
		'searching' => true,
		'processing' => true,
		'retrieve' => false,
		'paginate' => true,
		'responsive' => false,
		'autoWidth' => false,
		'dom' => 'lBfrtip',
		'bDeferRender' => true
	];
	
	/**
	 * Javascript Config for Rendering Datatables
	 *
	 * @param string $attr_id Table ID attribute
	 * @param string $columns Column configurations
	 * @param array $data_info Data table information and settings
	 * @param bool $server_side Enable server-side processing
	 * @param bool|array $filters Filter configurations
	 * @param bool|string|array $custom_link Custom link configurations
	 * @return string Generated JavaScript code
	 * @throws \InvalidArgumentException When invalid parameters provided
	 */
	protected function datatables($attr_id, $columns, $data_info = [], $server_side = false, $filters = false, $custom_link = false) {
		// Validate input parameters
		$this->validateDatatableParameters($attr_id, $columns, $data_info);
		
		// Extract table configuration
		$config = $this->extractTableConfiguration($attr_id, $data_info);
		
		// Build DataTable options
		$options = $this->buildDatatableOptions($attr_id, $columns, $data_info, $server_side, $filters, $custom_link, $config);
		
		// Generate final JavaScript
		$script = $this->generateDatatableScript($attr_id, $options, $config);
		
		// Memory optimization: cleanup large arrays if enabled
		if ($this->memoryOptimization) {
			$this->cleanupMemory($options, $config);
		}
		
		return $script;
	}
	
	/**
	 * Validate datatable input parameters
	 *
	 * @param string $attr_id
	 * @param string $columns
	 * @param array $data_info
	 * @throws \InvalidArgumentException
	 */
	private function validateDatatableParameters($attr_id, $columns, $data_info) {
		if (empty($attr_id) || !is_string($attr_id)) {
			throw new \InvalidArgumentException('Table ID must be a non-empty string');
		}
		
		if (!is_string($columns)) {
			throw new \InvalidArgumentException('Columns must be a string');
		}
		
		if (!is_array($data_info)) {
			throw new \InvalidArgumentException('Data info must be an array');
		}
		
		// Validate HTTP method if specified
		if (!empty($data_info['http_method']) && !in_array(strtoupper($data_info['http_method']), self::ALLOWED_HTTP_METHODS)) {
			throw new \InvalidArgumentException('Invalid HTTP method specified');
		}
	}
	
	/**
	 * Extract and prepare table configuration with caching
	 *
	 * @param string $attr_id
	 * @param array $data_info
	 * @return array
	 */
	private function extractTableConfiguration($attr_id, $data_info) {
		$cacheKey = md5($attr_id . serialize($data_info));
		
		if (isset(self::$configCache[$cacheKey])) {
			return self::$configCache[$cacheKey];
		}
		
		$config = [
			'table_id' => $attr_id,
			'var_table_id' => str_replace('-', '', $attr_id),
			'current_url' => url(diy_current_route()->uri),
			'http_method' => $this->determineHttpMethod($data_info),
			'has_fixed_columns' => !empty($data_info['fixed_columns']),
			'fixed_columns_data' => $data_info['fixed_columns'] ?? null,
			'has_conditions' => !empty($data_info['conditions']['columns']),
			'conditions_data' => $data_info['conditions'] ?? null,
			'table_name' => $data_info['name'] ?? null,
			'columns_data' => $data_info['columns'] ?? []
		];
		
		// Cache configuration for reuse
		self::$configCache[$cacheKey] = $config;
		
		return $config;
	}
	
	/**
	 * Build complete DataTable options
	 *
	 * @param string $attr_id
	 * @param string $columns
	 * @param array $data_info
	 * @param bool $server_side
	 * @param bool|array $filters
	 * @param bool|string|array $custom_link
	 * @param array $config
	 * @return array
	 */
	private function buildDatatableOptions($attr_id, $columns, $data_info, $server_side, $filters, $custom_link, $config) {
		$options = [
			'basic_options' => $this->buildBasicOptions($data_info),
			'buttons' => $this->buildButtonsConfiguration($attr_id),
			'length_menu' => $this->buildLengthMenuConfiguration($data_info),
			'columns' => $columns,
			'server_side' => $server_side,
			'filters' => $filters,
			'custom_link' => $custom_link,
			'conditional_js' => null,
			'ajax_config' => null,
			'init_complete' => null,
			'click_actions' => null,
			'filter_components' => null
		];
		
		// Build conditional columns if exists
		if ($config['has_conditions']) {
			$options['conditional_js'] = $this->conditionalColumns(
				"cody_{$config['var_table_id']}_dt", 
				$config['conditions_data']['columns'], 
				$config['columns_data']
			);
		}
		
		// Build server-side specific configurations
		if ($server_side) {
			$options = array_merge($options, $this->buildServerSideOptions($attr_id, $data_info, $custom_link, $config, $filters));
		}
		
		return $options;
	}
	
	/**
	 * Build basic DataTable options
	 *
	 * @param array $data_info
	 * @return string
	 */
	private function buildBasicOptions($data_info) {
		$options = [];
		
		// Build fixed columns configuration
		if (!empty($data_info['fixed_columns'])) {
			$fixedColumnData = json_encode($data_info['fixed_columns']);
			$options[] = "scrollY:" . self::DEFAULT_SCROLL_HEIGHT;
			$options[] = "scrollX:true";
			$options[] = "scrollCollapse:true";
			$options[] = "fixedColumns:" . $fixedColumnData;
		}
		
		// Add basic configurations
		foreach (self::DATATABLE_BASIC_OPTIONS as $key => $value) {
			if ($key === 'dom') {
				$options[] = "\"{$key}\":\"{$value}\"";
			} else {
				$boolValue = $value ? 'true' : 'false';
				$options[] = "\"{$key}\":{$boolValue}";
			}
		}
		
		// Add search delay
		$options[] = '"searchDelay":' . self::DEFAULT_SEARCH_DELAY;
		
		return implode(',', $options);
	}
	
	/**
	 * Build buttons configuration
	 *
	 * @param string $attr_id
	 * @return string
	 */
	private function buildButtonsConfiguration($attr_id) {
		$buttonConfig = 'exportOptions:{columns:":visible:not(:last-child)"}';
		
		return $this->setButtons($attr_id, [
			'excel|text:"<i class=\"fa fa-external-link\" aria-hidden=\"true\"></i> <u>E</u>xcel"|key:{key:"e",altKey:true}',
			'csv|' . $buttonConfig,
			'pdf|' . $buttonConfig,
			'copy|' . $buttonConfig,
			'print|' . $buttonConfig
		]);
	}
	
	/**
	 * Build length menu configuration
	 *
	 * @param array $data_info
	 * @return string
	 */
	private function buildLengthMenuConfiguration($data_info) {
		$limitRowsData = array_merge(self::DEFAULT_PAGE_LIMITS, [self::MAX_ROWS_LIMIT]);
		$onloadRowsLimit = [self::DEFAULT_ONLOAD_LIMIT];
		
		// Process onload limit rows configuration
		if (!empty($data_info['onload_limit_rows'])) {
			$onloadLimit = $this->processOnloadLimit($data_info['onload_limit_rows']);
			if ($onloadLimit !== null) {
				$onloadRowsLimit = [$onloadLimit];
				// Remove the selected limit from default options to avoid duplication
				$key = array_search($onloadLimit, $limitRowsData);
				if ($key !== false) {
					unset($limitRowsData[$key]);
				}
				$limitRowsData = array_merge($onloadRowsLimit, $limitRowsData);
			}
		}
		
		// Build display labels
		$limitRowsDataString = [];
		foreach ($limitRowsData as $limit) {
			$limitRowsDataString[] = ($limit == self::MAX_ROWS_LIMIT) ? "Show All" : $limit . " Rows";
		}
		
		$lengthMenu = json_encode([$limitRowsData, $limitRowsDataString]);
		return "lengthMenu:{$lengthMenu}";
	}
	
	/**
	 * Process onload limit configuration
	 *
	 * @param mixed $onloadLimit
	 * @return int|null
	 */
	private function processOnloadLimit($onloadLimit) {
		if (is_string($onloadLimit)) {
			if (in_array(strtolower($onloadLimit), ['*', 'all'])) {
				return self::MAX_ROWS_LIMIT;
			}
			return intval($onloadLimit);
		}
		
		if (is_numeric($onloadLimit)) {
			return intval($onloadLimit);
		}
		
		return null;
	}
	
	/**
	 * Build server-side specific options
	 *
	 * @param string $attr_id
	 * @param array $data_info
	 * @param mixed $custom_link
	 * @param array $config
	 * @param bool|array $filters
	 * @return array
	 */
	private function buildServerSideOptions($attr_id, $data_info, $custom_link, $config, $filters = false) {
		$options = [];
		
		// Build AJAX configuration
		$options['ajax_config'] = $this->buildAjaxConfiguration($attr_id, $data_info, $custom_link, $config);
		
		// Build init complete
		$options['init_complete'] = $this->initComplete($attr_id, false);
		
		// Build click actions
		$options['click_actions'] = $this->buildClickActions($attr_id, $config);
		
		// Build filter components if needed
		if ($filters !== false) {
			$options['filter_components'] = $this->buildFilterComponents($attr_id, $data_info, $config);
		}
		
		return $options;
	}
	
	/**
	 * Build AJAX configuration for server-side processing
	 *
	 * @param string $attr_id
	 * @param array $data_info
	 * @param mixed $custom_link
	 * @param array $config
	 * @return string
	 */
	private function buildAjaxConfiguration($attr_id, $data_info, $custom_link, $config) {
		// Determine URL and method
		$httpMethod = $config['http_method'];
		$current_url = $config['current_url'];
		
		// Build link URL
		$diftaURI = "&difta[name]={$config['table_name']}&difta[source]=dynamics";
		$link_url = "renderDataTables=true{$diftaURI}";
		
		if ($custom_link !== false) {
			$link_url = $this->buildCustomLinkUrl($custom_link);
		}
		
		// Build script URI based on HTTP method
		// NOTE: For POST, direct to package ajax endpoint with filter flag in query (bypass resource store)
		$scriptURI = ($httpMethod === 'POST')
			? route('ajax.post') . '?filterDataTables=true'
			: "{$current_url}?{$link_url}";

		
		// Build AJAX configuration based on method
		if ($httpMethod === 'POST') {
			return $this->buildPostAjaxConfig($scriptURI, $config);
		} else {
			return $this->buildGetAjaxConfig($scriptURI, $attr_id);
		}
	}
	
	/**
	 * Build POST AJAX configuration
	 *
	 * @param string $scriptURI
	 * @param array $config
	 * @return string
	 */
	private function buildPostAjaxConfig($scriptURI, $config) {
		$token = csrf_token();
		$tableName = htmlspecialchars($config['table_name'], ENT_QUOTES, 'UTF-8');

		return "ajax:{" .
			"url:'" . route('ajax.post') . "?filterDataTables=true'," .
			"type:'POST'," .
			"headers:{'X-CSRF-TOKEN':'{$token}','Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest','X-Incodiy-Datatables':'1'}," .
			"data: function(d) { " .
				"d.renderDataTables = 'true'; " .
				"d['difta[name]'] = '{$tableName}'; " .
				"d['difta[source]'] = 'dynamics'; " .
				"d.filters = 'true'; " .
				"return d; " .
			"} " .
		"}";
	}
	
	/**
	 * Build GET AJAX configuration
	 *
	 * @param string $scriptURI
	 * @param string $attr_id
	 * @return string
	 */
	private function buildGetAjaxConfig($scriptURI, $attr_id) {
		$ajaxLimitGetURLs = '';
		
		if ($this->strictGetUrls) {
			$idString = str_replace('-', '', $attr_id);
			$strictColumns = $this->strictColumns ? 'true' : 'false';
			$ajaxLimitGetURLs = ",data: function (data) {" .
				"var diyDUDC{$idString} = data; " .
				"deleteUnnecessaryDatatableComponents(diyDUDC{$idString}, {$strictColumns})" .
			"}";
		}
		
		return "ajax:{ url:'{$scriptURI}'{$ajaxLimitGetURLs} }";
	}
	
	/**
	 * Build custom link URL
	 *
	 * @param mixed $custom_link
	 * @return string
	 */
	private function buildCustomLinkUrl($custom_link) {
		if (is_array($custom_link)) {
			$key = htmlspecialchars($custom_link[0], ENT_QUOTES, 'UTF-8');
			$value = htmlspecialchars($custom_link[1], ENT_QUOTES, 'UTF-8');
			return "{$key}={$value}";
		}
		
		$sanitized_link = htmlspecialchars($custom_link, ENT_QUOTES, 'UTF-8');
		return "{$sanitized_link}=true";
	}
	
	/**
	 * Build click actions for table rows
	 *
	 * @param string $attr_id
	 * @param array $config
	 * @return string
	 */
	private function buildClickActions($attr_id, $config) {
		$url_path = htmlspecialchars($config['current_url'], ENT_QUOTES, 'UTF-8');
		$hash = hash_code_id();
		
		return ".on('click','td.clickable', function(){" .
			"var getRLP = $(this).parent('tr').attr('rlp'); " .
			"if(getRLP != false) { " .
				"var _rlp = parseInt(getRLP.replace('{$hash}','')-8*800/80); " .
				"window.location='{$url_path}/'+_rlp+'/edit'; " .
			"} " .
		"});";
	}
	
	/**
	 * Build filter components
	 *
	 * @param string $attr_id
	 * @param array $data_info
	 * @param array $config
	 * @return array
	 */
	private function buildFilterComponents($attr_id, $data_info, $config) {
		$components = [];
		
		// Build filter button
		$components['button'] = "$('div#{$attr_id}_wrapper>.dt-buttons').append('<span class=\"cody_{$attr_id}_diy-dt-filter-box\"></span>')";
		
		// Build filter JS - Use updated diyDataTableFilters (now supports POST body)
		$scriptURI = ($config['http_method'] === 'POST') ? $config['current_url'] : "{$config['current_url']}?renderDataTables=true";
		$components['js'] = $this->filter($attr_id, $scriptURI);  // ✅ Use updated filter method from filter.js
		
		// Build export functionality
		$diftaURI = "&difta[name]={$config['table_name']}&difta[source]=dynamics";
		$exportURI = route('ajax.export') . "?exportDataTables=true{$diftaURI}";
		$connection = !empty($this->connection) ? "::{$this->connection}" : '';
		$components['export'] = $this->export($attr_id . $connection, $exportURI);
		
		return $components;
	}
	
	/**
	 * Generate final DataTable script
	 *
	 * @param string $attr_id
	 * @param array $options
	 * @param array $config
	 * @return string
	 */
	private function generateDatatableScript($attr_id, $options, $config) {
		$varTableID = $config['var_table_id'];
		
		// Build main DataTable initialization
		$mainScript = $this->buildMainDatatableScript($attr_id, $varTableID, $options, $config);
		
		// Build document ready script
		$documentReadyScript = $this->buildDocumentReadyScript($attr_id, $options);
		
		// Combine scripts using template
		return sprintf(
			self::JS_WRAPPER_TEMPLATE,
			$mainScript,
			$documentReadyScript
		);
	}
	
	/**
	 * Build main DataTable script
	 *
	 * @param string $attr_id
	 * @param string $varTableID
	 * @param array $options
	 * @param array $config
	 * @return string
	 */
	private function buildMainDatatableScript($attr_id, $varTableID, $options, $config) {
		$script = "cody_{$varTableID}_dt = $('#{$attr_id}').DataTable({";
		
		// Add responsive row reorder
		$script .= "rowReorder :{selector:'td:nth-child(2)'},responsive: false,";
		
		// Add basic options
		$script .= $options['basic_options'] . ',';
		
		// Add buttons
		$script .= '"buttons":' . $options['buttons'] . ',';
		
		// Add length menu
		$script .= $options['length_menu'] . ',';
		
		// Add server-side specific options
		if ($options['server_side']) {
			$script .= "'serverSide':true,";
			$script .= $options['ajax_config'] . ',';
			
			// Add column definitions
			$colDefs = "columnDefs:[{target:[1],visible:false,searchable:false,className:'control hidden-column'}]";
			// Flexible default order: allow user-specified, else fallback to id/primary/unique, else keep current index
			$orderColumn = $this->buildOrderClause($options['data_info'] ?? [], $options['columns']);
			$script .= "columns:{$options['columns']},{$orderColumn},{$colDefs},";
			
			// Add init complete
			$script .= $options['init_complete'];
			
			// Add conditional columns
			if ($options['conditional_js']) {
				$script .= $options['conditional_js'];
			}
		} else {
			$script .= "columns:{$options['columns']}";
		}
		
		$script .= "})";
		
		// Add click actions
		if (!empty($options['click_actions'])) {
			$script .= $options['click_actions'];
		}
		
		// Add filter button
		if (!empty($options['filter_components']['button'])) {
			$script .= $options['filter_components']['button'];
		}
		
		return $script;
	}
	
	/**
	 * Build document ready script
	 *
	 * @param string $attr_id
	 * @param array $options
	 * @return string
	 */
	// Build flexible order clause: user-specified > id/primary-like > fallback index 1
		private function buildOrderClause($dataInfo, $columnsJson): string {
			$tableName = is_array($dataInfo) && !empty($dataInfo['name']) ? $dataInfo['name'] : 'default';
			$decoded = json_decode($columnsJson, true);
			if (!is_array($decoded)) return "order:[[1,'desc']]";
			
			// Runtime override (per table)
			$rt = self::getDatatableRuntime($tableName) ?? [];
			if (!empty($rt['order']) && is_array($rt['order'])) {
				list($name, $dir) = [$rt['order'][0] ?? null, strtolower($rt['order'][1] ?? 'desc')];
				$idx = $this->findColumnIndexByName($decoded, $name);
				if ($idx !== null) return "order:[[{$idx},'{$dir}']]";
			}
			
			// Fallbacks: id, created_at, updated_at
			$preferred = ['id', 'created_at', 'updated_at'];
			foreach ($preferred as $col) {
				$idx = $this->findColumnIndexByName($decoded, $col);
				if ($idx !== null) return "order:[[{$idx},'desc']]";
			}
			
			// Last resort: keep existing default to second column index 1
			return "order:[[1,'desc']]";
		}

		private function findColumnIndexByName(array $decoded, ?string $name): ?int {
			if (!$name) return null;
			foreach ($decoded as $i => $cfg) {
				if (!empty($cfg['name']) && $cfg['name'] === $name) return (int)$i;
			}
			return null;
		}

		private function buildDocumentReadyScript($attr_id, $options) {
		$scripts = [];
		$scripts[] = "$(document).ready(function() {";
		$scripts[] = "$('#{$attr_id}').wrap('<div class=\"diy-wrapper-table\"></div>');";
		
		// Add fallback functions to ensure filter/export functions are always available
		$scripts[] = $this->generateInlineFallbackFunctions();
		
		// Add filter JS
		if (!empty($options['filter_components']['js'])) {
			$scripts[] = $options['filter_components']['js'] . ';';
		}
		
		// Add export functionality
		if (!empty($options['filter_components']['export'])) {
			$scripts[] = $options['filter_components']['export'] . ';';
		}
		
		// Add fixed column styling
		$scripts[] = "$('.dtfc-fixed-left').last().addClass('last-of-scrool-column-table');";
		
		$scripts[] = "});";
		
		return implode(' ', $scripts);
	}
	
	/**
	 * Generate inline fallback functions for filter and export
	 * 
	 * @return string
	 */
	private function generateInlineFallbackFunctions() {
		return "
		// Fallback functions to ensure filter/export always work
		if (typeof window.diyDataTableFilters === 'undefined') {
			window.diyDataTableFilters = function(id, url, obTable, options) {
				options = options || {};
				console.log('Using fallback diyDataTableFilters for table:', id, 'Options:', options);
				
				// ENHANCED METHOD DETECTION: Check multiple sources for POST method
				var usePostMethod = false;
				
				// Check all possible sources for POST method indication
				if (options && typeof options === 'object') {
					usePostMethod = options.method === 'POST' || 
					               options.secure === true || 
					               options.usePost === true ||
					               options.secureMode === true;
				}
				
				console.log('🔍 Fallback Method Detection Debug for table ' + id + ':', {
					'typeof options': typeof options,
					'options': options,
					'options.method': options && options.method,
					'options.secure': options && options.secure,
					'options.secureMode': options && options.secureMode,
					'Final decision': usePostMethod ? 'POST' : 'GET (default)'
				});
				
				console.log('🎯 Fallback filter method for table ' + id + ':', usePostMethod ? 'POST' : 'GET (default)');
				
				// 🔧 ROBUST EVENT HANDLING: Multiple selectors and aggressive prevention
				var formSelectors = [
					'#' + id + '_cdyFILTERForm',
					'#' + id + '_cdyFILTERmodalBOX', 
					'[id$=\"_cdyFILTERForm\"]',
					'form[id*=\"' + id + '\"]'
				];
				
				// Remove any existing handlers
				$(formSelectors.join(', ')).off('submit.diyFilterFallback');
				
				// Handle filter form submission with aggressive prevention
				$(formSelectors.join(', ')).on('submit.diyFilterFallback', function(event) {
					console.log('🛑 FALLBACK FORM SUBMIT INTERCEPTED for table:', id, 'Form:', $(this).attr('id'));
					
					// AGGRESSIVE PREVENTION of page reload
					event.preventDefault();
					event.stopPropagation(); 
					event.stopImmediatePropagation();
					
					// Prevent default form action
					$(this).attr('action', 'javascript:void(0);');
					
					console.log('🔄 Fallback processing filter for table:', id, 'Using method:', usePostMethod ? 'POST' : 'GET');
					$('#' + id + '_cdyProcessing').show();
					
					if (usePostMethod) {
						// POST METHOD - for security
						var filterData = {};
						$.each($(this).serializeArray(), function(i, field) {
							if (field.name !== 'renderDataTables' && field.name !== 'difta' && field.name !== 'filters' && 
								field.value && field.value !== '' && field.value !== '____-__-__ __:__:__') {
								filterData[field.name] = field.value;
							}
						});
						
						var csrfToken = $('meta[name=\"csrf-token\"]').attr('content') || $('input[name=\"_token\"]').val();
						if (csrfToken) {
							filterData['_token'] = csrfToken;
						} else {
							console.warn('⚠️ No CSRF token found! Falling back to GET method.');
							usePostMethod = false;
						}
						
						if (usePostMethod && obTable && typeof obTable.ajax !== 'undefined') {
							if (!obTable.settings()[0]._originalAjaxData) {
								obTable.settings()[0]._originalAjaxData = obTable.settings()[0].ajax.data || function() { return {}; };
							}
							
							$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }});
							
							var originalDataFn = obTable.settings()[0]._originalAjaxData;
							obTable.settings()[0].ajax.data = function(d) {
								var originalData = typeof originalDataFn === 'function' ? originalDataFn(d) : originalDataFn || {};
								return $.extend({}, originalData, d, filterData);
							};
							
							obTable.settings()[0].ajax.error = function(xhr, error, thrown) {
								if (xhr.status === 419) {
									console.warn('419 CSRF Error - falling back to GET method');
									usePostMethod = false;
									// Retry with GET method
									$(event.target).trigger('submit');
									return;
								}
								$('#' + id + '_cdyProcessing').hide();
								console.error('AJAX Error:', xhr.status, error);
							};
							
							obTable.ajax.reload(function(json) {
								console.log('✅ POST filter applied successfully');
								$('#' + id + '_cdyProcessing').hide();
								$('#' + id + '_cdyFILTER').modal('hide');
							}, false);
							return;
						}
					}
					
					// GET METHOD (original working implementation) - DEFAULT
					console.log('Using GET method (original working implementation)');
					var filterURI = [];
					$.each($(this).serializeArray(), function(i, field) {
						if (field.name !== 'renderDataTables' && field.name !== 'difta' && field.name !== 'filters' && 
							field.value && field.value !== '' && field.value !== '____-__-__ __:__:__') {
							filterURI.push(field.name + '=' + encodeURIComponent(field.value));
						}
					});
					
					var newUrl = url;
					if (filterURI.length > 0) {
						newUrl += (url.indexOf('?') === -1 ? '?' : '&') + filterURI.join('&') + '&filters=true';
					}
					
					try {
						obTable.ajax.url(newUrl).load(function(json) {
							console.log('✅ Fallback GET filter applied successfully for table:', id);
							$('#' + id + '_cdyProcessing').hide();
							$('#' + id + '_cdyFILTER').modal('hide');
						}, function(xhr, error, thrown) {
							console.error('❌ Fallback GET filter error for table:', id, xhr.status, error);
							$('#' + id + '_cdyProcessing').hide();
							alert('Filter error: ' + xhr.status + ' ' + xhr.statusText);
						});
					} catch (e) {
						console.error('❌ Exception in fallback GET filter for table:', id, e);
						$('#' + id + '_cdyProcessing').hide();
					}
					
					// Explicit return false as additional safety
					return false;
				});
			};
		}
		
		if (typeof window.diyDataTableExports === 'undefined') {
			window.diyDataTableExports = function(tableId, exportURL) {
				console.log('Using fallback diyDataTableExports for table:', tableId);
				
				$(document).off('click', '#exportFilterButton' + tableId).on('click', '#exportFilterButton' + tableId, function() {
					var button = $(this);
					var originalText = button.html();
					button.prop('disabled', true).html('<i class=\"fa fa-spinner fa-spin\"></i> Exporting...');
					
					setTimeout(function() {
						window.open(exportURL, '_blank');
						button.prop('disabled', false).html(originalText);
					}, 500);
				});
			};
		}";
	}

	/**
	 * Determine HTTP method based on data info and fallback logic
	 * 
	 * @param array $data_info
	 * @return string
	 */
	private function determineHttpMethod($data_info) {
		// Priority order: data_info method > class method > default GET
		if (!empty($data_info['http_method'])) {
			return strtoupper($data_info['http_method']);
		}
		
		if (!empty($this->method)) {
			return strtoupper($this->method);
		}
		
		// Check if secure mode is enabled
		if (!empty($data_info['secure_mode']) && $data_info['secure_mode'] === true) {
			return 'POST';
		}
		
		// Default to GET for backward compatibility
		return 'GET';
	}
	
	private function getJsContainMatch($data, $match_contained = null) {
		if ('!=' === $match_contained || '!==' === $match_contained) $match = false;
		if ('==' === $match_contained || '===' === $match_contained) $match = true;
		
		if (true  == $match) return ":contains(\"{$data}\")";
		if (false == $match) return ":not(:contains(\"{$data}\"))";		
	}
	
	/**
	 * Generate conditional columns JavaScript with enhanced security
	 *
	 * @param string $tableIdentity
	 * @param array $data
	 * @param array $columns
	 * @return string|null
	 */
	private function conditionalColumns($tableIdentity, $data, $columns) {
		if (empty($data) || !is_array($data) || !is_array($columns)) {
			return null;
		}
		
		// Sanitize table identity
		$tableIdentity = preg_replace('/[^a-zA-Z0-9_]/', '_', $tableIdentity);
		
		// Build column index mapping
		$icols = $this->buildColumnMapping($columns);
		
		// Process and validate conditions
		$processedData = $this->processConditionalData($data, $icols);
		
		if (empty($processedData)) {
			return null;
		}
		
		// Generate JavaScript
		return $this->generateConditionalJS($processedData);
	}
	
	/**
	 * Build column mapping for safe index access
	 *
	 * @param array $columns
	 * @return array
	 */
	private function buildColumnMapping($columns) {
		$icols = [];
		foreach ($columns as $i => $v) {
			// Sanitize column names to prevent XSS
			$sanitizedColumn = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
			$icols[$sanitizedColumn] = intval($i);
		}
		return $icols;
	}
	
	/**
	 * Process and validate conditions
	 *
	 * @param array $data
	 * @param array $icols
	 * @return array
	 */
	private function processConditionalData($data, $icols) {
		$processedData = [];
		
		foreach ($data as $idx => $condition) {
			$processedCondition = $this->validateAndSanitizeCondition($condition, $icols);
			if ($processedCondition !== null) {
				$processedData[] = $processedCondition;
			}
		}
		
		return $processedData;
	}
	
	/**
	 * Validate and sanitize individual condition
	 *
	 * @param array $condition
	 * @param array $icols
	 * @return array|null
	 */
	private function validateAndSanitizeCondition($condition, $icols) {
		// Validate required fields
		if (empty($condition['logic_operator']) || empty($condition['field_name'])) {
			return null;
		}
		
		// Validate operator
		if (!in_array($condition['logic_operator'], self::ALLOWED_OPERATORS)) {
			return null;
		}
		
		// Validate target
		$fieldTarget = $condition['field_target'] ?? 'row';
		if (!in_array($fieldTarget, self::ALLOWED_TARGETS)) {
			return null;
		}
		
		// Validate rule
		$rule = $condition['rule'] ?? 'replace';
		if (!in_array($rule, self::ALLOWED_RULES)) {
			return null;
		}
		
		// Sanitize field names
		$fieldName = htmlspecialchars($condition['field_name'], ENT_QUOTES, 'UTF-8');
		$sanitizedTarget = htmlspecialchars($fieldTarget, ENT_QUOTES, 'UTF-8');
		
		// Build node mapping
		$node = [
			'field_name' => $icols[$fieldName] ?? null,
			'field_target' => isset($icols[$sanitizedTarget]) ? $icols[$sanitizedTarget] : null
		];
		
		return [
			'logic_operator' => $condition['logic_operator'],
			'field_name' => $fieldName,
			'field_target' => $sanitizedTarget,
			'rule' => htmlspecialchars($rule, ENT_QUOTES, 'UTF-8'),
			'action' => $this->sanitizeAction($condition['action'] ?? '', $rule),
			'value' => $this->sanitizeValue($condition['value'] ?? ''),
			'node' => $node
		];
	}
	
	/**
	 * Sanitize action value based on rule type
	 *
	 * @param mixed $action
	 * @param string $rule
	 * @return mixed
	 */
	private function sanitizeAction($action, $rule) {
		if (is_array($action)) {
			return array_map(function($item) {
				return htmlspecialchars($item, ENT_QUOTES, 'UTF-8');
			}, $action);
		}
		
		// For CSS properties, allow only safe values
		if (in_array($rule, ['background-color', 'color', 'font-weight'])) {
			return preg_replace('/[^a-zA-Z0-9\-#\s]/', '', $action);
		}
		
		return htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
	}
	
	/**
	 * Sanitize condition value
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function sanitizeValue($value) {
		if (is_array($value)) {
			return array_map(function($item) {
				return htmlspecialchars($item, ENT_QUOTES, 'UTF-8');
			}, $value);
		}
		
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
	
	/**
	 * Generate conditional JavaScript
	 *
	 * @param array $processedData
	 * @return string
	 */
	private function generateConditionalJS($processedData) {
		$js = ", 'createdRow': function(row, data, dataIndex, cells) {";
		
		foreach ($processedData as $condition) {
			$js .= $this->buildConditionLogic($condition);
			$js .= $this->buildConditionActions($condition);
			$js .= "}";
		}
		
		$js .= "}";
		return $js;
	}
	
	/**
	 * Build condition logic part
	 *
	 * @param array $condition
	 * @return string
	 */
	private function buildConditionLogic($condition) {
		$operator = $condition['logic_operator'];
		$fieldName = $condition['field_name'];
		$value = $condition['value'];
		
		if (in_array($operator, ['=', '==', '===', '<', '<=', '>', '>='])) {
			return "if (data.{$fieldName} {$operator} '{$value}') {";
		}
		
		// Handle LIKE operators
		$isNot = (strpos($operator, 'NOT') === 0) ? '!' : '';
		
		if (is_array($value)) {
			$conditions = [];
			foreach ($value as $val) {
				$conditions[] = "{$isNot}~data.{$fieldName}.indexOf('{$val}')";
			}
			$conditionStr = implode(' && ', $conditions);
		} else {
			$conditionStr = "{$isNot}~data.{$fieldName}.indexOf('{$value}')";
		}
		
		return "if ({$conditionStr}) {";
	}
	
	/**
	 * Build condition actions part
	 *
	 * @param array $condition
	 * @return string
	 */
	private function buildConditionActions($condition) {
		$target = $condition['field_target'];
		$rule = $condition['rule'];
		$action = $condition['action'];
		$node = $condition['node'];
		
		switch ($target) {
			case 'row':
				return $this->buildRowActions($rule, $action);
			
			case 'cell':
				return $this->buildCellActions($rule, $action, $node, $condition['field_name']);
			
			case 'column':
				return $this->buildColumnActions($rule, $action, $node, $condition['field_name']);
			
			default:
				return $this->buildCustomTargetActions($rule, $action, $node);
		}
	}
	
	/**
	 * Build row-level actions
	 *
	 * @param string $rule
	 * @param mixed $action
	 * @return string
	 */
	private function buildRowActions($rule, $action) {
		if ($rule === 'replace') {
			return "$(row).children('td').text('{$action}');";
		}
		
		return "$(row).children('td').css({'{$rule}': '{$action}'});";
	}
	
	/**
	 * Build cell-level actions
	 *
	 * @param string $rule
	 * @param mixed $action
	 * @param array $node
	 * @param string $fieldName
	 * @return string
	 */
	private function buildCellActions($rule, $action, $node, $fieldName) {
		$cellSelector = "$(cells[\"{$node['field_name']}\"])";
		
		switch ($rule) {
			case 'prefix':
				return "{$cellSelector}.text(\"{$action}\" + data.{$fieldName});";
			
			case 'suffix':
				return "{$cellSelector}.text(data.{$fieldName} + \"{$action}\");";
			
			case 'prefix&suffix':
				if (is_array($action) && count($action) >= 2) {
					return "{$cellSelector}.text(\"{$action[0]}\" + data.{$fieldName} + \"{$action[1]}\");";
				}
				return '';
			
			case 'replace':
				return $this->buildReplaceAction($cellSelector, $action);
			
			default:
				return "{$cellSelector}.css({'{$rule}': '{$action}'});";
		}
	}
	
	/**
	 * Build column-level actions
	 *
	 * @param string $rule
	 * @param mixed $action
	 * @param array $node
	 * @param string $fieldName
	 * @return string
	 */
	private function buildColumnActions($rule, $action, $node, $fieldName) {
		// Column actions are similar to cell actions
		return $this->buildCellActions($rule, $action, $node, $fieldName);
	}
	
	/**
	 * Build custom target actions
	 *
	 * @param string $rule
	 * @param mixed $action
	 * @param array $node
	 * @return string
	 */
	private function buildCustomTargetActions($rule, $action, $node) {
		if (empty($node['field_target'])) {
			return '';
		}
		
		$cellSelector = "$(cells[\"{$node['field_target']}\"])";
		
		if ($rule === 'replace') {
			return $this->buildReplaceAction($cellSelector, $action);
		}
		
		return "{$cellSelector}.css({'{$rule}': '{$action}'});";
	}
	
	/**
	 * Build replace action JavaScript
	 *
	 * @param string $cellSelector
	 * @param mixed $action
	 * @return string
	 */
	private function buildReplaceAction($cellSelector, $action) {
		switch ($action) {
			case 'integer':
				return "{$cellSelector}.text(parseInt({$cellSelector}.text()));";
			
			case 'float':
				return "{$cellSelector}.text(parseFloat({$cellSelector}.text()).toFixed(2));";
			
			default:
				if (strpos($action, 'float|') === 0) {
					$parts = explode('|', $action);
					$precision = intval($parts[1] ?? 2);
					return "{$cellSelector}.text(parseFloat({$cellSelector}.text()).toFixed({$precision}));";
				}
				
				return "{$cellSelector}.text('{$action}');";
		}
	}



	/**
	 * Generate filter button HTML
	 *
	 * @param array $data Filter configuration
	 * @return string|false Filter button HTML or false
	 */
	protected function filterButton(array $data) {
		if (!$this->isFilterEnabled($data)) {
			return false;
		}
		
		$btn_class = $this->getFilterButtonClass($data);
		$attributes = $this->buildFilterButtonAttributes($data);
		
		return sprintf(
			'<button type="button" class="%s %s" %s>%s</button>',
			$btn_class,
			$data['id'],
			$attributes,
			$data['button_label']  // Remove htmlspecialchars for HTML content like icons
		);
	}
	
	/**
	 * Check if filter is enabled
	 *
	 * @param array $data
	 * @return bool
	 */
	private function isFilterEnabled(array $data) {
		if (empty($data['searchable'])) {
			return false;
		}
		
		if (!empty($data['searchable']['all::columns']) && 
			false === $data['searchable']['all::columns']) {
			return false;
		}
		
		return $data['searchable'] !== false && !empty($data['class']);
	}
	
	/**
	 * Get filter button CSS class
	 *
	 * @param array $data
	 * @return string
	 */
	private function getFilterButtonClass(array $data) {
		return $data['class'] ?? 'btn btn-primary btn-flat btn-lg mt-3';
	}
	
	/**
	 * Build filter button attributes
	 *
	 * @param array $data
	 * @return string
	 */
	private function buildFilterButtonAttributes(array $data) {
		$id = htmlspecialchars($data['id'], ENT_QUOTES, 'UTF-8');
		return "data-toggle=\"modal\" data-target=\".{$id}\"";
	}

	/**
	 * Generate filter modal box HTML
	 *
	 * @param array $data Modal configuration
	 * @return string|false Modal HTML or false
	 */
	protected function filterModalbox(array $data) {
		if (!$this->isFilterEnabled($data) || empty($data['modal_content']['html'])) {
			return false;
		}
		
		$modalConfig = $this->buildModalConfiguration($data);
		return $this->generateModalHTML($modalConfig);
	}
	
	/**
	 * Build modal configuration array
	 *
	 * @param array $data
	 * @return array
	 */
	private function buildModalConfiguration(array $data) {
		// Safe URL generation with error handling
		$currentUrl = '#';
		
		// Check if Laravel facades are properly initialized
		if ($this->isLaravelFacadeAvailable()) {
			try {
				if (function_exists('diy_current_route') && function_exists('url')) {
					$route = diy_current_route();
					if ($route && isset($route->uri)) {
						$currentUrl = url($route->uri);
					}
				}
			} catch (Exception $e) {
				// Ignore facade errors and use fallbacks
			} catch (Error $e) {
				// Handle PHP7+ Error for facade calls
			} catch (Throwable $e) {
				// Handle any other errors
			}
		}
		
		// Use fallback URL if Laravel routes not available
		if ($currentUrl === '#' && isset($_SERVER['REQUEST_URI'])) {
			$currentUrl = $_SERVER['REQUEST_URI'];
		}

		return [
			'id' => htmlspecialchars($data['id'], ENT_QUOTES, 'UTF-8'),
			'title' => $data['modal_title'] ?? '',  // Remove htmlspecialchars for HTML content like icons
			'name' => htmlspecialchars($data['modal_content']['name'] ?? '', ENT_QUOTES, 'UTF-8'),
			'html' => $data['modal_content']['html'],
			'attributes' => $this->buildModalAttributes($data['attributes'] ?? []),
			'current_url' => htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8')
		];
	}
	
	/**
	 * Build modal HTML attributes string
	 *
	 * @param array $attributes
	 * @return string
	 */
	private function buildModalAttributes(array $attributes) {
		$attributeString = '';
		foreach ($attributes as $key => $value) {
			$key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
			$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
			$attributeString .= " {$key}=\"{$value}\"";
		}
		return $attributeString;
	}
	
	/**
	 * Check if Laravel facades are available and properly initialized
	 *
	 * @return bool
	 */
	private function isLaravelFacadeAvailable() {
		// Check if Laravel app is initialized
		if (!class_exists('\Illuminate\Support\Facades\Facade')) {
			return false;
		}
		
		try {
			// Try to access facade root
			$facadeRoot = \Illuminate\Support\Facades\Facade::getFacadeApplication();
			return $facadeRoot !== null;
		} catch (Exception $e) {
			return false;
		} catch (Error $e) {
			return false;
		} catch (Throwable $e) {
			return false;
		}
	}
	
	/**
	 * Generate complete modal HTML structure
	 *
	 * @param array $config
	 * @return string
	 */
	private function generateModalHTML(array $config) {
		$modalTemplate = '
			<div class="modal fade %s" role="dialog"%s>
				<div class="modal-dialog modal-lg">
					<div class="modal-content">
						<div class="modal-header">
							<h4 class="modal-title">%s</h4>
							<button type="button" class="close" data-dismiss="modal">&times;</button>
						</div>
						<form name="%s" method="POST" action="%s" class="diy-filter-form" data-ajax="true">
							%s
						</form>
					</div>
				</div>
			</div>';
		
		return sprintf(
			$modalTemplate,
			$config['id'],
			$config['attributes'],
			$config['title'],
			$config['name'],
			$config['current_url'],
			$config['html']
		);
	}

	/**
	 * Memory cleanup method
	 *
	 * @param array $options
	 * @param array $config
	 * @return void
	 */
	private function cleanupMemory(&$options, &$config) {
		// Unset large arrays that are no longer needed
		unset(
			$options['conditional_js'],
			$options['filter_components'],
			$config['conditions_data'],
			$config['columns_data']
		);
		
		// Force garbage collection if memory usage is high
		if (memory_get_usage() > (50 * 1024 * 1024)) { // 50MB threshold
			gc_collect_cycles();
		}
	}
	
	/**
	 * Get cached template or generate new one
	 *
	 * @param string $templateKey
	 * @param callable $generator
	 * @return string
	 */
	private function getCachedTemplate($templateKey, callable $generator) {
		if (!isset(self::$templateCache[$templateKey])) {
			self::$templateCache[$templateKey] = $generator();
		}
		
		return self::$templateCache[$templateKey];
	}
	
	/**
	 * Clear all caches (useful for long-running processes)
	 *
	 * @return void
	 */
	public static function clearCache() {
		self::$configCache = [];
		self::$templateCache = [];
	}
	
	/**
	 * Get performance statistics
	 *
	 * @return array
	 */
	public static function getPerformanceStats() {
		return [
			'config_cache_size' => count(self::$configCache),
			'template_cache_size' => count(self::$templateCache),
			'memory_usage' => memory_get_usage(true),
			'peak_memory' => memory_get_peak_usage(true)
		];
	}

	/**
	 * Set Buttons configuration for DataTables
	 * 
	 * @param string $id Table ID
	 * @param array $button_sets Button configuration sets
	 * @return string JSON button configuration
	 */
	private function setButtons($id, $button_sets = []) {
		$buttons = [];
		
		foreach ($button_sets as $button) {
			$button = trim($button);
			$option = null;
			$options = [];
			
			// Parse button configuration with pipe separator
			if (diy_string_contained($button, '|')) {
				$splits = explode('|', $button);
				foreach ($splits as $split) {
					if (diy_string_contained($split, ':')) {
						$options[] = $split;
					} else {
						$button = $split;
					}
				}
			}
			
			// Build options string
			if (!empty($options)) {
				$option = implode(',', $options);
			}
			
			// Add button configuration
			$buttons[] = '{extend:"' . htmlspecialchars($button, ENT_QUOTES, 'UTF-8') . '"' . 
						 ($option ? ', ' . $option : '') . '}';
		}
		
		return '[' . implode(',', $buttons) . ']';
	}

	/**
	 * Initialize complete callback for DataTable
	 * 
	 * @param string $id Table ID
	 * @param string|bool $location Footer location or false to remove footer
	 * @return string JavaScript initComplete configuration
	 */
	private function initComplete($id, $location = 'footer') {
		if (false === $location) {
			return "initComplete: function() {document.getElementById('{$id}').deleteTFoot();}";
		}
		
		if (true === $location) {
			$location = 'footer';
		}
		
		$js  = "initComplete: function() {";
			$js .= "this.api().columns().every(function(n) {";
				$js .= "if (n > 1) {";
					$js .= "var column = this;";
					$js .= "var input  = document.createElement(\"input\");";
					$js .= "$(input).attr({";
						$js .= "'class':'form-control',";
						$js .= "'placeholder': 'search'";
					$js .= "}).appendTo($(column.{$location}()).empty()).on('change', function () {";
						$js .= "column.search($(this).val(), false, false, true).draw();";
					$js .= "});";
				$js .= "}";
			$js .= "});";
		$js .= "}";
		
		return $js;
	}

	/**
	 * Generate filter JavaScript
	 * 
	 * @param string $id Table ID
	 * @param string $url Filter URL
	 * @return string Filter JavaScript
	 */
	private function filter($id, $url) {
		$varTableID = str_replace('-', '', $id);
		
		// Comprehensive method detection and options building
		$options = [];
		
		if ($this->secureMode || $this->method === 'POST') {
			$options[] = "method: 'POST'";
			$options[] = "secure: true";
			$options[] = "secureMode: true";
		}
		
		// Add debugging information
		$options[] = "debug: true";
		$options[] = "tableId: '{$id}'";
		$options[] = "currentMethod: '{$this->method}'";
		$options[] = "secureMode: " . ($this->secureMode ? 'true' : 'false');
		
		$optionsStr = empty($options) ? '{}' : '{' . implode(', ', $options) . '}';
		
		$jsCall = "diyDataTableFilters('{$id}', '{$url}', cody_{$varTableID}_dt, {$optionsStr});";
		
		// Add debugging for method detection - fix syntax
		$secureModeStr = $this->secureMode ? 'true' : 'false';
		$debugInfo = "console.log('🏗️ Filter setup for table {$id}:', {method: '{$this->method}', secureMode: {$secureModeStr}, options: {$optionsStr}});";
		
		return $debugInfo . "\n" . $jsCall;
	}

	/**
	 * Generate export functionality
	 * 
	 * @param string $id Table ID with connection
	 * @param string $exportURI Export URL
	 * @return string Export JavaScript
	 */
	private function export($id, $exportURI) {
		$tableId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
		$safeURI = htmlspecialchars($exportURI, ENT_QUOTES, 'UTF-8');
		
		// Simplified approach - just call the function, assume it's loaded
		return "diyDataTableExports('{$tableId}', '{$safeURI}');";
	}

	/**
	 * Generate AJAX filter JavaScript handler
	 * 
	 * @param string $tableId Table ID
	 * @return string AJAX filter JavaScript
	 */
	private function ajaxFilterHandler($tableId) {
		$varTableID = str_replace('-', '', $tableId);
		$js = "
		// AJAX Filter Handler for {$tableId}
		$(document).on('submit', '.diy-filter-form', function(e) {
			e.preventDefault();
			
			var form = $(this);
			var tableId = form.find('.diy-ajax-filter-btn').data('table-id');
			var dataTable = cody_{$varTableID}_dt;
			
			// Get form data
			var formData = form.serialize();
			
			// Add CSRF token if available
			if (typeof $('meta[name=\"csrf-token\"]').attr('content') !== 'undefined') {
				formData += '&_token=' + $('meta[name=\"csrf-token\"]').attr('content');
			}
			
			// Show loading indicator
			form.find('.diy-ajax-filter-btn').prop('disabled', true).html('<i class=\"fa fa-spinner fa-spin\"></i> Filtering...');
			
			// Apply server-side filter
			if (dataTable && typeof dataTable.ajax !== 'undefined') {
				// Convert form data to object for POST body
				var filterData = {};
				$.each(form.serializeArray(), function(i, field) {
					filterData[field.name] = field.value;
				});
				
				// Add CSRF token to filter data
				if (typeof $('meta[name=\"csrf-token\"]').attr('content') !== 'undefined') {
					filterData['_token'] = $('meta[name=\"csrf-token\"]').attr('content');
				}
				
				// Store original data function if not already stored
				if (!dataTable.settings()[0]._originalAjaxData) {
					dataTable.settings()[0]._originalAjaxData = dataTable.settings()[0].ajax.data || function() { return {}; };
				}
				
				// Update DataTable ajax data function to include filter data
				var originalDataFn = dataTable.settings()[0]._originalAjaxData;
				dataTable.settings()[0].ajax.data = function(d) {
					// Get original DataTable data (draw, start, length, etc.)
					var originalData = typeof originalDataFn === 'function' ? originalDataFn(d) : originalDataFn || {};
					
					// Merge DataTable params with filter data
					return $.extend({}, originalData, d, filterData);
				};
				
				// Reload DataTable with new filter data
				dataTable.ajax.reload(function() {
					// Close modal and reset button
					form.closest('.modal').modal('hide');
					form.find('.diy-ajax-filter-btn').prop('disabled', false).html('<i class=\"fa fa-filter\"></i> &nbsp; Filter Data');
				});
			} else {
				// For client-side processing - apply custom filter
				dataTable.draw();
				form.closest('.modal').modal('hide');
				form.find('.diy-ajax-filter-btn').prop('disabled', false).html('<i class=\"fa fa-filter\"></i> &nbsp; Filter Data');
			}
		});
		
		// Reset filter handler
		$(document).on('click', '[id*=\"-cancel\"]', function() {
			var tableId = '{$tableId}';
			var dataTable = cody_{$varTableID}_dt;
			
			if (dataTable && typeof dataTable.ajax !== 'undefined') {
				// Reset to original data function
				if (dataTable.settings()[0]._originalAjaxData) {
					dataTable.settings()[0].ajax.data = dataTable.settings()[0]._originalAjaxData;
				}
				
				// Reload table without filters
				dataTable.ajax.reload();
			} else {
				// Clear all filters for client-side
				dataTable.search('').columns().search('').draw();
			}
		});";
		
		return $js;
	}
	
	// Note: setMethod, setSecureMode, getMethod, and isSecureMode methods are defined in Builder.php
}
