<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class StockCorrection extends BaseModel
{
	protected $table = 'stock_correction';

	protected $guarded = ['id'];

    protected $fillable = [];

    public function quantityLogApp ()
    {
        return $this->morphMany('App\Models\Admin\QuantityLogApp', 'ref', 'ref_table', 'ref_table_id');
    }
}
