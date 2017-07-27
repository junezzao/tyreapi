<?php namespace App\Repositories\Criteria\Sales;

// use App\Repositories\Contracts\CriteriaContract;
// use App\Repositories\Contracts\RepositoryContract as Repository;
use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class SinceId extends Criteria
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
        // $this->channel_id = 1;
        $query = $model->where('sale_id', '>=', $this->sale_id);
        return $query;
    }
}
