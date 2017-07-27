<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PickingItemsStockOutChanges extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('picking_items',function(Blueprint $table)
        {
            $table->dropForeign('picking_items_order_item_id_foreign');
        });
        
        Schema::table('picking_items',function(Blueprint $table)
        {
            $table->renameColumn('order_item_id','item_id');
            $table->string('item_type')->after('channel_sku_id')->default('OrderItem');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('picking_items',function(Blueprint $table)
        {
            $table->dropColumn('item_type');
            $table->renameColumn('item_id','order_item_id');
        });

        Schema::table('picking_items',function(Blueprint $table)
        {   
            $table->foreign('order_item_id')->references('id')->on('order_items');
        });
    }
}
