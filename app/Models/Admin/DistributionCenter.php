<?php
use Illuminate\Database\Eloquent\SoftDeletes;

class DistributionCenter extends \Eloquent
{
    use SoftDeletes;

    protected $table = 'distribution_center';

    protected $primaryKey = 'distribution_center_id';
    protected $guarded = array('distribution_center_id');
    protected $hidden = ['created_at','deleted_at','updated_at'];
    
    protected $fillable = array('partner_id','default_sales_ch_id','distribution_ch_id','min_quantity');
    protected $dates = ['deleted_at'];

    public static $rules = array(
        'partner_id' => 'required',
        'default_sales_ch_id'  => 'required',
        'distribution_ch_id'  => 'required',
    );


    public function getDates()
    {
        return [];
    }
    
    public function default_sale_channel()
    {
        return $this->hasOne('Channel', 'channel_id', 'default_sales_ch_id');
    }
    
    public function channel()
    {
        return $this->belongsTo('Channel', 'distribution_ch_id', 'channel_id')->with('client');
    }
}
