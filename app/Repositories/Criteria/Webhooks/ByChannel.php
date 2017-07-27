<?php namespace App\Repositories\Criteria\Webhooks;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByChannel extends Criteria
{
    
    private $channel_id;
    
    public function __construct($channel_id)
    {
        $this->channel_id = $channel_id;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        // $this->channel_id = 1;
        $query = $model
                    ->where('channel_id', '=', $this->channel_id);
        return $query;
    }
}
