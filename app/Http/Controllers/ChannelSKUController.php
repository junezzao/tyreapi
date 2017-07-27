<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use App\Repositories\ChannelSKURepository as ChannelSKURepo;
use App\Repositories\Criteria\ChannelSKU\ByChannel;
use App\Repositories\Criteria\ChannelSKU\ByHubwireSKU;
use Cache;

class ChannelSKUController extends Controller
{

    private $channelsku;
    private $oauth;
    private $inputs;


    public function __construct(ChannelSKURepo $channelsku)
    {
        $this->channelsku = $channelsku;
        $this->oauth = \OAuthClient::find(\Authorizer::getResourceOwnerId());
        $this->inputs = \Input::all();
        if ($this->oauth->authenticatable_type === 'Channel') {
            $this->inputs['channel_id'] = $this->oauth->authenticatable->channel_id;
            $this->channelsku->pushCriteria(new ByChannel($this->inputs['channel_id']));
        }
    }

    public function index()
    {
        // return response()->json(['channel_skus'=> $this->channelsku->skip(\Input::get('start',0))->take(\Input::get('limit',50))->all()]);
    }

    public function show($id)
    {
        // return response()->json($this->channelsku->findOrFail($id));
    }

    public function store()
    {
        // return response()->json($this->channelsku->create($this->inputs));
    }

    public function update($id)
    {
        // return response()->json($this->channelsku->update($this->inputs, $id));
    }

    public function destroy($id)
    {
        // return response()->json($this->channelsku->delete($id));
    }

    public function byHubwire($hwsku)
    {
        $this->channelsku->pushCriteria(new ByHubwireSKU($hwsku));
        return response()->json($this->channelsku->first());
    }
}
