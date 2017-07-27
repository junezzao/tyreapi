<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;

class WebhooksRepository extends Repository
{
    public function model()
    {
        return 'App\Models\Admin\Webhook';
    }
   
    public function create(array $inputs)
    {
        // Inputs validations
        $v = \Validator::make($inputs, [
            'channel_id' => 'required|integer|min:1',
            'topic' => 'required|unique:webhooks,topic,NULL,webhook_id,channel_id,'.$inputs['channel_id'].',type,1|in:'.implode(',', config('api.webhook_events')),
            'address' => 'required|url'
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        unset($inputs['access_token']);
        $webhook = parent::create($inputs);
        return $this->find($webhook->webhook_id);
    }

    
    public function update(array $data, $id, $attribute="webhook_id")
    {
        // Inputs validations
        
        $v = \Validator::make($data, [
            'topic' => 'sometimes|required|in:'.implode(',', config('api.webhook_events')),
            'address' => 'required|url'
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        unset($data['access_token']);
        if (!empty($data['format'])) {
            unset($data['format']);
        }

        $webhook = parent::update($data, $id, $attribute);
        return $this->find($id);
    }
}
