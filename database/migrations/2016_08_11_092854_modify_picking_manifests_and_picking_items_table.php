<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyPickingManifestsAndPickingItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('picking_manifests', function ($table) {

            $table->renameColumn('manifest_id', 'id');
            $table->renameColumn('admin_id', 'user_id');
        });

        Schema::table('picking_items', function ($table) {
            $table->dropForeign('picking_items_sale_id_foreign');
            $table->dropColumn('sale_id');

            $table->renameColumn('item_id', 'id');
            
            $table->integer('order_item_id')->after('channel_sku_id')->unsigned();
            $table->foreign('order_item_id')->references('id')->on('order_items');

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
        Schema::table('picking_manifests', function ($table) {
            $table->renameColumn('id', 'manifest_id');
            $table->renameColumn('user_id', 'admin_id');
        });

        Schema::table('picking_items', function ($table) {
            $table->renameColumn('id', 'item_id');
            
            $table->integer('sale_id')->after('channel_sku_id')->unsigned();
            $table->foreign('sale_id')->references('id')->on('orders');

            $table->dropForeign('picking_items_order_item_id_foreign');
            $table->dropColumn('order_item_id');

        });
    }
}
