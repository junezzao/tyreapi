<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use App\Repositories\WebhooksRepository as WebhooksRepo;
use App\Repositories\Criteria\Webhooks\ByChannel;
use App\Models\Admin\Webhook;
use Cache;

class WebhooksController extends Controller
{

    private $webhook;
    private $oauth;
    private $inputs;


    public function __construct(WebhooksRepo $webhook)
    {
        $this->webhook = $webhook;
        $this->oauth = \OAuthClient::find(\Authorizer::getResourceOwnerId());
        $this->inputs = \Input::all();
        $this->inputs['format'] = 'json';
        $this->inputs['type'] = 1; // outgoing events
        
        unset($this->inputs['HTTP_Authorization']);
        if ($this->oauth->authenticatable_type === 'Channel') {
            $this->inputs['channel_id'] = $this->oauth->authenticatable_id;
            $this->webhook->pushCriteria(new ByChannel($this->inputs['channel_id']));
        }
    }

    public function index()
    {
        return response()->json([
            'code'=>config('globals.status_code.OK_STATUS'),
            'webhooks'=> Webhook::apiResponse($this->webhook->where('type','=',$this->inputs['type'])->skip(\Input::get('start', 0))->take(\Input::get('limit', 50))->all()) 
            ]);
    }

    public function show($id)
    {
        return response()->json([
            'code'=>config('globals.status_code.OK_STATUS'),
            'webhook'=>Webhook::apiResponse($this->webhook->findOrFail($id)),
            ]);
    }

    public function store()
    {
        return response()->json([
            'code' => config('globals.status_code.OK_STATUS'),
            'webhook'=>Webhook::apiResponse($this->webhook->create($this->inputs))
            ]);
    }

    public function update($id)
    {
        return response()->json([
            'code' => config('globals.status_code.OK_STATUS'),
            'webhook' => Webhook::apiResponse($this->webhook->update($this->inputs, $id))
            ]);
    }

    public function destroy($id)
    {
        $success = $this->webhook->delete($id);
        return response()->json([
            'code' => 200,
            'success'=>$success?true:false
            ]);
    }
}
