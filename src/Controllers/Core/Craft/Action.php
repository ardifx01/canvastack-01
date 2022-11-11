<?php
namespace Incodiy\Codiy\Controllers\Core\Craft;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Created on 24 Mar 2021
 * Time Created : 17:56:08
 *
 * @filesource	Action.php
 *
 * @author		wisnuwidi@gmail.com - 2021
 * @copyright	wisnuwidi
 * @email		wisnuwidi@gmail.com
 */
 
trait Action {
	
	public $model			            = [];
	public $model_path	            = null;
	public $model_table	            = null;
	public $model_id;
	public $model_data;
	public $model_original;
	
	public $softDeletedModel         = false;
	public $is_softdeleted           = false;
	
	public $validations	            = [];
	public $uploadTrack;
	
	public $stored_id;
	public $store_routeback          = true;
	public $filter_datatables_string = null;
	
	public function index() {
		$this->setPage();
		
		if (!empty($this->model_table)) {
			$this->table->searchable();
			$this->table->clickable();
			$this->table->sortable();
			
			$this->table->lists($this->model_table);
		}
		return $this->render();
	}
	
	public function create() {
		return $this->render();
	}
	
	private function RENDER_DEFAULT_SHOW($id) {
		$model_data = $this->model->find($id);
		
		$this->form->model($this->model, $this->model->find($id));
		foreach ($model_data->getAttributes() as $field => $value) {
			if ('id' !== $field) {
				if ('active' === $field) {
					$this->form->selectbox($field, active_box(), $model_data->active, ['disabled']);
				} elseif ('flag_status' === $field) {
					$this->form->selectbox($field, flag_status(), $model_data->flag_status, ['disabled']);
				} else {
					$this->form->text($field, $value, ['disabled']);
				}
			}
		}
		$this->form->close();
		
		return $this->render();
	}
	
	public function show($id) {
		$this->form->addAttributes(['readonly', 'disabled', 'class' => 'form-show-only']);
		
		return $this->create();
	}
	
	public function edit($id) {
		$this->setPage('&nbsp;');
		if (!empty($this->getModel($id))) {
			$model = $this->getModel($id);
			$model->find($id);
			
			if (!empty($model->getAttributes())) {
				return $this->create();
			}
		}
	}
	
	private function CHECK_DATATABLES_ACCESS_PROCESSOR() {
		if (!empty($_GET['renderDataTables'])) {
			if (!empty($_POST)) {
				unset($_POST['_token']);
				$input_filters	= [];
				
				foreach ($_POST as $field => $value) {
					if (!empty($value)) $input_filters[] = "infil[{$field}]={$value}";
				}
				
				$this->filter_datatables_string = '&filters=true&' . implode('&', $input_filters);
			}
		}
	}
	
	public function insert_data(Request $request, $routeback = true) {
		$this->validation($request, 'edit');
		return $this->INSERT_DATA_PROCESSOR($request, $routeback);
	}
	
	private function INSERT_DATA_PROCESSOR(Request $request, $routeback = true) {
		$this->CHECK_DATATABLES_ACCESS_PROCESSOR();
		
		$model = null;
		$this->store_routeback = $routeback;
		
		$req = $request->all();
		if (isset($req['filters']) && !empty($req['filters'])) {
			if ('true' === $req['filters']) {
				$this->filterDataTable($request);
			}
		} else {
			$request->validate($this->validations);
			
			if (empty($model))                        $model = $this->getModel();
			if ('Builder' === class_basename($model)) $model = $this->model_path;
			
			// check if any input file type submited
			$data            = $this->checkFileInputSubmited($request);
			$this->stored_id = diy_insert($model, $data, true);
		}
	}
	
	protected function store(Request $request) {
		$this->INSERT_DATA_PROCESSOR($request);
		
		if (true === $this->store_routeback) {
			return $this->routeBackAfterAction(__FUNCTION__, $this->stored_id);
		} else {
			return $this->stored_id;
		}
	}
	
