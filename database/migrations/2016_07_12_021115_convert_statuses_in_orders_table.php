<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ConvertStatusesInOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*
         * Current Statuses
         * 0 = Failed, 1 = Pending, 2 = New, 3 = Picking, 4 = Packing, 5 = Ready To Ship, 6 = Shipped, 7 = Completed
         *
         * New Statuses
         * 11 = Failed, 12 = Pending,
         * 21 = New, 22 = Picking, 23 = Packing, 24 = Ready To Ship,
         * 31 = Shipped, 32 = Completed
         */
        
        DB::table('orders')->where('status', '=', 0)->update(['status' => 11]);
        DB::table('orders')->where('status', '=', 1)->update(['status' => 12]);
        DB::table('orders')->where('status', '=', 2)->update(['status' => 21]);
        DB::table('orders')->where('status', '=', 3)->update(['status' => 22]);
        DB::table('orders')->where('status', '=', 4)->update(['status' => 23]);
        DB::table('orders')->where('status', '=', 5)->update(['status' => 24]);
        DB::table('orders')->where('status', '=', 6)->update(['status' => 31]);
        DB::table('orders')->where('status', '=', 7)->update(['status' => 32]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('orders')->where('status', '=', 11)->update(['status' => 0]);
        DB::table('orders')->where('status', '=', 12)->update(['status' => 1]);
        DB::table('orders')->where('status', '=', 21)->update(['status' => 2]);
        DB::table('orders')->where('status', '=', 22)->update(['status' => 3]);
        DB::table('orders')->where('status', '=', 23)->update(['status' => 4]);
        DB::table('orders')->where('status', '=', 24)->update(['status' => 5]);
        DB::table('orders')->where('status', '=', 31)->update(['status' => 6]);
        DB::table('orders')->where('status', '=', 32)->update(['status' => 7]);
    }
}
