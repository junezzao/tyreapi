<?php
namespace App\Modules\Fulfillment\Http\Controllers;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use App\Modules\Fulfillment\Repositories\Contracts\ReturnRepository;
use App\Repositories\Eloquent\SyncRepository;
use App\Repositories\RejectLogRepository as RejectRepo;
use App\Models\Admin\ReturnLog;
use Illuminate\Http\Request;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Order;
use App\Models\Admin\Channel;
use Activity;
use Event;
use App\Events\OrderUpdated;
use \DB;
use Illuminate\Http\Exception\HttpResponseException as HttpResponseException;
use App\Events\ChannelSkuQuantityChange;
use App\Http\Controllers\Controller;

class ReturnController extends Controller
{
    protected $returnRepo;
    protected $authorizer;

    public function __construct(ReturnRepository $returnRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->returnRepo = $returnRepo;
        $this->authorizer = $authorizer;
    }

    public function throwError($msg) {
        $errors = response()->json(array(
            'code' => 422,
            'error' => $msg
        ));

        throw new HttpResponseException($errors);
    }

    public function index()
    {
    }

    public function create()
    {
    }

    public function cancelOrder($order_id)
    {
        DB::beginTransaction();

        $order = Order::findOrFail($order_id);

        if($this->cancelled($order)) {
            $this->throwError('Order has already been cancelled.');
        }

        if(!$this->cancellable($order)) {
            $this->throwError('Order cannot be cancelled due to its status.');
        }

        $items = OrderItem::where('order_id', $order_id)->where('ref_type', 'ChannelSKU')->get();
        foreach($items as $item) {
            if(!in_array($item->status, array('Cancelled', 'Returned'))) {
                $this->throwError('All items must be cancelled or returned in order to cancel order.');
            }
        }

        $order->cancelled_status = 1;
        $order->cancelled_date = date("Y-m-d H:i:s");
        $order->save();

        Activity::log('Order ('. $order_id .') has been cancelled.', $this->authorizer->getResourceOwnerId());

        // fire order history
        event(new OrderUpdated($order_id, 'Order Cancelled', 'orders', $order_id, array(), $this->authorizer->getResourceOwnerId()));

        DB::commit();
        return response()->json($order);
    }

    public function show($id)
    {
        $returnLog = $this->returnRepo->find($id);
        return response()->json($returnLog);
    }

    public function edit($id)
    {
    }

    public function update($id)
    {
        DB::beginTransaction();

        \Input::merge(['user_id' => $this->authorizer->getResourceOwnerId()]);

        $returnLog = $this->returnRepo->update(\Input::all(), $id);

        $item = OrderItem::where('order_id', $returnLog->order_id)
                ->where('ref_type', 'ChannelSKU')
                ->findOrFail($returnLog->order_item_id);
        $channel_sku = ChannelSKU::where('channel_sku_id', $item->ref_id)->first();

        // sync to shopify
        $channel = Channel::findOrFail($channel_sku->channel_id);
        if ($channel->channel_type_id == 6) {
            $input['channel_id'] = $channel->id;
            $input['item_id'] = $item->id;
            $input['restock'] = ($returnLog->status == 'Restocked') ? true : false;

            $syncRepo = new SyncRepository;
            $newSync = $syncRepo->createItemRefund($input);
        }

        Activity::log('Return ('. $returnLog->id .') has been '. $returnLog->status.(!empty($returnLog->remark)?'. Reason: '.$returnLog->remark:'') , $this->authorizer->getResourceOwnerId());

        // fire order history event
        event(new OrderUpdated($returnLog->order_id, 'Returned Item: ' . $returnLog->status, 'return_log', $returnLog->id, array('orderItemId'=>$returnLog->order_item_id), $this->authorizer->getResourceOwnerId()));
        DB::commit();
        return response()->json($returnLog);
    }

    public function destroy($id)
    {
    }

    public function search()
    {
        $status = \Input::get('status');
        $order_id = \Input::get('order_id');
        $channel_id = \Input::get('channel_id');

        $returnLogs = ReturnLog::select('return_log.id', 'sku.hubwire_sku', 'products.name as product_name', 'orders.id as order_id', 'return_log.created_at', 'return_log.completed_at', 'return_log.status', 'return_log.quantity')
                        ->join('orders', 'orders.id', '=', 'return_log.order_id')
                        ->join('order_items', 'order_items.id', '=', 'return_log.order_item_id')
                        ->join('channel_sku', 'channel_sku.channel_sku_id', '=', 'order_items.ref_id')
                        ->join('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                        ->join('products', 'sku.product_id', '=', 'products.id')
                        ->where('order_items.ref_type', '=', 'ChannelSKU');

        if(\Input::has('status')) {
            $statuses = ['In Transit'];

            if (strcasecmp($status, 'done') == 0) {
                $statuses = ['Restocked', 'Rejected'];
            }

            $returnLogs = $returnLogs->whereIn('return_log.status', $statuses);
        }

        if(\Input::has('order_id')) {
            $returnLogs = $returnLogs->where('return_log.order_id', '=', \Input::get('order_id'));
        }

        if(\Input::has('channel_id')) {
            $returnLogs = $returnLogs->where('orders.channel_id', '=', $channel_id);
        }

        $returnLogs = $returnLogs->get()->toArray();

        return response()->json($returnLogs);
    }

    public function fulfilled($order){
        return $order->status >= Order::$shippedStatus ? true : false;
    }

    public function cancellable($order){
        return $order->status >= Order::$newStatus && $order->status <= Order::$shippedStatus ? true : false;
    }

    public function cancelled($order){
        return $order->cancelled_status == 1 ? true : false;
    }

}
