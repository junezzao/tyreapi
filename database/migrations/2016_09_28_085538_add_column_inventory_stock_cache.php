<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnInventoryStockCache extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('inventory_stock_cache', function(Blueprint $table)
        {
            $table->integer('reserved_quantity')->default(0)->after('channel_sku_quantity');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inventory_stock_cache', function(Blueprint $table)
        {
            $table->dropColumn('reserved_quantity');
        });
    }
}
