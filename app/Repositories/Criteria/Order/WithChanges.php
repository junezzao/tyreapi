<?php namespace App\Repositories\Criteria\Order;

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
        $query = $model->whereRaw("orders.created_at $op orders.updated_at");
        return $query;
    }
}
