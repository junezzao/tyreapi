<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Admin\Product;
use App\Models\BaseModel;
use App\Models\Admin\Webhook;


class ChannelSKU extends BaseModel
{
    use SoftDeletes;
    protected $fillable = [
            'channel_sku_quantity',
            'channel_sku_price',
            'channel_sku_promo_price',
            'promo_start_date',
            'promo_end_date',
            'channel_sku_coordinates',
            'channel_sku_active',
            'channel_id', 'sku_id',
            'product_id',
            'client_id',
            'ref_id'
            ];
    protected $table = 'channel_sku';
    protected $guarded = array('channel_sku_id');
    protected $primaryKey = 'channel_sku_id';

    protected $morphClass = 'ChannelSKU';

    protected $with = ['sku', 'product'];

    public function setPromoStartDateAttribute($value)
    {
        if(trim($value)=='')
            $this->attributes['promo_start_date'] = NULL;
        else
            $this->attributes['promo_start_date'] = $value;
    }

    public function setPromoEndDateAttribute($value)
    {
        if(trim($value)=='')
            $this->attributes['promo_end_date'] = NULL;
        else
            $this->attributes['promo_end_date'] = $value;
    }

    public function getDates()
    {
        return [];
    }

    public function channel()
    {
        return $this->belongsTo('App\Models\Admin\Channel', 'channel_id','id')->with('channel_type');
    }

    public function channel_details()
    {
        return $this->belongsTo('App\Models\Admin\ChannelDetails', 'channel_id','channel_id');
    }

    public function merchant()
    {
        return $this->belongsTo('App\Models\Admin\Merchant', 'merchant_id','id');
    }

    public function sku()
    {
        return $this->belongsTo('App\Models\Admin\SKU', 'sku_id');
    }

    public function skuWithbatch()
    {
        return $this->belongsTo('App\Models\Admin\SKU', 'sku_id')->with('batch');
    }


    public function product()
    {
        return $this->belongsTo('App\Models\Admin\Product', 'product_id')->withTrashed()->with('media','tags');
    }

    public function productBrand()
    {
        return $this->belongsTo('App\Models\Admin\Product', 'product_id')->with('brand', 'media', 'default_media');
    }

    public function sku_options()
    {
        $sku = $this->sku();
        return $sku->getResults()->hasMany('App\Models\Admin\SKUCombination', 'sku_id')->select('sku_id', 'option_name', 'option_value')
                                ->join('sku_options', 'sku_options.option_id', '=', 'sku_combinations.option_id');
    }

    public function tags()
    {
        $product = $this->product();
        return $product->getResults()->hasMany('App\Models\Admin\ProductTag', 'product_id');
    }


    public function SharedQty()
    {
        return $this->hasOne('ChannelSkuExtension', 'channel_sku_id');
    }

    public function orderItems()
    {
        return $this->morphMany('App\Models\Admin\OrderItem', 'ref', 'ref_type', 'ref_id');
    }

    public function quantityLogApp ()
    {
        return $this->morphMany('App\Models\Admin\QuantityLogApp', 'ref', 'ref_table', 'ref_table_id');
    }

    public function reservedQuantity ()
    {
        return $this->hasOne('App\Models\Admin\ReservedQuantity', 'channel_sku_id');
    }

    public function scopeIsActive($query)
    {
        return $query->where('channel_sku_active',1);
    }

    public function scopeOnDistributionCenter($query, $dc_id)
    {
        return $query->join('channels', function ($join) {
                            $join->on('channels.channel_id', '=', 'channel_sku.channel_id');
                            })
                     ->join('distribution_center', function ($join) {
                            $join->on('distribution_center.distribution_ch_id', '=', 'channels.channel_id');
                     })
                     ->where('distribution_center.distribution_center_id', '=', $dc_id);
    }

    // Elasticsearch
    public static function boot()
    {
        parent::boot();

        ChannelSKU::updated(function ($Obj) {
            $product = Product::find($Obj->product_id);
            if(!is_null($product))$product->updateElasticSearch();
        });

        ChannelSKU::created(function ($Obj) {
            Product::find($Obj->product_id)->updateElasticSearch();
        });

        ChannelSKU::deleted(function ($Obj) {
            Product::find($Obj->product_id)->deleteElasticsearch();
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

        $cskus = $data;
        $single = false;

        if (empty($data[0])) {
            $cskus = [$cskus];
            $single = true;
        }

        $result = array();
        foreach ($cskus as $cs) {
            // \Log::info(print_r($cs->toArray(), true));
            $response  = new \stdClass();
            $response->id = intval($cs->sku_id);
            // $response->channel_sku_id = intval($cs->channel_sku_id);
            $response->hubwire_sku = $cs->sku->hubwire_sku;
            $response->product_id = $cs->sku->product_id;
            $response->weight = $cs->sku->sku_weight;
            $response->quantity = (intval($cs->channel_sku_quantity) < 0) ? 0 : intval($cs->channel_sku_quantity);
            $response->active = ($cs->channel_sku_active == true) ? 1 : 0;
            $response->retail_price = number_format($cs->channel_sku_price,2,'.','');
            $response->sale_price = number_format($cs->channel_sku_promo_price,2,'.','');
            $response->warehouse_coordinate = $cs->channel_sku_coordinates;
            // $response->channel_id = $cs->channel_id;
            // $sku->supplier_code = $channel_sku->sku->sku_supplier_code;
            // $sku->barcode = $channel_sku->sku->sku_barcode;
            $option = new \stdClass();
            foreach ($cs->sku_options as $tmp) {
                $option->{$tmp->option_name} = $tmp->option_value;
            }
            $response->options = $option;
            $tags = array();
            // foreach ($cs->tags as $tag) {
            //     $tags[] = $tag->tag_value;
            // }
            // $response->tags = $tags;
            $response->created_at = $cs->created_at;
            $response->updated_at = $cs->updated_at;

            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }
}
