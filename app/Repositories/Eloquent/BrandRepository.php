<?php namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\Brand;
use App\Models\Admin\Channel;
use Activity;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use App\Repositories\Criteria\Brand\ByMerchant;

class BrandRepository extends Repository
{
    protected $model;

    protected $role;

    protected $skipCriteria = false;

    protected $user_id;


    public function __construct(Brand $model)
    {
        $this->model = $model;
        parent::__construct();
        $this->user_id = Authorizer::getResourceOwnerId();
    }

    public function model()
    {
        return 'App\Models\Admin\Brand';
    }

    public function create(array $data)
    {
        // Inputs validations

        $rules = [
           'merchant_id' => 'required|integer|exists:merchants,id',
           'name' => 'required',
           'prefix' => 'required|max:4|min:2|unique:brands,prefix,NULL,id,merchant_id,'.(!empty($data['merchant_id'])?$data['merchant_id']:'NULL').',deleted_at,NULL',
           'active' => 'sometimes|required|boolean'
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
        Activity::log('Brand '.$model->id.' was created', $this->user_id);
        
        return $this->find($model->id);
    }

    public function update(array $data, $id, $attribute='id')
    {
        // Inputs validations
        $brand = $this->withTrashFind($id);
        $rules = [
            'merchant_id' => 'sometimes|required|integer|exists:merchants,id',
            'name' => 'sometimes|required',
            'prefix' => 'sometimes|required|unique:brands,prefix,'.$id.',id,merchant_id,'.(!empty($data['merchant_id'])?$data['merchant_id']:'NULL').',deleted_at,NULL',
            'active' => 'sometimes|required|boolean'
        ];
        $messages = array();
        if(isset($data['active']))
        {
            if(intval($data['active'])===0)
            {
                // Check active products
                $products = $brand->products()->isActive()->get()->count();
                $rules['active_products'] = 'integer|max:0';
                $data['active_products'] = $products;
                $messages['active_products.max'] = 'Cannot deactivate brand while having active product(s).';
            }
            else if(intval($data['active'])===1)
            {
                // Check active merchant
                $merchant = isset($brand->merchant) ? $brand->merchant : '';
                if(!empty($merchant))
                {
                    $rules['active_merchant'] = 'integer|min:1';
                    $data['active_merchant'] = strcasecmp($merchant->status,'active')==0?1:0;
                    $messages['active_merchant.min'] = 'Cannot activate brand when merchant inactive.';
                }
            }
        }
    
        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        if(isset($data['active_products'])) unset($data['active_products']);
        if(isset($data['active_merchant'])) unset($data['active_merchant']);

        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        
        $data = $newinputs;
        $model = Brand::with('products')->withTrashed()->find($id);
        
        if(isset($data['active']) && ($brand->active != $data['active']))
        {
            // if activating brand, remove deactivated_date and deleted_at
            if ($data['active']==1) {
                $model->deactivated_date = null;
                $model->restore();
            }
            // if deactivating brand, populate deactivated_date col
            else {
                $model->deactivated_date = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now()->toDateTimeString())->setTimezone('UTC');
            }
            $model->save();
            Activity::log('Brand ('.$id.' - '. $model->name.') was '.($data['active']==1?'activated':'deactivated'), $this->user_id);
        }
        $updated = $brand->update($data);
        
        Activity::log('Brand ('.$id.' - '. $model->name.') was updated', $this->user_id);
        return $model;
    }

    public function delete($id)
    {
        $brand  = $this->findOrFail($id);
        $rules = ['product' => 'integer|max:0'];
        $messages = ['product.max'=>'Cannot delete brand while contains products.'];
        
        $num = $brand->products()->count();
        $v = \Validator::make(['product'=>(int)$num], $rules, $messages);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $ack  = parent::delete($id);
        Activity::log('Brand ('.$id.')  was deleted', $this->user_id);
        return $ack;
    }

    public function all($filters=array())
    {
        $channel_id = (isset($filters['channel_id']) && !empty($filters['channel_id'])) ? $filters['channel_id'] : '';
            
        if(!empty($channel_id)) 
        {
            $b_arr = Channel::find($channel_id)->merchants->lists('brands');
            $b_arr = (new Collection($b_arr))->collapse()->unique();
            $brands = array();
            foreach($b_arr as $b)
            {
                //$brand = Brand::find('id', '=', $b->id);
                $brand = $b;
                $brand->product_count = $brand->productsTotalByChannel($b->id, $channel_id)->total;
                $brands[] = $brand;
            }
        }
        // get deactivated brands
        else if (isset($filters['active']))
        {
            $brands = Brand::onlyTrashed()->where('active', '=', 0)->get();
        }
        elseif (!empty($filters['merchantId']))
        {
            $this->pushCriteria(new ByMerchant($filters['merchantId']));
            return parent::all();
        }
        else 
        {
            $brands = Brand::with('productsTotal')->get();
        }
        return $brands;
    }
}
