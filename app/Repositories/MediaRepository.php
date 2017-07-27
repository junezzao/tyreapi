<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use App\Models\Media;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use Illuminate\Support\Facades\Validator;

class MediaRepository extends Repository
{

    public function model()
    {
        return 'App\Models\Media';
    }

    public function create(array $inputs)
    {
        $rules = array(
            'filename'     =>    'required|string',
            'ext'          =>    'required|string',
            'media_url'    =>    'required|string',
            'media_key'    =>    'required|string',
        );

        $v = Validator::make($inputs, $rules);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $ext = $inputs['ext'];
        if($ext[0] != '.'){
            $ext = '.' . $ext;
        }
        $inputs['ext'] = $ext;

        $media = parent::create($inputs);
        return $this->find($media->media_id);
    }
}
