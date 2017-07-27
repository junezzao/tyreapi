<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class QuantityLogApp extends BaseModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'quantity_log_app';

    protected $primaryKey = 'log_id';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['channel_sku_id', 'quantity_old', 'quantity_new', 'ref_table_id', 'ref_table', 'created_at', 'triggered_at'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['log_id'];

    protected $morphClass = 'QuantityLogApp';

    protected $with = ['ref']; 

    /**
     *
     * Relationships
     *
     */
    public function channel_sku()
    {
        return $this->belongsTo('App\Models\Admin\ChannelSKU', 'channel_sku_id');
    }

    public function ref ()
    {
        return $this->morphTo(null, 'ref_table', 'ref_table_id');
    }
}
