<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class SKUQuantityLog extends BaseModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'sku_quantity_log';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['sku_id', 'quantity','quantity_log_app_id', 'created_at', 'updated_at'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     *
     * Relationships
     *
     */
    public function sku()
    {
        return $this->belongsTo('App\Models\Admin\SKU', 'sku_id');
    }
    public function quantity_log_app()
    {
        return $this->hasMany('App\Models\Admin\QuantityLogApp', 'quantity_log_app_id','log_id');
    }
}
