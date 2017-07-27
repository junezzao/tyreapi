<?php
namespace App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Admin\Product;
use App\Models\Admin\Webhook;

use App\Models\BaseModel;

class SKU extends BaseModel
{
    use SoftDeletes;
    protected $table = "sku";
    protected $primaryKey = "sku_id";
    protected $guarded = array('sku_id');
    protected $fillable = ['client_sku','product_id','merchant_id','sku_supplier_code','sku_weight', 'hubwire_sku','created_at','updated_at','deleted_at'];
    
    public function getDates()
    {
        return [];
    }
    
    public function combinations()
    {
        return $this->hasMany('App\Models\Admin\SKUCombination', 'sku_id')
                        ->select('sku_combinations.sku_id', 'option_name', 'option_value', 'sku_options.option_id')
                        ->join('sku_options', 'sku_combinations.option_id', '=', 'sku_options.option_id')
                        ->orderBy('sku_options.option_name', 'asc');
    }
    
    public function product()
    {
        return $this->belongsTo('App\Models\Admin\Product', 'product_id')->with('tags');
    }

    public function productWithTrashed()
    {
        return $this->belongsTo('App\Models\Admin\Product', 'product_id')->with('tags', 'category')->withTrashed();
    }

    public function productName()
    {
        return $this->belongsTo('App\Models\Admin\Product', 'product_id')->select(array('id', 'name', 'brand_id'));
    }

    public function merchantName()
    {
        return $this->belongsTo('App\Models\Admin\Merchant', 'merchant_id')->select(array('id', 'name'));
    }
    
    public function productDetails()
    {
        return $this->belongsTo('App\Models\Admin\Product', 'product_id')->with('brand', 'media', 'default_media');
    }

    public function batch()
    {
        return $this->hasMany('App\Models\Admin\PurchaseItem', 'sku_id')->with('batch');
    }

    public function tags()
    {
        return $this->hasMany('App\Models\Admin\SKUTag', 'sku_id');
    }

    public function channelSKUs ()
    {
        return $this->hasMany('App\Models\Admin\ChannelSKU', 'sku_id');
    }

    public function getSizeAttribute()
    {
        $options = $this->combinations;
        foreach ($options as $option) {
            if($option->option_name == 'Size')
                return $option->option_value;
        }
    }

    public function getColorAttribute()
    {
        $options = $this->combinations;
        foreach ($options as $option) {
            if($option->option_name == 'Colour')
                return ucwords($option->option_value);
        }
    }

    public function getReservedQtyAttribute()
    {

    }

    public static function boot()
    {
        parent::boot();
        
        SKU::updated(function ($Obj) {
            Product::find($Obj->product_id)->updateElasticSearch();
        });

        SKU::created(function ($Obj) {
            Product::find($Obj->product_id)->updateElasticSearch();
        });
        
    }

    public function toAPIResponse()
    {
        return $this->apiResponse($this);
    }

    public static function apiResponse($data, $criteria = null)
    {
        // \Log::info(print_r($data->toArray(), true));
        if (empty($data->toArray())) {
            return null;
        }
        
        $skus = $data;
        $single = false;
            
        if (empty($data[0])) {
            $skus = [$skus];
            $single = true;
        }
        
        $result = array();
        foreach ($skus as $sku) {
            $response  = new \stdClass();
            $response->id = $sku->sku_id;
            $response->hubwire_sku = $sku->hubwire_sku;
            $response->product_id = $sku->product_id;
            $response->weight = $sku->sku_weight;
            if (!empty($sku->channel_id)) {
                // $response->channel_sku_id = $sku->channel_sku_id;
                $response->quantity = ($sku->channel_sku_quantity < 0) ? 0 : $sku->channel_sku_quantity;
                $response->active = ($sku->channel_sku_active == true) ? 1 : 0;
                $response->retail_price = number_format($sku->channel_sku_price,2,'.','');
                $response->sale_price = number_format($sku->channel_sku_promo_price,2,'.','');
                $response->warehouse_coordinate = $sku->channel_sku_coordinates;
                // $response->channel_id = $sku->channel_id;
            }
            // $sku->supplier_code = $channel_sku->sku->sku_supplier_code;
            // $sku->barcode = $channel_sku->sku->sku_barcode;
            $option = new \stdClass();
            foreach ($sku->combinations as $tmp) {
                $option->{$tmp->option_name} = $tmp->option_value;
            }
            $response->options = $option;
            $response->created_at = $sku->created_at;
            $response->updated_at = $sku->updated_at;

            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }
}
