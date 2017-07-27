<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\User;
use App\Models\Media;
use LucaDegasperi\OAuth2Server\Authorizer;
use Activity;
use App\Repositories\MediaRepository as MediaRepo;
use Illuminate\Support\Collection;
use Illuminate\Container\Container as App;
use Log;
use Cache;

class MediaController extends Controller
{

    private $medias;
    private $inputs;
    protected $authorizer;
    protected $userID;

    public function __construct(MediaRepo $medias, Authorizer $authorizer)
    {
        $this->medias = $medias;
        $this->middleware('oauth');
        $this->authorizer = $authorizer;
        $this->userID = $this->authorizer->getResourceOwnerId();
        $this->inputs = \Input::all();
        unset($this->inputs['access_token']);
        unset($this->inputs['HTTP_Authorization']);
    }

    public function store(App $app, Collection $collection)
    {
        $media = $this->medias->create($this->inputs);
        Activity::log('User created new media, ID: ' . $media->media_id, $this->userID);
        return response()->json(Media::apiResponse($media));
    }

    public function storeMultiple(App $app, Collection $collection)
    {
        $response = array();
        $mediaInputs = json_decode($this->inputs['medias'], true);
        foreach ($mediaInputs as $input) {
            $mediaRepo = new MediaRepo($app, $collection);
            $media = $mediaRepo->create($input);
            Activity::log('User created new media, ID: ' . $media->media_id, $this->userID);
            $response[] = $media;
        }
        return response()->json(Media::apiResponse($response));
    }

    public function destroy($id)
    {
        $media = array();
        $media['media_id'] = $id;
        $media['success'] = $this->medias->delete($id);
        if ($media) {
            Activity::log('User deleted media, ID: ' . $id, $this->userID);
        }
        return response()->json(Media::apiResponse($media));
    }

    public function show($id)
    {
        return response()->json(Media::apiResponse($this->medias->findOrFail($id), $this->medias->getCriteria()));
    }
}
