<?php namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\Category;
use Activity;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository extends Repository
{
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
        return 'App\Models\Admin\Category';
    }

    public function create(array $data)
    {
    	// Inputs validations

        $rules = [
           'name' => 'required|unique:categories,name,NULL,id,parent_id,'.$data['parent_id'],
           'parent_id' => 'sometimes|integer'
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
        Activity::log('Category '.$model->id.' was created', $this->user_id);
        
        return $this->find($model->id);
    }

    public function update(array $data, $id, $attribute='id')
    {
        // Inputs validations
        $model = $this->find($id);

        $parent_id = !empty($data['parent_id'])?$data['parent_id']:$model->parent_id;
            
        $rules = [
            'name' => 'sometimes|required|unique:categories,name,'.$id.',id,parent_id,'.$parent_id,
            'parent_id' => 'sometimes|integer'
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
        $updated = parent::update($data, $id, $attribute);
        $model = $this->find($id);
        
        Activity::log('Category ('.$id.' - '. $model->name.') was updated', $this->user_id);
        return $model;
    }

    public function delete($id)
    {
        $model  = $this->findOrFail($id);
        $rules = ['product' => 'integer|max:0'];
        $messages = ['product.max'=>'Cannot delete category while contains products.'];
        
        $num = $model->products()->count();
        $v = \Validator::make(['product'=>(int)$num], $rules, $messages);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $ack  = parent::delete($id);
        Activity::log('Category ('.$id.')  was deleted', $this->user_id);
        return $ack;
    }
    
}