<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\ChannelSKU;
use App\Models\BaseModel;
use App\Models\Admin\Webhook;
use App\Repositories\Eloquent\SyncRepository;

class OrderItem extends BaseModel
{
    protected $table = "order_items";

    protected $guarded = ['id'];

    protected $fillable = [];

    protected $morphClass = 'OrderItem';

    protected $with = ['ref','order'];


    /**
    *
    * Relationships
    *
    */
    public function order ()
    {
        return $this->belongsTo('App\Models\Admin\Order', 'order_id');
    }

    public function returnLog ()
    {
        return $this->hasOne('App\Models\Admin\ReturnLog');
    }

    public function ref ()
    {
    	return $this->morphTo();
    }

    public function merchant ()
    {
        return $this->belongsTo('App\Models\Admin\Merchant', 'merchant_id');
    }

    /**
     *
     * Scopes
     *
     */
    public function isChargeable ()
    {
        if ($this->status == 'Verified' || $this->status == 'Returned')
            return true;
        return false;
    }

    public function scopeChargeable($query) {
        return $query->where('order_items.ref_type', '=', 'ChannelSKU')->whereIn('order_items.status', ['Verified', 'Returned']);
    }

    public function getSalePriceAttribute($value)
    {
        return ($value > 0 ? $value : $this->unit_price);
    }

    public function toAPIResponse()
    {
        return $this->apiResponse($this);
    }

    public static function apiResponse($data, $criteria = null)
    {
        if (empty($data->toArray())) {
            return null;
        }

        $items = $data;
        $single = false;

        if (empty($data[0])) {
            $items = [$items];
            $single = true;
        }
        $result = array();
        foreach ($items as $item) {
            if ($item->ref_type!=='ChannelSKU') {
                continue;
            }
            $response  = new \stdClass();
            $sku = SKU::with('product')->find($item->ref->sku_id);
            $response->id = $item->id;
            $response->tp_item_id = $item->tp_item_id;
            $response->sku_id = intval($item->ref->sku_id);
            $response->product_name = !empty($sku->product)?$sku->product->name:'';
            $response->hubwire_sku = !empty($sku)?$sku->hubwire_sku:'';
            $response->quantity = floatval($item->original_quantity);
            $response->price = floatval($item->sold_price);
            $response->retail_price = floatval($item->unit_price);
            // $response->returned_quantity = ($item->original_quantity>0)?($item->original_quantity - $item->quantity):0;
            $response->sale_price = floatval($item->sale_price);
            $response->discount = floatval($item->tp_discount);
            $response->hw_discount = floatval($item->discount);
            $response->status = $item->status;
            $response->tax = floatval($item->tax);
            $response->tax_inclusive = $item->tax_inclusive;
            $response->tax_rate = floatval($item->tax_rate);
            $result[] = $response;
        }
        return $single?(!empty($result[0])?$result[0]:null):$result;
    }

    public static function boot()
    {
        parent::boot();

        OrderItem::updated(function($model){
            Webhook::sendWebhook($model->order_id, 'sales/updated');
            $order = $model->order()->first();
            $order->updateElasticSearch();
        });
    }

}
