<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InsertShippedDateIntoOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        $updateOrders = array();

        DB::table('orders')->where('status', '=', 32)->whereNull('shipped_date')->chunk(1000, function($orders) use (&$updateOrders){
            foreach($orders as $order){
                $completedLog = DB::table('order_status_log')->where('order_id', '=', $order->id)->where('to_status', 
                    '=', 'Completed')->orderBy('created_at', 'asc')->first();
                if(!$completedLog){
                    $logDate = $order->paid_date;
                }else{
                    $logDate = $completedLog->created_at;
                }
                $updateOrders[] = array(
                    'orderId'   =>  $order->id,
                    'logDate'   =>  $logDate,
                );
            }
        });

        foreach($updateOrders as $orderUpdate){
            DB::table('orders')->where('id', '=', $orderUpdate['orderId'])->update(['shipped_date' => $orderUpdate['logDate']]);
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
