<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Order;

class RectifyOrdersTableData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*
         * Current Statuses
         * 0 = Failed, 1 = Pending, 2 = New, 3 = Picking, 4 = Packing, 5 = Ready To Ship, 6 = Shipped, 7 = Completed, 8 = Cancelled
         *
         * New Statuses
         * 11 = Failed, 12 = Pending,
         * 21 = New, 22 = Picking, 23 = Packing, 24 = Ready To Ship,
         * 31 = Shipped, 32 = Completed
         */

        DB::table('orders')->chunk(1000, function ($orders) {
            foreach ($orders as $order) {
                $sale = DB::table('sales')->where('sale_id', $order->id)->first();
                if(empty($sale)) continue;
                $sale_status = strtolower($sale->sale_status);

                // fields to be corrected
                $status = null;
                $paid_status = null;
                $cancelled_status = null;
                $paid_date = null;
                $consignment_no = null;
                $shipping_notification_date = null;

                $status = $this->mapToOrderStatus($sale_status);
                if($sale_status == 'paid' || $sale_status == 'completed') 
                {
                    $paid_status = true;

                    $extra = unserialize($sale->extra);
                    $paid_date = isset($extra['created_at']) ? $extra['created_at'] : $sale->created_at;
                } 
                elseif(!in_array($sale_status, array('pending', 'failed')))
                {
                    $paid_status = true;
                    $paid_status_log = DB::table('sales')->select('order_status_log.*')->leftJoin('order_status_log', 'sales.sale_id', '=', 'order_status_log.order_id')
                        ->where('sales.sale_id', $sale->sale_id)
                        ->where('order_status_log.to_status', 'like', 'paid')->first();
                    if(!empty($paid_status_log)) {
                        $paid_date = $paid_status_log->created_at;
                    }
                    else {
                        $paid_status_log2 = DB::table('sales')->select('sales.*')->leftJoin('order_status_log', 'sales.sale_id', '=', 'order_status_log.order_id')
                            ->where('sales.sale_id', $sale->sale_id)
                            ->where('order_status_log.from_status', 'like', 'paid')->first();
                        if(!empty($paid_status_log2)) {
                            $paid_date = $paid_status_log2->created_at;
                        } else {
                            $paid_status_log3 = DB::table('sales')->select('order_status_log.*')->leftJoin('order_status_log', 'sales.sale_id', '=', 'order_status_log.order_id')
                                ->where('sales.sale_id', $sale->sale_id)
                                ->where('order_status_log.to_status', 'like', 'completed')->first();
                            if(!empty($paid_status_log3)) {
                                $paid_date = $paid_status_log3->created_at;
                            } else {
                                $paid_status_log4 = DB::table('sales')->select('order_status_log.*')->leftJoin('order_status_log', 'sales.sale_id', '=', 'order_status_log.order_id')
                                    ->where('sales.sale_id', $sale->sale_id)
                                    ->where('order_status_log.to_status', 'like', 'shipped')->first();
                                if(!empty($paid_status_log4)) {
                                    $paid_date = $paid_status_log4->created_at;
                                } else {
                                    //Log::info('Unable to fetch paid_date for order #'.$order->id.' -> '.$sale->sale_status);
                                }
                            }
                        }
                    }
                } else { // status = pending / failed
                    $paid_status = false;
                }

                if($sale_status == 'cancelled') 
                {
                    $cancelled_status = true;
                    
                    $old_status = DB::table('sales')->leftJoin('order_status_log', 'sales.sale_id', '=', 'order_status_log.order_id')
                        ->where('sales.sale_id', $sale->sale_id)
                        ->where('order_status_log.to_status', 'like', 'cancelled')
                        ->orderBy('order_status_log.id', 'desc')->first()->from_status;
                    $status = $this->mapToOrderStatus($old_status);
                } else {
                    $cancelled_status = false;
                }

                $consignment_no = $sale->consignment_no;
                $shipping_notification_date = $sale->notification_date;

                $data = array();
                if(!is_null($status))
                    $data['status'] = $status;
                if(!is_null($paid_status)) {
                    $data['paid_status'] = $paid_status;
                    if($paid_status == false) {
                        $data['paid_date'] = null;
                    }
                }
                if(!is_null($cancelled_status))
                    $data['cancelled_status'] = $cancelled_status;
                if(!is_null($paid_date))
                    $data['paid_date'] = $paid_date;
                if(!is_null($consignment_no))
                    $data['consignment_no'] = $consignment_no;
                if(!is_null($shipping_notification_date))
                    $data['shipping_notification_date'] = $shipping_notification_date;

                DB::table('orders')->where('id', $order->id)->update($data);
            }
        });
    }

    public function mapToOrderStatus($old_status) 
    {
        switch(strtolower($old_status)) {
            case 'pending' :
            case Order::$pendingStatus:
                $status = Order::$pendingStatus;
                break;
            case 'paid' : 
            case Order::$newStatus:
                $status = Order::$newStatus;
                break;
            case 'picking' :
            case Order::$pickingStatus:
                $status = Order::$pickingStatus;
                break;
            case 'packing' :
            case Order::$packingStatus:
                $status = Order::$packingStatus;
                break;
            case 'shipped' :
            case Order::$shippedStatus:
                $status = Order::$shippedStatus;   
                break;   
            case 'completed' :
            case Order::$completedStatus:
                $status = Order::$completedStatus;
                break;
            case 'cancelled' :
                $status = '';
                break;
            case 'failed' :
            case Order::$failedStatus:
                $status = Order::$failedStatus;
                break;
            default:
                \Log::info('Fatal error under migration 2016_09_07_045710_rectify_orders_table_data');
                \Log::info('Error: Status not found Line 160... '.$old_status);
                die();
                break;
        } 

        return $status;
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
