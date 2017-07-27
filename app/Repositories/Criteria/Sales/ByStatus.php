    <?php namespace App\Repositories\Criteria\Sales;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByStatus extends Criteria {
    
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
        $query = $model->where('sale_status', '=', $this->status);
        return $query;
    }
}