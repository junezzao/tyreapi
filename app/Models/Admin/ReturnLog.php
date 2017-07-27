<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class ReturnLog extends BaseModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'return_log';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['member_id', 'user_id', 'order_id', 'order_item_id', 'quantity',
                            'refund_type', 'amount', 'status', 'remark', 'ref_id', 'completed_at',
                            'updated_at', 'created_at'];

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
    public function order ()
    {
        return $this->belongsTo('App\Models\Admin\Order', 'order_id');
    }

    public function item ()
    {
        return $this->belongsTo('App\Models\Admin\OrderItem', 'order_item_id');
    }

    public function quantityLogApp ()
    {
        return $this->morphMany('App\Models\Admin\QuantityLogApp', 'ref', 'ref_table', 'ref_table_id');
    }
}
