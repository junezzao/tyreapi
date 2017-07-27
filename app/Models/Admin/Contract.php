<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BaseModel;

class Contract extends BaseModel
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'contracts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'brand_id', 
        'merchant_id', 
        'start_date', 
        'end_date', 
        'name', 
        'guarantee', 
        'min_guarantee', 
        'start_charge_mg',
        'storage_fee',
        'inbound_fee',
        'outbound_fee',
        'shipped_fee',
        'return_fee',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $primaryKey = 'id';

    public static $rules = array(
                'name'=>'required|max:50',
                'brand_id'=>'required',
                'merchant_id'=>'required',
                'start_date'=>'required',
                'end_date'=>'sometimes',
                'rule'=>'required',
                'guarantee'=>'required',
                'min_guarantee' =>'required|boolean',
                'start_charge_mg'=>'sometimes|boolean'
    );

    /**
     *
     * Relationships
     *
     */

    public function brand()
    {
        return $this->belongsTo('App\Models\Admin\Brand', 'brand_id', 'id')->withTrashed();
    }

    public function merchant()
    {
        return $this->belongsTo('App\Models\Admin\Merchant', 'merchant_id', 'id')->withTrashed();
    }

    public function contract_rules()
    {
        return $this->hasMany('App\Models\Admin\ContractRule', 'contract_id', 'id');
    }
}
