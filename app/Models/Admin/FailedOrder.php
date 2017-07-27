<?php
namespace App\Models\Admin;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;

class FailedOrder extends BaseModel {

	use SoftDeletes;

	protected $fillable = ['channel_id', 'order_id', 'tp_order_id', 'error', 'status', 'user_id', 'tp_order_date'];

	protected $table = 'failed_orders';
	
	protected $guarded = array('failed_order_id');
	
	protected $primaryKey = 'failed_order_id';

	public static $statusCode = array(
        'New'       	=> 1,
        'Pending'       => 2,
        'Resolved'      => 3,
        'Discarded'     => 4,
    );

    public static $newStatus	     = 1;
    public static $pendingStatus     = 2;
    public static $resolvedStatus    = 3;
    public static $discardedStatus   = 4;

	public function channel() {
		return $this->belongsTo('App\Models\Admin\Channel', 'channel_id');
	}

	public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    public function getStatusAttribute($value)
    {
    	$statusArray = array_flip(static::$statusCode);

    	return $statusArray[$value];
    }

    public function getTpOrderDateAttribute($value)
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
		        if($this->attributes['created_at'] != '0000-00-00 00:00:00')
		        	return Helper::convertTimeToUserTimezone($value, $adminTz);
		        else
		        	return $value;
	        }catch(NoActiveAccessTokenException $e){
	            return $value;
	        }
    	}
    }
}