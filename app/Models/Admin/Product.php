<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Admin\ProductMedia;
use App\Models\Admin\Brand;
use App\Models\BaseModel;
use App\Models\Admin\Webhook;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Jobs\UpdateElasticSearch;


class Product extends BaseModel
{
    use SoftDeletes;
    protected $table = 'products';
    protected $primaryKey = 'id';
    protected $guarded = ['id'];
    protected $fillable = [
                'name',
                'description',
                'brand',
                'description2',
                'category_id',
                'merchant_id',
                'client_id',
                'brand_id',
                'default_media',
                'third_party',
                'active'
            ];
    protected $casts = [
        'active' => 'boolean',
    ];

    protected $appends = ['brand_name'];
    // protected $appends = ['quantity'];

    public function category()
    {
        return $this->belongsTo('App\Models\Admin\Category','category_id')->withTrashed();
    }

    public function getDates()
    {
        return [];
    }


    public function deactivate()
    {
        $this->active = 0;
        return $this->save();
    }

    public function activate()
    {
        $this->active = 1;
        return $this->save();
    }

    public function scopeIsActive($query)
    {
        return $query->where('active',1);
    }

    public function sku()
    {
        return $this->hasMany('App\Models\Admin\SKU', 'product_id')->with('combinations','batch');
    }

    public function quantity()
    {
        if (!$this->relationLoaded('sku_in_channel')) $this->load('sku_in_channel');
        $related = $this-> getRelation('sku_in_channel');
        return ($related) ? (int) $related->sum('channel_sku_quantity') : 0;
    }

    public function getQuantityAttribute()
    {
        return $this->quantity();
    }

    public function channel_sku()
    {
        return $this->hasMany('App\Models\Admin\ChannelSKU', 'product_id')->with('channel');
    }

    public function media()
    {
        return $this->hasMany('App\Models\Admin\ProductMedia', 'product_id')->with('media')->orderBy('sort_order', 'asc');
    }

    public function media_trashed()
    {
        return $this->hasMany('App\Models\Admin\ProductMedia', 'product_id')->with('media_trashed')->onlyTrashed();
    }

    public function tags()
    {
        return $this->hasMany('App\Models\Admin\ProductTag','product_id');
    }

    public function sku_in_channel()
    {
        return $this->hasMany('App\Models\Admin\ChannelSKU', 'product_id')->with('sku', 'sku_options', 'channel', 'channel_details')->orderBy('sku_id', 'asc');
    }

    public function batch()
    {
        return $this->hasMany('App\Models\Admin\SKU', 'product_id')->with('batch');
    }

    public function merchant()
    {
        return $this->belongsTo('App\Models\Admin\Merchant','merchant_id','id')->withTrashed();
    }

    public function Essku_in_channel()
    {
        return $this->hasMany('App\Models\Admin\ChannelSKU', 'product_id')->with('skuWithbatch', 'sku_options', 'channel', 'SharedQty');
    }

    public function product_category()
    {
        return $this->hasOne('App\Models\Admin\ProductThirdPartyCategory');
    }

    public function scopeInChannel($query, $channel_id, $sSearch = null, $admin = false)
    {
        return $query->join('sku', 'sku.product_id', '=', 'products.id')
                                                ->join('channel_sku', 'channel_sku.product_id', '=', 'products.id')
                                                ->join('sku_combinations', 'sku_combinations.sku_id', '=', 'sku.sku_id')
                                                ->join('sku_options', 'sku_options.option_id', '=', 'sku_combinations.option_id')
                                                ->join('sku_tag', 'sku_tag.sku_id', '=', 'sku.sku_id')
                                                ->where('channel_id', '=', $channel_id)
                                                ->where(function ($query) use ($admin, $sSearch) {
                                                    if (!$admin) {
                                                        $query->where('channel_sku_active', '=', true);
                                                    }
                                                    if ($sSearch['hasPQuantity']) {
                                                        $query->where('channel_sku_quantity', '>', 0);
                                                    }
                                                })
                                                // ->where(function($query) use ($sSearch)
                                                // {
                                                // 	if ($sSearch!== null)
                                                // 	{
                                                // 		$query->OrWhere('tag_value', '=', $sSearch)
                                                // 		->OrWhere('option_value', '=', $sSearch)
                                                // 		->OrWhere('name', 'LIKE', "%$sSearch%")
                                                // 		->OrWhere('description_2', 'LIKE', "%$sSearch%");
                                                // 	}
                                                // })
                                                ;
    }

    public function default_media()
    {
        return $this->belongsTo('App\Models\Admin\ProductMedia', 'default_media','id')->with('media');
    }

    public function brands()
    {
        return $this->hasOne('App\Models\Admin\Brand', 'id','brand_id')->withTrashed();
    } 

