<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateOrderStatusLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('order_status_log')->chunk(1000, function($orderStatusLogs){
            foreach($orderStatusLogs as $orderStatusLog){
                // $orderStatusLog->from_status = ucfirst($orderStatusLog->from_status);
                // $orderStatusLog->to_status = ucfirst($orderStatusLog->to_status);
                $updateOrderStatusLog = DB::table('order_status_log')
                                ->where('id', '=', $orderStatusLog->id)
                                ->update([
                                    'from_status' => ucfirst($orderStatusLog->from_status),
                                    'to_status' => ucfirst($orderStatusLog->to_status)
                                ]);
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
