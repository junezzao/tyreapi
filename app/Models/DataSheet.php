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

class DataSheet extends BaseModel
{
    use SoftDeletes;

    protected $table = 'data_sheet';
    
    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected $fillable = [
        'user_id',
        'filename',
        'total_count',
        'invalid_count',
        'invalid_pct',
        'summary',
        'remarks',
        'created_at',
        'updated_at'
    ];    
    
    public function getRemarksAttribute($value)
    {
        return json_decode($value, true);
    }

    public function getSummaryAttribute($value)
    {
        return json_decode($value, true);
    }

    public function data()
    {
        return $this->hasMany('App\Models\Data', 'sheet_id');
    }

    public function health()
    {
        $settingRow = \DB::table('settings')->where('name', 'Data Health')->first();
        $healthLevels = json_decode($settingRow->value, true);

        if($this->invalid_pct <= 0) {
            return $healthLevels[0];
        }
        else {
            foreach($healthLevels as $level) {
                $min_level = $level['min_level'];
                $max_level = $level['max_level'];

                if($this->invalid_pct > $level['min_level'] && $this->invalid_pct <= $level['max_level']) {
                    return $level;
                }
            }
        }

        return [
            'name'      => 'Undefined',
            'color'     => '#aaa',
            'message'   => ''
        ];
    }
}