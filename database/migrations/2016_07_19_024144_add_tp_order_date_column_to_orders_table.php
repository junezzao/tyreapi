<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTpOrderDateColumnToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('tp_order_date')->after('tp_order_code');
        });

        DB::table('orders')->chunk(1000, function ($orders) {
            foreach ($orders as $order) {
                $tp_extra = unserialize($order->tp_extra);
                $tp_order_date = isset($tp_extra['created_at']) ? $tp_extra['created_at'] : $order->created_at;

                DB::table('orders')->where('id', $order->id)->update([
                    'tp_extra' => json_encode($tp_extra),
                    'tp_order_date' => $tp_order_date
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
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tp_order_date');
        });

        DB::table('orders')->chunk(1000, function ($orders) {
            foreach ($orders as $order) {
                $tp_extra = json_decode($order->tp_extra, true);

                DB::table('orders')->where('id', $order->id)->update([
                    'tp_extra' => serialize($tp_extra)
                ]);
            }
        });
    }
}
