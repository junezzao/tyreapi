<?php namespace App\Repositories\Criteria\OrderItem;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByOrder extends Criteria
{
    
    private $order_id;
    
    public function __construct($order_id)
    {
        $this->order_id = $order_id;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $query = $model
                ->where('order_items.order_id', '=', $this->order_id)
                ;
        return $query;
    }
}
