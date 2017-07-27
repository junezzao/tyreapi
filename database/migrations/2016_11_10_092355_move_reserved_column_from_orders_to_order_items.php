<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Order;

class MoveReservedColumnFromOrdersToOrderItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function ($table) {
            $table->dropColumn(['reserved']);
        });

        Schema::table('order_items', function ($table) {
            $table->boolean('reserved')->default(0)->after('status');
        });

        /* since reserved in order_items default to 0, we only update it to 1 when 
            order is paid but not yet shipped, 
            and item is not cancelled
        */
        Order::where('paid_status', 1)->chunk(1000, function($orders) use (&$updateData){
            foreach($orders as $order) {
                foreach ($order->items as $item) {
                    if($item->ref_type == 'ChannelSKU') {
                        if(is_null($order->shipped_date) && is_null($order->cancelled_date) && $item->status != 'Cancelled') {
                            $item->reserved = 1;
                            $item->save();
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
    }
}
