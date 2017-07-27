<?php
namespace App\Models\Admin;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Admin\Product;
use App\Models\Admin\Webhook;
use App\Models\BaseModel;
use App\Models\Admin\SKU;

class SKUOption extends BaseModel
{
    use SoftDeletes;
    protected $table = "sku_options";
    protected $primaryKey = "option_id";
    protected $guarded = array('option_id');
    protected $fillable = ['option_name','option_value','created_at','updated_at','deleted_at'];

    public function getDates()
    {
        return [];
    }
    public function SKU()
    {
        return $this->belongsTo('App\Models\Admin\SKU', 'sku_id');
    }

    public function combinations()
    {
        return $this->hasMany('App\Models\Admin\SKUCombination', 'option_id');
    }

    public static function boot()
    {
        parent::boot();
        // SKUOption::created(function ($Obj) {
        //     $sku = SKU::find($Obj->sku_id);
        //     Product::find($sku->product_id)->updateElasticSearch();
        //     Webhook::sendWebhook($sku->product_id, 'product/updated'); 
        // });

        // SKUOption::updated(function ($Obj) {
        //     Product::find($Obj->SKU()->product_id)->updateElasticSearch();
        //     Webhook::sendWebhook($Obj->SKU()->product_id, 'product/updated'); 
        // });

        // SKUOption::deleted(function ($Obj) {
        //     Product::find($Obj->SKU()->product_id)->updateElasticSearch();
        //     Webhook::sendWebhook($Obj->SKU()->product_id, 'product/updated'); 
        // });
    }
}