	public function update_data(Request $request, $id, $routeback = true) {
		$this->validation($request, 'edit');
		return $this->UPDATE_DATA_PROCESSOR($request, $id, $routeback);
	}
	
	/**
	 * Set Validation Data
	 * 
	 * @param array $roles
	 * @param array $on_update
	 */
	public function setValidations($roles = [], $on_update = []) {
		$this->validations = $roles;
		if (!empty($on_update) && diy_array_contained_string(['edit', 'update'], explode('.', current_route()))) {
			unset($this->validations);
			$this->validations = $on_update;
		}
		$this->form->setValidations($this->validations);
	}
	
	private static $validation_messages = [];
	private static $validation_rules    = [];
	protected function validation(Request $request, $current_page = null) {
		$validator = Validator::make($request->all(), $this->validations);
		self::$validation_rules = $validator->getRules();
		if (true === $validator->fails()) {
			self::$validation_messages['status']   = 'failed';
			self::$validation_messages['messages'] = $validator->getMessageBag()->messages();
			
			return self::redirect($current_page, self::$validation_messages['messages'], self::$validation_messages['status']);
		}
	}
	
	public static function redirect($to, $message_data = [], $status_info = true) {
		$message = null;
		if (!empty($message_data)) {
			if (is_object($message_data) && 'Request' === class_basename($message_data)) {
				$message = $message_data->all();
			} else {				
				$message = $message_data;
			}
		}
		
		$status = false;
		if (!empty($status_info)) {
			if (!in_array($status_info, ['success', true]) || 'failed' === $status_info) {
				$status     = 'failed';
			} else $status = 'success';
		} else $status    = $status_info;
		
		$compact            = [];
		$compact['message'] = null;
		$compact['status']  = false;
		
		if (!empty($message)) $compact['message'] = compact('message');
		if (!empty($status))  $compact['status']  = compact('status');
		
		return diy_redirect($to, $compact['message'], $compact['status']);
	}
	
	private function UPDATE_DATA_PROCESSOR(Request $request, $id, $routeback = true) {
		$request->validate($this->validations);
		$model = $this->getModel($id);
		
		// check if any input file type submited
		$data = $this->checkFileInputSubmited($request);
		
		diy_update($model, $data);
		$this->stored_id = intval($id);
	}
	
	protected function update(Request $request, $id) {
		$this->UPDATE_DATA_PROCESSOR($request, $id);
		
		if (true === $this->store_routeback) {
			return $this->routeBackAfterAction(__FUNCTION__, $id);
		} else {
			return $this->stored_id;
		}
	}
	
	protected function destroy(Request $request, $id) {
		$model = $this->getModel($id);
		diy_delete($request, $model, $id);
		
		return $this->routeBackAfterAction(__FUNCTION__);
	}
	
	public function model_find($id) {
		$this->model_data = $this->model->find($id);
		
		if (true === $this->softDeletedModel) {
			if (!is_null($this->model_data->deleted_at)) {
				$this->is_softdeleted = true;
			}
		}
	}
	
	public $model_filters = [];
	/**
	 * Filter Page
	 * 
	 * @param array $filters
	 * 	: [
	 * 		'field_name' => 'value', 
	 * 		'field_name' => 'value'
	 * 	  ]
	 */
	public function filterPage($filters = [], $operator = '=') {
		if (!empty($filters)) {
			$this->model_filters = $filters;
		} else {
			if (!empty($this->filter_page)) {
				$this->model_filters = $this->filter_page;
			} else {
				$this->model_filters = $filters;
			}
		}
		
		foreach ($this->model_filters as $fieldname => $fieldvalue) {
			$this->table->conditions['where'][] = [
				'field_name' => $fieldname,
				'operator'   => $operator,
				'value'      => $fieldvalue
			];
		}
	}
	
