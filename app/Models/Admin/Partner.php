<?php
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends \Eloquent
{
    
    protected $fillable = array(
        'partner_name',
        'partner_contact',
        'partner_address',
    );

    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $table = 'partners';

    protected $primaryKey = 'partner_id';

    protected $guarded = array('partner_id');
        
    public static $rules = array(
        'partner_name'=>'required|max:100',
        'partner_contact'=>'required|max:100',
        'partner_address'=>'required'
    );
    
    public function getDates()
    {
        return [];
    }
    
    public function distribution_center()
    {
        return $this->hasMany('DistributionCenter', 'partner_id');
    }

    public function authenticatable()
    {
        return $this->morphOne('OAuthClient', 'authenticatable');
    }
}
