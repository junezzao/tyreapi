<?php namespace App\Repositories\Criteria\Order;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class SinceId extends Criteria
{
    
    private $id;
    
    public function __construct($id)
    {
        $this->id = $id;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $query = $model->where('id', '>=', $this->id);
        return $query;
    }
}