    public function getBrandNameAttribute()
    {
        return $this->brands->name;
    }

    public function brand()
    {
        return $this->hasOne('App\Models\Admin\Brand', 'id','brand_id');
    }

    public function updateElasticSearch()
    {
        dispatch( new UpdateElasticSearch($this->id) );
        return $this;
    }

    public function deleteElasticsearch()
    {
        Product::deleteProduct($this->id);
        return $this;
    }

    public static function deleteProduct($id)
    {
        $params = array();
        $params['index'] = env('ELASTICSEARCH_INDEX','products');
        $params['type']  = 'inventory';
        $params['id']    = $id;
        \Es::delete($params);
    }

    // Elasticsearch
    public static function insertProduct($data)
    {
        // Post To ElasticSearch

        $doc = array();

        // ['product']
        $doc['id'] =  $data['id'];
        $doc['client_id'] = $data['client_id'];
        $doc['category_id'] = $data['category_id'];
        $doc['merchant_id'] = $data['merchant_id'];
        $doc['name'] = $data['name'];
        $doc['description'] = $data['description'];
        $doc['description2'] = $data['description2'];
        $doc['active'] = $data['active'];
        $doc['created_at'] = $data['created_at'];
        $doc['updated_at'] = $data['updated_at'];
        // ['product'] Ends

        $doc['merchant_name'] = isset($data['merchant'])? $data['merchant']['name']:'';
        // $doc['merchant'] = isset($data['merchant'])?$data['merchant']:null;

        if(!empty($data['tags']))
        {
            foreach ($data['tags'] as $tags_key => $tags_val) {
                // ['tags']
                $doc['tags'][$tags_key]['tag_id'] = $tags_val['id'];
                $doc['tags'][$tags_key]['value'] = $tags_val['value'];
                // ['tags'] Ends
            }
        }

        if(!empty($data['default_media']))
        {
            $media = $data['default_media']['media'];
            $media['product_media_id'] = $data['default_media']['id'];
            $doc['default_media'] = $media;
        }

        // ['media']
        if(!empty($data['media']))
        {
            foreach($data['media'] as $product_media)
            {
                $media = $product_media['media'];
                $media['product_media_id'] = $product_media['id'];
                $doc['media'][] = $media;
            }
        }
        // ['media'] Ends

        if(!empty($data['brand']))
        {
            $doc['brand'] = $data['brand'];
        }
        
        if(!empty($data['category']))
        {
            // $doc['category'] = $data['category'];
        }
        
        $quantity = 0;
        if(!empty($data['essku_in_channel']))
        {

            foreach ($data['essku_in_channel'] as $channel__key => $channel_val)
            {
                $quantity += $channel_val['channel_sku_quantity'];

                // ['channel_sku']
                $doc['sku_in_channel'][$channel__key]['channel_id'] = $channel_val['channel_id'];
                $doc['sku_in_channel'][$channel__key]['channel_sku_id'] = $channel_val['channel_sku_id'];
                $doc['sku_in_channel'][$channel__key]['sku_id'] = $channel_val['sku_id'];
                $doc['sku_in_channel'][$channel__key]['channel_sku_active'] = $channel_val['channel_sku_active'];
                $doc['sku_in_channel'][$channel__key]['ref_id'] = $channel_val['ref_id'];
                $doc['sku_in_channel'][$channel__key]['channel_sku_quantity'] = $channel_val['channel_sku_quantity'];
                $doc['sku_in_channel'][$channel__key]['shared_quantity'] = !empty($channel_val['shared_qty']['shared_quantity'])?$channel_val['shared_qty']['shared_quantity']:0;
                $doc['sku_in_channel'][$channel__key]['channel_sku_price'] = $channel_val['channel_sku_price'];
                $doc['sku_in_channel'][$channel__key]['channel_sku_live_price'] = $channel_val['channel_sku_live_price'];
                $doc['sku_in_channel'][$channel__key]['channel_sku_promo_price'] = $channel_val['channel_sku_promo_price'];
                $doc['sku_in_channel'][$channel__key]['channel_sku_coordinates'] = $channel_val['channel_sku_coordinates'];
                $doc['sku_in_channel'][$channel__key]['sync_status'] = $channel_val['sync_status'];
                // ['channel_sku'] Ends

                //channel
                $doc['sku_in_channel'][$channel__key]['channel']['channel_id'] = $channel_val['channel_id'];
                $doc['sku_in_channel'][$channel__key]['channel']['name'] = $channel_val['channel']['name'];
                $doc['sku_in_channel'][$channel__key]['channel']['channel_type_id'] = $channel_val['channel']['channel_type_id'];
                $doc['sku_in_channel'][$channel__key]['channel']['website_url'] = $channel_val['channel']['website_url'];


                // ['sku']
                $doc['sku_in_channel'][$channel__key]['sku']['sku_id'] = $channel_val['sku_id'];
                $doc['sku_in_channel'][$channel__key]['sku']['client_sku'] = strtolower($channel_val['sku_withbatch']['client_sku']);
                $doc['sku_in_channel'][$channel__key]['sku']['sku_supplier_code'] = strtolower($channel_val['sku_withbatch']['sku_supplier_code']);
                $doc['sku_in_channel'][$channel__key]['sku']['sku_weight'] = $channel_val['sku_withbatch']['sku_weight'];
                $doc['sku_in_channel'][$channel__key]['sku']['hubwire_sku'] = strtolower($channel_val['sku_withbatch']['hubwire_sku']);
                $doc['sku_in_channel'][$channel__key]['sku']['created_at'] = $channel_val['sku_withbatch']['created_at'];
                $doc['sku_in_channel'][$channel__key]['sku']['updated_at'] = $channel_val['sku_withbatch']['updated_at'];
                // ['sku'] Ends
                $suppliers = [];
                foreach ($channel_val['sku_withbatch']['batch'] as $batch_key => $batch_val) {
                    // ['batch']
                    $doc['sku_in_channel'][$channel__key]['sku']['batch'][$batch_key]['batch_id'] = $batch_val['batch_id'];
                    $suppliers[] = $batch_val['batch']['supplier_id'];
                    $doc['sku_in_channel'][$channel__key]['sku']['batch'][$batch_key]['batch_date'] = $batch_val['created_at'];
                    // ['batch'] Ends
                }

                $doc['supplier_id'] = array_values(array_unique($suppliers));

                foreach ($channel_val['sku_options'] as $option_key => $option_val) {
                    // ['options']
                    $doc['sku_in_channel'][$channel__key]['options'][$option_key]['option_name'] = $option_val['option_name'];
                    $doc['sku_in_channel'][$channel__key]['options'][$option_key]['option_value'] = $option_val['option_value'];
                    // ['options'] Ends
                }
            }
        }

        $doc['quantity'] = $quantity;
        $params = array();
        $params['index'] = env('ELASTICSEARCH_INDEX','products');
        $params['type']  = 'inventory';
        $params['id']    = $doc['id'];
        $exists = \Es::exists($params);
        if(intval($exists)!==0)
            $params['body']['doc']  = $doc;
        else
            $params['body']  = $doc;

        $ret = $exists?\Es::update($params):\Es::create($params);
        //\Log::info('Product Updated to Elasticsearch : '.$doc['id']);
    }

