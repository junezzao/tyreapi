<?php namespace App\Repositories\Criteria\SalesItem;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class BySales extends Criteria
{
    
    private $sale_id;
    
    public function __construct($sale_id)
    {
        $this->sale_id = $sale_id;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $query = $model
                ->where('sales_items.sale_id', '=', $this->sale_id)
                ;
        return $query;
    }
}
