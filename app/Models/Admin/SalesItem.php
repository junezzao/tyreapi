<?php
namespace App\Models\Admin;
class SalesItem extends \Eloquent
{
    protected $table = "sales_items";
    protected $primaryKey = "item_id";
    protected $guarded = "item_id";
    protected $fillable = array(
        'product_id',
        'product_type',
        'item_price',
        'sale_id',
        'item_quantity',
        'item_original_quantity',
        'item_original_price',
        'item_tax',
        'item_inclusive',
        'decremented_from',
        'item_discount'
        );

    public function getDates()
    {
        return [];
    }
    
    public function sales()
    {
        return $this->belongsTo("Sales", "sale_id");
    }
    
    public function product()
    {
        return $this->morphTo();
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
            if ($item->product_type!=='ChannelSKU') {
                continue;
            }
            $response  = new \stdClass();
            $sku = \SKU::with('product')->find($item->product->sku_id);
            $response->id = $item->item_id;
            $response->sku_id = intval($item->product->sku_id);
            $response->product_name = !empty($sku->product)?$sku->product->product_name:'';
            $response->hubwire_sku = !empty($sku)?$sku->hubwire_sku:'';
            $response->quantity = floatval($item->item_quantity);
            $response->returned_quantity = ($item->item_original_quantity>0)?($item->item_original_quantity - $item->item_quantity):0;
            $response->price = $item->item_price;
            $response->discount = $item->item_discount;
            $response->tax = $item->item_tax;
            $response->tax_inclusive = $item->tax_inclusive;
            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }
}
