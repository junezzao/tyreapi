<?php
namespace App\Models\Admin;
use App\Models\BaseModel;

class Address extends BaseModel
{
    protected $table = "shipping_addresses";
    
    protected $primaryKey = 'address_id';
    
    protected $guarded = array('address_id');
    
    public function getDates()
    {
        return [];
    }
    
    public function member()
    {
        return $this->belongsTo('Member', 'member_id');
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
        
        $addresses = $data;
        $single = false;
            
        if (empty($data[0])) {
            $addresses = [$addresses];
            $single = true;
        }
        
        $result = array();
        foreach ($addresses as $address) {
            $response  = new \stdClass();
            $response->id = $address->address_id;
            $response->address_line_1 = $address->address_first_line;
            $response->address_line_2 = $address->address_second_line;
            $response->city = $address->address_city;
            $response->postcode = $address->address_postal_code;
            $response->country = $address->address_country;
            $response->phone = $address->address_phone;
            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }
}
