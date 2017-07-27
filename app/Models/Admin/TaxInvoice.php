<?php
namespace App\Models\Admin;

use DB;
use App\Models\BaseModel;
class TaxInvoice extends BaseModel {
	protected $table = "order_invoice";

    protected $guarded = ['id'];

    protected $fillable = [];

}