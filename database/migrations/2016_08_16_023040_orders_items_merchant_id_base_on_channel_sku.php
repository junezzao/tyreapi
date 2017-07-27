<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class OrdersItemsMerchantIdBaseOnChannelSku extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_items', function ($table) {
            $table->integer('merchant_id')->unsigned()->after('status');
        });
        DB::statement("update order_items oi join channel_sku cs
            on cs.channel_sku_id = oi.ref_id 
         set oi.merchant_id = cs.merchant_id where oi.ref_type = 'ChannelSKU';");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_items', function ($table) {
            $table->dropColumn('merchant_id');
        });
    }
}
