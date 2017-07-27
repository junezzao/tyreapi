<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnsToOrderItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_items', function ($table) {
            $table->decimal('hw_fee')->default(0.00)->after('tp_item_id');
            $table->decimal('hw_commission')->default(0.00)->after('hw_fee');
            $table->decimal('misc_fee')->default(0.00)->after('hw_commission');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_items', function ($table) {
            $table->dropColumn(['hw_fee', 'hw_commission', 'misc_fee']);
        });
    }
}
