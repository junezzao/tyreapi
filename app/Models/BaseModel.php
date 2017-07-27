<?php namespace App\Models;
use App\Extensions\CustomCollection;
use Illuminate\Database\Eloquent\Model;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;
use Illuminate\Http\Request;

class BaseModel extends Model {
	
	public function newCollection(array $models = Array())
	{
		return new CustomCollection($models);
	}

    public function getCreatedAtAttribute($value)
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

    public function getUpdatedAtAttribute($value)
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
		        if($this->attributes['updated_at'] != '0000-00-00 00:00:00')
		        	return Helper::convertTimeToUserTimezone($value, $adminTz);
		        else
		        	return $value;
	        }catch(NoActiveAccessTokenException $e){
	            return $value;
	        }
    	}
    }

    public function getDeletedAtAttribute($value)
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
		        if($this->attributes['deleted_at'] != '0000-00-00 00:00:00')
		        	return Helper::convertTimeToUserTimezone($value, $adminTz);
		        else
		        	return $value;
	        }catch(NoActiveAccessTokenException $e){
	            return $value;
	        }
    	}
    }
}
