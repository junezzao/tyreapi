<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Order;

class UpdateAllStatusLogToText extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('order_status_log')->chunk(1000, function($logs){
            foreach ($logs as $log) {
                if (is_numeric($log->to_status)) {
                    if(array_search($log->to_status, Order::$statusCode) !== false){
                        DB::table('order_status_log')->where('id', $log->id)->update(['to_status' => array_search($log->to_status, Order::$statusCode)]);
                    }
                }
                if (is_numeric($log->from_status)) {
                    if(array_search($log->from_status, Order::$statusCode) !== false){
                        DB::table('order_status_log')->where('id', $log->id)->update(['from_status' =>  array_search($log->from_status, Order::$statusCode)]);   
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
