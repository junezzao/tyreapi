<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class ProductMediaThirdParty extends BaseModel {
	protected $fillable = [];
	protected $primaryKey = 'product_media_third_partyid';
	protected $guarded = array('product_media_third_partyid');
	protected $table = 'product_media_third_party';
	
	public function media(){
		return $this->belongsTo('App\Models\Admin\ProductMedia','media_id');
	}
	
}