<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DeliveryOrdersItemsAddPickingStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('delivery_orders_items',function(Blueprint $table){
            $table->string('picking_status')->default('Picking')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery_orders_items',function(Blueprint $table){
            $table->dropColumn('picking_status');
        });
    }
}
