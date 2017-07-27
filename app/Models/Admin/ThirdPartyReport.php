<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;
use App\Models\Admin\ThirdPartyReportLog;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Illuminate\Database\Eloquent\SoftDeletes;
use Activity;

class ThirdPartyReport extends BaseModel
{
    use SoftDeletes;
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'third_party_report';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'media_id',
    	'channel_type_id',
    	'tp_order_code',
    	'order_id',
    	'tp_item_id',
    	'order_item_id',
    	'hubwire_sku',
    	'product_id',
    	'quantity',
    	'item_status',
    	'unit_price',
    	'sale_price',
    	'sold_price',
    	'channel_fees',
        'channel_shipping_fees',
        'channel_payment_gateway_fees',
    	'net_payout',
        'paid_status',
    	'payment_date',
    	'status',
        'created_by',
        'last_attended_by',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $with = ['channel_type', 'last_attended_by', 'created_by', 'order_item.ref.product.brands', 'order_item.ref.product.brands.merchant'];
    /**
     *
     * Relationships
     *
     */
    public function media()
    {
        return $this->belongsTo('App\Models\Media', 'media_id');
    }

    public function channel_type() {
        return $this->belongsTo('App\Models\Admin\ChannelType', 'channel_type_id');
    }

    public function logs()
    {
        return $this->hasMany('App\Models\Admin\ThirdPartyReportLog', 'tp_report_id');
    }

    public function remarks()
    {
        return $this->hasMany('App\Models\Admin\ThirdPartyReportRemark', 'tp_report_id')->orderBy('id', 'DESC');
    }

    public function order()
    {
        return $this->belongsTo('App\Models\Admin\Order', 'order_id');
    }

    public function order_item()
    {
        return $this->belongsTo('App\Models\Admin\OrderItem', 'order_item_id');
    }

    public function last_attended_by() {
        return $this->belongsTo('App\Models\User', 'last_attended_by');
    }

    public function created_by() {
        return $this->belongsTo('App\Models\User', 'created_by');
    }

    public static function boot()
    {
        parent::boot();

        ThirdPartyReport::updating(function ($Obj) {
            foreach($Obj->getDirty() as $attribute => $value){
                $original = $Obj->getOriginal($attribute);
                if( empty($original) && empty($value) ) {
                    continue;
                }

                $log = array(
                    'tp_report_id'  => $Obj->id,
                    'old_value'     => !empty($original) ? $original : '',
                    'new_value'     => $value,
                    'field'         => 'third_party_report.'.$attribute,
                    'modified_by'   => Authorizer::getResourceOwnerId()
                );
                ThirdPartyReportLog::create($log);
            }

            return true;
        });

        ThirdPartyReport::updated(function ($Obj) {
            Activity::log('ThirdPartyReport ' . $Obj->id . ' has been updated.', $Obj->last_attended_by);
        });

        ThirdPartyReport::created(function ($Obj) {
            Activity::log('ThirdPartyReport ' . $Obj->id . ' has been created.', is_null($Obj->last_attended_by) ? 0 : $Obj->last_attended_by);
        });
    }
}
