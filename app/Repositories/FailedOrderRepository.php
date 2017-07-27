<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;

use App\Models\Admin\FailedOrder;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use Illuminate\Http\Exception\HttpResponseException as HttpResponseException;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;

class FailedOrderRepository extends Repository
{
    protected $model;

    protected $role;

    protected $skipCriteria = false;

    public function __construct()
    {
        $this->model = new FailedOrder;
        parent::__construct();
    }
  /**
   * Specify Model class name
   *
   * @return mixed
   */
    public function model()
    {
        return 'App\Models\Admin\FailedOrder';
    }

    public function create(array $data)
    {
        // Inputs validations

        $rules = [
           'channel_id' => 'required|integer|exists:channels,id',
           'order_id' => 'sometimes|integer|exists:orders,id',
           'tp_order_id' => 'required|string',
           'error' => 'sometimes|string',
           'status' => 'required|integer',
           'user_id' => 'sometimes|integer|exists:users,id',
           'tp_order_date' => 'required|date',  
        ];

        $messages = [];
        
        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            $errors =  response()->json(
             array(
                'code' =>  422,
                'error' => $v->errors()
            ));
            throw new HttpResponseException($errors);
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

    public function update(array $data, $id, $attribute="id")
    {
        $this->makeModel();
        if(!isset($data['user_id'])){
            // if no user_id is set, set it to the current user's ID
            $data['user_id'] = Authorizer::getResourceOwnerId();
        }
        
        return $this->model->where($attribute, '=', $id)->first()->update($data);
    }

    public function countPendingFailedOrder()
    {
        return $this->model->select('channels.name', 'failed_orders.status', \DB::Raw('COUNT(*) as total'))
                            ->leftJoin('channels', 'failed_orders.channel_id', '=', 'channels.id')
                            ->groupBy('failed_orders.status', 'channels.name')
                            ->orderBy('failed_orders.status', 'asc', 'channels.name', 'asc')
                            ->where('failed_orders.status', '!=', 3)
                            ->where('failed_orders.status', '!=', 4)
                            ->get();
    }

    public function emailData()
    {
        $datas = $this->countPendingFailedOrder();
        $output = array();

        if($datas->count() > 0){
            foreach($datas as $data){
                $output[$data->name][$data->status] = $data->total;
            }
        }

        return $output;
    }
}
