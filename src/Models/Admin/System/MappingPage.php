<?php
namespace Incodiy\Codiy\Models\Admin\System;

use Illuminate\Database\Eloquent\Model;

/**
 * Created on 10 Sep 2022
 * Time Created	: 23:58:38
 *
 * @filesource	MappingPage.php
 *
 * @author		wisnuwidi@gmail.com - 2021
 * @copyright	wisnuwidi
 * @email		wisnuwidi@gmail.com
 */

class MappingPage extends Model {
	public $table = 'base_page_privilege';
	
	public $role_data = [];
	public function current_data($group_id, $data = []) {
		$findata = diy_query($this->table)->where('group_id', intval($group_id));
		
		if (!empty($data)) {
			$findata = $findata->where($data);
		}
		
		if (!empty($findata->get())) {
			foreach ($findata->get() as $role_data) {
				$this->role_data[$role_data->module_id][$role_data->target_table][$role_data->target_field_name] = $role_data;
			}
		}
		
		return $this->role_data;
	}
	
	public function insert_process($role_data, $group) {
		$checkTables = diy_query($this->table)->where('group_id', intval($group->id))->get();
		$checkData   = [];
		$qchecks     = [];
		
		$tables = [];
		foreach ($checkTables as $table) {
			$tables[$table->target_table][$table->target_field_name] = $table;
		}
		
		foreach ($role_data as $dataRequests) {
			foreach ($dataRequests as $req) {
				$qchecks[$req['target_table']] = diy_query($this->table)
					->where('group_id'    , $group->id)
					->where('module_id'   , $req['module_id'])
					->where('target_table', $req['target_table'])
					->get();
			}
		}
		
		foreach ($qchecks as $qcTable => $qrcheck) {
			foreach ($qrcheck as $qcheck) {
				$checkData[$qcTable][$qcheck->target_field_name]['id']                  = $qcheck->id;
				$checkData[$qcTable][$qcheck->target_field_name]['group_id']            = $group->id;
				$checkData[$qcTable][$qcheck->target_field_name]['module_id']           = $qcheck->module_id;
				$checkData[$qcTable][$qcheck->target_field_name]['target_table']        = $qcheck->target_table;
				$checkData[$qcTable][$qcheck->target_field_name]['target_field_name']   = $qcheck->target_field_name;
				$checkData[$qcTable][$qcheck->target_field_name]['target_field_values'] = $qcheck->target_field_values;
			}
		}
		
		$buffers = [];
		foreach ($role_data as $role_tables => $role_modules) {
			// cek if target_table not found in database
			if (empty($checkData[$role_tables])) {
				$buffers['insert'][$role_tables] = $role_modules;
			} else {
				foreach ($role_modules as $role_field => $role_values) {
					if (!empty($checkData[$role_tables][$role_field])) {
						// find diff data
						if (array_diff($role_values, $checkData[$role_tables][$role_field])) {
							$buffers['update'][$role_tables][$role_field]['info'] = intval($checkData[$role_tables][$role_field]['id']);
							$buffers['update'][$role_tables][$role_field]['data'] = array_diff($role_values, $checkData[$role_tables][$role_field]);
						}
					} else {
						$buffers['insert'][$role_tables][$role_field] = $role_values;
					}
				}
			}
		}
		
		foreach ($tables as $table_name => $table_field) {
			if (!isset($checkData[$table_name])) {
				// check if request target_table was null
				foreach ($table_field as $table_fieldname => $table_data) {
					$buffers['delete'][$table_name][$table_fieldname] = (array) $table_data;
				}
			}
		}
		
		foreach ($checkData as $check_tables => $check_modules) {
			foreach ($check_modules as $check_fields => $check_values) {
				// check if field was deleted
				if (empty($role_data[$check_tables][$check_fields])) {
					$buffers['delete'][$check_tables][$check_fields] = $check_values;
				}
			}
		}
		
		if (!empty($buffers)) {
			
			foreach ($buffers as $action => $dataMapping) {
				if ('insert' === $action) {
					foreach ($dataMapping as $tablename => $moduleData) {
						
						foreach ($moduleData as $fieldName => $fieldValues) {
							if (intval($group->id) === intval($fieldValues['group_id'])) {
								
								diy_query($this->table)->insert([
									'group_id'            => $fieldValues['group_id'],
									'module_id'           => $fieldValues['module_id'],
									'target_table'        => $tablename,
									'target_field_name'   => $fieldName,
									'target_field_values' => $fieldValues['target_field_values']
								]);
							}
						}
					}
				}
				
				if ('update' === $action) {
					foreach ($dataMapping as $tablename => $moduleData) {
						foreach ($moduleData as $fieldName => $fieldValues) {
							diy_query($this->table)->where(['group_id' => intval($group->id), 'id' => $fieldValues['info']])->update($fieldValues['data']);
						}
					}
				}
				
				if ('delete' === $action) {
					foreach ($dataMapping as $tablename => $moduleData) {
						foreach ($moduleData as $fieldName => $fieldValues) {
							diy_query($this->table)->where(['group_id' => intval($group->id), 'id' => $fieldValues['id']])->delete();
						}
					}
				}
			}
		}
	}
	
