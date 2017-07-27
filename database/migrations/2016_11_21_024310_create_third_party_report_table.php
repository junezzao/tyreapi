<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateThirdPartyReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('third_party_report', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('channel_type_id')->unsigned();
            $table->foreign('channel_type_id')->references('id')->on('channel_types')->onDelete('cascade');

            $table->string('tp_order_code'); // Lazada/Zalora uses this field and not the value stored in tp_order_id, 11Street/Lelong stores the same value, only Shopify has the long ID on the tp_order_id column
            $table->integer('order_id')->unsigned()->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            $table->string('tp_item_id');
            $table->integer('order_item_id')->unsigned()->unique()->nullable();
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade');

            $table->string('hubwire_sku')->nullable();
            $table->integer('product_id')->nullable();
            $table->integer('quantity');
            $table->string('item_status');
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('sold_price', 10, 2);
            $table->decimal('channel_fees', 10, 2);
            $table->decimal('net_payout', 10, 2);
            $table->date('payment_date')->nullable();
            $table->string('status');
            // $table->text('remarks')->nullable();

            $table->timestamps();
            $table->integer('last_attended_by')->unsigned()->nullable();
            $table->foreign('last_attended_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('third_party_report');
    }
}
