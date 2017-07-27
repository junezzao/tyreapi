<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersRelatedTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // orders table
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);
            $table->decimal('shipping_fee', 10, 2)->default(0.00);
            $table->decimal('cart_discount', 10, 2)->default(0.00);
            $table->decimal('total_discount', 10, 2)->default(0.00);
            $table->decimal('total_tax', 10, 2)->default(0.00);
            $table->string('currency', 4);
            $table->string('forex_rate');
            $table->integer('merchant_id');
            $table->integer('channel_id');
            $table->bigInteger('tp_order_id')->nullable();
            $table->string('tp_order_code')->nullable();
            $table->string('tp_source');
            //$table->enum('status', ['Failed', 'Pending', 'New', 'Picking', 'Packing', 'Ready To Ship', 'Shipped', 'Completed']);
            $table->tinyInteger('status'); // to set lookup values in config/model
            $table->boolean('partially_fulfilled')->default(0);
            $table->boolean('cancelled_status')->default(0);
            $table->boolean('paid_status')->default(0);
            $table->string('payment_type');
            $table->dateTime('paid_date')->nullable();
            $table->integer('member_id');
            $table->string('shipping_recipient');
            $table->string('shipping_phone');
            $table->string('shipping_street_1');
            $table->string('shipping_street_2')->nullable();
            $table->string('shipping_postcode', 10)->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_country')->nullable();
            $table->string('shipping_provider')->nullable();
            $table->string('consignment_no')->nullable();
            $table->dateTime('shipping_notification_date')->nullable();
            $table->boolean('reserved')->default(0);
            $table->decimal('refunded_amount', 10, 2)->default(0.00)->nullable();
            $table->text('tp_extra')->nullable();
            $table->timestamps();

            $table->index(['id', 'merchant_id', 'channel_id']);
            //$table->foreign('member_id')->references('id')->on('members');
            //$table->foreign('merchant_id')->references('id')->on('merchants');
            //$table->foreign('channel_id')->references('id')->on('channels');
        });

        // order_items table
        Schema::create('order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->unsigned();
            $table->string('ref_id');
            $table->string('ref_type');
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->decimal('sale_price', 10, 2)->default(0.00);
            $table->decimal('sold_price', 10, 2)->default(0.00);
            $table->boolean('tax_inclusive')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->integer('original_quantity');
            $table->integer('quantity');
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('tp_discount', 10, 2)->default(0.00);
            $table->decimal('weighted_cart_discount', 10, 2)->default(0.00);
            $table->tinyInteger('fulfilled_channel')->nullable();
            $table->string('tp_item_id')->nullable();
            $table->timestamps();

            $table->index(['id']);
            $table->foreign('order_id')->references('id')->on('orders');
        });

        // order_history table
        Schema::create('order_history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->unsigned();
            $table->string('description');
            $table->string('ref_type');
            $table->integer('ref_id');
            $table->integer('user_id');
            $table->timestamps();

            $table->index(['id']);
            $table->foreign('order_id')->references('id')->on('orders');
        });

        // order_invoice
        Schema::create('order_invoice', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id')->unsigned();
            $table->string('invoice_no')->unique();
            $table->string('type');
            $table->string('merchant_id');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // drop tables
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::drop('orders');
        Schema::drop('order_items');
        Schema::drop('order_history');
        Schema::drop('order_invoice');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
