<?php namespace App\Models\Admin;

use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Jobs\SendWebhook;
use App\Models\BaseModel;

class Webhook extends BaseModel
{
    protected $primaryKey = 'webhook_id';
    
    protected $guarded = array('webhook_id');
    
    protected $table = 'webhooks';

    public function getDates()
    {
        return [];
    }
    
    public static function apiResponse($data, $criteria = null)
    {
        if (empty($data->toArray())) {
            return null;
        }
        
        $webhooks = $data;
        $single = false;
            
        if (empty($data[0])) {
            $webhooks = [$webhooks];
            $single = true;
        }
        
        $result = array();
        foreach ($webhooks as $webhook) {
            $response  = new \stdClass();
            $response->id = $webhook->webhook_id;
            $response->topic = $webhook->topic;
            $response->address = $webhook->address;
            $response->created_at = $webhook->created_at;
            $response->updated_at = $webhook->updated_at;
            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }

    public static function sendWebhook($id, $event)
    {
        dispatch( new SendWebhook(['id'=>$id, 'event'=>$event]) );
    }
}
