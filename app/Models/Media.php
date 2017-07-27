<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;

class Media extends BaseModel
{
    protected $table = 'media';

    protected $fillable = ['filename', 'ext', 'media_url', 'media_key'];

    protected $primaryKey = 'media_id';

    use SoftDeletes;

    protected $dates = ['deleted_at'];

    public static function apiResponse($data)
    {
        $medias = $data;
        $single = false;
            
        if (empty($medias[0])) {
            $medias = [$medias];
            $single = true;
        }
        
        $result = array();
        foreach ($medias as $media) {
            $result[] = $media;
        }
        return $single?$result[0]:$result;
    }
}
