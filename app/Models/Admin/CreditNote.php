<?php
namespace App\Models\Admin;

use DB;
use App\Models\BaseModel;
class CreditNote extends BaseModel {
	protected $table = "order_credit_note";

    protected $guarded = ['id'];

    protected $fillable = [];

}