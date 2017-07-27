<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BaseModel;

class Channel extends BaseModel
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'channels';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'address', 'website_url', 'channel_type_id', 'issuing_company','merchant_id_bk', 'currency', 'timezone', 'status', 'docs_to_print'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $primaryKey = 'id';

    protected $morphClass = 'Channel';

    protected $with = ['issuing_company'];

    // protected $hidden = ['created_at','deleted_at','updated_at'];

    public static $rules = array(
                'channel_name'=>'required|max:50',
                'channel_address'=>'required_without:channel_web',
                'channel_web'=>'required_without:channel_address|required_if:channel_type,1',
                'channel_type'=>'required',
                'support_email'=>'required_if:channel_type,1|email',
                'noreply_email'=>'required_if:channel_type,1|email',
                'finance_email'=>'required_if:channel_type,1|email',
                'marketing_email'=>'required_if:channel_type,1|email',
                'issuing_company'=>'exist:issuing_companies.id'
                /*
                'ipay_merchant_code'=>'required_if:channel_type,1',
                'ipay_key'=>'required_if:channel_type,1',
                'paypal_email'=>'required_if:channel_type,1',
                'paypal_token'=>'required_if:channel_type,1',
                'google_analytics'=>'required_if:channel_type,1',
                'channel_logo'=>'required_if:channel_type,1|sometimes',
                'channel_template'=>'required_if:channel_type,1',
                'facebook_app_id'=>'required_if:channel_type,1',
                'facebook_app_secret'=>'required_if:channel_type,1'	*/
    );

    public function getDates()
    {
        return [];
    }

    /**
     *
     * Relationships
     *
     */
    public function merchants()
    {
        return $this->belongsToMany('App\Models\Admin\Merchant');
    }

    public function channel_detail()
    {
        return $this->hasOne('App\Models\Admin\ChannelDetails');
    }

    public function channel_type()
    {
        return $this->belongsTo('App\Models\Admin\ChannelType');
    }

    public function purchase()
    {
        return $this->hasOne('App\Models\Admin\Purchase');
    }

    public function client()
    {
        return $this->belongsTo('Client', 'client_id');
    }

    public function sku()
    {
        return $this->hasMany('ChannelSKU', 'channel_id');
    }

    // to merge into channel_detail
    public function channel_details()
    {
        return $this->hasOne('ChannelDetails', 'channel_id');
    }

    public function oauth_client()
    {
        return $this->hasOne('\OAuthClient', 'authenticatable_id');
    }

    public function channel_group()
    {
        return $this->hasMany('ChannelGroupChannel', 'channel_id', 'channel_id')->with('channel_group');
    }

    public function partner()
    {
        return $this->belongsTo('Partner', 'partner_id');
    }

    public function users()
    {
        return $this->belongsToMany('App\Models\User')->withTimestamps();
    }

    public function issuing_company()
    {
        return $this->belongsTo('App\Models\Admin\IssuingCompany', 'issuing_company');
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('status', 'asc')->orderBy('name', 'asc');
        });
    }

}
