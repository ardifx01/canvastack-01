<?php
namespace Incodiy\Codiy\Models\Admin\System;

use Incodiy\Codiy\Models\Core\Model;
use Illuminate\Support\Facades\DB;

/**
 * Enhanced DynamicTables with Universal Data Source Support
 * 
 * Created on 2 Jun 2021
 * Time Created : 13:24:01
 *
 * @filesource DynamicTables.php
 *
 * @author     wisnuwidi@incodiy.com - 2021
 * @copyright  wisnuwidi
 * @email      wisnuwidi@incodiy.com
 */
class DynamicTables extends Model {
	protected $connection = 'mysql';
	private $queryBuilder;
	private $rawQuery;
	private $queryType = 'raw_sql'; // 'raw_sql', 'query_builder', 'collection'
	
	public function __construct($sql = null, $connection = 'mysql') {
		if (!empty($connection)) $this->connection = $connection;
		
		if (!empty($sql)) {
			$this->rawQuery = $sql;
			$this->initializeFromSQL($sql);
		}
	}
	
	/**
	 * Initialize from SQL query
	 */
	private function initializeFromSQL($sql) {
		try {
			// Extract table name from SQL
			$this->table = diy_get_table_name_from_sql($sql);
			
			// Create query builder from raw SQL for DataTables compatibility
			$this->queryBuilder = DB::connection($this->connection)->table(DB::raw("({$sql}) as dynamic_table"));
			$this->queryType = 'query_builder';
			
			\Log::info("✅ DynamicTables initialized with query builder from SQL");
			
		} catch (\Exception $e) {
			\Log::error("❌ Error initializing DynamicTables from SQL: " . $e->getMessage());
			
			// Fallback to original method
			$data = diy_query($sql);
			foreach($data as $key => $value) {
				$this->$key = $value;
			}
			
			$this->table = diy_get_table_name_from_sql($sql);
			$this->queryType = 'raw_sql';
		}
	}
	
	/**
	 * Get Query Builder instance for DataTables processing
	 */
	public function getQueryBuilder() {
		if ($this->queryType === 'query_builder' && $this->queryBuilder) {
			return $this->queryBuilder;
		}
		
		// Create basic query builder if none exists
		if ($this->table) {
			return DB::connection($this->connection)->table($this->table);
		}
		
		throw new \Exception("No query builder available for DynamicTables");
	}
	
	/**
	 * Support DataTables methods by delegating to query builder
	 */
	public function __call($method, $parameters) {
		// Methods that should work with query builder
		$queryBuilderMethods = [
			'select', 'where', 'whereIn', 'whereNotIn', 'orWhere', 
			'join', 'leftJoin', 'rightJoin', 'innerJoin',
			'orderBy', 'groupBy', 'having', 'limit', 'offset',
			'skip', 'take', 'count', 'get', 'first', 'toSql', 'getBindings'
		];
		
		if (in_array($method, $queryBuilderMethods)) {
			$queryBuilder = $this->getQueryBuilder();
			$result = $queryBuilder->{$method}(...$parameters);
			
			// For methods that return builder, update our instance
			if ($result instanceof \Illuminate\Database\Query\Builder) {
				$this->queryBuilder = $result;
				return $this;
			}
			
			return $result;
		}
		
		// Fallback to parent method
		return parent::__call($method, $parameters);
	}
	
	public function setTable($table) {
		$this->table = $table;
		$this->queryBuilder = DB::connection($this->connection)->table($table);
		$this->queryType = 'query_builder';
		
		return $this;
	}
	
	public function guarded($guarded = []) {
		$this->guarded = $guarded;
		
		return $this;
	}
	
	private $get_query;
	public function setQuery($sql, $type = 'select') {
		$query = diy_query($sql, $type);
		$this->get_query = collect($query);
		
		return $this;
	}
	
	public function getQueryData() {
		return $this->get_query;
	}
	
	/**
	 * Get table name - required for DataTables processing
	 */
	public function getTable() {
		// CRITICAL FIX: Return actual table name if set, otherwise try to detect from context
		if (!empty($this->table)) {
			return $this->table;
		}
		
		// Try to get from query builder if available
		if ($this->queryBuilder) {
			try {
				$sql = $this->queryBuilder->toSql();
				$tableName = diy_get_table_name_from_sql($sql);
				if ($tableName && $tableName !== 'dynamic_table') {
					$this->table = $tableName; // Cache it
					return $tableName;
				}
			} catch (\Exception $e) {
				\Log::warning("Could not extract table name from query builder: " . $e->getMessage());
			}
		}
		
		// Emergency fallback - but log warning
		\Log::warning("⚠️  DynamicTables using fallback table name 'dynamic_table' - this may cause errors");
		return 'dynamic_table';
	}
	
	/**
	 * Create DynamicTables from Query Builder
	 */
	public static function fromQueryBuilder($queryBuilder, $connection = 'mysql') {
		$instance = new static(null, $connection);
		$instance->queryBuilder = $queryBuilder;
		$instance->queryType = 'query_builder';
		
		// Try to extract table name from query builder
		try {
			$sql = $queryBuilder->toSql();
			$instance->table = diy_get_table_name_from_sql($sql);
		} catch (\Exception $e) {
			$instance->table = 'dynamic_table';
		}
		
		return $instance;
	}
	
	/**
	 * Create DynamicTables from Collection
	 */
	public static function fromCollection($collection, $tableName = 'dynamic_collection') {
		$instance = new static(null);
		$instance->get_query = collect($collection);
		$instance->table = $tableName;
		$instance->queryType = 'collection';
		
		return $instance;
	}
}