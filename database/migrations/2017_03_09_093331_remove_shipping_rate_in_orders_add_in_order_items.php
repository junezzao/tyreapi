<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveShippingRateInOrdersAddInOrderItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function ($table) {
            $table->dropColumn('merchant_shipping_fee');
        });
        Schema::table('order_items', function($table) {
            $table->decimal('merchant_shipping_fee',8,2)->after('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function ($table) {
            $table->decimal('merchant_shipping_fee',8,2)->after('shipping_notification_date');
        });
        Schema::table('order_items', function ($table) {
            $table->dropColumn('merchant_shipping_fee');
        });
    }
}
