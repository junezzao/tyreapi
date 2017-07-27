<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;
use Carbon\Carbon;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;

class Data extends BaseModel
{
    use SoftDeletes;

    protected $table = 'data';
    
    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected $fillable = ['sheet_id', 'line_number', 'jobsheet_date', 'jobsheet_no', 'inv_no', 'inv_amt', 'jobsheet_type', 'customer_name', 'truck_no', 'pm_no', 'trailer_no', 'odometer', 'position', 'in_attr', 'in_price', 'in_size', 'in_brand', 'in_pattern', 'in_retread_brand', 'in_retread_pattern', 'in_serial_no', 'in_job_card_no', 'out_reason', 'out_size', 'out_brand', 'out_pattern', 'out_retread_brand', 'out_retread_pattern', 'out_serial_no', 'out_job_card_no', 'out_rtd', 'created_at', 'updated_at'];

    public function sheet()
    {
        return $this->belongsTo('App\Models\DataSheet', 'sheet_id');
    }

    public function getInvAmtAttribute($value)
    {
        return 'RM '.number_format($value, 2);
    }
}