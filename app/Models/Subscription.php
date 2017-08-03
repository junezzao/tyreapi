<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Admin\Product;
use App\Models\Admin\Webhook;

use App\Models\BaseModel;

class Subscription extends BaseModel
{
    use SoftDeletes;
    protected $table = "user_subscription";
    protected $primaryKey = "id";
    protected $guarded = array('id');
    protected $fillable = ['user_id','role_id','start_date','end_date','payment_status', 'status','created_at','updated_at','deleted_at'];
    
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function role()
    {
        return $this->belongsTo('Bican\Roles\Models\Role');
    }
}
