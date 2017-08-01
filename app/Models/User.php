<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
//use Bican\Roles\Traits\HasRoleAndPermission;
use Bican\Roles\Contracts\HasRoleAndPermission as HasRoleAndPermissionContract;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Traits\HasRoleAndPermission;

class User extends BaseModel implements AuthenticatableContract, CanResetPasswordContract, HasRoleAndPermissionContract
{

    use Authenticatable, CanResetPassword, HasRoleAndPermission, SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['first_name', 'last_name', 'email', 'password', 'contact_no', 'status', 'operation_type', 'company_name', 'address_line_1', 'address_line_2', 'address_city', 'address_postcode', 'address_state', 'address_country', 'category'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     *
     * Relationships
     *
     */
    public function scopeIsActive($query)
    {
        return $query->where('status','Active');
    }

    public function deactivate()
    {
        $this->status = strcasecmp($this->status,'active')==0?'Inactive':$this->status;
        return $this->save();
    }

    public function activate()
    {
        $this->status = 'Active';
        return $this->save();
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
            $builder->orderBy('status', 'asc')->orderBy('first_name', 'asc');
        });
    }
}
