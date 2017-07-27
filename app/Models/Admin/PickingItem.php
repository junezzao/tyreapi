<?php

namespace App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class PickingItem extends BaseModel
{
	protected $table = "picking_items";
	
	protected $primaryKey = "id";
	
	protected $guarded = array('id');

	protected $morphClass = 'PickingItem';

    protected $with = ['item']; 

	
	public function manifest()
	{
		return $this->belongsTo('App\Models\Admin\PickingManifest', 'manifest_id');
	}
	
	/*public function orderitem()
	{
		return $this->hasOne('OrderItem', 'order_item_id')
	}*/

	public function channelsku()
	{
		return $this->belongsTo('App\Models\Admin\ChannelSKU', 'channel_sku_id')->with('sku','product');
	}
	
	public function item()
    {
    	return $this->morphTo();
    }
	/*
	public function getCreatedAtAttribute($value){
    	return BaseController::utcToClientTz($value, $this->client_id);
    }

    public function getUpdatedAtAttribute($value){
    	return BaseController::utcToClientTz($value, $this->client_id);
    }

    public function getBatchDateAttribute($value){
    	$value = date(Config::get('globals.carbonFormat.dateFormat'),strtotime(BaseController::utcToClientTz($value, $this->client_id)));
    	return $value;
    }
    */
}