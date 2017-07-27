<?php namespace App\Repositories\Criteria\SKU;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByHubwireSKU extends Criteria
{
    
    private $hwsku;
    
    public function __construct($hwsku)
    {
        $this->hwsku = $hwsku;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $query = $model
                    ->where('hubwire_sku', '=', $this->hwsku);
        return $query;
    }
}
