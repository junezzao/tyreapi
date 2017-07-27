<?php namespace App\Repositories\Criteria\SKU;

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
        $query = $model
                    ->join('channel_sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                    ->where('channel_sku.channel_id', '=', $this->channel_id);
        return $query;
    }
}
