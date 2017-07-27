<?php namespace App\Models\Admin;

use App\Models\BaseModel;

class SoldQuantity extends BaseModel {
	protected $table = "sold_quantity";

	protected $primaryKey = "sold_id";

	protected $guarded = array('sold_id');

	protected $fillable = ["channel_sku_id", "quantity"];

	public function channel_sku()
	{
		return $this->belongsTo('App\Models\Admin\ChannelSKU', 'channel_sku_id');
	}
}