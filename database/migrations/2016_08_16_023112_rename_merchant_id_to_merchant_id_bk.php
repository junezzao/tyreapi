<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameMerchantIdToMerchantIdBk extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channels', function ($table) {
            $table->renameColumn('merchant_id', 'merchant_id_bk');
        });
        Schema::table('orders', function ($table) {
            $table->renameColumn('merchant_id', 'merchant_id_bk');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channels', function ($table) {
            $table->renameColumn('merchant_id_bk', 'merchant_id');
        });
        Schema::table('orders', function ($table) {
            $table->renameColumn('merchant_id_bk', 'merchant_id');
        });
    }
}
