<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class ProductThirdParty extends BaseModel {
	protected $fillable = [];

	protected $table = "product_third_party";
	
	protected $primaryKey = "product_third_party_id";
	
	protected $guarded = array('product_third_party_id');
}