<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BaseModel;

class ChannelContract extends BaseModel
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'channel_contracts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'channel_id', 'merchant_id', 'brand_id', 'start_date', 'end_date', 'guarantee', 'min_guarantee', 'start_charge_mg'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $primaryKey = 'id';


    public static $rules = array(
                'name'=>'required|max:50',
                'channel_id'=>'required',
                'merchant_id'=>'required',
                'brand_id'=>'required',
                'start_date'=>'required',
                'end_date'=>'sometimes',
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

    public function channel()
    {
        return $this->belongsTo('App\Models\Admin\Channel', 'channel_id', 'id')->withTrashed();
    }

    public function channel_contract_rules()
    {
        return $this->hasMany('App\Models\Admin\ChannelContractRule', 'contract_id', 'id');
    }


}
