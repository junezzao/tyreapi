<?php namespace App\Repositories\Criteria\ChannelSKU;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByHubwireSKU extends Criteria
{
    
    private $hwsku;
    
    public function __construct($hwsku)
    {
        $this->hwsku = $hwsku;
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
                    ->where('sku.hubwire_sku', '=', $this->hwsku);
        return $query;
    }
}
