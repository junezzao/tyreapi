<?php 
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;

class ThirdPartySyncArchive extends BaseModel
{
	protected $table = 'third_party_sync_archive';
	
	protected $fillable = [
		'id',
		'channel_id',
		'channel_type_id',
		'ref_table',
		'ref_table_id',
		'action',
		'sync_type',
		'extra_info',
		'trigger_event',
		'request_id',
		'status',
		'remarks',
		'sent_time',
		'merchant_id',
		'created_at',
		'updated_at'
	];

	public function logs() {
		return $this->hasMany('App\Models\Admin\ThirdPartySyncLog', 'sync_id')->orderBy('sent_time', 'desc');
	}

    public function getSentTimeAttribute($value)
    {	
    	if(is_null($value)){
    		return $value;
    	}
    	else{
    		try{
    			if(session()->has('user_timezone')){
    				$adminTz = session('user_timezone');
    			}else{
			        $userId = Authorizer::getResourceOwnerId();
			        $adminTz = User::where('id', '=', $userId)->value('timezone');
    				session(['user_timezone' => $adminTz]);
    			}
		        if($this->attributes['sent_time'] != '0000-00-00 00:00:00')
		        	return Helper::convertTimeToUserTimezone($value, $adminTz);
		        else
		        	return $value;
	        }catch(NoActiveAccessTokenException $e){
	            return $value;
	        }
    	}
    }
}
