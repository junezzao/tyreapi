<?php
namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\SupplierRepository as SupplierRepositoryInterface;
use App\Repositories\Repository as Repository;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\Supplier;
use App\Models\Admin\Channel;
use Illuminate\Database\Eloquent\Collection;
use Log;
class SupplierRepository extends Repository implements SupplierRepositoryInterface
{
    protected $model;

    protected $role;

    protected $skipCriteria = false;

    public function __construct()
    {
        parent::__construct();
    }
  /**
   * Specify Model class name
   *
   * @return mixed
   */
    public function model()
    {
        return 'App\Models\Admin\Supplier';
    }

    public function create(array $data)
    {
        // Inputs validations

        $rules = array(
            'name' => 'required|string',
            'address' => 'required',
            'phone' => 'required',
            'email' => 'required|email',
            'mobile' => 'sometimes',
            'contact_person' => 'required', 
            'merchant_id' => 'sometimes|required|exists:merchants,id',
            'registration_no' => 'required|string'
        );
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
        $data['name'] = htmlspecialchars($data['name']);
        $data['name'] = htmlentities(($data['name']));
        $data['phone'] = str_replace(['-', ' '],[''], $data['phone']);
        $data['phone'] = preg_replace('/[^A-Za-z0-9\-]/', '', $data['phone']); 
        if(isset($data['mobile']))
        {
            $data['mobile'] = str_replace(['-', ' '],[''], $data['mobile']);
            $data['mobile'] = preg_replace('/[^A-Za-z0-9\-]/', '', $data['mobile']); 
        }
        $model = parent::create($data);
        return $this->find($model->id);
    }

    public function update(array $data, $id, $attribute='id')
    {
        // Inputs validations
        $supplier = $this->find($id);
        $rules = [
            'name' => 'sometimes|required|string',
            'address' => 'sometimes|required',
            'phone' => 'sometimes|required',
            'email' => 'sometimes|required|email',
            'mobile' => 'sometimes',
            'contact_person' => 'sometimes|required',
            'active' => 'sometimes|boolean',
            'merchant_id' => 'sometimes|required|exists:merchants,id',
            'registration_no' => 'sometimes|required|string'
        ];
        $messages = array();
        if(isset($data['active']))
        {
            if(intval($data['active'])===1)
            {
                // Check active merchant
                $merchant = $supplier->merchant;
                $rules['active_merchant'] = 'integer|min:1';
                $data['active_merchant'] = strcasecmp($merchant->status,'active')==0?1:0;
                $messages['active_merchant.min'] = 'Cannot activate supplier when merchant inactive.';
            }
        }
        

        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        if(!empty($data['active_merchant'])) unset($data['active_merchant']);


        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $data = $newinputs;
        if(isset($data['name']))
        {
            $data['name'] = htmlspecialchars($data['name']);
            $data['name'] = htmlentities(($data['name']));
        }
        if(isset($data['phone']))
        {
            $data['phone'] = str_replace(['-', ' '],[''], $data['phone']);
            $data['phone'] = preg_replace('/[^A-Za-z0-9\-]/', '', $data['phone']); 
        }
        if(isset($data['mobile']))
        {
            $data['mobile'] = str_replace(['-', ' '],[''], $data['mobile']);
            $data['mobile'] = preg_replace('/[^A-Za-z0-9\-]/', '', $data['mobile']); 
        }
        $model = parent::update($data, $id, $attribute);
        return $this->find($id);
    }

    public function all($filters=array())
    {
        $channel_id = (isset($filters['channel_id']) && !empty($filters['channel_id'])) ? $filters['channel_id'] : '';
        $merchant_id = (isset($filters['merchant_id']) && !empty($filters['merchant_id'])) ? $filters['merchant_id'] : '';

        if(!empty($channel_id)) 
        {
            $s_arr = Channel::find($channel_id)->merchants->lists('suppliers');
            $s_arr = (new Collection($s_arr))->collapse()->unique();
            $suppliers = array();
            foreach($s_arr as $s)
            {
                $suppliers[] = Supplier::with('merchant')->find($s->id);
            }
        } 
        else 
        {
            $suppliers = Supplier::with('merchant');
            if(!empty($merchant_id)) $suppliers = $suppliers->where('merchant_id', $merchant_id);
            $suppliers = $suppliers->get();
        }  
        return $suppliers;
    }
}
