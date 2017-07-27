<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;
use Activity;

class ThirdPartyReportRemark extends BaseModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'third_party_report_remarks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['tp_report_id', 'added_by', 'remarks', 'type', 'resolve_status', 'completed_at'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];


    protected $with = ['added_by'];


    /**
     *
     * Relationships
     *
     */
    public function third_party_reports()
    {
        return $this->belongsTo('App\Models\Admin\ThirdPartyReport', 'tp_report_id');
    }


    public function added_by()
    {
        return $this->belongsTo('App\Models\User', 'added_by')->select('id', 'first_name', 'last_name');
    }


    public static function boot()
    {
        parent::boot();

        ThirdPartyReportRemark::created(function ($Obj) {
            if ($Obj->added_by > 0) {
                Activity::log('Remark ' . $Obj->id . ' added for ThirdPartyReport ' . $Obj->tp_report_id . '.', $Obj->added_by);
            }
        });
    }

    public function getCompletedAtAttribute($value)
    {   
        if(is_null($value)){
            return $value;
        }
        else{
            try{
                if(session()->has('user_timezone')){
                    $adminTz = session('user_timezone');
                }else{
                    $userId = Authorizer::getResourceOwnerId();
                    $adminTz = User::where('id', '=', $userId)->value('timezone');
                    session(['user_timezone' => $adminTz]);
                }
                if($this->attributes['completed_at'] != '0000-00-00 00:00:00')
                    return Helper::convertTimeToUserTimezone($value, $adminTz);
                else
                    return $value;
            }catch(NoActiveAccessTokenException $e){
                return $value;
            }
        }
    }
}
