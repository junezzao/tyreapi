<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class RunningNumber extends BaseModel
{
    protected $table = 'running_number';

    protected $primaryKey = 'id';

	protected $guarded = ['id'];

	public $timestamps = false;
}
