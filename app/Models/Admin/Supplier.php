<?php namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BaseModel;


class Supplier extends BaseModel
{
	protected $table = 'suppliers';

	protected $primaryKey = 'id';

	protected $guarded = ['id'];

	protected $casts = ['active'=>'boolean'];

	use SoftDeletes;

	public function batches()
	{
		return $this->hasMany('App\Model\Admin\Purchase', 'batch_id');
	}

	public function merchant()
	{
		return $this->belongsTo('App\Models\Admin\Merchant','merchant_id')->withTrashed();
	}

	public function scopeIsActive($query)
	{
		return $query->where('active',1);
	}

	public function deactivate()
    {
        $this->active = 0;
        return $this->save();
    }

    public function activate()
    {
        $this->active = 1;
        return $this->save();
    }


	/*
	public function getCreatedAtAttribute($value){
    	return BaseController::utcToClientTz($value, $this->client_id);
    }

    public function getUpdatedAtAttribute($value){
    	return BaseController::utcToClientTz($value, $this->client_id);
    }
    */
   /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('active', 'desc')->orderBy('name', 'asc');
        });
    }
}