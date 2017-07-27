<?php namespace App\Repositories\Criteria\Products;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class UpdatedAt extends Criteria
{
    
    private $date;
    
    public function __construct($date)
    {
        $this->date = date('Y-m-d H:i:s', strtotime($date));
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $query = $model->where('updated_at', '>=', $this->date);
        return $query;
    }
}
