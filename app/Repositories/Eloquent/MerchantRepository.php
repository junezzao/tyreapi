<?php
namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\MerchantRepository as MerchantRepositoryInterface;
use App\Repositories\Repository as Repository;
use App\Models\Admin\Merchant;
use App\Models\Admin\Channel;
use App\Models\Admin\Supplier;
use App\Models\Admin\Brand;
use Illuminate\Http\Exception\HttpResponseException as HttpResponseException;
use App\Exceptions\ValidationException as ValidationException;
use Activity;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Log;
use DB;
use Carbon\Carbon;

class MerchantRepository extends Repository implements MerchantRepositoryInterface
{
    protected $model;

    protected $role;

    protected $skipCriteria = true;

    protected $user_id;

    public function __construct(Merchant $model)
    {
        $this->model = $model;
        parent::__construct();
        $this->user_id = Authorizer::getResourceOwnerId();
    }
  /**
   * Specify Model class name
   *
   * @return mixed
   */
    public function model()
    {
        return 'App\Models\Admin\Merchant';
    }

    public function findOrFail($id, $columns = array('*'))
    {
        $this->applyCriteria();
        $this->eagerLoadRelations();
        return $this->result = $this->model->withTrashed()->findOrFail($id, $columns);
    }

    public function create(array $data)
    {
        // Inputs validations
        $rules = array(
            'name' => 'required|string',
            'slug' => 'required|alpha_num|unique:merchants,slug|max:20',
            'address' => 'sometimes|required_if:self_invoicing,1',
            'contact' => 'required',
            'email' => 'required|email',
            'gst_reg_no' => 'sometimes|required_if:self_invoicing,1',
            'self_invoicing' => 'sometimes|required|boolean',
            'timezone' => 'required',
            'currency' => 'required',
            'forex_rate' => 'sometimes|required',
            'ae' => 'required|integer',
            'status' => 'required|string',
            'currencies' => 'sometimes|required|array',
            'rate' => 'sometimes|required|array',
            'logo_url' => 'sometimes|required_if:self_invoicing,1|url',


        );
        $messages = array(
            'currencies.required'=>'Supported currency is required.',
            'rate.required' => 'Supported currency rate is required.'
        );
        if(!empty($data['currencies'])){
            $tmp = array();
            foreach($data['currencies'] as $key => $val)
            {
                $rules['currencies.'.$key] = 'required|string|not_in:'.$data['currency'];
                $rules['rate.'.$key] = 'required|numeric|min:0.0001';
                $tmp[] = ['currency'=>$val,'rate'=> floatval($data['rate'][$key]) ];
                $messages['currencies.'.$key.'.required'] = 'Currency is required.';
                $messages['currencies.'.$key.'.not_in'] = 'Currency cannot be the same with default.';
                $messages['rate.'.$key.'.required'] = 'Currency rate is required.';
                $messages['rate.'.$key.'.numeric'] = 'Currency rate must be in numeric.';
                $messages['rate.'.$key.'.min'] = 'Currency rate minimum is :min.';
            }
            $data['forex_rate'] = json_encode($tmp);
        }

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
        unset($data['access_token']);
        unset($data['currencies']);unset($data['rate']);
        // \Log::info(print_r($inputs, true));
        $model = parent::create($data);
        return $this->find($model->id);
    }