	public $model_class_path = null;
	/**
	 * Get Data Model
	 *
	 * @param object $class
	 */
	protected function model($class, $filter = []) {
		$routeprocessor         = ['store', 'update', 'delete'];
		$currentPage            = last(explode('.', current_route()));
		
		$this->model_path       = $class;
		$this->model_filters    = $filter;
		$this->softDeletedModel = diy_is_softdeletes($class);
		
		$this->model            = new $this->model_path();
		$this->model_table      = $this->model->getTable();
		if (true === $this->softDeletedModel) {
			if (!in_array($currentPage, $routeprocessor)) {
				$this->model      = $this->model::withTrashed();
			}
		}
		if (!empty($this->model_filters)) {
			$this->model         = $this->model->where($this->model_filters);
		}
		$this->model_original   = $this->model;
		
		if (!empty(diy_get_current_route_id())) {
			$this->model_id = diy_get_current_route_id();
			$this->model_find($this->model_id);
		}
		
		if (!empty($this->form)) {
			$this->form->model = $this->model;
		}
	}
	
	/**
	 * Redirect page after login
	 *
	 * created @Aug 18, 2018
	 * author: wisnuwidi
	 *
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function firstRedirect() {
		$group_id = null;
		if (!empty($this->session_auth['group_id'])) $group_id = intval($this->session_auth['group_id']);
		if (1 === intval($group_id)) {
			// root group as internal
			return redirect()->intended($this->rootPage);
		} else {
			// admin and/or another group except root group as external
			return redirect()->intended($this->adminPage);
		}
	}
	
	/**
	 * Get Model
	 * 
	 * @param boolean $find
	 * 
	 * @return object
	 */
	protected function getModel($find = false) {
		$model = [];
		if ('Builder' === class_basename($this->model)) {
			$model = $this->model_original;
		} else {
			$model = $this->model;
		}
		
		if (true === $this->softDeletedModel) {
			if (false !== $find) {
				if (!empty($model->find($find))) {
					return $model->find($find);
				} else {
					return $model::withTrashed()->find($find);
				}
				
			} else {
				return diy_get_model($model, $find);
			}
		} else {
			return diy_get_model($model, $find);
		}
	}
	
	/**
	 * Get Table Name By Model
	 * 
	 * @param boolean $find
	 * 
	 * @return string
	 */
	protected function getModelTable($find = false) {
		return $this->getModel($find)->getTable();
	}
	
	/**
	 * Redirect Back After Sumbit Data Process
	 * 
	 * @param string $function_name
	 * @param integer $id
	 * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
	 */
	private function routeBackAfterAction($function_name, $id = false) {
		if (!empty($id)) {
			$routeBack = str_replace('.', '/', str_replace($function_name, "{$id}.edit", current_route()));
		} else {
			$routeBack = str_replace('.', '/', str_replace($function_name, '', current_route()));
		}
		
		return redirect($routeBack);
	}
	
	/**
	 * Set Upload Path URL
	 * 
	 * @return mixed
	 */
	private function setUploadURL() {
		$currentRoute = explode('.', current_route());
		unset($currentRoute[array_key_last($currentRoute)]);
		$currentRoute = implode('.', $currentRoute);
		
		return str_replace('.', '/', str_replace('.' . __FUNCTION__, '', $currentRoute));
	}
	
	/**
	 * Check If any input type file submited or not
	 * 
	 * @param Request $request
	 * @return object|\Illuminate\Http\Request
	 */
	private function checkFileInputSubmited(Request $request) {
		if (!empty($request->files)) {
			
			foreach ($request->files as $inputname => $file) {
				if ($request->hasfile($inputname)) {
					// if any file type submited
					$file = $this->fileAttributes;
					return $this->uploadFiles($this->setUploadURL(), $request, $file);
				} else {
					// if no one file type submited
					return $request;
				}
			}
			
			// if no one file type submited
			return $request;
			
		} else {
			// if no one file type submited
			return $request;
		}
	}
}