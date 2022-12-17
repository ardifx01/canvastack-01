<?php
namespace Incodiy\Codiy\Database\Migrations;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
/**
 * Created on Dec 17, 2022
 * 
 * Time Created : 3:11:02 AM
 * Filename     : Config.php
 *
 * @filesource Config.php
 *
 * @author     wisnuwidi @Incodiy - 2022
 * @copyright  wisnuwidi
 * @email      eclipsync@gmail.com
 */
 
class Config extends Migration {
	
	public $schema;
	public $setEngine           = 'InnoDB';//'MyISAM';
	
	protected $is_multiplatform = false;
	protected $platform_table   = false;
	protected $platform_key     = false;
	
	public function __construct() {
		$this->is_multiplatform  = is_multiplatform();
		if (true === $this->is_multiplatform) {
			$this->platform_table = diy_config('settings.platform_table');
			$this->platform_key   = diy_config('settings.platform_key');
		}
	}
	
	protected function set_engine($table, $engine_name) {
		$setEngine = $this->setEngine;
		if (false !== $engine_name) $setEngine = $engine_name;
		
		$this->setEngine = $setEngine;
		$table->engine   = $this->setEngine;
	}
	
	public function schema() {
		$this->schema = Schema::class;
		return $this->schema;
	}
}