<?php namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use App\Repositories\Eloquent\SyncRepository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use Activity;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;



class ProductTagRepository extends Repository
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
        return 'App\Models\Admin\ProductTag';
    }

    public function create(array $data)
    {
        $rules = [
           'value' => 'required|unique:product_tags,value,NULL,id,product_id,'.(!empty($data['product_id'])?$data['product_id']:'NULL').',deleted_at,NULL',
           'product_id' => 'required|exists:products,id'
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
        Activity::log('ProductTag '.$model->value.' was created', $this->user_id);
        
        $syncRepo = new SyncRepository;
        $input['product_id'] = $model->product_id;
        $sync = $syncRepo->updateProduct($input);

        return $model;
    }

    public function update(array $data, $id, $attribute='id')
    {
        $tag = $this->find($id);
        // Inputs validations
        $rules = [
           'value' => 'required|unique:product_tags,value,'.$id.',id,product_id,'.$tag->product_id.',deleted_at,NULL',
           'product_id' => 'sometimes|required|exists:products,id'
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
        $model = $this->find($id);
        
        $syncRepo = new SyncRepository;
        $input['product_id'] = $model->product_id;
        $sync = $syncRepo->updateProduct($input);
        
        Activity::log('ProductTag '.$model->id.' was updated', $this->user_id);
        return $model;
    }

    public function delete($id)
    {
        $model = $this->findOrFail($id);
        $ack  = parent::delete($id);
        
        $syncRepo = new SyncRepository;
        $input['product_id'] = $model->product_id;
        $sync = $syncRepo->updateProduct($input);
        
        Activity::log('ProductTag ('.$id.')  was deleted', $this->user_id);
        return $ack;
        
    }

    public function updateTagsByProduct($product_id, $tags_input){
        $tags = $this->where('product_id','=',$product_id)->all();
        foreach($tags as $tag)
        {
            $tag->delete();
        }
        $newTags = array();
        foreach($tags_input as $t)
        {
            $t = trim($t);
            if($t != ''){
                $tag_input = ['product_id'=>$product_id,'value'=>$t];
                $newTags[] = $this->create($tag_input);
            }
        }

        return $newTags;
    }
}
