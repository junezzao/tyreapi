<?php 
namespace App\Modules\Channels\Repositories\Criteria;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByManualOrder extends Criteria
{
    
    private $status;
    
    public function __construct($status)
    {
        $this->status = $status;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $query = $model
                    ->where('manual_order', '=', $this->status);
                    
        return $query;
    }
}
