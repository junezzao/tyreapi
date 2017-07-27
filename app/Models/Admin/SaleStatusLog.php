<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SaleStatusLog extends Model
{
    protected $table = "order_status_log";

    protected $primarKey = "id";

    protected $fillable = ["admin_id", "sale_id", "from_status", "to_status", "created_at"];

    protected $morphClass = 'StatusLog';

    public function getDates()
    {
        return [];
    }

    public function order ()
    {
    	return $this->belongsTo('App\Models\Admin\Order');
    }

    public function sale ()
    {
        return $this->belongsTo('App\Models\Admin\Sales');
    }


    public function orderHistory()
    {
        return $this->morphMany('App\Models\Admin\OrderHistory', 'ref', 'ref_type', 'ref_id');
    }
}
