<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;
use App\Models\Admin\Product;
use App\Models\Admin\Webhook;



class ProductTag extends BaseModel
{
    use SoftDeletes;
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'product_tags';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['product_id','value','created_at','updated_at','deleted_at'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];


    protected $primaryKey = 'id';

    public function toAPIResponse()
    {
        return $this->value;
    }

    public static function boot()
    {
        parent::boot();
        ProductTag::created(function ($Obj) {
            Product::withTrashed()->find($Obj->product_id)->updateElasticSearch();
        });

        ProductTag::updated(function ($Obj) {
            Product::withTrashed()->find($Obj->product_id)->updateElasticSearch();
        });

        ProductTag::deleted(function ($Obj) {
            Product::withTrashed()->find($Obj->product_id)->updateElasticSearch();
        });
    }
}
