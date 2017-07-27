<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ReservedQuantity;

class PatchReservedQuantitiesData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {   
        $affectedOrders = [];

        // update for all shipped and completed
        Order::where('reserved', 1)->chunk(100, function($orders) use(&$affectedOrders) {
            foreach ($orders as $order) {
                if($order->status == Order::$shippedStatus || $order->status == Order::$completedStatus || $order->cancelled_status == 1){
                    \Log::info($order->id);
                    $affectedOrders[] = $order->id;
                }else{
                    \Log::info('Skipped order: '.$order->id);
                }
            }
        });
        
        foreach ($affectedOrders as $id) {
            $order = Order::find($id);
            $items = $order->items;
            foreach ($items as $item) {
                if($item->ref_type == 'ChannelSKU'){
                    $channel_sku_id = $item->ref_id;
                    $quantity = $item->original_quantity;
                    $reserve = ReservedQuantity::where('channel_sku_id', $channel_sku_id)->first();
                    if($reserve){
                        $reserved_quantity = $reserve->quantity;
                        \Log::info('['.$order->id.'] Old quantity: '.$reserve->channel_sku_id .' = '.$reserved_quantity);
                        $reserve->quantity = $reserved_quantity - $quantity;
                        \Log::info('['.$order->id.'] New quantity: '.$reserve->channel_sku_id .' = '.$reserve->quantity);
                        $reserve->save();
                    }
                }
            }
            $order->reserved = 0;
            $order->save();
        }
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
