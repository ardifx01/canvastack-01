<?php
namespace Incodiy\Codiy\Models\Admin\System;

use Illuminate\Database\Eloquent\SoftDeletes;
use Incodiy\Codiy\Models\Core\Model;

/**
 * Created on Jan 14, 2018
 * Time Created	: 12:06:59 AM
 * Filename		: Group.php
 *
 * @filesource	Group.php
 *
 * @author		wisnuwidi@Expresscode - 2018
 * @copyright	wisnuwidi
 * @email		wisnuwidi@gmail.com
 */
class Group extends Model {
	use SoftDeletes;
	protected $dates		= ['deleted_at'];
	
	protected $table		= 'base_group';
	protected $guarded	= [];
	
	public $timestamps	= false;
	
	public function relation() {
		if (true === is_multiplatform()) {
		//	return $this->hasOne(Multiplatforms::class, 'id', get_config('settings.platform_key'));
		}
	}
}