<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Media;
use App\Models\Admin\Product;
use App\Models\Admin\Webhook;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;

class ProductMedia extends BaseModel
{
    use SoftDeletes;
    protected $fillable = [];
    protected $primaryKey = 'id';
    protected $guarded = array('id');
    protected $table = 'product_media';
    
    public function getDates()
    {
        return [];
    }
    
    public function product()
    {
        return $this->belongsTo('App\Models\Admin\Product', 'product_id');
    }

    public function third_party()
    {
        return $this->hasMany('App\Models\Admin\ProductMediaThirdParty', 'id');
    }

    public static function boot()
    {
        parent::boot();
        ProductMedia::created(function ($Obj) {
            $product = Product::find($Obj->product_id);
            if($product){
                $product->updateElasticSearch();
            }
        });

        ProductMedia::updated(function ($Obj) {
            $product = Product::find($Obj->product_id);
            if($product){
                $product->updateElasticSearch();
            }
        });

        ProductMedia::deleted(function ($Obj) {
            $product = Product::find($Obj->product_id);
            if($product){
                $product->updateElasticSearch();
            }
        });
    }

    public function media()
    {
        return $this->belongsTo('App\Models\Media','media_id');
    }

    public function media_trashed()
    {
        return $this->belongsTo('App\Models\Media', 'media_id')->onlyTrashed();
    }

    public function toAPIResponse()
    {
        $this->load('media');
        $this->load('media_trashed');
        return $this->apiResponse($this);
    }

    public static function apiResponse($data, $criteria = null)
    {
        if (empty($data->toArray())) {
            return null;
        }
        $medias = $data;
        $single = false;
            
        if (empty($data[0])) {
            $medias = [$medias];
            $single = true;
        }
        
        $result = array();
        foreach ($medias as $media) {
            $mediaData = (empty($media->media) || count($media->media) < 1) ? $media->media_trashed : $media->media;

            $response  = new \stdClass();
            $response->id = $media->id;
            $response->url = str_replace($mediaData->ext,'',$mediaData->media_url).'_800x1148';
            $response->extension = $mediaData->ext;
            $response->order = $media->sort_order;
            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }
}
