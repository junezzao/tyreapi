<?php namespace App\Repositories\Criteria\Sales;

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
        $query = $model->whereRaw("sales.created_at $op sales.updated_at");
        return $query;
    }
}
