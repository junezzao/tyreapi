<?php namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;
use App\Models\Channels;


class IssuingCompany extends BaseModel
{
	protected $table = 'issuing_companies';

	protected $primaryKey = 'id';

	protected $guarded = ['id'];

	protected $appends = ['channel_count'];

	//use SoftDeletes;

	public function getChannelCountAttribute()
	{
		return Channel::where('issuing_company', '=', $this->id)->count();
	}

	public function channels() {
		return $this->hasMany('App\Models\Admin\Channel', 'issuing_company');
	}
}