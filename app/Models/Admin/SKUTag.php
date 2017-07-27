<?php
namespace App\Models\Admin;
use Illuminate\Database\Eloquent\SoftDeletes;

class SKUTag extends \Eloquent
{
    
    use SoftDeletes;
    protected $table = 'sku_tag';
    protected $dates = ['deleted_at'];
    protected $guarded = array('tag_id');
    protected $primaryKey = 'tag_id';
    protected $fillable = ['tag_value','sku_id','created_at','updated_at','deleted_at'];
    
    public function getDates()
    {
        return [];
    }
    
    public function SKU()
    {
        return $this->belongsTo('App\Models\Admin\SKU', 'sku_id');
    }
}
