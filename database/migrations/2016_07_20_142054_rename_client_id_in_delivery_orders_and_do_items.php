<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameClientIdInDeliveryOrdersAndDoItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropIndex('delivery_orders_client_id_index');
            $table->renameColumn('client_id', 'merchant_id');
            $table->index('merchant_id');
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
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropIndex('delivery_orders_merchant_id_index');
            $table->renameColumn('merchant_id', 'client_id');
            $table->index('client_id');
        });
    }
}
