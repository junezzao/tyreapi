<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AllowDeliveryOrdersOriginatingChannelNull extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropIndex('delivery_orders_originating_channel_id_index');
            $table->integer('originating_channel_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->index('originating_channel_id');
            $table->integer('originating_channel_id')->nullable(false)->change();
        });
    }
}
