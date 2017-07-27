<?php namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\SKUCombination;

class SKUCombinationRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    protected $model;

    protected $role;

    protected $skipCriteria = true;
    
    public function __construct()
    {
        $this->model = new SKUCombination;
        parent::__construct();
    }

    public function model()
    {
        return 'App\Models\Admin\SKUCombination';
    }

    public function create(array $data)
    {
        // Inputs validations

        $rules = [
           'sku_id' => 'required|integer|exists:sku',
           'option_id' => 'required|integer|exists:sku_options',
        ];

        $messages = [];

        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
   
        $data = $newinputs;
        $model = parent::create($data);
        return $this->find($model->combination_id);
    }
    
}
