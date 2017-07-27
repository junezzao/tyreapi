<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class OrderStatusLog extends BaseModel
{
    protected $table = "order_status_log";

    protected $primarKey = "id";

    protected $fillable = ["user_id", "order_id", "from_status", "to_status", "created_at"];

    protected $morphClass = 'StatusLog';

    public function getDates()
    {
        return [];
    }

    public function order ()
    {
    	return $this->belongsTo('App\Models\Admin\Order');
    }

    public function orderHistory()
    {
        return $this->morphMany('App\Models\Admin\OrderHistory', 'ref', 'ref_type', 'ref_id');
    }
}