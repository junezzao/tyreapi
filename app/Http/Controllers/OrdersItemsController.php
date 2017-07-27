<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use App\Repositories\Eloquent\OrderItemRepository as ItemRepo;
use App\Repositories\MemberRepository as MemberRepo;
use App\Repositories\Criteria\OrderItem\ByChannel;
use App\Repositories\Criteria\OrderItem\ByOrder;
use Illuminate\Support\Collection;
use Illuminate\Container\Container as App;
use Cache;

class OrdersItemsController extends Controller
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
            $this->inputs['channel_id'] = $this->oauth->authenticatable_id;
            // $this->inputs['client_id'] = $this->oauth->authenticatable->client_id;
            $this->items->pushCriteria(new ByChannel($this->inputs['channel_id']));
        }
    }

    public function index($order_id)
    {
        $this->items->pushCriteria(new ByOrder($order_id));
        return response()->json([ 
            'code'  => config('globals.status_code.OK_STATUS'),
            'items'=> $this->items->all(['orders.id','order_items.*'])->toAPIResponse() 
            ]);
    }

    public function show($order_id, $id)
    {
        $this->items->pushCriteria(new ByOrder($order_id));
        return response()->json([
            'code'  => config('globals.status_code.OK_STATUS'),
            'item'=>$this->items->findOrFail($id,['orders.id','order_items.*'])->toAPIResponse()
            ]);
    }
}
