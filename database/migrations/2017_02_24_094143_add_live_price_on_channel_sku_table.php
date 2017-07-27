<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLivePriceOnChannelSkuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_sku', function ($table) {
            $table->float('channel_sku_live_price',8,2)->after('channel_sku_promo_price')->default(0);
        }); 
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_sku', function ($table) {
            $table->dropColumn('channel_sku_live_price');
        }); 
    }
}
