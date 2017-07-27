<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateFailedOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('failed_orders')->chunk(1000, function($failedOrders){
            foreach($failedOrders as $failedOrder){
                $order = DB::table('orders')->where('tp_order_id', '=', $failedOrder->tp_order_id)->get();
                if(count($order)>0){
                    // \Log::info(print_r($order, true));
                    $updateFailedOrder = DB::table('failed_orders')->where('failed_order_id', '=', $failedOrder->failed_order_id)->update(['order_id' => $order[0]->id,'status' => 3, 'tp_order_date' => $order[0]->tp_order_date]);
                    continue;
                }

                if(!is_null($failedOrder->deleted_at)){
                    $updateFailedOrder = DB::table('failed_orders')->where('failed_order_id', '=', $failedOrder->failed_order_id)->update(['status' => 4]);
                }else{
                    $updateFailedOrder = DB::table('failed_orders')->where('failed_order_id', '=', $failedOrder->failed_order_id)->update(['status' => 1]);
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
        DB::table('failed_orders')->chunk(1000, function($failedOrders){
            foreach($failedOrders as $failedOrder){
                $updateFailedOrder = DB::table('failed_orders')->where('failed_order_id', '=', $failedOrder->failed_order_id)->update(['status' => 0, 'tp_order_date' => NULL]);
            }
        });
    }
}
