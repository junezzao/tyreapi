<?php 
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class ThirdPartySyncLog extends BaseModel
{
	protected $table = 'third_party_sync_log';

	public $timestamps = false;
	
	protected $fillable = [
		'sync_id',
		'request_id',
		'status',
		'remarks',
		'sent_time'
	];

	public function sync() {
		return $this->belongsTo('App\Models\Admin\ThirdPartySync', 'sync_id');
	}

	public function archived_sync() {
		return $this->belongsTo('App\Models\Admin\ThirdPartySyncArchive', 'sync_id');
	}
}
