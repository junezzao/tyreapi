<?php namespace App\Repositories\Criteria\Products;

// use App\Repositories\Contracts\CriteriaContract;
// use App\Repositories\Contracts\RepositoryContract as Repository;
use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class SinceId extends Criteria
{
    
    private $product_id;
    
    public function __construct($product_id)
    {
        $this->product_id = $product_id;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        // $this->channel_id = 1;
        $query = $model->where('product_id', '>=', $this->product_id);
        return $query;
    }
}
