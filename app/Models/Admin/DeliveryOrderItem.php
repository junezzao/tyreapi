<?php 
namespace App\Models\Admin;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;

class DeliveryOrderItem extends BaseModel
{
    use SoftDeletes;
    protected $fillable = ['channel_sku_id', 'quantity', 'status', 'do_id'];
    protected $table = 'delivery_orders_items';
    protected $guarded = array('id');
    protected $primaryKey = 'id';
    protected $hidden = array( "deleted_at", "created_at", "updated_at");
    protected $with = ['ref'];
    
    public function getDates()
    {
        return [];
    }
    
    public function ref()
    {
        return $this->belongsTo('App\Models\Admin\ChannelSKU', 'channel_sku_id')->with('product', 'sku', 'sku_options', 'tags', 'channel');
    }

    public function channel_sku()
    {
        return $this->belongsTo('App\Models\Admin\ChannelSKU', 'channel_sku_id')->with('product', 'sku', 'sku_options', 'tags', 'channel');
    }
    
    public function deliveryOrder()
    {
        return $this->belongsTo('App\Models\Admin\DeliveryOrder', 'do_id');
    }
}
