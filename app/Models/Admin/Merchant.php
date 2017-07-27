<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BaseModel;


class Merchant extends BaseModel
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'merchants';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['code','currency','forex_rate','name','slug','address','contact','email','logo_url','gst_reg_no','self_invoicing','timezone','ae', 'status', 'deactivated_date'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];


    protected $primaryKey = 'id';

	public function ae()
	{
		return $this->belongsTo('App\Models\User', 'ae')->withTrashed();
	}

	/**
     *
     * Relationships
     *
     */
    public function users()
    {
        return $this->hasMany('App\Models\User');
    }


    public function brands()
    {
        return $this->hasMany('App\Models\Admin\Brand');
    }

    public function suppliers()
    {
        return $this->hasMany('App\Models\Admin\Supplier');
    }

    public function orders ()
    {
        return $this->hasMany('App\Models\Admin\OrderItem')->with('order');
    }

    public function scopeIsActive ($query)
    {
        return $query->where('status', 'Active');
    }

    public function channels()
    {
        return $this->belongsToMany('App\Models\Admin\Channel')->with('channel_detail')->withTrashed();
    }
    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('status', 'asc')->orderBy('name', 'asc');
        });
    }
}
