<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends \Eloquent
{
    use SoftDeletes;
    protected $table = 'clients';
    protected $primaryKey = 'client_id';
    protected $guarded = array('client_id');
    protected $hidden = ['created_at','deleted_at','updated_at'];
    
    protected $fillable = array(
        'client_name',
        'client_contact_person',
        'client_contact_number',
        'client_address',
        'admin_email',
        'admin_name',
        'admin_password',
        'client_timezone',
        'client_currency',
        'channel_name',
        'channel_type',
        'channel_web',
        'support_email',
        'noreply_email',
        'finance_email',
        'marketing_email',
        'ipay_merchant_code',
        'ipay_key',
        'paypal_email',
        'paypal_token',
        'google_analytics',
    );
    
    public static $rules = array(
        'client_name'                => 'required|alpha_spaces|min:2',
        'client_contact_person'        => 'required|alpha_spaces|min:2',
        'client_contact_number'        => 'required',
        'client_address'            => 'required',
        'admin_email'                => 'required|email',
        'admin_name'                => 'required|alpha_spaces',
        'admin_password'            => 'required|between:10,18|confirmed',
        'admin_password_confirmation' => 'required|between:10,18',
        'client_timezone'            => 'required',
        'client_currency'            => 'required',
        'support_email'                => 'email',
        'noreply_email'                => 'email',
        'finance_email'                => 'email',
        'marketing_email'            => 'email',
        /*'channel_name'				=> 'required',
        'channel_type'				=> 'required',
        'channel_web'				=> 'required_if:channel_type,1',
        'support_email'				=> 'required_if:channel_type,1|email',
        'noreply_email'				=> 'required_if:channel_type,1|email',
        'finance_email'				=> 'required_if:channel_type,1|email',
        'marketing_email'			=> 'required_if:channel_type,1|email',
        'ipay_merchant_code'		=> 'required_if:channel_type,1',
        'ipay_key'					=> 'required_if:channel_type,1',
        'paypal_email'				=> 'required_if:channel_type,1',
        'paypal_token'				=> 'required_if:channel_type,1',
        'google_analytics'			=> 'required_if:channel_type,1',*/
    );
    
    public function getDates()
    {
        return [];
    }
    
    public function purchases()
    {
        return $this->hasMany('Purchase', 'client_id');
    }
    
    public function admins()
    {
        return $this->hasMany('ClientAdmin', 'client_id');
    }

    public function channels()
    {
        return $this->hasMany('Channel', 'client_id');
    }
}
