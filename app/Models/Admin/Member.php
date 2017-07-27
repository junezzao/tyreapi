<?php
namespace App\Models\Admin;

use App\Models\BaseModel;

class Member extends BaseModel
{
    protected $table = 'members';
    
    protected $primaryKey = 'id';
    
    protected $softDelete = true;
    
    protected $guarded = array('id', 'member_password');
    
    protected $hidden = array('member_password');
    
    protected $fillable = array(
        'member_name',
        'member_mobile',
        'member_birthday',
        'member_gender',
        'client_id',
        'channel_id',
        'member_type',
        'member_email'
    );
    
    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getDates()
    {
        return [];
    }
    
    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->attributes['member_password'];
    }

    /**
     * Get the e-mail address where password reminders are sent.
     *
     * @return string
     */
    public function getReminderEmail()
    {
        return $this->email;
    }
    
    public function getRememberToken()
    {
        return $this->remember_token;
    }
    
    public function setRememberToken($value)
    {
        $this->remember_token = $value;
    }
    
    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public function memberFacebook()
    {
        return $this->belongsTo('MemberFacebook', 'member_id', 'member_id');
    }

    public function memberThirdParty()
    {
        return $this->belongsTo('MemberThirdParty', 'member_id', 'member_id');
    }

    public function addresses()
    {
        return $this->hasMany('App\Models\Admin\Address');
    }

    public function orders()
    {
        return $this->hasMany('App\Models\Admin\Order');
    }

    public function toAPIResponse()
    {
        return $this->apiResponse($this);
    }

    public static function apiResponse($data, $criteria = null)
    {
        if (empty($data->toArray())) {
            return null;
        }
        
        $members = $data;
        $single = false;
            
        if (empty($data[0])) {
            $members = [$members];
            $single = true;
        }
        
        $result = array();
        foreach ($members as $member) {
            $response  = new \stdClass();
            $response->id = $member->id;
            $response->name = $member->member_name;
            $response->email = $member->member_email;
            $response->phone = $member->member_mobile;
            $response->created_at = $member->created_at;
            $response->updated_at = $member->updated_at;
            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }
}
