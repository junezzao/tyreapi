<?php 
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;
use Carbon\Carbon;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;

class Purchase extends BaseModel
{
    use SoftDeletes;

    protected $table = 'purchase_batches';
    
    protected $primaryKey = 'batch_id';

    protected $guarded = ['batch_id'];

    protected $fillable = [
        'batch_currency',
        'batch_conversion_rate',
        'batch_remarks',
        'client_id',
        'batch_status',
        'replenishment',
        'user_id',
        'supplier_id',
        'merchant_id',
        'batch_tax',
        'batch_shipping',
        'channel_id',
        'batch_date',
        'receive_date',
        'created_at',
        'updated_at',
        'deleted_at'
    ];    
    
    public function getDates()
    {
        return [];
    }
    
    public function items()
    {
        return $this->hasMany('App\Models\Admin\PurchaseItem', 'batch_id')
                        ->select('item_id', 'batch_id', 'sku_id', 'item_cost', 'updated_at', 'item_quantity', 'item_retail_price')
                        ->with('sku');
    }

    public function itemsWithTrashed()
    {
        return $this->hasMany('App\Models\Admin\PurchaseItem', 'batch_id')
                        ->select('item_id', 'batch_id', 'sku_id', 'item_cost', 'updated_at', 'item_quantity', 'item_retail_price')
                        ->with('skuWithTrashed');
    }
    
    public function itemCount()
    {
        return $this->hasOne('App\Models\Admin\PurchaseItem', 'batch_id')->selectRaw('batch_id, sum(item_quantity) as item_count')->groupBy('batch_id');
    }
    
    public function totalItemCost()
    {
        return $this->hasOne('App\Models\Admin\PurchaseItem', 'batch_id')->selectRaw('batch_id, sum(item_cost*item_quantity) as total_item_cost')->groupBy('batch_id');
    }
    
    public function client()
    {
        return $this->belongsTo('Client', 'client_id');
    }

    public function channel()
    {
        return $this->belongsTo('Channel', 'channel_id');
    }

    public function quantityLogApp ()
    {
        return $this->morphMany('App\Models\Admin\QuantityLogApp', 'ref', 'ref_table', 'ref_table_id');
    }
    
    public function getReceiveDateAttribute($value)
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
                if($this->attributes['receive_date'] != '0000-00-00 00:00:00')
                    return Helper::convertTimeToUserTimezone($value, $adminTz);
                else
                    return $value;
            }catch(NoActiveAccessTokenException $e){
                return $value;
            }
        }
    }
}