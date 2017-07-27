<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveDeliveryItemsGtoColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('delivery_orders_items', function ($table) {
                $table->dropColumn(['picking_status','picked']);
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('delivery_orders_items', function ($table) {
                $table->integer('picked')->after('status')->unsigned()->default(0);
                $table->string('picking_status')->default('Picking')->after('status');
            });
    }
}
