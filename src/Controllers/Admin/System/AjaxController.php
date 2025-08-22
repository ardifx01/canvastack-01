<?php
namespace Incodiy\Codiy\Controllers\Admin\System;

use Incodiy\Codiy\Controllers\Core\Controller;
use Incodiy\Codiy\Library\Components\Table\Craft\Datatables;
use Incodiy\Codiy\Library\Components\Table\Craft\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Incodiy\Codiy\Library\Components\Chart\Charts;
use Incodiy\Codiy\Library\Components\Table\Craft\DatatableRuntime;

/**
 * Created on Sep 23, 2022
 * 
 * Time Created : 7:51:52 PM
 *
 * @filesource	AjaxController.php
 *
 * @author     wisnuwidi@incodiy.com - 2022
 * @copyright  wisnuwidi
 * @email      wisnuwidi@incodiy.com
 */

class AjaxController extends Controller {
	
	public $ajaxConnection = null;
	
	public function __construct($connection = null) {
		if (!empty($connection)) $this->ajaxConnection = $connection;
	}
	
    public function post() {
        try {
            // 1) Prioritaskan POST DataTables
            if (!empty($_POST['renderDataTables']) && $_POST['renderDataTables'] === 'true') {
                // Normalisasi
                $method = $_POST;
                if (isset($_POST['difta[name]']))  $method['difta']['name']   = $_POST['difta[name]'];
                if (isset($_POST['difta[source]']))$method['difta']['source'] = $_POST['difta[source]'];

                $diftaName = $method['difta']['name'] ?? ($_POST['difta[name]'] ?? null);
                if (empty($diftaName)) {
                    return response()->json(['error' => 'Missing difta name'], 400);
                }

                // Coba runtime → jika tidak ada, fallback
                $dataObject = DatatableRuntime::get((string) $diftaName);
                $datatables = new Datatables();
                // Declarative Relations API: hydrate declared_relations & dot_columns from runtime
                try {
                    if (!empty($dataObject->datatables->declared_relations)) {
                        $method['declared_relations'] = $dataObject->datatables->declared_relations;
                    }
                    if (!empty($dataObject->datatables->dot_columns)) {
                        $method['dot_columns'] = $dataObject->datatables->dot_columns;
                    }
                } catch (\Throwable $e) {}

                if ($dataObject) {
                    return $datatables->process($method, $dataObject, $_POST, []);
                }

                // Fallback (legacy) — tidak tergantung runtime
                $_GET['filterDataTables'] = $_GET['filterDataTables'] ?? 'true';
                return $datatables->init_filter_datatables($_GET, $_POST, null);
            }

            // 2) Jalur lama GET
            if (!empty($_GET['AjaxPosF']))        return $this->post_filters();
            if (!empty($_GET['diyHostConn']))     return $this->getHostConnections();
            if (!empty($_GET['diyHostProcess']))  return $this->getHostProcess();
            if (!empty($_GET['filterDataTables'])) {
                $datatables = new Datatables();
                return $datatables->init_filter_datatables($_GET, $_POST, null);
            }
            if (!empty($_GET['filterCharts'])) {
                $charts = new Charts();
                return $charts->init_filter_charts($_GET, $_POST, null);
            }

            // 3) Default JSON valid
            return response()->json([
                'draw' => (int)($_POST['draw'] ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => []
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('AjaxController@post failed', [
                'message' => $e->getMessage(),
                'post' => $_POST,
                'get' => $_GET,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function postX4() {
        try {
            // 1) Prioritaskan POST DataTables
            if (!empty($_POST['renderDataTables']) && $_POST['renderDataTables'] === 'true') {

                // Normalisasi input
                $method = $_POST;
                if (isset($_POST['difta[name]']))  $method['difta']['name']   = $_POST['difta[name]'];
                if (isset($_POST['difta[source]']))$method['difta']['source'] = $_POST['difta[source]'];

                $diftaName = $method['difta']['name'] ?? ($_POST['difta[name]'] ?? null);
                if (empty($diftaName)) {
                    return response()->json(['error' => 'Missing difta name'], 400);
                }

                // Coba ambil runtime; jika tidak ada -> fallback ke init_filter_datatables
                $dataObject = DatatableRuntime::get((string) $diftaName);
                if ($dataObject) {
                    $datatables = new Datatables();
                    return $datatables->process($method, $dataObject, $_POST, []);
                } else {
                    // Fallback (legacy) — tidak tergantung runtime
                    $datatables = new Datatables();
                    $_GET['filterDataTables'] = $_GET['filterDataTables'] ?? 'true';
                    return $datatables->init_filter_datatables($_GET, $_POST, null);
                }
            }

            // 2) Jalur lama GET
            if (!empty($_GET['AjaxPosF']))        return $this->post_filters();
            if (!empty($_GET['diyHostConn']))     return $this->getHostConnections();
            if (!empty($_GET['diyHostProcess']))  return $this->getHostProcess();
            if (!empty($_GET['filterDataTables'])) {
                $datatables = new Datatables();
                return $datatables->init_filter_datatables($_GET, $_POST, null);
            }
            if (!empty($_GET['filterCharts'])) {
                $charts = new Charts();
                return $charts->init_filter_charts($_GET, $_POST, null);
            }

            // 3) Default JSON valid
            return response()->json([
                'draw' => (int)($_POST['draw'] ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => []
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('AjaxController@post failed', [
                'message' => $e->getMessage(),
                'post' => $_POST,
                'get' => $_GET,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Server error'], 500);
        }
    }

	public function postX3() {
        try {
			
            // 1) Tangani DataTables via POST terlebih dahulu
            if (!empty($_POST['renderDataTables']) && $_POST['renderDataTables'] === 'true') {
                $this->datatableClass();

                // Normalisasi input
                $method = $_POST;
                if (isset($_POST['difta[name]'])) {
                    $method['difta']['name'] = $_POST['difta[name]'];
                }
                if (isset($_POST['difta[source]'])) {
                    $method['difta']['source'] = $_POST['difta[source]'];
                }

                // Ambil nama difta dengan aman
                $diftaName = $method['difta']['name'] ?? ($_POST['difta[name]'] ?? null);
                if (empty($diftaName)) {
                    return response()->json(['error' => 'Missing difta name'], 400);
                }

                // Ambil konteks runtime yang didaftarkan saat Builder render tabel
                $dataObject = DatatableRuntime::get((string) $diftaName);
                if (!$dataObject) {
                    \Log::error('Datatable runtime context unavailable', ['post' => $_POST, 'difta' => $diftaName]);
                    return response()->json(['error' => 'Datatable context missing'], 500);
                }

                // Proses DataTables
				if (!empty($_POST)) {
					$filters = $_POST;
					return $this->datatables->process($method, $dataObject, $filters, []);
				}

				// Default: kembalikan JSON valid DataTables agar tidak memicu alert
				return response()->json([
					'draw' => (int)($_POST['draw'] ?? 0),
					'recordsTotal' => 0,
					'recordsFiltered' => 0,
					'data' => []
				], 200);
            }

            // 2) Jalur lama berbasis GET flags (tetap didukung)
            if (!empty($_GET['AjaxPosF'])) {
                return $this->post_filters();
            }
            if (!empty($_GET['diyHostConn'])) {
                return $this->getHostConnections();
            }
            if (!empty($_GET['diyHostProcess'])) {
                return $this->getHostProcess();
            }
            if (!empty($_GET['filterDataTables'])) {
                $this->datatableClass();
                return $this->datatables->init_filter_datatables($_GET, $_POST, $this->ajaxConnection);
            }
            if (!empty($_GET['filterCharts'])) {
                $this->chartClass(); // penting: gunakan chartClass, bukan datatableClass
                return $this->charts->init_filter_charts($_GET, $_POST, $this->ajaxConnection);
            }

            // 3) Default: kembalikan JSON valid DataTables agar tidak memicu alert
            return response()->json([
                'draw' => (int)($_POST['draw'] ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => []
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('AjaxController@post failed', [
                'message' => $e->getMessage(),
                'post' => $_POST,
                'get' => $_GET,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Server error'], 500);
        }
    }
		
	public static $ajaxUrli;
	/**
	 * Ajax Post URL Address
	 * 
	 * @param string $init_post
	 * 	: Initialize Post Key
	 * 	  ['AjaxPosF'         : by default]
	 * 	  ['filterDataTables' : for datatables filtering]
	 * @param boolean $return_data
	 * @return string
	 */
	public static function urli($init_post = 'AjaxPosF', $return_data = false) {
		$current_url  = route('ajax.post');
		if ('filterDataTables' === $init_post) {
			$urlset = [$init_post => 'true'];
		} else {
			$urlset = [$init_post => 'true' ,'_token'  => csrf_token()];
		}
		
		$uri = [];
		foreach ($urlset as $fieldurl => $urlvalue) {
			$uri[] = "{$fieldurl}={$urlvalue}";
		}
		
		self::$ajaxUrli = $current_url . '?' . implode('&', $uri);
		if (true === $return_data) {
			return self::$ajaxUrli;
		}
	}
	
	public function postXample() {
		if (!empty($_GET)) {
			if (!empty($_GET['AjaxPosF'])) {
				return $this->post_filters();
			} elseif (!empty($_GET['diyHostConn'])) {
				return $this->getHostConnections();
			} elseif (!empty($_GET['diyHostProcess'])) {
				return $this->getHostProcess();
			} elseif (!empty($_GET['filterDataTables'])) {
			    return $this->initFilterDatatables($_GET, $_POST);
			} elseif (!empty($_GET['filterCharts'])) {
			    return $this->initFilterCharts($_GET, $_POST);
			}
		}
	}

	/**
	 * Handle AJAX POST requests.
	 *
	 * This method handles incoming POST requests and checks for the following
	 * conditions:
	 *
	 * 1. If the request is a filter request, redirect to {@link post_filters()}.
	 * 2. If the request is a host connection request, redirect to {@link getHostConnections()}.
	 * 3. If the request is a host process request, redirect to {@link getHostProcess()}.
	 * 4. If the request is a DataTables rendering request, handle it via POST.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function postX2() {

		/*
		return response()->json([
			'draw' => (int)($_POST['draw'] ?? 0),
			'recordsTotal' => 0,
			'recordsFiltered' => 0,
			'data' => $_POST['renderDataTables']
		], 200);
		*/
		
		if (!empty($_GET)) {
			if (!empty($_GET['AjaxPosF'])) {
				return $this->post_filters();
			} elseif (!empty($_GET['diyHostConn'])) {
				return $this->getHostConnections();
			} elseif (!empty($_GET['diyHostProcess'])) {
				return $this->getHostProcess();
			} elseif (!empty($_GET['filterDataTables'])) {
				return $this->initFilterDatatables($_GET, $_POST);
			} elseif (!empty($_GET['filterCharts'])) {
				return $this->initFilterCharts($_GET, $_POST);
			}
		}

		
		// NEW: handle DataTables rendering via POST
		if (!empty($_POST['renderDataTables']) && $_POST['renderDataTables'] === 'true') {
			$this->datatableClass();

			// Normalize input for Datatables::initializeModel
			$method = $_POST;
			if (isset($_POST['difta[name]'])) {
				$method['difta']['name'] = $_POST['difta[name]'];
			}
			if (isset($_POST['difta[source]'])) {
				$method['difta']['source'] = $_POST['difta[source]'];
			}
			
			\Log::debug('DT POST incoming', ['difta' => $diftaName, 'post' => $_POST]);
			// TODO: ambil $data context datatable dari registry runtime saat Builder render tabel
			$dataObject = DatatableRuntime::get((string) $diftaName);
			if (!$dataObject) {
				\Log::error('Datatable runtime context unavailable', ['post' => $_POST]);
				return response()->json(['error' => 'Datatable context missing'], 500);
			}

			try {
				$filters = $_POST;
				$result = $this->datatables->process($method, $dataObject, $filters, []);
				return $result;
			} catch (\Throwable $e) {
				\Log::error('DataTables POST processing failed', [
					'message' => $e->getMessage(),
					'post' => $_POST,
					'trace' => $e->getTraceAsString()
				]);
				return response()->json(['error' => 'DataTables processing error'], 500);
			}
		}

		return response()->json(['error' => 'Invalid AJAX request'], 400);
	}

	
	private function getHostProcess() {
		unset($_POST['_token']);
		
		$sconnect        = $_POST['source_connection_name'];
		$stable          = $_POST['source_table_name'];
		$tconnect        = $_POST['target_connection_name'];
		$ttable          = $_POST['target_table_name'];
		
		$datasource      = DB::connection($sconnect)->select("SELECT * FROM {$stable}");
		$sourceData      = [];
		foreach ($datasource as $datasources) {
			$sourceData[] = (array) $datasources;
		}
		$sourceCounts    = count($sourceData);
		$limitCounts     = 100;
		$rowCountProcess = round($sourceCounts/$limitCounts);
		
		$result = [];
		if (!empty($datasource)) {
			$transfers    = DB::connection($tconnect);
			$transfers->beginTransaction();
			$transfers->delete("TRUNCATE {$ttable}");
			
			$datahandler  = array_chunk($sourceData, $limitCounts);
			$stillHandled = true;
			$countData    = 0;
			foreach($datahandler as $row) {
				$countData++;
				if (!$transfers->table($ttable)->insert($row)) $stillHandled = false;
			}
			
			if ($stillHandled) {
				if ($countData < $rowCountProcess) $transfers->commit();
			} else {
				$transfers->rollBack();
			}
					
			$result['counts']['source'] = $sourceCounts;
			$result['counts']['target'] = count($transfers->select("SELECT * FROM {$ttable}"));
		}
		
		return json_encode($result);
	}
	
	private function getHostConnections() {
		$connection_sources = diy_config('sources', 'connections');
		
		unset($_GET['diyHostConn']);
		unset($_GET['_token']);
		
		$info             = [];
		$info['selected'] = null;
		foreach ($_GET as $key => $data) {
			if ('s' === $key) $info['selected'] = decrypt($data);
		}
		
		$allTables = [];
		foreach ($_POST as $value) {
			$allTables = diy_get_all_tables($connection_sources[$value]['connection_name']);
		}
		
		$result = [];
		if (!empty($allTables)) {
			foreach ($allTables as $tablename) {
				$label = ucwords(str_replace('_', ' ', $tablename));
				$result['data'][$tablename] = $label;
			}
		}
		
		if (!empty($info['selected'])) {
			$result['selected'] = $info['selected'];
		}
		
		return json_encode($result);
	}
	
	private function post_filters() {
		unset($_GET['AjaxPosF']);
		unset($_GET['_token']);
		
		$info             = [];
		$info['label']    = null;
		$info['value']    = null;
		$info['selected'] = null;
		$info['query']    = null;
		
		foreach ($_GET as $key => $data) {
			if ('l' === $key) {
				$info['label']    = decrypt($data);
			} elseif ('v' === $key) {
				$info['value']    = decrypt($data);
			} elseif ('s' === $key) {
				$info['selected'] = decrypt($data);
			} else {
				$info['query']    = decrypt($data);
			}
		}
		
		$postKEY   = array_keys($_POST)[0];
		$postValue = array_values($_POST)[0];
		
		$queryData     = [];
		if (!empty($info['query'])) {
			$sql       = "{$info['query']} WHERE `{$postKEY}` = '{$postValue}' ORDER BY `{$postKEY}` DESC";
			$queryData = diy_query($sql, 'SELECT', $this->ajaxConnection);
		}
		
		$result = [];
		if (!empty($queryData)) {
			foreach ($queryData as $rowData) {
				$result['data'][$rowData->{$info['value']}] = $rowData->{$info['label']};
			}
		}
		
		if (!empty($info['selected'])) {
			$result['selected'] = $info['selected'];
		}
		$results = $result;
		
		return json_encode($results);
	}
	
	private $datatables = [];
	public function datatableClass() {
		$this->datatables = new Datatables();
	}
	
	public $filter_datatables = [];
	protected function filterDataTable(Request $request) {
		$this->datatableClass();
		$this->filter_datatables = $this->datatables->filter_datatable($request);
		
		return $this;
	}
	
	private function initFilterDatatables() {
		if (!empty($_GET['filterDataTables'])) {
			$this->datatableClass();
			return $this->datatables->init_filter_datatables($_GET, $_POST, $this->ajaxConnection);
		}
	}
	
	private $charts = [];
	private function chartClass() {
	    $this->charts = new Charts();
	}
	private function initFilterCharts() {
	    if (!empty($_GET['filterCharts'])) {
	        $this->datatableClass();
	        return $this->charts->init_filter_charts($_GET, $_POST, $this->ajaxConnection);
	    }
	}
	
	public function export() {
		$export = new Export();
		return $export->csv('assets/resources/exports');
	}
}