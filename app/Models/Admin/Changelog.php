<?php namespace App\Models\Admin;

use App\Models\BaseModel;
use DateTime;

class Changelog extends BaseModel {
	protected $primaryKey = 'id';
	protected $fillable = ['title', 'type', 'content'];
	protected $table = 'changelogs';
    protected $guarded = array('id');
}