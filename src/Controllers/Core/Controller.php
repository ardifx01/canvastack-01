<?php
namespace Incodiy\Codiy\Controllers\Core;

use Illuminate\Routing\Controller as BaseController;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;

use Incodiy\Codiy\Controllers\Core\Craft\View;
use Incodiy\Codiy\Controllers\Core\Craft\Action;
use Incodiy\Codiy\Controllers\Core\Craft\Scripts;
use Incodiy\Codiy\Controllers\Core\Craft\Session;

use Incodiy\Codiy\Controllers\Core\Craft\Components\MetaTags;
use Incodiy\Codiy\Controllers\Core\Craft\Components\Template;
use Incodiy\Codiy\Controllers\Core\Craft\Components\Form;
use Incodiy\Codiy\Controllers\Core\Craft\Components\Table;
use Incodiy\Codiy\Controllers\Core\Craft\Components\Chart;
use Incodiy\Codiy\Controllers\Core\Craft\Components\Email;

use Incodiy\Codiy\Controllers\Core\Craft\Includes\FileUpload;
use Incodiy\Codiy\Controllers\Core\Craft\Includes\RouteInfo;

/**
 * Bismillahirrahmanirrahiim
 * 
 * In the name of ALLAH SWT,
 * Alhamdulillah because of Allah SWT, this code succesfuly created piece by piece.
 * 
 * Base Controller,
 * 
 * First Created on Mar 29, 2017
 * Time Created : 4:58:17 PM
 * 
 * Re-Created on 10 Mar 2021
 * Time Created : 13:23:43
 *
 * @filesource Controller.php
 *            
 * @author    wisnuwidi@incodiy.com - 2021
 * @copyright wisnuwidi
 * @email     wisnuwidi@incodiy.com
 */
class Controller extends BaseController {
	
	use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
	use MetaTags, Template;
	use Scripts, View, Session;
	use Form, FileUpload, RouteInfo;
	use Table;
	use Chart;
	use Email;
	
	public $data         = [];
	public $session_auth = [];
	public $getLogin     = true;
	public $rootPage     = 'home';
	public $adminPage    = 'dashboard';
	public $connection;
	
	private $plugins     = [];
	protected $model_class = null; // Changed to protected for child access
	
	/**
	 * Constructor
	 * 
	 * @param boolean $model
	 * @param boolean $route_page
	 * @param array $filters
	 */
	public function __construct($model = false, $route_page = false) {
		diy_memory(false);
		
		$this->init_model($model);
		$this->dataCollections();
		
		if (false !== $route_page) $this->set_route_page($route_page);
	}
	
	private function init_model($model = false) {
		if (false !== $model) {
			$routelists  = ['index', 'create', 'edit'];
			$currentPage = last(explode('.', current_route()));
			
			if (in_array($currentPage, $routelists)) {
				$this->model_class = $model;
				$modelClass        = new $model();
				$this->connection  = $modelClass->getConnectionName();
			} else {
				$this->model($model);
			}
			
			$this->model_class = $model;
		}
		
		if (!empty($this->model_class)) {
			$this->model_class_path[$this->model_class] = $this->model_class;
		}
	}
	
	private function dataCollections() {
		$this->components();
		$this->autoInjectModelToComponents(); // Auto-inject model after components initialized
		$this->getHiddenFields();
		$this->getExcludeFields();
		
		$this->setDataValues('content_page', []);
	}
	
	/**
	 * Auto-inject model class to components that need it
	 * This eliminates the need for manual setup calls across components
	 * 
	 * Phase 2: Extended Component Integration
	 * Phase 3: Advanced Features with Relationship Auto-Discovery
	 */
	private function autoInjectModelToComponents() {
		if (!empty($this->model_class)) {
			// Auto-inject to Table component if it exists
			if (isset($this->table)) {
				$this->table->model($this->model_class);
				
				// Phase 3: Auto-discover and setup common relationships
				$this->autoDiscoverRelationships();
				
				\Log::debug("🔧 Auto-injected model to Table component", [
					'model' => $this->model_class,
					'component' => 'table',
					'auto_discovery' => 'enabled'
				]);
			}
			
			// Auto-inject to Form component if it exists
			if (isset($this->form)) {
				// Form component expects model instance, not class string
				// Skip auto-injection for Form as it requires more context (routes, etc.)
				// Form models are typically set manually in create/edit methods
				\Log::debug("ℹ️ Skipping Form auto-injection (requires manual setup)", [
					'model' => $this->model_class,
					'component' => 'form',
					'reason' => 'Form requires route context and specific model instances'
				]);
			}
			
			// Chart component doesn't need model auto-injection (data-driven)
			// Email component doesn't need model auto-injection (template-driven)
		}
	}
	
