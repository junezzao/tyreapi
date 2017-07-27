<?php

namespace App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;

class PickingManifest extends BaseModel
{
	protected $table = "picking_manifests";
	
	protected $primaryKey = "id";
	
	protected $guarded = array('id');
	
	public function pickingItem()
	{
		return $this->hasMany('PickingItem', 'manifest_id', 'id');
	}

	public function getPickupDateAttribute($value)
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
		        if($this->attributes['pickup_date'] != '0000-00-00 00:00:00')
		        	return Helper::convertTimeToUserTimezone($value, $adminTz);
		        else
		        	return $value;
	        }catch(NoActiveAccessTokenException $e){
	            return $value;
	        }
    	}
    }
}