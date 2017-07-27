<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class OrderHistory extends BaseModel
{
    protected $table = "order_history";

    protected $guarded = ['id'];

    protected $fillable = ['order_id', 'description', 'event', 'ref_type', 'ref_id', 'user_id'];

    /**
     *
     * Relationships
     *
     */
    public function ref()
    {
    	return $this->morphTo();
    }

    public function order()
    {
    	return $this->belongsTo('App\Models\Admin\Order');
    }
}
