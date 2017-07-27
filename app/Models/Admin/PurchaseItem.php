<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;

class PurchaseItem extends BaseModel
{
    use SoftDeletes;
    
    protected $table = 'purchase_items';
    
    protected $primaryKey = 'item_id';
    
    protected $guarded = ['item_id'];

    protected $fillable = [
        'batch_id',
        'sku_id',
        'item_cost',
        'item_quantity',
        'item_retail_price',
        'created_at',
        'updated_at',
        'deleted_at'
    ];
    
    
    
    public function getDates()
    {
        return [];
    }
    
    public function batch()
    {
        return $this->belongsTo('App\Models\Admin\Purchase', 'batch_id');
    }
    
    public function sku()
    {
        return $this->belongsTo('App\Models\Admin\SKU', 'sku_id')
        //->select('sku_id','sku_barcode','product_id')
        ->with('combinations', 'product');
    }

    public function skuWithTrashed()
    {
        return $this->belongsTo('App\Models\Admin\SKU', 'sku_id')
            ->with('combinations', 'productWithTrashed')->withTrashed();
    }
}
