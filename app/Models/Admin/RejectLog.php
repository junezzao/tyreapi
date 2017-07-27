<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class RejectLog extends BaseModel {
	/**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'reject_log';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['outbound','user_id', 'hw_admin', 'remarks', 'sku_id', 'channel_id', 'quantity', 'created_at'];

	protected $primaryKey = "id";

	public  $timestamps = false;

    // eager load relations to improve performance
    public function channel()
    {
        return $this->belongsTo('App\Models\Admin\Channel', 'channel_id', 'id')->select(array('id', 'name', 'issuing_company'));
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id')->withTrashed()->select(array('id', 'first_name', 'last_name'));
    }

    public function sku()
    {
        return $this->belongsTo('App\Models\Admin\SKU', 'sku_id')
            ->with('productName', 'merchantName');
    }

    public function quantityLogApp ()
    {
        return $this->morphMany('App\Models\Admin\QuantityLogApp', 'ref', 'ref_table', 'ref_table_id');
    }

    public function scopeOutbound($query)
    {
        return $query->where('outbound','=','1');
    }
}
