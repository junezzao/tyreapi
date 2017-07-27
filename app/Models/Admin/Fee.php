<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BaseModel;

class Fee extends BaseModel
{
    // use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'fees';
    protected $fillable = [
        'transaction', 
        'transaction_fee', 
        'inbound', 
        'inbound_fee', 
        'outbound', 
        'outbound_fee', 
        'storage', 
        'storage_fee',
        'shipped',
        'packaging',
        'packaging_fee',
        'contract_id',
        'start_date',
        'end_date'
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $primaryKey = 'id';
}