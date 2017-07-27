<?php
namespace App\Models\Admin;

use App\Models\BaseModel;

class ReservedQuantityLog extends BaseModel
{
    protected $table = "reserved_quantities_log";

    public $timestamps = false;

    protected $primaryKey = "log_id";

    protected $guarded = array('log_id');

    protected $fillable = ["channel_sku_id", "quantity_old", "quantity_new", "order_id", "order_status", "item_id", "item_status"];

    public function channel_sku()
    {
        return $this->belongsTo('ChannelSKU', 'channel_sku_id');
    }
}
