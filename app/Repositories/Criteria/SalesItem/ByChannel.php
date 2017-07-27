<?php namespace App\Repositories\Criteria\SalesItem;

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
        $query = $model->join('sales', 'sales.sale_id', '=', 'sales_items.sale_id')
                ->where('sales.channel_id', '=', $this->channel_id)
                ;
        return $query;
    }
}
