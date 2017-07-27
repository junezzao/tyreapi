<?php
namespace App\Models\Admin;
use App\Models\Admin\Product;
use App\Models\Admin\Webhook;

use App\Models\BaseModel;
class SKUCombination extends BaseModel
{
    protected $table = "sku_combinations";
    
    protected $primaryKey = "combination_id";
    
    protected $guarded = array('combination_id');

    protected $fillable = ['sku_id','option_id','created_at','updated_at','deleted_at'];
    
    //this table is going to have a lot of deletes whenever sku updates, and each rows not that important
    //protected $softDelete = true;

    public function getDates()
    {
        return [];
    }
    
    public function SKU()
    {
        return $this->belongsTo('App\Models\Admin\SKU', 'sku_id');
    }
    
    public function option()
    {
        return $this->belongsTo('App\Models\Admin\SKUOption', 'option_id');
    }

    public static function boot()
    {
        parent::boot();
        SKUCombination::created(function ($Obj) {
            Product::find($Obj->SKU->product_id)->updateElasticSearch();
        });

        SKUCombination::updated(function ($Obj) {
            Product::find($Obj->SKU->product_id)->updateElasticSearch();
        });

        SKUCombination::deleted(function ($Obj) {
            Product::find($Obj->SKU->product_id)->updateElasticSearch();
        });
    }
    
}
