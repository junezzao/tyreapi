<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSkuQuantityLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sku_quantity_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sku_id');
            $table->foreign('sku_id')->references('sku_id')->on('sku');
            $table->integer('quantity');
            $table->integer('quantity_log_app_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('sku_quantity_log');
    }
}
