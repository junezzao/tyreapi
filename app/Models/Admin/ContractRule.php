<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BaseModel;

class ContractRule extends BaseModel
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'contract_rules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['contract_id', 'type', 'type_amount', 'base', 'operand', 'min_amount', 'max_amount', 'categories', 'channels', 'products', 'fixed_charge'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $primaryKey = 'id';

    public static $rules = array(
            'contract_id'   =>'required',
            'type'          =>'sometimes',
            'type_amount'   =>'sometimes',
            'base'          =>'sometimes',
            'operand'       =>'sometimes',
            'min_amount'    =>'sometimes|integer',
            'max_amount'    =>'sometimes|integer',
            'fixed_charge'  =>'sometimes|boolean',
    );

    /**
     *
     * Relationships
     *
     */

    public function contract()
    {
        return $this->belongsTo('App\Models\Admin\Contract', 'contract_id', 'id');
    }
}