    public static function boot()
    {
        parent::boot();

        Product::updated(function ($Obj) {
            Product::find($Obj->id)->updateElasticSearch();
        });

        Product::created(function ($Obj) {
            Product::find($Obj->id)->updateElasticSearch();
        });

        Product::deleted(function ($Obj) {
            $Obj->deleteElasticSearch();
        });
    }

    public function toAPIResponse()
    {
        return $this->apiResponse($this);
    }

    public static function apiResponse($data, $criteria = null)
    {
        //\Log::info(print_r($data->toArray(), true));
        if (empty($data->toArray())) {
            return null;
        }

        $products = $data;
        $single = false;

        if (empty($products[0])) {
            $products = [$products];
            $single = true;
        }

        $result = array();
        foreach ($products as $product) {
            
            $response  = new \stdClass();
            $response->id = $product->id;
            $response->name = $product->name;
            $response->description = str_replace("\r\n","<br>",$product->description);
            $response->sub_description = str_replace("\r\n","<br>",$product->description2);
            $response->quantity = ($product->quantity < 0) ? 0 : $product->quantity;
            $tags = $product->tags;
            $response->tags = $tags->toAPIResponse();
            $response->category = !is_null($product->product_category)?$product->product_category->toAPIResponse():null;

            $response->brand = !is_null($product->getRelation('brand'))?$product->getRelation('brand')->toAPIResponse():null;

            $active = 0;
            if (!empty($product->sku_in_channel)) {
                $response->sku = $product->sku_in_channel->toAPIResponse();
                foreach($response->sku as $cs)
                {
                    if($cs->active == 1)
                    {
                        $active = 1; break;
                    }
                }
                // $response->tags = implode(",",array_unique($tags));
            } elseif (!empty($product->sku)) {
                $response->sku = $product->sku->toAPIResponse();
                // $response->sku = $product->sku;
            }
            $response->active = $active;
            

            $response->media = !empty($product->media)?$product->media->toAPIResponse():null;
            $response->default_media = $product->default_media;

            $response->created_at =  $product->created_at;
            $response->updated_at = $product->updated_at;

            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }
}
