<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateChannelSkuAddSalesPeriodInfo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_sku', function (Blueprint $table) {
            $table->date('promo_start_date')->after('channel_sku_promo_price')->nullable();
            $table->date('promo_end_date')->after('promo_start_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_sku', function (Blueprint $table) {
            $table->dropColumn(['promo_start_date','promo_end_date']);
        });
    }
}
