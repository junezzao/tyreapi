<?php

namespace App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;
use App\Models\Admin\GTOManifest;
use App\Models\Admin\SKU;

class GTOItem extends BaseModel
{
	protected $table = "gto_manifest_items";
	
	protected $primaryKey = "id";
	
	protected $guarded = array('id');

	protected $morphClass = 'GTOItem';

	
	public function manifest()
	{
		return $this->belongsTo('App\Models\Admin\GTOManifest', 'gto_id', 'id');
	}
	
	public function sku()
	{
		return $this->belongsTo('App\Models\Admin\SKU', 'sku_id')->with('combinations','product');
	}
	
	
}