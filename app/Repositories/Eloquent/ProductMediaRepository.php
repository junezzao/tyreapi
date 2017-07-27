<?php namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use App\Repositories\Eloquent\SyncRepository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\Brand;
use App\Models\Admin\ProductTag;
use App\Models\Admin\Product;
use Activity;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;



class ProductMediaRepository extends Repository
{
	/**
     * Specify Model class name
     *
     * @return mixed
     */
    protected $model;

    protected $role;

    protected $skipCriteria = true;

    protected $user_id;

    
    public function __construct()
    {
        parent::__construct();
        $this->user_id = Authorizer::getResourceOwnerId();
    }

    public function model()
    {
        return 'App\Models\Admin\ProductMedia';
    }

    public function create(array $data)
    {
        $rules = [
           'media_id' => 'required|unique:product_media,media_id,NULL,id,product_id,'.(!empty($data['product_id'])?$data['product_id']:'NULL'),
           'product_id' => 'required|exists:products,id',
           'sort_order' => 'sometimes|integer|min:0'
        ];
        $messages = array();
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
        Activity::log('ProductMedia '.$model->id.' was created', $this->user_id);
        
        $syncRepo = new SyncRepository;
        $input['product_id'] = $model->product_id;
        $sync = $syncRepo->updateMedia($input);
        
        return $model;
    }

    public function update(array $data, $id, $attribute='id')
    {
        $model = $this->find($id);
        // Inputs validations
        $rules = [
           'media_id' => 'sometimes|required|unique:product_media,media_id,NULL,id,product_id,'.$model->product_id,
           'product_id' => 'sometimes|required|exists:products,id',
           'sort_order' => 'sometimes|integer|min:0'
        ];
        $messages = array();
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
        $ack = parent::update($data, $id, $attribute);
        $model = $this->with('media')->find($id);
        Activity::log('ProductMedia '.$model->id.' was updated', $this->user_id);
        return $model;
    }

    public function delete($id)
    {
        $model = $this->findOrFail($id);
        $ack  = parent::delete($id);
        
        $syncRepo = new SyncRepository;
        $input['product_id'] = $model->product_id;
        $sync = $syncRepo->updateMedia($input);
        
        Activity::log('ProductMedia ('.$id.')  was deleted', $this->user_id);
        return $ack;
        
    }
}
