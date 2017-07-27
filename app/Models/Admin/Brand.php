<?php namespace App\Models\Admin;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BaseModel;
use DateTime;
use App\Models\Admin\ChannelSKU;

class Brand extends BaseModel
{
    use SoftDeletes;
    protected $fillable = ['id','name','prefix','merchant_id','active','created_at','updated_at','deleted_at'];
    protected $table = 'brands';
    protected $primaryKey = 'id';
    protected $guarded = array('id');
    protected $casts = [
        'active' => 'boolean',
    ];

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

    public function getDates()
    {
        return [];
    }

    public function products()
    {
        return $this->hasMany('App\Models\Admin\Product', 'brand_id', 'id');
    }

    public function productsTotal()
    {
      return $this->hasOne('App\Models\Admin\Product', 'brand_id', 'id')
        ->selectRaw('brand_id, count(*) as total')
        ->groupBy('brand_id');
    }

    public function productsTotalByChannel($brand_id, $channel_id)
    {
      return ChannelSKU::selectRaw('count(*) as total')
        ->leftJoin('products', 'products.id', '=', 'channel_sku.product_id')
        ->where('channel_sku.channel_id', $channel_id)
        ->where('products.brand_id', $brand_id)->first();
    }


    public function getCreatedAtAttribute($value)
    {
        $value = parent::getCreatedAtAttribute($value);
        $date = new DateTime($value);
        return $date->format('Y-m-d');
    }

    public function getUpdatedAtAttribute($value)
    {
        $value = parent::getUpdatedAtAttribute($value);
        $date = new DateTime($value);
        return $date->format('Y-m-d');
    }

    public function getProductsTotalAttribute()
    {
      if ( ! array_key_exists('productsTotal', $this->relations))
        $this->load('productsTotal');

      $related = $this->getRelation('productsTotal');

      return ($related) ? (int) $related->total : 0;
    }

    public function merchant()
    {
        return $this->belongsTo('App\Models\Admin\Merchant', 'merchant_id', 'id')->withTrashed();
    }

    public function scopeIsActive($query)
    {
        return $query->where('active',1);
    }

    public function scopeOfProduct($query, $product)
    {
        return $query->where('brand_id', '=', $product->brand_id);
    }

    public function toAPIResponse()
    {
        return $this->apiResponse($this);
    }

    public static function apiResponse($data, $criteria = null)
    {
        // \Log::info(print_r($data, true));
        if (empty($data->toArray())) {
            return null;
        }

        $brands = $data;
        $single = false;

        if (empty($brands[0])) {
            $brands = [$brands];
            $single = true;
        }

        $result = array();
        foreach ($brands as $brand) {
            $response  = new \stdClass();
            $response->id = $brand->id;
            $response->code = $brand->prefix;
            $response->name = $brand->name;
            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }

    public static function boot()
    {
        parent::boot();
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('active', 'desc')->orderBy('name', 'asc');
        });
    }
}
