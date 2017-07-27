<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Order;

class MigrateOrderStatusLogToOrderHistory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_history', function ($table) {
            //$table->dropForeign('order_history_order_id_foreign');
        });

        $order = new Order();
        $statusCode = $order->getStatusCode();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $statusLogs = DB::table('order_status_log')->orderBy('order_id', 'asc')->orderBy('created_at', 'asc')->chunk(1000, function ($statusLogs) {
            foreach ($statusLogs as $statusLog) {
                $fromStatus = is_numeric($statusLog->from_status) ? array_search($statusLog->from_status, $statusCode) : $statusLog->from_status;
                $toStatus = is_numeric($statusLog->to_status) ? array_search($statusLog->to_status, $statusCode) : $statusLog->to_status;
                DB::table('order_history')->insert([
                    'order_id'      =>  $statusLog->order_id,
                    'description'   =>  trans('order-history.description_status_update', ['fromStatus' =>  ucfirst(strtolower($fromStatus)), 'toStatus' =>  ucfirst(strtolower($toStatus))]),
                    'event'         =>  'Status Updated',
                    'ref_type'      =>  'order_status_log',
                    'ref_id'        =>  $statusLog->id,
                    'user_id'       =>  $statusLog->user_id,
                    'created_at'    =>  $statusLog->created_at,
                ]);

                if (strtolower($toStatus) == 'cancelled') {
                    DB::table('order_history')->insert([
                        'order_id'      =>  $statusLog->order_id,
                        'description'   =>  trans('order-history.description_order_cancelled'),
                        'event'         =>  'Order Cancelled',
                        'ref_type'      =>  'order_status_log',
                        'ref_id'        =>  $statusLog->id,
                        'user_id'       =>  $statusLog->user_id,
                        'created_at'    =>  $statusLog->created_at,
                    ]);
                }

                if (strtolower($toStatus) == 'completed') {
                    DB::table('order_history')->insert([
                        'order_id'      =>  $statusLog->order_id,
                        'description'   =>  trans('order-history.description_order_completed'),
                        'event'         =>  'Order Completed',
                        'ref_type'      =>  'order_status_log',
                        'ref_id'        =>  $statusLog->id,
                        'user_id'       =>  $statusLog->user_id,
                        'created_at'    =>  $statusLog->created_at,
                    ]);
                }
            }
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('order_history')->where('event', '=', 'Status Updated')->delete();
        DB::table('order_history')->where('event', '=', 'Order Cancelled')->delete();
        DB::table('order_history')->where('event', '=', 'Order Completed')->delete();
        Schema::table('order_history', function ($table) {
            //$table->foreign('order_id')->references('id')->on('orders');
        });
    }
}
