<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameColumnsInDoAndDoItemsTable extends Migration
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
            $table->renameColumn('do_id', 'id');
        });

        Schema::table('delivery_orders_items', function (Blueprint $table) {
            $table->renameColumn('item_id', 'id');
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
            $table->renameColumn('id', 'do_id');
        });

        Schema::table('delivery_orders_items', function (Blueprint $table) {
            $table->renameColumn('id', 'item_id');
        });
    }
}
