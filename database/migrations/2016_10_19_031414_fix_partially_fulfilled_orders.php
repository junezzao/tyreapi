<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Order;

class FixPartiallyFulfilledOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        DB::table('orders')->chunk(1000, function ($orders) {
            $status = Order::$shippedStatus; 

            foreach ($orders as $order) {
                // for each partially fulfilled order, change partially fulfilled column back to false for the following conditions:
                // 1. order shipped
                // 2. order cancelled
                // 3. order items cancelled
                if ($order->partially_fulfilled) {

                    // orders that are shipped or cancelled
                    if ($order->status>=$status || $order->cancelled_status) {
                        DB::table('orders')->where('id', $order->id)->update(['partially_fulfilled' => 0 ]);
                    }

                    // 
                    else {
                        $cancelledCount = DB::table('order_items')->where('order_id', '=', $order->id)->where('status', '=', 'Cancelled')->count();
                        $oosCount = DB::table('order_items')->where('order_id', '=', $order->id)->where('status', '=', 'Out of Stock')->count();
                        $numItems = DB::table('order_items')->where('order_id', '=', $order->id)->count();
                        $verifiedItems = DB::table('order_items')->where('order_id', '=', $order->id)->where('status', '=', 'Verified')->count();

                        // if no out of stock items but some items are cancelled
                        if ($cancelledCount > 0 && $oosCount == 0) 
                            DB::table('orders')->where('id', $order->id)->update(['partially_fulfilled' => 0 ]);
                        
                        // if all items are verified
                        else if ($numItems == $verifiedItems)
                            DB::table('orders')->where('id', $order->id)->update(['partially_fulfilled' => 0 ]);
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
        //
    }
}