	/**
	 * Phase 3: Auto-discover common relationships for the model
	 * This reduces boilerplate for standard relationships like user->group
	 * 
	 * Performance Optimized: Uses static cache and lazy loading
	 */
	private function autoDiscoverRelationships() {
		if (!isset($this->table) || empty($this->model_class)) {
			return;
		}
		
		// Performance optimization: Use static cache for discovered relationships
		static $relationshipCache = [];
		$cacheKey = $this->model_class;
		
		if (isset($relationshipCache[$cacheKey])) {
			$discoveredRelations = $relationshipCache[$cacheKey];
			\Log::debug("🚀 Using cached relationship discovery", [
				'model' => $this->model_class,
				'cached_relations' => array_keys($discoveredRelations)
			]);
		} else {
			try {
				// Lazy loading: Only create model instance when needed
				$modelName = class_basename($this->model_class);
				$discoveredRelations = [];
				
				// User model: auto-discover group relationship
				if ($modelName === 'User') {
					// Check if method exists without instantiating (performance optimization)
					if (method_exists($this->model_class, 'group')) {
						$discoveredRelations['group'] = [
							'method' => 'group',
							'columns' => ['group_info', 'group_name', 'group_alias'],
							'key_relations' => [
								'base_user_group.user_id' => 'users.id',
								'base_group.id' => 'base_user_group.group_id'
							]
						];
					}
				}
				
				// Cache the results for future use
				$relationshipCache[$cacheKey] = $discoveredRelations;
				
			} catch (\Throwable $e) {
				\Log::warning("⚠️ Failed to auto-discover relationships", [
					'model' => $this->model_class,
					'error' => $e->getMessage()
				]);
				return;
			}
		}
		
		// Apply discovered relationships
		foreach ($discoveredRelations as $relation => $config) {
			// Lazy instantiation: Only create model when actually applying relationships
			if (!empty($discoveredRelations)) {
				try {
					$modelInstance = new $this->model_class();
					
					// Setup relationship with key relations if provided
					if (isset($config['key_relations'])) {
						foreach ($config['columns'] as $column) {
							$this->table->relations($modelInstance, $relation, $column, $config['key_relations']);
						}
					}
					
					// Enable the relationship
					$this->table->useRelation($relation);
					
					\Log::debug("🔍 Auto-discovered relationship", [
						'model' => $this->model_class,
						'relation' => $relation,
						'columns' => $config['columns'],
						'performance' => 'optimized'
					]);
					
					break; // Only instantiate once for all relationships
				} catch (\Throwable $e) {
					\Log::warning("⚠️ Failed to apply auto-discovered relationship", [
						'model' => $this->model_class,
						'relation' => $relation,
						'error' => $e->getMessage()
					]);
				}
			}
		}
	}
	
	/**
	 * Initiate All Registered Plugin Components 
	 * 		=> from app\Http\Controllers\Core\Craft\Components
	 * 		=> data collection setting in config\diy.registers
	 */
	private function components() {
		if (!empty(diy_config('plugins', 'registers'))) {
			foreach (diy_config('plugins', 'registers') as $plugin) {
				$initiate = "init{$plugin}";
				$this->{$initiate}();
			}
			
			$this->setDataValues('components', diy_array_to_object_recursive($this->plugins));
		}
	}
	
	/**
	 * Set Data Value Used For Rendering Data In View
	 * 
	 * @param string $key
	 * @param string|array|integer $value
	 */
	private function setDataValues($key, $value) {
		$this->data[$key] = null;
		$this->data[$key] = $value;
	}
}