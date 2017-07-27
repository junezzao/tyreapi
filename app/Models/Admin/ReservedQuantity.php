<?php
namespace App\Models\Admin;

class ReservedQuantity extends \Eloquent
{
    protected $table = "reserved_quantities";

    protected $primaryKey = "id";

    protected $guarded = array('id');

    protected $fillable = ["channel_sku_id", "quantity"];

    public function channel_sku()
    {
        return $this->belongsTo('ChannelSKU', 'channel_sku_id');
    }
}
