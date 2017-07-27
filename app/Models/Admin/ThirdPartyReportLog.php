<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;

class ThirdPartyReportLog extends BaseModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'third_party_report_log';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['tp_report_id', 'old_value', 'new_value', 'field', 'modified_by'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     *
     * Relationships
     *
     */
    public function third_party_reports()
    {
        return $this->belongsTo('App\Models\Admin\ThirdPartyReport', 'tp_report_id');
    }

    public function modified_by() {
        return $this->belongsTo('App\Models\User', 'modified_by');
    }
}
