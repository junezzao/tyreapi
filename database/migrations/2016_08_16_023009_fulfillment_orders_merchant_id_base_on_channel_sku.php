<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FulfillmentOrdersMerchantIdBaseOnChannelSku extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fulfillment_orders', function ($table) {
            $table->integer('merchant_id')->unsigned()->after('channel_sku_id');
        });
        DB::statement("update fulfillment_orders fo join channel_sku cs
            on cs.channel_sku_id = fo.channel_sku_id 
         set fo.merchant_id = cs.merchant_id;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fulfillment_orders', function ($table) {
            $table->dropColumn('merchant_id');
        });
    }
}
