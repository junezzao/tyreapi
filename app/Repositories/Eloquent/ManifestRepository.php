<?php
namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\ManifestRepository as ManifestRepositoryInterface;
use App\Repositories\Repository as Repository;
use App\Models\Admin\PickingManifest;
use App\Models\Admin\PickingItem;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\OrderStatusLog;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\SKU;
use Carbon\Carbon;
use Activity;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Event;
use App\Events\OrderUpdated;

class ManifestRepository extends Repository implements ManifestRepositoryInterface
{
	protected $model;
    protected $user_id;
	protected $skipCriteria = true;
    static $NUM_OF_ORDERS = 20;

	public function __construct(PickingManifest $model)
    {
        // define constant for number of orders to be selected per picking manifest
        // define("NUM_OF_ORDERS", 20);
        $this->model = $model;
        $this->user_id = Authorizer::getResourceOwnerId();
    }

    public function model()
    {
        return 'App\Models\Admin\PickingManifest';
    }

    public function generateManifest($request) {
        $newStatus = Order::$newStatus;
        $ids = array(); // array to store all unique order ids
        $remainder = 0;
        $channelTypes = '';
        // if user selected specific channel types
        if (!empty($request->input('channel_types'))) {
            $channelTypes = implode(", ", $request->input('channel_types'));

            $query = "SELECT orders.id FROM orders
                        LEFT JOIN channels on channels.id = orders.channel_id
                        LEFT JOIN channel_details on channel_details.channel_id = orders.channel_id
                        WHERE orders.status = $newStatus
                        AND orders.cancelled_status = 0
                        AND channel_details.picking_manifest = 1
                        AND orders.id NOT IN
                            (SELECT order_items.order_id FROM picking_items
                            LEFT JOIN order_items ON order_items.id = picking_items.item_id
                            WHERE picking_items.item_id = order_items.id
                            AND picking_items.item_type='OrderItem')
                        AND channel_type_id IN ($channelTypes)
                        ORDER BY orders.tp_order_date ASC limit ". static::$NUM_OF_ORDERS;

            $orders = \DB::select(\DB::raw($query));
            foreach ($orders as $order) {
                $ids[] = $order->id;
            }

            // if not enough orders
            $numOrders = count($orders);
            if ($numOrders < static::$NUM_OF_ORDERS) {
                $remainder = static::$NUM_OF_ORDERS - $numOrders;
            }
        }

        else {
            $query = "SELECT orders.id FROM orders
                        LEFT JOIN channel_details on channel_details.channel_id = orders.channel_id
                        WHERE orders.status = $newStatus
                        AND orders.cancelled_status = 0
                        AND channel_details.picking_manifest = 1
                        AND orders.id NOT IN
                            (SELECT order_items.order_id FROM picking_items
                            LEFT JOIN order_items ON order_items.id = picking_items.item_id
                            AND picking_items.item_type = 'OrderItem'
                            WHERE picking_items.item_id = order_items.id)
                        ORDER BY orders.tp_order_date ASC limit ". static::$NUM_OF_ORDERS;

            $orders = \DB::select(\DB::raw($query));
            foreach ($orders as $order) {
                $ids[] = $order->id;
            }
        }

        // get more orders if not enough of orders of desired channel type
        if ($remainder > 0) {
            $query = "SELECT orders.id FROM orders
                        LEFT JOIN channel_details on channel_details.channel_id = orders.channel_id
                        WHERE orders.status = $newStatus
                        AND orders.cancelled_status = 0
                        AND channel_details.picking_manifest = 1
                        AND orders.id NOT IN
                            (SELECT order_items.order_id FROM picking_items
                            LEFT JOIN order_items ON order_items.id = picking_items.item_id
                            WHERE picking_items.item_id = order_items.id
                            AND picking_items.item_type = 'OrderItem')
                        ORDER BY orders.tp_order_date ASC limit $remainder";

            $orders = \DB::select(\DB::raw($query));
            foreach ($orders as $order) {
                $ids[] = $order->id;
            }
        }

        $response = array();
        if (!empty($ids)) {
            // select order items for all orders
            $orderItems  =  OrderItem::select('order_items.id', 'order_items.order_id', 'channel_sku.channel_sku_id', 'order_items.quantity')
                                ->leftjoin('channel_sku', 'order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                                ->whereIn('order_items.order_id', $ids)
                                ->get();

            \DB::beginTransaction();
            if (!empty($orderItems)) {
                $updated_at = date("Y-m-d H:i:s");
                // Update sales status from 'Paid' to 'Picking'
                \DB::table('orders')->whereIn('id', $ids)->update(['status' => Order::$pickingStatus, 'updated_at' => $updated_at]);

                foreach($ids as $id){
                    // Log the status changes
                    $log = new OrderStatusLog;
                    $log->order_id = $id;
                    $log->user_id = $this->user_id;

                    $log->from_status = "New";
                    $log->to_status = "Picking";
                    $log->save();
                    // Register into order history
                    $eventInfo = array(
                        'fromStatus' => Order::$statusCode[$log->from_status],
                        'toStatus' => Order::$statusCode[$log->to_status],
                    );
                    Event::fire(new OrderUpdated($id, 'Status Updated', 'order_status_log', $log->id, $eventInfo, $this->user_id));
                }

                // Generate picking manifest
                $manifestID = \DB::table('picking_manifests')->insertGetId(['user_id' => 0, 'status' => 'New', 'priority' => $channelTypes, 'creator_id' => $this->user_id, 'created_at' => date("Y-m-d H:i:s"), 'updated_at' => date("Y-m-d H:i:s")]);

                // Generate picking items
                foreach($orderItems as $item) {
                    for ($x=0; $x<$item->quantity; $x++) {
                        \DB::table('picking_items')->insert(['manifest_id' => $manifestID, 'channel_sku_id' => $item->channel_sku_id, 'item_id'=>$item->id, 'created_at' => date("Y-m-d H:i:s"), 'updated_at' => date("Y-m-d H:i:s")]);
                    }

                    $orderItem = OrderItem::findOrFail($item->id);
                    $oldStatus = $orderItem->status;
                    OrderItem::where('id', $item->id)->update(['status' => 'Picking']);

                    Event::fire(new OrderUpdated($orderItem->order_id, 'Item Status Updated', 'order_items', $orderItem->id, array('fromStatus' => $oldStatus, 'toStatus' => $orderItem->status), $this->user_id));
                }
                $response['success'] = true;
                $response['message'] = "Picking manifest generated.";
                Activity::log('Manifest ('.$manifestID.') was generated.', $this->user_id);
            }
            else {
                $response['success'] = false;
                $response['message'] = "No orders items found for selected orders.";
            }

            \DB::commit();
        }
        else {
            $response['success'] = false;
            $response['message'] = "No new orders found.";
        }
        return $response;
    }

    // search manifests
    public function search($request)
    {
        $type = $request->get('type',0);
        // separate query for counting number of manifests
        $count = \DB::select(\DB::raw("select count(*) as total from picking_manifests where type='".$type."'"));

        $manifests = PickingManifest::leftjoin('users as user', 'picking_manifests.user_id', '=', 'user.id')
                    ->leftjoin('users as creator', 'picking_manifests.creator_id', '=', 'creator.id')
                    ->where('picking_manifests.type','=', $type)
                    ->select('picking_manifests.*', 'user.first_name as user_name', 'creator.first_name as creator_name')
                    ->skip($request->input('start', 0))
                    ->take($request->input('length', 10));

        if ($request->input('order'))
        {
            $colNum = $request->input('order')[0]['column'];
            $direction = null;
            if ($request->input('order')[0]['dir'] == 'desc')
            {
                $direction = 'desc';
            }else
            {
                $direction = 'asc';
            }
            $colName = $request->input("columns")[$colNum]['name'];
            $manifests = $manifests->orderBy($colName, $direction);
        }
        else{
            $manifests = $manifests->orderBy('created_at', 'desc');
        }
        $manifests = $manifests->get();

        $response['success'] = true;
        $response['manifests'] = $manifests->toArray();
        $response['recordsFiltered'] = $count[0]->total;
        $response['recordsTotal'] = $count[0]->total;

        return $response;
    }

    // assign manifest to self
    public function pickUpManifest($request) {
        $id = $request->input('id');
        $manifest = PickingManifest::find($id);
        $data['success'] = false;
        if (!is_null($manifest) && $manifest->user_id==0) {
            $manifest->user_id = $this->user_id;
            $manifest->status = 'In Progress';
            $manifest->pickup_date = date("Y-m-d H:i:s");
            $manifest->save();
            $data['success'] = true;

            Activity::log('Manifest ('.$id.') was picked up.', $this->user_id);
        }

        return $data;
    }

    // show picking items
    public function pickingItems($id) {
        //return PickingItem::where('manifest_id','=',$id)->get();
        return \DB::table('picking_items')->select('item_id','picking_items.id', 'sku.hubwire_sku', 'products.name', 'channel_sku.channel_sku_coordinates', 'order_items.order_id', 'orders.tp_order_date', 'order_items.status', 'sku.sku_id')
                ->leftjoin('order_items', 'order_items.id', '=', 'picking_items.item_id')    
                ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')    
                ->leftjoin('channel_sku', 'order_items.ref_id', '=', 'channel_sku.channel_sku_id')
                ->leftjoin('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                ->leftjoin('products', 'products.id', '=', 'sku.product_id')
                ->where('manifest_id', '=', $id)
                ->groupBy('picking_items.id')
                ->get();
        
    }

    public function getUniqueOrders($id) {
        $statuses = array_flip(Order::$statusCode);
        $orders = PickingItem::select('orders.id','orders.tp_order_date', 'orders.cancelled_status', 'channels.name', 'orders.status')
                ->leftjoin('order_items', 'order_items.id', '=', 'picking_items.item_id')
                ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                ->leftjoin('channels', 'orders.channel_id', '=', 'channels.id')
                ->where('manifest_id', '=', $id)
                ->groupBy('orders.id')
                ->get();

        // change status code to name
        foreach ($orders as $order) {
            $order->status = isset($statuses[$order->status])? $statuses[$order->status] : $order->status;
            $order->cancelled_status = ($order->cancelled_status)? 'Cancelled' : 'Not Cancelled';
        }

        return $orders;
    }

    // function for handling scanning hubwire sku
    public function pickItem($request, $id) {
        $hubwireSku = trim($request->input('hubwire_sku'));

        $item = PickingItem::select('picking_manifests.user_id', 'item_id', 'picking_items.id', 'sku.hubwire_sku', 'products.name', 'order_items.status', 'orders.cancelled_status', \DB::raw('order_items.status as order_item_status'))
            ->leftjoin('picking_manifests', 'picking_manifests.id', '=', 'picking_items.manifest_id')
            ->leftjoin('order_items', 'picking_items.item_id', '=', 'order_items.id')
            ->leftjoin('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftjoin('channel_sku', 'picking_items.channel_sku_id', '=', 'channel_sku.channel_sku_id')
            ->leftjoin('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
            ->leftjoin('products', 'products.id', '=', 'sku.product_id')
            ->where('sku.hubwire_sku', '=', $hubwireSku)
            ->where('picking_items.manifest_id', '=', $id)
            ->where('order_items.status', '=', 'Picking')
            ->orderBy('channel_sku.channel_sku_coordinates', 'desc')
            ->orderBy('sku.sku_id', 'desc')
            ->orderBy('orders.id', 'desc')
            ->first();

        $data = array();
        $data['success'] = true;
        $data['readyToComplete'] = false;

        if (!is_null($item) && trim($item->hubwire_sku)==trim($hubwireSku)) {
            // ensure the user picking this item is assigned to this manifest
            if ($item->user_id==$this->user_id) {
                // ensure order hasn't been cancelled
                if ($item->cancelled_status==1) {
                    $data['message'] = "The order for this item has been cancelled.";
                    $data['success'] = false;
                }
                else if ($item->manifest_status=="Completed") {
                    $data['message'] = "This picking manifest has been marked completed.";
                    $data['success'] = false;
                }
                else if ($item->order_item_status=="Cancelled") {
                    $data['message'] = "This item has been cancelled.";
                    $data['success'] = false;
                }
                else {
                    //$pItem = PickingItem::find($item->id);
                    //$item->status = 'Picked';
                    //$item->save();
                    $orderItem = OrderItem::find($item->item_id);

                    if (!is_null($orderItem)) {
                        $oldStatus = $orderItem->status;
                        $orderItem->status = 'Picked';
                        $orderItem->save();

                        Event::fire(new OrderUpdated($orderItem->order_id, 'Item Status Updated', 'order_items', $orderItem->id, array('fromStatus' => $oldStatus, 'toStatus' => $orderItem->status), $this->user_id));
                    }
                    $data['orderItemId'] = $item->item_id;
                    $data['message'] = "Success!";
                    $data['readyToComplete'] = $this->isReadyToComplete($id);
                }
            }
            else if ($item->user_id==0) {
                $data['message'] = "This manifest has not been picked up by anyone. You must pick up this manifest to begin picking items.";
                $data['success'] = false;
            }
            else {
                $data['message'] = "You are not authorized to pick items for this picking manifest.";
                $data['success'] = false;
            }
        }
        else {
            $sku = SKU::select('products.name')->leftjoin('products', 'products.id', '=', 'sku.product_id')->where('sku.hubwire_sku','=',$hubwireSku)->first();
            $productName = (is_null($sku))?'':"-".$sku->product_name;

            $data['message'] = "The item ".$hubwireSku.$productName." was not found on the list, has already been picked, or has been marked 'Out of Stock'.";
            $data['success'] = false;
        }
        return $data;
    }

    // to inform the system that the picking item is out of stock
    public function outOfStock($request, $id) {
        $item = PickingItem::where('id', '=', $request->input('id'))->with('manifest')->first();
        $data['success'] = false;
        $data['readyToComplete'] = false;

        if ($item->manifest_id==$id && $item->manifest->user_id==$this->user_id) {

            $orderItem = OrderItem::find($item->item_id);

            if (!is_null($orderItem) && !in_array($orderItem->status, ['Cancelled', 'Verified', 'Returned'])) {
                $oldStatus = $orderItem->status;
                $orderItem->status = 'Out of Stock';
                $orderItem->save();

                Event::fire(new OrderUpdated($orderItem->order_id, 'Item Status Updated', 'order_items', $orderItem->id, array('fromStatus' => $oldStatus, 'toStatus' => $orderItem->status), $this->user_id));

                $data['orderItemId'] = $orderItem->id;
                $data['success'] = true;
                $data['readyToComplete'] = $this->isReadyToComplete($id);

                Activity::log('Order item ('.$data['orderItemId'].') was marked out of stock.', $this->user_id);
            }else{
                $data['orderItemId'] = $orderItem->id;
                $data['success'] = true;
                $data['readyToComplete'] = $this->isReadyToComplete($id);
                $data['message'] = "This items has already been cancelled/fulfilled.";
            }
        }
        else if ($item->manifest->user_id==0) {
            $data['message'] = "This manifest has not been picked up by anyone. You must pick up this manifest to update it.";
        }
        else if ($item->manifest->user_id!=$this->user_id) {
            $data['message'] = "You are not authorized to update this picking manifest.";
        }
        return $data;
    }

    // Mark manifest as complete
    public function completed($id) {
        $manifest = PickingManifest::where('id', '=', $id)->where('status', '=', 'In Progress')->first();
        $data['success'] = false;
        if (!is_null($manifest)) {
            if ($manifest->user_id!=$this->user_id) {
                $data['message'] = "You are not authorized to update this picking manifest.";
            }
            else if (!$this->isReadyToComplete($manifest->id)) {
                $data['message'] = "This manifest is not ready to be completed. Please pick all items before proceeding.";
            }
            else {
                $manifest->status = 'Completed';
                $manifest->touch();
                $manifest->save();
                $orderIds = PickingItem::leftjoin('order_items','picking_items.item_id','=','order_items.id')->leftjoin('orders','order_items.order_id', '=', 'orders.id')->select(\DB::raw('distinct(order_items.order_id)'))->where('picking_items.manifest_id', '=', $id)->get();
                foreach ($orderIds as $order) {

                    $pickedItems = PickingItem::leftjoin('order_items','picking_items.item_id','=','order_items.id')->where('order_id', '=', $order->order_id)->where('order_items.status','=', 'Picked')->count();
                    $allItems = PickingItem::leftjoin('order_items','picking_items.item_id','=','order_items.id')->where('order_id', '=', $order->order_id)->count();

                    $sale = Order::where('id', '=', $order->order_id)->first();

                    // if all items are out of stock ( no picked items )
                    /*
                    if ($pickedItems==0) {
                        $sale->status = $sale->getStatusCodeByName('New');
                        $sale->save();

                        $log = new OrderStatusLog;
                        $log->order_id = $order->order_id;
                        $log->user_id = $this->user_id;

                        $log->from_status = "Picking";
                        $log->to_status = "New";
                        $log->save();

                        // Register into order history
                        $eventInfo = array(
                            'fromStatus' => Order::$statusCode[$log->from_status],
                            'toStatus' => Order::$statusCode[$log->to_status],
                        );
                        Event::fire(new OrderUpdated($order->order_id, 'Status Updated', 'order_status_log', $log->id, $eventInfo, $this->user_id));
                    }
                    */

                    // if some or all items for an order have been picked
                    if ($pickedItems<=$allItems && $pickedItems > 0) {
                        $sale->status = Order::$packingStatus;
                        $sale->save();

                        $log = new OrderStatusLog;
                        $log->order_id = $order->order_id;
                        $log->user_id = $this->user_id;

                        $log->from_status = "Picking";
                        $log->to_status = "Packing";
                        $log->save();

                        if ($pickedItems<$allItems) {
                            $sale->partially_fulfilled = 1;
                            $sale->save();
                        }

                        // Register into order history
                        $eventInfo = array(
                            'fromStatus' => Order::$statusCode[$log->from_status],
                            'toStatus' => Order::$statusCode[$log->to_status],
                        );
                        Event::fire(new OrderUpdated($order->order_id, 'Status Updated', 'order_status_log', $log->id, $eventInfo, $this->user_id));
                    }
                }
                $data['success'] = true;
                Activity::log('Manifest ('.$id.') was completed.', $this->user_id);
            }
        }
        return $data;
    }

    public function cancel($id)
    {

    }

    // check if picking manifest is ready to be completed
    public function isReadyToComplete($id) {
        $items = PickingItem::where('manifest_id', '=', $id)
                    ->leftjoin('order_items', 'picking_items.item_id', '=', 'order_items.id')
                    ->where('order_items.status', '=', 'Picking')->count();

        if ($items>0)
            return false;
        else
            return true;
    }

    // returns number of new orders to be picked group by channel type
    public function count() {
        $newStatus = Order::$newStatus;
        $channelTypes = config('globals.channel_type_picking_manifest');
        $countStatements = '';

        foreach ($channelTypes as $key => $value) {
            $countStatements .= "count(case channels.channel_type_id when $key then 1 else null end) as " . str_replace(" ", "_", strtolower($value)) . ", ";
        }

        // remove trailing comma
        $countStatements = rtrim($countStatements, ", ");

        // Count orders for each channel type
        $query =   "SELECT $countStatements FROM orders
                    LEFT JOIN channels ON channels.id = orders.channel_id
                    LEFT JOIN channel_details on channel_details.channel_id = orders.channel_id
                    WHERE `orders`.`status` = $newStatus
                    AND orders.cancelled_status = 0
                    AND channel_details.picking_manifest = 1
                    AND orders.id NOT IN (
                        SELECT order_items.order_id FROM picking_items
                        LEFT JOIN order_items ON order_items.id = picking_items.item_id
                        AND picking_items.item_type = 'OrderItem'
                        WHERE picking_items.item_id = order_items.id
                    )";

        $count = \DB::select(\DB::raw($query));
        return $count;
    }

    public function assignUser($data, $id)
    {
        $manifest = $this->find($id);
        // \Log::info($manifest->toArray());
        if($manifest->status == 'New'){
            // update pickup date
            $data['pickup_date'] = date("Y-m-d H:i:s");
            $data['status'] = 'In Progress';
        }
        // \Log::info($data);
        $model = parent::update($data, $id);

        return $this->find($id);
    }
}
