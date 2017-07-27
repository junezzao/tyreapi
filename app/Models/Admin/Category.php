<?php namespace App\Models\Admin;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BaseModel;
use DB;

class Category extends BaseModel {
	
    use SoftDeletes;
    
    protected $fillable = ['name','full_name','parent_id','created_at','updated_at','deleted_at'];
    protected $table = 'categories';
    protected $primaryKey = 'id';
    protected $guarded = array('id');
    protected $appends = ['level','total_product','has_child'];
    // protected $with = ['parent'];

    private $max_level = 3;
    
    public function products()
    {
    	return $this->hasMany('App\Models\Admin\Product', 'category_id', 'id');
    }

    public function getTotalProductAttribute()
    {
        return $this->products()->count();
    }

    public function child()
    {
        return $this->hasMany('App\Models\Admin\Category','parent_id','id');
    }

    public function parent()
    {
        return $this->hasOne('App\Models\Admin\Category','id','parent_id');
    }
    public function getHasChildAttribute()
    {
        $child = $this->child()->first();
        return !empty($child)?true:false;
    }

    public function getLevelAttribute()
    {
    	$path = $this->getPath();
        return count($path);
    }

    public function getRoot()
    {
    	$path = $this->getPath();
        return $path[0];
    }

    public function getPath()
    {
        $id = $this->id;
        $fields = array();
        $joins = array();
        for($i=1;$i<=$this->max_level;$i++)
        {
            $j=$i-1;
            if($i==1)
            $fields[] = "t$i.name as node";
            else
            $fields[] = "t$i.name as parent$i";
            if($i>=2)
            $joins[] = "LEFT JOIN categories AS t$i ON t$j.parent_id = t$i.id";
        }

        $sql = "SELECT ".implode(',',$fields)." FROM categories AS t1";
        foreach($joins as $join)
        {
            $sql.=" ".$join." ";
        }
        $sql.=' WHERE t1.id = ?';
        $result = DB::select($sql, [$id]);
        $path = json_decode(json_encode($result[0]),1);
        return array_reverse(array_filter(array_values($path)));
    }

    public static function boot()
    {
        parent::boot();
        
        Category::deleting(function($model){
            $children = $model->child()->get();
            if(!empty($children))
            {
                foreach($children as $child)
                {
                    $child->parent_id = $model->parent_id;
                    $child->save();
                }
            }
        });

        Category::saving(function($model){
            $parent = !empty($model->parent_id)?Category::find($model->parent_id):null;
            $model->full_name = !empty($parent)?$parent->full_name.'/'.$model->name:$model->name;
        });

        Category::saved(function($model){
            $children = $model->child()->get();
            if(!empty($children))
            {
                foreach($children as $child)
                {
                    $child->touch();
                }
            }
        });
    }
    

}