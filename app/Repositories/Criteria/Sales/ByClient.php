<?php namespace App\Repositories\Criteria\Sales;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByClient extends Criteria
{
    
    private $client_id;
    
    public function __construct($client_id)
    {
        $this->client_id = $client_id;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $query = $model->where('client_id', '=', $this->client_id);
        return $query;
    }
}
