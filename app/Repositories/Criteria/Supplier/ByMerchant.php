<?php 
namespace App\Repositories\Criteria\Supplier;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByMerchant extends Criteria
{
    
    private $merchant_id;
    
    public function __construct($merchant_id)
    {
        $this->merchant_id = $merchant_id;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $query = $model
                    ->where('merchant_id', '=', $this->merchant_id);
        return $query;
    }
}
