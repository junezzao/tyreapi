<?php
class APILog extends \Eloquent
{
    protected $fillable = [];
    protected $table = 'api_logs';
    protected $primaryKey = 'log_id';
    protected $guarded = array('log_id');
}
