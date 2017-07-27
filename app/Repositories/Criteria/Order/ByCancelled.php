<?php namespace App\Repositories\Criteria\Order;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByCancelled extends Criteria
{
    
    private $isCancelled;
    
    public function __construct($isCancelled)
    {
        $this->isCancelled = $isCancelled;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $query = $model
                    ->where('cancelled_status', '=', $this->isCancelled);
        return $query;
    }
}
