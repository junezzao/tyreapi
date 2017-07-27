<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use App\Repositories\SKURepository as SKURepo;
use App\Repositories\Criteria\SKU\ByChannel;
use App\Repositories\Criteria\SKU\ByHubwireSKU;
use Cache;

class SKUController extends Controller
{

    private $sku;
    private $oauth;
    private $inputs;


    public function __construct(SKURepo $sku)
    {
        $this->sku = $sku;
        $this->oauth = \OAuthClient::find(\Authorizer::getResourceOwnerId());
        $this->inputs = \Input::all();
        if ($this->oauth->authenticatable_type === 'Channel') {
            $this->inputs['channel_id'] = $this->oauth->authenticatable_id;
            $this->sku->pushCriteria(new ByChannel($this->inputs['channel_id']));
        }
    }

    public function index()
    {
        // return response()->json(['sku'=> $this->sku->with('combinations')->skip(\Input::get('start',0))->take(\Input::get('limit',50))->all()]);
    }

    public function show($id)
    {
        return response()->json([
            'code' => config('globals.status_code.OK_STATUS'),
            'sku'=>$this->sku->with('combinations')->findOrFail($id)->toAPIResponse()
            ]);
    }

    public function store()
    {
        // return response()->json($this->sku->create($this->inputs));
    }

    public function update($id)
    {
        // return response()->json($this->sku->update($this->inputs, $id));
    }

    public function destroy($id)
    {
        // return response()->json($this->sku->delete($id));
    }

    public function byHubwire($hwsku)
    {
        $criteriaHwSku = new ByHubwireSKU($hwsku);
        $this->sku->getByCriteria($criteriaHwSku);
        return response()->json($this->sku->first());
    }
}
