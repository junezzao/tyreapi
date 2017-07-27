<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Contracts\CriteriaContract;
use App\Repositories\Criteria;
// use App\Repositories\Exceptions\RepositoryException;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Container\Container as App;

/**
 * Class Repository
 * @package App\Repositories\Eloquent
 */
abstract class Repository implements RepositoryContract, CriteriaContract
{

    /**
     * @var App
     */
    private $app;
    private $with;
    
    protected $result = null;
    /**
     * @var
     */
    protected $model;

    /**
     * @var Collection
     */
    protected $criteria;
    
    /**
     * @var bool
     */
    protected $skipCriteria = false;

    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $eagerLoad = [];

 
    /**
     * @param App $app
     * @throws \App\Repositories\Exceptions\RepositoryException
     */
    public function __construct()
    {
        $this->app = new App;
        $this->criteria = new Collection;
        $this->resetScope();
        $this->makeModel();
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    abstract public function model();

    public function increment($field, $amount)
    {
        $this->model->increment($field, $amount);
        $this->model->touch();
        return $this;
    }

    public function decrement($field, $amount)
    {
        $this->model->decrement($field, $amount);
        $this->model->touch();
        return $this;
    }

    

    protected function parseRelations(array $relations)
    {
        $results = [];

        foreach ($relations as $name => $constraints) {
            // If the "relation" value is actually a numeric key, we can assume that no
            // constraints have been specified for the eager load and we'll just put
            // an empty Closure with the loader so that we can treat all the same.
            if (is_numeric($name)) {
                $f = function () {
                    //
                };

                list($name, $constraints) = [$constraints, $f];
            }

            // We need to separate out any nested includes. Which allows the developers
            // to load deep relationships using "dots" without stating each level of
            // the relationship with its own key in the array of eager load names.
            $results = $this->parseNested($name, $results);

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * Parse the nested relationships in a relation.
     *
     * @param  string  $name
     * @param  array   $results
     * @return array
     */
    protected function parseNested($name, $results)
    {
        $progress = [];

        // If the relation has already been set on the result array, we will not set it
        // again, since that would override any constraints that were already placed
        // on the relationships. We will only set the ones that are not specified.
        foreach (explode('.', $name) as $segment) {
            $progress[] = $segment;

            if (! isset($results[$last = implode('.', $progress)])) {
                $results[$last] = function () {
                    //
                };
            }
        }

        return $results;
    }

    public function with($relations)
    {

        // if (is_string($relations)) $relations = func_get_args();
     
        // $this->with = $relations;
        // $this->model = $this->model->with($relations);
     
        // return $this;
        
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $this->model->with = $this->with = $relations;
        $this->model = $this->model->newQuery()->with($relations);

        return $this;

    }

    protected function eagerLoadRelations()
    {
        if(isset($this->with)) {
            foreach ($this->with as $relation) {
                $this->model->newQuery()->with($relation);
            }
        }
     
        return $this;
    }

    public function skip($start)
    {
        if (intval($start)>0) {
            $this->model = $this->model->newQuery()->skip($start);
        }
        return $this;
    }

    public function take($limit)
    {
        if (intval($limit)>0) {
            $this->model = $this->model->newQuery()->take($limit);
        }
        return $this;
    }

    public function count()
    {
        $this->applyCriteria();
        return $this->model->count();
    }

    public function newQuery()
    {
        $this->model = $this->model->newQuery();
        return $this;
    }

    
    /**
     * @param array $columns
     * @return mixed
     */
    public function all($columns = array('*'))
    {
        $this->applyCriteria();
        $this->newQuery()->eagerLoadRelations();
        return $this->model->get($columns);
    }

        /**
     * @param int $perPage
     * @param array $columns
     * @return mixed
     */
    public function paginate($perPage = 15, $columns = array('*'))
    {
        $this->applyCriteria();
        $this->newQuery()->eagerLoadRelations();
        return $this->model->paginate($perPage, $columns);
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function create(array $data)
    {
        $this->makeModel();
        return $this->model->create($data);
    }

    /**
     * @param array $data
     * @param $id
     * @param string $attribute
     * @return mixed
     */
    public function update(array $data, $id, $attribute="id")
    {
        $this->makeModel();
        return $this->model->where($attribute, '=', $id)->first()->update($data);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    /**
     * @param $id
     * @param array $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        $this->applyCriteria();
        $this->eagerLoadRelations();
        return $this->result = $this->model->find($id, $columns);
    }

    public function findAllBy($field, $value, $columns = array('*'))
    {
        $this->applyCriteria();
        $this->eagerLoadRelations();
        return $this->result = $this->model->where($field, '=', $value)->get($columns);
    }

    /**
     * @param $id
     * @param array $columns
     * @return mixed
     */
    public function findOrFail($id, $columns = array('*'))
    {
        $this->applyCriteria();
        $this->eagerLoadRelations();
        return $this->result = $this->model->findOrFail($id, $columns);
    }

    /**
     * @param $attribute
     * @param $value
     * @param array $columns
     * @return mixed
     */
    public function findBy($attribute, $value, $columns = array('*'))
    {
        $this->applyCriteria();
        $this->eagerLoadRelations();
        return $this->result = $this->model->where($attribute, '=', $value)->first($columns);
    }

    public function first($columns = array('*'))
    {
        $this->applyCriteria();
        $this->eagerLoadRelations();
        return $this->result = $this->model->first($columns);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws RepositoryException
     */
    public function makeModel()
    {
        if(empty($this->app))
        {
            $this->app = new App;
        }
        if(empty($this->criteria)){
            $this->criteria = new Collection;
        }
        $model = $this->app->make($this->model());

        if (!$model instanceof Model) {
            throw new RepositoryException("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $this->model = $model;
    }

     /**
     * @return $this
     */
    public function resetScope()
    {
        $this->skipCriteria(false);
        return $this;
    }
 
    /**
     * @param bool $status
     * @return $this
     */
    public function skipCriteria($status = true)
    {
        $this->skipCriteria = $status;
        return $this;
    }
 
    /**
     * @return mixed
     */
    public function getCriteria()
    {
        return $this->criteria;
    }
 
    /**
     * @param Criteria $criteria
     * @return $this
     */
    public function getByCriteria(Criteria $criteria)
    {
        $this->model = $criteria->apply($this->model, $this);
        return $this;
    }
 
    /**
     * @param Criteria $criteria
     * @return $this
     */
    public function pushCriteria(Criteria $criteria)
    {
        $this->criteria->push($criteria);
        return $this;
    }
 
    /**
     * @return $this
     */
    public function applyCriteria()
    {
        if ($this->skipCriteria === true) {
            return $this;
        }
        if(!empty($this->getCriteria())){
            foreach ($this->getCriteria() as $criteria) {
                if ($criteria instanceof Criteria) {
                    $this->model = $criteria->apply($this->model, $this);
                }
            }
        }
 
        return $this;
    }

    public function  where( $column,  $operator = null,  $value = null,  $boolean = 'and'){
        $this->applyCriteria();
        $this->eagerLoadRelations();
        $this->model = $this->model->where($column,  $operator,  $value ,  $boolean);
        return $this;
    }

    public function  whereIn( $column, $value = array()){
        $this->applyCriteria();
        $this->eagerLoadRelations();
        return $this->result  = $this->model->whereIn($column,  $value)->get();
    }

    public function  whereNotIn( $column, $value = array()){
        $this->applyCriteria();
        $this->eagerLoadRelations();
        return $this->result  = $this->model->whereNotIn($column,  $value)->get();
    }

    public function  whereRaw( $query = ''){
        $this->applyCriteria();
        $this->eagerLoadRelations();
        return $this->result  = $this->model->whereRaw($query)->get();
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function withTrashAll($columns = array('*'))
    {
        $this->applyCriteria();
        $this->newQuery()->eagerLoadRelations();
        return $this->model->withTrashed()->get($columns);
    }

    /**
     * @param $id
     * @param array $columns
     * @return mixed
     */
    public function withTrashFind($id, $columns = array('*'))
    {
        $this->applyCriteria();
        $this->eagerLoadRelations();
        return $this->result = $this->model->withTrashed()->find($id, $columns);
    }

    public function apiResponse()
    {
        return $this->result;
    }
}
