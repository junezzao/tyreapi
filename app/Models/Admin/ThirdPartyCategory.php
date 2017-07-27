<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class ThirdPartyCategory extends BaseModel {
	protected $table = "third_party_categories";
	
	protected $primaryKey = "id";
	
	protected $guarded = array('id');
	
	protected $fillable = array("category_id", "channel_id", "category_code", 'ref_id', 'tags');
}