<?php 
namespace App\Models\Admin;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;

class DeliveryOrder extends BaseModel
{
    use SoftDeletes;

    /*
     *  Status:
     *  0 - Draft
     *  1 - In Transit
     *  2 - Received
     */

    protected $fillable = ['created_at','deleted_at','updated_at', 'do_type', 'originating_channel_id', 'target_channel_id', 'batch_id', 'remarks', 'transport_co', 'driver_name','driver_id', 'lorry_no'];
    protected $table = 'delivery_orders';
    protected $guarded = array('id');
    protected $primaryKey = 'id';
    protected $hidden = array( "deleted_at");
    protected $morphClass = 'DeliveryOrder';
    
    public function items()
    {
        return $this->hasMany('App\Models\Admin\DeliveryOrderItem', 'do_id', 'id')->with('channel_sku');
    }
    
    public function originating_channel()
    {
        return $this->belongsTo('App\Models\Admin\Channel', 'originating_channel_id');
    }
    
    public function target_channel()
    {
        return $this->belongsTo('App\Models\Admin\Channel', 'target_channel_id');
    }

    public function merchant()
    {
        return $this->belongsTo('App\Models\Admin\Merchant', 'merchant_id')->withTrashed();
    }

    public function quantityLogApp ()
    {
        return $this->morphMany('App\Models\Admin\QuantityLogApp', 'ref', 'ref_table', 'ref_table_id');
    }

    public function manifest()
    {
        return $this->belongsTo('App\Models\Admin\GTOManifest','id','do_id');
    }

    public function getReceiveAtAttribute($value)
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
                if($this->attributes['receive_at'] != '0000-00-00 00:00:00')
                    return Helper::convertTimeToUserTimezone($value, $adminTz);
                else
                    return $value;
            }catch(NoActiveAccessTokenException $e){
                return $value;
            }
        }
    }

    public function getSentAtAttribute($value)
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
                if($this->attributes['sent_at'] != '0000-00-00 00:00:00')
                    return Helper::convertTimeToUserTimezone($value, $adminTz);
                else
                    return $value;
            }catch(NoActiveAccessTokenException $e){
                return $value;
            }
        }
    }
}
