<?php

namespace App\Listeners;

use App\Events\OrderUpdated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Repositories\DashboardStatisticsRepository;
use App\Models\Admin\OrderHistory;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ChannelSKU;
use Vinkla\Pusher\Facades\Pusher;
use Event;


class RecordIntoOrderHistory
{
    protected $dashboardRepo;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        $this->dashboardRepo = new DashboardStatisticsRepository;
    }

    /**
     * Handle the event.
     *
     * @param  OrderUpdated  $event
     * @return void
     */
    public function handle(OrderUpdated $event)
    {
        $eventInfo = $event->eventInfo;
        $completedOrder = false;

        switch ($event->eventType) {
            case 'Order Created':
                $description = trans('order-history.description_order_created');
                Pusher::trigger('order-statistics', 'orders-count-update', $this->dashboardRepo->countOrdersAndOrderItems());
                $order = Order::where('id', '=', $event->orderId)->with('items', 'channel', 'items.merchant')->first();
                $data = array();
                foreach ($order['items'] as $item) {
                    $data[$item['merchant']['slug']]['quantity'] = 0;
                    $data[$item['merchant']['slug']]['price'] = 0;
                    $data[$item['merchant']['slug']]['order'] = $order['id'];
                    $data[$item['merchant']['slug']]['merchant'] = $item['merchant']['name'];
                    $data[$item['merchant']['slug']]['channel'] = $order['channel']['name'];
                    $data[$item['merchant']['slug']]['currency'] = $order['currency'];
                }
                foreach ($order['items'] as $item) {
                    $data[$item['merchant']['slug']]['quantity'] += $item['quantity'];
                    $data[$item['merchant']['slug']]['price'] += $item['sold_price'];
                    
                }
                foreach ($data as $key => $value) {
                    $interest = $key;
                    $notification = array(
                        'apns' => array(
                            'aps' => array(
                                'alert' => array(
                                    'title' => $value['merchant'],
                                    'subtitle' => 'New Order #'.$value['order'],
                                    'body' => $value['channel'].' : '.$value['quantity'].' item(s) sold for '.$value['currency'].' '.number_format($value['price'], 2),
                                ),
                            ),
                        ),
                    );
                    Pusher::connection('mobileAlternative')->notify($interest, $notification);
                }
                break;

            case 'Order Paid':
                $description = trans('order-history.description_order_paid');
                break;

            case 'Status Updated':
                $order = new Order();
                //\Log::info($eventInfo);
                $statusCode = $order->getStatusCode();
                $fromStatus = array_search($eventInfo['fromStatus'], $statusCode);
                $toStatus = array_search($eventInfo['toStatus'], $statusCode);
                $description = trans('order-history.description_status_update', ['fromStatus' =>  ucfirst(strtolower($fromStatus)), 'toStatus' =>  ucfirst(strtolower($toStatus))]);

                if($eventInfo['toStatus'] == Order::$completedStatus){
                    $completedOrder = true;
                }
                if ($eventInfo['toStatus'] == Order::$shippedStatus) {
                    $order = Order::where('id', '=', $event->orderId)->with('items', 'channel', 'items.merchant')->first();
                    $data = array();
                    foreach ($order['items'] as $item) {
                        $data[$item['merchant']['slug']]['quantity'] = 0;
                        $data[$item['merchant']['slug']]['price'] = 0;
                        $data[$item['merchant']['slug']]['order'] = $order['id'];
                        $data[$item['merchant']['slug']]['merchant'] = $item['merchant']['name'];
                        $data[$item['merchant']['slug']]['channel'] = $order['channel']['name'];
                    }
                    foreach ($order['items'] as $item) {
                        $data[$item['merchant']['slug']]['quantity'] += $item['quantity'];
                        $data[$item['merchant']['slug']]['price'] += $item['sold_price'];
                        
                    }
                    foreach ($data as $key => $value) {
                        $interest = $key;
                        $notification = array(
                            'apns' => array(
                                'aps' => array(
                                    'alert' => array(
                                        'title' => $value['merchant'],
                                        'subtitle' => 'Order #'.$value['order'].'  Shipped ',
                                        'body' => $value['channel'].' : '.$value['quantity']. ' item(s) has been shipped to customer',
                                    ),
                                ),
                            ),
                        );
                        Pusher::connection('mobileAlternative')->notify($interest, $notification);
                    }
                }
                break;

            case 'Order Completed':
                $description = trans('order-history.description_order_completed');
                break;

            case 'Item Returned':
                $hwSku = $this->getHwSku($eventInfo);
                $description = trans('order-history.description_order_return_item', ['hwSku' => $hwSku]);
                Pusher::trigger('order-statistics', 'returned-count-update', $this->dashboardRepo->countReturnedItems());
                break;

            case 'Item Status Updated':
                $eventInfo['orderItemId'] = $event->refId;
                $hwSku = $this->getHwSku($eventInfo);

                if (empty($eventInfo['fromStatus']) || is_null($eventInfo['fromStatus'])) {
                    $eventInfo['fromStatus'] = 'None';
                }
                
                $description = trans('order-history.description_order_item_status_update', ['hwSku' => $hwSku, 'fromStatus' =>  $eventInfo['fromStatus'], 'toStatus' =>  $eventInfo['toStatus']]);

                if ($eventInfo['toStatus']=='Cancelled')
                    Pusher::trigger('order-statistics', 'cancelled-count-update', $this->dashboardRepo->countCancelledItems());

                break;

            case 'Consignment Number Updated':
                $description = trans('order-history.description_updated_consignment', ['consignmentNo' => $eventInfo['consignmentNo']]);
                break;

            case 'Order Cancelled':
                $description = trans('order-history.description_order_cancelled');
                break;

            case 'Returned Item: In Transit':
                $hwSku = $this->getHwSku($eventInfo);

                $description = trans('order-history.description_order_item_in_transit', ['hwSku' => $hwSku]);
                Pusher::trigger('order-statistics', 'returned-count-update', $this->dashboardRepo->countReturnedItems());
                
                break;

            case 'Returned Item: Restocked':
                $hwSku = $this->getHwSku($eventInfo);

                $description = trans('order-history.description_order_item_restock', ['hwSku' => $hwSku]);
                break;

            case 'Returned Item: Rejected':
                $hwSku = $this->getHwSku($eventInfo);

                $description = trans('order-history.description_order_item_reject', ['hwSku' => $hwSku]);
                break;

            case 'Consignment Number Update Failed':
                $description = trans('order-history.description_update_consignment_attempt_failed') . $eventInfo['error'];
                break;

            case 'Consignment Number Sent/Requested':
                $description = trans('order-history.description_update_consignment_attempt');
                break;
        }
        $orderHistory['order_id'] = $event->orderId;
        $orderHistory['description'] = $description;
        $orderHistory['ref_type'] = $event->refType;
        $orderHistory['ref_id'] = $event->refId;
        $orderHistory['event'] = $event->eventType;
        $orderHistory['user_id'] = $event->userId;
        OrderHistory::create($orderHistory);

        // Fire completed order event
        if($completedOrder){
            Event::fire(new OrderUpdated($event->orderId, 'Order Completed', 'orders', $event->orderId, array(), $event->userId));
        }
    }

    public function getHwSkuFromOdrItemRefId($odrItemRefId){
        $output = ChannelSKU::where('channel_sku_id', '=', $odrItemRefId)->with('sku')->first();
        return $output->sku->hubwire_sku;
    }

    public function getHwSkuFromOdrItemId($odrItemId){
        $orderItem = OrderItem::findOrFail($odrItemId);
        return $this->getHwSkuFromOdrItemRefId($orderItem->ref_id);
    }

    public function getHwSku($eventInfo){
        if(isset($eventInfo['orderItemRefId']))
            return $this->getHwSkuFromOdrItemRefId($eventInfo['orderItemRefId']);
        elseif(isset($eventInfo['orderItemId']))
            return $this->getHwSkuFromOdrItemId($eventInfo['orderItemId']);
    }
}
