<?php namespace App\Repositories\Criteria\Products;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class WithChanges extends Criteria
{
    
    private $changed;
    
    public function __construct($changed)
    {
        $this->changed = $changed;
    }
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $op = $this->changed==0?'=':'<>';
        $query = $model->whereRaw("products.created_at $op products.updated_at");
        return $query;
    }
}