    public function update(array $data, $id, $attribute='id')
    {
        // Inputs validations
        $merchant = $this->find($id);

        $rules = [
            'name' => 'sometimes|required|string',
            'slug' => 'sometimes|required|string|unique:merchants,slug,'.$id,
            'address' => 'sometimes|required_if:self_invoicing,1',
            'contact' => 'sometimes|required',
            'email' => 'sometimes|required|email',
            'logo_url' => 'sometimes|required_if:self_invoicing,1|url',
            'gst_reg_no' => 'sometimes|required_if:self_invoicing,1',
            'self_invoicing' => 'sometimes|required|boolean',
            'timezone' => 'sometimes|required',
            'currency' => 'sometimes|required',
            'forex_rate' => 'sometimes|required_with:currency',
            'ae' => 'sometimes|required|integer',
            'status' => 'sometimes|required|string',
            'created_at' => 'sometimes|date_format:Y-m-d H:i:s',
            'updated_at' => 'sometimes|date_format:Y-m-d H:i:s',
            'currencies' => 'sometimes|required|array',
            'rate' => 'sometimes|required|array',
        ];
        $messages = array();
        if(!empty($data['currencies'])){
            $tmp = array();
            $messages['currencies.required'] ='Supported currency is required.';
            $messages['rate.required'] = 'Supported currency rate is required.';
            foreach($data['currencies'] as $key => $val)
            {
                $rules['currencies.'.$key] = 'required|string|not_in:'.$data['currency'];
                $rules['rate.'.$key] = 'required|numeric|min:0.0001';
                $tmp[] = ['currency'=>$val,'rate'=>floatval($data['rate'][$key]) ];
                $messages['currencies.'.$key.'.required'] = 'Currency is required.';
                $messages['currencies.'.$key.'.not_in'] = 'Currency cannot be the same with default.';
                $messages['rate.'.$key.'.required'] = 'Currency rate is required.';
                $messages['rate.'.$key.'.numeric'] = 'Currency rate must be in numeric.';
                $messages['rate.'.$key.'.min'] = 'Currency rate minimum is :min.';
            }
            $data['forex_rate'] = json_encode($tmp);
        }

        if(isset($data['status']) && strcasecmp($data['status'],'inactive')==0)
        {
            // Check active brands
            $brands = $merchant->brands()->isActive()->get()->count();
            $rules['active_brands'] = 'integer|max:0';
            $data['active_brands'] = $brands;
            $messages['active_brands.max'] = 'Cannot deactivate merchant while having active brand(s).';
        }



        $v = \Validator::make($data, $rules, $messages);

        // if(isset($data['active_users'])) unset($data['active_users']);
        if(isset($data['active_brands'])) unset($data['active_brands']);
        // if(isset($data['active_suppliers'])) unset($data['active_suppliers']);


        if ($v->fails()) {
            throw new ValidationException($v);
        }
        unset($data['access_token']);
        unset($data['currencies']);unset($data['rate']);
        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $data = $newinputs;
        if(!empty($data['name']))
        {
            $data['name'] = htmlentities(($data['name']));
        }
        if(isset($data['status']))
        {
            if (strcasecmp($data['status'],'inactive')==0) {
                $data['deactivated_date'] = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now()->toDateTimeString())->setTimezone('UTC'); 

                // Deactivate users
                $users = $merchant->users()->isActive()->get();
                $users->deactivate();
                Activity::log('Users under merchant ('.$id.')  was deactivated', $this->user_id);

                // Deactivate suppliers
                $suppliers = $merchant->suppliers()->isActive()->get();
                $suppliers->deactivate();
                Activity::log('Suppliers under merchant ('.$id.')  was deactivated', $this->user_id);
            }
            else {
                $data['deactivated_date'] = null; 
            }
        }
        $model = parent::update($data, $id, $attribute);
        return $this->find($id);
    }

    public function all($filters=array())
    {
        $channel_id = (isset($filters['channel_id']) && !empty($filters['channel_id'])) ? $filters['channel_id'] : '';

        if(!empty($channel_id))
        {
            $m_arr = Channel::find($channel_id)->merchants;
            $merchants = array();
            foreach($m_arr as $m)
            {
                $merchants[] = Merchant::with('ae')->find($m->id);
            }
        }
        else
        {
            $merchants = Merchant::with('ae')->get();
        }

        return $merchants;


        $this->applyCriteria();
        $this->newQuery()->eagerLoadRelations();
        return $this->model->withTrashed()->get($columns);
    }

    public function delete($id)
    {
        $merchant  = $this->findOrFail($id);
        $supplierCount = Supplier::where('merchant_id', '=', $id)->count();
        $brandCount = Brand::where('merchant_id', '=', $id)->count();

        $rules = [
            'status'    => 'in:Inactive',
            'suppliers' => 'in:0',
            'brands'    => 'in:0'
        ];
        $messages = [
            'status.in'     => 'Cannot delete active merchant.',
            'suppliers.in' => 'There are still ' . $supplierCount . ' supplier(s) tied to the merchant.',
            'brands.in'    => 'There are still ' . $brandCount . ' brand(s) tied to the merchant.'
        ];

        $v = \Validator::make(['status' => $merchant->status, 'suppliers' => $supplierCount, 'brands' => $brandCount], $rules, $messages);
        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $ack  = parent::delete($id);
        Activity::log('Merchant ('.$id.')  was deleted', $this->user_id);
        return $ack;
    }

    public function getNewMerchantsByMonth($month = null)
    {
        if(is_null($month)){
            // use current month
            $month = Carbon::now()->month;
            $merchants = Merchant::whereBetween('created_at', [date("Y-".$month."-01"), date("Y-".$month."-t")])->get();
        }else{
            $monthDate = Carbon::createFromDate(null, $month, null, null);
            $lastDayInMonth = $monthDate->daysInMonth;
            $merchants = Merchant::whereBetween('created_at', [date("Y-".$month."-01"), date("Y-".$month."-".$lastDayInMonth)])->get();
        }
        return $merchants;
    }

    public function getActiveMerchants($byDate)
    {
        if($byDate == 'week'){
            $date = Carbon::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'))->subDays(6);
        }elseif($byDate == 'month'){
            $date = Carbon::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'))->subDays(30);
        }
        $merchants = Merchant::select(DB::raw('merchants.*'))
                                ->leftJoin('order_items', 'merchants.id', '=', 'order_items.merchant_id')
                                ->leftJoin('orders', 'order_items.order_id', '=', 'orders.id')
                                ->whereBetween('orders.updated_at', [$date, date("Y-m-d")])
                                ->groupBy('merchants.id')
                                ->get();

        return $merchants;
    }

    public function getMerchantsByChannel($channelId)
    {
        $merchants = Merchant::whereHas('channels', function($q) use ($channelId){
            $q->where('channel_id', '=', $channelId);
        })->get();

        return $merchants;
    }
}
