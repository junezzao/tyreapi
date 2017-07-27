<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;

class ChannelDetails extends BaseModel
{
    use SoftDeletes;
    protected $table = 'channel_details';
    protected $primaryKey = 'id';
    protected $guarded = array('id');
    protected $appends = ['raw_extra_info'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['channel_id', 'support_email', 'noreply_email', 'finance_email', 'marketing_email',
                            'channel_logo', 'api_key', 'api_password', 'api_secret', 'returns_chargable', 'webhook_signature', 'money_flow', 'extra_info', 'shipping_default', 'shipping_rate', 'use_shipping_rate', 'picking_manifest', 'sale_amount'];
    
    /*protected $fillable = ['support_email', 'noreply_email','finance_email','marketing_email','ipay_merchant_code','ipay_key','paypal_email','channel_id',
                        'paypal_token','google_analytics','channel_logo','channel_template','facebook_app_id','facebook_app_secret','channel_title',
                        'channel_description', 'channel_keyword','api_key','api_secret','api_password','webhook_signature','extra_info'];
    */
                        
    public function getDates()
    {
        return [];
    }
    
    /**
     *
     * Relationships
     *
     */
    public function channel()
    {
        return $this->belongsTo('App\Models\Admin\Channel');
    }

    // to override unset channel custom field values using Channel Type custom field DEFAULT values
    public function getExtraInfoAttribute()
    {
        $channelTypeFields = json_decode($this->channel->channel_type->fields, true);
        $channelTypeFields = is_array($channelTypeFields) ? $channelTypeFields : array(); // to ensure $channelTypeFields is always an array

        $channelFields = json_decode($this->attributes['extra_info'], true);
        $channelFields = is_array($channelFields) ? $channelFields : array(); // to ensure $channelFields is always an array

        foreach($channelTypeFields as $field) {
            if( isset($field['default']) && !empty($field['default']) && // if channel type custom field has default value
                (!isset($channelFields[$field['api']]) || empty($channelFields[$field['api']])) // if channel type custom field does not have value
            ) {
                $channelFields[$field['api']] = $field['default'];
            }
        }
        return json_encode($channelFields);
    }

    public function getRawExtraInfoAttribute()
    {
        return $this->attributes['extra_info'];
    }

    //temporary in case migration is not yet implemented ( API keys rename)
    /* Commented because not being relevant.
    public function setApiKeyAttribute($value)
    {
        $key = isset($this->attributes['api_key'])?'api_key':'shopify_api_key';
        $this->attributes[$key] = $value;
    }
    public function getApiKeyAttribute()
    {
        return isset($this->attributes['api_key'])?$this->attributes['api_key']:$this->attributes['shopify_api_key'];
    }
    public function setApiSecretAttribute($value)
    {
        $key = isset($this->attributes['api_secret'])?'api_secret':'shopify_api_secret';
        $this->attributes[$key] = $value;
    }
    public function getApiSecretAttribute()
    {
        return isset($this->attributes['api_secret'])?$this->attributes['api_secret']:$this->attributes['shopify_api_secret'];
    }
    public function setApiPasswordAttribute($value)
    {
        $key = isset($this->attributes['api_password'])?'api_password':'shopify_api_password';
        $this->attributes[$key] = $value;
    }
    public function getApiPasswordAttribute()
    {
        return isset($this->attributes['api_password'])?$this->attributes['api_password']:$this->attributes['shopify_api_password'];
    }
    */
}
