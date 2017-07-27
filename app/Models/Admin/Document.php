<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class Document extends BaseModel {
	protected $table = "documents";
	
	protected $primaryKey = "document_id";
	
	protected $guarded = "document_id";
	
	protected $fillable = array('sale_id','document_type','item_id','document_content');
	
}