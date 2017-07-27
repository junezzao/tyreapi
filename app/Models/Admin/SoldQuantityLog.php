<?php namespace App\Models\Admin;
use App\Models\BaseModel;

class SoldQuantityLog extends BaseModel {
	protected $table = "sold_quantity_log";

	protected $primaryKey = "log_id";

	protected $guarded = array('log_id');

	protected $fillable = ["channel_sku_id", "quantity_old", "quantity_new"];

	public function channel_sku()
	{
		return $this->belongsTo('App\Models\Admin\ChannelSKU', 'channel_sku_id');
	}
}