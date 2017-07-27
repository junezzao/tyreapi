<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateOrderItemStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('return_log')->chunk(1000, function($returns){
            foreach ($returns as $return) {
                $item = DB::table('order_items')->where('id', $return->order_item_id)->first();
                if($return->completed_at == $return->created_at){
                    if(empty($item->status))
                        DB::table('order_items')->where('id', $return->order_item_id)->update(['status' => 'Cancelled' ]);
                }else{
                    if(empty($item->status))
                        DB::table('order_items')->where('id', $return->order_item_id)->update(['status' => 'Returned' ]);
                }  
            }
        });

        DB::table('orders')->chunk(1000, function($orders){
            foreach ($orders as $order) {
                $items = DB::table('order_items')->where('order_id', $order->id)->get();
                foreach ($items as $item) {
                    if($item->status == null && $item->ref_type == 'ChannelSKU'){
                        // completed orders
                        if($order->status == Order::$completedStatus){
                            if($order->cancelled_status == false || $order->cancelled_status == 0){
                                DB::table('order_items')->where('id', $item->id)->update(['status' => 'Verified' ]);
                                Log::info('Order: '.$order->id.' | item: '.$item->id.', '.$order->status.', Verified');
                            }
                        }
                        //shipped 
                        if($order->status == Order::$shippedStatus){
                            if($order->cancelled_status == false || $order->cancelled_status == 0){
                                DB::table('order_items')->where('id', $item->id)->update(['status' => 'Verified' ]);
                                Log::info('Order: '.$order->id.' | item: '.$item->id.', '.$order->status.', Verified');
                            }
                        }
                        // ready to ship     
                        if($order->status == Order::$readyToShipStatus){
                            if($order->cancelled_status == false || $order->cancelled_status == 0){
                                DB::table('order_items')->where('id', $item->id)->update(['status' => 'Verified' ]);
                                Log::info('Order: '.$order->id.' | item: '.$item->id.', '.$order->status.', Verified');
                            }
                        } 
                        // packing     
                        if($order->status == Order::$packingStatus){
                            if($order->cancelled_status == false || $order->cancelled_status == 0){
                                DB::table('order_items')->where('id', $item->id)->update(['status' => 'Picked' ]);
                                Log::info('Order: '.$order->id.' | item: '.$item->id.', '.$order->status.', Picked');
                            }
                        }

                        // item cancelled but no return_log entry
                        if($item->status == null && ($order->cancelled_status == true || $order->cancelled_status == 1)){
                            DB::table('order_items')->where('id', $item->id)->update(['status' => 'Cancelled' ]);
                            Log::info('Order: '.$order->id.' | item: '.$item->id.', '.$order->status.', Cancelled');
                        }
                    }
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
        // clear all item status
        DB::table('order_items')->chunk(1000, function($items){
            foreach ($items as $item) {
                DB::table('order_items')->where('id', $item->id)->update(['status' => null]);
            }
        });
    }
}
