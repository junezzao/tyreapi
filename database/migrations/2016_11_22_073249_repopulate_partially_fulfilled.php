<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Order;

class RepopulatePartiallyFulfilled extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Order::with('items')->whereDate('created_at', '>', '2016-09-31 16:00:00')->chunk(1000, function($orders) {
            foreach($orders as $order){
                $pfFlag = false;
                foreach($order->items as $item){
                    if($item->status == 'Out of Stock'){
                        $pfFlag = true;
                    }
                }
                if($pfFlag){
                    if($order->partially_fulfilled != 1){
                        $order->partially_fulfilled = 1;
                        $order->save();
                    }
                }else{
                    if($order->partially_fulfilled == 1){
                        $order->partially_fulfilled = 0;
                        $order->save();
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
        
    }
}
