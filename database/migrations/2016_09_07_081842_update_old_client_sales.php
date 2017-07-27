<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Sales;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ReturnLog;
use App\Models\Admin\OrderStatusLog;
use App\Models\Admin\ThirdPartySync;
use App\Events\OrderUpdated;
use Carbon\Carbon;

class UpdateOldClientSales extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $cancelledOrders = array();

        // get all old gvc, badlab client sales
        Sales::whereIn('client_id', [2, 6])->whereIn('sale_status', ['pending', 'paid', 'packing'])->chunk(1000, function($sales){
            foreach ($sales as $sale) {
                if(strtolower($sale->sale_status) == 'pending'){
                    // update pending => failed
                    $sale->sale_status = 'Failed';
                }

                // update paid to cancelled, write to status log
                if(in_array(strtolower($sale->sale_status), ['paid', 'packing'])){
                    $sale->sale_status = 'Cancelled';
                }

                $sale->save();
            }
        });

        // get all old gvc, badlab client orders
        Order::whereIn('merchant_id_bk', [2, 6])->whereIn('status', [Order::$pendingStatus, Order::$newStatus, Order::$packingStatus])->chunk(1000, function($orders){
            foreach ($orders as $order) {
                $oldStatus = '';
                $statusLog = new OrderStatusLog();
                if($order->status == Order::$pendingStatus){
                    // update pending => failed
                    $oldStatus = $order->status;
                    $order->status = Order::$failedStatus;
                    $eventInfo = array(
                        'fromStatus' => $oldStatus,
                        'toStatus' =>  $order->status,
                    );
                    $statusLog->to_status = array_search($order->status, Order::$statusCode);

                    $order->save();
                
                    $statusLog->from_status = array_search($oldStatus, Order::$statusCode);
                    $statusLog->user_id = 0;
                    $log = $order->statusLog()->save($statusLog);

                    Event::fire(new OrderUpdated($order->id, 'Status Updated', 'order_status_log', $log->id, $eventInfo, 0));
                }

                // update paid to cancelled, write to status log
                if(in_array($order->status, [Order::$newStatus, Order::$packingStatus])){
                    $oldStatus = $order->status;
                    $order->cancelled_status = true;
                    $eventInfo = array(
                        'fromStatus' => $order->status,
                        'toStatus' => 'Cancelled',
                    );
                    $statusLog->to_status = 'Cancelled';
                    $cancelledOrders[] = $order;

                    $order->save();
                    
                    $statusLog->from_status = array_search($oldStatus, Order::$statusCode);
                    $statusLog->user_id = 0;
                    $log = $order->statusLog()->save($statusLog);

                    Event::fire(new OrderUpdated($order->id, 'Order Cancelled', 'order_status_log', $log->id, $eventInfo, 0));
                }
            }
        });

        // update order item status
        if(!empty($cancelledOrders)){
            foreach ($cancelledOrders as $order) {
                $items = OrderItem::where('order_id', $order->id)->get();
                foreach ($items as $item) {
                    $item->status = 'Cancelled';
                    $item->save();

                    //  create return log entries for cancelled orders
                    $return = new ReturnLog();
                    $return->member_id = $order->member_id;
                    $return->user_id = 0;
                    $return->order_id = $order->id;
                    $return->order_item_id = $item->id;
                    $return->quantity = $item->quantity;
                    $return->refund_type = null;
                    $return->amount = ($item->quantity > 1 ? $item->quantity * $item->sold : $item->sold);
                    $return->remark = 'Update old client sales';
                    $return->ref_id = $item->ref->ref_id;
                    $return->status = 'Restocked';
                    $return->completed_at = Carbon::now();
                    $return->updated_at = Carbon::now();
                    $return->updated_at = Carbon::now();
                    $return->save();
                }
            }
        }
    
        // set all existing active sync to cancelled
        ThirdPartySync::whereIn('merchant_id', [2, 6])->chunk(1000, function($syncs){
            foreach ($syncs as $sync) {
                if($sync->status == 'SCHEDULED' || $sync->status == 'NEW'){
                    $sync->status = 'CANCELLED';
                    $sync->save();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
