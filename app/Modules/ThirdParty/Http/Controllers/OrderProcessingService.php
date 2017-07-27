<?php
namespace App\Modules\ThirdParty\Http\Controllers;

use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\AdminController;
use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Repositories\Eloquent\OrderRepository as OrderRepo;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\Order;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Member;
use App\Models\Admin\Address;
use App\Models\Admin\OrderItem;
use App\Models\Admin\FailedOrder;
use App\Models\Admin\ReservedQuantity;
use App\Models\Admin\OrderStatusLog;
use App\Events\OrderUpdated;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\ReturnLog;
use DB;
use Activity;
use Log;
use stdClass;
use Input;
use Response;
use Event;
use Carbon\Carbon;
use App\Services\Mailer;
use App\Events\ReservedQuantityChange;

class OrderProcessingService extends AdminController
{
	private $thirdParty, $channel, $order, $orderItems, $orderItem, $sync;
	private $error = array();

	public function __construct($channel_id = null, MarketplaceInterface $thirdParty = null, $order_id = null, ThirdPartySync $sync = null)
	{
		try{

            $this->sync = !is_null($sync) ? $sync : null;

            if(!is_null($channel_id)) {
                $channel = Channel::with('channel_detail')->findOrFail($channel_id);
                $this->setChannel($channel);
            }

            if(!is_null($thirdParty)) {
                $this->setThirdPartyObj($thirdParty);
            }

			if(!is_null($order_id)) {
				$this->setOrder($order_id);
			}

		}
		catch(Exception $e){
			$error = array();
			$error['error_desc'] = $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
	}

	public function setChannel($channel)
	{
		$this->channel = $channel;
	}

	public function getChannel()
	{
		return $this->channel;
	}

	public function setThirdPartyObj(MarketplaceInterface $thirdParty)
	{
		$this->thirdParty = $thirdParty;
        $this->thirdParty->initialize($this->channel, $this->sync);
	}

	public function getThirdPartyObj()
	{
		return $this->thirdParty;
	}

	public function setOrder($order_id)
    {
        $this->order = Order::findOrFail($order_id);

        if (!is_null($this->thirdParty)) {
            $this->thirdParty->setOrder($this->order);
        }
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrderItems($order_id)
    {
        $this->orderItems = OrderItem::where('order_id', '=', $order_id)->get();
    }

    public function getOrderItems()
    {
        return $this->orderItems;
    }

    public function setOrderItem($orderId, $orderItemId)
    {
        $this->orderItem = OrderItem::where('order_id', '=', $orderId)->where('id', '=', $orderItemId)->first();
    }

    public function getOrderItem()
    {
        return $this->orderItem;
    }

	/**
	 * Get orders from a Marketplace channel
	 * @param datetime $createdAfter Get orders created after this date Y-m-d H:i:s
	 * @param datetime $until Get orders created until this date Y-m-d H:i:s
	 * @return
	 */
	public function getAndCreateOrders($createdAfter, $until)
	{
		$responses = $this->getOrders($createdAfter, $until);

        $return = array();

        if ($responses !== false) {
            foreach($responses as $response) {
                if(isset($response['success']) && $response['success']) {
                    $return[] = $this->createOrder($this->channel->id, $response);
                } else {
                    $return[] = $response;
                }
            }
        }

        return $return;
	}

    public function getOrders($createdAfter, $until)
    {
        $filter = array(
            'startTime' => $createdAfter,
            'endTime' => $until
        );

        $response = $this->thirdParty->getOrders($filter);
        return (count($response) == count($response, COUNT_RECURSIVE)) ? array($response) : $response;
    }

    /**
     *
     */
    public function createOrder($channel_id, $data)
    {
    	$response = Order::createOrder($channel_id, $data);
    	return $response;
    }

    // when order status = Paid
    public function incrementReservedQuantity($orderId = null)
    {
        if (!is_null($orderId)) {
            $this->setOrder($orderId);
        }

        $this->setOrderItems($this->order->id);

        foreach ($this->orderItems as $item) {
            if ($item->reserved == 0 && strcasecmp($item->ref_type, 'ChannelSKU') == 0) {
                $reserved_quantity = ReservedQuantity::firstOrNew(array('channel_sku_id' => $item->ref_id));
                $old_quantity = (!empty($reserved_quantity->quantity)) ? $reserved_quantity->quantity : 0;
                $reserved_quantity->quantity = $old_quantity + $item->quantity;
                $reserved_quantity->save();
                event(new ReservedQuantityChange($item->ref_id, $old_quantity, $this->order->id, $this->order->getStatusName(), $item->id, $item->status));

                $item->reserved = 1;
                $item->save();
            }
        }
    }

    // when order status = Shipped
    public function decrementReservedQuantity($orderId = null)
    {
        if (!is_null($orderId)) {
            $this->setOrder($orderId);
        }

        $this->setOrderItems($this->order->id);

        foreach ($this->orderItems as $item) {
            if ($item->reserved == 1 && strcasecmp($item->ref_type, 'ChannelSKU') == 0 && $item->status != 'Out of Stock') {
                $reserved_quantity = ReservedQuantity::firstOrNew(array('channel_sku_id' => $item->ref_id));
                $old_quantity = (!empty($reserved_quantity->quantity)) ? $reserved_quantity->quantity : 0;
                $reserved_quantity->quantity = $old_quantity - $item->quantity;
                $reserved_quantity->save();
                event(new ReservedQuantityChange($item->ref_id, $old_quantity, $this->order->id, $this->order->getStatusName(), $item->id, $item->status));

                $item->reserved = 0;
                $item->save();
            }
        }
    }

    // when order item is cancelled, adjust reserved quantity accordingly
    public function processReturnforReservedQuantity($orderId, $orderItemId, $returnedQty=1)
    {
        $this->setOrder($orderId);

        $this->setOrderItem($this->order->id, $orderItemId);

        if ($this->orderItem->reserved == 1 && strcasecmp($this->orderItem->ref_type, 'ChannelSKU') == 0) {
            $reservedQty = ReservedQuantity::firstOrNew(array('channel_sku_id' => $this->orderItem->ref_id));
            $old_quantity = (!empty($reservedQty->quantity)) ? $reservedQty->quantity : 0;
            $reservedQty->quantity = $old_quantity - $returnedQty;
            $reservedQty->save();
            event(new ReservedQuantityChange($this->orderItem->ref_id, $old_quantity, $this->order->id, $this->order->getStatusName(), $this->orderItem->id, $this->orderItem->status));

            $this->orderItem->reserved = 0;
            $this->orderItem->save();
        }
    }

    /* receive update from third party */
    public function main_receiver($channelType, $module, $event=NULL) {
        Log::info("***Receive: $channelType >> $module >> $event ...");

        $api = new stdClass();
        $output = new stdClass();
        $func = $module;
        if($event) $func .= '_'.$event;

        $data = Input::all();

        switch($channelType){
            case "shopify" :
                $shopifyController = ChannelType::where('name', 'Shopify')->firstOrFail()->controller;
                //Shopify POS use the same controller
                $api = new $shopifyController();
                $output = $api->receiver();

                $data['channel'] = json_decode(json_encode($output['channel']), true);

                //check for webhook test , prevent it from being insert into the database
                if (Input::get('test') =='true' && Input::get('gateway') !='bogus'){
                    Log::info('ThirdPartyController.Receiver Capture Webhook Test Data for '.$channelType.' Contents: '.print_r(Request::header(),true).print_r($output,true).print_r(Input::all(),true));
                    $response['success'] = true;
                    return Response::JSON($response, MpUtils::getStatusCode('OK_STATUS'));
                }
                break;
            case "lelong" :
                $email_data = array(
                    'subject'=>'New Lelong Order',
                    'data'=>$data
                );
                $this->ErrorAlert($email_data);
                $lelongController = ChannelType::where('name', 'Lelong')->firstOrFail()->controller;

                $api = new $lelongController();
                $output = $api->receiver();
                break;
            default :
                //do nothing
                Log::info("ThirdPartyController.receiver > Unsupported channel type: ". $channelType);
                break;
        }

        try{
            if($output["verified"] ==true){
                // return Response::JSON(array('succees'=>true), MpUtils::getStatusCode('OK_STATUS'));

                // call function module_event e.g order_create
                $response = $api->$func($data);

                if (!is_array($response)) {
                    $response = json_decode($response, true);
                }

                if($response['success']) {
        		    $return = array('success'=>true);
        		    if(is_array($response)) {
                        $return += $response;
        		    }
                    return Response::JSON($return, MpUtils::getStatusCode('OK_STATUS'));
                }
                else {
                    Log::info('ThirdPartyController.receiver > Failed :'.print_r($response, true));
                    return Response::JSON($response);
                }
            }else{
                Log::info('ThirdPartyController.receiver > Verification Failed :'.print_r($output,true));
                //Forbidden
                return Response::JSON(array('success'=>false), MpUtils::getStatusCode('VALIDATION_ERROR'));
            }
        }catch(Exception $ex){
            // function does not exist
            $input = Input::all();
            Log::info("ThirdPartyController.receiver > Fail to call function ".$channelType.".".$module.'_'.$event." >> Error: ".$ex->getMessage());
            Log::info('Error for Input ***'.print_r($input,true));
            return Response::JSON(array('success'=>false), MpUtils::getStatusCode('SERVER_ERROR'));
        }
    }

    public function getSingleOrder($order_code)
    {

        $output = $this->thirdParty->getSingleOrder($order_code);
        return $output;
               /* $response['success'] = true;
                return Response::JSON($response, MpUtils::getStatusCode('OK_STATUS'));
                break;
            default :
                //do nothing
                Log::info("ThirdPartyController.getSingleOrder > Unsupported channel type: ". $channel_type);
                break;
        }*/

    }

    public function updateOrderDetails($order_id, $data, $user_id)
    {
        $orderRepo = new OrderRepo(new Order, new Mailer);
        $order = $orderRepo->findOrFail($order_id);
        $currentStatus = $order->status;
        $currentStatusName = $order->getStatusName();
        $success = $orderRepo->update($data, $order_id);
        $updatedOrder = $orderRepo->findOrFail($order_id);

        if($updatedOrder->status != $currentStatus){
            $statusLog = new OrderStatusLog(['user_id' => $user_id, 'from_status' => $currentStatusName, 'to_status' => $updatedOrder->getStatusName(), 'created_at' => Carbon::now()]);
            $log = $updatedOrder->statusLog()->save($statusLog);

            // If to_status is completed and shipped_date is empty, get created_at date of completed status log and save it into order's shipped date.
	        if($updatedOrder->getStatusName() == 'Completed' && is_null($updatedOrder->shipped_date)){
	            $updatedOrder->shipped_date = $log->created_at;
	            $updatedOrder->save();
	        }

            // Register into order history
            $eventInfo = array(
                'fromStatus' => $currentStatus,
                'toStatus' => $updatedOrder->status,
            );
            Event::fire(new OrderUpdated($order_id, 'Status Updated', 'order_status_log', $log->id, $eventInfo, $user_id));
            Activity::log('Order status was updated from '.$currentStatus.' to '.$updatedOrder->status.' for order #'.$order_id, $user_id);
        }

        if($updatedOrder->paid_status != $order->paid_status)
        {
            Activity::log('Order updated from Unpaid to Paid for order #'.$order_id, $user_id);
            Event::fire(new OrderUpdated($order_id, 'Order Paid', 'orders', $order_id, trans('order-history.description_order_paid'), $user_id));
        }

        if ($updatedOrder->cancelled_status != $order->cancelled_status && $order->cancelled_status == 1) {
        	Activity::log('Order cancelled for order #'.$order_id, $user_id);
			Event::fire(new OrderUpdated($order->id, 'Order Cancelled', 'orders', $order_id, array(), $user_id));
    	}

        return Response::JSON(array('success' => $success, 'order' => $updatedOrder));
    }

    public function readyToShip($userId)
    {
    	if ($this->order->cancelled_status) {
    		return ['success' => false, 'message' => 'Order has already been cancelled.'];
    	}

        Event::fire(new OrderUpdated($this->order->id, 'Consignment Number Sent/Requested', 'orders', $this->order->id, array(), $userId));

        $data = array();
        $data['tracking_no'] = Input::get('tracking_no');

        $response = $this->thirdParty->readyToShip($data);

        $tracking_no = '';
        if(!empty($data['tracking_no']))
        {
            $tracking_no = $data['tracking_no'];
        }
        elseif(!empty($response['tracking_no']))
        {
            $tracking_no = $response['tracking_no'];
        }

        if($response['success'] && !empty($tracking_no))
        {
            $this->order->consignment_no = $tracking_no;
            $this->order->shipping_notification_date = date('Y-m-d H:i:s');
            $this->order->save();

            Event::fire(new OrderUpdated($this->order->id, 'Consignment Number Updated', 'orders', $this->order->id, array('consignmentNo' => $tracking_no), $userId));
            Activity::log('Consignment number updated for order #' . $this->order->id, $userId);

            $response['notification_date'] = $this->order->shipping_notification_date;
        }else
        {
            Event::fire(new OrderUpdated($this->order->id, 'Consignment Number Update Failed', 'orders', $this->order->id, array('error' => ' Error: ' . (!empty($response['message']) ? $response['message'] : 'No error message found.') ), $userId));
        }
        return $response;
    }

    public function pushOrder()
    {
        $this->sync->sent_time = date('Y-m-d H:i:s');
        $this->updateSyncStatus('PROCESSING');
        $data = Order::find($this->sync->ref_table_id);
        $this->thirdParty->pushOrder($data->toArray());
    }

    public function processRefund() {
        $this->sync->sent_time = date('Y-m-d H:i:s');
        $this->updateSyncStatus('PROCESSING');

        $extraInfo = json_decode($this->channel->channel_detail->extra_info, true);

        $orderItem = OrderItem::findOrFail($this->sync->ref_table_id);
        $order = Order::findOrFail($orderItem->order_id);

        $data['item_ref'] = $orderItem->tp_item_id;
        $data['quantity'] = 1;
        $data['order_ref'] = $order->tp_order_id;
        $data['refund_applicable'] = (!empty($extraInfo['refund_applicable']) && $extraInfo['refund_applicable'] == 1) ? true : false;
        $data['restock'] = json_decode($this->sync->extra_info, true)['restock'];

        $log = ReturnLog::where('order_id', '=', $order->id)
                            ->where('order_item_id', '=', $orderItem->id)
                            ->first();

        if(empty($log)){
            $this->sync->remarks = 'Return request has never been initiated. Please contact system administrator.';
            $this->updateSyncStatus('FAILED');
            return;
        }

        $data['log_id'] = $log->id;
        $data['remark'] = $log->remark;

        $response = json_decode($this->thirdParty->createItemRefund($data)->content(), true);

        if (!$response['success']) {
            $this->sync->remarks = json_encode($response['error']['message']);
            $this->updateSyncStatus('FAILED');
        }
        else {
            $this->sync->remarks = $response['response'];
            $this->updateSyncStatus('SUCCESS');
        }
    }

    private function updateSyncStatus($status) {
        $this->sync->status = strtoupper($status);
        $this->sync->save();
    }
    public function getShippingProvider(Request $request){
        $response = $this->thirdParty->getShippingProviderDetail($request);
        return $response;

    }
}
