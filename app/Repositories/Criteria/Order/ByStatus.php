    <?php namespace App\Repositories\Criteria\Order;

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
        $query = $model->where('status', '=', $this->status);
        return $query;
    }
}