	public static function getTableFields($data) {
		$fields = [];
		if (is_array($data)) {
			foreach ($data as $tableName) {
				foreach (diy_get_table_columns($tableName) as $fieldname) {
					$fields[$fieldname] = $fieldname;
				}
			}
		} else {
			foreach (diy_get_table_columns($data) as $fieldname) {
				$fields[$fieldname]    = $fieldname;
			}
		}
		
		return json_encode($fields);
	}
	
	private static function queryFieldValues($requests, $tablename, $node = null) {
		$sql      = [];
		$fieldset = [];
		
		if (is_array($requests)) {
			foreach ($requests as $request) {
				
				$fieldNameValue     = $request;
				if (diy_string_contained($request, '::')) {
					$explode         = explode('::', $request);
					$fieldNameValue  = $explode[1];
				}
				
				$rows['table_name'] = $tablename;
				$rows['field_name'] = $fieldNameValue;
				
				$fieldset = $rows['field_name'];
				$sql      = "SELECT `{$rows['field_name']}` FROM {$rows['table_name']} WHERE `{$rows['field_name']}` IS NOT NULL GROUP BY `{$rows['field_name']}`;";
			}
		} else {
			$explode = explode('::', $requests);
			
			$rows['table_name'] = explode($node, $explode[0])[0];
			$rows['field_name'] = $explode[1];
			
			$fieldset = $rows['field_name'];
			$sql      = "SELECT `{$rows['field_name']}` FROM {$rows['table_name']} WHERE `{$rows['field_name']}` IS NOT NULL GROUP BY `{$rows['field_name']}`;";
		}
		
		$data             = [];
		$data['data']     = diy_query($sql, 'SELECT');
		$data['fieldset'] = $fieldset;
		
		return $data;		
	}
	
	public static function getFieldValues($data, $node_id = '__node__') {
		$rows  = [];
		$query = [];
		
		if (is_array($data)) {
			if (isset($_POST) && !empty($_GET['usein'])) {
				foreach ($data as $moduleData) {
					foreach ($moduleData as $tablename => $requests) {
						$query = self::queryFieldValues($requests, $tablename);
					}
				}
			} else {
				foreach ($data as $tablename => $requests) {
					$query = self::queryFieldValues($requests, $tablename);
				}
			}
		}
		
		$rows  = [];
		foreach ($query['data'] as $row) {
			$rows[$row->{$query['fieldset']}] = $row->{$query['fieldset']};
		}
		
		return json_encode($rows);
	}
	
	public static $prefixNode = 'rolePages';
	public static function getData($data, $usein, $node_id) {
		$data = $data[self::$prefixNode];
		
		if ('table_name' === $usein) $output = self::getTableFields($data['table_name']);
		if ('field_name' === $usein) $output = self::getFieldValues($data['field_name'], $node_id);
		
		return $output;
	}
	
	public function getUserDataMapping($user_id) {
		$sql = "
			SELECT
				bug.user_id,
				bpp.group_id,
				bpp.module_id,
				u.username,
				u.email,
				u.fullname,
				bg.group_name,
				bg.group_info,
				bm.route_path,
				bm.parent_name,
				bm.module_name,
				bm.module_info,
				bpp.target_table,
				bpp.target_field_name,
				bpp.target_field_values
			FROM `base_page_privilege` bpp
			JOIN base_group bg ON bpp.group_id = bg.id
			JOIN base_user_group bug ON bg.id = bug.group_id 
				AND bpp.group_id = bg.id
			JOIN users u ON u.id = bug.user_id
			JOIN base_module bm ON bpp.module_id = bm.id
			WHERE u.id = {$user_id}
			GROUP BY bug.user_id, bpp.group_id, bpp.module_id, u.username, u.email, u.fullname, bg.group_name, bg.group_info, bm.route_path, bm.parent_name, bm.module_name, bm.module_info, bpp.target_table, bpp.target_field_name, bpp.target_field_values
			ORDER BY bug.user_id, bpp.group_id, bpp.module_id, u.username, u.email, u.fullname, bg.group_name, bg.group_info, bm.route_path, bm.parent_name, bm.module_name, bm.module_info, bpp.target_table, bpp.target_field_name, bpp.target_field_values
		";
		
		$map    = [];
		$object = diy_query($sql, 'SELECT');
		
		foreach ($object as $row) {
			$target_field_values = $row->target_field_values;
			
			if (is_null($row->target_field_values)) {
				$target_field_values = 'IS NOT NULL';
			}
			
			$filter_query = $target_field_values;
			if (diy_string_contained($row->target_field_values, '::')) {
				$target_field_values = explode('::', $row->target_field_values);
				$filter_query        = "IN ('" . implode("', '", $target_field_values) . "')";
			}
			
			$map[$row->user_id][$row->route_path][$row->target_table]['table_name']                                   = $row->target_table;
			$map[$row->user_id][$row->route_path][$row->target_table]['target_field_name'][]                          = $row->target_field_name;
			$map[$row->user_id][$row->route_path][$row->target_table]['target_field_values'][]                        = $target_field_values;
			$map[$row->user_id][$row->route_path][$row->target_table]['target_filter_query'][$row->target_field_name] = $target_field_values;
			
			$map[$row->user_id][$row->route_path][$row->target_table]['filter_query'][$row->target_field_name]        = $filter_query;
			$map[$row->user_id][$row->route_path][$row->target_table]['filter_data'][$row->target_field_name]         = $target_field_values;
		}
		
		return $map;
	}
}