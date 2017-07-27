<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrateOrdersToOrderHistory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $orders = DB::table('orders')->orderBy('id', 'asc')->chunk(1000, function ($orders) {
            foreach ($orders as $order) {
                DB::table('order_history')->insert([
                    'order_id'      =>  $order->id,
                    'description'   =>  trans('order-history.description_order_created'),
                    'event'         =>  'Order Created',
                    'ref_type'      =>  'orders',
                    'ref_id'        =>  $order->id,
                    'user_id'       =>  0,
                    'created_at'    =>  $order->created_at,
                ]);
                DB::table('order_history')->insert([
                    'order_id'      =>  $order->id,
                    'description'   =>  trans('order-history.description_order_paid'),
                    'event'         =>  'Order Paid',
                    'ref_type'      =>  'orders',
                    'ref_id'        =>  $order->id,
                    'user_id'       =>  0,
                    'created_at'    =>  $order->paid_date,
                ]);
                if (!is_null($order->shipping_notification_date) && !is_null($order->consignment_no)) {
                    DB::table('order_history')->insert([
                        'order_id'      =>  $order->id,
                        'description'   =>  trans('order-history.description_updated_consignment', ['consignmentNo' => $order->consignment_no]),
                        'event'         =>  'Consignment Number Updated',
                        'ref_type'      =>  'orders',
                        'ref_id'        =>  $order->id,
                        'user_id'       =>  0,
                        'created_at'    =>  $order->shipping_notification_date,
                    ]);
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
        DB::table('order_history')->where('event', '=', 'Consignment Number Updated')->delete();
        DB::table('order_history')->where('event', '=', 'Order Created')->delete();
        DB::table('order_history')->where('event', '=', 'Order Paid')->delete();
    }
}
