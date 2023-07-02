<?php
namespace Incodiy\Codiy\Controllers\Core;

use Illuminate\Routing\Controller as BaseController;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;

use Incodiy\Codiy\Controllers\Core\Craft\View;
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
	private $model_class = null;
	
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
		$this->getHiddenFields();
		$this->getExcludeFields();
		
		$this->setDataValues('content_page', []);
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