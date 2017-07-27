<?php
namespace App\Modules\Fulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Repositories\Contracts\OrderRepository;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\Order;
use App\Models\User;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use Log;
use DB;
use Input;

use App\Modules\Fulfillment\Repositories\Contracts\ReturnRepository;
use App\Repositories\Criteria\Order\ByMerchant;

class OrdersController extends Controller
{
	protected $orderRepo;
	protected $returnsRepo;

	public function __construct(OrderRepository $orderRepo, ReturnRepository $returnRepo, Authorizer $authorizer)
	{
		$this->middleware('oauth');
		$this->orderRepo = $orderRepo;
		$this->returnRepo = $returnRepo;
		$this->authorizer = $authorizer;
		$token_client = \OAuthClient::find($authorizer->getClientId());
		// Mobile Token
		if(strcasecmp($token_client->authenticatable_type, 'HWMobile')==0)
		{
			$this->user = User::with('merchant')->find($authorizer->getResourceOwnerId());
			$this->orderRepo->pushCriteria(new ByMerchant($this->user->merchant_id));
			// auto include merchant id in datatable columns search
			$merchant = ['name'=>'merchant_id','search'=>['value'=>$this->user->merchant_id]];
			$columns = request()->get('columns', []);
			$columns = array_merge($columns, [$merchant]);
			request()->merge(['columns'=>$columns]);
		}
		
	}
	public function index()
	{
		$data['statuses'] = Config::get('globals.sales.search_status');
		$data['payments'] = Config::get('globals.sales.payment');

		return response()->json($data);
	}

	// search orders
    public function search(Request $request)
    {
        $response = $this->orderRepo->search($request);
        return response()->json($response);
    }

    // search orders
    public function searchDB(Request $request)
    {
    	$token_client = \OAuthClient::find($this->authorizer->getClientId());
    	if(strcasecmp($token_client->authenticatable_type, 'HWMobile')==0)
		{
        	$response = $this->orderRepo->searchDB($request, $this->user->merchant_id);
        }else{
        	$response = $this->orderRepo->searchDB($request);
        }
        return response()->json($response);
    }

    public function countLevels()
    {
    	$response = $this->orderRepo->countLevels();
    	return response()->json($response);
    }

	public function countOrders(Request $request)
	{
		$response = $this->orderRepo->countOrders($request);

		return response()->json($response);
	}

	public function show($id)
	{
		$order = $this->orderRepo->with('channel')->find($id);
		return response()->json($order);
	}

	public function getNotes($id)
	{
		$data['orderId'] = $id;
		$data['adminId'] = $this->authorizer->getResourceOwnerId();
		$notes = $this->orderRepo->getOrderNotes($data);
		return response()->json($notes);
	}

	public function getItems($id)
	{
		$items = $this->orderRepo->getOrderItems($id);
		return response()->json($items);
	}

	public function getHistory($id)
	{
		$data['orderId'] = $id;
		$data['adminId'] = $this->authorizer->getResourceOwnerId();
		$records = $this->orderRepo->getHistory($data);
		return response()->json($records);
	}

	public function getThirdPartyOrder($channel_id, $order_code)
    {
        $channel = Channel::find($channel_id);
        $channelType = ChannelType::find($channel->channel_type_id);

        $elevStController = $channelType->controller;

        $order_proc = new OrderProc($channel->id, new $elevStController);
        $order = $order_proc->getSingleOrder($order_code);

        if(isset($order['response']['success']) && $order['response']['success'] == false)
            return response()->json($order['response']);
        else
            return response()->json($order['response'][$order_code]);
    }

	public function createManualOrder(Request $request)
	{
		$request->merge(array('user_id' => $this->authorizer->getResourceOwnerId()));
		$order = $this->orderRepo->createManualOrder($request);
		$order = $this->orderRepo->createOrder($request->channel, $order);
		return response()->json($order);
	}

	public function cancelItem($order_id, $item_id) {
		// $data['store_credit'] = request()->get('store_credit', null);
		$data['order_id'] = $order_id;
		$data['item_id'] = $item_id;
		$data['user_id'] = $this->authorizer->getResourceOwnerId();

		$result = $this->orderRepo->processCancelReturn($data , true);

		return response()->json($result);
	}

	public function returnItem($order_id, $item_id) {
		$data['store_credit'] = request()->get('store_credit', null);
		$data['remark'] = request()->get('return_reason', '');
		$data['order_id'] = $order_id;
		$data['item_id'] = $item_id;
		$data['user_id'] = $this->authorizer->getResourceOwnerId();

		$result = $this->orderRepo->processCancelReturn($data , false);

		return response()->json($result);
	}

	public function update(Request $request, $id)
	{
		$data = $request->input('data');
		$order = $this->orderRepo->find($id);
		$user = $this->authorizer->getResourceOwnerId();

		$channel = Channel::find($order->channel_id);
        $channelType = ChannelType::find($channel->channel_type_id);

        $controller = $channelType->controller;
		$order_proc = new OrderProc($channel->id, new $controller);
        $response = $order_proc->updateOrderDetails($order->id, $data, $user);
		return $response->getContent();
	}

	public function readyToShip($id)
	{
		$order = $this->orderRepo->find($id);
		$userId = $this->authorizer->getResourceOwnerId();

        // get channel of the order
        $channel = Channel::where('id', $order->channel_id)->where('status', 'Active')->firstOrFail();

        $order_proc = new OrderProc($channel->id, new $channel->channel_type->controller, $id);
        $response = $order_proc->readyToShip($userId);

        return response()->json($response);
    }

    public function getReturnsAndCancelledItems($order_id)
    {
    	return $this->returnRepo->findAllBy('order_id', $order_id);
    }

    public function createNote(Request $request, $id)
    {
		$userId = $this->authorizer->getResourceOwnerId();
    	$response = $this->orderRepo->createNote($request, $id, $userId);
    	return response()->json($response);
    }

    public function packItem(Request $request, $order_id){
    	$response = $this->orderRepo->packItem($request, $order_id, $this->authorizer->getResourceOwnerId());
    	return response()->json($response);
    }

    public function updateItemStatus(Request $request, $order_id){
		$userId = $this->authorizer->getResourceOwnerId();
    	$response = $this->orderRepo->updateItemStatus($request, $order_id, $userId);
    	return response()->json($response);
    }

    public function getPromotionCodes($orderId)
    {
    	$response = $this->orderRepo->getPromotionCodes($orderId);
    	return response()->json($response);
    }

    public function getOrderSheetInfo($orderId) {
        $response = $this->orderRepo->getOrderSheetInfo($orderId);	
    	return response()->json($response);
    }

    public function getReturnSlipInfo($orderId) {
    	$response = $this->orderRepo->getReturnSlipInfo($orderId);
    	return response()->json($response);
    }
}