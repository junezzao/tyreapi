<?php namespace App\Repositories\Criteria\Products;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByBrand extends Criteria
{
    
    private $brand_id;
    
    public function __construct($brand_id)
    {
        $this->brand_id = $brand_id;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $query = $model->where('brand_id', '=', $this->brand_id)->orderBy('name', 'asc');
        return $query;
    }
}
