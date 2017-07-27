<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class ProductThirdPartyCategory extends BaseModel {
	protected $fillable = ['product_id', 'channel_id', 'cat_id', 'cat_name'];

	protected $table = 'product_third_party_categories';

	protected $guarded = array('id');

	public function product()
	{
		return $this->belongsTo('App\Models\Admin\Product');
	}

	public function toAPIResponse()
    {
        return $this->apiResponse($this);
    }

    public static function apiResponse($data, $criteria = null)
    {
        // \Log::info(print_r($data, true));
        if (empty($data->toArray())) {
            return null;
        }

        $categories = $data;
        $single = false;

        if (empty($categories[0])) {
            $categories = [$categories];
            $single = true;
        }

        $result = array();
        foreach ($categories as $category) {
            $response  = new \stdClass();
            $response->id = $category->cat_id;
            $response->name = $category->cat_name;
            $result[] = $response;
        }
        
        return ($single)?$result[0]:$result;
    }
}