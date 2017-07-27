<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePurchaseBatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_batches', 'channel_id')) {
                $table->integer('channel_id')->unsigned()->nullable()->after('merchant_id');
                $table->foreign('channel_id')->references('id')->on('channels');
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
        Schema::table('purchase_batches', function (Blueprint $table) {
            $table->dropColumn('channel_id');
        });
    }
}
