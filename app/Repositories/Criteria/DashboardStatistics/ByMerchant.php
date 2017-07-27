<?php namespace App\Repositories\Criteria\DashboardStatistics;

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
        $query  =   $model
                    ->with(['items'=> function ($q) {
                            $q->where('order_items.merchant_id', '=', $this->merchant_id);
                        }])
                    ->whereHas('items', function ($query) {
                        $query->where('order_items.merchant_id', '=', $this->merchant_id);
                    });
        return $query;
    }
}
