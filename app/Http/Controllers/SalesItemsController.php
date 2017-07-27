<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use App\Repositories\SalesRepository as SalesRepo;
use App\Repositories\SalesItemRepository as ItemRepo;
use App\Repositories\MemberRepository as MemberRepo;
use App\Repositories\Criteria\SalesItem\ByChannel;
use App\Repositories\Criteria\SalesItem\BySales;
use Illuminate\Support\Collection;
use Illuminate\Container\Container as App;
use Cache;

class SalesItemsController extends Controller
{
    private $items;
    private $oauth;
    private $inputs;


    public function __construct(ItemRepo $items)
    {
        $this->items = $items;
        $this->oauth = \OAuthClient::find(\Authorizer::getResourceOwnerId());
        $this->inputs = \Input::except('access_token');
        if ($this->oauth->authenticatable_type === 'Channel') {
            $this->inputs['channel_id'] = $this->oauth->authenticatable->channel_id;
            $this->inputs['client_id'] = $this->oauth->authenticatable->client_id;
            $this->items->pushCriteria(new ByChannel($this->inputs['channel_id']));
        }
    }

    public function index($sale_id)
    {
        $this->items->pushCriteria(new BySales($sale_id));
        return response()->json([ 'items'=> \SalesItem::apiResponse($this->items->all()) ]);
    }

    public function show($sale_id, $id)
    {
        $this->items->pushCriteria(new BySales($sale_id));
        return response()->json(\SalesItem::apiResponse($this->items->findOrFail($id)));
    }
}
