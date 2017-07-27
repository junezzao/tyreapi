<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;

use App\Models\Admin\RejectLog;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;

class RejectLogRepository extends Repository
{
    protected $model;

    protected $role;

    protected $skipCriteria = false;

    public function __construct()
    {
        $this->model = new RejectLog;
        parent::__construct();
    }
  /**
   * Specify Model class name
   *
   * @return mixed
   */
    public function model()
    {
        return 'App\Models\Admin\RejectLog';
    }

    public function create(array $data)
    {
        // Inputs validations

        $rules = [
           'sku_id' => 'required|integer|exists:sku',
           'user_id' => 'required|integer' . (($data['user_id'] > 0) ? '|exists:users,id' : ''),
           'channel_id' => 'required|integer|exists:channels,id',
           'quantity' => 'sometimes|required|integer|min:1',
           'remarks' => 'required|string',

        ];

        $messages = [];

        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
        	\Log::info(print_r($v->errors(), true));
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

        return $model;
    }

}
